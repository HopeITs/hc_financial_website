<?php

namespace App\Services\Excel;

use App\Services\Excel\Datasets\AdvancesExcel;
use App\Services\Excel\Datasets\CashCountsExcel;
use App\Services\Excel\Datasets\ContractsExcel;
use App\Services\Excel\Datasets\DailyRatesExcel;
use App\Services\Excel\Datasets\DonorsExcel;
use App\Services\Excel\Datasets\InstalmentsExcel;
use App\Services\Excel\Datasets\MonthlyRatesExcel;

/** The kinds of record the Excel tools handle, by the name a screen gives them. */
class ExcelKinds
{
    /** @var array<string, class-string<ExcelDataset>> */
    public const KINDS = [
        'daily_rates' => DailyRatesExcel::class,
        'monthly_rates' => MonthlyRatesExcel::class,
        'donors' => DonorsExcel::class,
        'agreements' => ContractsExcel::class,
        'instalments' => InstalmentsExcel::class,
        'cash_counts' => CashCountsExcel::class,
        'advances' => AdvancesExcel::class,
    ];

    public function for(string $kind): ExcelDataset
    {
        abort_unless(isset(self::KINDS[$kind]), 404);

        return app(self::KINDS[$kind]);
    }
}
