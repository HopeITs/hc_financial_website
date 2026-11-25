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
 * Only adds columns and a table; no existing data changes. Each step checks what is already there, so the migration
 * can run again after a stop half way: MySQL does not roll back table changes, and the first run on the server stopped
 * at the notes' unique index, whose generated name was longer than MySQL's 64 characters. It now has a short name.
 */
return new class extends Migration
{
    private const NOTES_UNIQUE = 'gl_bl_notes_line_period_unique';

    public function up(): void
    {
        if (! Schema::hasColumn('gl_budget_lines', 'category')) {
            Schema::table('gl_budget_lines', function (Blueprint $table) {
                $table->string('category')->nullable()->after('name');
            });
        }

        foreach (['document_type' => fn (Blueprint $table) => $table->string('document_type', 12)->nullable()->after('description'),
            'document_number' => fn (Blueprint $table) => $table->string('document_number', 60)->nullable()->after('document_type'),
            'description_en' => fn (Blueprint $table) => $table->text('description_en')->nullable()->after('document_number')] as $column => $add) {
            if (! Schema::hasColumn('gl_vouchers', $column)) {
                Schema::table('gl_vouchers', $add);
            }
        }

        if (! Schema::hasTable('gl_budget_line_notes')) {
            Schema::create('gl_budget_line_notes', function (Blueprint $table) {
                $table->id();
                $table->string('budget_line_key');
                $table->date('period_from');
                $table->date('period_to');
                $table->text('note');
                $table->unsignedBigInteger('written_by');
                $table->timestamps();

                $table->foreign('budget_line_key')->references('key')->on('gl_budget_lines')->cascadeOnDelete();
                $table->unique(['budget_line_key', 'period_from', 'period_to'], self::NOTES_UNIQUE);
            });

            return;
        }

        // The table made by the run that stopped: its unique index is what is missing.
        if (! Schema::hasIndex('gl_budget_line_notes', self::NOTES_UNIQUE)) {
            Schema::table('gl_budget_line_notes', function (Blueprint $table) {
                $table->unique(['budget_line_key', 'period_from', 'period_to'], self::NOTES_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_budget_line_notes');

        $columns = array_values(array_filter(['document_type', 'document_number', 'description_en'], fn (string $column): bool => Schema::hasColumn('gl_vouchers', $column)));
        if ($columns !== []) {
            Schema::table('gl_vouchers', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }

        if (Schema::hasColumn('gl_budget_lines', 'category')) {
            Schema::table('gl_budget_lines', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};
