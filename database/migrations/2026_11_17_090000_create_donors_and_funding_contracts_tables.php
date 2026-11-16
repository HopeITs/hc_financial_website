<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Donors and their funding contracts (finance design of 2026-11-16, it-requests/2026-11-16-donors-and-funding-contracts.md).
 *
 * - A donor is kept in its own list, apart from the chart of accounts: all income goes to 4001.
 * - A contract belongs to one donor and is split over one or more projects by amount, in the contract currency.
 * - A contract has its expected instalments (some conditional) and the reports CHC owes the donor.
 * - Closing records what happens to the unspent balance, approved by the Financial Manager and then the General Manager.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_donors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('type', 24);
            $table->string('country')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 64)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('gl_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->string('donor_reference')->nullable();
            $table->foreignId('donor_id')->constrained('gl_donors')->restrictOnDelete();
            $table->string('title');
            $table->date('signed_on')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('currency', 3);
            $table->decimal('amount', 18, 2);
            $table->decimal('fixed_rate', 18, 6)->default(1);
            $table->string('restriction', 16);
            $table->boolean('is_yearly_general')->default(false);
            $table->string('status', 16)->default('draft');
            $table->string('signed_file_path')->nullable();
            $table->string('signed_file_name')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('closing_outcome', 16)->nullable();
            $table->text('closing_note')->nullable();
            $table->string('closing_file_path')->nullable();
            $table->string('closing_file_name')->nullable();
            $table->unsignedBigInteger('closing_requested_by')->nullable();
            $table->timestamp('closing_requested_at')->nullable();
            $table->unsignedBigInteger('closing_fm_by')->nullable();
            $table->timestamp('closing_fm_at')->nullable();
            $table->unsignedBigInteger('closing_gm_by')->nullable();
            $table->timestamp('closing_gm_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('gl_contract_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('gl_contracts')->cascadeOnDelete();
            $table->string('project_code', 16);
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->foreign('project_code')->references('code')->on('gl_projects')->restrictOnDelete();
            $table->unique(['contract_id', 'project_code']);
            $table->index('project_code');
        });

        Schema::create('gl_contract_instalments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('gl_contracts')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->date('due_on');
            $table->decimal('amount', 18, 2);
            $table->string('condition')->nullable();
            $table->boolean('is_conditional')->default(false);
            $table->date('condition_met_on')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'number']);
        });

        Schema::create('gl_contract_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('gl_contracts')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->date('period_from');
            $table->date('period_to');
            $table->date('due_on');
            $table->string('status', 24)->default('not_sent');
            $table->date('sent_on')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->foreignId('instalment_id')->nullable()->constrained('gl_contract_instalments')->nullOnDelete();
            $table->timestamps();

            $table->index('due_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_contract_reports');
        Schema::dropIfExists('gl_contract_instalments');
        Schema::dropIfExists('gl_contract_projects');
        Schema::dropIfExists('gl_contracts');
        Schema::dropIfExists('gl_donors');
    }
};
