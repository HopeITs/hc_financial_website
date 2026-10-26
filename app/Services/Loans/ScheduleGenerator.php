<?php

namespace App\Services\Loans;

use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Equal instalments for a loan, one every month or every few months. Each is cut (not rounded up) to the currency's smallest unit, cents for USD and
 * whole pounds for SYP, and the last takes what is left, so the schedule always adds up to the loan. Due dates keep the
 * day of the first one and fall on the last day of a shorter month.
 */
class ScheduleGenerator
{
    /**
     * @return list<array{due_date: string, amount: string}>
     *
     * @throws LoanException
     */
    public function equalMonthly(string $loan, int $count, CarbonImmutable $firstDue, string $currency, int $intervalMonths = 1): array
    {
        $total = Money::normalize($loan);

        if (bccomp($total, '0', 2) <= 0 || $count < 1 || $count > 120 || $intervalMonths < 1 || $intervalMonths > 12 || ! in_array($currency, ['USD', 'SYP'], true)) {
            throw new LoanException([__('loans.problem.schedule')]);
        }

        $each = Money::normalize(bcdiv($total, (string) $count, $currency === 'SYP' ? 0 : 2));
        $given = '0.00';
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $amount = $i === $count - 1 ? bcsub($total, $given, 2) : $each;
            $given = bcadd($given, $amount, 2);
            $rows[] = ['due_date' => $firstDue->addMonthsNoOverflow($i * $intervalMonths)->toDateString(), 'amount' => $amount];
        }

        return $rows;
    }
}
