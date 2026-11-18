<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One treasury for the organisation (finance decision of 2026-11-18): the cash in the tills, the transfer company and
     * the banks belongs to the organisation, and a project with its donor is named only where money comes in and where it
     * is spent. So the cash accounts no longer require a project or a fund source on their lines, and 5503 exchange
     * differences, which belong to the organisation, no longer requires a project. Other tags (Center, Contact) stay.
     */
    private const DROPPED_FROM_CASH = ['Project', 'Fund source'];

    private const EXCHANGE_DIFFERENCES = '5503';

    public function up(): void
    {
        if (! Schema::hasTable('gl_accounts')) {
            return;
        }

        foreach (DB::table('gl_accounts')->where('is_cash', true)->get(['code', 'required_tags']) as $account) {
            $this->setTags($account->code, array_values(array_diff($this->tagsOf($account->required_tags), self::DROPPED_FROM_CASH)));
        }

        $exchange = DB::table('gl_accounts')->where('code', self::EXCHANGE_DIFFERENCES)->first(['code', 'required_tags']);
        if ($exchange !== null) {
            $this->setTags($exchange->code, array_values(array_diff($this->tagsOf($exchange->required_tags), ['Project'])));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('gl_accounts')) {
            return;
        }

        foreach (DB::table('gl_accounts')->where('is_cash', true)->get(['code', 'required_tags']) as $account) {
            $this->setTags($account->code, array_values(array_unique([...self::DROPPED_FROM_CASH, ...$this->tagsOf($account->required_tags)])));
        }

        $exchange = DB::table('gl_accounts')->where('code', self::EXCHANGE_DIFFERENCES)->first(['code', 'required_tags']);
        if ($exchange !== null) {
            $this->setTags($exchange->code, array_values(array_unique(['Project', ...$this->tagsOf($exchange->required_tags)])));
        }
    }

    /** @return list<string> */
    private function tagsOf(mixed $stored): array
    {
        $tags = is_string($stored) ? json_decode($stored, true) : $stored;

        return is_array($tags) ? array_values(array_map('strval', $tags)) : [];
    }

    /** @param  list<string>  $tags */
    private function setTags(string $code, array $tags): void
    {
        DB::table('gl_accounts')->where('code', $code)->update(['required_tags' => json_encode($tags)]);
    }
};
