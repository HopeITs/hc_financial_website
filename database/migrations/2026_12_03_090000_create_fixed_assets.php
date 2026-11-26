<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The warehouses and the fixed-asset register (finance request of 2026-11-28): the organisation's fixed assets (700 USD
 * or more, accounts 1401 to 1408), where each one is, who holds it, its count, its monthly depreciation and its end.
 *
 * - `gl_warehouses`: the stores and places assets are kept in;
 * - `gl_asset_categories`: a kind of asset, its fixed-asset account and its useful life (seeded from 1401 to 1408);
 * - `gl_assets`: one row per asset, with its tag, cost (in its currency and in dollars), project, center, place and
 *   holder, and its state;
 * - `gl_asset_movements`: everything that happened to an asset (received, handed over, returned, moved, repaired,
 *   counted, depreciated, written off, sold), with the voucher it made;
 * - `gl_asset_counts` and `gl_asset_count_lines`: a count of a warehouse, asset by asset (found or missing, condition).
 *
 * Only creates its own tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gl_warehouses')) {
            Schema::create('gl_warehouses', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('city')->nullable();
                $table->string('center')->nullable();
                $table->string('keeper')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gl_asset_categories')) {
            Schema::create('gl_asset_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name_en');
                $table->string('name_ar');
                $table->string('account_code', 4);
                $table->unsignedSmallInteger('life_months');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });

            $now = now();
            DB::table('gl_asset_categories')->insert(array_map(fn (array $row): array => [
                'name_en' => $row[1], 'name_ar' => $row[2], 'account_code' => $row[0], 'life_months' => $row[3], 'active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ], [
                ['1401', 'Office and center renovation', 'ترميم المكاتب والمراكز', 60],
                ['1402', 'Furniture and fixtures', 'الأثاث والتجهيزات', 60],
                ['1403', 'IT equipment', 'أجهزة الحاسوب والشبكات', 36],
                ['1404', 'Generators, solar and backup power', 'المولدات والطاقة الشمسية', 60],
                ['1405', 'Air conditioners', 'المكيفات', 60],
                ['1406', 'Office and program equipment', 'معدات المكاتب والبرامج', 60],
                ['1407', 'Software and system licenses', 'البرمجيات والتراخيص', 36],
                ['1408', 'Vehicles', 'السيارات', 60],
            ]));
        }

        if (! Schema::hasTable('gl_assets')) {
            Schema::create('gl_assets', function (Blueprint $table) {
                $table->id();
                $table->string('tag', 32)->unique();
                $table->string('name');
                $table->foreignId('category_id')->constrained('gl_asset_categories');
                $table->string('account_code', 4);
                $table->string('project_code', 16);
                $table->string('center')->nullable();
                $table->foreignId('warehouse_id')->nullable()->constrained('gl_warehouses')->nullOnDelete();
                $table->string('custodian')->nullable();
                $table->string('serial')->nullable();
                $table->string('description')->nullable();
                $table->date('acquired_on');
                $table->decimal('cost', 18, 2);
                $table->string('currency', 3);
                $table->decimal('rate', 18, 6)->default(1);
                $table->decimal('cost_usd', 18, 2);
                $table->decimal('salvage_usd', 18, 2)->default(0);
                $table->unsignedSmallInteger('life_months');
                $table->string('depreciation_from', 7);
                $table->string('status', 16)->default('in_store');
                $table->string('condition', 16)->default('good');
                $table->string('source', 16)->default('opening');
                $table->unsignedBigInteger('purchase_id')->nullable();
                $table->unsignedBigInteger('purchase_item_id')->nullable();
                $table->string('voucher_id', 20)->nullable();
                $table->date('ended_on')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by');
                $table->timestamps();

                $table->index(['project_code', 'status']);
                $table->index('purchase_item_id');
            });
        }

        if (! Schema::hasTable('gl_asset_movements')) {
            Schema::create('gl_asset_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_id')->constrained('gl_assets')->cascadeOnDelete();
                $table->string('kind', 16);
                $table->date('happened_on');
                $table->string('month', 7)->nullable();
                $table->unsignedBigInteger('from_warehouse_id')->nullable();
                $table->unsignedBigInteger('to_warehouse_id')->nullable();
                $table->string('from_custodian')->nullable();
                $table->string('to_custodian')->nullable();
                $table->string('condition', 16)->nullable();
                $table->decimal('amount_usd', 18, 2)->nullable();
                $table->string('previous_status', 16)->nullable();
                $table->string('voucher_id', 20)->nullable();
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by');
                $table->timestamps();

                $table->index(['asset_id', 'kind']);
                $table->index(['kind', 'month']);
                $table->index('voucher_id');
            });
        }

        if (! Schema::hasTable('gl_asset_counts')) {
            Schema::create('gl_asset_counts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('warehouse_id')->nullable()->constrained('gl_warehouses')->nullOnDelete();
                $table->date('counted_on');
                $table->string('status', 10)->default('open');
                $table->string('counted_by_name')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by');
                $table->timestamps();
            });

            Schema::create('gl_asset_count_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('count_id')->constrained('gl_asset_counts')->cascadeOnDelete();
                $table->foreignId('asset_id')->constrained('gl_assets')->cascadeOnDelete();
                $table->boolean('found')->nullable();
                $table->string('condition', 16)->nullable();
                $table->string('note')->nullable();

                $table->unique(['count_id', 'asset_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_asset_count_lines');
        Schema::dropIfExists('gl_asset_counts');
        Schema::dropIfExists('gl_asset_movements');
        Schema::dropIfExists('gl_assets');
        Schema::dropIfExists('gl_asset_categories');
        Schema::dropIfExists('gl_warehouses');
    }
};
