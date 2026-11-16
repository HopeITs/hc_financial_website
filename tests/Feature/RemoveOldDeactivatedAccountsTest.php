<?php

use App\Models\GlAccount;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    (require database_path('migrations/2026_10_24_090000_create_gl_accounts_projects_budget_lines_tables.php'))->up();
    (require database_path('migrations/2026_10_24_100000_create_gl_voucher_tables.php'))->up();
    (require database_path('migrations/2026_11_03_090000_create_gl_month_end_tables.php'))->up();
    $this->seed(ChartOfAccountsSeeder::class);
});

/**
 * An account of the old chart as the restructure left it on the live site.
 */
function oldAccount(string $code, ?string $parent, string $level, string $class, bool $isActive = false): void
{
    GlAccount::query()->create([
        'code' => $code, 'parent_code' => $parent, 'level' => $level, 'class' => $class,
        'name_en' => 'Old '.$code, 'name_ar' => 'قديم '.$code, 'statement_line' => null,
        'normal_side' => $class === 'income' ? 'credit' : 'debit', 'currency' => $level === 'posting' ? 'Any' : null,
        'city' => null, 'is_cash' => false, 'required_tags' => [], 'usage_note' => null, 'is_active' => $isActive,
    ]);
}

function removeOldDeactivatedAccounts(): void
{
    (require database_path('migrations/2026_11_16_090000_remove_old_deactivated_accounts.php'))->up();
}

function isRemoved(string $code): bool
{
    return GlAccount::withTrashed()->findOrFail($code)->trashed();
}

it('removes the old income accounts, in-transit accounts and old groups once they have no lines', function () {
    oldAccount('4100', '4000', 'header', 'income');
    oldAccount('4101', '4100', 'posting', 'income');
    oldAccount('4102', '4100', 'posting', 'income');
    oldAccount('1110', '1100', 'header', 'assets');
    oldAccount('1113', '1110', 'posting', 'assets');
    oldAccount('1170', '1100', 'header', 'assets');

    removeOldDeactivatedAccounts();

    foreach (['4100', '4101', '4102', '1110', '1113', '1170'] as $code) {
        expect(isRemoved($code))->toBeTrue($code);
    }

    expect(GlAccount::query()->where('class', 'income')->pluck('code')->all())->toBe(['4000', '4001']);
});

it('keeps an old account that still has lines, and the group above it', function () {
    oldAccount('4300', '4000', 'header', 'income');
    oldAccount('4302', '4300', 'posting', 'income');
    DB::table('gl_month_counts')->insert(['month' => '2026-10', 'account_code' => '4302', 'counted' => 0, 'entered_by' => 1]);

    removeOldDeactivatedAccounts();

    expect(isRemoved('4302'))->toBeFalse()
        ->and(isRemoved('4300'))->toBeFalse();
});

it('keeps an old account that is still active, and every account of the new chart', function () {
    $before = GlAccount::query()->count();
    oldAccount('4600', '4000', 'header', 'income', isActive: true);
    oldAccount('4601', '4600', 'posting', 'income', isActive: true);

    removeOldDeactivatedAccounts();

    expect(isRemoved('4600'))->toBeFalse()
        ->and(isRemoved('4601'))->toBeFalse()
        ->and(GlAccount::query()->count())->toBe($before + 2);
});
