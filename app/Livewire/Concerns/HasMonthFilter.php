<?php

namespace App\Livewire\Concerns;

use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;

/**
 * One month picker for every screen that filters by a `from` and `to` date: choosing a month fills both dates,
 * clearing it empties them. The dates can still be set by hand afterwards.
 */
trait HasMonthFilter
{
    #[Url]
    public string $month = '';

    public function updatedMonth(string $value): void
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1) {
            $start = CarbonImmutable::createFromFormat('!Y-m', $value);

            if ($start !== null) {
                $this->from = $start->toDateString();
                $this->to = $start->endOfMonth()->toDateString();

                return;
            }
        }

        $this->month = '';
        $this->from = '';
        $this->to = '';
    }
}
