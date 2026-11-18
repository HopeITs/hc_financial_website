<?php

namespace App\Services\Treasury;

use App\Enums\AccountLevel;
use App\Enums\VoucherType;
use App\Models\GlAccount;
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
 * - Transfer (TRF): the same currency leaves one account and reaches another, valued at the day's approved rate. When
 *   the transfer company is one side and less arrives than was sent, the gap is its commission (5504).
 * - Exchange (EXG): one currency leaves, another arrives, at the rate really obtained. The dollar side fixes the value of
 *   both; between two other currencies, what leaves is valued at the day's approved rate. Whatever that rate differs
 *   from the day's approved rate is settled by the daily reconciliation, on 5503.
 */
class TreasuryMoves
{
    public function __construct(
        private readonly PostVoucher $posting,
        private readonly DailyRates $rates,
        private readonly Currencies $currencies,
    ) {}

    /** @return Collection<string, GlAccount> the active cash accounts, by code */
    public function accounts(): Collection
    {
        return GlAccount::query()
            ->where('is_cash', true)->where('is_active', true)->where('level', AccountLevel::Posting->value)
            ->orderBy('code')->get()->keyBy('code');
    }

    /**
     * @param  numeric-string  $sent
     * @param  numeric-string|null  $received  what reached the other account; null when all of it did
     *
     * @throws TreasuryException
     */
    public function transfer(string $from, string $to, string $day, string $sent, ?string $received, string $contact, string $center, string $note, User $by): Voucher
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

        $company = (string) config('ledger.transfer_company_account');
        $gap = $amount !== null && $arrived !== null ? bcsub($amount, $arrived, 2) : null;

        if ($arrived === null || bccomp($arrived, '0', 2) !== 1 || ($gap !== null && bccomp($gap, '0', 2) === -1)) {
            $problems[] = __('treasury.bad_received');
        } elseif ($gap !== null && bccomp($gap, '0', 2) === 1 && $from !== $company && $to !== $company) {
            $problems[] = __('treasury.commission_only_company');
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

        $lines = [
            $this->line($source, credit: true, amount: $amount, rate: $rate, usd: $sentUsd, contact: $contact, center: $center, commission: $commission),
            $this->line($target, credit: false, amount: $arrived, rate: $rate, usd: $arrivedUsd, contact: $contact, center: $center),
        ];

        if (bccomp($commission, '0', 2) === 1) {
            $lines[] = new LineDraft(HawalaReceiptIsConsistent::COMMISSION_ACCOUNT, Currencies::BASE, debit: $commission);
        }

        return $this->post(VoucherType::Transfer, $date, $lines, trim($note) ?: __('treasury.transfer_description', ['from' => $source->code, 'to' => $target->code]), $by);
    }

    /**
     * @param  numeric-string  $given  what left the first account, in its currency
     * @param  numeric-string  $received  what reached the second, in its currency
     *
     * @throws TreasuryException
     */
    public function exchange(string $from, string $to, string $day, string $given, string $received, string $contact, string $center, string $note, User $by): Voucher
    {
        [$source, $target, $date, $problems] = $this->check($from, $to, $day);
        $out = $this->amount($given);
        $in = $this->amount($received);

        if ($out === null || bccomp($out, '0', 2) !== 1) {
            $problems[] = __('treasury.bad_amount');
        }

        if ($in === null || bccomp($in, '0', 2) !== 1) {
            $problems[] = __('treasury.bad_received');
        }

        if ($source !== null && $target !== null && $source->currency === $target->currency) {
            $problems[] = __('treasury.exchange_other_currency');
        }

        $problems = [...$problems, ...$this->tagProblems([$source, $target], $center)];
        $this->stopOn($problems);

        /** @var GlAccount $source */
        /** @var GlAccount $target */
        /** @var CarbonImmutable $date */
        /** @var numeric-string $out */
        /** @var numeric-string $in */
        $usd = match (true) {
            $source->currency === Currencies::BASE => Money::normalize($out),
            $target->currency === Currencies::BASE => Money::normalize($in),
            default => $this->currencies->toUsd((string) $source->currency, $out, $this->rateOrStop((string) $source->currency, $date)),
        };

        if (bccomp($usd, '0.01', 2) === -1) {
            $this->stopOn([__('treasury.bad_amount')]);
        }

        $lines = [
            $this->line($source, credit: true, amount: $out, rate: $this->rateOf((string) $source->currency, $out, $usd), usd: $usd, contact: $contact, center: $center),
            $this->line($target, credit: false, amount: $in, rate: $this->rateOf((string) $target->currency, $in, $usd), usd: $usd, contact: $contact, center: $center),
        ];

        return $this->post(VoucherType::Exchange, $date, $lines, trim($note) ?: __('treasury.exchange_description', ['given' => $out.' '.$source->currency, 'received' => $in.' '.$target->currency]), $by);
    }

    /**
     * The rate an exchange really got, in the quoting of the currency that is not the dollar, next to the day's approved
     * rate, so the form can show both; null when the amounts are not typed yet.
     *
     * @return array{currency: string, actual: string, approved: string|null}|null
     */
    public function exchangeRates(string $from, string $to, string $day, string $given, string $received): ?array
    {
        $accounts = $this->accounts();
        $source = $accounts->get($from);
        $target = $accounts->get($to);
        $out = $this->amount($given);
        $in = $this->amount($received);
        $date = $this->day($day);

        if ($source === null || $target === null || $out === null || $in === null || $source->currency === $target->currency) {
            return null;
        }

        [$currency, $orig, $usd] = match (true) {
            $source->currency === Currencies::BASE => [(string) $target->currency, $in, $out],
            $target->currency === Currencies::BASE => [(string) $source->currency, $out, $in],
            default => [null, null, null],
        };

        $actual = $currency !== null ? $this->currencies->rateOf($currency, $orig, $usd) : null;

        if ($currency === null || $actual === null) {
            return null;
        }

        $approved = $date !== null ? $this->rates->rateFor($currency, $date) : null;

        return ['currency' => $currency, 'actual' => $actual, 'approved' => $approved !== null ? Money::normalize($approved->rate, 6) : null];
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
