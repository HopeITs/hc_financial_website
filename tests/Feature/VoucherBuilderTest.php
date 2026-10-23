<?php

use App\Enums\FundSource;
use App\Enums\RoleId;
use App\Enums\VoucherStatus;
use App\Livewire\VoucherBuilder;
use App\Livewire\Vouchers;
use App\Models\GlProject;
use App\Models\MonthClose;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherAttachment;
use App\Services\Ledger\LineDraft;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_add_contract_dates_to_gl_projects.php'))->up();
    (new ChartOfAccountsSeeder)->run();
});

function builderUser(RoleId $role, string $name = 'Ann'): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

function builderPage(?User $user = null)
{
    return Livewire::actingAs($user ?? builderUser(RoleId::Accountant))->test(VoucherBuilder::class);
}

/** Two lines of a balanced receipt: money into the Aleppo USD safe from the ACN grant, project F2F. */
function fillReceipt($page, string $amount = '250', string $second = '250')
{
    return $page->set('lines.0.account', '1111')->set('lines.0.amount', $amount)->set('lines.0.project', 'F2F')->set('lines.0.fund_source', 'donor_grant')
        ->set('lines.1.account', '4101')->set('lines.1.side', 'credit')->set('lines.1.amount', $second)->set('lines.1.project', 'F2F');
}

function checkStates($page): array
{
    return collect($page->viewData('checks'))->mapWithKeys(fn ($check) => [$check['label'] => $check['ok']])->all();
}

it('opens for the roles that prepare vouchers and not for the others', function () {
    $this->actingAs(builderUser(RoleId::Accountant))->get(route('vouchers.create'))->assertOk()->assertSee('New voucher');
    $this->actingAs(builderUser(RoleId::HeadOffice, 'Hana'))->get(route('vouchers.create'))->assertOk();
    $this->actingAs(builderUser(RoleId::FinancialManager, 'Fatima'))->get(route('vouchers.create'))->assertForbidden();
    $this->actingAs(builderUser(RoleId::Cashier, 'Carl'))->get(route('vouchers.create'))->assertForbidden();
});

it('adds a New voucher button to the list only for those who can prepare', function () {
    Livewire::actingAs(builderUser(RoleId::Accountant))->test(Vouchers::class)->assertSeeHtml('data-test="new-voucher"');
    Livewire::actingAs(builderUser(RoleId::FinancialManager, 'Fatima'))->test(Vouchers::class)->assertDontSeeHtml('data-test="new-voucher"');
});

it('shows the saved notice on the list after the builder redirects there', function () {
    session()->flash('notice', 'Voucher CRV-2026-0001 is saved and waits for approval.');

    Livewire::actingAs(builderUser(RoleId::Accountant))->test(Vouchers::class)->assertSeeHtml('data-test="notice"')->assertSee('is saved and waits for approval');
});

it('starts with two blank lines and lets lines be added and removed', function () {
    builderPage()->assertCount('lines', 2)
        ->call('addLine')->assertCount('lines', 3)
        ->call('removeLine', 0)->assertCount('lines', 2)
        ->call('removeLine', 0)->call('removeLine', 0)->assertCount('lines', 1);
});

it('lets a budget line fill its project and account, and lets a different account let go of it', function () {
    builderPage()
        ->set('lines.0.budget_line', 'F2F|Maintenance')
        ->assertSet('lines.0.account', '5405')->assertSet('lines.0.project', 'F2F')
        ->set('lines.0.account', '1111')
        ->assertSet('lines.0.budget_line', '');
});

it('lets a cash account fix the currency, and only a pound line asks for a rate', function () {
    builderPage()
        ->set('lines.0.account', '1112')->assertSet('lines.0.currency', 'SYP')
        ->assertSeeHtml('data-test="rate-0"')
        ->set('lines.1.account', '1111')->assertSet('lines.1.currency', 'USD')
        ->assertDontSeeHtml('data-test="rate-1"');
});

it('gives a pound payment the average rate of the project as soon as the project is known', function () {
    ledgerApprovedVoucher(['date' => CarbonImmutable::parse('2026-02-01')], [
        new LineDraft('1112', 'SYP', debit: 7500000, rate: 15000, debitUsd: 500, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
        new LineDraft('1111', 'USD', credit: 500, projectCode: 'F2F', fundSource: FundSource::DonorGrant),
    ]);

    builderPage()->set('type', 'CPV')->set('lines.0.account', '1112')->set('lines.0.project', 'F2F')->assertSet('lines.0.rate', '15000');
    builderPage()->set('lines.0.account', '1112')->set('lines.0.project', 'F2F')->assertSet('lines.0.rate', '')->set('type', 'CPV')->assertSet('lines.0.rate', '15000');
    builderPage()->set('type', 'CPV')->set('lines.0.account', '1112')->set('lines.0.project', 'SZ')->assertSet('lines.0.rate', '');
});

it('shows the dollars of each line and the totals as you type', function () {
    $page = builderPage()->set('type', 'CRV')
        ->set('lines.0.account', '1112')->set('lines.0.amount', '7,500,000')->set('lines.0.rate', '15000')
        ->set('lines.1.account', '1111')->set('lines.1.side', 'credit')->set('lines.1.amount', '500');

    $page->assertSeeHtml('data-test="usd-0"')->assertSee('500.00');

    expect($page->viewData('debit'))->toBe('500.00')
        ->and($page->viewData('credit'))->toBe('500.00')
        ->and($page->viewData('difference'))->toBe('0.00');
});

it('has a live check list that is the posting rules: nothing ticked until the voucher is right', function () {
    $page = builderPage();
    expect(array_filter(checkStates($page)))->toBe([]);

    fillReceipt($page);
    expect(array_filter(checkStates($page)))->toHaveCount(9)
        ->and($page->viewData('ready'))->toBeTrue();
    $page->assertSeeHtml('data-test="ready"');
});

it('shows which check fails and why, with the row number, when the voucher is out of balance or a tag is missing', function () {
    $page = fillReceipt(builderPage(), '100', '90')->set('lines.0.fund_source', '');

    $states = checkStates($page);

    expect($states[__('ledger.builder.check.balanced')])->toBeFalse()
        ->and($states[__('ledger.builder.check.tags')])->toBeFalse()
        ->and($states[__('ledger.builder.check.accounts')])->toBeTrue()
        ->and($page->viewData('ready'))->toBeFalse();

    $page->assertSee('out by 10.00 USD')->assertSee('Line 1: Account 1111 needs: Fund source.');
});

it('numbers a message by the row you see even when blank rows come before it', function () {
    builderPage()->call('addLine')->set('lines.2.account', '1111')->set('lines.2.amount', '10')->assertSee('Line 3:');
});

it('warns about spending after the project end date and about a budget line spent past its limit, and still lets you save', function () {
    GlProject::query()->find('F2F')->update(['end_date' => '2026-01-31']);

    $page = builderPage()->set('type', 'CPV')->set('date', '2026-03-10')
        ->set('lines.0.budget_line', 'F2F|Maintenance')->set('lines.0.amount', '12000')->set('lines.0.center', 'Aleppo')
        ->set('lines.1.account', '1111')->set('lines.1.side', 'credit')->set('lines.1.amount', '12000')->set('lines.1.project', 'F2F')->set('lines.1.fund_source', 'donor_grant');

    $page->assertSeeHtml('data-test="warnings"')->assertSee('ended on 2026-01-31')->assertSee('Budget line F2F|Maintenance has');

    // 12,000 USD needs an approved request, which is a real rule and not a warning; a smaller payment is only warned about.
    $small = builderPage()->set('type', 'CPV')->set('date', '2026-03-10')
        ->set('lines.0.budget_line', 'F2F|Maintenance')->set('lines.0.amount', '120')->set('lines.0.center', 'Aleppo')
        ->set('lines.1.account', '1111')->set('lines.1.side', 'credit')->set('lines.1.amount', '120')->set('lines.1.project', 'F2F')->set('lines.1.fund_source', 'donor_grant');

    $small->assertSee('ended on 2026-01-31');
    expect($small->viewData('ready'))->toBeTrue();
});

it('saves a receipt as pending approval prepared by the signed-in person, and goes to it', function () {
    $user = builderUser(RoleId::Accountant);

    fillReceipt(builderPage($user))->set('description', 'ACN first instalment')->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('vouchers', ['voucher' => 'CRV-2026-0001']));

    $voucher = Voucher::query()->findOrFail('CRV-2026-0001');

    expect($voucher->status)->toBe(VoucherStatus::PendingApproval)
        ->and($voucher->prepared_by)->toBe((int) $user->getAuthIdentifier())
        ->and($voucher->description)->toBe('ACN first instalment')
        ->and($voucher->lines)->toHaveCount(2)
        ->and($voucher->lines[0]->donor)->toBe('Caritas Poland')
        ->and($voucher->lines[0]->donor_currency)->toBe('EUR')
        ->and($voucher->lines[0]->debit_donor)->toBe('217.39');
});

it('saves a payment and a transfer too', function () {
    builderPage()->set('type', 'CPV')
        ->set('lines.0.budget_line', 'F2F|Maintenance')->set('lines.0.amount', '120')->set('lines.0.center', 'Aleppo')
        ->set('lines.1.account', '1111')->set('lines.1.side', 'credit')->set('lines.1.amount', '120')->set('lines.1.project', 'F2F')->set('lines.1.fund_source', 'donor_grant')
        ->call('save')->assertHasNoErrors();

    builderPage()->set('type', 'TRF')
        ->set('lines.0.account', '1121')->set('lines.0.amount', '75')->set('lines.0.project', 'SZ')->set('lines.0.fund_source', 'donor_grant')
        ->set('lines.1.account', '1111')->set('lines.1.side', 'credit')->set('lines.1.amount', '75')->set('lines.1.project', 'SZ')->set('lines.1.fund_source', 'donor_grant')
        ->call('save')->assertHasNoErrors();

    expect(Voucher::query()->orderBy('id')->pluck('id')->all())->toBe(['CPV-2026-0001', 'TRF-2026-0001'])
        ->and(Voucher::query()->where('status', 'pending_approval')->count())->toBe(2);
});

it('keeps attached files on the private disk with their checksum and size', function () {
    Storage::fake('local');
    $file = UploadedFile::fake()->create('bank-slip.pdf', 120, 'application/pdf');

    fillReceipt(builderPage())->set('attachments', [$file])->call('save')->assertHasNoErrors();

    $attachment = VoucherAttachment::query()->firstOrFail();

    expect($attachment->voucher_id)->toBe('CRV-2026-0001')
        ->and($attachment->original_name)->toBe('bank-slip.pdf')
        ->and($attachment->size)->toBe(120 * 1024)
        ->and($attachment->sha256)->toHaveLength(64)
        ->and($attachment->stored_path)->toStartWith('voucher-attachments/CRV-2026-0001/')->toEndWith('.pdf');

    Storage::disk('local')->assertExists($attachment->stored_path);
});

it('refuses files of the wrong type, too big or too many, in either language', function () {
    Storage::fake('local');

    builderPage()->set('attachments', [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])
        ->assertHasErrors(['attachments.0'])->assertSee('Only PDF, JPG and PNG');

    builderPage()->set('attachments', [UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf')])
        ->assertHasErrors(['attachments.0'])->assertSee('at most 5 MB');

    builderPage()->set('attachments', array_map(fn ($n) => UploadedFile::fake()->create("f{$n}.pdf", 10, 'application/pdf'), range(1, 11)))
        ->assertHasErrors(['attachments'])->assertSee('at most 10 files');
});

it('lets a file be removed before saving', function () {
    Storage::fake('local');

    $page = builderPage()->set('attachments', [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')])
        ->assertCount('attachments', 2)->call('removeAttachment', 0);

    $page->assertCount('attachments', 1);
});

it('saves nothing and says why when there are no lines, a pound line has no rate, or the month is closed', function () {
    builderPage()->call('save')->assertSee('Add at least two lines');

    builderPage()->set('lines.0.account', '1112')->set('lines.0.amount', '1500000')->set('lines.1.account', '1111')->set('lines.1.side', 'credit')->set('lines.1.amount', '100')
        ->call('save')->assertSee('a pound line needs a rate');

    MonthClose::query()->create(['month' => '2026-03', 'closed_at' => now(), 'closed_by' => 1]);
    fillReceipt(builderPage())->set('date', '2026-03-10')->call('save')->assertSee('The month 2026-03 is closed');

    expect(Voucher::query()->count())->toBe(0);
});

it('keeps the voucher number and every file out of a save that fails', function () {
    Storage::fake('local');
    MonthClose::query()->create(['month' => '2026-03', 'closed_at' => now(), 'closed_by' => 1]);

    fillReceipt(builderPage())->set('date', '2026-03-10')->set('attachments', [UploadedFile::fake()->create('slip.pdf', 10, 'application/pdf')])->call('save');

    expect(Voucher::query()->count())->toBe(0)
        ->and(VoucherAttachment::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('voucher-attachments'))->toBe([]);

    $ok = fillReceipt(builderPage())->set('date', '2026-04-10')->call('save');
    expect(Voucher::query()->first()->id)->toBe('CRV-2026-0001');
});

it('speaks Arabic', function () {
    app()->setLocale('ar');

    builderPage()->assertSee('سند جديد')->assertSee('الفحوصات')->assertSee('إضافة سطر');
});
