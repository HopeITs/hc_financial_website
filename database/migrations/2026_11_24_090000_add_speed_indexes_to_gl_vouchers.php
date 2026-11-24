<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the voucher reads that run on most pages (finance request of 2026-11-24): the count of vouchers waiting
 * for approval in the menu and the "waiting for you" panel (by status), a person's own vouchers (by preparer), and the
 * voucher list filtered by type, newest first. It adds indexes only; no data changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_vouchers', function (Blueprint $table) {
            $table->index(['status', 'date']);
            $table->index(['prepared_by', 'status']);
            $table->index(['type', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('gl_vouchers', function (Blueprint $table) {
            $table->dropIndex(['status', 'date']);
            $table->dropIndex(['prepared_by', 'status']);
            $table->dropIndex(['type', 'date']);
        });
    }
};
