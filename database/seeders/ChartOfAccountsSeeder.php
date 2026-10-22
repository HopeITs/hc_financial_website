<?php

namespace Database\Seeders;

use App\Models\GlAccount;
use Illuminate\Database\Seeder;

/**
 * The accounts of the mockup chart (data/chart_of_accounts.json), including the v7 changes. Safe to run again:
 * an account is matched by its code and only the codes the file lists are touched.
 */
class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode((string) file_get_contents(__DIR__.'/data/chart_of_accounts.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($rows as $row) {
            GlAccount::query()->updateOrCreate(['code' => $row['code']], [
                'name_en' => $row['en'],
                'name_ar' => $row['ar'],
                'level' => $row['level'],
                'parent_code' => $row['parent'],
                'class' => $row['cls'],
                'statement_line' => $row['stmt'],
                'normal_side' => $row['side'],
                'currency' => $row['cur'],
                'city' => $row['city'],
                'is_cash' => $row['cash'],
                'tags' => $row['tags'],
                'usage_note' => $row['use'],
            ]);
        }
    }
}
