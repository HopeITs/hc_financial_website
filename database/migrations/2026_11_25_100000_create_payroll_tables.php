<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The monthly payroll (finance request of 2026-11-24):
 *
 * - `gl_staff`: the staff register, one row per employee (the same id as their payroll split), with the salary of their
 *   contract, usually fixed in dollars, and the project whose payroll they are on.
 * - `gl_payrolls`: one project's payroll for a month, paid in dollars or in pounds at the rate the Financial Manager sets;
 *   approved by the Financial Manager, then paid by one allocation voucher.
 * - `gl_payroll_lines`: each employee's line, the payslip: basic pay, adjustment (bonus), unpaid leave and absence,
 *   other deductions, net pay, and how it splits over the budget lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id')->unique();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('project_code', 16);
            $table->decimal('basic_pay', 18, 2);
            $table->string('currency', 3)->default('USD');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['project_code', 'active']);
        });

        Schema::create('gl_payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('project_code', 16);
            $table->date('month');
            $table->string('pay_currency', 3)->default('SYP');
            $table->decimal('rate', 18, 6)->nullable();
            $table->string('status', 12)->default('draft');
            $table->json('approvals')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('cash_account', 10)->nullable();
            $table->string('voucher_id', 20)->nullable();
            $table->unsignedInteger('prepared_by');
            $table->timestamps();

            $table->unique(['project_code', 'month']);
            $table->index('voucher_id');
        });

        Schema::create('gl_payroll_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('gl_payrolls')->cascadeOnDelete();
            $table->unsignedInteger('employee_id');
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('currency', 3);
            $table->decimal('basic_pay', 18, 2);
            $table->decimal('adjustment', 18, 2)->default(0);
            $table->decimal('unpaid_days', 5, 2)->default(0);
            $table->decimal('unpaid_leave', 18, 2)->default(0);
            $table->decimal('other_deductions', 18, 2)->default(0);
            $table->string('notes')->nullable();
            $table->decimal('net', 18, 2);
            $table->decimal('net_paid', 18, 2);
            $table->json('allocations')->nullable();
            $table->timestamps();

            $table->unique(['payroll_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_payroll_lines');
        Schema::dropIfExists('gl_payrolls');
        Schema::dropIfExists('gl_staff');
    }
};
