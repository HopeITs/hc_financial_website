<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring payments (finance decision of 2026-11-24): rent and the like, paid by themselves on their day. Each one is a
 * payee, an amount on a project's budget line, how often it is paid and when next; its payment voucher is made on that
 * day from its cash account and waits for approval like any other.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_recurring_payments', function (Blueprint $table) {
            $table->id();
            $table->string('project_code', 16);
            $table->string('budget_line_key');
            $table->string('payee');
            $table->string('description')->nullable();
            $table->string('currency', 3);
            $table->decimal('amount', 18, 2);
            $table->unsignedTinyInteger('every_months')->default(1);
            $table->date('next_due');
            $table->date('ends_on')->nullable();
            $table->string('cash_account', 10);
            $table->string('center')->nullable();
            $table->unsignedBigInteger('request_id')->nullable();
            $table->boolean('active')->default(true);
            $table->string('last_voucher_id', 20)->nullable();
            $table->text('last_problem')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->foreign('budget_line_key')->references('key')->on('gl_budget_lines')->restrictOnDelete();
            $table->index(['active', 'next_due']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_recurring_payments');
    }
};
