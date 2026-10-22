<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The chart of accounts of the ledger (the "Hope Center Finance" mockup). It is separate from the
     * Windows program's `account` table, which holds the project accounts that own money.
     */
    public function up(): void
    {
        Schema::create('gl_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name_en');
            $table->string('name_ar');
            $table->string('level', 8);
            $table->string('parent_code', 8)->nullable()->index();
            $table->string('class', 16);
            $table->string('statement_line')->nullable();
            $table->string('normal_side', 8);
            $table->string('currency', 8)->nullable();
            $table->string('city', 32)->nullable();
            $table->boolean('is_cash')->default(false);
            $table->string('tags')->nullable();
            $table->text('usage_note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_accounts');
    }
};
