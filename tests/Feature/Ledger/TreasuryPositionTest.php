<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Livewire\Treasury;
use App\Models\User;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\TreasuryPosition;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    $usd = fn (string $account, ?int $debit, ?int $credit, string $project, ?string $contact = null) => new LineDraft(
        $account, 'USD', debit: $debit, credit: $credit, projectCode: $project, fundSource: FundSource::DonorGrant, contact: $contact,
    );
    $post = fn (string $date, array $lines, VoucherType $type = VoucherType::Transfer) => ledgerApprovedVoucher(
        ['date' => CarbonImmutable::parse($date), 'type' => $type], $lines,
    );

    // Aleppo's general USD account is funded with 5,000 USD.
    $post('2026-03-01', [$usd('1111', 5000, null, 'GEN-ALP-USD'), new LineDraft('4101', 'USD', credit: 5000, projectCode: 'GEN-ALP-USD')], VoucherType::Receipt);

    // Two bulk purchases of pounds (transfers into the pound safe): 7.5M for 500 USD, then 3.2M for 200 USD.
    $post('2026-03-10', [
        new LineDraft('1112', 'SYP', debit: 7500000, rate: 15000, debitUsd: 500, projectCode: 'GEN-ALP-SYP', fundSource: FundSource::DonorGrant),
        $usd('1111', null, 500, 'GEN-ALP-USD'),
    ]);
    $post('2026-03-20', [
        new LineDraft('1112', 'SYP', debit: 3200000, rate: 16000, debitUsd: 200, projectCode: 'GEN-ALP-SYP', fundSource: FundSource::DonorGrant),
        $usd('1111', null, 200, 'GEN-ALP-USD'),
    ]);

    // 1.5M pounds (100 USD in the books) go to a project.
    $post('2026-04-05', [
        $usd('1121', 100, null, 'F2F'),
        new LineDraft('1112', 'SYP', credit: 1500000, rate: 15000, creditUsd: 100, projectCode: 'GEN-ALP-SYP', fundSource: FundSource::DonorGrant),
    ]);

    // 300 USD on the way from the transfer company.
    $post('2026-03-12', [$usd('1113', 300, null, 'GEN-ALP-USD'), $usd('1111', null, 300, 'GEN-ALP-USD')]);

    // 1,000 USD with the transfer company.
    $post('2026-03-14', [$usd('1161', 1000, null, 'F2F', 'Hawala Co'), $usd('1111', null, 1000, 'F2F')]);

    // Latakia: pounds received for 10.01 USD against 10 USD of income leaves one cent on the exchange differences account.
    $post('2026-03-15', [
        new LineDraft('1122', 'SYP', debit: 150000, rate: 15000, debitUsd: '10.01', projectCode: 'GEN-LTK-SYP', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: 10, projectCode: 'GEN-LTK-SYP'),
    ], VoucherType::Receipt);

    // Pending, so not counted.
    app(PostVoucher::class)->post(ledgerReceipt(['date' => CarbonImmutable::parse('2026-03-16')], [
        $usd('1111', 999, null, 'GEN-ALP-USD'), new LineDraft('4101', 'USD', credit: 999, projectCode: 'GEN-ALP-USD'),
    ]));
});

function treasuryCity(string $name, ?string $asAt = null)
{
    return collect(app(TreasuryPosition::class)->at($asAt)->cities)->firstWhere('city', $name);
}

it('lists the five cities, each with its two general accounts', function () {
    $report = app(TreasuryPosition::class)->at();

    expect(collect($report->cities)->pluck('city')->all())->toBe(['Aleppo', 'Damascus', 'Homs', 'Latakia', 'Tartous'])
        ->and(treasuryCity('Aleppo')->usdProject)->toBe('GEN-ALP-USD')
        ->and(treasuryCity('Aleppo')->sypProject)->toBe('GEN-ALP-SYP')
        ->and(treasuryCity('Tartous')->sypProject)->toBe('GEN-TRT-SYP');
});

it('shows the dollars the general USD account holds', function () {
    // 5,000 received, 700 spent on pounds; the 300 on the way stays in the account (it moved from the safe to transit).
    expect(treasuryCity('Aleppo')->usdHeld)->toBe('4300.00')
        ->and(treasuryCity('Damascus')->usdHeld)->toBe('0.00');
});

it('shows the pounds the general SYP account holds, what they cost and their average rate', function () {
    $aleppo = treasuryCity('Aleppo');

    expect($aleppo->sypHeld)->toBe('9200000.00')
        ->and($aleppo->sypBookUsd)->toBe('600.00')
        ->and($aleppo->sypAverageRate)->toBe('15333.333333')
        ->and(treasuryCity('Homs')->sypHeld)->toBe('0.00')
        ->and(treasuryCity('Homs')->sypAverageRate)->toBeNull();
});

it('totals the bulk purchases, the transfers into the pound safe, and ignores other receipts of pounds', function () {
    $aleppo = treasuryCity('Aleppo');

    expect($aleppo->purchasedSyp)->toBe('10700000.00')
        ->and($aleppo->purchasedUsd)->toBe('700.00')
        ->and($aleppo->purchaseRate)->toBe('15285.714285')
        ->and($aleppo->purchaseCount)->toBe(2)
        ->and($aleppo->lastPurchase)->toBe('2026-03-20')
        ->and(treasuryCity('Latakia')->purchaseCount)->toBe(0)
        ->and(treasuryCity('Latakia')->lastPurchase)->toBeNull();
});

it('shows the cash of each safe by currency, in its own currency and in USD', function () {
    $safes = collect(treasuryCity('Aleppo')->safes)->keyBy('code');

    expect($safes['1111']->currency)->toBe('USD')
        ->and($safes['1111']->orig)->toBe('3000.00')
        ->and($safes['1111']->usd)->toBe('3000.00')
        ->and($safes['1112']->currency)->toBe('SYP')
        ->and($safes['1112']->orig)->toBe('9200000.00')
        ->and($safes['1112']->usd)->toBe('600.00')
        ->and($safes->has('1113'))->toBeFalse()
        ->and(collect(treasuryCity('Latakia')->safes)->keyBy('code')['1122']->orig)->toBe('150000.00');
});

it('lists the cash in transit apart, by city', function () {
    $transit = collect(treasuryCity('Aleppo')->inTransit)->keyBy('code');

    expect(collect(treasuryCity('Aleppo')->inTransit)->pluck('code')->all())->toBe(['1113', '1114'])
        ->and($transit['1113']->orig)->toBe('300.00')
        ->and($transit['1114']->orig)->toBe('0.00')
        ->and(app(TreasuryPosition::class)->at()->totalInTransitUsd())->toBe('300.00');
});

it('lists the cash that belongs to no city, with the transfer company on its own', function () {
    $report = app(TreasuryPosition::class)->at();
    $other = collect($report->other)->keyBy('code');

    expect(collect($report->other)->pluck('code')->all())->toBe(['1161', '1171', '1172'])
        ->and($other['1161']->usd)->toBe('1000.00')
        ->and($report->transferCompanyUsd())->toBe('1000.00');
});

it('keeps the exchange reserve of each city from the exchange differences on its general accounts', function () {
    expect(treasuryCity('Latakia')->reserve)->toBe('0.01')
        ->and(treasuryCity('Aleppo')->reserve)->toBe('0.00')
        ->and(app(TreasuryPosition::class)->at()->totalReserve())->toBe('0.01');
});

it('counts approved vouchers only', function () {
    expect(treasuryCity('Aleppo')->usdHeld)->toBe('4300.00')
        ->and(collect(treasuryCity('Aleppo')->safes)->keyBy('code')['1111']->usd)->toBe('3000.00');
});

it('shows the position as at a day, including that day', function () {
    $early = treasuryCity('Aleppo', '2026-03-10');
    $later = treasuryCity('Aleppo', '2026-03-31');

    expect($early->sypHeld)->toBe('7500000.00')
        ->and($early->purchaseCount)->toBe(1)
        ->and($early->purchasedUsd)->toBe('500.00')
        ->and($later->sypHeld)->toBe('10700000.00')
        ->and($later->purchaseCount)->toBe(2)
        ->and(treasuryCity('Aleppo', '2026-02-28')->sypHeld)->toBe('0.00');
});

it('adds the cities up for the totals at the top', function () {
    $report = app(TreasuryPosition::class)->at();

    expect($report->totalUsdHeld())->toBe('4300.00')
        ->and($report->totalSypHeld())->toBe('9350000.00');
});

it('opens for a signed-in user and shows each city with its position', function () {
    $user = User::query()->whereKey(createFinanceUser('tuser', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value))->firstOrFail();

    $this->actingAs($user)->get('/screens/treasury')->assertOk()->assertSee('GEN-ALP-SYP');

    Livewire::actingAs($user)->test(Treasury::class)
        ->assertSeeInOrder(['Aleppo', '4,300.00', '9,200,000.00'])
        ->assertSee('1,000.00')
        ->set('asAt', '2026-03-10')
        ->assertSee('7,500,000.00')
        ->assertDontSee('9,200,000.00');
});

it('names the cities in Arabic and falls back to the stored name', function () {
    app()->setLocale('ar');

    expect(treasuryCity('Aleppo')->label())->toBe('حلب')
        ->and(treasuryCity('Tartous')->label())->toBe('طرطوس')
        ->and(collect(treasuryCity('Aleppo')->safes)->keyBy('code')['1111']->name())->toContain('حلب');
});
