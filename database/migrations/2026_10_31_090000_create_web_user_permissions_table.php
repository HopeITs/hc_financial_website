<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permissions given to one person on top of their roles: one screen slug and one action (config/permissions.php).
     * Like `web_user_roles`, no foreign key to the Windows program's `user` table.
     */
    public function up(): void
    {
        Schema::create('web_user_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('screen', 40);
            $table->string('action', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'screen', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_user_permissions');
    }
};
