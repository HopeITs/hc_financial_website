<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F-06: an audit row written by the Super User is marked, so a test-mode action is never mistaken for a real one. */
return new class extends Migration
{
    private const TABLES = ['gl_voucher_audit', 'gl_request_audit'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasTable($name) && ! Schema::hasColumn($name, 'is_super')) {
                Schema::table($name, fn (Blueprint $table) => $table->boolean('is_super')->default(false));
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasColumn($name, 'is_super')) {
                Schema::table($name, fn (Blueprint $table) => $table->dropColumn('is_super'));
            }
        }
    }
};
