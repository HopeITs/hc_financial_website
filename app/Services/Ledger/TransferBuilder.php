<?php

namespace App\Services\Ledger;

use App\Enums\FundSource;
use App\Enums\TransferKind;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\GlProject;
use App\Support\Money;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Builds the transfer voucher (TRF with its kind) that moves money between a city's general accounts and a project, or
 * between two projects. Money moves by tag: the same cash account, a different project on each side.
 *
 *  - general USD to project: dollars, one for one.
 *  - general SYP to project: the pounds leave the general SYP account at its average rate (what they cost) and reach the
 *    project at the city's approved rate. The project pays the dollar value at that rate from its balance with the transfer
 *    company (1161), which the general USD account receives; the gap between that value and the cost is the exchange
 *    difference, kept on 5503 under the general SYP account. With the average-cost switch on, both rates are the average
 *    and there is no difference.
 *  - project back to general, and project to project: dollars one for one, pounds at the sending project's average rate,
 *    so the dollars follow the pounds and nothing is gained or lost.
 *
 *  - local exchange (R4-9): dollars leave the city's general USD cash account and pounds enter its general SYP cash account at
 *    the typed rate, in one voucher. The pounds are booked at that rate; when fewer or more pounds were really received than
 *    dollars x rate, and for any commission paid in dollars, the difference goes to 5503 under the general SYP account.
 *
 * It never posts and never throws on bad input: it reports problems (no pounds in the general account, not enough
 * pounds, no approved rate, ...) and returns no draft.
 */
class TransferBuilder
{
    public function __construct(
        private readonly ProjectAverageRate $averages,
        private readonly TransferRates $rates,
    ) {}

    public function build(
        string $kind,
        string $date,
        string $city,
        string $project,
        string $otherProject,
        string $currency,
        string $amount,
        string $cashAccount,
        string $contact,
        string $description,
        int $preparedBy,
        string $rate = '',
        string $received = '',
        string $commission = '',
        string $sypCashAccount = '',
    ): BuiltTransfer {
        $transfer = TransferKind::tryFrom($kind);
        if ($transfer === null) {
            return $this->failed(__('ledger.transfers.bad_kind'));
        }

        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $date) : false;
        if (! $day instanceof CarbonImmutable || $day->format('Y-m-d') !== $date) {
            return $this->failed(__('ledger.builder.bad_date'));
        }

        $value = $this->amount($amount);
        if ($value === null || bccomp($value, '0', 2) <= 0) {
            return $this->failed(__('ledger.transfers.bad_amount'));
        }

        $currency = match ($transfer) {
            TransferKind::UsdToProject, TransferKind::LocalExchange => 'USD',
            TransferKind::SypToProject => 'SYP',
            default => $currency,
        };
        if (! in_array($currency, ['USD', 'SYP'], true)) {
            return $this->failed(__('ledger.transfers.bad_currency'));
        }

        $generals = GlProject::query()->where('is_general_account', true)->where('city', $city)->get();
        $usdGeneral = $generals->first(fn (GlProject $p) => str_ends_with($p->code, '-USD'))?->code;
        $sypGeneral = $generals->first(fn (GlProject $p) => str_ends_with($p->code, '-SYP'))?->code;
        if ($usdGeneral === null || $sypGeneral === null) {
            return $this->failed(__('ledger.transfers.bad_city'));
        }

        $cash = GlAccount::query()->whereKey($cashAccount)->first();
        if ($cash === null || ! $cash->is_cash || $cash->currency !== $currency || $cash->city !== $city || preg_match((string) config('ledger.in_transit_pattern'), $cash->code) === 1) {
            return $this->failed(__('ledger.transfers.bad_cash', ['currency' => $currency, 'city' => $city]));
        }

        if ($transfer === TransferKind::LocalExchange) {
            return $this->localExchange($cash, $sypCashAccount, $city, $usdGeneral, $sypGeneral, $value, $rate, $received, $commission, $day, $description, $preparedBy);
        }

        $projects = GlProject::query()->whereIn('code', array_filter([$project, $otherProject]))->get()->keyBy('code');
        $needed = $transfer === TransferKind::BetweenProjects ? [$project, $otherProject] : [$project];
        foreach ($needed as $code) {
            $found = $projects->get($code);
            if ($found === null || $found->is_general_account) {
                return $this->failed(__('ledger.transfers.bad_project', ['project' => $code]));
            }
        }
        if ($transfer === TransferKind::BetweenProjects && $project === $otherProject) {
            return $this->failed(__('ledger.transfers.same_project'));
        }

        $general = $currency === 'USD' ? $usdGeneral : $sypGeneral;
        $from = match ($transfer) {
            TransferKind::UsdToProject, TransferKind::SypToProject => $general,
            default => $project,
        };
        $to = match ($transfer) {
            TransferKind::UsdToProject, TransferKind::SypToProject => $project,
            TransferKind::Return => $general,
            TransferKind::BetweenProjects => $otherProject,
            TransferKind::LocalExchange => $general,
        };

        $company = (string) config('ledger.transfer_company_account');
        $name = trim($contact) !== '' ? trim($contact) : (string) config('ledger.transfer_company_contact');
        $line = fn (string $account, string $curr, string $side, string $amt, string $rate, string $usd, string $code) => $this->line($account, $curr, $side, $amt, $rate, $usd, $code, $projects->get($code), $name);

        $held = $this->averages->holding($from, $currency);
        if (bccomp($held['orig'], '0', 2) <= 0) {
            return $this->failed(__($currency === 'USD' ? 'ledger.transfers.no_dollars' : 'ledger.transfers.no_pounds', ['project' => $from]));
        }
        if (bccomp($held['orig'], $value, 2) < 0) {
            return $this->failed(__($currency === 'USD' ? 'ledger.transfers.not_enough_dollars' : 'ledger.transfers.not_enough_pounds', ['project' => $from, 'held' => number_format((float) $held['orig'], 2)]));
        }

        $title = trim($description) !== '' ? trim($description) : $transfer->label();

        if ($currency === 'USD') {
            $lines = [
                $line($cash->code, 'USD', 'debit', $value, '1.000000', $value, $to),
                $line($cash->code, 'USD', 'credit', $value, '1.000000', $value, $from),
            ];

            return new BuiltTransfer($this->draft($transfer, $day, $preparedBy, $lines, $title), [], '1.000000', $value, Money::zero());
        }

        $average = $this->averages->rate($from);
        if ($average === null) {
            return $this->failed(__('ledger.transfers.no_average', ['project' => $from]));
        }

        if ($transfer !== TransferKind::SypToProject) {
            $usd = Money::normalize(bcdiv($value, $average, 6));
            $lines = [
                $line($cash->code, 'SYP', 'debit', $value, $average, $usd, $to),
                $line($cash->code, 'SYP', 'credit', $value, $average, $usd, $from),
            ];

            return new BuiltTransfer($this->draft($transfer, $day, $preparedBy, $lines, $title), [], $average, $usd, Money::zero());
        }

        $approved = $this->rates->approvedFor($city, $day);
        if ($approved === null) {
            return $this->failed(__('ledger.transfers.no_rate', ['city' => $city, 'date' => $date]));
        }

        $rate = $approved->allocate_at_average_cost ? $average : Money::normalize($approved->rate, 6);
        $projectUsd = Money::normalize(bcdiv($value, $rate, 6));
        $cost = Money::normalize(bcdiv($value, $average, 6));
        $difference = bcsub($projectUsd, $cost, 2);

        $lines = [
            $line($cash->code, 'SYP', 'debit', $value, $rate, $projectUsd, $project),
            $line($cash->code, 'SYP', 'credit', $value, $average, $cost, $sypGeneral),
            $line($company, 'USD', 'credit', $projectUsd, '1.000000', $projectUsd, $project),
            $line($company, 'USD', 'debit', $projectUsd, '1.000000', $projectUsd, $usdGeneral),
        ];
        if (bccomp($difference, '0', 2) !== 0) {
            $lines[] = $line((string) config('ledger.rounding_account'), 'USD', bccomp($difference, '0', 2) > 0 ? 'credit' : 'debit', Money::abs($difference), '1.000000', Money::abs($difference), $sypGeneral);
        }

        return new BuiltTransfer($this->draft($transfer, $day, $preparedBy, $lines, $title), [], $rate, $projectUsd, $difference);
    }

    /**
     * Local exchange: `$usdCash` (already checked as a USD cash account of the city) pays `$dollars`; the pounds received
     * (`$received`, default dollars x rate) enter the city's SYP cash account at `$rate`. Dollars worth of the pounds that
     * differ from the dollars given, and the commission, are 5503 on the general SYP account; the commission also leaves the
     * USD cash account.
     */
    private function localExchange(GlAccount $usdCash, string $sypCashAccount, string $city, string $usdGeneral, string $sypGeneral, string $dollars, string $rate, string $received, string $commission, CarbonImmutable $day, string $description, int $preparedBy): BuiltTransfer
    {
        $rate = $this->rateValue($rate);
        if ($rate === null) {
            return $this->failed(__('ledger.transfers.bad_exchange_rate'));
        }

        $sypCash = GlAccount::query()->whereKey($sypCashAccount)->first();
        if ($sypCash === null || ! $sypCash->is_cash || $sypCash->currency !== 'SYP' || $sypCash->city !== $city || preg_match((string) config('ledger.in_transit_pattern'), $sypCash->code) === 1) {
            return $this->failed(__('ledger.transfers.bad_cash', ['currency' => 'SYP', 'city' => $city]));
        }

        $pounds = trim($received) === '' ? Money::normalize(bcmul($dollars, $rate, 6)) : $this->amount($received);
        if ($pounds === null || bccomp($pounds, '0', 2) <= 0) {
            return $this->failed(__('ledger.transfers.bad_received'));
        }

        $fee = trim($commission) === '' ? Money::zero() : $this->amount($commission);
        if ($fee === null || bccomp($fee, '0', 2) < 0) {
            return $this->failed(__('ledger.transfers.bad_commission'));
        }

        $held = $this->averages->holding($usdGeneral, 'USD');
        $needed = bcadd($dollars, $fee, 2);
        if (bccomp($held['orig'], '0', 2) <= 0) {
            return $this->failed(__('ledger.transfers.no_dollars', ['project' => $usdGeneral]));
        }
        if (bccomp($held['orig'], $needed, 2) < 0) {
            return $this->failed(__('ledger.transfers.not_enough_dollars', ['project' => $usdGeneral, 'held' => number_format((float) $held['orig'], 2)]));
        }

        $poundsUsd = Money::normalize(bcdiv($pounds, $rate, 6));
        $difference = bcsub($dollars, $poundsUsd, 2);
        $title = trim($description) !== '' ? trim($description) : TransferKind::LocalExchange->label();
        $rounding = (string) config('ledger.rounding_account');

        $lines = [
            $this->line($usdCash->code, 'USD', 'credit', $dollars, '1.000000', $dollars, $usdGeneral, null, ''),
            $this->line($sypCash->code, 'SYP', 'debit', $pounds, $rate, $poundsUsd, $sypGeneral, null, ''),
        ];
        if (bccomp($difference, '0', 2) !== 0) {
            $lines[] = $this->line($rounding, 'USD', bccomp($difference, '0', 2) > 0 ? 'debit' : 'credit', Money::abs($difference), '1.000000', Money::abs($difference), $sypGeneral, null, '');
        }
        if (bccomp($fee, '0', 2) > 0) {
            $lines[] = $this->line($rounding, 'USD', 'debit', $fee, '1.000000', $fee, $sypGeneral, null, '');
            $lines[] = $this->line($usdCash->code, 'USD', 'credit', $fee, '1.000000', $fee, $usdGeneral, null, '');
        }

        return new BuiltTransfer($this->draft(TransferKind::LocalExchange, $day, $preparedBy, $lines, $title), [], $rate, $poundsUsd, bcsub('0.00', bcadd($difference, $fee, 2), 2));
    }

    /** @return numeric-string|null */
    private function rateValue(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        try {
            $rate = Money::normalize($clean, 6);
        } catch (InvalidArgumentException) {
            return null;
        }

        return bccomp($rate, '0', 6) > 0 ? $rate : null;
    }

    /** @param  list<LineDraft>  $lines */
    private function draft(TransferKind $kind, CarbonImmutable $day, int $preparedBy, array $lines, string $description): VoucherDraft
    {
        return new VoucherDraft(
            type: VoucherType::Transfer, date: $day, preparedBy: $preparedBy, lines: $lines,
            description: $description, transferKind: $kind->value,
        );
    }

    private function line(string $account, string $currency, string $side, string $amount, string $rate, string $usd, string $projectCode, ?GlProject $project, string $contact): LineDraft
    {
        $debit = $side === 'debit';

        $donorAmount = $usd;
        if ($project !== null && $project->donor_currency !== 'USD' && bccomp(Money::normalize($project->donor_rate, 6), '0', 6) > 0) {
            $donorAmount = Money::normalize(bcdiv($usd, Money::normalize($project->donor_rate, 6), 6));
        }

        return new LineDraft(
            accountCode: $account,
            currency: $currency,
            debit: $debit ? $amount : null,
            credit: $debit ? null : $amount,
            rate: $rate,
            debitUsd: $debit ? $usd : null,
            creditUsd: $debit ? null : $usd,
            projectCode: $projectCode,
            fundSource: $project === null ? FundSource::Treasury : FundSource::DonorGrant,
            contact: $account === config('ledger.transfer_company_account') ? $contact : null,
            donor: $project?->donor,
            restriction: $project?->restriction,
            donorCurrency: $project?->donor_currency,
            debitDonor: $project !== null && $debit ? $donorAmount : null,
            creditDonor: $project !== null && ! $debit ? $donorAmount : null,
        );
    }

    private function failed(string $problem): BuiltTransfer
    {
        return new BuiltTransfer(null, [$problem]);
    }

    /** @return numeric-string|null */
    private function amount(string $text): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        try {
            return Money::normalize($clean);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
