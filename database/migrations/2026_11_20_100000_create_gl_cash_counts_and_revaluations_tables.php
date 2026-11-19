<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The treasury's daily control (finance decisions of 2026-11-19 and 2026-11-20, one treasury):
     *
     * - gl_cash_counts: each day's count of a cash or bank account against its book balance, with the adjustment voucher
     *   that books a shortage or an overage on 5505;
     * - gl_revaluations: the month's revaluation voucher, which values the pounds at the month's rate and the other
     *   currencies at the day's, the difference going to 5503;
     * - account 5505 cash shortages and overages, an organisation expense with no project.
     */
    public function up(): void
    {
        Schema::create('gl_cash_counts', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('account_code', 4);
            $table->decimal('book', 18, 2);
            $table->decimal('counted', 18, 2);
            $table->decimal('difference', 18, 2);
            $table->string('explanation', 500)->nullable();
            $table->string('voucher_id', 20)->nullable();
            $table->unsignedBigInteger('counted_by');
            $table->timestamps();

            $table->unique(['day', 'account_code']);
            $table->foreign('voucher_id')->references('id')->on('gl_vouchers')->nullOnDelete();
        });

        Schema::create('gl_revaluations', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7);
            $table->string('voucher_id', 20)->nullable();
            $table->unsignedBigInteger('prepared_by');
            $table->timestamps();

            $table->index('month');
            $table->foreign('voucher_id')->references('id')->on('gl_vouchers')->nullOnDelete();
        });

        if (Schema::hasTable('gl_accounts') && DB::table('gl_accounts')->where('code', '5500')->exists() && ! DB::table('gl_accounts')->where('code', '5505')->exists()) {
            DB::table('gl_accounts')->insert([
                'code' => '5505', 'parent_code' => '5500', 'level' => 'posting', 'class' => 'expenses',
                'name_en' => 'Cash shortages and overages', 'name_ar' => 'عجز وزيادة الصناديق',
                'statement_line' => 'Financial and banking costs', 'normal_side' => 'debit', 'currency' => 'Any', 'city' => null,
                'is_cash' => false, 'required_tags' => json_encode([]), 'is_active' => true,
                'usage_note' => 'The difference between a till\'s count and its book balance, from the daily cash count. An organisation expense: post it without a project.',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_revaluations');
        Schema::dropIfExists('gl_cash_counts');
    }
};
