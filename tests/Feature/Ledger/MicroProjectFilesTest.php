<?php

use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Livewire\MicroProjectFiles;
use App\Models\GlBudgetLine;
use App\Models\MicroLoan;
use App\Models\MicroQuote;
use App\Models\User;
use App\Models\Voucher;
use App\Models\WebUserRole;
use App\Services\Ledger\VoucherApprovals;
use App\Services\Loans\LoanException;
use App\Services\Loans\MicroFiles;
use App\Services\Loans\MicroLoans;
use App\Services\Loans\MicroPurchases;
use App\Services\Treasury\MonthlyRates;
use App\Support\AmountInWords;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    seedChartWithTestProjects();

    GlBudgetLine::query()->create(['key' => 'F2F|Micro grants', 'project_code' => 'F2F', 'name' => 'Micro grants', 'account_code' => '5413', 'budget' => 100000, 'currency' => 'USD']);

    $this->accountant = mfUser(RoleId::Accountant, 'Ann');
    $this->manager = mfUser(RoleId::FinancialManager, 'Fadi');
    $this->program = mfUser(RoleId::Employee, 'Paula');
    WebUserRole::query()->create(['user_id' => $this->program->getKey(), 'role_id' => RoleId::ProgramManager->value]);
    app(MonthlyRates::class)->set('2026-05', 'SYP', '15000', null, $this->manager);
});

function mfUser(RoleId $role, string $name): User
{
    return User::query()->findOrFail(createFinanceUser($name, 'secret pass', $role->name, $role->value));
}

/** A file of 1,000 USD for Huda on F2F, half to repay at 50 USD a month, with its purchase list. */
function mfFile(User $by): MicroLoan
{
    return app(MicroFiles::class)->save($by, null, [
        'beneficiary' => 'Huda Saleh', 'name_en' => 'Huda Saleh', 'national_number' => '0102', 'mobile' => '0944 123 456',
        'project_number' => '3520', 'profession' => 'Upholstery', 'address' => 'Aleppo', 'funding' => 'project:F2F', 'currency' => 'USD',
        'requested_amount' => '1000', 'recovery_percent' => '50', 'instalment_amount' => '50',
    ], [['name' => 'Sewing machine', 'kind' => 'Juki', 'quantity' => '1', 'condition' => 'new']]);
}

/** The same file with two quotes, approved by the Program Manager and the Financial Manager. */
function mfApproved(User $by, User $program, User $manager): MicroLoan
{
    $files = app(MicroFiles::class);
    $file = mfFile($by);
    $files->addQuote($by, $file, 'Huda', MicroQuote::BENEFICIARY, '2026-05-01', '1000', 'USD', null);
    $files->addQuote($by, $file, 'Abu Sami', MicroQuote::MERCHANT, '2026-05-02', '1050', 'USD', null);
    $files->submit($file);
    $files->approve($program, $file);

    return $files->approve($manager, $file->fresh());
}

it('keeps a file with no voucher until its payment order is approved in turn', function () {
    $files = app(MicroFiles::class);
    $file = mfFile($this->accountant);

    expect($file->stage)->toBe(MicroLoan::DRAFT)
        ->and($file->items()->count())->toBe(1)
        ->and(fn () => $files->submit($file))->toThrow(LoanException::class);

    $files->addQuote($this->accountant, $file, 'Huda', MicroQuote::BENEFICIARY, '2026-05-01', '1000', 'USD', null);
    expect(fn () => $files->submit($file))->toThrow(LoanException::class);

    $files->addQuote($this->accountant, $file, 'Abu Sami', MicroQuote::MERCHANT, '2026-05-02', '1050', 'USD', null);
    $file = $files->submit($file);

    // The Financial Manager comes after the Program Manager; 1,000 USD needs no General Manager.
    expect(fn () => $files->approve($this->manager, $file))->toThrow(LoanException::class);
    $file = $files->approve($this->program, $file);
    expect($file->stage)->toBe(MicroLoan::SUBMITTED)->and($files->nextRole($file))->toBe(RoleId::FinancialManager);

    $file = $files->approve($this->manager, $file);
    expect($file->stage)->toBe(MicroLoan::APPROVED)
        ->and(Voucher::query()->count())->toBe(0)
        ->and(fn () => $files->save($this->accountant, $file, ['beneficiary' => 'X'], []))->toThrow(LoanException::class);
});

it('pays the purchases of an approved file only, the file standing for the request above the limit', function () {
    $purchases = app(MicroPurchases::class);
    $draft = mfFile($this->accountant);

    expect(fn () => $purchases->payInvoice($this->accountant, $draft, [
        'date' => '2026-05-10', 'cash_account' => '1111', 'amount' => '600', 'merchant' => 'Abu Sami', 'number' => 'F-1', 'description_ar' => 'ماكينة',
    ]))->toThrow(LoanException::class);

    $file = mfApproved($this->accountant, $this->program, $this->manager);

    // 9,000,000 SYP at the month's 15,000 adds 600 USD to the loan, above the 500 USD limit, with no request.
    $invoice = $purchases->payInvoice($this->accountant, $file, [
        'date' => '2026-05-10', 'cash_account' => '1112', 'amount' => '9,000,000', 'merchant' => 'Abu Sami', 'number' => 'F-1', 'description_ar' => 'ماكينة خياطة',
    ]);
    $voucher = Voucher::query()->with('lines')->findOrFail($invoice->voucher_id);

    expect($voucher->type)->toBe(VoucherType::Payment)
        ->and($voucher->micro_loan_id)->toBe($file->id)
        ->and($voucher->request_id)->toBeNull()
        ->and($voucher->lines->map(fn ($line): array => [$line->account_code, $line->currency, (string) $line->debit_orig, (string) $line->credit_orig, (string) $line->debit_usd, (string) $line->credit_usd])->all())->toBe([
            ['1204', 'USD', '600.00', '0.00', '600.00', '0.00'],
            ['1112', 'SYP', '0.00', '9000000.00', '0.00', '600.00'],
        ])
        ->and((string) $invoice->loan_amount)->toBe('600.00');
});

it('buys through an advance, takes back what is left, and opens the loan when the protocol is signed', function () {
    $purchases = app(MicroPurchases::class);
    $file = mfApproved($this->accountant, $this->program, $this->manager);

    $purchases->payInvoice($this->accountant, $file, ['date' => '2026-05-10', 'cash_account' => '1111', 'amount' => '300', 'merchant' => 'Abu Sami', 'number' => 'F-1', 'description_ar' => 'ماكينة']);
    $advance = $purchases->giveAdvance($this->accountant, $file, ['date' => '2026-05-11', 'cash_account' => '1111', 'amount' => '800', 'person' => 'Rami']);
    $purchases->invoiceFromAdvance($this->accountant, $file, $advance->id, ['date' => '2026-05-12', 'amount' => '700', 'merchant' => 'Al Nour', 'number' => 'N-7', 'description_ar' => 'طاولة']);

    expect(fn () => $purchases->invoiceFromAdvance($this->accountant, $file, $advance->id, ['date' => '2026-05-12', 'amount' => '150', 'merchant' => 'X', 'number' => '1', 'description_ar' => 'Y']))->toThrow(LoanException::class)
        ->and(fn () => $purchases->close($this->accountant, $file, ['signed_on' => '2026-06-01']))->toThrow(LoanException::class);

    $return = $purchases->returnAdvance($this->accountant, $file, $advance->id, ['date' => '2026-05-13', 'amount' => '100']);
    expect(Voucher::query()->find($return->voucher_id)->type)->toBe(VoucherType::Receipt)
        ->and($purchases->totals($file))->toMatchArray(['invoiced' => '1000.00', 'invoiced_usd' => '1000.00', 'open_advances' => '0.00'])
        ->and(fn () => $purchases->close($this->accountant, $file, ['signed_on' => '2026-06-01', 'trust_bond' => '999']))->toThrow(LoanException::class);

    $file = $purchases->close($this->accountant, $file, ['signed_on' => '2026-06-01']);
    $grant = Voucher::query()->with('lines')->findOrFail($file->closing_voucher_id);

    expect($file->stage)->toBe(MicroLoan::ACTIVE)
        ->and([(string) $file->loan_amount, (string) $file->grant_amount, (string) $file->book_usd])->toBe(['500.00', '500.00', '500.00'])
        ->and($file->loan_date->toDateString())->toBe('2026-05-12')
        ->and($grant->lines->map(fn ($line): array => [$line->account_code, $line->budget_line_key, (string) $line->debit_usd, (string) $line->credit_usd])->all())->toBe([
            ['5413', 'F2F|Micro grants', '500.00', '0.00'],
            ['1204', null, '0.00', '500.00'],
        ])
        ->and($file->instalments()->count())->toBe(10)
        ->and($file->instalments()->first()->due_date->toDateString())->toBe('2026-08-01');

    // The loan is repaid like any other: 50 USD off 1204 at its book dollars.
    $receipt = app(MicroLoans::class)->receive($file->fresh(), CarbonImmutable::parse('2026-08-02'), '50', '1111', 5);
    expect($receipt->lines->pluck('account_code')->all())->toBe(['1111', '1204'])
        ->and($file->fresh()->owed())->toBe('450.00');
});

it('opens the purchases again when the grant voucher of the closing is rejected', function () {
    $purchases = app(MicroPurchases::class);
    $file = mfApproved($this->accountant, $this->program, $this->manager);
    $purchases->payInvoice($this->accountant, $file, ['date' => '2026-05-10', 'cash_account' => '1111', 'amount' => '400', 'merchant' => 'Abu Sami', 'number' => 'F-1', 'description_ar' => 'ماكينة']);
    $file = $purchases->close($this->accountant, $file, ['signed_on' => '2026-06-01']);

    app(VoucherApprovals::class)->reject(Voucher::query()->findOrFail($file->closing_voucher_id), $this->manager, 'Wrong date');

    expect($file->fresh()->stage)->toBe(MicroLoan::APPROVED)
        ->and($file->instalments()->count())->toBe(0)
        ->and($file->fresh()->closing_voucher_id)->toBeNull();
});

it('creates a file from the screen, lets only the next approver approve, and prints its forms', function () {
    Livewire::actingAs($this->accountant)->test(MicroProjectFiles::class)
        ->call('newFile')
        ->set('form.beneficiary', 'Samir')->set('form.funding', 'project:F2F')->set('form.currency', 'USD')
        ->set('form.requested_amount', '2000')
        ->assertSet('form.instalment_amount', '10')
        ->set('items.0.name', 'Oven')
        ->call('saveFile')
        ->assertSet('problems', [])
        ->assertSee(__('micro.saved'));

    $file = MicroLoan::query()->sole();
    app(MicroFiles::class)->addQuote($this->accountant, $file, 'Samir', MicroQuote::BENEFICIARY, '', '2000', 'USD', null);
    app(MicroFiles::class)->addQuote($this->accountant, $file, 'Abu Sami', MicroQuote::MERCHANT, '', '2100', 'USD', null);

    Livewire::actingAs($this->accountant)->test(MicroProjectFiles::class, ['file' => (string) $file->id])
        ->call('submit')->assertSet('problems', [])
        ->assertDontSeeHtml('data-test="approve"');

    Livewire::actingAs($this->manager)->test(MicroProjectFiles::class, ['file' => (string) $file->id])
        ->assertDontSeeHtml('data-test="approve"')
        ->call('approve')->assertSet('problems', [__('micro.problem.not_your_step')]);

    Livewire::actingAs($this->program)->test(MicroProjectFiles::class, ['file' => (string) $file->id])
        ->assertSeeHtml('data-test="approve"')
        ->call('approve')->assertSet('problems', []);

    $this->withoutVite();
    $this->actingAs($this->accountant)->get(route('entries.micro-files.print', ['file' => $file->id, 'form' => 'order']))
        ->assertOk()->assertSee('طلب صرف')->assertSee('Samir');
    $this->actingAs($this->accountant)->get(route('entries.micro-files.print', ['file' => $file->id, 'form' => 'bond']))
        ->assertOk()->assertSee('فقط ألفان دولار أمريكي لا غير');
    $this->actingAs(mfUser(RoleId::Cashier, 'Carl'))->get(route('entries.micro-files.print', ['file' => $file->id, 'form' => 'order']))->assertForbidden();
});

it('writes amounts in words in Arabic and English', function () {
    $words = new AmountInWords;

    expect($words->arabic('58500000', 'SYP'))->toBe('فقط ثمانية وخمسون مليون وخمسمائة ألف ليرة سورية لا غير')
        ->and($words->arabic('200000', 'SYP'))->toBe('فقط مئتا ألف ليرة سورية لا غير')
        ->and($words->english('3015.50', 'USD'))->toBe('Only three thousand fifteen US dollars and 50/100');
});
