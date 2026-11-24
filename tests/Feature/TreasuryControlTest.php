<?php

use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Livewire\DailyCashCount;
use App\Livewire\LiquidityPosition;
use App\Models\CashCount;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Models\WebUserRole;
use App\Services\Ledger\LineDraft;
use App\Services\Treasury\CashBook;
use App\Services\Treasury\DailyRates;
use App\Services\Treasury\DailyValuation;
use App\Services\Treasury\MonthlyRates;
use App\Services\Treasury\Revaluations;
use App\Services\Treasury\TreasuryException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (require database_path('migrations/2026_11_24_100000_create_purchase_files.php'))->up();
    (require database_path('migrations/2026_11_25_090000_create_gl_recurring_payments_table.php'))->up();
    seedChartWithTestProjects();
    Carbon::setTestNow('2026-11-19 10:00:00');
});

function tcUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

function tcManager(): User
{
    return User::query()->where('Username', 'Rates')->first() ?? tcUser(RoleId::FinancialManager, 'Rates');
}

/** An approved receipt of 1,300,000 SYP into the Aleppo pound safe at 13,000 (100 $). */
function tcPounds(string $date): void
{
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse($date)], [
        new LineDraft('1112', 'SYP', debit: 1300000, rate: 13000, debitUsd: 100),
        new LineDraft('4001', 'SYP', credit: 1300000, rate: 13000, creditUsd: 100, projectCode: 'F2F'),
    ]);
}

/** An approved receipt of 1,000 EUR into the euro bank at 1.15 (1,150 $). */
function tcEuros(string $date): void
{
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse($date)], [
        new LineDraft('1176', 'EUR', debit: 1000, rate: 1.15, debitUsd: 1150),
        new LineDraft('4001', 'EUR', credit: 1000, rate: 1.15, creditUsd: 1150, projectCode: 'F2F'),
    ]);
}

/** @return list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}> account, currency, debit, credit, debit USD, credit USD */
function tcLines(string $voucherId): array
{
    return VoucherLine::query()->where('voucher_id', $voucherId)->orderBy('line_no')->get()
        ->map(fn (VoucherLine $line): array => [$line->account_code, $line->currency, $line->debit_orig, $line->credit_orig, $line->debit_usd, $line->credit_usd])
        ->all();
}

it('counts a city\'s tills against the books, and books an explained shortage on 5505', function () {
    tcPounds('2026-11-10');
    app(MonthlyRates::class)->set('2026-11', 'SYP', '13000', null, tcManager());

    $page = Livewire::actingAs(tcUser(RoleId::Accountant, 'Ann'))->test(DailyCashCount::class)
        ->assertSet('day', '2026-11-19')
        ->assertSet('city', 'Aleppo')
        ->assertSeeHtml('data-test="count-1112"')
        ->assertSee('1,300,000.00')
        ->set('counted.1111', '0')
        ->call('saveCount', '1111')
        ->assertSee('The count of 1111 matches the books.')
        ->set('counted.1112', '1,295,000')
        ->call('saveCount', '1112')
        ->assertSee('The count of 1112 differs by -5,000.00.')
        ->call('adjust', '1112')
        ->assertSee('Explain the difference before booking it.')
        ->set('explanation.1112', 'Short at closing')
        ->call('saveCount', '1112')
        ->call('adjust', '1112')
        ->assertSet('problems', []);

    $count = CashCount::query()->where('account_code', '1112')->sole();
    $voucher = Voucher::query()->findOrFail($count->voucher_id);

    expect([$count->book, $count->counted, $count->difference])->toBe(['1300000.00', '1295000.00', '-5000.00'])
        ->and($voucher->type)->toBe(VoucherType::Journal)
        ->and(tcLines($voucher->id))->toBe([
            ['5505', 'SYP', '5000.00', '0.00', '0.38', '0.00'],
            ['1112', 'SYP', '0.00', '5000.00', '0.00', '0.38'],
        ]);

    $page->call('saveCount', '1112')->assertSee("This count already has its voucher {$voucher->id}.");
});

it('revalues the month\'s pounds at the monthly rate, the difference on 5503, and leaves the euros to the day\'s estimate', function () {
    tcPounds('2026-10-15');
    tcEuros('2026-10-20');
    $revaluations = app(Revaluations::class);

    expect(fn () => $revaluations->prepare('2026-10', tcManager()))->toThrow(TreasuryException::class);

    app(MonthlyRates::class)->set('2026-10', 'SYP', '13500', null, tcManager());
    app(DailyRates::class)->propose('EUR', '2026-10-31', '1.17', null, tcManager(), approveNow: true);

    // Pounds: 1,300,000 at 13,500 is 96.30 $ against 100 $ booked (-3.70). The euros are not revalued with a voucher.
    expect($revaluations->preview('2026-10')['difference'])->toBe('-3.70')
        ->and(collect($revaluations->preview('2026-10')['rows'])->pluck('code')->all())->toBe(['1112']);

    Livewire::actingAs(tcUser(RoleId::Accountant, 'Ann'))->test(LiquidityPosition::class)
        ->set('month', '2026-10')
        ->assertSee('-3.70')
        ->call('revalue')
        ->assertSet('problems', []);

    $voucher = Voucher::query()->where('type', VoucherType::Journal->value)->sole();

    expect($voucher->date->toDateString())->toBe('2026-10-31')
        ->and(tcLines($voucher->id))->toBe([
            ['1112', 'SYP', '0.00', '1300000.00', '0.00', '100.00'],
            ['1112', 'SYP', '1300000.00', '0.00', '96.30', '0.00'],
            ['5503', 'USD', '3.70', '0.00', '3.70', '0.00'],
        ])
        ->and($revaluations->preview('2026-10')['difference'])->toBe('0.00')
        ->and($revaluations->preview('2026-10')['vouchers'])->toHaveCount(1);

    expect(fn () => $revaluations->prepare('2026-10', tcManager()))->toThrow(TreasuryException::class, 'Nothing to revalue');

    // The euros' estimate: 1,000 EUR at the day's 1.17 is 1,170 $ against 1,150 $ in the books, 20 $ not yet realised.
    expect(app(DailyValuation::class)->asAt(CarbonImmutable::parse('2026-10-31'))['1176'])
        ->toMatchArray(['orig' => '1000.00', 'usd' => '1150.00', 'value' => '1170.00', 'difference' => '20.00']);
});

it('shows where the money is, by bank, transfer company and city, at book dollars and today\'s value', function () {
    tcPounds('2026-11-10');
    tcEuros('2026-11-11');
    app(MonthlyRates::class)->set('2026-11', 'SYP', '13000', null, tcManager());

    $position = app(CashBook::class)->position(CarbonImmutable::today());

    expect(array_column($position['groups'], 'key'))->toBe(['banks', 'city:Aleppo'])
        ->and($position['total_usd'])->toBe('1250.00')
        ->and($position['missing_rates'])->toBe(['EUR'])
        ->and($position['groups'][1]['rows'][0])->toMatchArray(['code' => '1112', 'orig' => '1300000.00', 'usd' => '100.00', 'value' => '100.00']);

    Livewire::actingAs(tcUser(RoleId::GeneralManager, 'Gina'))->test(LiquidityPosition::class)
        ->assertSeeHtml('data-test="position-1176"')
        ->assertSee('No rate yet for EUR')
        ->assertDontSeeHtml('data-test="revalue"');
});

it('lets the financial manager see the count but not change it', function () {
    Livewire::actingAs(tcUser(RoleId::FinancialManager, 'Fadi'))->test(DailyCashCount::class)
        ->assertDontSeeHtml('data-test="save-count-1112"')
        ->call('saveCount', '1112')
        ->assertForbidden();

    $this->actingAs(tcUser(RoleId::Cashier, 'Cass'))->get('/treasury/daily-count')->assertOk()->assertSeeHtml('data-test="save-count-1112"');

    expect(CashCount::query()->count())->toBe(0);
});
