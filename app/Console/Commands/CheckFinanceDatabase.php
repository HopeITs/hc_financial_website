<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckFinanceDatabase extends Command
{
    protected $signature = 'finance:check-db';

    protected $description = 'Check the legacy database: version, required tables and views, row counts';

    public function handle(): int
    {
        $connection = DB::connection('legacy');
        $failed = false;

        try {
            $version = $connection->table('version')->value('l_version');
        } catch (Throwable $e) {
            $this->components->error('Cannot read version.l_version: '.$e->getMessage());

            return self::FAILURE;
        }

        $expected = config('finance.legacy_version');

        if ($version !== $expected) {
            $this->components->error("Legacy version is [{$version}], expected [{$expected}].");
            $failed = true;
        } else {
            $this->components->info("Legacy version {$version}.");
        }

        $rows = [];

        foreach ([...config('finance.legacy_tables'), ...config('finance.legacy_views')] as $name) {
            try {
                $rows[] = [$name, number_format($connection->table($name)->count())];
            } catch (Throwable) {
                $rows[] = [$name, 'MISSING'];
                $failed = true;
            }
        }

        $this->table(['Table / view', 'Rows'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
