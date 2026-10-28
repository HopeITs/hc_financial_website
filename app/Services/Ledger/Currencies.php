<?php

namespace App\Services\Ledger;

use App\Models\Currency;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * The currencies of the books and the one place that converts between a currency and USD (R4-4). A rate is typed and
 * stored the way its currency is quoted: SYP as pounds per dollar, EUR as dollars per euro. Registered as a scoped service,
 * so the list is read once per request.
 */
class Currencies
{
    public const BASE = 'USD';

    /** @var Collection<string, Currency>|null */
    private ?Collection $all = null;

    /** @return Collection<string, Currency> every currency, active or not, in display order */
    public function all(): Collection
    {
        return $this->all ??= Currency::query()->orderBy('sort_order')->orderBy('code')->get()->keyBy('code');
    }

    /** @return list<string> the active currency codes, USD first */
    public function codes(): array
    {
        return $this->all()->filter(fn (Currency $currency) => $currency->is_active || $currency->is_base)->keys()->all();
    }

    /** @return list<string> the active currencies other than USD */
    public function foreign(): array
    {
        return array_values(array_filter($this->codes(), fn (string $code) => $code !== self::BASE));
    }

    public function isActive(string $code): bool
    {
        return in_array($code, $this->codes(), true);
    }

    public function isForeign(string $code): bool
    {
        return $code !== self::BASE && $this->isActive($code);
    }

    /** Whether the rate of this currency is dollars per unit (EUR) and not units per dollar (SYP). */
    public function isUsdPerUnit(string $code): bool
    {
        return $this->all()->get($code)?->quote === Currency::USD_PER_UNIT;
    }

    /**
     * The dollar value of an amount at a typed rate; a dollar amount is itself.
     *
     * @param  numeric-string  $amount
     * @param  numeric-string  $rate
     * @return numeric-string
     */
    public function toUsd(string $code, string $amount, string $rate): string
    {
        if ($code === self::BASE) {
            return Money::normalize($amount);
        }

        return $this->isUsdPerUnit($code)
            ? Money::normalize(bcmul($amount, $rate, 6))
            : Money::normalize(bcdiv($amount, $rate, 6));
    }

    /**
     * The amount in a currency that a dollar value is worth at a typed rate.
     *
     * @param  numeric-string  $usd
     * @param  numeric-string  $rate
     * @return numeric-string
     */
    public function fromUsd(string $code, string $usd, string $rate): string
    {
        if ($code === self::BASE) {
            return Money::normalize($usd);
        }

        return $this->isUsdPerUnit($code)
            ? Money::normalize(bcdiv($usd, $rate, 6))
            : Money::normalize(bcmul($usd, $rate, 6));
    }

    /**
     * The rate that turns `$orig` of a currency into `$usd`, in the currency's own quoting, or null when the dollars are zero.
     *
     * @param  numeric-string  $orig
     * @param  numeric-string  $usd
     * @return numeric-string|null
     */
    public function rateOf(string $code, string $orig, string $usd): ?string
    {
        if (bccomp($orig, '0', 2) <= 0 || bccomp($usd, '0.01', 2) < 0) {
            return null;
        }

        return $this->isUsdPerUnit($code) ? bcdiv($usd, $orig, 6) : bcdiv($orig, $usd, 6);
    }
}
