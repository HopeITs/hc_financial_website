<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The point of sale with open orders, like a restaurant's tables (finance request of 2026-11-27): a sale is opened on
 * a table (or as take-away or delivery), items are added to it over time, and it is paid when the table closes.
 *
 * - `gl_revenue_tickets`: its status (open, paid), table, service, customer, persons, payment method, waiter, note, the
 *   discount percent, when it was printed and when it was paid;
 * - `gl_revenue_lines.note`: a note on one line ("no sugar");
 * - `gl_revenue_items.code`: the item's number, typed as "3*12" (three of item 12) on the add-items window.
 *
 * Only adds; the sales already saved are `paid`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gl_revenue_tickets') && ! Schema::hasColumn('gl_revenue_tickets', 'status')) {
            Schema::table('gl_revenue_tickets', function (Blueprint $table) {
                $table->string('status', 10)->default('paid');
                $table->string('table_label', 32)->nullable();
                $table->string('service', 16)->default('hall');
                $table->string('customer')->nullable();
                $table->unsignedSmallInteger('persons')->nullable();
                $table->string('payment_method', 16)->default('cash');
                $table->string('waiter')->nullable();
                $table->decimal('discount_percent', 6, 2)->nullable();
                $table->timestamp('printed_at')->nullable();
                $table->timestamp('closed_at')->nullable();

                $table->index(['sheet_id', 'status']);
            });
        }

        if (Schema::hasTable('gl_revenue_lines') && ! Schema::hasColumn('gl_revenue_lines', 'note')) {
            Schema::table('gl_revenue_lines', fn (Blueprint $table) => $table->string('note')->nullable());
        }

        if (Schema::hasTable('gl_revenue_items') && ! Schema::hasColumn('gl_revenue_items', 'code')) {
            Schema::table('gl_revenue_items', fn (Blueprint $table) => $table->string('code', 32)->nullable()->index());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gl_revenue_items', 'code')) {
            Schema::table('gl_revenue_items', function (Blueprint $table) {
                $table->dropIndex(['code']);
                $table->dropColumn('code');
            });
        }

        if (Schema::hasColumn('gl_revenue_lines', 'note')) {
            Schema::table('gl_revenue_lines', fn (Blueprint $table) => $table->dropColumn('note'));
        }

        if (Schema::hasColumn('gl_revenue_tickets', 'status')) {
            Schema::table('gl_revenue_tickets', function (Blueprint $table) {
                $table->dropIndex(['sheet_id', 'status']);
                $table->dropColumn(['status', 'table_label', 'service', 'customer', 'persons', 'payment_method', 'waiter', 'discount_percent', 'printed_at', 'closed_at']);
            });
        }
    }
};
