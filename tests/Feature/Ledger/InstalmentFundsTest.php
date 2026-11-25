<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Livewire\InstalmentFundsScreen;
use App\Models\FundExchange;
use App\Models\LedgerRequest;
use App\Models\MicroLoan;
use App\Models\User;
use App\Services\Loans\InstalmentFunds;
use App\Services\Loans\LoanException;
use App\Services\Loans\MicroLoans;
use App\Services\Treasury\DailyRates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    seedChartWithTestProjects();

    $this->manager = User::query()->findOrFail(createFinanceUser('Fadi', 'secret pass', RoleId::FinancialManager->name, RoleId::FinancialManager->value));
    app(DailyRates::class)->propose('SYP', '2026-05-10', '16000', null, $this->manager, approveNow: true);
});

/** A loan of the F2F project (Caritas Poland, Aleppo), in four instalments from 2026-04-30, with no grant. */
function fundProjectLoan(string $amount, string $currency, string $rate = ''): MicroLoan
{
    $request = LedgerRequest::query()->create(['type' => 'purchase', 'project_code' => 'F2F', 'amount' => '5000.00', 'currency' => 'USD', 'status' => 'approved', 'requested_by' => 3]);

    return app(MicroLoans::class)->open(
        'Huda', 'F2F', $amount, '0', $currency, $rate, CarbonImmutable::parse('2026-03-01'), 4, CarbonImmutable::parse('2026-04-30'),
        $currency === 'SYP' ? '1112' : '1111', '', FundSource::DonorGrant, 5, $request->id,
    )['loan'];
}

function fundRepay(MicroLoan $loan, string $amount): void
{
    app(MicroLoans::class)->receive($loan->fresh(), CarbonImmutable::parse('2026-05-10'), $amount, $loan->currency === 'SYP' ? '1112' : '1111', 5);
}

/** A loan for a new beneficiary from the Caritas Poland fund in Aleppo. */
function fundPoolLoan(string $loan, string $grant, string $currency, string $cash, string $rate = '', string $city = 'Aleppo'): array
{
    return app(MicroLoans::class)->open(
        'Samir', '', $loan, $grant, $currency, $rate, CarbonImmutable::parse('2026-05-12'), 2, CarbonImmutable::parse('2026-06-12'),
        $cash, '', FundSource::DonorGrant, 5, fundFunder: 'Caritas Poland', fundCity: $city,
    );
}

it('shows each funder\'s fund by city, with the instalments still owed, the pounds at the day\'s rate', function () {
    fundRepay(fundProjectLoan('12000000', 'SYP', '15000'), '3000000');
    fundRepay(fundProjectLoan('400', 'USD'), '100');

    // 400 $ + (3,000,000 held + 9,000,000 owed) SYP at 16,000 = 400 + 750.
    expect(app(InstalmentFunds::class)->balances(CarbonImmutable::parse('2026-05-10')))->toBe([[
        'funder' => 'Caritas Poland', 'city' => 'Aleppo', 'syp' => '3000000.00', 'usd' => '100.00', 'owed_syp' => '9000000.00', 'owed_usd' => '300.00',
        'fixed_syp' => '0.00', 'fixed_usd' => '0.00', 'value_usd' => '1150.00',
    ]]);
});

it('funds a new beneficiary from a fund with no project, and refuses more than it holds or cash of another city', function () {
    fundRepay(fundProjectLoan('400', 'USD'), '300');

    ['loan' => $loan, 'voucher' => $voucher] = fundPoolLoan('200', '50', 'USD', '1111');

    expect($loan->only(['project_code', 'funder', 'city']))->toBe(['project_code' => null, 'funder' => 'Caritas Poland', 'city' => 'Aleppo'])
        ->and($loan->label())->toBe('Caritas Poland · Aleppo')
        // Funded like a micro-project file (finance decision of 2026-11-25): the whole 250 spent on the grants, then the
        // 200 to repay moved from the grants to the loan.
        ->and($voucher->lines->map(fn ($line): array => [$line->account_code, $line->project_code, $line->donor, (string) $line->debit_usd, (string) $line->credit_usd])->all())->toBe([
            ['5413', null, 'Caritas Poland', '250.00', '0.00'],
            ['1111', null, 'Caritas Poland', '0.00', '250.00'],
            ['1204', null, 'Caritas Poland', '200.00', '0.00'],
            ['5413', null, 'Caritas Poland', '0.00', '200.00'],
        ])
        ->and($voucher->lines[1]->fund_source)->toBe(FundSource::RecoveredLoans)
        ->and($voucher->lines[2]->fund_source)->toBe(FundSource::RecoveredLoans)
        ->and(app(InstalmentFunds::class)->available('Caritas Poland', 'Aleppo', 'USD', CarbonImmutable::parse('2026-05-12')))->toBe('50.00');

    expect(fn () => fundPoolLoan('100', '0', 'USD', '1111'))->toThrow(LoanException::class)
        ->and(fn () => fundPoolLoan('10', '0', 'USD', '1121'))->toThrow(LoanException::class);
});

it('pays a dollar loan from a fund\'s pounds at the invoice\'s rate', function () {
    fundRepay(fundProjectLoan('12000000', 'SYP', '15000'), '3000000');

    ['loan' => $loan, 'voucher' => $voucher] = fundPoolLoan('100', '0', 'USD', '1112', '15000');

    expect($loan->currency)->toBe('USD')
        ->and([$voucher->lines[0]->account_code, $voucher->lines[0]->currency, (string) $voucher->lines[0]->debit_usd])->toBe(['5413', 'USD', '100.00'])
        ->and([$voucher->lines[1]->account_code, $voucher->lines[1]->currency, (string) $voucher->lines[1]->credit_orig, (string) $voucher->lines[1]->credit_usd])->toBe(['1112', 'SYP', '1500000.00', '100.00'])
        ->and([$voucher->lines[2]->account_code, (string) $voucher->lines[2]->debit_usd])->toBe(['1204', '100.00'])
        ->and(app(InstalmentFunds::class)->available('Caritas Poland', 'Aleppo', 'SYP', CarbonImmutable::parse('2026-05-12')))->toBe('1500000.00');
});

it('fixes a fund\'s pounds in the organisation\'s dollars and gives them back later, the organisation keeping the saving', function () {
    fundRepay(fundProjectLoan('12000000', 'SYP', '15000'), '3000000');
    $funds = app(InstalmentFunds::class);
    $day = CarbonImmutable::parse('2026-05-10');

    expect(fn () => $funds->exchange('Caritas Poland', 'Aleppo', FundExchange::FIX, '3000001', '15000', '1112', '1111', $day, $this->manager))->toThrow(LoanException::class);

    $fix = $funds->exchange('Caritas Poland', 'Aleppo', FundExchange::FIX, '3,000,000', '15000', '1112', '1111', $day, $this->manager);
    $lines = DB::table('gl_voucher_lines')->where('voucher_id', $fix->voucher_id)->orderBy('id')->get();

    expect((string) $fix->usd_amount)->toBe('200.00')
        ->and($lines->map(fn ($line): array => [$line->account_code, $line->project_code, $line->fund_source, (float) $line->debit_usd, (float) $line->credit_usd])->all())->toBe([
            ['1112', null, 'recovered_loans', 0.0, 200.0],
            ['1112', 'GEN-ALP-SYP', 'chc_own_funds', 200.0, 0.0],
            ['1111', 'GEN-ALP-USD', 'chc_own_funds', 0.0, 200.0],
            ['1111', null, 'recovered_loans', 200.0, 0.0],
        ])
        ->and($funds->balances($day)[0])->toMatchArray(['syp' => '0.00', 'usd' => '200.00', 'fixed_syp' => '3000000.00', 'fixed_usd' => '200.00']);

    // The pound fell to 20,000: the same pounds are worth 150 $ now, so the organisation saves 50 $.
    expect($funds->saving($fix, '20000'))->toBe('50.00');
    $back = $funds->giveBack($fix, '20000', $day->addDays(30), $this->manager);

    expect($back->isOpen())->toBeFalse()
        ->and($funds->balances($day->addDays(30))[0])->toMatchArray(['syp' => '3000000.00', 'usd' => '0.00', 'fixed_syp' => '0.00'])
        ->and(fn () => $funds->giveBack($back, '20000', $day->addDays(31), $this->manager))->toThrow(LoanException::class);
});

it('lets only the Financial Manager exchange a fund\'s pounds', function () {
    fundRepay(fundProjectLoan('12000000', 'SYP', '15000'), '3000000');
    $accountant = User::query()->findOrFail(createFinanceUser('Ann', 'secret pass', RoleId::Accountant->name, RoleId::Accountant->value));
    // The date proposes its day's rate, and the fund clears the accounts: both are set before what they reset.
    $form = ['fund' => 'Caritas Poland|Aleppo', 'date' => '2026-05-10', 'kind' => FundExchange::FIX, 'pounds' => '1500000', 'rate' => '15000', 'poundAccount' => '1112', 'dollarAccount' => '1111'];

    Livewire::actingAs($accountant)->test(InstalmentFundsScreen::class)
        ->assertSee('Caritas Poland')
        ->set($form)->call('exchange')->assertForbidden();

    Livewire::actingAs($this->manager)->test(InstalmentFundsScreen::class)
        ->set($form)->call('exchange')
        ->assertSet('problems', [])
        ->assertSee(__('funds.exchanged.fix', ['voucher' => FundExchange::query()->value('voucher_id'), 'usd' => '100.00']));

    expect(FundExchange::query()->count())->toBe(1);
});
