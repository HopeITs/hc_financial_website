<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles that exist only in the web app (the General Manager). The legacy `role` table stays read only,
     * and there is no foreign key to the Windows program's `user` table.
     */
    public function up(): void
    {
        Schema::create('web_user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedSmallInteger('role_id');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_user_roles');
    }
};
