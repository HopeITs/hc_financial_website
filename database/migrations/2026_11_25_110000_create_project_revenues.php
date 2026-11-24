<?php

use App\Services\Entries\ProjectRevenues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Project revenues (finance request of 2026-11-24): what the projects earn themselves, such as Study Zone's drinks and
 * food, students' subscriptions, internet and DISC's paid courses, in place of the separate sales program.
 *
 * - A new income account, 4002 "Income from project activities", beside 4001 (grants and donations).
 * - `gl_revenue_items`: each project's price list (a product or service, its kind and price).
 * - `gl_revenue_sheets` and `gl_revenue_lines`: a day's takings of a project into a cash account, line by line; each
 *   sheet is one receipt voucher on 4002.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Under the income group; on an empty chart (a fresh database before its seeder) the revenues screen adds it.
        if (DB::table('gl_accounts')->where('code', '4000')->exists()) {
            DB::table('gl_accounts')->insertOrIgnore(ProjectRevenues::account() + ['created_at' => $now, 'updated_at' => $now]);
        }

        Schema::create('gl_revenue_items', function (Blueprint $table) {
            $table->id();
            $table->string('project_code', 16);
            $table->string('name');
            $table->string('kind', 16);
            $table->decimal('unit_price', 18, 2);
            $table->string('currency', 3);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['project_code', 'active']);
        });

        Schema::create('gl_revenue_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('project_code', 16);
            $table->date('day');
            $table->string('cash_account', 10);
            $table->string('currency', 3);
            $table->decimal('total', 18, 2);
            $table->string('status', 10)->default('posted');
            $table->string('voucher_id', 20)->nullable();
            $table->string('notes')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->index(['day', 'project_code']);
            $table->index('voucher_id');
        });

        Schema::create('gl_revenue_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sheet_id')->constrained('gl_revenue_sheets')->cascadeOnDelete();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('kind', 16);
            $table->string('description');
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('amount', 18, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_revenue_lines');
        Schema::dropIfExists('gl_revenue_sheets');
        Schema::dropIfExists('gl_revenue_items');

        if (! DB::table('gl_voucher_lines')->where('account_code', '4002')->exists()) {
            DB::table('gl_accounts')->where('code', '4002')->delete();
        }
    }
};
