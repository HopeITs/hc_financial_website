<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project revenues as a point of sale (finance request of 2026-11-26):
 *
 * - `gl_revenue_tickets`: each sale of an open shift (its gross, discount and net), so a wrong sale can be voided
 *   before the shift is closed;
 * - `gl_revenue_lines.ticket_id`: the sale a line belongs to (empty for a day entered by hand);
 * - `gl_revenue_items.category`: the group an item is shown under on the sale screen ("Hot drinks", "Sandwiches").
 *
 * A shift is a sheet whose status is `open`; closing it makes its receipt voucher. Only adds; nothing existing changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gl_revenue_sheets')) {
            return;
        }

        if (! Schema::hasTable('gl_revenue_tickets')) {
            Schema::create('gl_revenue_tickets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sheet_id')->constrained('gl_revenue_sheets')->cascadeOnDelete();
                $table->unsignedInteger('number');
                $table->decimal('gross', 18, 2);
                $table->decimal('discount', 18, 2)->default(0);
                $table->decimal('net', 18, 2);
                $table->decimal('paid', 18, 2)->nullable();
                $table->string('note')->nullable();
                $table->unsignedInteger('created_by');
                $table->timestamps();

                $table->unique(['sheet_id', 'number']);
            });
        }

        if (! Schema::hasColumn('gl_revenue_lines', 'ticket_id')) {
            Schema::table('gl_revenue_lines', function (Blueprint $table) {
                $table->unsignedBigInteger('ticket_id')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('gl_revenue_items', 'category')) {
            Schema::table('gl_revenue_items', function (Blueprint $table) {
                $table->string('category', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gl_revenue_items', 'category')) {
            Schema::table('gl_revenue_items', fn (Blueprint $table) => $table->dropColumn('category'));
        }

        if (Schema::hasColumn('gl_revenue_lines', 'ticket_id')) {
            Schema::table('gl_revenue_lines', function (Blueprint $table) {
                $table->dropIndex(['ticket_id']);
                $table->dropColumn('ticket_id');
            });
        }

        Schema::dropIfExists('gl_revenue_tickets');
    }
};
