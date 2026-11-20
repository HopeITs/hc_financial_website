<?php

namespace App\Services\Excel\Datasets;

use App\Models\CashCount;
use App\Models\User;
use App\Services\Excel\ExcelDataset;
use App\Services\Excel\ExcelException;
use App\Services\Treasury\CashBook;
use App\Services\Treasury\CashCounts;
use App\Services\Treasury\TreasuryException;
use Carbon\CarbonImmutable;

/**
 * One day's cash count to and from Excel (context `day`, and `city` for the template and the export). The template
 * lists the city's accounts with their book balance; a row left without a counted amount is skipped. A count is saved
 * as on the screen: against the book balance of that day, and not over a count that has its adjustment voucher.
 */
class CashCountsExcel extends ExcelDataset
{
    public function __construct(
        private readonly CashCounts $counts,
        private readonly CashBook $book,
    ) {}

    public function screen(): string
    {
        return 'tills';
    }

    public function fileName(): string
    {
        return 'cash-count';
    }

    public function columns(): array
    {
        return ['account', 'counted', 'explanation'];
    }

    public function exportColumns(): array
    {
        return [...$this->columns(), 'account_name', 'currency', 'book', 'difference', 'voucher'];
    }

    public function exportRows(array $context): iterable
    {
        foreach ($this->counts->rows($this->day($context), (string) ($context['city'] ?? '')) as $row) {
            yield [
                $row['code'], $row['counted'] !== null ? (float) $row['counted'] : null, (string) $row['explanation'], $row['name'], $row['currency'],
                (float) $row['book'], $row['difference'] !== null ? (float) $row['difference'] : null, (string) $row['voucher'],
            ];
        }
    }

    /** The template: every account of the city, to fill in what was counted. */
    public function exampleRows(array $context): array
    {
        return array_map(fn (array $row): array => [$row['code'], null, ''], $this->counts->rows($this->day($context), (string) ($context['city'] ?? '')));
    }

    public function referenceSheets(array $context): array
    {
        return [
            __('excel.sheet.book_balances') => [
                'headings' => [__('excel.col.account'), __('excel.col.account_name'), __('excel.col.currency'), __('excel.col.book')],
                'rows' => array_map(fn (array $row): array => [$row['code'], $row['name'], $row['currency'], (float) $row['book']], $this->counts->rows($this->day($context), '')),
            ],
        ];
    }

    protected function check(array $cells, array $before, User $user, array $context): array
    {
        $day = $this->day($context);
        $problems = [];

        if ($this->book->accounts()->get($cells['account']) === null) {
            $problems[] = __('excel.bad_cash_account', ['account' => $cells['account']]);
        }

        if ($cells['counted'] === '') {
            return ['action' => 'skip', 'problems' => $problems];
        }

        if (! is_numeric(str_replace(',', '', $cells['counted'])) || (float) str_replace(',', '', $cells['counted']) < 0) {
            $problems[] = __('treasury.count_bad_amount');
        }

        if (in_array($cells['account'], array_column(array_filter($before, fn (array $earlier): bool => $earlier['counted'] !== ''), 'account'), true)) {
            $problems[] = __('excel.twice');
        }

        if ($problems === [] && $this->counts->isLocked($day, $cells['account'])) {
            $problems[] = __('excel.count_locked');
        }

        $exists = CashCount::query()->whereDate('day', $day->toDateString())->where('account_code', $cells['account'])->exists();

        return ['action' => $exists ? 'update' : 'new', 'problems' => $problems];
    }

    protected function saveRow(array $cells, User $user, array $context): void
    {
        try {
            $this->counts->save($this->day($context), $cells['account'], $cells['counted'], $cells['explanation'], $user);
        } catch (TreasuryException $refused) {
            throw new ExcelException($refused->problems);
        }
    }

    private function day(array $context): CarbonImmutable
    {
        $text = (string) ($context['day'] ?? '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) === 1 ? CarbonImmutable::parse($text) : CarbonImmutable::today();
    }
}
