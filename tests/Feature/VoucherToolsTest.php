<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Livewire\VoucherBuilder;
use App\Livewire\VoucherImportScreen;
use App\Livewire\Vouchers;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAudit;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherExport;
use App\Services\Ledger\VoucherImport;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    seedChartWithTestProjects();
});

function toolsUser(RoleId $role, string $name = 'Ann'): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

/** A pending receipt of the given amount into the Aleppo USD safe, project F2F. */
function toolsVoucher(User $by, int $amount = 100): Voucher
{
    return app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => (int) $by->getAuthIdentifier()], [
        new LineDraft('1111', 'USD', debit: $amount, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('4001', 'USD', credit: $amount, projectCode: 'F2F'),
    ]));
}

/**
 * The rows of a file with one balanced receipt per reference.
 *
 * @return list<array<string, string>>
 */
function toolsRows(array $refs = ['A'], string $amount = '100'): array
{
    $rows = [];
    foreach ($refs as $ref) {
        $head = ['voucher' => $ref, 'type' => 'CRV', 'date' => '2026-03-10', 'description' => 'Grant '.$ref, 'request' => '', 'project' => 'F2F', 'budget_line' => '', 'currency' => 'USD', 'rate' => '', 'contact' => '', 'center' => ''];
        $rows[] = $head + ['account' => '1111', 'debit' => $amount, 'credit' => '', 'fund_source' => 'donor_grant', 'row' => (string) (count($rows) + 2)];
        $rows[] = $head + ['account' => '4001', 'debit' => '', 'credit' => $amount, 'fund_source' => '', 'row' => (string) (count($rows) + 2)];
    }

    return $rows;
}

it('edits a pending voucher in the builder: same number, new lines, and an edit in its history', function () {
    $ann = toolsUser(RoleId::Accountant);
    $voucher = toolsVoucher($ann);

    Livewire::actingAs($ann)->test(VoucherBuilder::class, ['voucher' => $voucher->id])
        ->assertSet('editing', $voucher->id)
        ->assertSet('lines.0.account', '1111')->assertSet('lines.0.amount', '100.00')
        ->set('description', 'ACN grant, corrected')
        ->set('lines.0.amount', '250')->set('lines.1.amount', '250')
        ->call('save')->assertHasNoErrors()->assertRedirect();

    $voucher->refresh();
    expect(Voucher::query()->count())->toBe(1)
        ->and($voucher->status)->toBe(VoucherStatus::PendingApproval)
        ->and($voucher->description)->toBe('ACN grant, corrected')
        ->and((float) $voucher->lines()->sum('debit_usd'))->toBe(250.0)
        ->and(VoucherAudit::query()->where('voucher_id', $voucher->id)->where('action', 'edit')->exists())->toBeTrue();
});

it('will not edit an approved voucher: that one is reversed', function () {
    $ann = toolsUser(RoleId::Accountant);
    $voucher = toolsVoucher($ann);
    $voucher->update(['status' => VoucherStatus::Approved, 'approved_by' => 9, 'approved_at' => now()]);

    $this->actingAs($ann)->get(route('entries.vouchers.edit', $voucher->id))->assertForbidden();
});

it('deletes a pending voucher only with a reason, and keeps the reason in its history', function () {
    $ann = toolsUser(RoleId::Accountant);
    $voucher = toolsVoucher($ann);

    $page = Livewire::actingAs($ann)->withQueryParams(['voucher' => $voucher->id])->test(Vouchers::class)
        ->assertSeeHtml('data-test="change-actions"')
        ->call('openDelete')->call('deleteVoucher')->assertHasErrors('deleteReason');

    expect(Voucher::query()->find($voucher->id))->not->toBeNull();

    $page->set('deleteReason', 'Entered twice')->call('deleteVoucher')->assertHasNoErrors();

    expect(Voucher::query()->find($voucher->id))->toBeNull()
        ->and(Voucher::withTrashed()->find($voucher->id))->not->toBeNull()
        ->and(VoucherAudit::query()->where('voucher_id', $voucher->id)->where('action', 'delete')->value('reason'))->toBe('Entered twice');
});

it('does not delete an approved voucher', function () {
    $ann = toolsUser(RoleId::Accountant);
    $voucher = toolsVoucher($ann);
    $voucher->update(['status' => VoucherStatus::Approved, 'approved_by' => 9, 'approved_at' => now()]);

    Livewire::actingAs($ann)->withQueryParams(['voucher' => $voucher->id])->test(Vouchers::class)
        ->assertDontSeeHtml('data-test="change-actions"')
        ->call('openDelete')->set('deleteReason', 'No')->call('deleteVoucher')
        ->assertSee(__('vouchers.change.only_pending'));

    expect(Voucher::query()->find($voucher->id))->not->toBeNull();
});

it('exports the listed vouchers to Excel, one row per line, in the columns the import reads', function () {
    $ann = toolsUser(RoleId::Accountant);
    $voucher = toolsVoucher($ann, 300);

    Livewire::actingAs($ann)->test(Vouchers::class)->call('export')->assertFileDownloaded('vouchers-'.now()->format('Y-m-d').'.xlsx');

    $rows = app(VoucherImport::class)->read(app(VoucherExport::class)->xlsx([$voucher->id], false));
    expect($rows)->toHaveCount(2)
        ->and($rows[0]['voucher'])->toBe($voucher->id)
        ->and($rows[0]['account'])->toBe('1111')
        ->and($rows[1]['credit'])->toBe('300');
});

it('gives a template whose example vouchers are right', function () {
    $ann = toolsUser(RoleId::Accountant);

    Livewire::actingAs($ann)->test(VoucherImportScreen::class)->call('template')->assertFileDownloaded('vouchers-template.xlsx');

    $rows = app(VoucherImport::class)->read(app(VoucherExport::class)->template(false));
    $review = app(VoucherImport::class)->review($rows, (int) $ann->getAuthIdentifier());

    expect($review)->toHaveCount(2);
});

it('shows every voucher of the file with what is wrong, and saves none while one is wrong', function () {
    $ann = toolsUser(RoleId::Accountant);
    $rows = toolsRows(['A', 'B']);
    $rows[3]['credit'] = '90';

    $page = Livewire::actingAs($ann)->test(VoucherImportScreen::class)->set('rows', $rows)
        ->assertSeeHtml('data-test="import-A"')->assertSeeHtml('data-test="import-B"');

    expect($page->viewData('broken'))->toBe(1);

    $page->call('save');
    expect(Voucher::query()->count())->toBe(0);
});

it('saves all the vouchers of a right file as pending approval', function () {
    $ann = toolsUser(RoleId::Accountant);

    Livewire::actingAs($ann)->test(VoucherImportScreen::class)->set('rows', toolsRows(['A', 'B', 'C']))
        ->call('save')->assertRedirect();

    expect(Voucher::query()->where('status', VoucherStatus::PendingApproval)->count())->toBe(3);
});

it('reads a type, a debit or a credit on every row', function () {
    $rows = toolsRows();
    $rows[0]['type'] = 'XYZ';
    $rows[1]['debit'] = '100';

    $problems = app(VoucherImport::class)->review($rows, 5)[0]['problems'];

    expect($problems)->toContain(__('vouchers.excel.bad_type', ['row' => '2', 'types' => 'CRV, CPV, JV, TRF, EXG']))
        ->toContain(__('vouchers.excel.debit_or_credit', ['row' => '3']));
});
