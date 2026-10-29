<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The in-app notifications (U-01): one row per person and event, for the bell in the top bar. `user_id` is the
     * Windows program's `user`.`ID`; the web app never writes that table, so there is no foreign key to it.
     */
    public function up(): void
    {
        Schema::create('web_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('kind', 32);
            $table->string('subject', 40);
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_notifications');
    }
};
