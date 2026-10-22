<?php

use App\Enums\AccountClass;
use App\Enums\AccountLevel;
use App\Enums\NormalSide;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $migration = require database_path('migrations/2026_10_24_090000_create_gl_accounts_projects_budget_lines_tables.php');
    $migration->up();
    $this->migration = $migration;
});

function glAccount(string $code, array $extra = []): GlAccount
{
    return GlAccount::query()->create($extra + [
        'code' => $code, 'level' => AccountLevel::Posting, 'class' => AccountClass::Expenses,
        'name_en' => 'Account '.$code, 'name_ar' => 'حساب '.$code, 'normal_side' => NormalSide::Debit,
    ]);
}

it('creates the three ledger tables and drops them again', function () {
    expect(Schema::hasTable('gl_accounts'))->toBeTrue()
        ->and(Schema::hasTable('gl_projects'))->toBeTrue()
        ->and(Schema::hasTable('gl_budget_lines'))->toBeTrue();

    $this->migration->down();

    expect(Schema::hasTable('gl_accounts'))->toBeFalse()
        ->and(Schema::hasTable('gl_projects'))->toBeFalse()
        ->and(Schema::hasTable('gl_budget_lines'))->toBeFalse();
});

it('links an account to its parent and keeps tags and enums cast', function () {
    glAccount('5100', ['level' => AccountLevel::Header]);
    $child = glAccount('5101', ['parent_code' => '5100', 'required_tags' => ['Project', 'Budget line'], 'is_cash' => false]);

    $child = GlAccount::query()->find('5101');

    expect($child->parent->code)->toBe('5100')
        ->and($child->parent->level)->toBe(AccountLevel::Header)
        ->and($child->class)->toBe(AccountClass::Expenses)
        ->and($child->required_tags)->toBe(['Project', 'Budget line'])
        ->and(GlAccount::query()->find('5100')->children->pluck('code')->all())->toBe(['5101']);
});

it('soft deletes accounts and rejects a parent that does not exist', function () {
    glAccount('5101')->delete();

    expect(GlAccount::query()->find('5101'))->toBeNull()
        ->and(GlAccount::withTrashed()->find('5101'))->not->toBeNull();

    expect(fn () => glAccount('5102', ['parent_code' => '9999']))->toThrow(QueryException::class);
});

it('files a budget line under its project and its expense account', function () {
    glAccount('5101');
    GlProject::query()->create(['code' => 'F2F', 'name' => 'Face to Face', 'restriction' => 'With donor restrictions', 'donor_rate' => '1.08']);
    GlBudgetLine::query()->create(['key' => 'F2F|Salaries', 'project_code' => 'F2F', 'name' => 'Salaries', 'account_code' => '5101', 'budget' => '1200.5']);

    $line = GlBudgetLine::query()->find('F2F|Salaries');

    expect($line->project->name)->toBe('Face to Face')
        ->and($line->account->code)->toBe('5101')
        ->and($line->budget)->toBe('1200.50')
        ->and(GlProject::query()->find('F2F')->donor_rate)->toBe('1.080000')
        ->and(GlProject::query()->find('F2F')->budgetLines)->toHaveCount(1);
});

it('keeps a legacy account id on at most one project', function () {
    GlProject::query()->create(['code' => 'SZ', 'name' => 'Study Zone', 'restriction' => 'Without donor restrictions', 'legacy_account_id' => 10]);

    expect(fn () => GlProject::query()->create(['code' => 'SZ2', 'name' => 'Other', 'restriction' => 'Without donor restrictions', 'legacy_account_id' => 10]))
        ->toThrow(QueryException::class);
});

it('labels the enums in English and Arabic', function () {
    expect(AccountClass::NetAssets->label())->toBe('Net assets');

    app()->setLocale('ar');

    expect(AccountClass::NetAssets->label())->toBe('صافي الأصول')
        ->and(NormalSide::Credit->label())->toBe('دائن');
});
