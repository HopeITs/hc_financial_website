<?php

use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Livewire\StaffAdvances;
use App\Livewire\VoucherBuilder;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherLine;
use App\Models\WebUserRole;
use App\Services\Entries\Advances;
use App\Services\Treasury\MonthlyRates;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (require database_path('migrations/2026_11_24_100000_create_purchase_files.php'))->up();
    seedChartWithTestProjects();
    Carbon::setTestNow('2026-11-19 10:00:00');
});

function saUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

/** @return list<array{0: string, 1: string|null, 2: string|null, 3: string, 4: string, 5: string, 6: string}> account, project, contact, debit, credit, debit USD, credit USD */
function saLines(string $voucherId): array
{
    return VoucherLine::query()->where('voucher_id', $voucherId)->orderBy('line_no')->get()
        ->map(fn (VoucherLine $line): array => [$line->account_code, $line->project_code, $line->contact, $line->debit_orig, $line->credit_orig, $line->debit_usd, $line->credit_usd])
        ->all();
}

/** Ann gives Rami 1,300,000 SYP for F2F at the November rate of 13,000 (100 $), and returns the screen. */
function saGive(User $ann)
{
    app(MonthlyRates::class)->set('2026-11', 'SYP', '13000', null, saUser(RoleId::FinancialManager, 'Rates'));

    return Livewire::actingAs($ann)->test(StaffAdvances::class)
        ->call('startGive')
        ->set('giveAccount', '1112')
        ->assertSet('giveRate', '13000')
        ->set('givePerson', 'Rami')->set('giveProject', 'F2F')->set('giveAmount', '1,300,000')->set('givePurpose', 'Workshop supplies')
        ->call('give')
        ->assertSet('problems', []);
}

/** Opens the settlement of the one open advance with a bill on F2F's maintenance line. */
function saSettle($page, string $amount, string $rest = 'now')
{
    $key = app(Advances::class)->open()[0]['key'];

    return $page->call('startSettle', $key)
        ->set('bills.0.budget_line', 'F2F|Maintenance')
        ->assertSet('bills.0.account', '5405')
        ->set('bills.0.amount', $amount)
        ->set('settleCenter', 'Science Zone Aleppo')
        ->set('rest', $rest);
}

it('gives an advance as a payment on 1201 for the person and the project, and lists it as open', function () {
    $page = saGive(saUser(RoleId::Accountant, 'Ann'));

    $voucher = Voucher::query()->sole();

    expect($voucher->type)->toBe(VoucherType::Payment)
        ->and($voucher->description)->toBe('Advance to Rami: Workshop supplies')
        ->and(saLines($voucher->id))->toBe([
            ['1201', 'F2F', 'Rami', '1300000.00', '0.00', '100.00', '0.00'],
            ['1112', null, null, '0.00', '1300000.00', '0.00', '100.00'],
        ]);

    expect(app(Advances::class)->open())->toHaveCount(1)
        ->and(app(Advances::class)->open()[0])->toMatchArray(['person' => 'Rami', 'project' => 'F2F', 'currency' => 'SYP', 'open' => '1300000.00', 'open_usd' => '100.00', 'rate' => '13000.000000', 'pending' => true]);

    $page->assertSee("Advance voucher {$voucher->id} saved and waiting for approval.")
        ->assertSee('Rami')
        ->assertSee('1,300,000.00');
});

it('settles with the bills and the rest handed back, in one journal voucher at the advance\'s rate', function () {
    $page = saSettle(saGive(saUser(RoleId::Accountant, 'Ann')), '1000000')
        ->assertSee('300,000.00')
        ->set('settleAccount', '1112')
        ->call('settle')
        ->assertSet('problems', []);

    $settlement = Voucher::query()->where('type', VoucherType::Journal->value)->sole();

    expect($settlement->status)->toBe(VoucherStatus::PendingApproval)
        ->and(saLines($settlement->id))->toBe([
            ['5405', 'F2F', 'Rami', '1000000.00', '0.00', '76.92', '0.00'],
            ['1201', 'F2F', 'Rami', '0.00', '1300000.00', '0.00', '100.00'],
            ['1112', null, null, '300000.00', '0.00', '23.08', '0.00'],
        ])
        ->and(app(Advances::class)->open())->toBe([]);

    $page->assertSee("Settlement voucher {$settlement->id} saved and waiting for approval.")->assertSee('No open advance.');
});

it('pays the person the bills above the advance, or keeps the rest open for bills to come', function () {
    $ann = saUser(RoleId::Accountant, 'Ann');
    $page = saGive($ann);

    // Half the bills, the rest kept open.
    saSettle($page, '650000', 'open')->call('settle')->assertSet('problems', []);

    $partial = Voucher::query()->where('type', VoucherType::Journal->value)->sole();

    expect(saLines($partial->id))->toBe([
        ['5405', 'F2F', 'Rami', '650000.00', '0.00', '50.00', '0.00'],
        ['1201', 'F2F', 'Rami', '0.00', '650000.00', '0.00', '50.00'],
    ])
        ->and(app(Advances::class)->open()[0])->toMatchArray(['open' => '650000.00', 'open_usd' => '50.00']);

    // Then bills of 910,000 against the 650,000 left: 260,000 paid to the person.
    saSettle($page, '910000')->set('settleAccount', '1112')->call('settle')->assertSet('problems', []);

    $final = Voucher::query()->where('type', VoucherType::Journal->value)->where('id', '!=', $partial->id)->sole();

    expect(saLines($final->id))->toBe([
        ['5405', 'F2F', 'Rami', '910000.00', '0.00', '70.00', '0.00'],
        ['1201', 'F2F', 'Rami', '0.00', '650000.00', '0.00', '50.00'],
        ['1112', null, null, '0.00', '260000.00', '0.00', '20.00'],
    ])
        ->and(app(Advances::class)->open())->toBe([]);
});

it('says what is missing when giving or settling', function () {
    $ann = saUser(RoleId::Accountant, 'Ann');

    Livewire::actingAs($ann)->test(StaffAdvances::class)
        ->call('startGive')
        ->set('giveAccount', '1111')->set('givePerson', 'Rami')->set('giveAmount', '100')
        ->call('give')
        ->assertSee('Choose the project the advance is for.');

    $key = app(Advances::class)->key('Nobody', 'F2F', 'USD');
    expect(app(Advances::class)->preview($key, '2026-11-19', [], true, '', '', '')['problems'])->toBe(['This advance is no longer open.']);

    $page = saGive($ann);
    $page->call('startSettle', app(Advances::class)->open()[0]['key'])
        ->call('settle')
        ->assertSee('Add at least one bill with an amount.');

    saSettle($page, '1000000')->call('settle')->assertSee('Choose the SYP cash or bank account the difference goes through.');

    expect(Voucher::query()->count())->toBe(1);
});

it('opens the journal entry from the menu on a journal voucher, for those who may prepare vouchers', function () {
    $this->actingAs(saUser(RoleId::Accountant, 'Ann'))->get('/entries/journal')
        ->assertOk()
        ->assertSeeLivewire(VoucherBuilder::class)
        ->assertSee('&quot;type&quot;:&quot;JV&quot;', false);
    $this->get('/entries/advances')->assertOk()->assertSeeHtml('data-test="open-advances"');

    $this->actingAs(saUser(RoleId::FinancialManager, 'Fadi'))->get('/entries/journal')->assertForbidden();
    $this->get('/entries/advances')->assertOk()->assertDontSeeHtml('data-test="start-give"');
});
