<?php

namespace App\Services\Requests;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The deadline of a monthly request (Financial Manager, R4-10). A request for month M is due in the last
 * `WINDOW` working days of the month before; it is late when submitted after the last working day of that month.
 * Working days skip the weekend days of `ledger.weekend_days` (Friday and Saturday by default, as in Syria). A late
 * request is still accepted: the project manager only sees a warning.
 */
class SubmissionDeadline
{
    public const WINDOW = 5;

    /** The last working day of the month before `$month`: the day the request is due by. */
    public function dueBy(CarbonInterface $month): CarbonImmutable
    {
        return $this->lastWorkingDays($month)[0];
    }

    /** The first of the last working days of the month before `$month`: the day the submission window opens. */
    public function windowOpens(CarbonInterface $month): CarbonImmutable
    {
        $days = $this->lastWorkingDays($month);

        return $days[count($days) - 1];
    }

    public function isLate(CarbonInterface $month, CarbonInterface $submittedAt): bool
    {
        return CarbonImmutable::instance($submittedAt)->startOfDay()->greaterThan($this->dueBy($month));
    }

    /** @return list<CarbonImmutable> the last working days of the previous month, the latest first */
    private function lastWorkingDays(CarbonInterface $month): array
    {
        $weekend = array_map('intval', (array) config('ledger.weekend_days', [5, 6]));
        $day = CarbonImmutable::instance($month)->startOfMonth()->subDay()->startOfDay();
        $days = [];

        while (count($days) < self::WINDOW) {
            if (! in_array($day->dayOfWeek, $weekend, true)) {
                $days[] = $day;
            }
            $day = $day->subDay();
        }

        return $days;
    }
}
