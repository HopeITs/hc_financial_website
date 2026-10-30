<?php

use App\Enums\RoleId;
use App\Livewire\MultiAttachments;
use App\Livewire\MultiEntry;
use App\Livewire\SingleEntry;
use App\Models\GlBudgetLine;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAttachment;
use App\Models\VoucherAudit;
use App\Services\Ledger\OperationSheet;
use App\Services\Ledger\OperationTemplate;
use App\Services\Ledger\PostVoucher;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_add_contract_dates_to_gl_projects.php'))->up();
    (new ChartOfAccountsSeeder)->run();
    Storage::fake('local');
});

function entryUser(RoleId $role = RoleId::Accountant, string $name = 'Ann'): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

/** A tab-separated row in the sheet's column order. */
function sheetRow(array $with = []): string
{
    $row = $with + ['date' => '2026-03-10', 'kind' => 'income', 'project' => 'F2F', 'budget_line' => '', 'cash' => '1111', 'account' => '4101', 'to_cash' => '', 'amount' => '250', 'rate' => '', 'contact' => '', 'fund_source' => '', 'document' => '', 'description' => 'Grant'];

    return implode("\t", array_map(fn (string $column) => $row[$column], OperationSheet::COLUMNS));
}

it('saves a single entry as a pending voucher with its file, and goes to the voucher', function () {
    Livewire::actingAs(entryUser())->test(SingleEntry::class)
        ->set('kind', 'income')->set('date', '2026-03-10')->set('project', 'F2F')->set('cash', '1111')->set('account', '4101')->set('amount', '250')
        ->set('description', 'Grant')
        ->set('attachments', [UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')])
        ->assertSee('1111')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('failures', []);

    $voucher = Voucher::query()->firstOrFail();

    expect($voucher->type->value)->toBe('CRV')
        ->and($voucher->status->value)->toBe('pending_approval')
        ->and(VoucherAttachment::query()->where('voucher_id', $voucher->id)->count())->toBe(1);
});

it('shows what is wrong instead of saving a single entry', function () {
    Livewire::actingAs(entryUser())->test(SingleEntry::class)
        ->set('kind', 'income')->set('date', '2026-03-10')->set('cash', '1111')->set('account', '4101')->set('amount', '250')
        ->assertSeeHtml('data-test="entry-messages"')
        ->call('save');

    expect(Voucher::query()->count())->toBe(0);
});

it('fills the project and account from a budget line on a single expense', function () {
    $line = GlBudgetLine::query()->orderBy('key')->firstOrFail();

    $page = Livewire::actingAs(entryUser())->test(SingleEntry::class)
        ->set('kind', 'expense')->set('budgetLine', $line->key);

    expect($page->get('project'))->toBe($line->project_code)->and($page->get('account'))->toBe($line->account_code);
});

it('reads rows pasted from Excel, with or without a header, old letters and dates as typed', function () {
    $sheet = app(OperationSheet::class);

    $plain = $sheet->parse(sheetRow(['kind' => 'I', 'date' => '10/03/2026', 'amount' => '1,250']));
    $headed = $sheet->parse("amount\tdate\tkind\tcash\tproject\taccount\n40\t2026/03/11\tE\t1111\tF2F\t4101");

    expect($plain)->toHaveCount(1)
        ->and($plain[0]['operation']['kind'])->toBe('income')
        ->and($plain[0]['operation']['date'])->toBe('2026-03-10')
        ->and($plain[0]['operation']['amount'])->toBe('1250')
        ->and($headed[0]['operation']['kind'])->toBe('expense')
        ->and($headed[0]['operation']['amount'])->toBe('40')
        ->and($headed[0]['operation']['date'])->toBe('2026-03-11')
        ->and($headed[0]['line'])->toBe(2);
});

it('saves every pasted row as a pending voucher', function () {
    $sheet = sheetRow()."\n".sheetRow(['kind' => 'exchange', 'account' => '', 'to_cash' => '1112', 'amount' => '100', 'rate' => '15000'])."\n\n".sheetRow(['amount' => '75']);

    Livewire::actingAs(entryUser())->test(MultiEntry::class)
        ->set('sheet', $sheet)
        ->assertSeeHtml('data-test="multi-summary"')
        ->call('save')
        ->assertSet('failures', []);

    expect(Voucher::query()->count())->toBe(3)
        ->and(Voucher::query()->pluck('type')->map->value->sort()->values()->all())->toBe(['CRV', 'CRV', 'EXG']);
});

it('saves nothing when one pasted row is wrong', function () {
    $sheet = sheetRow()."\n".sheetRow(['project' => ''])."\n".sheetRow(['kind' => 'gift']);

    $page = Livewire::actingAs(entryUser())->test(MultiEntry::class)
        ->set('sheet', $sheet)
        ->assertSeeHtml('data-test="multi-row-2"')
        ->call('save');

    expect(Voucher::query()->count())->toBe(0)
        ->and($page->get('failures'))->toBe([__('ledger.multi.not_saved')]);
});

it('saves nothing when the month of a row is closed', function () {
    $operations = app(OperationSheet::class);
    $rows = $operations->parse(sheetRow()."\n".sheetRow(['amount' => '80']));

    // The check refuses the closed month, so no voucher is posted for any row.
    DB::table('gl_month_closes')->insert(['month' => '2026-03', 'closed_at' => now(), 'closed_by' => 9]);

    expect($operations->postAll($rows, 7))->toBe([])->and(Voucher::query()->count())->toBe(0);
});

it('refuses an empty paste and too many rows', function () {
    Livewire::actingAs(entryUser())->test(MultiEntry::class)
        ->call('save')
        ->assertSet('failures', [__('ledger.multi.empty')])
        ->set('sheet', implode("\n", array_fill(0, OperationSheet::MAX_ROWS + 1, sheetRow())))
        ->call('save')
        ->assertSet('failures', [__('ledger.multi.too_many', ['max' => OperationSheet::MAX_ROWS])]);
});

it('attaches many files to their vouchers by the number their names start with', function () {
    $user = entryUser();
    $first = app(PostVoucher::class)->post(ledgerReceipt());
    $second = app(PostVoucher::class)->post(ledgerReceipt(['date' => CarbonImmutable::parse('2026-03-12')]));

    Livewire::actingAs($user)->test(MultiAttachments::class)
        ->set('files', [
            UploadedFile::fake()->create($first->id.'_invoice.pdf', 50, 'application/pdf'),
            UploadedFile::fake()->create(strtolower($second->id).'-receipt.png', 50, 'image/png'),
        ])
        ->call('save')
        ->assertSet('failures', []);

    expect(VoucherAttachment::query()->where('voucher_id', $first->id)->count())->toBe(1)
        ->and(VoucherAttachment::query()->where('voucher_id', $second->id)->count())->toBe(1)
        ->and(VoucherAudit::query()->where('action', 'attach')->count())->toBe(2);
});

it('attaches nothing when one file has no voucher', function () {
    $voucher = app(PostVoucher::class)->post(ledgerReceipt());

    Livewire::actingAs(entryUser())->test(MultiAttachments::class)
        ->set('files', [
            UploadedFile::fake()->create($voucher->id.'.pdf', 50, 'application/pdf'),
            UploadedFile::fake()->create('CPV-2026-9999.pdf', 50, 'application/pdf'),
            UploadedFile::fake()->create('scan.pdf', 50, 'application/pdf'),
        ])
        ->assertSeeHtml('data-test="multiatt-row-2"')
        ->call('save')
        ->assertSet('failures', [__('ledger.multiatt.not_saved')]);

    expect(VoucherAttachment::query()->count())->toBe(0);
});

it('keeps the entry screens to those who may create, and the old approval screen opens on the pending vouchers', function () {
    $viewer = entryUser(RoleId::Cashier, 'Cass');

    $this->actingAs($viewer)->get('/entries/multi')->assertForbidden();
    Livewire::actingAs($viewer)->test(MultiEntry::class)->set('sheet', sheetRow())->call('save')->assertForbidden();

    $this->actingAs(entryUser(RoleId::Accountant, 'Ann'))->get('/entries/operations')->assertOk();
    $this->actingAs(entryUser(RoleId::FinancialManager, 'Fay'))->get('/entries/approval')->assertOk();
});

it('prints a voucher for paper, with its lines and the places to sign', function () {
    $voucher = app(PostVoucher::class)->post(ledgerReceipt());

    $this->actingAs(entryUser())->get(route('entries.vouchers.print', ['voucher' => $voucher->id]))
        ->assertOk()
        ->assertSee($voucher->id)
        ->assertSee('data-test="print-lines"', false)
        ->assertSee('100.00')
        ->assertSee(__('vouchers.received_by'));

    $this->actingAs(entryUser(RoleId::Employee, 'Emp'))->get(route('entries.vouchers.print', ['voucher' => $voucher->id]))->assertForbidden();
    $this->actingAs(entryUser())->get(route('entries.vouchers.print', ['voucher' => 'CRV-2026-9999']))->assertNotFound();
});

it('writes a template with the entry sheet and the lists, and reads a filled one back', function () {
    $template = app(OperationTemplate::class);
    $path = $template->write(false);

    $reader = new XlsxReader;
    $reader->open($path);
    $names = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        $names[] = $sheet->getName();
    }
    $reader->close();
    unlink($path);

    expect($names)->toBe([OperationTemplate::SHEET, OperationTemplate::LISTS]);

    $filled = tempnam(sys_get_temp_dir(), 'fil');
    $writer = new XlsxWriter;
    $writer->openToFile($filled);
    $writer->getCurrentSheet()->setName(OperationTemplate::SHEET);
    $writer->addRow(Row::fromValues(['date | التاريخ', 'kind', 'project', 'cash', 'account', 'amount']));
    $writer->addRow(Row::fromValues([]));
    $writer->addRow(Row::fromValues(['2026-03-10', 'income', 'F2F', '1111', '4101', 250.5]));
    $writer->close();

    $text = $template->read($filled);
    unlink($filled);

    $rows = app(OperationSheet::class)->parse($text);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['line'])->toBe(3)
        ->and($rows[0]['operation']['amount'])->toBe('250.5')
        ->and($rows[0]['operation']['kind'])->toBe('income');
});

it('shows the rows of an uploaded workbook in the preview and downloads the template', function () {
    $path = tempnam(sys_get_temp_dir(), 'up');
    $writer = new XlsxWriter;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['date', 'kind', 'project', 'cash', 'account', 'amount']));
    $writer->addRow(Row::fromValues(['2026-03-10', 'income', 'F2F', '1111', '4101', 250]));
    $writer->close();

    Livewire::actingAs(entryUser())->test(MultiEntry::class)
        ->call('template')->assertFileDownloaded('operations-template.xlsx')
        ->set('upload', UploadedFile::fake()->createWithContent('rows.xlsx', (string) file_get_contents($path)))
        ->assertSet('failures', [])
        ->assertSeeHtml('data-test="multi-row-2"')
        ->assertSeeHtml('data-test="uploaded-name"');

    unlink($path);
});

it('refuses a file that is not a workbook', function () {
    Livewire::actingAs(entryUser())->test(MultiEntry::class)
        ->set('upload', UploadedFile::fake()->createWithContent('rows.xlsx', 'not a workbook'))
        ->assertSet('sheet', '');
});
