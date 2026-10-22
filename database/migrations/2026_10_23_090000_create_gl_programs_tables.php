<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Programs added from the web (the Windows program's `project` table is never written) and the
     * links that file a legacy project (`subproject`) under a legacy or web program. No foreign key
     * reaches the legacy tables, which stay read only.
     */
    public function up(): void
    {
        Schema::create('gl_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('gl_program_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('sub_project_id')->primary();
            $table->string('program_source', 8);
            $table->unsignedBigInteger('program_id');
            $table->unsignedBigInteger('linked_by')->nullable();
            $table->timestamps();

            $table->index(['program_source', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_program_projects');
        Schema::dropIfExists('gl_programs');
    }
};
