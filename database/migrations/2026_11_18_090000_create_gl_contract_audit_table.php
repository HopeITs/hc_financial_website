<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The audit trail of agreements (finance decision of 2026-11-17): every budget line added, removed or imported, and
     * every agreement deleted, with who, when and whether the agreement was already approved. The agreement is kept by id
     * and number without a foreign key, so its trail outlives a deleted agreement.
     */
    public function up(): void
    {
        Schema::create('gl_contract_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contract_id');
            $table->string('contract_number', 20);
            $table->string('action', 32);
            $table->string('project_code', 16)->nullable();
            $table->string('budget_line_key')->nullable();
            $table->text('details')->nullable();
            $table->boolean('after_approval')->default(false);
            $table->unsignedBigInteger('changed_by');
            $table->timestamp('created_at')->useCurrent();

            $table->index('contract_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_contract_audit');
    }
};
