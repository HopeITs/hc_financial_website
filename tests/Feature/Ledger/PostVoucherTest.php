<?php

use App\Data\LineDraft;
use App\Data\VoucherDraft;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\MonthClose;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Models\VoucherLine;
use App\Models\VoucherSequence;
use App\Services\Ledger\AmendVoucher;
use App\Services\Ledger\CloseMonth;
use App\Services\Ledger\PostingRuleViolation;
use App\Services\Ledger\PostVoucher;
use Carbon\Carbon;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    createFinanceSchema();
    $this->seed(ChartOfAccountsSeeder::class);
});

function postDraft(?array $lines = null, string $date = '2026-03-01', VoucherType $type = VoucherType::Receipt): VoucherDraft
{
    return new VoucherDraft(
        type: $type, date: Carbon::parse($date), preparedBy: 5, description: 'Grant',
        lines: $lines ?? [usdLine('1111', 'debit', '100.00'), usdLine('4101', 'credit', '100.00')],
    );
}

it('numbers, saves as pending, writes the lines and an audit row in one go', function () {
    $voucher = app(PostVoucher::class)->handle(postDraft());

    expect($voucher->id)->toBe('CRV-2026-0001')
        ->and($voucher->status)->toBe(VoucherStatus::PendingApproval)
        ->and($voucher->prepared_by)->toBe(5)
        ->and($voucher->lines)->toHaveCount(2)
        ->and($voucher->lines[0]->debit_usd)->toBe('100.00')
        ->and($voucher->lines[1]->line_no)->toBe(2)
        ->and(VoucherAudit::query()->where('voucher_id', 'CRV-2026-0001')->where('action', 'created')->count())->toBe(1);
});

it('counts numbers per type and year without gaps', function () {
    $post = app(PostVoucher::class);

    expect($post->handle(postDraft())->id)->toBe('CRV-2026-0001')
        ->and($post->handle(postDraft())->id)->toBe('CRV-2026-0002')
        ->and($post->handle(postDraft(date: '2027-01-05'))->id)->toBe('CRV-2027-0001')
        ->and($post->handle(postDraft(type: VoucherType::Journal))->id)->toBe('JV-2026-0001');
});

it('a failed rule leaves no rows and no consumed number', function () {
    $post = app(PostVoucher::class);
    $post->handle(postDraft());

    $unbalanced = postDraft([usdLine('1111', 'debit', '100.00'), usdLine('4101', 'credit', '90.00')]);
    expect(fn () => $post->handle($unbalanced))->toThrow(PostingRuleViolation::class);

    expect(Voucher::query()->count())->toBe(1)
        ->and(VoucherLine::query()->count())->toBe(2)
        ->and(VoucherAudit::query()->count())->toBe(1)
        ->and(VoucherSequence::query()->where('type', 'CRV')->value('last_number'))->toBe(1)
        ->and($post->handle(postDraft())->id)->toBe('CRV-2026-0002');
});

it('a failure while saving lines rolls the number back too', function () {
    $post = app(PostVoucher::class);
    $badLine = new LineDraft(['account_code' => '1111', 'currency' => 'USD', 'project_code' => 'NOPE', 'fund_source' => 'donor_grant', 'debit_orig' => '100.00', 'debit_usd' => '100.00']);

    // The foreign key (SQLite enforces it when asked to) or the rules refuse; either way nothing is left behind.
    DB::statement('PRAGMA foreign_keys = ON');
    expect(fn () => $post->handle(postDraft([$badLine, usdLine('4101', 'credit', '100.00')])))->toThrow(QueryException::class);

    expect(Voucher::query()->count())->toBe(0)
        ->and(VoucherSequence::query()->count())->toBe(0);
});

it('amends an approved voucher keeping old and new value, user and reason', function () {
    $voucher = app(PostVoucher::class)->handle(postDraft());
    $voucher->update(['status' => VoucherStatus::Approved, 'approved_by' => 4]);

    $amend = app(AmendVoucher::class);
    expect(fn () => $amend->handle($voucher, ['description' => 'Fixed'], 4, null))->toThrow(PostingRuleViolation::class);

    $amend->handle($voucher, ['description' => 'Fixed', 'date' => '2026-03-02'], 4, 'Typo');

    $audit = VoucherAudit::query()->where('action', 'amended')->orderBy('id')->get();
    expect($audit)->toHaveCount(2)
        ->and($audit[0]->field)->toBe('description')
        ->and($audit[0]->old_value)->toBe('Grant')
        ->and($audit[0]->new_value)->toBe('Fixed')
        ->and($audit[0]->changed_by)->toBe(4)
        ->and($audit[0]->reason)->toBe('Typo')
        ->and($audit[1]->old_value)->toBe('2026-03-01')
        ->and($audit[1]->new_value)->toBe('2026-03-02')
        ->and($voucher->fresh()->description)->toBe('Fixed');
});

it('refuses to amend a voucher into or out of a closed month', function () {
    $voucher = app(PostVoucher::class)->handle(postDraft());
    $voucher->update(['status' => VoucherStatus::Approved]);
    MonthClose::query()->create(['month' => '2026-02', 'closed_at' => now(), 'closed_by' => 2]);

    expect(fn () => app(AmendVoucher::class)->handle($voucher, ['date' => '2026-02-10'], 4, 'Move'))->toThrow(PostingRuleViolation::class)
        ->and(VoucherAudit::query()->where('action', 'amended')->count())->toBe(0);

    MonthClose::query()->create(['month' => '2026-03', 'closed_at' => now(), 'closed_by' => 2]);
    expect(fn () => app(AmendVoucher::class)->handle($voucher, ['description' => 'x'], 4, 'Move'))->toThrow(PostingRuleViolation::class);
});

it('closes a month and then refuses new vouchers in it', function () {
    app(CloseMonth::class)->handle('2026-03', 2);

    expect(MonthClose::query()->whereKey('2026-03')->exists())->toBeTrue()
        ->and(fn () => app(PostVoucher::class)->handle(postDraft()))->toThrow(PostingRuleViolation::class);
});

it('has an english and an arabic message for every rule key', function () {
    $keys = array_keys(trans('ledger.rules', [], 'en'));

    expect($keys)->not->toBeEmpty()
        ->and(array_keys(trans('ledger.rules', [], 'ar')))->toEqualCanonicalizing($keys);

    foreach (glob(app_path('Services/Ledger/Rules/*.php')) as $file) {
        preg_match_all("/PostingRuleViolation::of\('([a-z_]+)'/", file_get_contents($file), $found);
        foreach ($found[1] as $key) {
            expect($keys)->toContain($key);
        }
    }
});
