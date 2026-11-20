<?php

namespace App\Services\Excel\Datasets;

use App\Enums\FundSource;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\MicroLoan;
use App\Models\User;
use App\Services\Excel\ExcelDataset;
use App\Services\Excel\ExcelException;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Loans\LoanException;
use App\Services\Loans\MicroLoans;
use App\Services\Treasury\MonthlyRates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Micro loans to and from Excel (finance request of 2026-11-21). Each imported row opens a new loan exactly as the
 * screen does: the beneficiary, the schedule and the disbursement voucher, waiting for approval. The check opens it
 * in a transaction it then undoes, so every rule of the loan and of the voucher is seen before anything is saved.
 * A pound loan with no rate takes the month's rate. The export adds what is still owed and late.
 */
class MicroLoansExcel extends ExcelDataset
{
    public function __construct(
        private readonly MicroLoans $loans,
        private readonly MonthlyRates $monthly,
    ) {}

    public function screen(): string
    {
        return 'payments';
    }

    public function fileName(): string
    {
        return 'micro-loans';
    }

    public function columns(): array
    {
        return ['beneficiary', 'national_number', 'mobile', 'partners', 'project', 'currency', 'loan_amount', 'grant_amount', 'rate', 'date', 'instalments', 'first_due', 'interval', 'cash_account', 'budget_line', 'fund_source', 'request'];
    }

    public function exportColumns(): array
    {
        return [...$this->columns(), 'owed', 'arrears', 'late_payments', 'disbursement_voucher'];
    }

    public function exportRows(array $context): iterable
    {
        $today = CarbonImmutable::today();

        foreach (MicroLoan::query()->with('instalments')->orderBy('project_code')->orderBy('beneficiary')->get() as $loan) {
            $first = $loan->instalments->first();
            $line = $loan->disbursement_voucher_id !== null
                ? DB::table('gl_voucher_lines')->where('voucher_id', $loan->disbursement_voucher_id)->where('account_code', '!=', MicroLoans::LOANS_ACCOUNT)->where('credit_orig', '>', 0)->first(['account_code', 'rate'])
                : null;

            yield [
                $loan->beneficiary, (string) $loan->national_number, (string) $loan->mobile, (string) $loan->partners, $loan->project_code, $loan->currency,
                (float) $loan->loan_amount, (float) $loan->grant_amount, $line !== null && $loan->currency !== 'USD' ? (float) $line->rate : null,
                $loan->created_at?->toDateString(), $loan->instalments->count(), $first?->due_date->toDateString() ?? $loan->first_payment_date?->toDateString(),
                $loan->payment_interval_months, $line?->account_code, '', '', '',
                (float) $loan->owed(), (float) $this->loans->arrears($loan, $today)['amount'], $this->loans->latePayments($loan, $today), (string) $loan->disbursement_voucher_id,
            ];
        }
    }

    public function exampleRows(array $context): array
    {
        $project = GlProject::query()->where('is_general_account', false)->orderBy('code')->first();
        $line = $project !== null ? GlBudgetLine::query()->where('project_code', $project->code)->where('account_code', MicroLoans::GRANTS_ACCOUNT)->first() : null;
        $today = CarbonImmutable::today();

        return [[
            'Beneficiary name', '', '', '', $project?->code ?? 'PROJECT', 'USD', 1000, 200, null, $today->toDateString(), 10,
            $today->addMonthNoOverflow()->toDateString(), 1, '1111', $line?->key ?? '', FundSource::DonorGrant->value, '',
        ]];
    }

    public function referenceSheets(array $context): array
    {
        return [
            __('excel.sheet.projects') => [
                'headings' => [__('excel.col.project'), __('excel.col.name_en')],
                'rows' => GlProject::query()->where('is_general_account', false)->orderBy('code')->get(['code', 'name'])->map(fn (GlProject $project): array => [$project->code, (string) $project->name])->all(),
            ],
            __('excel.sheet.budget_lines') => [
                'headings' => [__('excel.col.budget_line'), __('excel.col.project'), __('excel.col.account')],
                'rows' => GlBudgetLine::query()->orderBy('key')->get(['key', 'project_code', 'account_code'])->map(fn (GlBudgetLine $line): array => [$line->key, $line->project_code, $line->account_code])->all(),
            ],
            __('excel.sheet.cash_accounts') => [
                'headings' => [__('excel.col.account'), __('excel.col.account_name'), __('excel.col.currency')],
                'rows' => GlAccount::query()->where('is_cash', true)->where('is_active', true)->whereIn('currency', ['USD', 'SYP'])->orderBy('code')->get()
                    ->map(fn (GlAccount $account): array => [$account->code, $account->name_en, (string) $account->currency])->all(),
            ],
            __('excel.sheet.fund_sources') => [
                'headings' => [__('excel.col.fund_source')],
                'rows' => array_map(fn (FundSource $source): array => [$source->value], FundSource::cases()),
            ],
        ];
    }

    protected function check(array $cells, array $before, User $user, array $context): array
    {
        $problems = [];

        foreach (['date', 'first_due'] as $column) {
            if (! $this->isDay($cells[$column])) {
                $problems[] = __('excel.bad_day').' ('.__('excel.col.'.$column).')';
            }
        }

        if ($cells['fund_source'] !== '' && FundSource::tryFrom($cells['fund_source']) === null) {
            $problems[] = __('excel.bad_fund_source');
        }

        if ($problems !== []) {
            return ['action' => 'new', 'problems' => $problems];
        }

        // Open it for real, then undo: every rule of the loan and of its voucher is checked.
        DB::beginTransaction();

        try {
            $this->open($cells, $user);
        } catch (LoanException $refused) {
            $problems = $refused->problems;
        } catch (LedgerRuleException $refused) {
            $problems = array_map(fn ($violation): string => (string) $violation->message, $refused->violations);
        } finally {
            DB::rollBack();
        }

        return ['action' => 'new', 'problems' => $problems];
    }

    protected function saveRow(array $cells, User $user, array $context): void
    {
        try {
            $this->open($cells, $user);
        } catch (LoanException $refused) {
            throw new ExcelException($refused->problems);
        } catch (LedgerRuleException $refused) {
            throw new ExcelException(array_map(fn ($violation): string => (string) $violation->message, $refused->violations));
        }
    }

    /**
     * @param  array<string, string>  $cells
     *
     * @throws LoanException
     * @throws LedgerRuleException
     */
    private function open(array $cells, User $user): void
    {
        $currency = strtoupper($cells['currency'] !== '' ? $cells['currency'] : 'USD');
        $date = CarbonImmutable::parse($cells['date']);
        $rate = $cells['rate'];

        if ($currency !== 'USD' && $rate === '') {
            $rate = (string) ($this->monthly->forDay($currency, $date)['rate'] ?? '');
        }

        $this->loans->open(
            $cells['beneficiary'], $cells['project'], $cells['loan_amount'], $cells['grant_amount'], $currency, $rate, $date,
            (int) ($cells['instalments'] !== '' ? $cells['instalments'] : 1), CarbonImmutable::parse($cells['first_due']), $cells['cash_account'], $cells['budget_line'],
            FundSource::tryFrom($cells['fund_source']) ?? FundSource::DonorGrant, (int) $user->getAuthIdentifier(),
            ctype_digit($cells['request']) ? (int) $cells['request'] : null,
            $cells['national_number'], $cells['mobile'], $cells['partners'], (int) ($cells['interval'] !== '' ? $cells['interval'] : 1),
        );
    }

    private function isDay(string $text): bool
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text;
    }
}
