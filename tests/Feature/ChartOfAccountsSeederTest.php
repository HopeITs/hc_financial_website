<?php

use App\Enums\AccountClass;
use App\Enums\AccountLevel;
use App\Enums\NormalSide;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use Database\Seeders\ChartOfAccountsSeeder;

beforeEach(function () {
    (require database_path('migrations/2026_10_24_090000_create_gl_accounts_projects_budget_lines_tables.php'))->up();
    $this->seed(ChartOfAccountsSeeder::class);
});

it('seeds the chart with cash grouped by currency and a single income account', function () {
    $all = GlAccount::query()->get();
    $headers = $all->where('level', AccountLevel::Header);
    $cash = $all->where('is_cash', true);

    expect($all)->toHaveCount(125)
        ->and($headers)->toHaveCount(25)
        ->and($all->where('level', AccountLevel::Posting))->toHaveCount(100)
        ->and($cash->where('currency', 'USD'))->toHaveCount(7)
        ->and($cash->where('currency', 'SYP'))->toHaveCount(15);
});

it('gives every account a parent that exists, is a header and has a smaller code', function () {
    $byCode = GlAccount::query()->get()->keyBy('code');

    foreach ($byCode as $account) {
        if ($account->parent_code === null) {
            expect($account->level)->toBe(AccountLevel::Header, $account->code);

            continue;
        }

        expect($byCode->has($account->parent_code))->toBeTrue($account->code)
            ->and($byCode[$account->parent_code]->level)->toBe(AccountLevel::Header, $account->code)
            ->and($account->parent_code < $account->code)->toBeTrue($account->code);
    }
});

it('gives every posting account a class, a side, a statement line and both names', function () {
    $posting = GlAccount::query()->where('level', AccountLevel::Posting->value)->get();

    foreach ($posting as $account) {
        expect($account->class)->toBeInstanceOf(AccountClass::class, $account->code)
            ->and($account->normal_side)->toBeInstanceOf(NormalSide::class, $account->code)
            ->and($account->statement_line)->not->toBeEmpty($account->code)
            ->and($account->name_en)->not->toBeEmpty($account->code)
            ->and($account->name_ar)->not->toBeEmpty($account->code)
            ->and($account->required_tags)->toBeArray($account->code);
    }
});

it('keeps headers free of currency, cash flag and tags, and cash accounts in one currency', function () {
    foreach (GlAccount::query()->where('level', AccountLevel::Header->value)->get() as $header) {
        expect($header->currency)->toBeNull($header->code)
            ->and($header->is_cash)->toBeFalse($header->code)
            ->and($header->required_tags)->toBe([], $header->code);
    }

    foreach (GlAccount::query()->where('is_cash', true)->get() as $cash) {
        expect($cash->currency)->toBeIn(['USD', 'SYP'], $cash->code)
            ->and($cash->class)->toBe(AccountClass::Assets, $cash->code);
    }
});

it('groups every cash safe and till under the header of its currency, with no in-transit accounts', function () {
    foreach (GlAccount::query()->whereIn('parent_code', ['1101', '1102'])->get() as $cash) {
        expect($cash->parent_code)->toBe($cash->currency === 'USD' ? '1101' : '1102', $cash->code)
            ->and($cash->is_cash)->toBeTrue($cash->code);
    }

    expect(GlAccount::query()->find('1101')->parent_code)->toBe('1100')
        ->and(GlAccount::query()->find('1102')->parent_code)->toBe('1100')
        ->and(GlAccount::query()->whereIn('code', ['1110', '1113', '1114', '1120', '1153', '1154'])->exists())->toBeFalse();

    foreach (['1125', '1126', '1127', '1135', '1145', '1146', '1147', '1155'] as $code) {
        $till = GlAccount::query()->find($code);

        expect($till->parent_code)->toBe('1102')
            ->and($till->currency)->toBe('SYP')
            ->and($till->is_cash)->toBeTrue()
            ->and($till->required_tags)->toBe(['Project', 'Fund source', 'Center']);
    }

    expect(GlAccount::query()->find('1145')->name_ar)->toBe('صندوق Science Zone حمص – ليرة سورية');
});

it('applies the v7 renames', function () {
    expect(GlAccount::query()->find('1115')->name_en)->toBe('Science Zone Aleppo Cash Till – SYP')
        ->and(GlAccount::query()->find('1171')->name_en)->toBe('Fransabank – USD')
        ->and(GlAccount::query()->find('1172')->name_ar)->toBe('Fransabank – ليرة سورية');
});

it('adds 5504 hawala company commission as an expense with no project tag', function () {
    $account = GlAccount::query()->find('5504');

    expect($account->parent_code)->toBe('5500')
        ->and($account->name_en)->toBe('Hawala company commission')
        ->and($account->name_ar)->toBe('عمولة شركة الحوالات')
        ->and($account->class)->toBe(AccountClass::Expenses)
        ->and($account->normal_side)->toBe(NormalSide::Debit)
        ->and($account->required_tags)->toBe([]);
});

it('keeps the transfer company in its own group directly under assets', function () {
    expect(GlAccount::query()->find('1160')->parent_code)->toBe('1000')
        ->and(GlAccount::query()->find('1161')->parent_code)->toBe('1160');
});

it('has a single income account, accounts receivable', function () {
    $income = GlAccount::query()->where('class', AccountClass::Income->value)->where('level', AccountLevel::Posting->value)->get();

    expect($income)->toHaveCount(1)
        ->and($income->first()->code)->toBe('4001')
        ->and($income->first()->parent_code)->toBe('4000')
        ->and($income->first()->name_en)->toBe('Accounts receivable')
        ->and($income->first()->normal_side)->toBe(NormalSide::Credit);
});

it('reduces the mockup tag text to the five tag names', function () {
    $tags = GlAccount::query()->get()->pluck('required_tags')->flatten()->unique()->sort()->values()->all();

    expect($tags)->toBe(['Budget line', 'Center', 'Contact', 'Fund source', 'Project']);
});

it('seeds F2F, SZ, CORE and a USD and an SYP general account for each of the five cities', function () {
    $projects = GlProject::query()->get()->keyBy('code');
    $general = $projects->where('is_general_account', true);

    expect($projects)->toHaveCount(13)
        ->and($projects->has('F2F'))->toBeTrue()
        ->and($projects['F2F']->donor_currency)->toBe('EUR')
        ->and($projects['F2F']->donor_rate)->toBe('1.150000')
        ->and($projects->has('SZ'))->toBeTrue()
        ->and($projects->has('CORE'))->toBeTrue()
        ->and($projects['CORE']->restriction)->toBe('Without donor restrictions')
        ->and($general)->toHaveCount(10)
        ->and($general->keys()->sort()->values()->all())->toBe([
            'GEN-ALP-SYP', 'GEN-ALP-USD', 'GEN-DAM-SYP', 'GEN-DAM-USD', 'GEN-HMS-SYP',
            'GEN-HMS-USD', 'GEN-LTK-SYP', 'GEN-LTK-USD', 'GEN-TRT-SYP', 'GEN-TRT-USD',
        ]);
});

it('seeds ten budget lines, each on an existing project and an expense posting account', function () {
    $lines = GlBudgetLine::query()->with(['project', 'account'])->get();

    expect($lines)->toHaveCount(10);

    foreach ($lines as $line) {
        expect($line->project)->not->toBeNull($line->key)
            ->and($line->account->class)->toBe(AccountClass::Expenses, $line->key)
            ->and($line->account->level)->toBe(AccountLevel::Posting, $line->key)
            ->and($line->key)->toBe($line->project_code.'|'.$line->name);
    }

    expect(GlBudgetLine::query()->find('F2F|Maintenance')->budget)->toBe('10000.00')
        ->and(GlBudgetLine::query()->find('F2F|Maintenance')->currency)->toBe('EUR');
});

it('can run again without duplicating rows or overwriting an edit', function () {
    GlAccount::query()->find('1111')->update(['name_en' => 'Edited in the web app']);
    GlAccount::query()->find('1111')->delete();

    $this->seed(ChartOfAccountsSeeder::class);

    expect(GlAccount::query()->count())->toBe(124)
        ->and(GlAccount::withTrashed()->count())->toBe(125)
        ->and(GlAccount::withTrashed()->find('1111')->name_en)->toBe('Edited in the web app')
        ->and(GlProject::query()->count())->toBe(13)
        ->and(GlBudgetLine::query()->count())->toBe(10);
});
