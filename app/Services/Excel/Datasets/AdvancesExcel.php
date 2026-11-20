<?php

namespace App\Services\Excel\Datasets;

use App\Models\User;
use App\Services\Entries\Advances;
use App\Services\Excel\ExcelDataset;
use LogicException;

/**
 * The open staff advances to Excel, for reading: an advance is a payment voucher and its settlement a journal voucher,
 * so they are entered on the advances screen or imported with the vouchers, and changed or deleted as vouchers.
 */
class AdvancesExcel extends ExcelDataset
{
    public function __construct(private readonly Advances $advances) {}

    public function screen(): string
    {
        return 'advance';
    }

    public function importable(): bool
    {
        return false;
    }

    public function fileName(): string
    {
        return 'open-advances';
    }

    public function columns(): array
    {
        return ['person', 'project', 'currency', 'advanced', 'settled', 'open', 'open_usd', 'rate', 'since', 'days', 'pending'];
    }

    public function exportRows(array $context): iterable
    {
        foreach ($this->advances->open() as $row) {
            yield [
                $row['person'], $row['project'], $row['currency'], (float) $row['advanced'], (float) $row['settled'], (float) $row['open'],
                (float) $row['open_usd'], (float) $row['rate'], $row['since'], $row['days'], $row['pending'] ? __('excel.yes') : __('excel.no'),
            ];
        }
    }

    protected function check(array $cells, array $before, User $user, array $context): array
    {
        throw new LogicException('Open advances are not imported.');
    }

    protected function saveRow(array $cells, User $user, array $context): void
    {
        throw new LogicException('Open advances are not imported.');
    }
}
