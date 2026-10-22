<?php

use App\Enums\AccountClass;
use App\Enums\AccountLevel;
use App\Enums\FundSource;
use App\Enums\NormalSide;
use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\GlAccount;
use App\Models\GlProject;
use App\Models\ImportBatch;
use App\Models\ImportIssue;
use App\Models\LegacyMap;
use App\Models\Voucher;
use App\Models\VoucherAttachment;
use App\Models\VoucherAudit;
use App\Models\VoucherLine;
use App\Models\VoucherSequence;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

const VOUCHER_TABLES = [
    'gl_vouchers', 'gl_voucher_lines', 'gl_voucher_audit', 'gl_voucher_sequences', 'gl_voucher_attachments',
    'gl_month_closes', 'gl_allocation_rates', 'gl_legacy_map', 'gl_import_batches', 'gl_import_issues', 'gl_cash_till_merges',
];

beforeEach(function () {
    (require database_path('migrations/2026_10_24_090000_create_gl_accounts_projects_budget_lines_tables.php'))->up();
    $this->migration = require database_path('migrations/2026_10_24_100000_create_gl_voucher_tables.php');
    $this->migration->up();

    foreach (['1111' => AccountClass::Assets, '4101' => AccountClass::Income] as $code => $class) {
        GlAccount::query()->create([
            'code' => $code, 'level' => AccountLevel::Posting, 'class' => $class, 'name_en' => 'Account '.$code,
            'name_ar' => 'حساب '.$code, 'normal_side' => NormalSide::Debit,
        ]);
    }
    GlProject::query()->create(['code' => 'F2F', 'name' => 'Face to Face', 'restriction' => 'With donor restrictions']);
});

function glVoucher(string $id = 'CRV-2026-0001', array $extra = []): Voucher
{
    return Voucher::query()->create($extra + [
        'id' => $id, 'type' => VoucherType::Receipt, 'date' => '2026-03-01', 'description' => 'Grant',
        'status' => VoucherStatus::PendingApproval, 'prepared_by' => 5,
    ]);
}

function glLine(string $voucherId, int $lineNo, array $extra = []): VoucherLine
{
    return VoucherLine::query()->create($extra + [
        'voucher_id' => $voucherId, 'line_no' => $lineNo, 'account_code' => '1111', 'currency' => 'USD',
        'debit_orig' => '100.00', 'debit_usd' => '100.00',
    ]);
}

it('creates the eleven voucher tables and drops them again', function () {
    foreach (VOUCHER_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeTrue($table);
    }

    $this->migration->down();

    foreach (VOUCHER_TABLES as $table) {
        expect(Schema::hasTable($table))->toBeFalse($table);
    }
});

it('keeps a voucher with its ordered lines, audit trail and attachments', function () {
    glVoucher();
    glLine('CRV-2026-0001', 2, ['account_code' => '4101', 'debit_orig' => 0, 'debit_usd' => 0, 'credit_orig' => '100', 'credit_usd' => '100', 'project_code' => 'F2F', 'fund_source' => FundSource::DonorGrant]);
    glLine('CRV-2026-0001', 1);
    VoucherAudit::query()->create(['voucher_id' => 'CRV-2026-0001', 'action' => 'create', 'changed_by' => 5]);
    VoucherAttachment::query()->create(['voucher_id' => 'CRV-2026-0001', 'original_name' => 'a.pdf', 'stored_path' => 'attachments/a.pdf', 'size' => 10]);

    $voucher = Voucher::query()->find('CRV-2026-0001');

    expect($voucher->type)->toBe(VoucherType::Receipt)
        ->and($voucher->status)->toBe(VoucherStatus::PendingApproval)
        ->and($voucher->date->toDateString())->toBe('2026-03-01')
        ->and($voucher->lines->pluck('line_no')->all())->toBe([1, 2])
        ->and($voucher->lines[1]->fund_source)->toBe(FundSource::DonorGrant)
        ->and($voucher->lines[1]->credit_usd)->toBe('100.00')
        ->and($voucher->lines[1]->project->name)->toBe('Face to Face')
        ->and($voucher->lines[0]->account->code)->toBe('1111')
        ->and($voucher->audits)->toHaveCount(1)
        ->and($voucher->audits[0]->created_at)->not->toBeNull()
        ->and($voucher->attachments)->toHaveCount(1);
});

it('rejects a line that is both a debit and a credit, in any currency column', function (array $amounts) {
    glVoucher();

    expect(fn () => glLine('CRV-2026-0001', 1, $amounts))->toThrow(QueryException::class);
})->with([
    'original' => [['debit_orig' => '5', 'credit_orig' => '5']],
    'usd' => [['debit_usd' => '5', 'credit_usd' => '5']],
    'donor' => [['debit_donor' => '5', 'credit_donor' => '5']],
]);

it('rejects a negative amount', function (array $amounts) {
    glVoucher();

    expect(fn () => glLine('CRV-2026-0001', 1, $amounts))->toThrow(QueryException::class);
})->with([
    'debit' => [['debit_orig' => '-1']],
    'credit' => [['debit_orig' => 0, 'debit_usd' => 0, 'credit_orig' => '-1', 'credit_usd' => '-1']],
]);

it('rejects an update that makes a saved line both a debit and a credit', function () {
    glVoucher();
    $line = glLine('CRV-2026-0001', 1);

    expect(fn () => $line->update(['credit_orig' => '10']))->toThrow(QueryException::class);
});

it('rejects a line on an unknown account, project, budget line or voucher', function (array $extra) {
    glVoucher();

    expect(fn () => glLine($extra['voucher_id'] ?? 'CRV-2026-0001', 1, $extra))->toThrow(QueryException::class);
})->with([
    'account' => [['account_code' => '9999']],
    'project' => [['project_code' => 'NOPE']],
    'budget line' => [['budget_line_key' => 'F2F|Nothing']],
    'voucher' => [['voucher_id' => 'CRV-2026-9999']],
]);

it('allows one line number once per voucher only', function () {
    glVoucher();
    glVoucher('CPV-2026-0001', ['type' => VoucherType::Payment]);
    glLine('CRV-2026-0001', 1);
    glLine('CPV-2026-0001', 1);

    expect(fn () => glLine('CRV-2026-0001', 1))->toThrow(QueryException::class);
});

it('keeps a legacy action id on one voucher at most, while any number of vouchers have none', function () {
    glVoucher('CRV-2026-0001');
    glVoucher('CRV-2026-0002');
    glVoucher('CRV-2026-0003', ['legacy_action_id' => 77, 'source' => 'migration']);

    expect(fn () => glVoucher('CRV-2026-0004', ['legacy_action_id' => 77]))->toThrow(QueryException::class);
});

it('keeps a legacy attachment id on one attachment at most', function () {
    glVoucher();
    $attach = fn () => VoucherAttachment::query()->create([
        'voucher_id' => 'CRV-2026-0001', 'original_name' => 'a.pdf', 'stored_path' => 'a.pdf', 'legacy_attachment_id' => 9,
    ]);
    $attach();

    expect($attach)->toThrow(QueryException::class);
});

it('soft deletes vouchers and attachments', function () {
    $voucher = glVoucher();
    $attachment = VoucherAttachment::query()->create(['voucher_id' => $voucher->id, 'original_name' => 'a.pdf', 'stored_path' => 'a.pdf']);

    $attachment->delete();
    $voucher->delete();

    expect(Voucher::query()->find('CRV-2026-0001'))->toBeNull()
        ->and(Voucher::withTrashed()->find('CRV-2026-0001'))->not->toBeNull()
        ->and(VoucherAttachment::withTrashed()->count())->toBe(1)
        ->and(VoucherAttachment::query()->count())->toBe(0);
});

it('numbers each voucher type once per year', function () {
    VoucherSequence::query()->create(['type' => 'CRV', 'year' => 2026, 'last_number' => 1]);
    VoucherSequence::query()->create(['type' => 'CPV', 'year' => 2026, 'last_number' => 1]);
    VoucherSequence::query()->create(['type' => 'CRV', 'year' => 2027, 'last_number' => 1]);

    expect(fn () => VoucherSequence::query()->create(['type' => 'CRV', 'year' => 2026, 'last_number' => 2]))->toThrow(QueryException::class);
});

it('keeps one confirmed mapping per kind and legacy name', function () {
    LegacyMap::query()->create(['kind' => 'cash', 'legacy_key' => 'Aleppo safe|USD', 'target' => '1111']);
    LegacyMap::query()->create(['kind' => 'donor', 'legacy_key' => 'Aleppo safe|USD', 'target' => '4101']);

    expect(fn () => LegacyMap::query()->create(['kind' => 'cash', 'legacy_key' => 'Aleppo safe|USD', 'target' => '1112']))->toThrow(QueryException::class);
});

it('lists the issues of an import batch and deletes them with it', function () {
    $batch = ImportBatch::query()->create(['type' => 'operations']);
    ImportIssue::query()->create(['batch_id' => $batch->id, 'legacy_action_id' => 12, 'kind' => 'map', 'message' => 'Unmapped donor', 'payload' => ['donor' => 'X']]);

    $batch = $batch->fresh();

    expect($batch->status)->toBe('pending')
        ->and($batch->issues)->toHaveCount(1)
        ->and($batch->issues[0]->payload)->toBe(['donor' => 'X']);

    $batch->delete();

    expect(ImportIssue::query()->count())->toBe(0);
});

it('labels the voucher enums in English and Arabic', function () {
    expect(VoucherType::Allocation->label())->toBe('Allocation')
        ->and(VoucherStatus::PendingApproval->label())->toBe('Pending approval')
        ->and(FundSource::ChcOwnFunds->label())->toBe('CHC own funds');

    app()->setLocale('ar');

    expect(VoucherType::Receipt->label())->toBe('سند قبض')
        ->and(VoucherStatus::Approved->label())->toBe('معتمد')
        ->and(FundSource::Treasury->label())->toBe('الخزينة');
});
