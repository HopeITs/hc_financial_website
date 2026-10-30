<?php

namespace App\Services\Ledger;

use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Settling an advance (F-12): the bills a person brings back for an advance (account 1201, by contact) are booked on budget
 * lines (debit the expense, centered on the project's city, credit 1201), and the difference with what is still open is settled in cash: money handed back
 * is a receipt (CRV, debit cash, credit 1201), money paid out to the person when the bills are more than the advance is a
 * payment (CPV, debit 1201, credit cash). The bills are one journal voucher; the cash difference, when there is one, is
 * a second voucher, tagged with the project of the first bill. All are saved pending, in one transaction. Dollars only: the advances report is in dollars.
 */
class AdvanceSettlement
{
    public function __construct(
        private readonly VoucherDraftBuilder $builder,
        private readonly OperationEntry $entry,
        private readonly PostVoucher $posting,
        private readonly FollowUpReports $reports,
    ) {}

    /** @return list<array{person: string, open: numeric-string}> the people with an open advance (more than zero), from the ledger */
    public function openAdvances(): array
    {
        $rows = $this->reports->run('advances', new FollowUpFilters)['rows'];

        return array_values(array_map(
            fn (array $row) => ['person' => (string) $row['person'], 'open' => Money::normalize($row['open'])],
            array_filter($rows, fn (array $row) => bccomp(Money::normalize($row['open']), '0', 2) > 0),
        ));
    }

    /**
     * The vouchers a settlement makes, or the problems that stop it.
     *
     * @param  array<int, array{budget_line: string, amount: string, note?: string}>  $bills
     * @return array{drafts: list<VoucherDraft>, problems: array<int, string>, open: string, billed: string, difference: string}
     */
    public function build(string $person, array $bills, string $cash, string $date, string $description, int $preparedBy): array
    {
        $zero = Money::zero();
        $fail = fn (string $problem, string $open = '0.00', string $billed = '0.00', string $difference = '0.00') => ['drafts' => [], 'problems' => [$problem], 'open' => Money::normalize($open), 'billed' => Money::normalize($billed), 'difference' => Money::normalize($difference)];

        $open = collect($this->openAdvances())->firstWhere('person', $person)['open'] ?? null;
        if ($open === null) {
            return $fail(__('ledger.settlement.no_advance'));
        }

        $rows = [];
        $billed = $zero;
        $problems = [];
        $project = '';

        foreach ($bills as $index => $bill) {
            $number = $index + 1;
            $key = trim($bill['budget_line']);
            $amountText = str_replace([',', ' '], '', trim($bill['amount']));

            if ($key === '' && $amountText === '') {
                continue;
            }

            $line = $key !== '' ? GlBudgetLine::query()->whereKey($key)->first() : null;
            if ($line === null) {
                $problems[] = __('ledger.settlement.bad_line', ['row' => $number]);

                continue;
            }

            try {
                $amount = Money::normalize($amountText);
            } catch (InvalidArgumentException) {
                $amount = $zero;
            }
            if (bccomp($amount, '0', 2) <= 0) {
                $problems[] = __('ledger.settlement.bad_amount', ['row' => $number]);

                continue;
            }

            $billed = bcadd($billed, $amount, 2);
            $project = $project !== '' ? $project : $line->project_code;
            $common = ['currency' => 'USD', 'amount' => $amount, 'rate' => '', 'project' => $line->project_code, 'fund_source' => 'donor_grant', 'contact' => $person];
            $center = (string) GlProject::query()->whereKey($line->project_code)->value('city');
            $rows[] = $common + ['account' => $line->account_code, 'side' => 'debit', 'budget_line' => $key, 'center' => $center];
            $rows[] = $common + ['account' => OperationEntry::ADVANCE_ACCOUNT, 'side' => 'credit', 'budget_line' => ''];
        }

        if ($problems === [] && $rows === []) {
            $problems[] = __('ledger.settlement.no_bills');
        }

        $difference = bcsub(Money::normalize($open), $billed, 2);

        if ($problems !== []) {
            return ['drafts' => [], 'problems' => $problems, 'open' => $open, 'billed' => $billed, 'difference' => $difference];
        }

        $text = $description !== '' ? $description : __('ledger.settlement.description', ['person' => $person]);
        $built = $this->builder->build('JV', $date, $text, '', $rows, $preparedBy);
        if ($built->draft === null || $built->problems !== []) {
            return ['drafts' => [], 'problems' => array_map('strval', $built->problems !== [] ? $built->problems : [__('ledger.builder.no_lines')]), 'open' => $open, 'billed' => $billed, 'difference' => $difference];
        }

        $drafts = [$built->draft];

        if (bccomp($difference, '0', 2) !== 0) {
            $cashBuilt = $this->entry->build([
                'kind' => bccomp($difference, '0', 2) > 0 ? 'repayment' : 'advance',
                'date' => $date, 'cash' => $cash, 'project' => $project, 'amount' => Money::abs($difference), 'contact' => $person, 'description' => $text,
            ], $preparedBy);

            if ($cashBuilt->draft === null || $cashBuilt->problems !== []) {
                return ['drafts' => [], 'problems' => array_map('strval', $cashBuilt->problems !== [] ? $cashBuilt->problems : [__('ledger.builder.no_lines')]), 'open' => $open, 'billed' => $billed, 'difference' => $difference];
            }

            $drafts[] = $cashBuilt->draft;
        }

        return ['drafts' => $drafts, 'problems' => [], 'open' => $open, 'billed' => $billed, 'difference' => $difference];
    }

    /**
     * Checks a settlement against the posting rules without saving anything.
     *
     * @param  list<VoucherDraft>  $drafts
     * @return list<string>
     */
    public function violations(array $drafts): array
    {
        $messages = [];

        foreach ($drafts as $draft) {
            foreach ($this->posting->check($draft) as $violation) {
                $messages[] = $violation->message;
            }
        }

        return $messages;
    }

    /**
     * Saves every voucher of a settlement, pending, in one transaction.
     *
     * @param  list<VoucherDraft>  $drafts
     * @return list<string> the voucher numbers
     *
     * @throws LedgerRuleException
     */
    public function post(array $drafts): array
    {
        return DB::transaction(fn () => array_map(fn (VoucherDraft $draft) => $this->posting->post($draft)->id, $drafts));
    }
}
