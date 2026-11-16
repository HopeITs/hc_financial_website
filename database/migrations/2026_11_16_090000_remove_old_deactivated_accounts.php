<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Removes the accounts that the chart restructure (2026_11_13_090000) could only deactivate because they still had
 * lines: the old income accounts and groups (every income account but 4000 and 4001), the in-transit accounts
 * (11x3, 11x4), the old city groups (1110–1150) and the old bank group 1170.
 *
 * Finance deleted all financial transactions on 2026-11-16, so these accounts have no lines left. Each one is
 * soft-deleted only when it is inactive, has no voucher, budget, month-end or till-merge line and no child that
 * is still there; anything that still has lines is left as it is. Postings go first, then the groups, deepest
 * first, so a group whose children are all removed goes too.
 */
return new class extends Migration
{
    private const OLD_GROUPS = ['1110', '1120', '1130', '1140', '1150', '1170'];

    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();

            $candidates = DB::table('gl_accounts')
                ->whereNull('deleted_at')
                ->where('is_active', false)
                ->get(['code', 'class', 'level'])
                ->filter(fn (object $account): bool => $this->isOldAccount($account))
                ->sortBy([['level', 'desc'], ['code', 'desc']]);

            foreach ($candidates as $account) {
                if ($this->hasLines($account->code) || $this->hasChildren($account->code)) {
                    continue;
                }

                DB::table('gl_accounts')->where('code', $account->code)->update(['deleted_at' => $now, 'updated_at' => $now]);
            }
        });
    }

    /** The removed accounts stay soft-deleted; restore the backup taken before this migration to undo it. */
    public function down(): void {}

    private function isOldAccount(object $account): bool
    {
        return ($account->class === 'income' && ! in_array($account->code, ['4000', '4001'], true))
            || preg_match('/^11[1-5][34]$/', $account->code) === 1
            || in_array($account->code, self::OLD_GROUPS, true);
    }

    private function hasLines(string $code): bool
    {
        return DB::table('gl_voucher_lines')->where('account_code', $code)->exists()
            || DB::table('gl_budget_lines')->where('account_code', $code)->exists()
            || DB::table('gl_month_counts')->where('account_code', $code)->exists()
            || DB::table('gl_cash_till_merges')->where('from_account', $code)->orWhere('into_account', $code)->exists();
    }

    private function hasChildren(string $code): bool
    {
        return DB::table('gl_accounts')->where('parent_code', $code)->whereNull('deleted_at')->exists();
    }
};
