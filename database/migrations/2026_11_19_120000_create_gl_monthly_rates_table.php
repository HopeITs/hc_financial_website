<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The monthly exchange rate of each currency (finance decision of 2026-11-19): typed by the Financial Manager from
     * what the dollars were really exchanged at that month. Every invoice and expense takes it by default; until the
     * month has its own, the month before's is used. Changing it later leaves the vouchers already made as they are.
     */
    public function up(): void
    {
        Schema::create('gl_monthly_rates', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7);
            $table->string('currency', 3);
            $table->decimal('rate', 18, 6);
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('set_by');
            $table->timestamps();

            $table->unique(['month', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_monthly_rates');
    }
};
