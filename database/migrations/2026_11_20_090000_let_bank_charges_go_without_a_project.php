<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A bank's fee on a donor's money can be the organisation's own cost (finance decision of 2026-11-20): 5501 bank
     * charges no longer requires a project and a budget line. A fee charged to a project still names them.
     */
    private const BANK_CHARGES = '5501';

    public function up(): void
    {
        if (Schema::hasTable('gl_accounts')) {
            DB::table('gl_accounts')->where('code', self::BANK_CHARGES)->update(['required_tags' => json_encode([])]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gl_accounts')) {
            DB::table('gl_accounts')->where('code', self::BANK_CHARGES)->update(['required_tags' => json_encode(['Project', 'Budget line'])]);
        }
    }
};
