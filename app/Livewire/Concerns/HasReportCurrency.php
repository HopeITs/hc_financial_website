<?php

namespace App\Livewire\Concerns;

use App\Services\Ledger\Currencies;
use Livewire\Attributes\Url;

/**
 * The currency switch every report shares: USD, or another active currency (SYP, EUR, ...) at a rate typed on the report
 * (no rate table), typed the way that currency is quoted. Pair it with `<x-ui.currency-switch />` in the view.
 */
trait HasReportCurrency
{
    #[Url]
    public string $currency = 'USD';

    #[Url]
    public string $rate = '';

    protected function reportCurrency(): string
    {
        return app(Currencies::class)->isActive($this->currency) ? $this->currency : Currencies::BASE;
    }

    protected function reportRate(): ?string
    {
        return is_numeric($this->rate) ? $this->rate : null;
    }
}
