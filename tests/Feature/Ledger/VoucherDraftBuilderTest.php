<?php

use App\Enums\FundSource;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\GlProject;
use App\Models\Voucher;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\ProjectAverageRate;
use App\Services\Ledger\VoucherDraftBuilder;
use App\Services\Ledger\VoucherWarnings;
use Carbon\CarbonImmutable;

beforeEach(function () {
    createLedgerSchema();
    (require database_path('migrations/2026_10_25_090000_add_contract_dates_to_gl_projects.php'))->up();
});

function row(array $fields): array
{
    return $fields + ['account' => '', 'currency' => 'USD', 'side' => 'debit', 'amount' => '', 'rate' => '', 'project' => '', 'budget_line' => '', 'fund_source' => '', 'contact' => '', 'center' => '', 'lira_type' => ''];
}

function building(string $type, array $rows, string $date = '2026-03-10', string $request = '')
{
    return app(VoucherDraftBuilder::class)->build($type, $date, 'Test voucher', $request, $rows, 5);
}

/** An approved holding of pounds for a project: 7,500,000 SYP bought for 500 USD, so the average rate is 15,000. */
function poundsHeld(string $project = 'F2F'): void
{
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-02-01')], [
        new LineDraft('1112', 'SYP', debit: 7500000, rate: 15000, debitUsd: 500, projectCode: $project, fundSource: FundSource::DonorGrant),
        new LineDraft('1111', 'USD', credit: 500, projectCode: $project, fundSource: FundSource::DonorGrant),
    ]);
}

it('lets a budget line fill its project and account', function () {
    $built = building('CPV', [
        row(['budget_line' => 'F2F|Maintenance', 'amount' => '100', 'center' => 'Aleppo']),
        row(['account' => '1111', 'side' => 'credit', 'amount' => '100', 'project' => 'F2F', 'fund_source' => 'donor_grant']),
    ]);

    $line = $built->draft->lines[0];

    expect($line->accountCode)->toBe('5405')
        ->and($line->projectCode)->toBe('F2F')
        ->and($line->budgetLineKey)->toBe('F2F|Maintenance')
        ->and($built->rows[0]['account'])->toBe('5405');
});

it('lets a cash account fix the currency', function () {
    $built = building('CRV', [
        row(['account' => '1112', 'currency' => 'USD', 'amount' => '1500000', 'rate' => '15000', 'project' => 'F2F']),
        row(['account' => '1111', 'currency' => 'SYP', 'side' => 'credit', 'amount' => '100']),
    ]);

    expect($built->draft->lines[0]->currency)->toBe('SYP')
        ->and($built->draft->lines[1]->currency)->toBe('USD')
        ->and($built->draft->lines[1]->debitUsd)->toBe('0.00')
        ->and($built->draft->lines[1]->creditUsd)->toBe('100.00');
});

it('works out the dollars of a pound line from the rate, and asks for a rate when there is none', function () {
    $withRate = building('CRV', [row(['account' => '1112', 'amount' => '7,500,000', 'rate' => '15000'])]);
    $noRate = building('CRV', [row(['account' => '1112', 'amount' => '7500000'])]);

    expect($withRate->rows[0]['usd'])->toBe('500.00')
        ->and($withRate->draft->lines[0]->debitOrig)->toBe('7500000.00')
        ->and($withRate->problems)->toBe([])
        ->and($noRate->problems)->toHaveCount(1)
        ->and($noRate->rows[0]['usd'])->toBe('0.00');
});

it('gives a pound payment with a project the average rate of the pounds the project holds', function () {
    poundsHeld();

    expect(app(ProjectAverageRate::class)->rate('F2F'))->toBe('15000.000000')
        ->and(app(ProjectAverageRate::class)->rate('SZ'))->toBeNull();

    $payment = building('CPV', [row(['account' => '1112', 'side' => 'credit', 'amount' => '1500000', 'project' => 'F2F'])]);
    $receipt = building('CRV', [row(['account' => '1112', 'amount' => '1500000', 'project' => 'F2F'])]);
    $other = building('CPV', [row(['account' => '1112', 'side' => 'credit', 'amount' => '1500000', 'project' => 'SZ'])]);

    expect($payment->rows[0]['rate'])->toBe('15000.000000')
        ->and($payment->rows[0]['usd'])->toBe('100.00')
        ->and($payment->problems)->toBe([])
        ->and($receipt->problems)->toHaveCount(1)
        ->and($other->problems)->toHaveCount(1);
});

it('ignores pending vouchers when it averages the rate', function () {
    app(PostVoucher::class)->post(ledgerReceipt(['date' => CarbonImmutable::parse('2026-02-01')], [
        new LineDraft('1112', 'SYP', debit: 7500000, rate: 15000, debitUsd: 500, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('1111', 'USD', credit: 500, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
    ]));

    expect(app(ProjectAverageRate::class)->rate('F2F'))->toBeNull();
});

it('gives a project line the project donor, restriction and its amount in the donor currency', function () {
    $euro = building('CRV', [row(['account' => '4101', 'side' => 'credit', 'amount' => '115', 'project' => 'F2F'])])->draft->lines[0];
    $dollar = building('CRV', [row(['account' => '4301', 'side' => 'credit', 'amount' => '80', 'project' => 'SZ'])])->draft->lines[0];
    $none = building('CRV', [row(['account' => '1111', 'amount' => '10'])])->draft->lines[0];

    expect($euro->donor)->toBe('Caritas Poland')
        ->and($euro->donorCurrency)->toBe('EUR')
        ->and($euro->creditDonor)->toBe('100.00')
        ->and($euro->debitDonor)->toBe('0.00')
        ->and($euro->restriction)->toBe('With donor restrictions')
        ->and($dollar->donorCurrency)->toBe('USD')
        ->and($dollar->creditDonor)->toBe('80.00')
        ->and($none->donor)->toBeNull()
        ->and($none->donorCurrency)->toBeNull();
});

it('skips blank rows, keeps the sides and the tags, and only keeps the lira type on pound lines', function () {
    $built = building('CRV', [
        row([]),
        row(['account' => '1112', 'amount' => '1500000', 'rate' => '15000', 'project' => 'F2F', 'fund_source' => 'donor_grant', 'contact' => ' Ali ', 'lira_type' => 'new']),
        row(['account' => '1111', 'side' => 'credit', 'amount' => '100', 'project' => 'F2F', 'lira_type' => 'old']),
    ]);

    [$pounds, $dollars] = $built->draft->lines;

    expect($built->draft->lines)->toHaveCount(2)
        ->and(array_keys($built->rows))->toBe([1, 2])
        ->and($pounds->fundSource)->toBe(FundSource::DonorGrant)
        ->and($pounds->contact)->toBe('Ali')
        ->and($pounds->liraType)->toBe('new')
        ->and($dollars->liraType)->toBeNull()
        ->and($dollars->creditOrig)->toBe('100.00');
});

it('reports a bad type, date, request number and amount instead of failing', function () {
    $badDate = building('CRV', [row(['account' => '1111', 'amount' => '10'])], date: '2026-02-30');
    $badType = building('OPN', [row(['account' => '1111', 'amount' => '10'])]);
    $other = building('CRV', [row(['account' => '1111', 'amount' => 'ten'])], request: 'abc');

    expect($badDate->draft)->toBeNull()->and($badDate->problems)->toHaveCount(1)
        ->and($badType->draft)->toBeNull()
        ->and($other->problems)->toHaveCount(2);
});

it('prepares a receipt, a payment and a transfer that appear as pending', function () {
    $receipt = building('CRV', [
        row(['account' => '1111', 'amount' => '250', 'project' => 'F2F', 'fund_source' => 'donor_grant']),
        row(['account' => '4101', 'side' => 'credit', 'amount' => '250', 'project' => 'F2F']),
    ]);
    $payment = building('CPV', [
        row(['budget_line' => 'F2F|Maintenance', 'amount' => '120', 'center' => 'Aleppo']),
        row(['account' => '1111', 'side' => 'credit', 'amount' => '120', 'project' => 'F2F', 'fund_source' => 'donor_grant']),
    ]);
    $transfer = building('TRF', [
        row(['account' => '1121', 'amount' => '75', 'project' => 'SZ', 'fund_source' => 'donor_grant']),
        row(['account' => '1111', 'side' => 'credit', 'amount' => '75', 'project' => 'SZ', 'fund_source' => 'donor_grant']),
    ]);

    $posted = collect([$receipt, $payment, $transfer])->map(fn ($built) => app(PostVoucher::class)->post($built->draft));

    expect($posted->pluck('id')->all())->toBe(['CRV-2026-0001', 'CPV-2026-0001', 'TRF-2026-0001'])
        ->and($posted->every(fn (Voucher $v) => $v->status === VoucherStatus::PendingApproval))->toBeTrue()
        ->and($posted[1]->lines[0]->account_code)->toBe('5405')
        ->and($posted[1]->lines[0]->donor)->toBe('Caritas Poland');
});

it('shows exactly what posting would refuse: the live check and the server agree', function () {
    $built = building('CRV', [
        row(['account' => '1111', 'amount' => '100', 'project' => 'F2F']),
        row(['account' => '4101', 'side' => 'credit', 'amount' => '90', 'project' => 'F2F']),
        row(['account' => '1100', 'amount' => '5']),
    ]);

    $live = collect(app(PostVoucher::class)->check($built->draft))->pluck('rule')->unique()->sort()->values()->all();

    try {
        app(PostVoucher::class)->post($built->draft);
        $this->fail('Posting should have been refused.');
    } catch (LedgerRuleException $e) {
        expect(collect($e->rules())->sort()->values()->all())->toBe($live)->and($live)->toContain(1, 4, 5);
    }

    expect(Voucher::query()->count())->toBe(0);
});

it('warns, without blocking, when a project is spent on after its end date', function () {
    GlProject::query()->find('F2F')->update(['end_date' => '2026-01-31']);
    $spend = fn (string $type, string $date, bool $expense) => building($type, [
        $expense
            ? row(['budget_line' => 'F2F|Maintenance', 'amount' => '50', 'center' => 'Aleppo'])
            : row(['account' => '1121', 'amount' => '50', 'project' => 'F2F', 'fund_source' => 'donor_grant']),
        row(['account' => '1111', 'side' => 'credit', 'amount' => '50', 'project' => 'F2F', 'fund_source' => 'donor_grant']),
    ], $date)->draft;

    $warnings = app(VoucherWarnings::class);

    expect($warnings->for($spend('CPV', '2026-03-10', true)))->toHaveCount(1)
        ->and($warnings->for($spend('CPV', '2026-03-10', true))[0])->toContain('F2F')->toContain('2026-01-31')
        ->and($warnings->for($spend('CPV', '2026-01-15', true)))->toBe([])
        ->and($warnings->for($spend('TRF', '2026-03-10', false)))->toBe([])
        ->and(app(PostVoucher::class)->check($spend('TRF', '2026-03-10', true)))->toBe([]);
});

it('warns, without blocking, when a budget line is spent past what is left of it', function () {
    // F2F|Maintenance holds 10,000 EUR; at 1.15 USD per EUR, 11,500 USD spent so far uses all of it.
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-02-01'), 'type' => VoucherType::Transfer], [
        new LineDraft('5405', 'USD', debit: 11000, projectCode: 'F2F', budgetLineKey: 'F2F|Maintenance', center: 'Aleppo'),
        new LineDraft('1111', 'USD', credit: 11000, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
    ]);

    $draft = fn (string $amount) => building('TRF', [
        row(['budget_line' => 'F2F|Maintenance', 'amount' => $amount, 'center' => 'Aleppo']),
        row(['account' => '1111', 'side' => 'credit', 'amount' => $amount, 'project' => 'F2F', 'fund_source' => 'donor_grant']),
    ])->draft;

    $warnings = app(VoucherWarnings::class);
    $over = $warnings->for($draft('1000'));

    expect($warnings->for($draft('400')))->toBe([])
        ->and($over)->toHaveCount(1)
        ->and($over[0])->toContain('F2F|Maintenance')->toContain('434.78')->toContain('EUR')
        ->and(app(PostVoucher::class)->check($draft('1000')))->toBe([]);
});
