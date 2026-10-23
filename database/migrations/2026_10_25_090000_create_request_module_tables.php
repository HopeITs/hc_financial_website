<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The project managers' requests (plan 6.9): `gl_requests` (made by T2.5b for the USD 500 rule) gets the month, the
     * category that needs price quotes and the submit / reject / close details; its lines, the two-step approvals, the
     * audit trail and the quote files are new. `amount` is the request's total in USD.
     */
    public function up(): void
    {
        Schema::table('gl_requests', function (Blueprint $table) {
            $table->date('month')->nullable()->after('project_code');
            $table->string('category', 16)->nullable()->after('month');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->index(['project_code', 'month']);
        });

        Schema::create('gl_request_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->string('budget_line_key');
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->decimal('rate', 18, 6)->default(1);
            $table->decimal('amount_usd', 18, 2);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('gl_requests')->cascadeOnDelete();
            $table->foreign('budget_line_key')->references('key')->on('gl_budget_lines')->restrictOnDelete();
        });

        Schema::create('gl_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->unsignedTinyInteger('step');
            $table->unsignedSmallInteger('role_id');
            $table->string('decision', 10);
            $table->unsignedBigInteger('decided_by');
            $table->text('reason')->nullable();
            $table->timestamp('decided_at');

            $table->foreign('request_id')->references('id')->on('gl_requests')->cascadeOnDelete();
            $table->unique(['request_id', 'step']);
        });

        Schema::create('gl_request_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->string('action', 24);
            $table->string('field', 40)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->unsignedBigInteger('changed_by');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('request_id')->references('id')->on('gl_requests')->cascadeOnDelete();
            $table->index('request_id');
        });

        Schema::create('gl_request_quotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->string('original_name');
            $table->string('stored_path');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64)->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('gl_requests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_request_quotes');
        Schema::dropIfExists('gl_request_audit');
        Schema::dropIfExists('gl_request_approvals');
        Schema::dropIfExists('gl_request_lines');

        Schema::table('gl_requests', function (Blueprint $table) {
            $table->dropIndex(['project_code', 'month']);
            $table->dropColumn(['month', 'category', 'submitted_at', 'closed_at', 'reject_reason']);
        });
    }
};
