<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A donor's logo (finance request of 2026-11-28), shown on the papers made for that donor: the delivery protocol of a
 * micro project and the donor's financial reports. The file is kept on the private disk; only its place is stored here.
 *
 * Only adds a column, and checks first, so it can run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gl_donors', 'logo_path')) {
            Schema::table('gl_donors', function (Blueprint $table) {
                $table->string('logo_path')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gl_donors', 'logo_path')) {
            Schema::table('gl_donors', function (Blueprint $table) {
                $table->dropColumn('logo_path');
            });
        }
    }
};
