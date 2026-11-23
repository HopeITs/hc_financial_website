<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Micro-project files (finance decisions of 2026-11-23, from the micro-projects procedure). A beneficiary is first a
     * file with no voucher: details, the purchase list and the price quotes, then a payment order (طلب صرف) that the
     * Program Manager, the Financial Manager and, above the threshold, the General Manager approve. The centre buys the
     * equipment itself, by direct payment to the merchant or through an advance to the purchasing officer; each invoice
     * is kept on the file. The loan opens when the beneficiary signs the receipt protocol.
     *
     * - `gl_micro_loans` gets the file's stage and details; loans opened before stay `active`.
     * - `gl_micro_items` is the purchase list, `gl_micro_quotes` the quotes, `gl_micro_purchases` the advances,
     *   invoices and returned advances with their vouchers.
     * - A voucher can belong to a file (`gl_vouchers.micro_loan_id`): an approved file stands for the request a payment
     *   above the limit needs (rule 14).
     * - 1201 (operational advances) no longer requires a project, since an advance for a file funded from an instalment
     *   fund has none; the advances screen still asks for one.
     */
    public function up(): void
    {
        Schema::table('gl_micro_loans', function (Blueprint $table) {
            $table->string('stage', 12)->default('active')->after('id');
            $table->string('name_en')->nullable()->after('beneficiary');
            $table->string('project_number', 40)->nullable()->after('name_en');
            $table->string('profession')->nullable()->after('project_number');
            $table->string('address')->nullable()->after('profession');
            $table->decimal('rate', 18, 6)->nullable()->after('currency');
            $table->decimal('requested_amount', 18, 2)->nullable()->after('rate');
            $table->decimal('seizure_fee', 18, 2)->default(0)->after('requested_amount');
            $table->decimal('extra_amount', 18, 2)->default(0)->after('seizure_fee');
            $table->string('extra_reason')->nullable()->after('extra_amount');
            $table->decimal('recovery_percent', 5, 2)->default(50)->after('extra_reason');
            $table->decimal('instalment_amount', 18, 2)->nullable()->after('recovery_percent');
            $table->string('fund_source', 24)->nullable()->after('instalment_amount');
            $table->json('approvals')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('return_reason')->nullable();
            $table->decimal('trust_bond_amount', 18, 2)->nullable();
            $table->date('protocol_signed_on')->nullable();
            $table->date('loan_date')->nullable();
            $table->decimal('book_usd', 18, 2)->nullable();
            $table->string('closing_voucher_id', 20)->nullable();
            $table->unsignedInteger('created_by')->nullable();

            $table->index('stage');
        });

        Schema::create('gl_micro_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('gl_micro_loans')->cascadeOnDelete();
            $table->string('name');
            $table->string('kind')->nullable();
            $table->decimal('quantity', 12, 2)->default(1);
            $table->string('condition', 8)->default('new');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('gl_micro_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('gl_micro_loans')->cascadeOnDelete();
            $table->string('supplier');
            $table->string('source', 12);
            $table->date('quoted_on')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->boolean('chosen')->default(false);
            $table->string('original_name')->nullable();
            $table->string('stored_path')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
        });

        Schema::create('gl_micro_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('gl_micro_loans')->cascadeOnDelete();
            $table->string('kind', 10);
            $table->foreignId('advance_id')->nullable()->constrained('gl_micro_purchases')->nullOnDelete();
            $table->string('person')->nullable();
            $table->string('cash_account', 10)->nullable();
            $table->boolean('is_seizure')->default(false);
            $table->string('merchant')->nullable();
            $table->string('number', 60)->nullable();
            $table->date('purchase_date');
            $table->string('description_ar')->nullable();
            $table->string('description_en')->nullable();
            $table->string('currency', 3);
            $table->decimal('amount', 18, 2);
            $table->decimal('rate', 18, 6);
            $table->decimal('loan_amount', 18, 2)->default(0);
            $table->decimal('usd', 18, 2);
            $table->string('voucher_id', 20);
            $table->string('original_name')->nullable();
            $table->string('stored_path')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->index(['loan_id', 'kind']);
        });

        Schema::table('gl_vouchers', function (Blueprint $table) {
            $table->unsignedBigInteger('micro_loan_id')->nullable()->after('request_id');
            $table->index('micro_loan_id');
        });

        if (Schema::hasTable('gl_accounts')) {
            DB::table('gl_accounts')->where('code', '1201')->update(['required_tags' => json_encode(['Contact'])]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('gl_accounts')) {
            DB::table('gl_accounts')->where('code', '1201')->update(['required_tags' => json_encode(['Project', 'Contact'])]);
        }

        Schema::table('gl_vouchers', function (Blueprint $table) {
            $table->dropIndex(['micro_loan_id']);
            $table->dropColumn('micro_loan_id');
        });

        Schema::dropIfExists('gl_micro_purchases');
        Schema::dropIfExists('gl_micro_quotes');
        Schema::dropIfExists('gl_micro_items');

        Schema::table('gl_micro_loans', function (Blueprint $table) {
            $table->dropIndex(['stage']);
            $table->dropColumn([
                'stage', 'name_en', 'project_number', 'profession', 'address', 'rate', 'requested_amount', 'seizure_fee', 'extra_amount',
                'extra_reason', 'recovery_percent', 'instalment_amount', 'fund_source', 'approvals', 'submitted_at', 'approved_at',
                'return_reason', 'trust_bond_amount', 'protocol_signed_on', 'loan_date', 'book_usd', 'closing_voucher_id', 'created_by',
            ]);
        });
    }
};
