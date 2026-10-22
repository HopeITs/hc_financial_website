<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ledger's vouchers and everything around them: lines, audit trail, numbering, attachments, month closes,
     * allocation rates and the migration bookkeeping. No foreign key reaches the legacy tables, which stay read
     * only; user columns hold the legacy `user`.`ID` and `legacy_*_id` columns remember the source row.
     */
    public function up(): void
    {
        Schema::create('gl_vouchers', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('type', 3);
            $table->date('date');
            $table->text('description')->nullable();
            $table->string('status', 24);
            $table->unsignedBigInteger('prepared_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_carry_forward')->default(false);
            $table->boolean('is_temporary')->default(false);
            $table->string('source', 12)->default('web');
            $table->unsignedBigInteger('legacy_action_id')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['date', 'status']);
        });

        Schema::create('gl_voucher_lines', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_id', 20);
            $table->unsignedSmallInteger('line_no');
            $table->string('account_code', 4);
            $table->string('currency', 3);
            $table->decimal('debit_orig', 18, 2)->default(0);
            $table->decimal('credit_orig', 18, 2)->default(0);
            $table->decimal('rate', 18, 6)->default(1);
            $table->decimal('debit_usd', 18, 2)->default(0);
            $table->decimal('credit_usd', 18, 2)->default(0);
            $table->string('donor_currency', 3)->nullable();
            $table->decimal('debit_donor', 18, 2)->default(0);
            $table->decimal('credit_donor', 18, 2)->default(0);
            $table->string('project_code', 16)->nullable();
            $table->string('donor')->nullable();
            $table->string('budget_line_key')->nullable();
            $table->string('fund_source', 24)->nullable();
            $table->string('city')->nullable();
            $table->string('center')->nullable();
            $table->string('contact')->nullable();
            $table->string('restriction', 32)->nullable();
            $table->string('reason')->nullable();
            $table->string('lira_type', 3)->nullable();
            $table->timestamps();

            $table->foreign('voucher_id')->references('id')->on('gl_vouchers')->restrictOnDelete();
            $table->foreign('account_code')->references('code')->on('gl_accounts')->restrictOnDelete();
            $table->foreign('project_code')->references('code')->on('gl_projects')->restrictOnDelete();
            $table->foreign('budget_line_key')->references('key')->on('gl_budget_lines')->restrictOnDelete();
            $table->unique(['voucher_id', 'line_no']);
            $table->index(['account_code', 'project_code']);
        });

        $this->addDebitOrCreditCheck();

        Schema::create('gl_voucher_audit', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_id', 20);
            $table->string('action', 16);
            $table->string('field')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->unsignedBigInteger('changed_by');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('voucher_id')->references('id')->on('gl_vouchers')->restrictOnDelete();
            $table->index('voucher_id');
        });

        Schema::create('gl_voucher_sequences', function (Blueprint $table) {
            $table->string('type', 3);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->primary(['type', 'year']);
        });

        Schema::create('gl_voucher_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_id', 20);
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime', 127)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->char('sha256', 64)->nullable();
            $table->unsignedBigInteger('legacy_attachment_id')->nullable()->unique();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('voucher_id')->references('id')->on('gl_vouchers')->restrictOnDelete();
            $table->index('voucher_id');
        });

        Schema::create('gl_month_closes', function (Blueprint $table) {
            $table->string('month', 7)->primary();
            $table->timestamp('closed_at');
            $table->unsignedBigInteger('closed_by');
            $table->timestamps();
        });

        Schema::create('gl_allocation_rates', function (Blueprint $table) {
            $table->id();
            $table->string('city');
            $table->decimal('rate', 18, 6);
            $table->date('effective_from');
            $table->string('status', 12)->default('pending');
            $table->unsignedBigInteger('proposed_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->boolean('allocate_at_average_cost')->default(false);
            $table->timestamps();

            $table->index(['city', 'effective_from']);
        });

        Schema::create('gl_legacy_map', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 12);
            $table->string('legacy_key');
            $table->string('target');
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'legacy_key']);
        });

        Schema::create('gl_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 12);
            $table->string('status', 12)->default('pending');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('posted_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('issue_count')->default(0);
            $table->unsignedBigInteger('started_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gl_import_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('legacy_action_id')->nullable();
            $table->string('kind', 16);
            $table->text('message');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('batch_id')->references('id')->on('gl_import_batches')->cascadeOnDelete();
            $table->index(['batch_id', 'kind']);
        });

        Schema::create('gl_cash_till_merges', function (Blueprint $table) {
            $table->id();
            $table->string('from_account', 4);
            $table->string('into_account', 4)->nullable();
            $table->string('voucher_id', 20);
            $table->unsignedBigInteger('confirmed_by');
            $table->timestamps();

            $table->foreign('from_account')->references('code')->on('gl_accounts')->restrictOnDelete();
            $table->foreign('into_account')->references('code')->on('gl_accounts')->restrictOnDelete();
            $table->foreign('voucher_id')->references('id')->on('gl_vouchers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_cash_till_merges');
        Schema::dropIfExists('gl_import_issues');
        Schema::dropIfExists('gl_import_batches');
        Schema::dropIfExists('gl_legacy_map');
        Schema::dropIfExists('gl_allocation_rates');
        Schema::dropIfExists('gl_month_closes');
        Schema::dropIfExists('gl_voucher_attachments');
        Schema::dropIfExists('gl_voucher_sequences');
        Schema::dropIfExists('gl_voucher_audit');
        Schema::dropIfExists('gl_voucher_lines');
        Schema::dropIfExists('gl_vouchers');
    }

    /**
     * A line is a debit or a credit, never both and never negative, in every currency column pair. MariaDB and
     * MySQL get a CHECK constraint; SQLite cannot add one to an existing table, so it gets equivalent triggers.
     */
    private function addDebitOrCreditCheck(): void
    {
        $pairs = [['debit_orig', 'credit_orig'], ['debit_usd', 'credit_usd'], ['debit_donor', 'credit_donor']];
        $condition = fn (string $prefix) => collect($pairs)
            ->map(fn (array $p) => "{$prefix}{$p[0]} >= 0 AND {$prefix}{$p[1]} >= 0 AND ({$prefix}{$p[0]} = 0 OR {$prefix}{$p[1]} = 0)")
            ->implode(' AND ');

        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $event) {
                DB::statement(
                    'CREATE TRIGGER gl_voucher_lines_dr_or_cr_'.strtolower($event)." BEFORE {$event} ON gl_voucher_lines "
                    .'WHEN NOT ('.$condition('NEW.').") BEGIN SELECT RAISE(ABORT, 'gl_voucher_lines_dr_or_cr'); END"
                );
            }

            return;
        }

        DB::statement('ALTER TABLE gl_voucher_lines ADD CONSTRAINT gl_voucher_lines_dr_or_cr CHECK ('.$condition('').')');
    }
};
