<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase files (finance request of 2026-11-24): the organisation's procurement cycle, on the forms of its process.
 *
 * - `gl_purchases`: one purchase of an approved request: what is bought (goods or a service), the supplier awarded and
 *   why, the purchase order and its approvals, the goods or service received with the supplier's invoice, and the
 *   voucher that paid it.
 * - `gl_purchase_items`: what is bought, on a budget line of the request's project; how much came and in what state.
 * - `gl_purchase_quotes` and `gl_purchase_quote_prices`: the suppliers' quotes, with a price for each item, for the
 *   comparison of the bids.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('gl_requests')->restrictOnDelete();
            $table->string('project_code', 16);
            $table->string('stage', 12)->default('sourcing');
            $table->string('kind', 8)->default('goods');
            $table->string('title');
            $table->string('location')->nullable();
            $table->string('delivery_place')->nullable();
            $table->date('needed_by')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedBigInteger('awarded_quote_id')->nullable();
            $table->text('award_reason')->nullable();
            $table->date('ordered_on')->nullable();
            $table->decimal('other_charges', 18, 2)->default(0);
            $table->json('approvals')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->date('received_on')->nullable();
            $table->string('receiving_location')->nullable();
            $table->string('invoice_number', 60)->nullable();
            $table->date('invoice_date')->nullable();
            $table->decimal('invoice_amount', 18, 2)->nullable();
            $table->boolean('accepted')->nullable();
            $table->text('receipt_remarks')->nullable();
            $table->string('payment_method', 8)->nullable();
            $table->string('voucher_id', 20)->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->index(['stage', 'project_code']);
            $table->index('voucher_id');
        });

        Schema::create('gl_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('gl_purchases')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->string('budget_line_key');
            $table->string('description');
            $table->string('unit', 40)->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('estimated_price', 18, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->decimal('received_quantity', 12, 2)->nullable();
            $table->string('condition')->nullable();
            $table->date('expected_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->timestamps();

            $table->foreign('budget_line_key')->references('key')->on('gl_budget_lines')->restrictOnDelete();
        });

        Schema::create('gl_purchase_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('gl_purchases')->cascadeOnDelete();
            $table->string('supplier');
            $table->string('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->date('quoted_on')->nullable();
            $table->string('valid_for', 60)->nullable();
            $table->string('original_name')->nullable();
            $table->string('stored_path')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
        });

        Schema::create('gl_purchase_quote_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('gl_purchase_quotes')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('gl_purchase_items')->cascadeOnDelete();
            $table->decimal('unit_price', 18, 2);
            $table->string('brand')->nullable();

            $table->unique(['quote_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_purchase_quote_prices');
        Schema::dropIfExists('gl_purchase_quotes');
        Schema::dropIfExists('gl_purchase_items');
        Schema::dropIfExists('gl_purchases');
    }
};
