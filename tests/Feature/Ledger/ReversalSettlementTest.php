<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Livewire\AdvanceSettlementForm;
use App\Livewire\Operations;
use App\Livewire\Vouchers;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Ledger\AdvanceSettlement;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\ReverseVoucher;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_11_04_090000_add_reversal_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    GlProject::query()->create(['code' => 'F2F', 'name' => 'F2F', 'restriction' => 'restricted']);
    GlBudgetLine::query()->create(['key' => 'F2F|Travel', 'project_code' => 'F2F', 'name' => 'Travel', 'account_code' => GlAccount::query()->where('class', 'expenses')->where('level', 'posting')->value('code'), 'budget' => 5000, 'currency' => 'USD']);
});

function approvedReceipt(int $amount = 100): Voucher
{
    return ledgerApprovedVoucher([], [
        new LineDraft('1111', 'USD', debit: $amount, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: $amount, projectCode: 'F2F'),
    ]);
}

it('reverses an approved voucher with the same lines on the opposite sides and leaves the original alone', function () {
    $original = approvedReceipt(100);

    $reversal = app(ReverseVoucher::class)->reverse($original, 7, ' Wrong donor ');

    expect($reversal->status)->toBe(VoucherStatus::PendingApproval)
        ->and($reversal->reverses_id)->toBe($original->id)
        ->and($reversal->reversal_reason)->toBe('Wrong donor')
        ->and($reversal->type)->toBe($original->type);

    $lines = $reversal->lines->keyBy('account_code');
    expect($lines['1111']->credit_usd)->toBe('100.00')
        ->and($lines['1111']->debit_usd)->toBe('0.00')
        ->and($lines['4101']->debit_usd)->toBe('100.00');

    $original->refresh();
    expect($original->status)->toBe(VoucherStatus::Approved)
        ->and($original->reverses_id)->toBeNull();
});

it('refuses to reverse a voucher twice, a reversal, a pending voucher, and a reversal with no reason', function () {
    $original = approvedReceipt();
    $service = app(ReverseVoucher::class);

    expect(fn () => $service->reverse($original, 7, ''))->toThrow(LedgerRuleException::class);

    $reversal = $service->reverse($original, 7, 'Mistake');

    expect(fn () => $service->reverse($original, 7, 'Again'))->toThrow(LedgerRuleException::class)
        ->and($service->problem($reversal))->not->toBeNull()
        ->and($service->problem($original->fresh()))->not->toBeNull();
});

function openAdvance(int $amount = 500): void
{
    ledgerApprovedVoucher(['type' => VoucherType::Payment], [
        new LineDraft('1201', 'USD', debit: $amount, projectCode: 'F2F', fundSource: FundSource::DonorGrant, contact: 'Rana'),
        new LineDraft('1111', 'USD', credit: $amount, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
    ]);
}

it('settles an advance: the bills on budget lines, and the cash returned as a receipt', function () {
    openAdvance(500);
    $settlement = app(AdvanceSettlement::class);

    expect($settlement->openAdvances())->toBe([['person' => 'Rana', 'open' => '500.00']]);

    $result = $settlement->build('Rana', [['budget_line' => 'F2F|Travel', 'amount' => '300', 'note' => '']], '1111', '2026-03-12', '', 9);

    expect($result['problems'])->toBe([])
        ->and($result['difference'])->toBe('200.00')
        ->and(array_map(fn ($draft) => $draft->type->value, $result['drafts']))->toBe(['JV', 'CRV']);

    $numbers = $settlement->post($result['drafts']);

    expect($numbers)->toHaveCount(2)
        ->and(Voucher::query()->whereKey($numbers)->where('status', VoucherStatus::PendingApproval->value)->count())->toBe(2);
});

it('pays the person out when the bills are more than the advance', function () {
    openAdvance(500);
    $result = app(AdvanceSettlement::class)->build('Rana', [['budget_line' => 'F2F|Travel', 'amount' => '650', 'note' => '']], '1111', '2026-03-12', '', 9);

    expect($result['problems'])->toBe([])
        ->and($result['difference'])->toBe('-150.00')
        ->and(array_map(fn ($draft) => $draft->type->value, $result['drafts']))->toBe(['JV', 'CPV']);
});

it('refuses a settlement with no bills, a bad budget line, or a person with no open advance', function () {
    openAdvance(500);
    $settlement = app(AdvanceSettlement::class);

    expect($settlement->build('Rana', [], '1111', '2026-03-12', '', 9)['problems'])->not->toBe([])
        ->and($settlement->build('Rana', [['budget_line' => 'nope', 'amount' => '10', 'note' => '']], '1111', '2026-03-12', '', 9)['problems'])->not->toBe([])
        ->and($settlement->build('Omar', [['budget_line' => 'F2F|Travel', 'amount' => '10', 'note' => '']], '1111', '2026-03-12', '', 9)['problems'])->not->toBe([]);
});

it('shows the settlement form in the Operations screen and the Reverse button on an approved voucher', function () {
    $user = User::query()->findOrFail(createFinanceUser('Fay', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));
    $voucher = approvedReceipt();
    openAdvance(500);

    Livewire\Livewire::actingAs($user)->test(Operations::class)
        ->call('pick', 'CPV')->set('mode', 'settlement')
        ->assertSeeHtml('data-test="settlement-panel"')
        ->assertSeeLivewire(AdvanceSettlementForm::class);

    Livewire\Livewire::actingAs($user)->test(AdvanceSettlementForm::class)
        ->set('person', 'Rana')->set('cash', '1111')->set('bills.0.budget_line', 'F2F|Travel')->set('bills.0.amount', '300')
        ->assertSeeHtml('data-test="settle-summary"')
        ->call('save')->assertSet('failures', []);

    Livewire\Livewire::actingAs($user)->test(Vouchers::class, ['number' => $voucher->id])
        ->assertSeeHtml('data-test="reverse"')
        ->call('openReverse')->set('reverseReason', 'Wrong donor')->call('reverse');

    expect(Voucher::query()->where('reverses_id', $voucher->id)->count())->toBe(1);
});
