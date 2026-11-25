<?php

use App\Enums\RoleId;
use App\Livewire\LegacyMicroImport as ImportScreen;
use App\Models\MicroLoan;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Excel\ExcelException;
use App\Services\Excel\ExcelSheet;
use App\Services\Loans\LegacyMicroImport;
use App\Services\Loans\MicroLoans;
use App\Services\Treasury\DailyRates;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_11_27_090000_add_legacy_import_to_gl_micro_loans.php'))->up();
    seedChartWithTestProjects();

    $this->accountant = User::query()->findOrFail(createFinanceUser('Ann', 'secret pass', RoleId::Accountant->name, RoleId::Accountant->value));
});

/**
 * An old-projects file: the projects sheet first, then the instalments.
 *
 * @param  list<list<string|int|float|null>>  $projects
 * @param  list<list<string|int|float|null>>  $instalments
 */
function legacyFile(array $projects, array $instalments): string
{
    return app(ExcelSheet::class)->write([
        __('legacy_micro.sheet.projects') => ['headings' => LegacyMicroImport::PROJECT_COLUMNS, 'rows' => $projects],
        __('legacy_micro.sheet.instalments') => ['headings' => LegacyMicroImport::INSTALMENT_COLUMNS, 'rows' => $instalments],
    ], false);
}

/** A pound project of F2F: 20,000,000 at 50 % and 10,000, four instalments of 2,500,000, the first one paid; and a dollar one from a fund, fully repaid. */
function legacyGoodFile(): string
{
    return legacyFile([
        ['P-1', 'Huda', '', '', 'Tailor', '', 'F2F', '', 'SYP', 20000000, 50, 10000, '2023-01-10', 1],
        ['P-2', 'Samir', '', '', 'Grocery', 'Aleppo', '', 'Caritas Poland', 'USD', 1000, '', '', '2023-02-01', 2],
    ], [
        ['P-1', '2023-02-10', 2500000, 2500000],
        ['P-1', '2023-03-10', 2500000, 0],
        ['P-1', '2023-04-10', 2500000, 0],
        ['P-1', '2023-05-10', 2500000, 0],
        ['P-2', '2023-04-01', 250, 250],
        ['P-2', '2023-06-01', 250, 250],
    ]);
}

it('imports old projects with their schedule and what was repaid, posting nothing, and skips them when sent again', function () {
    $import = app(LegacyMicroImport::class);
    $path = legacyGoodFile();

    expect($import->review($path))->toMatchArray(['projects' => 2, 'new' => 2, 'skipped' => 0, 'instalments' => 6, 'owed' => ['SYP' => '7500000.00', 'USD' => '0.00'], 'problem_count' => 0]);

    expect($import->save($path, $this->accountant))->toBe(2)
        ->and(Voucher::query()->count())->toBe(0);

    $pounds = MicroLoan::query()->where('project_number', 'P-1')->with('instalments')->firstOrFail();
    expect($pounds->only(['stage', 'project_code', 'funder', 'city', 'currency', 'fund_source', 'payment_interval_months']))->toBe([
        'stage' => 'active', 'project_code' => 'F2F', 'funder' => 'Caritas Poland', 'city' => 'Aleppo', 'currency' => 'SYP', 'fund_source' => 'donor_grant', 'payment_interval_months' => 1,
    ])
        ->and([(string) $pounds->loan_amount, (string) $pounds->grant_amount, (string) $pounds->book_usd, (string) $pounds->legacy_paid])->toBe(['10000000.00', '10000000.00', '1000.00', '2500000.00'])
        ->and($pounds->imported_at)->not->toBeNull()
        ->and($pounds->owed())->toBe('7500000.00');

    $dollars = MicroLoan::query()->where('project_number', 'P-2')->firstOrFail();
    expect([$dollars->project_code, $dollars->funder, $dollars->fund_source, (string) $dollars->recovery_percent, $dollars->owed()])->toBe([null, 'Caritas Poland', 'recovered_loans', '50.00', '0.00']);

    expect($import->review($path))->toMatchArray(['new' => 0, 'skipped' => 2, 'problem_count' => 0])
        ->and($import->save($path, $this->accountant))->toBe(0)
        ->and(MicroLoan::query()->count())->toBe(2);
});

it('clears an imported loan\'s dollars with its last repayment, counting what was repaid before the import', function () {
    $manager = User::query()->findOrFail(createFinanceUser('Fadi', 'secret pass', RoleId::FinancialManager->name, RoleId::FinancialManager->value));
    app(DailyRates::class)->propose('SYP', '2026-05-10', '16000', null, $manager, approveNow: true);
    app(LegacyMicroImport::class)->save(legacyGoodFile(), $this->accountant);
    $loan = MicroLoan::query()->where('project_number', 'P-1')->firstOrFail();

    $voucher = app(MicroLoans::class)->receive($loan, CarbonImmutable::parse('2026-05-10'), '7500000', '1112', 5);

    // 1,000 $ on the books less the 250 $ repaid before: the last 7,500,000 take the 750 $ left.
    expect((string) $voucher->lines->firstWhere('account_code', MicroLoans::LOANS_ACCOUNT)->credit_usd)->toBe('750.00')
        ->and($loan->fresh()->owed())->toBe('0.00');
});

it('saves nothing from a file with a wrong project or instalment, and says where', function () {
    $path = legacyFile([
        ['P-1', 'Huda', '', '', '', '', 'NOPE', '', 'SYP', 1000, 50, '', '2023-01-10', 1],
        ['P-2', 'Samir', '', '', '', 'Aleppo', 'F2F', '', 'USD', 1000, 50, '', '2023-02-01', 1],
        ['P-2', 'Twice', '', '', '', 'Aleppo', 'F2F', '', 'USD', 1000, 50, '', '2023-02-01', 1],
    ], [
        ['P-2', '2023-03-01', 300, 400],
        ['P-9', '2023-03-01', 100, 0],
    ]);
    $import = app(LegacyMicroImport::class);
    $review = $import->review($path);

    expect($review['new'])->toBe(0)
        ->and($review['problems'])->toContain(__('legacy_micro.where.project', ['row' => 2, 'number' => 'P-1']).': '.__('legacy_micro.problem.project', ['code' => 'NOPE']))
        ->and($review['problems'])->toContain(__('legacy_micro.where.project', ['row' => 2, 'number' => 'P-1']).': '.__('legacy_micro.problem.rate'))
        ->and($review['problems'])->toContain(__('legacy_micro.where.instalment', ['row' => 2]).': '.__('legacy_micro.problem.paid_amount'))
        ->and($review['problems'])->toContain(__('legacy_micro.where.project', ['row' => 4, 'number' => 'P-2']).': '.__('legacy_micro.problem.duplicate', ['row' => 3]))
        ->and($review['problems'])->toContain(__('legacy_micro.problem.orphan', ['number' => 'P-9']));

    expect(fn () => $import->save($path, $this->accountant))->toThrow(ExcelException::class)
        ->and(MicroLoan::query()->count())->toBe(0);
});

it('uploads the file on the loans screen, shows its summary and saves it', function () {
    Storage::fake('local');
    $file = UploadedFile::fake()->createWithContent('old-projects.xlsx', (string) file_get_contents(legacyGoodFile()));

    Livewire::actingAs($this->accountant)->test(ImportScreen::class)
        ->call('open')
        ->set('upload', $file)
        ->assertSeeHtml('data-test="legacy-micro-summary"')
        ->assertSeeHtml('data-test="legacy-micro-save"')
        ->call('save')
        ->assertDispatched('excel-imported')
        ->assertSet('show', false);

    expect(MicroLoan::query()->whereNotNull('imported_at')->count())->toBe(2)
        ->and(Storage::disk('local')->allFiles('legacy-micro'))->toBe([]);
});
