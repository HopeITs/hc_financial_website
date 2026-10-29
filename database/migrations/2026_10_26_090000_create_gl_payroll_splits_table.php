<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How one employee's salary is split over projects and budget lines (plan 3.2, round 2 decision R2-6).
     * `valid_from` is always the first day of a month: a split applies to the whole month it starts in and to every
     * later month until a newer one starts. The percentages of one employee and one `valid_from` add up to 100.
     */
    public function up(): void
    {
        // The first version of this migration failed on MySQL after creating the table (the unique index's generated
        // name was too long), and MySQL cannot roll a CREATE TABLE back, so that empty, half-built table was left behind.
        if (Schema::hasTable('gl_payroll_splits') && DB::table('gl_payroll_splits')->doesntExist()) {
            Schema::drop('gl_payroll_splits');
        }

        Schema::create('gl_payroll_splits', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_employee_id');
            $table->string('employee_name')->nullable();
            $table->string('project_code');
            $table->string('budget_line_key');
            $table->decimal('percent', 5, 2);
            $table->date('valid_from');
            $table->timestamps();

            $table->foreign('project_code')->references('code')->on('gl_projects')->restrictOnDelete();
            $table->foreign('budget_line_key')->references('key')->on('gl_budget_lines')->restrictOnDelete();
            $table->unique(['legacy_employee_id', 'budget_line_key', 'valid_from'], 'gl_payroll_splits_employee_line_from_unique');
            $table->index(['legacy_employee_id', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_payroll_splits');
    }
};
