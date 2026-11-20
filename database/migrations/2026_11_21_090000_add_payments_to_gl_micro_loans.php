<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Micro loans, finance decisions of 2026-11-21:
     *
     * - a loan keeps the voucher that disbursed it, to find its book dollars and to delete a loan whose disbursement still
     *   waits for approval;
     * - an instalment can be written off (forgiven): what is left of a loan moved to the project's grants line;
     * - every repayment and write-off is kept with the instalments it settled, so deleting or rejecting its voucher
     *   gives them back.
     *
     * Loans opened before keep working: they simply have no disbursement voucher and no payment rows.
     */
    public function up(): void
    {
        Schema::table('gl_micro_loans', function (Blueprint $table) {
            $table->string('disbursement_voucher_id', 20)->nullable()->after('currency');
            $table->index('disbursement_voucher_id');
        });

        Schema::table('gl_micro_instalments', function (Blueprint $table) {
            $table->decimal('written_off_amount', 18, 2)->default(0)->after('paid_amount');
        });

        Schema::create('gl_micro_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->string('voucher_id', 20);
            $table->string('kind', 12);
            $table->decimal('amount', 18, 2);
            $table->decimal('usd', 18, 2);
            $table->json('allocations');
            $table->timestamps();

            $table->foreign('loan_id')->references('id')->on('gl_micro_loans')->cascadeOnDelete();
            $table->index('voucher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_micro_payments');

        Schema::table('gl_micro_instalments', function (Blueprint $table) {
            $table->dropColumn('written_off_amount');
        });

        Schema::table('gl_micro_loans', function (Blueprint $table) {
            $table->dropIndex(['disbursement_voucher_id']);
            $table->dropColumn('disbursement_voucher_id');
        });
    }
};
