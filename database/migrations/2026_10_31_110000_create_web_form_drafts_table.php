<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drafts of the entry forms (U-04): what a person typed on a screen and saved as a draft, one per person and
     * screen. A draft is not a voucher: it has no number and no status, and no report or approval reads it.
     * `user_id` is the Windows program's `user`.`ID`, with no foreign key to that read-only table.
     */
    public function up(): void
    {
        Schema::create('web_form_drafts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->string('screen', 32);
            $table->longText('payload');
            $table->timestamp('saved_at')->nullable();

            $table->primary(['user_id', 'screen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_form_drafts');
    }
};
