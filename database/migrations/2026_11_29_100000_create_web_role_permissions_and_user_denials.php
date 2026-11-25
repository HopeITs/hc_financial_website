<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles and permissions changed on the users page (finance request of 2026-11-29):
 *
 * - `web_role_permissions`: what a role allows, once changed on the page; it then replaces that role's list in
 *   config/permissions.php. A role never changed keeps the list from the file. A row with screen `*` marks a role as
 *   changed, so a role emptied on purpose stays empty.
 * - `web_user_denials`: an action a person's role allows that is taken from this one person.
 *
 * Only creates its own tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('web_role_permissions')) {
            Schema::create('web_role_permissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('role_id');
                $table->string('screen', 40);
                $table->string('action', 12);
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamps();

                $table->unique(['role_id', 'screen', 'action']);
            });
        }

        if (! Schema::hasTable('web_user_denials')) {
            Schema::create('web_user_denials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('screen', 40);
                $table->string('action', 12);

                $table->unique(['user_id', 'screen', 'action']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('web_user_denials');
        Schema::dropIfExists('web_role_permissions');
    }
};
