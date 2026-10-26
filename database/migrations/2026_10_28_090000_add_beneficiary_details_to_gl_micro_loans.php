<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The beneficiary details the Windows program keeps next to a micro loan (`beneficiary`): national number, mobile,
     * partners, the months between instalments and the date of the first one. All optional, so loans opened before keep working.
     */
    public function up(): void
    {
        Schema::table('gl_micro_loans', function (Blueprint $table) {
            $table->string('national_number', 30)->nullable()->after('beneficiary');
            $table->string('mobile', 50)->nullable()->after('national_number');
            $table->string('partners')->nullable()->after('mobile');
            $table->unsignedTinyInteger('payment_interval_months')->default(1)->after('currency');
            $table->date('first_payment_date')->nullable()->after('payment_interval_months');
        });
    }

    public function down(): void
    {
        Schema::table('gl_micro_loans', function (Blueprint $table) {
            $table->dropColumn(['national_number', 'mobile', 'partners', 'payment_interval_months', 'first_payment_date']);
        });
    }
};
