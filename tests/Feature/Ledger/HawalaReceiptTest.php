<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Livewire\VoucherBuilder;
use App\Models\User;
use App\Models\VoucherLine;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\ProjectBalances;
use App\Services\Ledger\VoucherDraftBuilder;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();
});

/** The two rows of a donor receipt through the transfer company: dollars into 1161 and the project's income. */
function hawalaRows(string $received, array $hawala = [], string $project = 'F2F'): array
{
    return [
        ['account' => '1161', 'currency' => 'USD', 'side' => 'debit', 'amount' => $received, 'project' => $project, 'fund_source' => 'donor_grant', 'contact' => 'Hawala Co'] + $hawala,
        ['account' => '4101', 'currency' => 'USD', 'side' => 'credit', 'amount' => $received, 'project' => $project],
    ];
}

function buildHawala(array $rows, string $type = 'CRV')
{
    return app(VoucherDraftBuilder::class)->build($type, '2026-03-10', '', '', $rows, 7);
}

it('posts a euro receipt: the project is credited the net dollars and the commission goes to 5504 without a project', function () {
    // 1,000 EUR at 1.10 = 1,100 USD, less 15 USD commission = 1,085 USD received.
    $built = buildHawala(hawalaRows('1085', ['eur_amount' => '1000', 'eur_rate' => '1.1', 'commission' => '15']));

    expect($built->problems)->toBe([])
        ->and($built->draft->lines)->toHaveCount(4);

    $voucher = app(PostVoucher::class)->post($built->draft);
    $lines = $voucher->lines->keyBy(fn (VoucherLine $line) => $line->account_code.($line->debit_usd > 0 ? 'D' : 'C'));

    expect($lines['1161D']->debit_usd)->toBe('1085.00')
        ->and($lines['1161D']->project_code)->toBe('F2F')
        ->and($lines['1161D']->eur_amount)->toBe('1000.00')
        ->and($lines['1161D']->eur_rate)->toBe('1.100000')
        ->and($lines['1161D']->commission_usd)->toBe('15.00')
        ->and($lines['4101C']->credit_usd)->toBe('1085.00')
        ->and($lines['4101C']->project_code)->toBe('F2F')
        ->and($lines['5504D']->debit_usd)->toBe('15.00')
        ->and($lines['5504D']->project_code)->toBeNull()
        ->and($lines['1161C']->credit_usd)->toBe('15.00')
        ->and($lines['1161C']->project_code)->toBe('GEN-ALP-USD');
});

it('takes the project\'s default rate when none is typed', function () {
    // F2F has a default of 1.15: 1,000 EUR = 1,150 USD, less 15 = 1,135.
    $built = buildHawala(hawalaRows('1135', ['eur_amount' => '1000', 'commission' => '15']));

    expect($built->problems)->toBe([])
        ->and($built->draft->lines[0]->eurRate)->toBe('1.150000');
    app(PostVoucher::class)->post($built->draft);
});

it('needs a typed rate when the project has no default, and refuses euros that do not add up', function () {
    $noDefault = buildHawala(hawalaRows('1000', ['eur_amount' => '900'], project: 'SZ'));
    expect($noDefault->problems)->toBe([__('ledger.builder.hawala_needs_rate', ['line' => 1])]);

    $off = buildHawala(hawalaRows('1090', ['eur_amount' => '1000', 'eur_rate' => '1.1', 'commission' => '15']));
    $violations = app(PostVoucher::class)->check($off->draft);

    expect(collect($violations)->pluck('rule')->all())->toContain(15)
        ->and(fn () => app(PostVoucher::class)->post($off->draft))->toThrow(LedgerRuleException::class);
});

it('accepts a difference of one cent and refuses two', function () {
    // 333.33 EUR x 1.1 = 366.663; with 15 commission 351.663: 351.66 is within a cent, 351.64 is not.
    $within = buildHawala(hawalaRows('351.66', ['eur_amount' => '333.33', 'eur_rate' => '1.1', 'commission' => '15']));
    $outside = buildHawala(hawalaRows('351.64', ['eur_amount' => '333.33', 'eur_rate' => '1.1', 'commission' => '15']));

    expect(collect(app(PostVoucher::class)->check($within->draft))->pluck('rule')->all())->not->toContain(15)
        ->and(collect(app(PostVoucher::class)->check($outside->draft))->pluck('rule')->all())->toContain(15);
});

it('leaves an ordinary dollar receipt alone', function () {
    $built = buildHawala(hawalaRows('500'));

    expect($built->problems)->toBe([])
        ->and($built->draft->lines)->toHaveCount(2)
        ->and($built->draft->lines[0]->eurAmount)->toBeNull();
    $voucher = app(PostVoucher::class)->post($built->draft);

    expect($voucher->lines->first()->eur_amount)->toBeNull()
        ->and($voucher->lines->first()->commission_usd)->toBe('0.00');
});

it('keeps the euro fields to a receipt into the transfer company', function () {
    $payment = buildHawala(hawalaRows('1085', ['eur_amount' => '1000', 'eur_rate' => '1.1', 'commission' => '15']), 'CPV');
    $wrongAccount = buildHawala([
        ['account' => '1111', 'currency' => 'USD', 'side' => 'debit', 'amount' => '100', 'project' => 'F2F', 'fund_source' => 'donor_grant', 'eur_amount' => '90', 'eur_rate' => '1.1'],
        ['account' => '4101', 'currency' => 'USD', 'side' => 'credit', 'amount' => '100', 'project' => 'F2F'],
    ]);

    expect($payment->problems)->not->toBe([])
        ->and($wrongAccount->problems)->not->toBe([]);
});

it('refuses a commission that is not posted to 5504', function () {
    $draft = ledgerReceipt(['type' => VoucherType::Receipt], [
        new LineDraft('1161', 'USD', debit: 1085, projectCode: 'F2F', fundSource: FundSource::DonorGrant, contact: 'Hawala Co', eurAmount: 1000, eurRate: '1.1', commissionUsd: 15),
        new LineDraft('4101', 'USD', credit: 1085, projectCode: 'F2F'),
    ]);

    expect(collect(app(PostVoucher::class)->check($draft))->pluck('rule')->all())->toContain(15);
});

it('shows the donor-currency view from the stored euros: net dollars at the actual rate', function () {
    $built = buildHawala(hawalaRows('1085', ['eur_amount' => '1000', 'eur_rate' => '1.1', 'commission' => '15']));
    $voucher = app(PostVoucher::class)->post($built->draft);
    DB::table('gl_vouchers')->where('id', $voucher->id)->update(['status' => 'approved', 'approved_by' => 9]);

    $row = collect(app(ProjectBalances::class)->report()['rows'])->firstWhere('code', 'F2F');

    // 1,085 / 1.10 = 986.36 EUR, not 1,085 / 1.15 = 943.48 at the default rate.
    expect($row['donor_currency'])->toBe('EUR')
        ->and($row['donor_income'])->toBe('986.36')
        ->and($row['income'])->toBe('1085.00');
});

it('checks a saved voucher again with its euro fields', function () {
    $built = buildHawala(hawalaRows('1085', ['eur_amount' => '1000', 'eur_rate' => '1.1', 'commission' => '15']));
    $voucher = app(PostVoucher::class)->post($built->draft);

    expect(app(PostVoucher::class)->recheck($voucher->load('lines')))->toBe([]);
});

it('shows the euro fields in the voucher builder only for a receipt into the transfer company', function () {
    $user = User::query()->whereKey(createFinanceUser('ho', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value))->firstOrFail();

    Livewire\Livewire::actingAs($user)->test(VoucherBuilder::class)
        ->set('type', 'CRV')->set('lines.0.account', '1161')->set('lines.0.side', 'debit')
        ->assertSeeHtml('data-test="hawala-0"')
        ->set('lines.0.account', '1111')
        ->assertDontSeeHtml('data-test="hawala-0"')
        ->set('lines.0.account', '1161')->set('type', 'CPV')
        ->assertDontSeeHtml('data-test="hawala-0"');
});
