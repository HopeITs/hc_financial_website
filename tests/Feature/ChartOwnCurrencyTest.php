<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Livewire\Chart;
use App\Models\User;
use App\Services\Chart\AccountBalances;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    seedChartWithTestProjects();
});

/** Pounds bought into the Aleppo SYP safe with dollars of the Aleppo USD safe, at the given rate. */
function poundsIn(int $pounds, int $rate, int $dollars, bool $approved = true): void
{
    $lines = [
        new LineDraft('1112', 'SYP', debit: $pounds, rate: $rate, debitUsd: $dollars, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('1111', 'USD', credit: $dollars, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
    ];

    if ($approved) {
        ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-02-01')], $lines);

        return;
    }

    app(PostVoucher::class)->post(ledgerReceipt(['date' => CarbonImmutable::parse('2026-02-01')], $lines));
}

it('gives a pound account its pounds, their dollars and the average rate, from approved vouchers only', function () {
    poundsIn(7500000, 15000, 500);
    poundsIn(3000000, 12000, 250);
    poundsIn(1000000, 10000, 100, approved: false);

    $balances = app(AccountBalances::class)->inOwnCurrency();

    expect($balances['1112']['currency'])->toBe('SYP')
        ->and($balances['1112']['amount'])->toBe('10500000.00')
        ->and($balances['1112']['usd'])->toBe('750.00')
        ->and((float) $balances['1112']['rate'])->toBe(14000.0);
});

it('shows it on a header whose accounts are all in pounds, and not on one that mixes dollars', function () {
    poundsIn(7500000, 15000, 500);

    $balances = app(AccountBalances::class)->inOwnCurrency();

    expect($balances)->toHaveKey('1102')
        ->and($balances['1102']['amount'])->toBe('7500000.00')
        ->and($balances)->not->toHaveKey('1100')
        ->and($balances)->not->toHaveKey('1111');
});

it('shows the pounds and the average rate on the chart screen', function () {
    poundsIn(7500000, 15000, 500);
    $user = User::query()->findOrFail(createFinanceUser('Ann', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));

    Livewire::actingAs($user)->withQueryParams(['account' => '1112'])->test(Chart::class)
        ->assertSeeHtml('data-test="own-currency"')
        ->assertSee('7,500,000.00')
        ->assertSee('15,000.00')
        ->assertSee(__('chart.average_rate'));

    Livewire::actingAs($user)->withQueryParams(['account' => '1102'])->test(Chart::class)
        ->assertSeeHtml('data-test="own-1112"')
        ->assertSeeHtml('data-test="rate-1112"');
});
