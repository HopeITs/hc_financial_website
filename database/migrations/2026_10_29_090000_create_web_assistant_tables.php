<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The AI assistant's conversations, kept whole (they are both its memory and its log), and the entries it
     * prepared, which wait for the person to press Save. No foreign key to the Windows program's `user` table.
     */
    public function up(): void
    {
        Schema::create('web_assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->uuid('conversation_id');
            $table->string('role', 16);
            // The content blocks exactly as the API sent or received them; they are replayed unchanged.
            $table->longText('content');
            $table->timestamp('created_at')->nullable();

            $table->index(['conversation_id', 'id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('web_assistant_proposals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->uuid('conversation_id');
            // The operation as the single entry screen takes it (see OperationEntry).
            $table->json('operation');
            $table->string('status', 16)->default('pending');
            $table->string('voucher_id', 20)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_assistant_proposals');
        Schema::dropIfExists('web_assistant_messages');
    }
};
