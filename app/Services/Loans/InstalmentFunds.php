<?php

namespace App\Services\Loans;

use App\Enums\FundSource;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\FundExchange;
use App\Models\GlAccount;
use App\Models\GlProject;
use App\Models\MicroLoan;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\NetAssets;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherDraft;
use App\Services\Ledger\Violation;
use App\Services\Treasury\DailyRates;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Instalment funds (finance decisions of 2026-11-22). The micro-loan repayments stay the funder's: each funder has a
 * fund in each city, in pounds and in dollars, made of the repayments received into that city's cash and less what
 * funded new beneficiaries from it. It is not an account of the chart: it is read from the voucher lines, the cash
 * lines with the fund source "Recovered loans", by their donor and the city of their cash account.
 *
 * The report values a fund's pounds, and the instalments still owed in pounds, at the day's approved rate; a loan
 * funded from it is fixed at the rate of its invoice. The Financial Manager can exchange a fund's pounds with the
 * organisation (FundExchange): fixed in dollars and given back in pounds later, or exchanged for good.
 */
class InstalmentFunds
{
    public const POUNDS = 'SYP';

    public const DOLLARS = 'USD';

    public function __construct(
        private readonly DailyRates $daily,
        private readonly PostVoucher $posting,
    ) {}

    /**
     * Every fund on a day, by funder and city.
     *
     * @return list<array{funder: string, city: string, syp: string, usd: string, owed_syp: string, owed_usd: string, fixed_syp: string, fixed_usd: string, value_usd: string|null}>
     */
    public function balances(CarbonImmutable $asAt): array
    {
        $funds = [];
        $blank = ['syp' => '0.00', 'usd' => '0.00', 'owed_syp' => '0.00', 'owed_usd' => '0.00', 'fixed_syp' => '0.00', 'fixed_usd' => '0.00'];

        foreach ($this->cash($asAt) as $row) {
            $key = $row->funder."\u{1F}".$row->city;
            $funds[$key] ??= ['funder' => (string) $row->funder, 'city' => (string) $row->city] + $blank;
            $column = $row->currency === self::POUNDS ? 'syp' : 'usd';
            $funds[$key][$column] = bcadd($funds[$key][$column], Money::normalize(number_format((float) $row->orig, 2, '.', '')), 2);
        }

        foreach (MicroLoan::query()->with(['instalments', 'project'])->get() as $loan) {
            $funder = (string) ($loan->funder ?? $loan->project?->donor);
            $city = (string) ($loan->city ?? $loan->project?->city);
            $owed = $loan->owed();

            if ($funder === '' || $city === '' || bccomp($owed, '0', 2) <= 0) {
                continue;
            }

            $key = $funder."\u{1F}".$city;
            $funds[$key] ??= ['funder' => $funder, 'city' => $city] + $blank;
            $column = $loan->currency === self::POUNDS ? 'owed_syp' : 'owed_usd';
            $funds[$key][$column] = bcadd($funds[$key][$column], $owed, 2);
        }

        foreach (FundExchange::query()->where('kind', FundExchange::FIX)->whereNull('return_voucher_id')->get() as $fix) {
            $key = $fix->funder."\u{1F}".$fix->city;
            $funds[$key] ??= ['funder' => $fix->funder, 'city' => $fix->city] + $blank;
            $funds[$key]['fixed_syp'] = bcadd($funds[$key]['fixed_syp'], (string) $fix->syp_amount, 2);
            $funds[$key]['fixed_usd'] = bcadd($funds[$key]['fixed_usd'], (string) $fix->usd_amount, 2);
        }

        $rate = $this->todayRate($asAt);
        ksort($funds);

        return array_values(array_map(fn (array $fund): array => $fund + [
            'value_usd' => $rate === null ? null : bcadd(
                bcadd($fund['usd'], $fund['owed_usd'], 2),
                Money::normalize(bcdiv(bcadd($fund['syp'], $fund['owed_syp'], 2), $rate, 6)),
                2,
            ),
        ], $funds));
    }

    /** The day's approved pound rate the funds are valued at, or null when there is none yet. */
    public function todayRate(CarbonImmutable $day): ?string
    {
        $rate = $this->daily->rateFor(self::POUNDS, $day);

        return $rate !== null ? Money::normalize($rate->rate, 6) : null;
    }

    /**
     * What a fund holds in a currency, in all the city's cash, or in one cash account.
     *
     * @return numeric-string
     */
    public function available(string $funder, string $city, string $currency, CarbonImmutable $asAt, ?string $account = null): string
    {
        $sum = '0.00';

        foreach ($this->cash($asAt, $account) as $row) {
            if ((string) $row->funder === $funder && (string) $row->city === $city && $row->currency === $currency) {
                $sum = bcadd($sum, Money::normalize(number_format((float) $row->orig, 2, '.', '')), 2);
            }
        }

        return $sum;
    }

    /**
     * The funds a new loan can be paid from: funder and city, with what they hold.
     *
     * @return array<string, string> "funder|city" => label
     */
    public function choices(CarbonImmutable $asAt): array
    {
        $choices = [];

        foreach ($this->balances($asAt) as $fund) {
            if (bccomp($fund['syp'], '0', 2) > 0 || bccomp($fund['usd'], '0', 2) > 0) {
                $choices[$fund['funder'].'|'.$fund['city']] = $fund['funder'].' · '.$fund['city'].' ('.number_format((float) $fund['syp'], 0).' SYP · '.number_format((float) $fund['usd'], 2).' USD)';
            }
        }

        return $choices;
    }

    /**
     * The Financial Manager exchanges a fund's pounds with the organisation, at the day's rate (it can be changed): the
     * pounds go to the city's general pound account and the same value in dollars, from the city's general dollar
     * account, goes to the fund. "fix" is given back later; "exchange" is for good. The cash stays where it is: only who
     * it belongs to changes, by the lines' fund source, donor and project.
     *
     * @throws LoanException
     */
    public function exchange(string $funder, string $city, string $kind, string $pounds, string $rate, string $poundAccount, string $dollarAccount, CarbonImmutable $date, User $by): FundExchange
    {
        $amount = $this->number($pounds, 2);
        $value = $this->number($rate, 6);
        $problems = $this->accountProblems($city, $poundAccount, $dollarAccount);

        if (! in_array($kind, [FundExchange::FIX, FundExchange::EXCHANGE], true)) {
            $problems[] = __('funds.problem.kind');
        }

        if ($amount === null || bccomp($amount, '0', 2) <= 0) {
            $problems[] = __('funds.problem.amount');
        }

        if ($value === null || bccomp($value, '0', 6) <= 0) {
            $problems[] = __('funds.problem.rate');
        }

        if ($amount !== null && $problems === []) {
            $held = $this->available($funder, $city, self::POUNDS, $date, $poundAccount);
            if (bccomp($amount, $held, 2) > 0) {
                $problems[] = __('funds.problem.short', ['held' => $held, 'currency' => self::POUNDS, 'account' => $poundAccount]);
            }
        }

        [$generalPounds, $generalDollars] = $this->generalProjects($city);
        if ($generalPounds === null || $generalDollars === null) {
            $problems[] = __('funds.problem.no_general', ['city' => $city]);
        }

        if ($problems !== []) {
            throw new LoanException($problems);
        }

        /** @var numeric-string $amount */
        /** @var numeric-string $value */
        /** @var GlProject $generalPounds */
        /** @var GlProject $generalDollars */
        $dollars = Money::normalize(bcdiv($amount, $value, 6));

        return DB::transaction(function () use ($funder, $city, $kind, $amount, $value, $dollars, $poundAccount, $dollarAccount, $date, $by, $generalPounds, $generalDollars): FundExchange {
            $voucher = $this->post($date, $by, __('funds.exchange_description.'.$kind, ['funder' => $funder, 'city' => $city]), [
                $this->fundLine($poundAccount, self::POUNDS, $amount, $value, $dollars, credit: true, funder: $funder, city: $city),
                $this->generalLine($poundAccount, self::POUNDS, $amount, $value, $dollars, credit: false, project: $generalPounds),
                $this->generalLine($dollarAccount, self::DOLLARS, $dollars, '1', $dollars, credit: true, project: $generalDollars),
                $this->fundLine($dollarAccount, self::DOLLARS, $dollars, '1', $dollars, credit: false, funder: $funder, city: $city),
            ]);

            return FundExchange::query()->create([
                'funder' => $funder, 'city' => $city, 'kind' => $kind, 'syp_amount' => $amount, 'usd_amount' => $dollars, 'rate' => $value,
                'fixed_on' => $date->toDateString(), 'syp_account' => $poundAccount, 'usd_account' => $dollarAccount, 'voucher_id' => $voucher->id,
                'created_by' => (int) $by->getKey(),
            ]);
        });
    }

    /**
     * Gives a fixed fund its pounds back, at the day's rate (it can be changed): the organisation gives back the same
     * pounds and takes its dollars again. What the organisation saved is the dollars less what the pounds are worth now.
     *
     * @throws LoanException
     */
    public function giveBack(FundExchange $fix, string $rate, CarbonImmutable $date, User $by): FundExchange
    {
        $value = $this->number($rate, 6);
        $problems = [];

        if (! $fix->isOpen()) {
            $problems[] = __('funds.problem.not_open');
        }

        if ($value === null || bccomp($value, '0', 6) <= 0) {
            $problems[] = __('funds.problem.rate');
        }

        [$generalPounds, $generalDollars] = $this->generalProjects($fix->city);
        if ($generalPounds === null || $generalDollars === null) {
            $problems[] = __('funds.problem.no_general', ['city' => $fix->city]);
        }

        if ($problems !== []) {
            throw new LoanException($problems);
        }

        /** @var numeric-string $value */
        /** @var GlProject $generalPounds */
        /** @var GlProject $generalDollars */
        $pounds = Money::normalize($fix->syp_amount);
        $dollars = Money::normalize($fix->usd_amount);
        $poundsNow = Money::normalize(bcdiv($pounds, $value, 6));

        return DB::transaction(function () use ($fix, $value, $pounds, $dollars, $poundsNow, $date, $by, $generalPounds, $generalDollars): FundExchange {
            $voucher = $this->post($date, $by, __('funds.give_back_description', ['funder' => $fix->funder, 'city' => $fix->city]), [
                $this->fundLine($fix->usd_account, self::DOLLARS, $dollars, '1', $dollars, credit: true, funder: $fix->funder, city: $fix->city),
                $this->generalLine($fix->usd_account, self::DOLLARS, $dollars, '1', $dollars, credit: false, project: $generalDollars),
                $this->generalLine($fix->syp_account, self::POUNDS, $pounds, $value, $poundsNow, credit: true, project: $generalPounds),
                $this->fundLine($fix->syp_account, self::POUNDS, $pounds, $value, $poundsNow, credit: false, funder: $fix->funder, city: $fix->city),
            ]);

            $fix->update(['returned_on' => $date->toDateString(), 'return_rate' => $value, 'return_voucher_id' => $voucher->id]);

            return $fix->refresh();
        });
    }

    /**
     * What giving a fix back at a rate saves the organisation, in dollars: the dollars it set aside less what the same
     * pounds are worth at that rate (negative when the pound has risen).
     *
     * @return numeric-string
     */
    public function saving(FundExchange $fix, string $rate): string
    {
        $value = $this->number($rate, 6);

        return $value === null || bccomp($value, '0', 6) <= 0
            ? '0.00'
            : bcsub(Money::normalize($fix->usd_amount), Money::normalize(bcdiv(Money::normalize($fix->syp_amount), $value, 6)), 2);
    }

    /**
     * The fund's cash, from every voucher that stands (the money moved): by donor, city of the cash account and currency.
     *
     * @return iterable<object{funder: string|null, city: string|null, currency: string, orig: mixed}>
     */
    private function cash(CarbonImmutable $asAt, ?string $account = null): iterable
    {
        return DB::table('gl_voucher_lines as l')
            ->join('gl_vouchers as v', 'v.id', '=', 'l.voucher_id')
            ->join('gl_accounts as a', 'a.code', '=', 'l.account_code')
            ->where('a.is_cash', true)
            ->where('l.fund_source', FundSource::RecoveredLoans->value)
            ->where('v.status', '!=', VoucherStatus::Replaced->value)
            ->whereNull('v.deleted_at')
            ->whereDate('v.date', '<=', $asAt->toDateString())
            ->when($account !== null, fn ($query) => $query->where('l.account_code', $account))
            ->whereNotNull('l.donor')
            ->groupBy('l.donor', 'a.city', 'l.currency')
            ->selectRaw('l.donor as funder, a.city as city, l.currency as currency, sum(l.debit_orig) - sum(l.credit_orig) as orig')
            ->get();
    }

    /** @return array{0: GlProject|null, 1: GlProject|null} the city's general pound and dollar accounts */
    private function generalProjects(string $city): array
    {
        $general = GlProject::query()->where('is_general_account', true)->where('city', $city)->get();

        return [
            $general->first(fn (GlProject $project): bool => str_ends_with($project->code, '-'.self::POUNDS)),
            $general->first(fn (GlProject $project): bool => str_ends_with($project->code, '-'.self::DOLLARS)),
        ];
    }

    /** @return list<string> */
    private function accountProblems(string $city, string $poundAccount, string $dollarAccount): array
    {
        $problems = [];

        foreach ([[$poundAccount, self::POUNDS], [$dollarAccount, self::DOLLARS]] as [$code, $currency]) {
            $account = GlAccount::query()->find($code);
            if ($account === null || ! $account->is_cash || $account->currency !== $currency || (string) $account->city !== $city) {
                $problems[] = __('funds.problem.account', ['currency' => $currency, 'city' => $city]);
            }
        }

        return $problems;
    }

    private function fundLine(string $account, string $currency, string $amount, string $rate, string $usd, bool $credit, string $funder, string $city): LineDraft
    {
        return new LineDraft(
            accountCode: $account, currency: $currency, debit: $credit ? null : $amount, credit: $credit ? $amount : null, rate: $rate,
            debitUsd: $credit ? null : $usd, creditUsd: $credit ? $usd : null,
            fundSource: FundSource::RecoveredLoans, donor: $funder, city: $city, restriction: NetAssets::RESTRICTED,
        );
    }

    private function generalLine(string $account, string $currency, string $amount, string $rate, string $usd, bool $credit, GlProject $project): LineDraft
    {
        return new LineDraft(
            accountCode: $account, currency: $currency, debit: $credit ? null : $amount, credit: $credit ? $amount : null, rate: $rate,
            debitUsd: $credit ? null : $usd, creditUsd: $credit ? $usd : null,
            projectCode: $project->code, fundSource: FundSource::ChcOwnFunds,
        );
    }

    /**
     * @param  list<LineDraft>  $lines
     *
     * @throws LoanException
     */
    private function post(CarbonImmutable $date, User $by, string $description, array $lines): Voucher
    {
        try {
            return $this->posting->post(new VoucherDraft(
                type: VoucherType::Journal, date: $date, preparedBy: (int) $by->getAuthIdentifier(), lines: $lines, description: $description,
            ));
        } catch (LedgerRuleException $refused) {
            throw new LoanException(array_map(fn (Violation $violation): string => $violation->message, $refused->violations));
        }
    }

    /** @return numeric-string|null */
    private function number(string $text, int $scale): ?string
    {
        $clean = str_replace([',', ' '], '', trim($text));

        return preg_match('/^\d+(\.\d+)?$/', $clean) === 1 ? Money::normalize($clean, $scale) : null;
    }
}
