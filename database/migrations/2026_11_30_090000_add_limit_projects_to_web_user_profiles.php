<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a person sees and enters only the projects given to them (finance request of 2026-11-30): ticked on the users
 * page for anyone, whatever the role; project managers are limited anyway. Off for everyone at first, so nobody loses
 * sight of a project on the day this runs. Only adds a column, and checks first.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('web_user_profiles') && ! Schema::hasColumn('web_user_profiles', 'limit_projects')) {
            Schema::table('web_user_profiles', function (Blueprint $table) {
                $table->boolean('limit_projects')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('web_user_profiles', 'limit_projects')) {
            Schema::table('web_user_profiles', function (Blueprint $table) {
                $table->dropColumn('limit_projects');
            });
        }
    }
};
