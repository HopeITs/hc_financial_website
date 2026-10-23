<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demo data is for a test copy of the database only. It refuses to run in production, or on a database whose
 * name does not say it is a test or scratch copy (the real one is `hcsyria_financial`).
 */
trait GuardsDemoData
{
    protected function assertTestDatabase(): void
    {
        $database = (string) DB::connection()->getDatabaseName();
        $isTestCopy = app()->environment('testing') || preg_match('/(test|scratch)/i', $database) === 1;

        if (app()->isProduction() || ! $isTestCopy) {
            throw new RuntimeException("Demo data only goes into a test database; this one is '{$database}'.");
        }
    }
}
