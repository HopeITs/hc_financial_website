<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Small per-user settings of the web app (e.g. the width of the side tree). No foreign key to the
     * Windows program's `user` table, which stays read only.
     */
    public function up(): void
    {
        Schema::create('web_user_preferences', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->string('key', 64);
            $table->string('value', 255);
            $table->timestamp('updated_at')->nullable();

            $table->primary(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_user_preferences');
    }
};
