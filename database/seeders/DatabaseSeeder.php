<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Accounts and roles belong to the finance database that the Windows program already runs on;
 * only the ledger's chart of accounts is seeded here.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ChartOfAccountsSeeder::class);
    }
}
