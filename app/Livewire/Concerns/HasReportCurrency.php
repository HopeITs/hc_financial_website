<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

/**
 * The currency switch every report shares: USD, or SYP at a rate typed on the report (no rate table).
 * Pair it with `<x-ui.currency-switch />` in the view.
 */
trait HasReportCurrency
{
    #[Url]
    public string $currency = 'USD';

    #[Url]
    public string $rate = '';

    protected function reportCurrency(): string
    {
        return $this->currency === 'SYP' ? 'SYP' : 'USD';
    }

    protected function reportRate(): ?string
    {
        return is_numeric($this->rate) ? $this->rate : null;
    }
}
