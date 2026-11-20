<?php

namespace App\Services\Excel\Datasets;

use App\Models\DailyRate;
use App\Models\User;
use App\Services\Excel\ExcelDataset;
use App\Services\Excel\ExcelException;
use App\Services\Treasury\DailyRates;
use App\Services\Treasury\TreasuryException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * Daily rates to and from Excel: one rate per currency and day. An imported rate is proposed like one typed on the
 * screen: approved at once when the Financial Manager imports it, else waiting for them. A rate for a day that already
 * has one replaces it once approved.
 */
class DailyRatesExcel extends ExcelDataset
{
    public function __construct(private readonly DailyRates $rates) {}

    public function screen(): string
    {
        return 'alloc';
    }

    public function fileName(): string
    {
        return 'daily-rates';
    }

    public function columns(): array
    {
        return ['currency', 'day', 'rate', 'note'];
    }

    public function exportColumns(): array
    {
        return [...$this->columns(), 'status'];
    }

    public function exportRows(array $context): iterable
    {
        foreach (DailyRate::query()->orderByDesc('day')->orderBy('currency')->orderByDesc('id')->limit(5000)->cursor() as $rate) {
            yield [$rate->currency, $rate->day->toDateString(), (float) $rate->rate, (string) $rate->note, __('treasury.rate_status.'.$rate->status)];
        }
    }

    public function exampleRows(array $context): array
    {
        return array_map(fn (string $currency): array => [$currency, CarbonImmutable::today()->toDateString(), null, ''], $this->rates->currencies());
    }

    protected function check(array $cells, array $before, User $user, array $context): array
    {
        $problems = [];
        $currency = strtoupper($cells['currency']);

        if (! in_array($currency, $this->rates->currencies(), true)) {
            $problems[] = __('excel.bad_currency', ['currency' => $cells['currency'], 'allowed' => implode(', ', $this->rates->currencies())]);
        }

        if (! $this->isDay($cells['day'])) {
            $problems[] = __('excel.bad_day');
        }

        if (! is_numeric(str_replace(',', '', $cells['rate'])) || (float) str_replace(',', '', $cells['rate']) <= 0) {
            $problems[] = __('excel.bad_rate');
        }

        foreach ($before as $earlier) {
            if (strtoupper($earlier['currency']) === $currency && $earlier['day'] === $cells['day']) {
                $problems[] = __('excel.twice');
                break;
            }
        }

        $exists = $problems === [] && DailyRate::query()->where('currency', $currency)->whereDate('day', $cells['day'])->where('status', DailyRate::APPROVED)->exists();

        return ['action' => $exists ? 'update' : 'new', 'problems' => $problems];
    }

    protected function saveRow(array $cells, User $user, array $context): void
    {
        try {
            $this->rates->propose(strtoupper($cells['currency']), $cells['day'], $cells['rate'], $cells['note'], $user, Gate::forUser($user)->allows('screen', ['alloc', 'approve']));
        } catch (TreasuryException $refused) {
            throw new ExcelException($refused->problems);
        }
    }

    private function isDay(string $text): bool
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $text) : false;

        return $day instanceof CarbonImmutable && $day->format('Y-m-d') === $text;
    }
}
