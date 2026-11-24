<?php

use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Livewire\ExchangeRates;
use App\Livewire\TreasuryTransfers;
use App\Models\DailyRate;
use App\Models\MonthlyRate;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Models\WebUserRole;
use App\Services\Ledger\LineDraft;
use App\Services\Treasury\CashBook;
use App\Services\Treasury\DailyRates;
use App\Services\Treasury\MonthlyRates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (require database_path('migrations/2026_11_24_100000_create_purchase_files.php'))->up();
    (require database_path('migrations/2026_11_25_090000_create_gl_recurring_payments_table.php'))->up();
    (require database_path('migrations/2026_11_25_100000_create_payroll_tables.php'))->up();
    (require database_path('migrations/2026_11_25_110000_create_project_revenues.php'))->up();
    seedChartWithTestProjects();
    Carbon::setTestNow('2026-11-19 10:00:00');
});

function tmUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

/** An approved rate, entered as the Financial Manager would. */
function tmRate(string $currency, string $day, string $rate): DailyRate
{
    $manager = User::query()->where('Username', 'Rates')->first() ?? tmUser(RoleId::FinancialManager, 'Rates');

    return app(DailyRates::class)->propose($currency, $day, $rate, null, $manager, approveNow: true);
}

/** A monthly rate, set as the Financial Manager would. */
function tmMonthly(string $currency, string $month, string $rate): MonthlyRate
{
    $manager = User::query()->where('Username', 'Rates')->first() ?? tmUser(RoleId::FinancialManager, 'Rates');

    return app(MonthlyRates::class)->set($month, $currency, $rate, null, $manager);
}

/** @return list<array{0: string, 1: string|null, 2: string, 3: string, 4: string, 5: string, 6: string}> account, project, currency, debit, credit, debit USD, credit USD */
function tmLines(string $voucherId): array
{
    return VoucherLine::query()->where('voucher_id', $voucherId)->orderBy('line_no')->get()
        ->map(fn (VoucherLine $line): array => [$line->account_code, $line->project_code, $line->currency, $line->debit_orig, $line->credit_orig, $line->debit_usd, $line->credit_usd])
        ->all();
}

function tmMove(User $user, string $kind, array $fields)
{
    $page = Livewire::actingAs($user)->test(TreasuryTransfers::class)->set('kind', $kind);

    foreach ($fields as $name => $value) {
        $page->set($name, $value);
    }

    return $page->call('save');
}

it('lets the accountant propose the day\'s rate and the financial manager approve it, never their own', function () {
    $accountant = tmUser(RoleId::Accountant, 'Ann');
    $manager = tmUser(RoleId::FinancialManager, 'Fadi');

    Livewire::actingAs($accountant)->test(ExchangeRates::class)
        ->assertSet('day', '2026-11-19')
        ->set('currency', 'SYP')->set('rate', '13,100')
        ->call('propose')
        ->assertSee('The SYP rate of 19/11/2026 is waiting for the Financial Manager.');

    $proposed = DailyRate::query()->sole();

    expect($proposed->status)->toBe(DailyRate::PENDING)
        ->and($proposed->rate)->toBe('13100.000000')
        ->and(app(DailyRates::class)->rateFor('SYP', CarbonImmutable::today()))->toBeNull();

    Livewire::actingAs($accountant)->test(ExchangeRates::class)->call('approve', $proposed->id)->assertForbidden();

    Livewire::actingAs($manager)->test(ExchangeRates::class)
        ->assertSeeHtml('data-test="approve-rate-'.$proposed->id.'"')
        ->call('approve', $proposed->id)
        ->assertSee('Rate approved.');

    expect($proposed->refresh()->status)->toBe(DailyRate::APPROVED)
        ->and($proposed->decided_by)->toBe($manager->getKey())
        ->and(app(DailyRates::class)->rateFor('SYP', CarbonImmutable::today())?->id)->toBe($proposed->id);

    // The manager's own rate is approved at once and replaces the day's earlier one; a later day without a rate uses it.
    Livewire::actingAs($manager)->test(ExchangeRates::class)
        ->set('currency', 'SYP')->set('rate', '13200')
        ->call('propose')
        ->assertSee('The SYP rate of 19/11/2026 is approved.');

    expect($proposed->refresh()->status)->toBe(DailyRate::REPLACED)
        ->and(app(DailyRates::class)->rateFor('SYP', CarbonImmutable::parse('2026-11-22'))?->rate)->toBe('13200.000000')
        ->and(app(DailyRates::class)->rateFor('SYP', CarbonImmutable::parse('2026-11-18')))->toBeNull();

    $own = app(DailyRates::class)->propose('EUR', '2026-11-19', '1.16', null, $accountant, approveNow: false);

    Livewire::actingAs($accountant)->test(ExchangeRates::class)->assertDontSeeHtml('data-test="approve-rate-'.$own->id.'"');
});

it('refuses a rate for the dollar, a rate of zero and a bad date, and lets nobody decide twice or on their own proposal', function () {
    $ann = tmUser(RoleId::Accountant, 'Ann');
    $fadi = tmUser(RoleId::FinancialManager, 'Fadi');

    Livewire::actingAs($ann)->test(ExchangeRates::class)
        ->set('currency', 'USD')->set('rate', '0')->set('day', '2026-02-30')
        ->call('propose')
        ->assertSee('Choose a currency other than the dollar.')
        ->assertSee('Type a valid date.')
        ->assertSee('Type a rate above zero.');

    $rate = app(DailyRates::class)->propose('SYP', '2026-11-19', '13000', null, $fadi, approveNow: false);

    Livewire::actingAs($fadi)->test(ExchangeRates::class)->call('approve', $rate->id)->assertSee('You proposed this rate: someone else approves it.');

    app(DailyRates::class)->reject($rate->id, User::query()->whereKey(createFinanceUser('Hana', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value))->firstOrFail());

    Livewire::actingAs($fadi)->test(ExchangeRates::class)->call('approve', $rate->id)->assertSee('This rate is no longer waiting for a decision.');

    expect(DailyRate::query()->where('status', DailyRate::APPROVED)->count())->toBe(0);
});

it('sets the monthly rate as the financial manager, beside what the month\'s exchanges really got, and uses the month before\'s until then', function () {
    $fadi = tmUser(RoleId::FinancialManager, 'Fadi');
    $ann = tmUser(RoleId::Accountant, 'Ann');
    $monthly = app(MonthlyRates::class);

    tmMove($ann, 'exchange', ['from' => '1111', 'to' => '1112', 'amount' => '1000', 'received' => '13000000']);
    tmMove($ann, 'exchange', ['from' => '1161', 'to' => '1122', 'amount' => '3000', 'received' => '39600000']);

    // 52,600,000 pounds for 4,000 dollars: 13,150 on average.
    $page = Livewire::actingAs($fadi)->test(ExchangeRates::class)
        ->assertSet('month', '2026-11')
        ->assertSee('Exchanged this month: 13150 on average, over 2 exchanges of 4,000.00 USD.')
        ->set('monthlyCurrency', 'SYP')->set('monthlyRate', '13150')
        ->call('setMonthly')
        ->assertSee('The SYP rate of 11/2026 is saved.');

    expect($monthly->forDay('SYP', CarbonImmutable::parse('2026-11-30')))->toBe(['rate' => '13150.000000', 'month' => '2026-11', 'provisional' => false])
        ->and($monthly->forDay('SYP', CarbonImmutable::parse('2026-12-02')))->toBe(['rate' => '13150.000000', 'month' => '2026-11', 'provisional' => true])
        ->and($monthly->forDay('SYP', CarbonImmutable::parse('2026-10-31')))->toBeNull();

    $page->set('monthlyRate', '13100')->call('setMonthly');

    expect(MonthlyRate::query()->sole()->rate)->toBe('13100.000000');

    Livewire::actingAs($ann)->test(ExchangeRates::class)
        ->assertDontSeeHtml('data-test="monthly-form"')
        ->set('monthlyRate', '1')->call('setMonthly')
        ->assertForbidden();
});

it('transfers pounds between a safe and a center\'s till at the monthly rate, with no project', function () {
    $ann = tmUser(RoleId::Accountant, 'Ann');
    $move = ['from' => '1112', 'to' => '1115', 'amount' => '1300000'];

    tmMove($ann, 'transfer', $move + ['center' => 'Science Zone Aleppo'])->assertSee('There is no SYP monthly rate for 11/2026 or before.');

    // October's rate, until November's is set.
    tmMonthly('SYP', '2026-10', '13000');

    tmMove($ann, 'transfer', $move)->assertSee('Account 1115 is a center\'s till: type the center.');

    tmMove($ann, 'transfer', $move + ['center' => 'Science Zone Aleppo'])->assertHasNoErrors()->assertSee('prepared and waiting for approval');

    $voucher = Voucher::query()->where('type', VoucherType::Transfer->value)->sole();

    expect($voucher->status)->toBe(VoucherStatus::PendingApproval)
        ->and($voucher->transfer_kind)->toBeNull()
        ->and(tmLines($voucher->id))->toBe([
            ['1112', null, 'SYP', '0.00', '1300000.00', '0.00', '100.00'],
            ['1115', null, 'SYP', '1300000.00', '0.00', '100.00', '0.00'],
        ])
        ->and(VoucherLine::query()->where('voucher_id', $voucher->id)->where('account_code', '1115')->value('center'))->toBe('Science Zone Aleppo');
});

it('books what did not arrive as the fee, the organisation\'s or a project\'s as chosen', function () {
    $ann = tmUser(RoleId::Accountant, 'Ann');

    tmMove($ann, 'transfer', ['from' => '1161', 'to' => '1111', 'amount' => '1000', 'received' => '990', 'contact' => 'Abu Sami'])->assertHasNoErrors();

    $voucher = Voucher::query()->sole();
    $company = VoucherLine::query()->where('voucher_id', $voucher->id)->where('account_code', '1161')->sole();

    expect(tmLines($voucher->id))->toBe([
        ['1161', null, 'USD', '0.00', '1000.00', '0.00', '1000.00'],
        ['1111', null, 'USD', '990.00', '0.00', '990.00', '0.00'],
        ['5504', null, 'USD', '10.00', '0.00', '10.00', '0.00'],
    ])
        ->and([$company->contact, $company->commission_usd])->toBe(['Abu Sami', '10.00']);

    // A bank's fee: the organisation's bank charges (5501), or a project's budget line when chosen.
    tmMove($ann, 'transfer', ['from' => '1171', 'to' => '1111', 'amount' => '1000', 'received' => '995'])->assertSet('problems', []);
    tmMove($ann, 'transfer', ['from' => '1171', 'to' => '1111', 'amount' => '500', 'received' => '498', 'feeTo' => 'F2F|Bank charges'])->assertSet('problems', []);

    [$organisation, $project] = Voucher::query()->where('id', '!=', $voucher->id)->orderBy('id')->pluck('id')->all();

    expect(tmLines($organisation)[2])->toBe(['5501', null, 'USD', '5.00', '0.00', '5.00', '0.00'])
        ->and(tmLines($project)[2])->toBe(['5501', 'F2F', 'USD', '2.00', '0.00', '2.00', '0.00'])
        ->and(VoucherLine::query()->where('voucher_id', $project)->where('line_no', 3)->value('budget_line_key'))->toBe('F2F|Bank charges');

    tmMove($ann, 'transfer', ['from' => '1171', 'to' => '1112', 'amount' => '1000'])
        ->assertSee('A transfer stays in one currency');
    tmMove($ann, 'transfer', ['from' => '1171', 'to' => '1111', 'amount' => '10', 'received' => '9', 'feeTo' => 'NOPE|Nothing'])
        ->assertSee('Charge the fee to the organisation or to an existing budget line.');

    expect(Voucher::query()->count())->toBe(3);
});

it('withdraws a donor\'s euros at their average rate in the books, the bank\'s fee in euros, the real gain or loss on 5503', function () {
    $ann = tmUser(RoleId::Accountant, 'Ann');
    tmRate('EUR', '2026-11-19', '1.16');

    // 3,000 EUR came at 1.15 (3,450 $) and 2,000 EUR at 1.20 (2,400 $): 5,000 EUR held at 5,850 $, an average of 1.17.
    foreach ([[3000, 1.15, 3450], [2000, 1.20, 2400]] as [$euros, $rate, $dollars]) {
        ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-11-10')], [
            new LineDraft('1176', 'EUR', debit: $euros, rate: $rate, debitUsd: $dollars),
            new LineDraft('4001', 'EUR', credit: $euros, rate: $rate, creditUsd: $dollars, projectCode: 'F2F'),
        ]);
    }

    Livewire::actingAs($ann)->test(TreasuryTransfers::class)
        ->set('kind', 'exchange')
        ->set('from', '1176')->set('to', '1161')->set('amount', '4000')->set('fee', '10')->set('received', '4700')
        ->assertSee('EUR')
        ->assertSee('Average rate in the books: 1.17')
        ->call('save')
        ->assertSet('problems', []);

    // 4,000 EUR leave at 1.17 (4,680 $); the bank keeps 10 EUR (11.70 $); 3,990 EUR came to 4,700 $: 31.70 $ gained.
    $first = Voucher::query()->where('type', VoucherType::Exchange->value)->sole();
    expect(tmLines($first->id))->toBe([
        ['1176', null, 'EUR', '0.00', '4000.00', '0.00', '4680.00'],
        ['1161', null, 'USD', '4700.00', '0.00', '4700.00', '0.00'],
        ['5501', null, 'EUR', '10.00', '0.00', '11.70', '0.00'],
        ['5503', null, 'USD', '0.00', '31.70', '0.00', '31.70'],
    ]);

    // The last 1,000 EUR leave at 1,170 $: the account is empty in euros and in dollars, whatever the rate obtained.
    tmMove($ann, 'exchange', ['from' => '1176', 'to' => '1161', 'amount' => '1000', 'received' => '1160'])->assertSet('problems', []);

    expect(app(CashBook::class)->balances(CarbonImmutable::parse('2026-11-19'))['1176'])->toMatchArray(['orig' => '0.00', 'usd' => '0.00']);

    tmMove($ann, 'exchange', ['from' => '1176', 'to' => '1161', 'amount' => '10', 'fee' => '10', 'received' => '1'])
        ->assertSee('The bank\'s fee must be less than the euros withdrawn.');
    tmMove($ann, 'exchange', ['from' => '1176', 'to' => '1112', 'amount' => '10', 'received' => '10'])
        ->assertSee('Euros are withdrawn to the transfer company or a dollar bank account.');
});

it('exchanges dollars for pounds at the rate really obtained, showing it beside the monthly rate', function () {
    $ann = tmUser(RoleId::Accountant, 'Ann');
    tmMonthly('SYP', '2026-11', '13000');

    Livewire::actingAs($ann)->test(TreasuryTransfers::class)
        ->set('kind', 'exchange')
        ->set('from', '1111')->set('to', '1112')->set('amount', '1000')->set('received', '13200000')
        ->assertSeeHtml('data-test="exchange-rate"')
        ->assertSee('Rate obtained: 13200 (SYP).')
        ->assertSee('Monthly rate: 13000')
        ->call('save')
        ->assertHasNoErrors();

    $voucher = Voucher::query()->sole();

    expect($voucher->type)->toBe(VoucherType::Exchange)
        ->and(tmLines($voucher->id))->toBe([
            ['1111', null, 'USD', '0.00', '1000.00', '0.00', '1000.00'],
            ['1112', null, 'SYP', '13200000.00', '0.00', '1000.00', '0.00'],
        ])
        ->and(VoucherLine::query()->where('voucher_id', $voucher->id)->where('account_code', '1112')->value('rate'))->toBe('13200.000000');

    // Not from a dollar bank, and pounds only into a city's general pound account.
    tmMove($ann, 'exchange', ['from' => '1171', 'to' => '1112', 'amount' => '10', 'received' => '130000'])
        ->assertSee('Money is exchanged from the transfer company, a city\'s dollar cash or a euro bank account.');
    tmMove($ann, 'exchange', ['from' => '1111', 'to' => '1115', 'amount' => '10', 'received' => '130000', 'center' => 'Science Zone Aleppo'])
        ->assertSee('The pounds go to a city\'s general pound account (its main safe).');

    expect(Voucher::query()->count())->toBe(1);
});

it('lets only the preparing roles prepare a transfer, and others see the screen', function () {
    tmMove(tmUser(RoleId::GeneralManager, 'Gina'), 'transfer', ['from' => '1111', 'to' => '1121', 'amount' => '10'])->assertForbidden();

    Livewire::actingAs(tmUser(RoleId::FinancialManager, 'Fadi'))->test(TreasuryTransfers::class)
        ->assertOk()
        ->assertDontSeeHtml('data-test="move-form"');

    $this->actingAs(tmUser(RoleId::Accountant, 'Ann'))->get('/treasury/transfers')->assertOk()->assertSeeHtml('data-test="move-form"');
    $this->get('/treasury/exchange-rates')->assertOk()->assertSeeHtml('data-test="rate-form"');

    expect(Voucher::query()->count())->toBe(0);
});
