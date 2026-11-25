<?php

namespace App\Services\Entries;

use App\Enums\AccountClass;
use App\Enums\AccountLevel;
use App\Enums\FundSource;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\LedgerRequest;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\Currencies;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherAttachments;
use App\Services\Ledger\VoucherDraft;
use App\Services\Ledger\VoucherWarnings;
use App\Services\Projects\ProjectAccess;
use App\Services\Treasury\MonthlyRates;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * The payment and receipt vouchers (finance design of 2026-11-19), built the way accounting programs build them: a
 * header (date, the cash or bank account, who was paid or who paid, the invoice number, the rate) and a grid of
 * lines. Each line names a project and its budget line, which brings the account of the chart; an organisation
 * expense with no project names the account itself. The cash line is written by the program, last, so the grid's
 * line numbers are the voucher's. Pounds take the month's rate by default (MonthlyRates) and can be given another.
 * A payment above the limit needs an approved request, whose lines it can be filled from. It waits for approval.
 */
class CashVouchers
{
    public const PAYMENT = 'payment';

    public const RECEIPT = 'receipt';

    /** A budget line this used up or more after the voucher is worth a warning. */
    private const BUDGET_WARNING_SHARE = '0.80';

    public function __construct(
        private readonly PostVoucher $posting,
        private readonly MonthlyRates $monthly,
        private readonly Currencies $currencies,
        private readonly VoucherWarnings $warnings,
        private readonly VoucherAttachments $attachments,
    ) {}

    /** @return Collection<string, GlAccount> the active cash and bank accounts, by code */
    public function cashAccounts(): Collection
    {
        return GlAccount::query()->where('is_cash', true)->where('is_active', true)->where('level', AccountLevel::Posting->value)
            ->orderBy('code')->get()->keyBy('code');
    }

    /**
     * The accounts a line can use, from the chart: active posting accounts that are not cash and not net assets
     * (which are computed). A payment lists the expenses first, a receipt the income.
     *
     * @return Collection<string, GlAccount>
     */
    public function lineAccounts(string $kind): Collection
    {
        $first = $kind === self::RECEIPT ? AccountClass::Income : AccountClass::Expenses;

        return GlAccount::query()
            ->where('is_cash', false)->where('is_active', true)->where('level', AccountLevel::Posting->value)
            ->where('class', '!=', AccountClass::NetAssets->value)
            ->orderBy('code')->get()
            ->sortBy(fn (GlAccount $account): string => ($account->class === $first ? '0' : '1').$account->code)
            ->keyBy('code');
    }

    /** @return array<string, string> account code => its place in the chart, "5303 · Supplies › Programme costs" */
    public function accountLabels(Collection $accounts): array
    {
        $arabic = app()->getLocale() === 'ar';
        $parents = GlAccount::query()->whereIn('code', $accounts->pluck('parent_code')->filter()->unique()->values())->get()->keyBy('code');
        $name = fn (GlAccount $account): string => $arabic ? $account->name_ar : $account->name_en;

        return $accounts->map(fn (GlAccount $account): string => $account->code.' · '.$name($account)
            .($account->parent_code !== null && $parents->has($account->parent_code) ? ' ‹ '.$name($parents[$account->parent_code]) : ''))->all();
    }

    /** @return Collection<string, GlProject> the projects a line can name, by code (the old general accounts are not projects) */
    public function projects(): Collection
    {
        // A person limited to their projects picks only those (finance request of 2026-11-30).
        $user = auth()->user();
        $limit = $user instanceof User ? app(ProjectAccess::class)->limitedTo($user) : null;

        return GlProject::query()->where('is_general_account', false)
            ->when($limit !== null, fn ($query) => $query->whereIn('code', $limit ?? []))
            ->orderBy('code')->get()->keyBy('code');
    }

    /** @return Collection<int, GlBudgetLine> */
    public function budgetLines(string $project): Collection
    {
        return GlBudgetLine::query()->where('project_code', $project)->orderBy('name')->get();
    }

    /**
     * The rate a voucher in this currency takes by default on that day: the month's (or the month before's).
     *
     * @return array{rate: string, month: string, provisional: bool}|null
     */
    public function defaultRate(string $currency, CarbonImmutable $day): ?array
    {
        return $currency === Currencies::BASE ? null : $this->monthly->forDay($currency, $day);
    }

    /** @return Collection<int, LedgerRequest> the approved requests (a closed one has the status closed), newest first */
    public function openRequests(): Collection
    {
        return LedgerRequest::query()->where('status', LedgerRequest::APPROVED)->orderByDesc('id')->limit(100)->get();
    }

    /**
     * A request's lines as voucher lines, in the voucher's currency: the dollars of each line taken at the voucher's rate
     * when the line is in another currency.
     *
     * @return list<array{project: string, budget_line: string, account: string, description: string, amount: string}>
     */
    public function linesFromRequest(int $requestId, string $currency, string $rate): array
    {
        $request = LedgerRequest::query()->with('lines.budgetLine')->find($requestId);

        if ($request === null) {
            return [];
        }

        $usable = bccomp($this->number($rate, 6) ?? '0', '0', 6) === 1;

        return array_values($request->lines->map(fn ($line): array => [
            'project' => (string) ($line->budgetLine?->project_code ?? $request->project_code),
            'budget_line' => (string) $line->budget_line_key,
            'account' => (string) ($line->budgetLine?->account_code ?? ''),
            'description' => (string) ($line->note ?? ''),
            'amount' => match (true) {
                $line->currency === $currency => (string) $line->amount,
                $currency === Currencies::BASE => (string) $line->amount_usd,
                $usable => $this->currencies->fromUsd($currency, (string) $line->amount_usd, (string) $rate),
                default => '',
            },
        ])->all());
    }

    /**
     * Builds the voucher from the form, without saving it: the draft, what stops it, the soft warnings and the totals.
     *
     * @param  array{date: string, account: string, rate: string, party: string, reference: string, center: string, description: string, request: string}  $header
     * @param  list<array{project?: string, budget_line?: string, account?: string, description?: string, amount?: string}>  $rows
     * @return array{draft: VoucherDraft|null, problems: list<string>, warnings: list<string>, total: string, total_usd: string, currency: string|null}
     */
    public function build(string $kind, array $header, array $rows): array
    {
        $problems = [];
        $cash = $this->cashAccounts()->get($header['account']);
        $currency = $cash?->currency;
        $date = $this->day($header['date']);
        $rate = $currency === Currencies::BASE ? '1.000000' : $this->number($header['rate'], 6);

        if ($date === null) {
            $problems[] = __('entries.bad_date');
        }

        if ($cash === null) {
            $problems[] = __($kind === self::PAYMENT ? 'entries.choose_paid_from' : 'entries.choose_received_in');
        } elseif ($rate === null || bccomp($rate, '0', 6) !== 1) {
            $problems[] = __('entries.needs_rate', ['currency' => $currency]);
        }

        if (trim($header['party']) === '') {
            $problems[] = __($kind === self::PAYMENT ? 'entries.needs_payee' : 'entries.needs_payer');
        }

        $accounts = $this->lineAccounts($kind);
        $projects = $this->projects();
        $budgetLines = GlBudgetLine::query()->whereIn('key', collect($rows)->pluck('budget_line')->filter()->unique()->values())->get()->keyBy('key');
        $lines = [];
        $total = Money::zero();
        $totalUsd = Money::zero();
        $number = 0;

        foreach ($rows as $row) {
            $amountText = trim((string) ($row['amount'] ?? ''));
            $blank = $amountText === '' && trim((string) ($row['account'] ?? '')) === '' && trim((string) ($row['budget_line'] ?? '')) === '';
            if ($blank) {
                continue;
            }

            $number++;
            $budgetLine = $budgetLines->get((string) ($row['budget_line'] ?? ''));
            $projectCode = trim((string) ($row['project'] ?? '')) ?: $budgetLine?->project_code;
            $accountCode = $budgetLine?->account_code ?? trim((string) ($row['account'] ?? ''));
            $amount = $this->number($amountText, 2);

            if ($amount === null || bccomp($amount, '0', 2) !== 1) {
                $problems[] = __('entries.line_amount', ['line' => $number]);
            }

            if (! $accounts->has($accountCode)) {
                $problems[] = __('entries.line_account', ['line' => $number]);
            }

            if ($projectCode !== null && ! $projects->has($projectCode)) {
                $problems[] = __('entries.line_project', ['line' => $number]);
            }

            if ($budgetLine !== null && $projectCode !== $budgetLine->project_code) {
                $problems[] = __('entries.line_budget_project', ['line' => $number]);
            }

            if ($amount === null || $rate === null || $currency === null || ! $accounts->has($accountCode)) {
                continue;
            }

            $usd = $this->currencies->toUsd($currency, $amount, $rate);
            $total = bcadd($total, $amount, 2);
            $totalUsd = bcadd($totalUsd, $usd, 2);
            $lines[] = $this->line($kind, $accounts[$accountCode], $currency, $amount, $rate, $usd, $projectCode !== null ? $projects->get($projectCode) : null, $budgetLine?->key, $header, trim((string) ($row['description'] ?? '')));
        }

        if ($number === 0) {
            $problems[] = __('entries.needs_lines');
        }

        if ($problems !== [] || $cash === null || $date === null || $rate === null) {
            return ['draft' => null, 'problems' => $problems, 'warnings' => [], 'total' => $total, 'total_usd' => $totalUsd, 'currency' => $currency];
        }

        $lines[] = $this->cashLine($kind, $cash, $total, $rate, $totalUsd, $header);
        $request = $kind === self::PAYMENT && ctype_digit($header['request']) ? (int) $header['request'] : null;

        $draft = new VoucherDraft(
            type: $kind === self::PAYMENT ? VoucherType::Payment : VoucherType::Receipt,
            date: $date,
            preparedBy: 0,
            lines: $lines,
            description: $this->description($kind, $header),
            requestId: $request,
        );

        $problems = array_map(fn (Violation $violation): string => $violation->message, $this->posting->check($draft));

        return [
            'draft' => $problems === [] ? $draft : null,
            'problems' => $problems,
            'warnings' => [...$this->warnings->for($draft), ...$this->budgetUse($draft)],
            'total' => $total,
            'total_usd' => $totalUsd,
            'currency' => $currency,
        ];
    }

    /**
     * Saves the voucher as pending approval, with its files.
     *
     * @param  array{date: string, account: string, rate: string, party: string, reference: string, center: string, description: string, request: string}  $header
     * @param  list<array<string, string>>  $rows
     * @param  iterable<TemporaryUploadedFile>  $files
     *
     * @throws EntryException
     */
    public function save(string $kind, array $header, array $rows, iterable $files, User $by): Voucher
    {
        $built = $this->build($kind, $header, $rows);

        if ($built['draft'] === null) {
            throw new EntryException($built['problems']);
        }

        $draft = $built['draft'];
        $draft = new VoucherDraft(
            type: $draft->type, date: $draft->date, preparedBy: (int) $by->getAuthIdentifier(), lines: $draft->lines,
            description: $draft->description, requestId: $draft->requestId,
        );
        $stored = [];

        try {
            return DB::transaction(function () use ($draft, $files, $by, &$stored): Voucher {
                $voucher = $this->posting->post($draft);
                $stored = $this->attachments->store($voucher->id, $files, (int) $by->getAuthIdentifier());

                return $voucher;
            });
        } catch (LedgerRuleException $refused) {
            throw new EntryException(array_map(fn (Violation $violation): string => $violation->message, $refused->violations));
        } catch (Throwable $failed) {
            $this->attachments->forget($stored);

            throw $failed;
        }
    }

    /** @param  array{party: string, reference: string, center: string}  $header */
    private function line(string $kind, GlAccount $account, string $currency, string $amount, string $rate, string $usd, ?GlProject $project, ?string $budgetLine, array $header, string $description): LineDraft
    {
        $tags = (array) $account->required_tags;
        $payment = $kind === self::PAYMENT;
        $donorAmount = $project !== null ? $this->donorAmount($usd, $project) : null;

        return new LineDraft(
            accountCode: $account->code,
            currency: $currency,
            debit: $payment ? $amount : null,
            credit: $payment ? null : $amount,
            rate: $rate,
            debitUsd: $payment ? $usd : null,
            creditUsd: $payment ? null : $usd,
            projectCode: $project?->code,
            budgetLineKey: $budgetLine,
            fundSource: $this->fundSource($kind, $project, $tags),
            contact: trim($header['party']) ?: null,
            center: in_array('Center', $tags, true) ? (trim($header['center']) ?: null) : null,
            donor: $project?->donor,
            restriction: $project?->restriction,
            reason: $description !== '' ? $description : null,
            donorCurrency: $project?->donor_currency,
            debitDonor: $payment ? $donorAmount : null,
            creditDonor: $payment ? null : $donorAmount,
        );
    }

    /** @param  array{party: string, center: string}  $header */
    private function cashLine(string $kind, GlAccount $cash, string $total, string $rate, string $usd, array $header): LineDraft
    {
        $tags = (array) $cash->required_tags;
        $payment = $kind === self::PAYMENT;
        $company = $cash->code === (string) config('ledger.transfer_company_account');

        return new LineDraft(
            accountCode: $cash->code,
            currency: (string) $cash->currency,
            debit: $payment ? null : $total,
            credit: $payment ? $total : null,
            rate: $rate,
            debitUsd: $payment ? null : $usd,
            creditUsd: $payment ? $usd : null,
            contact: $company || in_array('Contact', $tags, true) ? (trim($header['party']) ?: (string) config('ledger.transfer_company_contact')) : null,
            center: in_array('Center', $tags, true) ? (trim($header['center']) ?: null) : null,
        );
    }

    /** @param  list<string>  $tags */
    private function fundSource(string $kind, ?GlProject $project, array $tags): ?FundSource
    {
        if ($project === null) {
            return in_array('Fund source', $tags, true) ? ($kind === self::RECEIPT ? FundSource::OwnIncome : FundSource::ChcOwnFunds) : null;
        }

        return $project->donor !== null && $project->donor !== '' ? FundSource::DonorGrant : FundSource::ChcOwnFunds;
    }

    /** @return numeric-string|null the dollars in the project's donor currency, at its donor rate */
    private function donorAmount(string $usd, GlProject $project): ?string
    {
        $rate = Money::normalize($project->donor_rate ?? '0', 6);

        if ($project->donor_currency === null || $project->donor_currency === Currencies::BASE || bccomp($rate, '0', 6) !== 1) {
            return $usd;
        }

        return Money::normalize(bcdiv($usd, $rate, 6));
    }

    /** @param  array{party: string, reference: string, description: string}  $header */
    private function description(string $kind, array $header): string
    {
        if (trim($header['description']) !== '') {
            return trim($header['description']);
        }

        return __($kind === self::PAYMENT ? 'entries.payment_description' : 'entries.receipt_description', ['party' => trim($header['party'])])
            .(trim($header['reference']) !== '' ? ' · '.__('entries.reference_short', ['reference' => trim($header['reference'])]) : '');
    }

    /**
     * A budget line that this voucher brings to 80% of its budget or more (beyond 100% the voucher builder's own
     * warning already says how much is left).
     *
     * @return list<string>
     */
    private function budgetUse(VoucherDraft $draft): array
    {
        $adding = [];
        foreach ($draft->lines as $line) {
            if ($line->budgetLineKey !== null) {
                $adding[$line->budgetLineKey] = bcadd($adding[$line->budgetLineKey] ?? '0.00', bcsub($line->debitUsd, $line->creditUsd, 2), 2);
            }
        }

        $adding = array_filter($adding, fn (string $usd): bool => bccomp($usd, '0', 2) === 1);
        if ($adding === []) {
            return [];
        }

        $spent = DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->where('v.status', VoucherStatus::Approved->value)
            ->whereNull('v.deleted_at')
            ->whereIn('l.budget_line_key', array_map('strval', array_keys($adding)))
            ->groupBy('l.budget_line_key')
            ->selectRaw('l.budget_line_key as line_key, sum(l.debit_usd) - sum(l.credit_usd) as usd')
            ->pluck('usd', 'line_key');
        $warnings = [];

        foreach (GlBudgetLine::query()->whereIn('key', array_map('strval', array_keys($adding)))->with('project')->get() as $budgetLine) {
            $budgetUsd = $this->budgetInUsd($budgetLine);
            if ($budgetUsd === null || bccomp($budgetUsd, '0', 2) !== 1) {
                continue;
            }

            $after = bcadd(Money::normalize(number_format((float) ($spent[$budgetLine->key] ?? 0), 2, '.', '')), $adding[$budgetLine->key], 2);
            $share = bcdiv($after, $budgetUsd, 4);

            if (bccomp($share, self::BUDGET_WARNING_SHARE, 4) >= 0 && bccomp($share, '1', 4) <= 0) {
                $warnings[] = __('entries.budget_reached', ['line' => $budgetLine->name, 'project' => $budgetLine->project_code, 'share' => (int) floor((float) bcmul($share, '100', 2))]);
            }
        }

        return $warnings;
    }

    /** @return numeric-string|null */
    private function budgetInUsd(GlBudgetLine $budgetLine): ?string
    {
        $budget = Money::normalize($budgetLine->budget);

        if ($budgetLine->currency === Currencies::BASE) {
            return $budget;
        }

        $project = $budgetLine->project;
        $rate = $project !== null ? Money::normalize($project->donor_rate ?? '0', 6) : '0.000000';

        return $project !== null && $project->donor_currency === $budgetLine->currency && bccomp($rate, '0', 6) === 1
            ? Money::normalize(bcmul($budget, $rate, 6))
            : null;
    }

    private function day(string $text): ?CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text ? $day : null;
    }

    /** @return numeric-string|null */
    private function number(string $text, int $scale): ?string
    {
        if (trim($text) === '') {
            return null;
        }

        try {
            return Money::normalize(str_replace([',', ' '], '', trim($text)), $scale);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
