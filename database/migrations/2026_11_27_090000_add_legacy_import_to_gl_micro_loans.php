<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The old micro projects brought in for follow-up (finance request of 2026-11-27): about 3,000 loans given before the
 * web program, imported from Excel with their schedule and what was repaid, with no voucher (their balance enters the
 * books through the opening balances).
 *
 * - `imported_at`: when the loan was imported; null for a loan opened in the program.
 * - `legacy_paid`: what the beneficiary had repaid before the import, in the loan's currency, so the last repayment
 *   clears the loan's book dollars exactly.
 *
 * Only adds columns; each step checks what is there, so it can run again after a stop half way.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gl_micro_loans', 'imported_at')) {
            Schema::table('gl_micro_loans', function (Blueprint $table) {
                $table->timestamp('imported_at')->nullable();
            });
        }

        if (! Schema::hasColumn('gl_micro_loans', 'legacy_paid')) {
            Schema::table('gl_micro_loans', function (Blueprint $table) {
                $table->decimal('legacy_paid', 18, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (['legacy_paid', 'imported_at'] as $column) {
            if (Schema::hasColumn('gl_micro_loans', $column)) {
                Schema::table('gl_micro_loans', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
