<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the donor's financial report shows beyond the ledger (finance request of 2026-11-26):
 *
 * - `gl_budget_lines.category`: the budget category a line belongs to, as the donor's budget groups them;
 * - `gl_vouchers.document_type`, `document_number`, `description_en`: the supporting document of a voucher (invoice,
 *   receipt, payslip or other), its number, and the description in English for the donor;
 * - `gl_budget_line_notes`: why a budget line went over or stayed under its budget, written for a reporting period.
 *
 * Only adds columns and a table; no existing data changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_budget_lines', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
        });

        Schema::table('gl_vouchers', function (Blueprint $table) {
            $table->string('document_type', 12)->nullable()->after('description');
            $table->string('document_number', 60)->nullable()->after('document_type');
            $table->text('description_en')->nullable()->after('document_number');
        });

        Schema::create('gl_budget_line_notes', function (Blueprint $table) {
            $table->id();
            $table->string('budget_line_key');
            $table->date('period_from');
            $table->date('period_to');
            $table->text('note');
            $table->unsignedBigInteger('written_by');
            $table->timestamps();

            $table->foreign('budget_line_key')->references('key')->on('gl_budget_lines')->cascadeOnDelete();
            $table->unique(['budget_line_key', 'period_from', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_budget_line_notes');

        Schema::table('gl_vouchers', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'document_number', 'description_en']);
        });

        Schema::table('gl_budget_lines', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
