<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Financial Manager decision R1-4: a project carries its contract number, start date and end date. The voucher
     * builder warns (never blocks) when money is spent after the end date. Nothing in the Windows program changes.
     */
    public function up(): void
    {
        Schema::table('gl_projects', function (Blueprint $table) {
            $table->string('contract_number', 64)->nullable()->after('name');
            $table->date('start_date')->nullable()->after('contract_number');
            $table->date('end_date')->nullable()->after('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('gl_projects', function (Blueprint $table) {
            $table->dropColumn(['contract_number', 'start_date', 'end_date']);
        });
    }
};
