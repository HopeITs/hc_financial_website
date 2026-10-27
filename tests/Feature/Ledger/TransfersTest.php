<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Livewire\Transfers;
use App\Models\AllocationRate;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\TransferBuilder;
use App\Services\Ledger\TransferRateException;
use App\Services\Ledger\TransferRates;
use App\Services\Ledger\TreasuryPosition;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    // Aleppo's general USD account has 5,000 USD and its general SYP account 7.5M pounds bought for 500 USD (average 15,000).
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-01'), 'type' => VoucherType::Receipt], [
        new LineDraft('1111', 'USD', debit: 5000, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury),
        new LineDraft('4101', 'USD', credit: 5000, projectCode: 'GEN-ALP-USD'),
    ]);
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-10'), 'type' => VoucherType::Transfer], [
        new LineDraft('1112', 'SYP', debit: 7500000, rate: 15000, debitUsd: 500, projectCode: 'GEN-ALP-SYP', fundSource: FundSource::Treasury),
        new LineDraft('1111', 'USD', credit: 500, projectCode: 'GEN-ALP-USD', fundSource: FundSource::Treasury),
    ]);
});

function approvedRate(string $city, string $rate, string $from, bool $average = false): AllocationRate
{
    return AllocationRate::query()->create([
        'city' => $city, 'rate' => $rate, 'effective_from' => $from, 'status' => 'approved',
        'proposed_by' => 1, 'approved_by' => 2, 'allocate_at_average_cost' => $average,
    ]);
}

function buildTransfer(string $kind, array $with = [])
{
    $with += [
        'date' => '2026-04-01', 'city' => 'Aleppo', 'project' => 'F2F', 'other' => '', 'currency' => 'SYP', 'amount' => '2100000',
        'cash' => '1112', 'contact' => '', 'description' => '',
    ];

    return app(TransferBuilder::class)->build($kind, $with['date'], $with['city'], $with['project'], $with['other'], $with['currency'], $with['amount'], $with['cash'], $with['contact'], $with['description'], 7);
}

function sumLines($draft, string $side): string
{
    $total = '0.00';
    foreach ($draft->lines as $line) {
        $total = bcadd($total, $side === 'debit' ? $line->debitUsd : $line->creditUsd, 2);
    }

    return $total;
}

it('proposes a rate, which waits, and another user approves it', function () {
    $rates = app(TransferRates::class);

    $rate = $rates->propose('Aleppo', '14,000', '2026-04-01', false, 1);

    expect($rate->status)->toBe('pending')
        ->and($rate->rate)->toBe('14000.000000')
        ->and($rates->approvedFor('Aleppo', CarbonImmutable::parse('2026-04-05')))->toBeNull();

    $rates->approve($rate->id, 2);

    expect($rates->approvedFor('Aleppo', CarbonImmutable::parse('2026-04-05'))?->id)->toBe($rate->id);
});

it('does not let the proposer decide, nor decide twice', function () {
    $rates = app(TransferRates::class);
    $rate = $rates->propose('Aleppo', '14000', '2026-04-01', false, 1);

    expect(fn () => $rates->approve($rate->id, 1))->toThrow(TransferRateException::class);

    $rates->reject($rate->id, 2);

    expect($rate->fresh()->status)->toBe('rejected')
        ->and(fn () => $rates->approve($rate->id, 2))->toThrow(TransferRateException::class)
        ->and($rates->approvedFor('Aleppo', CarbonImmutable::parse('2026-04-05')))->toBeNull();
});

it('refuses a rate with no city, no value or no date', function () {
    $rates = app(TransferRates::class);

    expect(fn () => $rates->propose('Paris', '14000', '2026-04-01', false, 1))->toThrow(TransferRateException::class)
        ->and(fn () => $rates->propose('Aleppo', '0', '2026-04-01', false, 1))->toThrow(TransferRateException::class)
        ->and(fn () => $rates->propose('Aleppo', 'abc', '2026-04-01', false, 1))->toThrow(TransferRateException::class)
        ->and(fn () => $rates->propose('Aleppo', '14000', '2026-13-45', false, 1))->toThrow(TransferRateException::class);
});

it('uses the latest approved rate that has started', function () {
    approvedRate('Aleppo', '13000', '2026-01-01');
    approvedRate('Aleppo', '14000', '2026-04-01');
    approvedRate('Aleppo', '15000', '2026-06-01');
    app(TransferRates::class)->propose('Aleppo', '99999', '2026-04-02', false, 1);

    $rates = app(TransferRates::class);

    expect($rates->approvedFor('Aleppo', CarbonImmutable::parse('2026-03-31'))?->rate)->toBe('13000.000000')
        ->and($rates->approvedFor('Aleppo', CarbonImmutable::parse('2026-04-01'))?->rate)->toBe('14000.000000')
        ->and($rates->approvedFor('Aleppo', CarbonImmutable::parse('2026-05-01'))?->rate)->toBe('14000.000000')
        ->and($rates->approvedFor('Latakia', CarbonImmutable::parse('2026-05-01')))->toBeNull();
});

it('moves dollars from the general USD account to a project one for one', function () {
    $built = buildTransfer('usd_to_project', ['amount' => '1200', 'cash' => '1111']);

    expect($built->problems)->toBe([])
        ->and($built->draft->transferKind)->toBe('usd_to_project')
        ->and($built->draft->lines)->toHaveCount(2);

    [$debit, $credit] = $built->draft->lines;
    expect($debit->projectCode)->toBe('F2F')
        ->and($debit->debitOrig)->toBe('1200.00')
        ->and($credit->projectCode)->toBe('GEN-ALP-USD')
        ->and($credit->creditOrig)->toBe('1200.00');

    $voucher = app(PostVoucher::class)->post($built->draft);
    expect($voucher->type)->toBe(VoucherType::Transfer)->and($voucher->transfer_kind)->toBe('usd_to_project');
});

it('moves pounds at the approved rate, the project paying the dollars through the transfer company, the difference going to 5503', function () {
    approvedRate('Aleppo', '14000', '2026-04-01');

    $built = buildTransfer('syp_to_project');

    // 2.1M pounds cost 140 USD (average 15,000); at 14,000 the project pays 150 USD; the general account gains 10.
    expect($built->problems)->toBe([])
        ->and($built->rate)->toBe('14000.000000')
        ->and($built->usd)->toBe('150.00')
        ->and($built->difference)->toBe('10.00')
        ->and(sumLines($built->draft, 'debit'))->toBe('300.00')
        ->and(sumLines($built->draft, 'credit'))->toBe('300.00');

    $lines = collect($built->draft->lines)->keyBy(fn (LineDraft $line) => $line->accountCode.($line->debitOrig > 0 ? 'D' : 'C').'-'.$line->projectCode);
    expect($lines['1112D-F2F']->debitUsd)->toBe('150.00')
        ->and($lines['1112C-GEN-ALP-SYP']->creditUsd)->toBe('140.00')
        ->and($lines['1161C-F2F']->creditUsd)->toBe('150.00')
        ->and($lines['1161D-GEN-ALP-USD']->debitUsd)->toBe('150.00')
        ->and($lines['5503C-GEN-ALP-SYP']->creditUsd)->toBe('10.00')
        ->and($lines['1161D-GEN-ALP-USD']->contact)->toBe('Transfer company');

    $voucher = app(PostVoucher::class)->post($built->draft);
    DB::table('gl_vouchers')->where('id', $voucher->id)->update(['status' => 'approved', 'approved_by' => 9]);

    $aleppo = collect(app(TreasuryPosition::class)->at()->cities)->firstWhere('city', 'Aleppo');
    expect($aleppo->sypHeld)->toBe('5400000.00')
        ->and($aleppo->sypBookUsd)->toBe('360.00')
        ->and($aleppo->reserve)->toBe('10.00');
});

it('books a loss on 5503 when the approved rate is stronger than the cost', function () {
    approvedRate('Aleppo', '16000', '2026-04-01');

    $built = buildTransfer('syp_to_project', ['amount' => '1600000']);

    // Cost 106.67, project pays 100.00: a loss of 6.67 on the debit side.
    expect($built->problems)->toBe([])
        ->and($built->difference)->toBe('-6.67');
    $reserve = collect($built->draft->lines)->first(fn (LineDraft $line) => $line->accountCode === '5503');
    expect($reserve->debitUsd)->toBe('6.67');
    app(PostVoucher::class)->post($built->draft);
});

it('allocates at average cost when the approved rate carries the switch: no difference', function () {
    approvedRate('Aleppo', '14000', '2026-04-01', average: true);

    $built = buildTransfer('syp_to_project');

    expect($built->problems)->toBe([])
        ->and($built->rate)->toBe('15000.000000')
        ->and($built->usd)->toBe('140.00')
        ->and($built->difference)->toBe('0.00')
        ->and(collect($built->draft->lines)->contains(fn (LineDraft $line) => $line->accountCode === '5503'))->toBeFalse();
    app(PostVoucher::class)->post($built->draft);
});

it('says so when the general account has no pounds, not enough pounds, or the city has no approved rate', function () {
    approvedRate('Aleppo', '14000', '2026-04-01');
    approvedRate('Latakia', '14000', '2026-04-01');

    expect(buildTransfer('syp_to_project', ['city' => 'Latakia', 'cash' => '1122'])->problems)->toBe([__('ledger.transfers.no_pounds', ['project' => 'GEN-LTK-SYP'])])
        ->and(buildTransfer('syp_to_project', ['amount' => '9000000'])->problems)->toBe([__('ledger.transfers.not_enough_pounds', ['project' => 'GEN-ALP-SYP', 'held' => '7,500,000.00'])])
        ->and(buildTransfer('syp_to_project', ['date' => '2026-03-31'])->problems)->toBe([__('ledger.transfers.no_rate', ['city' => 'Aleppo', 'date' => '2026-03-31'])])
        ->and(buildTransfer('syp_to_project', ['date' => '2026-03-31'])->draft)->toBeNull();
});

it('says so when the general USD account has no dollars or not enough', function () {
    expect(buildTransfer('usd_to_project', ['amount' => '9000', 'cash' => '1111'])->problems)->toBe([__('ledger.transfers.not_enough_dollars', ['project' => 'GEN-ALP-USD', 'held' => '4,500.00'])])
        ->and(buildTransfer('usd_to_project', ['city' => 'Latakia', 'amount' => '10', 'cash' => '1121'])->problems)->toBe([__('ledger.transfers.no_dollars', ['project' => 'GEN-LTK-USD'])]);
});

it('returns pounds from a project to the general account at the project\'s average rate', function () {
    approvedRate('Aleppo', '14000', '2026-04-01');
    $voucher = app(PostVoucher::class)->post(buildTransfer('syp_to_project')->draft);
    DB::table('gl_vouchers')->where('id', $voucher->id)->update(['status' => 'approved', 'approved_by' => 9]);

    // F2F now holds 2.1M pounds worth 150 USD (average 14,000); 700,000 of them go back, worth 50 USD.
    $built = buildTransfer('return', ['amount' => '700000', 'date' => '2026-04-10']);

    expect($built->problems)->toBe([])
        ->and($built->draft->transferKind)->toBe('return')
        ->and($built->rate)->toBe('14000.000000')
        ->and($built->usd)->toBe('50.00')
        ->and(sumLines($built->draft, 'debit'))->toBe('50.00');

    [$debit, $credit] = $built->draft->lines;
    expect($debit->projectCode)->toBe('GEN-ALP-SYP')->and($credit->projectCode)->toBe('F2F');
    app(PostVoucher::class)->post($built->draft);

    expect(buildTransfer('return', ['amount' => '9000000', 'date' => '2026-04-10'])->problems)
        ->toBe([__('ledger.transfers.not_enough_pounds', ['project' => 'F2F', 'held' => '2,100,000.00'])]);
});

it('moves dollars and pounds between two projects', function () {
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-03-20'), 'type' => VoucherType::Receipt], [
        new LineDraft('1111', 'USD', debit: 400, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: 400, projectCode: 'F2F'),
    ]);

    $usd = buildTransfer('between_projects', ['currency' => 'USD', 'amount' => '150', 'cash' => '1111', 'other' => 'SZ']);
    expect($usd->problems)->toBe([])->and($usd->draft->lines[0]->projectCode)->toBe('SZ')->and($usd->draft->lines[1]->projectCode)->toBe('F2F');
    app(PostVoucher::class)->post($usd->draft);

    expect(buildTransfer('between_projects', ['currency' => 'USD', 'other' => 'F2F', 'cash' => '1111', 'amount' => '10'])->problems)->toBe([__('ledger.transfers.same_project')])
        ->and(buildTransfer('between_projects', ['currency' => 'USD', 'other' => 'SZ', 'cash' => '1111', 'amount' => '10', 'project' => 'GEN-ALP-USD'])->problems)->toBe([__('ledger.transfers.bad_project', ['project' => 'GEN-ALP-USD'])])
        ->and(buildTransfer('between_projects', ['currency' => 'SYP', 'other' => 'SZ', 'amount' => '1000'])->problems)->toBe([__('ledger.transfers.no_pounds', ['project' => 'F2F'])]);
});

it('refuses a cash account that is in transit, in the wrong currency or in another city', function () {
    $problem = fn (string $cash, string $currency = 'USD', string $city = 'Aleppo') => buildTransfer('between_projects', ['currency' => $currency, 'city' => $city, 'cash' => $cash, 'other' => 'SZ', 'amount' => '1'])->problems;

    expect($problem('1113'))->toBe([__('ledger.transfers.bad_cash', ['currency' => 'USD', 'city' => 'Aleppo'])])
        ->and($problem('1112'))->toBe([__('ledger.transfers.bad_cash', ['currency' => 'USD', 'city' => 'Aleppo'])])
        ->and($problem('1121'))->toBe([__('ledger.transfers.bad_cash', ['currency' => 'USD', 'city' => 'Aleppo'])]);
});

it('refuses a bad kind, date, amount, currency or city', function () {
    expect(buildTransfer('nonsense')->problems)->toBe([__('ledger.transfers.bad_kind')])
        ->and(buildTransfer('usd_to_project', ['date' => '31/12/2026'])->problems)->toBe([__('ledger.builder.bad_date')])
        ->and(buildTransfer('usd_to_project', ['amount' => '-5'])->problems)->toBe([__('ledger.transfers.bad_amount')])
        ->and(buildTransfer('usd_to_project', ['amount' => 'abc'])->problems)->toBe([__('ledger.transfers.bad_amount')])
        ->and(buildTransfer('return', ['currency' => 'EUR'])->problems)->toBe([__('ledger.transfers.bad_currency')])
        ->and(buildTransfer('usd_to_project', ['city' => 'Paris'])->problems)->toBe([__('ledger.transfers.bad_city')]);
});

it('lets an accountant propose a rate and the financial manager approve it, then saves a transfer as a pending voucher', function () {
    $accountant = User::query()->whereKey(createFinanceUser('acc', 'secret pass', RoleId::Accountant->name, RoleId::Accountant->value))->firstOrFail();
    $manager = User::query()->whereKey(createFinanceUser('fm', 'secret pass', RoleId::FinancialManager->name, RoleId::FinancialManager->value))->firstOrFail();

    $this->actingAs($accountant)->get('/treasury/allocation-rates')->assertOk();

    Livewire::actingAs($accountant)->test(Transfers::class)
        ->set('rateCity', 'Aleppo')->set('rateValue', '14000')->set('rateFrom', '2026-04-01')
        ->call('propose')
        ->assertSet('notice', __('ledger.transfers.rate_proposed'))
        ->assertDontSee('approve-rate-');

    $rate = AllocationRate::query()->firstOrFail();

    Livewire::actingAs($accountant)->test(Transfers::class)->call('approve', $rate->id)->assertForbidden();

    Livewire::actingAs($manager)->test(Transfers::class)
        ->assertSee('approve-rate-'.$rate->id)
        ->call('approve', $rate->id)
        ->assertSet('notice', __('ledger.transfers.rate_approved'));
    expect($rate->fresh()->status)->toBe('approved');

    Livewire::actingAs($accountant)->test(Transfers::class)
        ->set('kind', 'syp_to_project')->set('date', '2026-04-01')->set('city', 'Aleppo')->set('project', 'F2F')
        ->set('amount', '2100000')
        ->assertSee('150.00')
        ->call('save');

    $voucher = Voucher::query()->firstWhere('transfer_kind', 'syp_to_project');
    expect($voucher)->not->toBeNull()->and($voucher->status->value)->toBe('pending_approval');
});

it('shows the problem and saves nothing when the transfer cannot be built', function () {
    $accountant = User::query()->whereKey(createFinanceUser('acc', 'secret pass', RoleId::Accountant->name, RoleId::Accountant->value))->firstOrFail();

    Livewire::actingAs($accountant)->test(Transfers::class)
        ->set('kind', 'syp_to_project')->set('date', '2026-04-01')->set('city', 'Aleppo')->set('project', 'F2F')->set('amount', '2100000')
        ->call('save')
        ->assertSet('failures', [__('ledger.transfers.no_rate', ['city' => 'Aleppo', 'date' => '2026-04-01'])]);

    expect(Voucher::query()->where('transfer_kind', 'syp_to_project')->count())->toBe(0);
});
