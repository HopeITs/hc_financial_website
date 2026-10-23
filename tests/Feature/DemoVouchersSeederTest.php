<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Models\VoucherLine;
use App\Services\Ledger\AccountStatement;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\DemoVouchersSeeder;
use Database\Seeders\RemoveDemoVouchersSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    $this->accountant = createFinanceUser('acc', 'pass pass', RoleId::Accountant->name, RoleId::Accountant->value);
    $this->manager = createFinanceUser('fm', 'pass pass', RoleId::FinancialManager->name, RoleId::FinancialManager->value);
    $this->head = createFinanceUser('ho', 'pass pass', RoleId::HeadOffice->name, RoleId::HeadOffice->value);
});

function seedDemo(): void
{
    (new DemoVouchersSeeder)->run(app(PostVoucher::class));
}

it('adds eleven demo vouchers: eight pending, two approved and one rejected', function () {
    seedDemo();

    expect(Voucher::withTrashed()->count())->toBe(11)
        ->and(Voucher::query()->count())->toBe(10)
        ->and(Voucher::query()->where('status', VoucherStatus::PendingApproval->value)->count())->toBe(8)
        ->and(Voucher::query()->where('status', VoucherStatus::Approved->value)->count())->toBe(2)
        ->and(Voucher::onlyTrashed()->count())->toBe(1)
        ->and(Voucher::withTrashed()->where('description', 'not like', '[DEMO] %')->count())->toBe(0);
});

it('posts every voucher through the real rules, so each one balances in USD', function () {
    seedDemo();

    foreach (Voucher::withTrashed()->get() as $voucher) {
        $lines = VoucherLine::query()->where('voucher_id', $voucher->id);

        expect(bcsub((string) $lines->sum('debit_usd'), (string) $lines->sum('credit_usd'), 2))->toBe('0.00', $voucher->id);
    }
});

it('is prepared by real users: the accountant, plus one voucher each by the manager and head office', function () {
    seedDemo();

    $by = Voucher::withTrashed()->pluck('prepared_by')->countBy()->all();

    expect($by[$this->accountant])->toBe(9)
        ->and($by[$this->manager])->toBe(1)
        ->and($by[$this->head])->toBe(1);
});

it('includes a voucher above the manager limit, a Syrian pound one, a payment and a rejected one with its reason', function () {
    seedDemo();

    $biggest = VoucherLine::query()->selectRaw('voucher_id, sum(debit_usd) as total')->groupBy('voucher_id')->orderByDesc('total')->first();
    $reject = VoucherAudit::query()->where('action', 'reject')->first();

    expect((float) $biggest->total)->toBe(9000.0)
        ->and(VoucherLine::query()->where('currency', 'SYP')->where('debit_orig', '7500000.00')->exists())->toBeTrue()
        ->and(Voucher::query()->where('type', 'CPV')->where('status', 'pending_approval')->count())->toBe(1)
        ->and($reject->reason)->toBe('[DEMO] Entered twice')
        ->and(Voucher::onlyTrashed()->first()->id)->toBe($reject->voucher_id);
});

it('gives the account statement something to show from the approved vouchers', function () {
    seedDemo();

    $statement = app(AccountStatement::class)->for('1111');

    expect($statement['closing'])->toBe('3750.00')
        ->and(count($statement['rows']))->toBe(2);
});

it('does nothing the second time and leaves other vouchers alone', function () {
    $real = app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => $this->accountant, 'description' => 'A real voucher']));

    seedDemo();
    seedDemo();

    expect(Voucher::withTrashed()->count())->toBe(12)
        ->and(Voucher::query()->find($real->id)->description)->toBe('A real voucher');
});

it('refuses to run in production, and without anyone to prepare the vouchers', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => seedDemo())->toThrow(RuntimeException::class, 'test database')
        ->and(Voucher::withTrashed()->count())->toBe(0);

    app()->detectEnvironment(fn () => 'testing');
    DB::table('user')->delete();

    expect(fn () => seedDemo())->toThrow(RuntimeException::class, 'no active');
});

it('removes only the demo vouchers, with their lines and history, and refuses in production', function () {
    $real = app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => $this->accountant, 'description' => 'A real voucher'], [
        new LineDraft('1111', 'USD', debit: 25, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4101', 'USD', credit: 25, projectCode: 'F2F'),
    ]));
    seedDemo();

    app()->detectEnvironment(fn () => 'production');
    expect(fn () => (new RemoveDemoVouchersSeeder)->run())->toThrow(RuntimeException::class)
        ->and(Voucher::withTrashed()->count())->toBe(12);

    app()->detectEnvironment(fn () => 'testing');
    (new RemoveDemoVouchersSeeder)->run();

    expect(Voucher::withTrashed()->pluck('id')->all())->toBe([$real->id])
        ->and(VoucherLine::query()->count())->toBe(2)
        ->and(VoucherAudit::query()->count())->toBe(1);
});
