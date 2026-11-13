<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Finance's new layout of the chart of accounts, applied to a chart that was already seeded.
 *
 * - Cash is grouped by currency first: the city groups (1110–1150) give way to "Cash – USD" (1101) and
 *   "Cash – SYP" (1102), and each safe and till moves under the group of its currency.
 * - The transfer company (1160, with 1161) becomes its own group directly under Assets.
 * - The "in transit from the transfer company" accounts (11x3, 11x4) are removed.
 * - Income is a single account, 4001 "Accounts receivable"; every other income account and group is removed.
 *
 * Account codes do not change. A removed account that already has lines (vouchers, budget lines or month-end
 * counts) is only deactivated, so its history stays readable; an unused one is soft-deleted.
 */
return new class extends Migration
{
    private const CITY_HEADERS = ['1110', '1120', '1130', '1140', '1150'];

    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();

            $this->insertIfMissing($this->header('1101', '1100', 'Cash – USD', 'النقد – دولار أمريكي', 'Cash and cash equivalents'), $now);
            $this->insertIfMissing($this->header('1102', '1100', 'Cash – SYP', 'النقد – ليرة سورية', 'Cash and cash equivalents'), $now);
            $this->insertIfMissing([
                'code' => '4001', 'parent_code' => '4000', 'level' => 'posting', 'class' => 'income',
                'name_en' => 'Accounts receivable', 'name_ar' => 'الذمم المدينة', 'statement_line' => 'Accounts receivable',
                'normal_side' => 'credit', 'currency' => 'Any', 'city' => null, 'is_cash' => false,
                'required_tags' => json_encode(['Project']), 'usage_note' => null, 'is_active' => true,
            ], $now);

            foreach (DB::table('gl_accounts')->whereIn('parent_code', self::CITY_HEADERS)->get(['code', 'currency']) as $account) {
                $parent = match ($account->currency) {
                    'USD' => '1101',
                    'SYP' => '1102',
                    default => '1100',
                };
                DB::table('gl_accounts')->where('code', $account->code)->update(['parent_code' => $parent, 'updated_at' => $now]);
            }

            DB::table('gl_accounts')->where('code', '1160')
                ->update(['parent_code' => '1000', 'name_en' => 'Transfer company', 'name_ar' => 'شركة الحوالات', 'updated_at' => $now]);

            $inTransit = DB::table('gl_accounts')->where('code', 'like', '11__')->pluck('code')
                ->filter(fn (string $code): bool => preg_match('/^11[1-5][34]$/', $code) === 1);

            $income = DB::table('gl_accounts')->where('class', 'income')->whereNotIn('code', ['4000', '4001'])->get(['code', 'level']);
            DB::table('gl_accounts')->whereIn('code', $income->where('level', 'posting')->pluck('code'))
                ->update(['parent_code' => '4000', 'updated_at' => $now]);

            foreach ([...$inTransit, ...$income->where('level', 'posting')->pluck('code')] as $code) {
                $this->remove($code, $now);
            }

            foreach ([...self::CITY_HEADERS, ...$income->where('level', 'header')->pluck('code')] as $code) {
                if (! DB::table('gl_accounts')->where('parent_code', $code)->whereNull('deleted_at')->exists()) {
                    $this->remove($code, $now);
                }
            }
        });
    }

    /** The old layout is not rebuilt; restore a backup taken before this migration if it has to be undone. */
    public function down(): void {}

    /**
     * @param  array<string, mixed>  $row
     */
    private function insertIfMissing(array $row, mixed $now): void
    {
        if (DB::table('gl_accounts')->where('code', $row['code'])->exists()) {
            return;
        }

        DB::table('gl_accounts')->insert([...$row, 'created_at' => $now, 'updated_at' => $now]);
    }

    /**
     * @return array<string, mixed>
     */
    private function header(string $code, string $parent, string $nameEn, string $nameAr, string $statementLine): array
    {
        return [
            'code' => $code, 'parent_code' => $parent, 'level' => 'header', 'class' => 'assets',
            'name_en' => $nameEn, 'name_ar' => $nameAr, 'statement_line' => $statementLine, 'normal_side' => 'debit',
            'currency' => null, 'city' => null, 'is_cash' => false, 'required_tags' => json_encode([]),
            'usage_note' => null, 'is_active' => true,
        ];
    }

    /** Deactivates an account that already has lines, and soft-deletes one that has none. */
    private function remove(string $code, mixed $now): void
    {
        $isUsed = DB::table('gl_voucher_lines')->where('account_code', $code)->exists()
            || DB::table('gl_budget_lines')->where('account_code', $code)->exists()
            || DB::table('gl_month_counts')->where('account_code', $code)->exists();

        DB::table('gl_accounts')->where('code', $code)
            ->update($isUsed ? ['is_active' => false, 'updated_at' => $now] : ['deleted_at' => $now, 'updated_at' => $now]);
    }
};
