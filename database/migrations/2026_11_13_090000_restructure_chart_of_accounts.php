<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Finance's new layout of the chart of accounts, applied to a chart that was already seeded.
 *
 * - Cash is grouped by currency first: the city groups (1110–1150) give way to "Cash – USD" (1101) and
 *   "Cash – SYP" (1102), and each safe and till moves under the group of its currency.
 * - The transfer company (1160, with 1161) becomes its own group directly under Assets.
 * - Bank accounts become their own group directly under Assets (1165), by currency first (1166 USD, 1167 EUR,
 *   1168 SYP). The banks abroad that receive the donors' money are added (1173–1178); the old group 1170 goes.
 * - The "in transit from the transfer company" accounts (11x3, 11x4) are removed.
 * - Income is a single account, 4001 "Grant and donation income"; every other income account and group is
 *   removed. What a donor still owes under a funding contract is the asset 1210 "Accounts receivable – donors".
 *
 * Account codes do not change. A removed account that already has lines (vouchers, budget lines or month-end
 * counts) is only deactivated, so its history stays readable; an unused one is soft-deleted.
 */
return new class extends Migration
{
    private const CITY_HEADERS = ['1110', '1120', '1130', '1140', '1150'];

    private const CASH_LINE = 'Cash and cash equivalents';

    private const BANK_NOTE = 'Receives the money donors send from abroad. Money is withdrawn from here and deposited with the transfer company (1161). Reconcile to the bank statement every month.';

    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();

            foreach ($this->newAccounts() as $row) {
                $this->insertIfMissing($row, $now);
            }

            foreach (DB::table('gl_accounts')->whereIn('parent_code', self::CITY_HEADERS)->get(['code', 'currency']) as $account) {
                $parent = match ($account->currency) {
                    'USD' => '1101',
                    'SYP' => '1102',
                    default => '1100',
                };
                DB::table('gl_accounts')->where('code', $account->code)->update(['parent_code' => $parent, 'updated_at' => $now]);
            }

            foreach (DB::table('gl_accounts')->where('parent_code', '1170')->get(['code', 'currency']) as $account) {
                $parent = match ($account->currency) {
                    'USD' => '1166',
                    'EUR' => '1167',
                    'SYP' => '1168',
                    default => '1165',
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

            foreach ([...self::CITY_HEADERS, '1170', ...$income->where('level', 'header')->pluck('code')] as $code) {
                if (! DB::table('gl_accounts')->where('parent_code', $code)->whereNull('deleted_at')->exists()) {
                    $this->remove($code, $now);
                }
            }
        });
    }

    /** The old layout is not rebuilt; restore a backup taken before this migration if it has to be undone. */
    public function down(): void {}

    /**
     * The groups and accounts the new layout adds, parent first.
     *
     * @return list<array<string, mixed>>
     */
    private function newAccounts(): array
    {
        return [
            $this->header('1101', '1100', 'Cash – USD', 'النقد – دولار أمريكي'),
            $this->header('1102', '1100', 'Cash – SYP', 'النقد – ليرة سورية'),
            $this->header('1165', '1000', 'Bank accounts', 'الحسابات المصرفية'),
            $this->header('1166', '1165', 'Bank accounts – USD', 'الحسابات المصرفية – دولار أمريكي'),
            $this->header('1167', '1165', 'Bank accounts – EUR', 'الحسابات المصرفية – يورو'),
            $this->header('1168', '1165', 'Bank accounts – SYP', 'الحسابات المصرفية – ليرة سورية'),
            $this->bank('1173', '1166', 'Bank of Ruifeng (China)', 'USD'),
            $this->bank('1174', '1166', 'Bloom Bank (Lebanon)', 'USD'),
            $this->bank('1175', '1166', 'Bank of Beirut (Lebanon)', 'USD'),
            $this->bank('1176', '1167', 'Bank of Ruifeng (China)', 'EUR'),
            $this->bank('1177', '1167', 'Bloom Bank (Lebanon)', 'EUR'),
            $this->bank('1178', '1167', 'Bank of Beirut (Lebanon)', 'EUR'),
            $this->posting('1210', '1200', 'assets', 'Accounts receivable – donors', 'ذمم المانحين', 'Advances, loans and receivables', 'debit', 'Any', false, ['Project'],
                'What a donor still owes under a signed funding contract. Debit it when the contract is signed (credit 4001); credit it when the money reaches a bank account or the transfer company.'),
            $this->posting('4001', '4000', 'income', 'Grant and donation income', 'إيرادات المنح والتبرعات', 'Grants and donations', 'credit', 'Any', false, ['Project'],
                'The only income account. Credit it when a funding contract is signed (debit 1210), or when money arrives that was not recorded as owed. The project, the donor and the funding contract are linked on the line.'),
        ];
    }

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
    private function header(string $code, string $parent, string $nameEn, string $nameAr): array
    {
        return [
            'code' => $code, 'parent_code' => $parent, 'level' => 'header', 'class' => 'assets',
            'name_en' => $nameEn, 'name_ar' => $nameAr, 'statement_line' => self::CASH_LINE, 'normal_side' => 'debit',
            'currency' => null, 'city' => null, 'is_cash' => false, 'required_tags' => json_encode([]),
            'usage_note' => null, 'is_active' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bank(string $code, string $parent, string $bank, string $currency): array
    {
        $currencyAr = ['USD' => 'دولار أمريكي', 'EUR' => 'يورو'][$currency];

        return $this->posting($code, $parent, 'assets', $bank.' – '.$currency, $bank.' – '.$currencyAr, self::CASH_LINE, 'debit', $currency, true, ['Project', 'Fund source'], self::BANK_NOTE);
    }

    /**
     * @param  list<string>  $tags
     * @return array<string, mixed>
     */
    private function posting(string $code, string $parent, string $class, string $nameEn, string $nameAr, string $statementLine, string $side, string $currency, bool $isCash, array $tags, string $note): array
    {
        return [
            'code' => $code, 'parent_code' => $parent, 'level' => 'posting', 'class' => $class,
            'name_en' => $nameEn, 'name_ar' => $nameAr, 'statement_line' => $statementLine, 'normal_side' => $side,
            'currency' => $currency, 'city' => null, 'is_cash' => $isCash, 'required_tags' => json_encode($tags),
            'usage_note' => $note, 'is_active' => true,
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
