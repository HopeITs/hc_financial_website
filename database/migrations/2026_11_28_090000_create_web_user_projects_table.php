<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The projects a person may see, kept by the web program itself (finance decision of 2026-11-27, leaving the Windows
 * program): until now they came from the Windows program's `user_accounts` links, which nobody will keep up once it
 * stops, and from `web_project_managers`. Both are copied in once here, by the project's code, with the same rules the
 * old reading had:
 * - every person: the project accounts of the sub-projects given to them, and the projects given on the web;
 * - an accountant: also the project accounts named for them.
 *
 * Only creates and fills its own table; the old links are left as they are. It can run again after a stop half way.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('web_user_projects')) {
            Schema::create('web_user_projects', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id');
                $table->string('project_code', 16);
                $table->timestamps();

                $table->primary(['user_id', 'project_code']);
                $table->index('project_code');
            });
        }

        $codes = DB::table('gl_projects')->whereNotNull('legacy_account_id')->pluck('code', 'legacy_account_id')->all();
        $links = [];
        $add = function (int $userId, int $accountId) use (&$links, $codes): void {
            if (isset($codes[$accountId])) {
                $links[$userId.'|'.$codes[$accountId]] = ['user_id' => $userId, 'project_code' => $codes[$accountId]];
            }
        };

        if (Schema::hasTable('web_project_managers')) {
            foreach (DB::table('web_project_managers')->get(['user_id', 'account_id']) as $row) {
                $add((int) $row->user_id, (int) $row->account_id);
            }
        }

        if (Schema::hasTable('user_accounts') && Schema::hasTable('account')) {
            $bySubProject = DB::table('user_accounts as ua')
                ->join('account as a', 'a.SubProject_ID', '=', 'ua.subproject_id')
                ->whereNull('ua.account_id')->whereNotNull('ua.subproject_id')
                ->get(['ua.user_id', 'a.ID as account_id']);

            foreach ($bySubProject as $row) {
                $add((int) $row->user_id, (int) $row->account_id);
            }

            if (Schema::hasTable('accounttype') && Schema::hasTable('user')) {
                $accountants = DB::table('user')->where('Role', 5)->pluck('ID')
                    ->merge(Schema::hasTable('web_user_roles') ? DB::table('web_user_roles')->where('role_id', 5)->pluck('user_id') : [])
                    ->map(fn ($id): int => (int) $id)->unique()->all();

                $named = DB::table('user_accounts as ua')
                    ->join('account as a', 'a.ID', '=', 'ua.account_id')
                    ->join('accounttype as t', 't.id', '=', 'a.Accounttype_id')
                    ->whereIn('ua.user_id', $accountants)
                    ->whereRaw('lower(t.name) = ?', ['project'])
                    ->get(['ua.user_id', 'a.ID as account_id']);

                foreach ($named as $row) {
                    $add((int) $row->user_id, (int) $row->account_id);
                }
            }
        }

        $now = now();
        foreach (array_chunk(array_values($links), 500) as $chunk) {
            DB::table('web_user_projects')->insertOrIgnore(array_map(fn (array $link): array => $link + ['created_at' => $now, 'updated_at' => $now], $chunk));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('web_user_projects');
    }
};
