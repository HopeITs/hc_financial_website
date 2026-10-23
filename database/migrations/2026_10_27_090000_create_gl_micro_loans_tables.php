<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Micro projects (plan Q13): a loan and a grant per beneficiary and the schedule of its instalments.
     * A paid instalment points at the voucher that recorded the payment; only an approved voucher counts as collected.
     */
    public function up(): void
    {
        Schema::create('gl_micro_loans', function (Blueprint $table) {
            $table->id();
            $table->string('beneficiary');
            $table->string('project_code', 16);
            $table->decimal('loan_amount', 18, 2)->default(0);
            $table->decimal('grant_amount', 18, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedBigInteger('legacy_micro_project_id')->nullable()->unique();
            $table->timestamps();

            $table->foreign('project_code')->references('code')->on('gl_projects')->restrictOnDelete();
            $table->index('project_code');
        });

        Schema::create('gl_micro_instalments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->date('due_date');
            $table->decimal('amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->string('paid_voucher_id', 20)->nullable();
            $table->timestamps();

            $table->foreign('loan_id')->references('id')->on('gl_micro_loans')->cascadeOnDelete();
            $table->foreign('paid_voucher_id')->references('id')->on('gl_vouchers')->restrictOnDelete();
            $table->index(['loan_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_micro_instalments');
        Schema::dropIfExists('gl_micro_loans');
    }
};
