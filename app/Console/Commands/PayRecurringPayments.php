<?php

namespace App\Console\Commands;

use App\Services\Entries\RecurringPayments;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Makes the payment vouchers of the recurring payments due today or before (rent and the like), each waiting for
 * approval. Scheduled daily in routes/console.php.
 */
class PayRecurringPayments extends Command
{
    protected $signature = 'payments:recurring';

    protected $description = 'Make the payment vouchers of the recurring payments that are due (rent and the like)';

    public function handle(RecurringPayments $payments): int
    {
        $result = $payments->payDue(CarbonImmutable::today());

        $this->components->info("Recurring payments: {$result['paid']} paid, {$result['refused']} refused (see the screen for why).");

        return self::SUCCESS;
    }
}
