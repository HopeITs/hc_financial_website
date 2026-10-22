<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The ledger's chart of accounts, its projects (the owners of money) and their budget lines. No foreign
     * key reaches the legacy tables, which stay read only; `legacy_*_id` columns only remember the source row.
     */
    public function up(): void
    {
        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->string('code', 4)->primary();
            $table->string('parent_code', 4)->nullable();
            $table->string('level', 8);
            $table->string('class', 16);
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('statement_line')->nullable();
            $table->string('normal_side', 6);
            $table->string('currency', 3)->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_cash')->default(false);
            $table->json('required_tags')->nullable();
            $table->text('usage_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_code')->references('code')->on('gl_accounts')->restrictOnDelete();
            $table->index(['class', 'level']);
            $table->index('parent_code');
        });

        Schema::create('gl_projects', function (Blueprint $table) {
            $table->string('code', 16)->primary();
            $table->string('name');
            $table->string('donor')->nullable();
            $table->string('restriction', 32);
            $table->string('city')->nullable();
            $table->string('donor_currency', 3)->default('USD');
            $table->decimal('donor_rate', 18, 6)->default(1);
            $table->unsignedBigInteger('legacy_account_id')->nullable()->unique();
            $table->boolean('is_general_account')->default(false);
            $table->timestamps();
        });

        Schema::create('gl_budget_lines', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('project_code', 16);
            $table->string('donor')->nullable();
            $table->string('name');
            $table->string('account_code', 4);
            $table->decimal('budget', 18, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedBigInteger('legacy_budget_line_id')->nullable()->unique();
            $table->timestamps();

            $table->foreign('project_code')->references('code')->on('gl_projects')->restrictOnDelete();
            $table->foreign('account_code')->references('code')->on('gl_accounts')->restrictOnDelete();
            $table->index('project_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_budget_lines');
        Schema::dropIfExists('gl_projects');
        Schema::dropIfExists('gl_accounts');
    }
};
