<?php

namespace App\Services\Ledger;

use App\Enums\FundSource;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Support\Money;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Turns the voucher builder's form into a draft for the posting rules, applying the form's own rules first:
 * a budget line fills its project and account, an account fixed to USD or SYP fixes the currency, a pound line takes
 * its rate (a payment with none takes the project's average rate), the USD amount follows from the original amount and
 * the rate, and a line of a project carries the project's donor, restriction and donor-currency amount (what the reports
 * read). Blank rows are ignored. It never posts and never throws on bad input: it reports problems instead.
 */
class VoucherDraftBuilder
{
    public function __construct(private readonly ProjectAverageRate $averages) {}

    /**
     * @param  array<int, array<string, string>>  $rows  the form lines: account, currency, side, amount, rate, project,
     *                                                   budget_line, fund_source, contact, center, lira_type
     */
    public function build(string $type, string $date, string $description, string $requestId, array $rows, int $preparedBy): BuiltVoucher
    {
        $problems = [];

        $voucherType = VoucherType::tryFrom($type);
        if ($voucherType === null || $voucherType === VoucherType::Opening) {
            $problems[] = __('ledger.builder.bad_type');
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        if (! $day instanceof CarbonImmutable || $day->format('Y-m-d') !== $date) {
            $day = null;
            $problems[] = __('ledger.builder.bad_date');
        }

        $request = null;
        if (trim($requestId) !== '') {
            if (ctype_digit(trim($requestId))) {
                $request = (int) trim($requestId);
            } else {
                $problems[] = __('ledger.builder.bad_request');
            }
        }

        $accounts = GlAccount::query()->whereIn('code', collect($rows)->pluck('account')->filter()->unique()->all())->get()->keyBy('code');
        $budgetLines = GlBudgetLine::query()->whereIn('key', collect($rows)->pluck('budget_line')->filter()->unique()->all())->get()->keyBy('key');
        $projectCodes = collect($rows)->pluck('project')->merge($budgetLines->pluck('project_code'))->filter()->unique()->all();
        $projects = GlProject::query()->whereIn('code', $projectCodes)->get()->keyBy('code');

        $lines = [];
        $built = [];

        foreach ($rows as $index => $row) {
            $row += ['account' => '', 'currency' => 'USD', 'side' => 'debit', 'amount' => '', 'rate' => '', 'project' => '', 'budget_line' => '', 'fund_source' => '', 'contact' => '', 'center' => '', 'lira_type' => ''];
            $number = $index + 1;

            if (trim($row['account']) === '' && trim($row['amount']) === '' && trim($row['budget_line']) === '') {
                continue;
            }

            $budgetLine = $row['budget_line'] !== '' ? $budgetLines->get($row['budget_line']) : null;
            $accountCode = trim($row['account']);
            $projectCode = trim($row['project']);
            if ($budgetLine !== null) {
                $accountCode = $budgetLine->account_code;
                $projectCode = $budgetLine->project_code;
            }
            $account = $accounts->get($accountCode) ?? ($accountCode !== '' ? GlAccount::query()->find($accountCode) : null);
            $project = $projectCode !== '' ? ($projects->get($projectCode) ?? GlProject::query()->find($projectCode)) : null;

            $currency = $account !== null && in_array($account->currency, ['USD', 'SYP'], true)
                ? $account->currency
                : (in_array($row['currency'], ['USD', 'SYP'], true) ? $row['currency'] : 'USD');

            $amount = $this->amount($row['amount']);
            if ($amount === null) {
                $problems[] = __('ledger.builder.bad_amount', ['line' => $number]);
                $amount = Money::zero();
            }

            $rate = '1.000000';
            $usd = $amount;
            if ($currency === 'SYP') {
                $rate = $this->rate($row['rate']) ?? ($voucherType === VoucherType::Payment && $projectCode !== '' ? $this->averages->rate($projectCode) : null);

                if ($rate === null || bccomp($rate, '0', 6) <= 0) {
                    $problems[] = __('ledger.builder.needs_rate', ['line' => $number]);
                    $rate = '0.000000';
                    $usd = Money::zero();
                } else {
                    $usd = Money::normalize(bcdiv($amount, $rate, 6));
                }
            }

            $donorAmount = $usd;
            $donorRate = $project === null ? '0.000000' : Money::normalize($project->donor_rate, 6);
            if ($project !== null && $project->donor_currency !== 'USD' && bccomp($donorRate, '0', 6) > 0) {
                $donorAmount = Money::normalize(bcdiv($usd, $donorRate, 6));
            }

            $debit = $row['side'] === 'credit' ? null : $amount;
            $credit = $row['side'] === 'credit' ? $amount : null;

            $lines[] = new LineDraft(
                accountCode: $accountCode,
                currency: $currency,
                debit: $debit,
                credit: $credit,
                rate: $rate,
                debitUsd: $debit === null ? null : $usd,
                creditUsd: $credit === null ? null : $usd,
                projectCode: $projectCode !== '' ? $projectCode : null,
                budgetLineKey: $budgetLine?->key,
                fundSource: FundSource::tryFrom($row['fund_source']),
                contact: trim($row['contact']) !== '' ? trim($row['contact']) : null,
                center: trim($row['center']) !== '' ? trim($row['center']) : null,
                donor: $project?->donor,
                restriction: $project?->restriction,
                liraType: $currency === 'SYP' && in_array($row['lira_type'], ['old', 'new'], true) ? $row['lira_type'] : null,
                donorCurrency: $project?->donor_currency,
                debitDonor: $project !== null && $debit !== null ? $donorAmount : null,
                creditDonor: $project !== null && $credit !== null ? $donorAmount : null,
            );

            $built[$index] = ['account' => $accountCode, 'currency' => $currency, 'project' => $projectCode, 'rate' => $rate, 'usd' => $usd];
        }

        $draft = $voucherType !== null && $voucherType !== VoucherType::Opening && $day !== null
            ? new VoucherDraft(
                type: $voucherType, date: $day, preparedBy: $preparedBy, lines: $lines,
                description: trim($description) !== '' ? trim($description) : null, requestId: $request,
            )
            : null;

        return new BuiltVoucher($draft, $built, $problems);
    }

    /** @return numeric-string|null */
    private function amount(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        if ($clean === '') {
            return Money::zero();
        }

        try {
            return Money::normalize($clean);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** @return numeric-string|null */
    private function rate(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        if ($clean === '') {
            return null;
        }

        try {
            $rate = Money::normalize($clean, 6);
        } catch (InvalidArgumentException) {
            return null;
        }

        return bccomp($rate, '0', 6) > 0 ? $rate : null;
    }
}
