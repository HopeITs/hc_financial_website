<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-09: the month-end count of each cash account (safe cash count, transfer company or bank statement balance) with the
 * explanation of any difference, and the log of who closed and reopened a month and why. The closed month itself stays in
 * `gl_month_closes`, which rule 8 reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_month_counts', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7);
            $table->string('account_code', 4);
            $table->decimal('counted', 18, 2);
            $table->text('explanation')->nullable();
            $table->unsignedBigInteger('entered_by');
            $table->timestamps();

            $table->unique(['month', 'account_code']);
        });

        Schema::create('gl_month_close_log', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7);
            $table->string('action', 12);
            $table->unsignedBigInteger('user_id');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_month_close_log');
        Schema::dropIfExists('gl_month_counts');
    }
};
