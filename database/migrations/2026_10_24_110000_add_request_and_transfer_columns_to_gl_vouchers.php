<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What plan section 3.2 lists for vouchers and lines but T2.3 left out: the transfer sub-type, the link to a
     * project manager's request (and the request table itself, needed by the USD 500 rule), and the hawala and
     * uncompensated-cash columns of a voucher line.
     */
    public function up(): void
    {
        Schema::create('gl_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 12);
            $table->string('project_code', 16);
            $table->decimal('amount', 18, 2);
            $table->string('currency', 3);
            $table->text('description')->nullable();
            $table->string('status', 12)->default('submitted');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('project_code')->references('code')->on('gl_projects')->restrictOnDelete();
            $table->index(['project_code', 'status']);
        });

        Schema::table('gl_vouchers', function (Blueprint $table) {
            $table->string('transfer_kind', 24)->nullable()->after('type');
            $table->unsignedBigInteger('request_id')->nullable()->after('transfer_kind');
            $table->index('request_id');
        });

        // SQLite cannot add a foreign key to an existing table without rebuilding it; the other drivers get it.
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('gl_vouchers', fn (Blueprint $table) => $table->foreign('request_id')->references('id')->on('gl_requests')->restrictOnDelete());
        }

        Schema::table('gl_voucher_lines', function (Blueprint $table) {
            $table->decimal('eur_amount', 18, 2)->nullable();
            $table->decimal('eur_rate', 18, 6)->nullable();
            $table->decimal('commission_usd', 18, 2)->default(0);
            $table->boolean('is_uncompensated')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('gl_voucher_lines', function (Blueprint $table) {
            $table->dropColumn(['eur_amount', 'eur_rate', 'commission_usd', 'is_uncompensated']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('gl_vouchers', fn (Blueprint $table) => $table->dropForeign(['request_id']));
        }

        Schema::table('gl_vouchers', function (Blueprint $table) {
            $table->dropIndex(['request_id']);
            $table->dropColumn(['transfer_kind', 'request_id']);
        });

        Schema::dropIfExists('gl_requests');
    }
};
