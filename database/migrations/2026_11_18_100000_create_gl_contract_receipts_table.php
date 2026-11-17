<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Receipts linked to an agreement's instalments (finance step of 2026-11-18): which approved voucher brought which
     * instalment, for how much in the agreement currency and in dollars. One voucher may be split over instalments, and
     * one instalment may come in several vouchers. The voucher stays as it is; this only links it.
     */
    public function up(): void
    {
        Schema::create('gl_contract_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('gl_contracts')->cascadeOnDelete();
            $table->foreignId('instalment_id')->constrained('gl_contract_instalments')->cascadeOnDelete();
            $table->string('voucher_id', 20);
            $table->decimal('amount', 18, 2);
            $table->decimal('amount_usd', 18, 2);
            $table->unsignedBigInteger('linked_by');
            $table->timestamps();

            $table->foreign('voucher_id')->references('id')->on('gl_vouchers')->restrictOnDelete();
            $table->unique(['instalment_id', 'voucher_id']);
            $table->index('voucher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_contract_receipts');
    }
};
