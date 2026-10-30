<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F-12: a reversal voucher points at the voucher it reverses and keeps the reason; the original is never edited. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_vouchers', function (Blueprint $table) {
            $table->string('reverses_id', 20)->nullable();
            $table->text('reversal_reason')->nullable();
            $table->index('reverses_id');
        });
    }

    public function down(): void
    {
        Schema::table('gl_vouchers', function (Blueprint $table) {
            $table->dropIndex(['reverses_id']);
            $table->dropColumn(['reverses_id', 'reversal_reason']);
        });
    }
};
