<?php

namespace App\Services\Excel\Datasets;

use App\Models\MonthlyRate;
use App\Models\User;
use App\Services\Excel\ExcelDataset;
use App\Services\Excel\ExcelException;
use App\Services\Treasury\DailyRates;
use App\Services\Treasury\MonthlyRates;
use App\Services\Treasury\TreasuryException;
use Carbon\CarbonImmutable;

/**
 * Monthly rates to and from Excel: one rate per currency and month, set by the Financial Manager. Importing a month
 * that has a rate changes it; the vouchers already made keep theirs.
 */
class MonthlyRatesExcel extends ExcelDataset
{
    public function __construct(
        private readonly MonthlyRates $monthly,
        private readonly DailyRates $rates,
    ) {}

    public function screen(): string
    {
        return 'alloc';
    }

    public function importAction(): string
    {
        return 'approve';
    }

    public function fileName(): string
    {
        return 'monthly-rates';
    }

    public function columns(): array
    {
        return ['month', 'currency', 'rate', 'note'];
    }

    public function exportRows(array $context): iterable
    {
        foreach (MonthlyRate::query()->orderByDesc('month')->orderBy('currency')->cursor() as $rate) {
            yield [$rate->month, $rate->currency, (float) $rate->rate, (string) $rate->note];
        }
    }

    public function exampleRows(array $context): array
    {
        return array_map(fn (string $currency): array => [CarbonImmutable::today()->format('Y-m'), $currency, null, ''], $this->rates->currencies());
    }

    protected function check(array $cells, array $before, User $user, array $context): array
    {
        $problems = [];
        $currency = strtoupper($cells['currency']);

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $cells['month']) !== 1) {
            $problems[] = __('excel.bad_month');
        }

        if (! in_array($currency, $this->rates->currencies(), true)) {
            $problems[] = __('excel.bad_currency', ['currency' => $cells['currency'], 'allowed' => implode(', ', $this->rates->currencies())]);
        }

        if (! is_numeric(str_replace(',', '', $cells['rate'])) || (float) str_replace(',', '', $cells['rate']) <= 0) {
            $problems[] = __('excel.bad_rate');
        }

        foreach ($before as $earlier) {
            if (strtoupper($earlier['currency']) === $currency && $earlier['month'] === $cells['month']) {
                $problems[] = __('excel.twice');
                break;
            }
        }

        $exists = $problems === [] && MonthlyRate::query()->where('currency', $currency)->where('month', $cells['month'])->exists();

        return ['action' => $exists ? 'update' : 'new', 'problems' => $problems];
    }

    protected function saveRow(array $cells, User $user, array $context): void
    {
        try {
            $this->monthly->set($cells['month'], strtoupper($cells['currency']), str_replace(',', '', $cells['rate']), $cells['note'], $user);
        } catch (TreasuryException $refused) {
            throw new ExcelException($refused->problems);
        }
    }
}
