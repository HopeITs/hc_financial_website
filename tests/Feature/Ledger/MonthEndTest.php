<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Livewire\MonthEndScreen;
use App\Models\GlProject;
use App\Models\MonthClose;
use App\Models\User;
use App\Services\Ledger\LedgerRuleException;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\MonthEnd;
use App\Services\Ledger\MonthEndException;
use App\Services\Ledger\PostVoucher;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    GlProject::query()->create(['code' => 'F2F', 'name' => 'F2F', 'restriction' => 'restricted']);
    ledgerApprovedVoucher([], [
        new LineDraft('1111', 'USD', debit: 100, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: 100, projectCode: 'F2F'),
    ]);
});

function monthEnd(): MonthEnd
{
    return app(MonthEnd::class);
}

it('lists the ledger balance of each cash account and blocks the month until it is counted', function () {
    $rows = collect(monthEnd()->rows('2026-03'))->keyBy('code');

    expect($rows['1111']['ledger'])->toBe('100.00')
        ->and($rows['1111']['counted'])->toBeNull()
        ->and(monthEnd()->blockers('2026-03'))->not->toBe([])
        ->and(fn () => monthEnd()->close('2026-03', 9))->toThrow(MonthEndException::class);
});

it('closes the month once every difference is zero, and then refuses a voucher dated in it', function () {
    monthEnd()->saveCount('2026-03', '1111', '100', '', 9);

    expect(monthEnd()->blockers('2026-03'))->toBe([]);

    monthEnd()->close('2026-03', 9);

    expect(MonthClose::query()->whereKey('2026-03')->exists())->toBeTrue()
        ->and(monthEnd()->history('2026-03')->pluck('action')->all())->toBe(['closed'])
        ->and(fn () => app(PostVoucher::class)->post(ledgerReceipt()))->toThrow(LedgerRuleException::class)
        ->and(fn () => monthEnd()->saveCount('2026-03', '1111', '90', '', 9))->toThrow(MonthEndException::class);
});

it('needs a written explanation for a difference', function () {
    monthEnd()->saveCount('2026-03', '1111', '95.50', '', 9);

    $row = collect(monthEnd()->rows('2026-03'))->firstWhere('code', '1111');

    expect($row['difference'])->toBe('-4.50')
        ->and($row['settled'])->toBeFalse()
        ->and(monthEnd()->blockers('2026-03'))->not->toBe([]);

    monthEnd()->saveCount('2026-03', '1111', '95.50', 'Receipt not yet entered', 9);

    expect(monthEnd()->blockers('2026-03'))->toBe([]);
});

it('reopens a month only with a reason, and logs it', function () {
    monthEnd()->saveCount('2026-03', '1111', '100', '', 9);
    monthEnd()->close('2026-03', 9);

    expect(fn () => monthEnd()->reopen('2026-03', 2, ' '))->toThrow(MonthEndException::class);

    monthEnd()->reopen('2026-03', 2, 'Missing receipt');

    expect(MonthClose::query()->count())->toBe(0)
        ->and(monthEnd()->history('2026-03')->last()->reason)->toBe('Missing receipt')
        ->and(app(PostVoucher::class)->post(ledgerReceipt())->id)->not->toBeEmpty();
});

it('lets Head Office reopen but not the Financial Manager', function () {
    monthEnd()->saveCount('2026-03', '1111', '100', '', 9);
    $head = User::query()->findOrFail(createFinanceUser('hana', 'secret pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value));
    $fm = User::query()->findOrFail(createFinanceUser('fatima', 'secret pass', RoleId::FinancialManager->name, RoleId::FinancialManager->value));

    Livewire::actingAs($fm)->test(MonthEndScreen::class, ['month' => '2026-03'])
        ->call('close')->assertSet('failures', [])
        ->call('reopen')->assertForbidden();

    Livewire::actingAs($head)->test(MonthEndScreen::class, ['month' => '2026-03'])
        ->set('reopenReason', 'Fix')->call('reopen')->assertSet('failures', []);

    expect(MonthClose::query()->count())->toBe(0);
});
