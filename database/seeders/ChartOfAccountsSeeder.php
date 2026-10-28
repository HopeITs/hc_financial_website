<?php

namespace Database\Seeders;

use App\Models\GlAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the ledger's chart of accounts from the JSON file next to it.
 *
 * It only adds rows that are missing (never overwrites), so running it again cannot undo an edit made
 * in the web app. Accounts are listed parent first, which the self foreign key needs.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach ($this->load('chart_accounts') as $row) {
                GlAccount::withTrashed()->firstOrCreate(['code' => $row['code']], $row);
            }
        });
    }

    /** @return list<array<string, mixed>> */
    private function load(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/data/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
