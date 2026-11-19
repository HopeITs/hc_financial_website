<?php

namespace App\Services\Treasury;

use App\Enums\AccountLevel;
use App\Enums\FundSource;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\Currencies;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\Rules\HawalaReceiptIsConsistent;
use App\Services\Ledger\Violation;
use App\Services\Ledger\VoucherDraft;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Money moving inside the organisation (finance decision of 2026-11-19, one treasury): between the banks, the transfer
 * company and the city safes and tills, and from one currency to another. No line names a project or a donor: a project
 * is named only where money comes in and where it is spent. Both wait for approval like any voucher.
 *
 * - Transfer (TRF): the same currency leaves one account and reaches another, valued at the month's rate. Less may
 *   arrive than was sent: the gap is the fee of the bank or the transfer company.
 * - Exchange (EXG): dollars leave the transfer company or a city's dollar cash and pounds reach the city's general
 *   pound account (its main safe), at the rate really obtained; or a donor's euros are withdrawn from the bank to the
 *   transfer company or a dollar bank. The month's exchanges into pounds are what the monthly rate is set from.
 *
 * Every fee is charged, as chosen each time, to the organisation (5504 through the transfer company, else 5501 bank
 * charges, no project) or to a budget line of a project.
 */
class TreasuryMoves
{
    public const FEE_TO_ORGANISATION = 'organisation';

    public const BANK_CHARGES = '5501';

    public const EXCHANGE_DIFFERENCES = '5503';

    /** The group of the bank accounts, under assets. */
    public const BANKS_GROUP = '1165';

    public function __construct(
        private readonly PostVoucher $posting,
        private readonly MonthlyRates $rates,
        private readonly Currencies $currencies,
        private readonly DailyRates $dailyRates,
    ) {}

    /** @return Collection<string, GlAccount> the active cash accounts, by code */
    public function accounts(): Collection
    {
        return GlAccount::query()
            ->where('is_cash', true)->where('is_active', true)->where('level', AccountLevel::Posting->value)
            ->orderBy('code')->get()->keyBy('code');
    }

    /**
     * Where money is exchanged from: the transfer company and the cities' dollar cash (into pounds), and the banks
     * holding another currency than dollars and pounds (withdrawn to dollars).
     *
     * @return Collection<string, GlAccount>
     */
    public function exchangeSources(): Collection
    {
        $banks = $this->bankCodes();

        return $this->accounts()->filter(fn (GlAccount $account): bool => $this->isCompany($account)
            || ($account->currency === Currencies::BASE && $account->city !== null && ! $this->isCenterTill($account))
            || (in_array($account->code, $banks, true) && ! in_array($account->currency, [Currencies::BASE, 'SYP'], true)));
    }

    /**
     * Where the money goes: from dollars, each city's general pound account (its main safe, not a center's till); from
     * a bank's euros, the transfer company or a dollar bank.
     *
     * @return Collection<string, GlAccount>
     */
    public function exchangeTargets(?GlAccount $source = null): Collection
    {
        if ($source !== null && $source->currency !== Currencies::BASE) {
            $banks = $this->bankCodes();

            return $this->accounts()->filter(fn (GlAccount $account): bool => $this->isCompany($account)
                || ($account->currency === Currencies::BASE && in_array($account->code, $banks, true)));
        }

        return $this->accounts()->filter(fn (GlAccount $account): bool => $account->currency !== Currencies::BASE
            && $account->city !== null && ! $this->isCenterTill($account));
    }

    /** @return list<string> */
    private function bankCodes(): array
    {
        $groups = GlAccount::query()->where('parent_code', self::BANKS_GROUP)->pluck('code')->all();

        return array_values(GlAccount::query()->whereIn('parent_code', $groups)->pluck('code')->map(fn ($code): string => (string) $code)->all());
    }

    /**
     * @param  numeric-string  $sent
     * @param  numeric-string|null  $received  what reached the other account; null when all of it did
     *
     * @throws TreasuryException
     */
    /**
     * @param  numeric-string  $sent
     * @param  numeric-string|null  $received  what reached the other account; null when all of it did. What did not
     *                                         arrive is the fee of the bank or the transfer company.
     * @param  string  $feeTo  self::FEE_TO_ORGANISATION or the key of the budget line the fee is charged to
     *
     * @throws TreasuryException
     */
    public function transfer(string $from, string $to, string $day, string $sent, ?string $received, string $contact, string $center, string $note, User $by, string $feeTo = self::FEE_TO_ORGANISATION): Voucher
    {
        [$source, $target, $date, $problems] = $this->check($from, $to, $day);
        $amount = $this->amount($sent);
        $arrived = $received === null || trim($received) === '' ? $amount : $this->amount($received);

        if ($amount === null || bccomp($amount, '0', 2) !== 1) {
            $problems[] = __('treasury.bad_amount');
        }

        if ($source !== null && $target !== null && $source->currency !== $target->currency) {
            $problems[] = __('treasury.transfer_same_currency');
        }

        $gap = $amount !== null && $arrived !== null ? bcsub($amount, $arrived, 2) : null;

        if ($arrived === null || bccomp($arrived, '0', 2) !== 1 || ($gap !== null && bccomp($gap, '0', 2) === -1)) {
            $problems[] = __('treasury.bad_received');
        }

        if ($gap !== null && bccomp($gap, '0', 2) === 1) {
            $problems = [...$problems, ...$this->feeProblems($feeTo)];
        }

        $problems = [...$problems, ...$this->tagProblems([$source, $target], $center)];
        $this->stopOn($problems);

        /** @var GlAccount $source */
        /** @var GlAccount $target */
        /** @var CarbonImmutable $date */
        /** @var numeric-string $amount */
        /** @var numeric-string $arrived */
        $currency = (string) $source->currency;
        $rate = $currency === Currencies::BASE ? '1' : $this->rateOrStop($currency, $date);
        $sentUsd = $this->currencies->toUsd($currency, $amount, $rate);
        $arrivedUsd = $this->currencies->toUsd($currency, $arrived, $rate);
        $commission = bcsub($sentUsd, $arrivedUsd, 2);
        $throughCompany = $this->isCompany($source) || $this->isCompany($target);
        $companyCommission = $feeTo === self::FEE_TO_ORGANISATION && $throughCompany ? $commission : '0';

        $lines = [
            $this->line($source, credit: true, amount: $amount, rate: $rate, usd: $sentUsd, contact: $contact, center: $center, commission: $companyCommission),
            $this->line($target, credit: false, amount: $arrived, rate: $rate, usd: $arrivedUsd, contact: $contact, center: $center),
        ];

        if (bccomp($commission, '0', 2) === 1) {
            $lines[] = $this->feeLine($feeTo, $throughCompany, $currency, (string) $gap, $rate, $commission);
        }

        return $this->post(VoucherType::Transfer, $date, $lines, trim($note) ?: __('treasury.transfer_description', ['from' => $source->code, 'to' => $target->code]), $by);
    }

    /**
     * Two kinds of exchange, both at the rate really obtained:
     *
     * - dollars from the transfer company or a city's dollar cash into the city's general pound account;
     * - another currency held in a bank (euros) withdrawn to the transfer company or a dollar bank. The euros leave at
     *   what the books hold them at, the account's average rate; what the dollars really came to differs from that, and
     *   the difference is the organisation's (5503).
     *
     * @param  numeric-string  $given  what left the first account, in its currency
     * @param  numeric-string  $received  what reached the second, in its currency, after any fee
     * @param  numeric-string  $fee  what was kept: of the pounds received for dollars; of the euros given for a withdrawal (the bank's fee)
     *
     * @throws TreasuryException
     */
    public function exchange(string $from, string $to, string $day, string $given, string $received, string $contact, string $center, string $note, User $by, string $fee = '0', string $feeTo = self::FEE_TO_ORGANISATION): Voucher
    {
        [$source, $target, $date, $problems] = $this->check($from, $to, $day);
        $out = $this->amount($given);
        $in = $this->amount($received);
        $kept = trim($fee) === '' ? '0.00' : $this->amount($fee);

        if ($out === null || bccomp($out, '0', 2) !== 1) {
            $problems[] = __('treasury.bad_amount');
        }

        if ($in === null || bccomp($in, '0', 2) !== 1) {
            $problems[] = __('treasury.bad_received');
        }

        if ($source !== null && ! $this->exchangeSources()->has($source->code)) {
            $problems[] = __('treasury.exchange_bad_source');
        } elseif ($source !== null && $target !== null && ! $this->exchangeTargets($source)->has($target->code)) {
            $problems[] = __($source->currency === Currencies::BASE ? 'treasury.exchange_bad_target' : 'treasury.withdraw_bad_target');
        }

        if ($kept === null || bccomp($kept, '0', 2) === -1) {
            $problems[] = __('treasury.bad_fee');
        } elseif (bccomp($kept, '0', 2) === 1) {
            $problems = [...$problems, ...$this->feeProblems($feeTo)];
        }

        $problems = [...$problems, ...$this->tagProblems([$source, $target], $center)];
        $this->stopOn($problems);

        /** @var GlAccount $source */
        /** @var GlAccount $target */
        /** @var CarbonImmutable $date */
        /** @var numeric-string $out */
        /** @var numeric-string $in */
        /** @var numeric-string $kept */
        $throughCompany = $this->isCompany($source) || $this->isCompany($target);
        $targetCurrency = (string) $target->currency;

        if ($source->currency === Currencies::BASE) {
            // Dollars into pounds: the dollars fix the value; the pounds kept as a fee share it.
            $usd = Money::normalize($out);
            $grossIn = bcadd($in, $kept, 2);
            $rate = $this->rateOf($targetCurrency, $grossIn, $usd);
            $inUsd = $this->currencies->toUsd($targetCurrency, $in, $rate);
            $lines = [
                $this->line($source, credit: true, amount: $out, rate: '1', usd: $usd, contact: $contact, center: $center),
                $this->line($target, credit: false, amount: $in, rate: $rate, usd: $inUsd, contact: $contact, center: $center),
            ];

            if (bccomp($kept, '0', 2) === 1) {
                $lines[] = $this->feeLine($feeTo, $throughCompany, $targetCurrency, $kept, $rate, bcsub($usd, $inUsd, 2));
            }
        } else {
            // Euros withdrawn to dollars (finance decision of 2026-11-21): the euros leave the bank at what the books
            // hold them at, the account's average rate, so an emptied account is nothing in euros and nothing in
            // dollars. The bank's fee is in euros, out of the euros given; the dollars are what really came, and the
            // gap with the book value of the euros converted is the real gain or loss (5503).
            $sourceCurrency = (string) $source->currency;

            if (bccomp($kept, $out, 2) >= 0) {
                throw new TreasuryException([__('treasury.withdraw_fee_too_big')]);
            }

            $bookRate = $this->bookRate($source, $date);
            $book = $this->currencies->toUsd($sourceCurrency, $out, $bookRate);
            $feeUsd = bccomp($kept, '0', 2) === 1 ? $this->currencies->toUsd($sourceCurrency, $kept, $bookRate) : '0.00';
            $difference = bcsub(bcadd($in, $feeUsd, 2), $book, 2);
            $lines = [
                $this->line($source, credit: true, amount: $out, rate: $bookRate, usd: $book, contact: $contact, center: $center),
                $this->line($target, credit: false, amount: $in, rate: '1', usd: $in, contact: $contact, center: $center),
            ];

            if (bccomp($kept, '0', 2) === 1) {
                $lines[] = $this->feeLine($feeTo, false, $sourceCurrency, $kept, $bookRate, $feeUsd);
            }

            if (bccomp($difference, '0', 2) !== 0) {
                $gain = bccomp($difference, '0', 2) === 1;
                $size = $gain ? $difference : bcmul($difference, '-1', 2);
                $lines[] = new LineDraft(self::EXCHANGE_DIFFERENCES, Currencies::BASE, debit: $gain ? null : $size, credit: $gain ? $size : null);
            }
        }

        return $this->post(VoucherType::Exchange, $date, $lines, trim($note) ?: __('treasury.exchange_description', ['given' => $out.' '.$source->currency, 'received' => $in.' '.$target->currency]), $by);
    }

    /** @return array<string, string> every budget line a fee can be charged to: key => "PROJECT · name" */
    public function feeBudgetLines(): array
    {
        return GlBudgetLine::query()->orderBy('project_code')->orderBy('name')->get()
            ->mapWithKeys(fn (GlBudgetLine $line): array => [$line->key => $line->project_code.' · '.$line->name])
            ->all();
    }

    /** @return list<string> */
    private function feeProblems(string $feeTo): array
    {
        return $feeTo === self::FEE_TO_ORGANISATION || GlBudgetLine::query()->whereKey($feeTo)->exists()
            ? []
            : [__('treasury.bad_fee_line')];
    }

    /**
     * The fee's line: the organisation's (5504 through the transfer company, else 5501 bank charges, no project) or the
     * budget line it is charged to, with its project.
     *
     * @param  numeric-string  $amount
     * @param  numeric-string  $rate
     * @param  numeric-string  $usd
     */
    private function feeLine(string $feeTo, bool $throughCompany, string $currency, string $amount, string $rate, string $usd): LineDraft
    {
        $budgetLine = $feeTo === self::FEE_TO_ORGANISATION ? null : GlBudgetLine::query()->with('project')->find($feeTo);

        if ($budgetLine === null) {
            return new LineDraft($throughCompany ? HawalaReceiptIsConsistent::COMMISSION_ACCOUNT : self::BANK_CHARGES, $currency, debit: $amount, rate: $rate, debitUsd: $usd);
        }

        $project = $budgetLine->project;
        $donorRate = $project !== null ? Money::normalize($project->donor_rate ?? '0', 6) : '0.000000';
        $donorAmount = $project !== null && $project->donor_currency !== null && $project->donor_currency !== Currencies::BASE && bccomp($donorRate, '0', 6) === 1
            ? Money::normalize(bcdiv($usd, $donorRate, 6))
            : $usd;

        return new LineDraft(
            accountCode: $budgetLine->account_code,
            currency: $currency,
            debit: $amount,
            rate: $rate,
            debitUsd: $usd,
            projectCode: $budgetLine->project_code,
            budgetLineKey: $budgetLine->key,
            fundSource: FundSource::DonorGrant,
            donor: $project?->donor,
            restriction: $project?->restriction,
            donorCurrency: $project?->donor_currency,
            debitDonor: $donorAmount,
        );
    }

    private function isCompany(GlAccount $account): bool
    {
        return $account->code === (string) config('ledger.transfer_company_account');
    }

    /**
     * The rate an exchange really got, next to the month's rate, so the form can show both; null when the amounts are
     * not typed yet.
     *
     * @return array{currency: string, actual: string, reference: array{kind: string, rate: string}|null}|null
     */
    public function exchangeRates(string $from, string $to, string $day, string $given, string $received, string $fee = ''): ?array
    {
        $source = $this->accounts()->get($from);
        $target = $this->accounts()->get($to);
        $out = $this->amount($given);
        $in = $this->amount($received);
        $date = $this->day($day);

        if ($source === null || $target === null || $out === null || $in === null || $source->currency === $target->currency) {
            return null;
        }

        if ($source->currency !== Currencies::BASE) {
            // Euros withdrawn to dollars: the euros converted (after the bank's fee), beside the average rate the books hold them at.
            $kept = $this->amount($fee) ?? '0.00';
            $converted = bcsub($out, bccomp($kept, '0', 2) === 1 ? $kept : '0', 2);
            $actual = $this->currencies->rateOf((string) $source->currency, $converted, $in);

            return $actual === null ? null : [
                'currency' => (string) $source->currency,
                'actual' => $actual,
                'reference' => $date !== null ? $this->bookReference($source, $date) : null,
            ];
        }

        $actual = $this->currencies->rateOf((string) $target->currency, $in, $out);
        $monthly = $date !== null ? $this->rates->forDay((string) $target->currency, $date) : null;

        return $actual === null ? null : [
            'currency' => (string) $target->currency,
            'actual' => $actual,
            'reference' => $monthly !== null ? ['kind' => 'monthly', 'rate' => $monthly['rate']] : null,
        ];
    }

    /**
     * The average rate a currency account holds its money at on a day: its amount against its book dollars, from every
     * voucher that stands. With nothing in it yet, the day's approved rate.
     *
     * @return numeric-string
     *
     * @throws TreasuryException when it is empty and the day has no approved rate
     */
    public function bookRate(GlAccount $account, CarbonImmutable $date): string
    {
        $balance = app(CashBook::class)->balances($date)[$account->code] ?? null;
        $rate = $balance !== null ? $this->currencies->rateOf((string) $account->currency, $balance['orig'], $balance['usd']) : null;

        return $rate ?? $this->dailyRates->requireRate((string) $account->currency, $date);
    }

    /** @return array{kind: string, rate: string}|null the average rate in the books, for the form; null when there is none */
    private function bookReference(GlAccount $account, CarbonImmutable $date): ?array
    {
        try {
            return ['kind' => 'book', 'rate' => $this->bookRate($account, $date)];
        } catch (TreasuryException) {
            return null;
        }
    }

    private function isCenterTill(GlAccount $account): bool
    {
        return in_array('Center', (array) $account->required_tags, true);
    }

    /** @return array{0: GlAccount|null, 1: GlAccount|null, 2: CarbonImmutable|null, 3: list<string>} */
    private function check(string $from, string $to, string $day): array
    {
        $accounts = $this->accounts();
        $source = $accounts->get($from);
        $target = $accounts->get($to);
        $date = $this->day($day);
        $problems = [];

        if ($source === null || $target === null) {
            $problems[] = __('treasury.bad_account');
        } elseif ($source->code === $target->code) {
            $problems[] = __('treasury.same_account');
        }

        if ($date === null) {
            $problems[] = __('treasury.bad_date');
        }

        return [$source, $target, $date, $problems];
    }

    /**
     * A till that belongs to a center needs the center named on its line.
     *
     * @param  list<GlAccount|null>  $accounts
     * @return list<string>
     */
    private function tagProblems(array $accounts, string $center): array
    {
        foreach ($accounts as $account) {
            if ($account !== null && in_array('Center', (array) $account->required_tags, true) && trim($center) === '') {
                return [__('treasury.center_needed', ['account' => $account->code])];
            }
        }

        return [];
    }

    /**
     * @param  numeric-string  $amount
     * @param  numeric-string  $rate
     * @param  numeric-string  $usd
     */
    private function line(GlAccount $account, bool $credit, string $amount, string $rate, string $usd, string $contact, string $center, string $commission = '0'): LineDraft
    {
        $tags = (array) $account->required_tags;
        $isCompany = $account->code === (string) config('ledger.transfer_company_account');

        return new LineDraft(
            accountCode: $account->code,
            currency: (string) $account->currency,
            debit: $credit ? null : $amount,
            credit: $credit ? $amount : null,
            rate: $rate,
            debitUsd: $credit ? null : $usd,
            creditUsd: $credit ? $usd : null,
            contact: $isCompany || in_array('Contact', $tags, true) ? (trim($contact) ?: (string) config('ledger.transfer_company_contact')) : null,
            center: in_array('Center', $tags, true) ? trim($center) : null,
            commissionUsd: $isCompany && $credit ? $commission : null,
        );
    }

    /**
     * @param  list<LineDraft>  $lines
     *
     * @throws TreasuryException
     */
    private function post(VoucherType $type, CarbonImmutable $date, array $lines, string $description, User $by): Voucher
    {
        try {
            return $this->posting->post(new VoucherDraft(
                type: $type,
                date: $date,
                preparedBy: (int) $by->getAuthIdentifier(),
                lines: $lines,
                description: $description,
            ));
        } catch (LedgerRuleException $refused) {
            throw new TreasuryException(array_map(fn (Violation $violation): string => $violation->message, $refused->violations));
        }
    }

    /** @return numeric-string */
    private function rateOf(string $currency, string $orig, string $usd): string
    {
        return $currency === Currencies::BASE ? '1' : ($this->currencies->rateOf($currency, $orig, $usd) ?? '1');
    }

    /**
     * @return numeric-string
     *
     * @throws TreasuryException
     */
    private function rateOrStop(string $currency, CarbonImmutable $date): string
    {
        return $this->rates->requireRate($currency, $date);
    }

    /**
     * @param  list<string>  $problems
     *
     * @throws TreasuryException
     */
    private function stopOn(array $problems): void
    {
        if ($problems !== []) {
            throw new TreasuryException($problems);
        }
    }

    private function day(string $text): ?CarbonImmutable
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text ? $day : null;
    }

    /** @return numeric-string|null */
    private function amount(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        try {
            return Money::normalize(str_replace([',', ' '], '', trim($text)));
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
