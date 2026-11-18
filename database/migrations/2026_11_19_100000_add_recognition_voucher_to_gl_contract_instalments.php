<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The journal voucher that books an instalment as income (finance step of 2026-11-19): debit 1210 receivable from
     * donors, credit 4001 grant income, split over the agreement's projects at the fixed rate. It is prepared when the
     * agreement is approved, or when a conditional instalment's condition is met, and waits for approval like any voucher.
     */
    public function up(): void
    {
        Schema::table('gl_contract_instalments', function (Blueprint $table) {
            $table->string('recognition_voucher_id', 20)->nullable()->after('condition_met_on');

            $table->foreign('recognition_voucher_id')->references('id')->on('gl_vouchers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gl_contract_instalments', function (Blueprint $table) {
            $table->dropForeign(['recognition_voucher_id']);
            $table->dropColumn('recognition_voucher_id');
        });
    }
};
