<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The list of cities that accounts and operations are linked to, kept on the chart of accounts screen. It starts
     * with every city already written on an account or a project.
     */
    public function up(): void
    {
        Schema::create('gl_cities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('name_ar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $existing = DB::table('gl_accounts')->whereNotNull('city')->where('city', '!=', '')->pluck('city')
            ->merge(DB::table('gl_projects')->whereNotNull('city')->where('city', '!=', '')->pluck('city'))
            ->unique()->sort()->values();

        $now = now();
        foreach ($existing as $name) {
            DB::table('gl_cities')->insert(['name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_cities');
    }
};
