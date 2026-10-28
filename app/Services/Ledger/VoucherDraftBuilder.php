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
     *                                                   budget_line, fund_source, contact, center, lira_type; a hawala
     *                                                   receipt line also eur_amount, eur_rate, commission; the commission itself
     *                                                   is typed as a 5504 line, the builder adds none
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

        // The rate a hawala receipt was really converted at also converts the project's other dollar lines of this voucher
        // into the donor currency, so the donor-currency view of the project follows the stored euros.
        $hawalaRates = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row['project'] ?? ''));
            if (trim((string) ($row['account'] ?? '')) === (string) config('ledger.transfer_company_account') && trim((string) ($row['eur_amount'] ?? '')) !== '' && $code !== '') {
                $rate = $this->hawalaRate((string) ($row['eur_rate'] ?? ''), $projects->get($code));
                if ($rate !== null) {
                    $hawalaRates[$code] = $rate;
                }
            }
        }

        foreach ($rows as $index => $row) {
            $row += ['account' => '', 'currency' => 'USD', 'side' => 'debit', 'amount' => '', 'rate' => '', 'project' => '', 'budget_line' => '', 'fund_source' => '', 'contact' => '', 'center' => '', 'lira_type' => '', 'eur_amount' => '', 'eur_rate' => '', 'commission' => ''];
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
            $donorRate = $project === null ? '0.000000' : ($hawalaRates[$projectCode] ?? Money::normalize($project->donor_rate, 6));
            if ($project !== null && $project->donor_currency !== 'USD' && bccomp($donorRate, '0', 6) > 0) {
                $donorAmount = Money::normalize(bcdiv($usd, $donorRate, 6));
            }

            $debit = $row['side'] === 'credit' ? null : $amount;
            $credit = $row['side'] === 'credit' ? $amount : null;

            $hawala = $this->hawala($row, $accountCode, $voucherType, $debit !== null, $project);
            if ($hawala['problem'] === 'only_receipt') {
                $problems[] = __('ledger.rules.hawala_only_receipt', ['account' => config('ledger.transfer_company_account')]).' ('.$number.')';
            } elseif ($hawala['problem'] === 'bad_amount') {
                $problems[] = __('ledger.builder.bad_amount', ['line' => $number]);
            } elseif ($hawala['problem'] === 'needs_rate') {
                $problems[] = __('ledger.builder.hawala_needs_rate', ['line' => $number]);
            }

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
                eurAmount: $hawala['eur'],
                eurRate: $hawala['rate'],
                commissionUsd: $hawala['commission'],
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

    /**
     * The hawala fields of a line: the euros sent, the rate they were really converted at (the project's default rate when
     * none is typed) and the commission, only on a dollar debit to the transfer company in a receipt.
     *
     * @param  array<string, string>  $row
     * @return array{eur: numeric-string|null, rate: numeric-string|null, commission: numeric-string, problem: 'only_receipt'|'bad_amount'|'needs_rate'|null}
     */
    private function hawala(array $row, string $account, ?VoucherType $type, bool $debit, ?GlProject $project): array
    {
        $none = ['eur' => null, 'rate' => null, 'commission' => Money::zero(), 'problem' => null];

        if (trim($row['eur_amount']) === '' && trim($row['commission']) === '' && trim($row['eur_rate']) === '') {
            return $none;
        }

        if ($account !== (string) config('ledger.transfer_company_account') || ! $debit || $type !== VoucherType::Receipt) {
            return ['problem' => 'only_receipt'] + $none;
        }

        $eur = trim($row['eur_amount']) === '' ? null : $this->amount($row['eur_amount']);
        $commission = trim($row['commission']) === '' ? Money::zero() : $this->amount($row['commission']);

        if ((trim($row['eur_amount']) !== '' && $eur === null) || $commission === null || bccomp($commission, '0', 2) < 0) {
            return ['problem' => 'bad_amount'] + $none;
        }

        $rate = $this->hawalaRate($row['eur_rate'], $project);

        return ['eur' => $eur, 'rate' => $eur === null ? null : $rate, 'commission' => $commission, 'problem' => $eur !== null && $rate === null ? 'needs_rate' : null];
    }

    /**
     * The rate a transfer was converted at: the one typed, else the project's default when it has a donor currency.
     *
     * @return numeric-string|null
     */
    private function hawalaRate(string $typed, ?GlProject $project): ?string
    {
        $rate = $this->rate($typed);

        if ($rate !== null) {
            return $rate;
        }

        if ($project === null || $project->donor_currency === 'USD') {
            return null;
        }

        $default = Money::normalize($project->donor_rate, 6);

        return bccomp($default, '0', 6) > 0 ? $default : null;
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
