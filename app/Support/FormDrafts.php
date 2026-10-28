<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The saved drafts of the entry forms, one per person and screen, in the app's own `web_form_drafts` table.
 */
class FormDrafts
{
    /** A pasted sheet can be long, but a draft is never a dump of a whole ledger. */
    public const MAX_BYTES = 1_000_000;

    /** @return array{payload: array<string, mixed>, saved_at: Carbon}|null */
    public function find(int $userId, string $screen): ?array
    {
        $row = DB::table('web_form_drafts')->where('user_id', $userId)->where('screen', $screen)->first(['payload', 'saved_at']);

        if ($row === null) {
            return null;
        }

        $payload = json_decode((string) $row->payload, true);

        return is_array($payload) ? ['payload' => $payload, 'saved_at' => Carbon::parse((string) $row->saved_at)] : null;
    }

    /**
     * Keeps the draft, replacing the person's earlier one on the screen. False when it is too large to keep.
     *
     * @param  array<string, mixed>  $payload
     */
    public function save(int $userId, string $screen, array $payload): bool
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (strlen($json) > self::MAX_BYTES) {
            return false;
        }

        DB::table('web_form_drafts')->upsert(
            [['user_id' => $userId, 'screen' => $screen, 'payload' => $json, 'saved_at' => now()]],
            ['user_id', 'screen'],
            ['payload', 'saved_at'],
        );

        return true;
    }

    public function forget(int $userId, string $screen): void
    {
        DB::table('web_form_drafts')->where('user_id', $userId)->where('screen', $screen)->delete();
    }
}
