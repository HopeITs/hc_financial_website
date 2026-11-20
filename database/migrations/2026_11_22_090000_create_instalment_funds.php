<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instalment funds (finance decisions of 2026-11-22): the repayments of the micro loans stay the funder's, in the
     * city's cash they came into, and can fund new beneficiaries with no project, on the funder and the city only.
     *
     * - A loan keeps its funder and city, and a loan funded from an instalment fund has no project.
     * - 1204 (loans) and 5413 (micro grants) no longer require a project and a budget line, since such a loan has
     *   neither; a loan from a project still names them.
     * - The Financial Manager can exchange a fund's pounds with the organisation: "fixed" in dollars and given back in
     *   pounds later, or exchanged for good (to fund a loan in dollars). Each exchange is kept with its vouchers.
     */
    public function up(): void
    {
        Schema::table('gl_micro_loans', function (Blueprint $table) {
            $table->string('project_code', 16)->nullable()->change();
            $table->string('funder')->nullable()->after('project_code');
            $table->string('city')->nullable()->after('funder');
        });

        if (Schema::hasTable('gl_accounts')) {
            DB::table('gl_accounts')->where('code', '1204')->update(['required_tags' => json_encode(['Contact', 'Fund source'])]);
            DB::table('gl_accounts')->where('code', '5413')->update(['required_tags' => json_encode(['Contact'])]);
        }

        Schema::create('gl_fund_exchanges', function (Blueprint $table) {
            $table->id();
            $table->string('funder');
            $table->string('city');
            $table->string('kind', 12);
            $table->decimal('syp_amount', 18, 2);
            $table->decimal('usd_amount', 18, 2);
            $table->decimal('rate', 18, 6);
            $table->date('fixed_on');
            $table->string('syp_account', 10);
            $table->string('usd_account', 10);
            $table->string('voucher_id', 20);
            $table->date('returned_on')->nullable();
            $table->decimal('return_rate', 18, 6)->nullable();
            $table->string('return_voucher_id', 20)->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();

            $table->index(['funder', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_fund_exchanges');

        if (Schema::hasTable('gl_accounts')) {
            DB::table('gl_accounts')->where('code', '1204')->update(['required_tags' => json_encode(['Project', 'Contact', 'Fund source'])]);
            DB::table('gl_accounts')->where('code', '5413')->update(['required_tags' => json_encode(['Project', 'Budget line', 'Contact'])]);
        }

        Schema::table('gl_micro_loans', function (Blueprint $table) {
            $table->dropColumn(['funder', 'city']);
        });
    }
};
