<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which project accounts a project manager (role 3) looks after. The Windows program has no such link,
     * so it is kept here; no foreign key reaches the legacy tables.
     */
    public function up(): void
    {
        Schema::create('web_project_managers', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('account_id');
            $table->timestamps();

            $table->primary(['user_id', 'account_id']);
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_project_managers');
    }
};
