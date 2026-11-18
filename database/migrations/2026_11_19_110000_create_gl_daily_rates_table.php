<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The daily exchange rate of each currency for the whole organisation (finance decision of 2026-11-19, one
     * treasury): an accountant proposes it, the Financial Manager approves it. A payment in pounds takes the day's
     * approved rate, and the daily reconciliation values the cash at it. Rates are stored the way their currency is
     * quoted: pounds per dollar, dollars per euro.
     */
    public function up(): void
    {
        Schema::create('gl_daily_rates', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('currency', 3);
            $table->decimal('rate', 18, 6);
            $table->string('status', 20);
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('proposed_by');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['currency', 'day', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_daily_rates');
    }
};
