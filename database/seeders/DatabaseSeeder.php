<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Accounts, roles and the Windows program's tables belong to the finance database that the program already runs on;
 * only the web-owned chart of accounts is seeded.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ChartOfAccountsSeeder::class);
    }
}
