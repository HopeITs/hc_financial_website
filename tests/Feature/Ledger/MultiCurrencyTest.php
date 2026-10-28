<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Livewire\CurrencySettings;
use App\Models\Currency;
use App\Models\GlAccount;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\Currencies;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\ProjectAverageRate;
use App\Services\Ledger\Rules\CashAccountInOwnCurrency;
use App\Services\Ledger\TrialBalance;
use App\Services\Ledger\VoucherDraftBuilder;
use Database\Seeders\ChartOfAccountsSeeder;
use Livewire\Livewire;

/*
 * Decision R4-4: the books are not fixed to USD and SYP. Currencies are rows of `gl_currencies`; EUR is seeded and a new one
 * can be added. A rate is typed the way its currency is quoted (SYP per dollar, dollars per euro).
 */

/** An EUR cash account next to the Fransabank accounts, so euros can be held and paid out. */
function euroCashAccount(): GlAccount
{
    $account = GlAccount::query()->findOrFail('1171')->replicate();
    $account->code = '1173';
    $account->name_en = 'Fransabank – EUR';
    $account->name_ar = 'فرنسبنك – يورو';
    $account->currency = 'EUR';
    $account->save();

    return $account;
}

/** 1,000 euros received at 1.14 dollars per euro into the EUR account, from the OO grant, project F2F. */
function euroReceipt(): Voucher
{
    return app(PostVoucher::class)->post(ledgerReceipt(lines: [
        new LineDraft('1173', 'EUR', debit: 1000, rate: '1.14', debitUsd: 1140, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4107', 'USD', credit: 1140, projectCode: 'F2F'),
    ]));
}

describe('ledger', function () {
    beforeEach(fn () => createLedgerSchema());

    it('seeds USD, SYP and EUR, with USD as the base', function () {
        $currencies = app(Currencies::class);

        expect($currencies->codes())->toBe(['USD', 'SYP', 'EUR'])
            ->and($currencies->foreign())->toBe(['SYP', 'EUR'])
            ->and($currencies->isUsdPerUnit('EUR'))->toBeTrue()
            ->and($currencies->isUsdPerUnit('SYP'))->toBeFalse();
    });

    it('converts each currency the way it is quoted', function () {
        $currencies = app(Currencies::class);

        expect($currencies->toUsd('SYP', '150000.00', '15000'))->toBe('10.00')
            ->and($currencies->toUsd('EUR', '85075.00', '1.14'))->toBe('96985.50')
            ->and($currencies->toUsd('USD', '12.50', '1'))->toBe('12.50')
            ->and($currencies->fromUsd('SYP', '10.00', '15000'))->toBe('150000.00')
            ->and($currencies->fromUsd('EUR', '114.00', '1.14'))->toBe('100.00')
            ->and($currencies->rateOf('EUR', '1000.00', '1140.00'))->toBe('1.140000')
            ->and($currencies->rateOf('SYP', '148000000.00', '10000.00'))->toBe('14800.000000');
    });

    it('rule 2: takes EUR lines, an EUR cash account only in EUR, and a currency once it is added', function () {
        euroCashAccount();

        $draft = ledgerReceipt(lines: [
            new LineDraft('1173', 'EUR', debit: 100, rate: '1.14', debitUsd: 114),
            new LineDraft('1173', 'USD', debit: 10),
            new LineDraft('4101', 'GBP', credit: 124, creditUsd: 124),
        ]);
        expect(ruleLines(new CashAccountInOwnCurrency, $draft))->toBe([2, 3]);

        Currency::query()->create(['code' => 'GBP', 'name_en' => 'Pound sterling', 'name_ar' => 'جنيه إسترليني', 'quote' => 'usd_per_unit']);
        app()->forgetInstance(Currencies::class);

        expect(ruleLines(new CashAccountInOwnCurrency, $draft))->toBe([2]);
    });

    it('refuses a line in a currency that was switched off', function () {
        Currency::query()->whereKey('EUR')->update(['is_active' => false]);
        app()->forgetInstance(Currencies::class);

        $draft = ledgerReceipt(lines: [new LineDraft('4101', 'EUR', credit: 100, rate: '1.14', creditUsd: 114)]);

        expect(ruleLines(new CashAccountInOwnCurrency, $draft))->toBe([1]);
    });

    it('builds an EUR line at its typed rate and asks for a rate when none is typed', function () {
        euroCashAccount();
        $row = ['account' => '1173', 'side' => 'debit', 'amount' => '1000', 'rate' => '1.14', 'project' => 'F2F', 'fund_source' => 'donor_grant'];

        $built = app(VoucherDraftBuilder::class)->build('CRV', '2026-03-10', '', '', [$row, ['account' => '4107', 'side' => 'credit', 'amount' => '1140', 'project' => 'F2F']], 5);

        expect($built->problems)->toBe([])
            ->and($built->draft->lines[0]->currency)->toBe('EUR')
            ->and($built->draft->lines[0]->debitUsd)->toBe('1140.00')
            ->and($built->draft->lines[0]->rate)->toBe('1.140000');

        $missing = app(VoucherDraftBuilder::class)->build('CRV', '2026-03-10', '', '', [['rate' => ''] + $row], 5);

        expect($missing->problems)->toContain(__('ledger.builder.needs_rate', ['line' => 1]));
    });

    it('posts euros, keeps them as a third balance and measures their average rate', function () {
        euroCashAccount();
        euroReceipt();
        Voucher::query()->update(['status' => 'approved']);

        $held = app(ProjectAverageRate::class)->holding('F2F', 'EUR');

        expect($held)->toBe(['orig' => '1000.00', 'usd' => '1140.00'])
            ->and(app(ProjectAverageRate::class)->rate('F2F', 'EUR'))->toBe('1.140000');
    });

    it('shows the trial balance in EUR: euro lines as entered, dollar lines converted at the typed rate', function () {
        euroCashAccount();
        euroReceipt();
        Voucher::query()->update(['status' => 'approved']);

        $rows = collect(app(TrialBalance::class)->asAt(null, 'EUR', '1.14')['classes'])->flatMap(fn ($class) => $class['rows'])->keyBy('code');

        expect($rows['1173']['debit'])->toBe('1000.00')
            ->and($rows['4107']['credit'])->toBe('1000.00');

        $usd = collect(app(TrialBalance::class)->asAt()['classes'])->flatMap(fn ($class) => $class['rows'])->keyBy('code');

        expect($usd['1173']['debit'])->toBe('1140.00');
    });
});

describe('currencies screen', function () {
    beforeEach(function () {
        createFinanceSchema();
        (new ChartOfAccountsSeeder)->run();
    });

    function currencyUser(RoleId $role, string $name = 'Ann'): User
    {
        return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
    }

    it('lets Head Office add a currency that the books then accept', function () {
        Livewire::actingAs(currencyUser(RoleId::HeadOffice))->test(CurrencySettings::class)
            ->call('add')
            ->set('code', 'gbp')
            ->set('nameEn', 'Pound sterling')
            ->set('nameAr', 'جنيه إسترليني')
            ->set('quote', 'usd_per_unit')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('GBP');

        app()->forgetInstance(Currencies::class);

        expect(app(Currencies::class)->codes())->toContain('GBP')
            ->and(app(Currencies::class)->isUsdPerUnit('GBP'))->toBeTrue();
    });

    it('refuses a duplicate or malformed code', function () {
        Livewire::actingAs(currencyUser(RoleId::HeadOffice))->test(CurrencySettings::class)
            ->call('add')->set('code', 'EUR')->set('nameEn', 'x')->set('nameAr', 'x')->call('save')->assertHasErrors('code')
            ->set('code', 'E1')->call('save')->assertHasErrors('code');
    });

    it('keeps the way a used currency is quoted', function () {
        euroCashAccount();
        DB::table('gl_vouchers')->insert(['id' => 'CRV-2026-0001', 'type' => 'CRV', 'date' => '2026-03-10', 'status' => 'approved', 'prepared_by' => 1, 'source' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gl_voucher_lines')->insert(['voucher_id' => 'CRV-2026-0001', 'line_no' => 1, 'account_code' => '1173', 'currency' => 'EUR', 'debit_orig' => 10, 'rate' => 1.14, 'debit_usd' => 11.4]);

        Livewire::actingAs(currencyUser(RoleId::HeadOffice))->test(CurrencySettings::class)
            ->call('edit', 'EUR')
            ->set('quote', 'per_usd')
            ->call('save')
            ->assertHasErrors('quote');

        expect(Currency::query()->find('EUR')->quote)->toBe('usd_per_unit');
    });

    it('lets the accountant see the currencies but not add one', function () {
        Livewire::actingAs(currencyUser(RoleId::Accountant))->test(CurrencySettings::class)
            ->assertDontSeeHtml('data-test="add-currency"')
            ->call('add')
            ->assertForbidden();
    });
});
