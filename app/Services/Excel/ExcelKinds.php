<?php

namespace App\Services\Excel;

use App\Services\Excel\Datasets\DailyRatesExcel;
use App\Services\Excel\Datasets\DonorsExcel;
use App\Services\Excel\Datasets\MonthlyRatesExcel;

/** The kinds of record the Excel tools handle, by the name a screen gives them. */
class ExcelKinds
{
    /** @var array<string, class-string<ExcelDataset>> */
    public const KINDS = [
        'daily_rates' => DailyRatesExcel::class,
        'monthly_rates' => MonthlyRatesExcel::class,
        'donors' => DonorsExcel::class,
    ];

    public function for(string $kind): ExcelDataset
    {
        abort_unless(isset(self::KINDS[$kind]), 404);

        return app(self::KINDS[$kind]);
    }
}
