<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person's details beside their sign-in (finance request of 2026-11-29, the users page): full name, job title,
 * e-mail, mobile, city and notes. The Windows program's `user` table keeps only the username, password, role and
 * state, so the rest is kept here, one row per person. Only creates its own table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('web_user_profiles')) {
            return;
        }

        Schema::create('web_user_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('full_name')->nullable();
            $table->string('job_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('city')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_user_profiles');
    }
};
