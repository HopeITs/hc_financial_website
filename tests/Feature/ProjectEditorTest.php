<?php

use App\Enums\RoleId;
use App\Livewire\ProjectEditor;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\User;
use App\Services\Projects\BudgetSheetReader;
use App\Services\Projects\ExpenseAccountMapper;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

beforeEach(function () {
    createFinanceSchema();
    if (! Schema::hasColumn('gl_projects', 'contract_number')) {
        (require database_path('migrations/2026_10_25_090000_add_contract_dates_to_gl_projects.php'))->up();
    }
    (new ChartOfAccountsSeeder)->run();
    GlBudgetLine::query()->delete();
    GlProject::query()->delete();
});

function projectEditorUser(RoleId $role = RoleId::HeadOffice): User
{
    return User::query()->findOrFail(createFinanceUser('editor'.$role->value, 'secret pass', $role->name, $role->value));
}

const PROJECT_SHEET_HEADERS = ['Budget Category', 'Budget Line Type', 'Budget Line Name', 'Unit', 'Timing', 'Unit/$ Year 1', 'Total/$ Year 1', 'Unit/EUR', 'Total/EUR', 'Donor'];

/** An ADISC-style budget sheet in a temporary .xlsx file. */
function projectBudgetSheet(array $rows, array $headers = PROJECT_SHEET_HEADERS): string
{
    $path = tempnam(sys_get_temp_dir(), 'bud').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues($headers));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

function projectAdiscRows(): array
{
    return [
        ['Personnel', 'Salaries', 'Project manager', 1, 12, 500, 6000, 440, 5280, 'MCC'],
        ['Personnel', 'Other staff', 'Cleaner', 1, 12, 100, 1200.5, 90, 1080, 'MCC'],
        ['Operations', 'Rent', 'Office rent', 1, 12, 300, 3600, 260, 3120, 'OO'],
        ['Operations', 'Fuel, electricity and water', 'Electricity', 1, 12, 50, 600, 44, 528, 'OO'],
        ['Activities', 'Training', 'Staff training and activities', 1, 4, 250, 1000, 220, 880, 'OO'],
        ['Activities', 'Something unusual', 'Mystery item', 1, 1, 10, 10, 9, 9, 'OO'],
        ['', '', '', '', '', '', '', '', '', ''],
    ];
}

it('proposes the expense account of decision R4-12 from the line type and name', function (string $type, string $name, string $account) {
    expect((new ExpenseAccountMapper)->propose($type, $name))->toBe($account);
})->with([
    ['Salaries', 'Project manager', '5101'],
    ['Other staff', 'Cleaner', '5102'],
    ['Rent', 'Office rent', '5301'],
    ['Fuel', 'Generator fuel', '5302'],
    ['Electricity', 'x', '5302'],
    ['Water', 'x', '5302'],
    ['Phone and internet', 'x', '5303'],
    ['Stationery', 'x', '5304'],
    ['Cleaning', 'x', '5309'],
    ['Maintenance', 'x', '5307'],
    ['Solar', 'Solar panels', '5204'],
    ['Internal transport', 'x', '5306'],
    ['Travel', 'x', '5408'],
    ['Lodging', 'x', '5408'],
    ['Training', 'x', '5406'],
    ['Events', 'x', '5406'],
    ['Courses', 'x', '5406'],
    ['Certificates', 'x', '5403'],
    ['Hospitality', 'x', '5403'],
    ['Staff training', 'x', '5104'],
    ['Activities', 'Staff activities', '5104'],
]);

it('proposes nothing when no rule matches', function () {
    expect((new ExpenseAccountMapper)->propose('Something unusual', 'Mystery item'))->toBeNull();
});

it('maps every account of the decision onto an active expense posting account', function () {
    foreach (['5101', '5102', '5104', '5204', '5301', '5302', '5303', '5304', '5306', '5307', '5309', '5403', '5406', '5408'] as $code) {
        expect(GlAccount::query()->whereKey($code)->where('is_active', true)->exists())->toBeTrue("account $code is missing");
    }
});

it('reads the budget sheet and skips blank rows', function () {
    $lines = (new BudgetSheetReader)->read(projectBudgetSheet(projectAdiscRows()));

    expect($lines)->toHaveCount(6)
        ->and($lines[1])->toMatchArray(['name' => 'Cleaner', 'type' => 'Other staff', 'donor' => 'MCC', 'budget' => '1200.50']);
});

it('keeps the lines of a sheet without a Donor column under the donor typed in the form', function () {
    $file = UploadedFile::fake()->createWithContent('plain.xlsx', file_get_contents(projectBudgetSheet([['Rent', 100]], ['Budget Line Name', 'Total/$ Year 1'])));

    $projects = Livewire::actingAs(projectEditorUser())->test(ProjectEditor::class)
        ->set('projects.0.code', 'P1')->set('projects.0.name', 'P1')->set('projects.0.donor', 'ACN')
        ->set('sheet', $file)->call('importSheet')->get('projects');

    expect($projects)->toHaveCount(1)->and($projects[0]['code'])->toBe('P1')->and($projects[0]['donor'])->toBe('ACN')
        ->and($projects[0]['lines'][0]['donor'])->toBe('ACN');
});

it('lets a role that may create open the screen and a project manager not', function () {
    $this->actingAs(projectEditorUser(RoleId::FinancialManager))->get('/projects/new')->assertOk();
    $this->actingAs(projectEditorUser(RoleId::Accountant))->get('/projects/new')->assertOk();
    $this->actingAs(projectEditorUser(RoleId::ProjectManager))->get('/projects/new')->assertForbidden();
});

it('reads a sheet with other column names, a title block, extra columns and section headings', function () {
    $path = projectBudgetSheet([
        ['Q3 plan', '', '', '', ''],
        ['', '', '', '', ''],
        ['Description', 'Amount (EUR)', 'Total cost (USD)', 'Notes', 'Funder'],
        ['Personnel', '', '', '', ''],
        ['Project manager', 5000, 6000, 'x', 'MCC'],
        ['Office rent', 2600, 3000, '', 'MCC'],
        ['Grand total', 7600, 9000, '', ''],
    ], ['Project budget', '', '', '', '']);
    // The header sits on the third row, below the title row written by the helper and one more title line.
    $lines = (new BudgetSheetReader)->read($path);

    expect($lines)->toHaveCount(2)
        ->and($lines[0]['name'])->toBe('Project manager')
        ->and($lines[0]['budget'])->toBe('6000.00')   // the USD column beats the EUR one
        ->and($lines[0]['currency'])->toBe('USD')
        ->and($lines[0]['category'])->toBe('Personnel') // the heading row names the lines below it
        ->and($lines[1]['donor'])->toBe('MCC');
});

it('reads Arabic headers, picks the busiest sheet and computes a missing total', function () {
    $path = tempnam(sys_get_temp_dir(), 'bud').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['ملاحظات']));
    $writer->getCurrentSheet()->setName('Notes');
    $writer->addNewSheetAndMakeItCurrent()->setName('الموازنة');
    $writer->addRow(Row::fromValues(['اسم البند', 'العدد', 'المدة', 'سعر الوحدة']));
    $writer->addRow(Row::fromValues(['إيجار المكتب', 2, 12, 100]));
    $writer->addRow(Row::fromValues(['كهرباء', 1, 6, '1,000.5']));
    $writer->close();

    $lines = (new BudgetSheetReader)->read($path);

    expect($lines)->toHaveCount(2)
        ->and($lines[0]['budget'])->toBe('2400.00')
        ->and($lines[1]['budget'])->toBe('6003.00')
        ->and($lines[0]['donor'])->toBe('');
});

it('refuses a workbook with no recognisable budget table', function () {
    (new BudgetSheetReader)->read(projectBudgetSheet([['a', 'b']], ['Foo', 'Bar']));
})->throws(InvalidArgumentException::class);

it('splits an ADISC-style sheet into one project per donor, with the proposed accounts', function () {
    $file = UploadedFile::fake()->createWithContent('adisc.xlsx', file_get_contents(projectBudgetSheet(projectAdiscRows())));

    $component = Livewire::actingAs(projectEditorUser())->test(ProjectEditor::class)
        ->set('projects.0.code', 'ADISC')->set('projects.0.name', 'ADISC')->set('projects.0.city', 'Aleppo')
        ->set('sheet', $file)
        ->call('importSheet')
        ->assertHasNoErrors();

    $projects = $component->get('projects');

    expect($projects)->toHaveCount(2)
        ->and($projects[0]['code'])->toBe('ADISC-MCC')
        ->and($projects[1]['code'])->toBe('ADISC-OO')
        ->and($projects[0]['city'])->toBe('Aleppo')
        ->and(collect($projects[0]['lines'])->pluck('account_code')->all())->toBe(['5101', '5102'])
        ->and(collect($projects[1]['lines'])->pluck('account_code')->all())->toBe(['5301', '5302', '5104', '']);
});

it('saves the two donor projects with their start dates and lines once the missing account is chosen', function () {
    $file = UploadedFile::fake()->createWithContent('adisc.xlsx', file_get_contents(projectBudgetSheet(projectAdiscRows())));

    $component = Livewire::actingAs(projectEditorUser())->test(ProjectEditor::class)
        ->set('projects.0.code', 'ADISC')->set('projects.0.name', 'ADISC')
        ->set('sheet', $file)->call('importSheet')
        ->set('projects.0.start_date', '2026-04-01')
        ->set('projects.1.start_date', '2026-07-01');

    // The mystery line has no proposal, so saving is refused until the user picks an account.
    $component->call('save')->assertHasErrors(['projects.1.lines.3.account_code']);
    expect(GlProject::query()->count())->toBe(0);

    $component->set('projects.1.lines.3.account_code', '5307')->call('save')->assertHasNoErrors();

    $mcc = GlProject::query()->findOrFail('ADISC-MCC');
    $oo = GlProject::query()->findOrFail('ADISC-OO');

    expect($mcc->start_date->toDateString())->toBe('2026-04-01')
        ->and($mcc->end_date->toDateString())->toBe('2027-03-31')
        ->and($oo->start_date->toDateString())->toBe('2026-07-01')
        ->and($oo->end_date->toDateString())->toBe('2027-06-30')
        ->and($mcc->donor)->toBe('MCC')
        ->and($mcc->budgetLines()->count())->toBe(2)
        ->and($oo->budgetLines()->count())->toBe(4);

    $line = GlBudgetLine::query()->findOrFail('ADISC-OO|Mystery item');
    expect($line->account_code)->toBe('5307')->and($line->budget)->toBe('10.00')->and($line->currency)->toBe('USD')
        ->and(GlBudgetLine::query()->findOrFail('ADISC-MCC|Cleaner')->budget)->toBe('1200.50');
});

it('creates a project and a budget line by hand', function () {
    Livewire::actingAs(projectEditorUser(RoleId::Accountant))->test(ProjectEditor::class)
        ->set('projects.0.code', 'WATER')->set('projects.0.name', 'Water project')->set('projects.0.donor', 'ACN')
        ->set('projects.0.city', 'Homs')->set('projects.0.donor_currency', 'EUR')->set('projects.0.donor_rate', '1.14')
        ->set('projects.0.contract_number', 'C-77')->set('projects.0.start_date', '2026-05-01')->set('projects.0.end_date', '2026-12-31')
        ->call('addLine', 0)
        ->set('projects.0.lines.0.name', 'Pump rent')->set('projects.0.lines.0.account_code', '5301')->set('projects.0.lines.0.budget', '2500')
        ->call('save')
        ->assertHasNoErrors();

    $project = GlProject::query()->findOrFail('WATER');

    expect($project->donor_currency)->toBe('EUR')->and($project->donor_rate)->toBe('1.140000')->and($project->contract_number)->toBe('C-77')
        ->and($project->end_date->toDateString())->toBe('2026-12-31')
        ->and(GlBudgetLine::query()->findOrFail('WATER|Pump rent')->account_code)->toBe('5301');
});

it('rejects a duplicate code, a bad code and a non-expense account', function () {
    GlProject::query()->create(['code' => 'TAKEN', 'name' => 'Taken', 'restriction' => 'With donor restrictions']);

    Livewire::actingAs(projectEditorUser())->test(ProjectEditor::class)
        ->set('projects.0.code', 'TAKEN')->set('projects.0.name', 'x')
        ->call('save')->assertHasErrors(['projects.0.code'])
        ->set('projects.0.code', 'bad code')
        ->call('save')->assertHasErrors(['projects.0.code'])
        ->set('projects.0.code', 'OKAY')
        ->call('addLine', 0)
        ->set('projects.0.lines.0.name', 'Bank')->set('projects.0.lines.0.account_code', '1111')
        ->call('save')->assertHasErrors(['projects.0.lines.0.account_code']);

    expect(GlProject::query()->whereKey('OKAY')->exists())->toBeFalse();
});

it('rejects a project name that is already used, ignoring case', function () {
    GlProject::query()->create(['code' => 'USED', 'name' => 'Clean Water', 'restriction' => 'With donor restrictions', 'city' => 'Idlib']);

    Livewire::actingAs(projectEditorUser())->test(ProjectEditor::class)
        ->set('projects.0.code', 'NEWP')->set('projects.0.name', ' clean water ')
        ->call('save')->assertHasErrors(['projects.0.name'])
        ->set('projects.0.name', 'Other')->set('projects.0.city', 'IDLIB')
        ->call('save')->assertHasNoErrors();

    expect(GlProject::query()->findOrFail('NEWP')->city)->toBe('Idlib');
});

it('edits a saved project and line without changing the line key', function () {
    GlProject::query()->create(['code' => 'EDIT', 'name' => 'Before', 'restriction' => 'With donor restrictions']);
    GlBudgetLine::query()->create(['key' => 'EDIT|Rent', 'project_code' => 'EDIT', 'name' => 'Rent', 'account_code' => '5301', 'budget' => 100, 'currency' => 'USD']);

    Livewire::actingAs(projectEditorUser())->test(ProjectEditor::class, ['code' => 'EDIT'])
        ->assertSet('projects.0.name', 'Before')
        ->set('projects.0.name', 'After')
        ->set('projects.0.lines.0.name', 'Office rent')->set('projects.0.lines.0.budget', '250')->set('projects.0.lines.0.account_code', '5302')
        ->call('removeLine', 0, 0) // a saved line cannot be dropped
        ->call('save')
        ->assertHasNoErrors();

    $line = GlBudgetLine::query()->findOrFail('EDIT|Rent');

    expect(GlProject::query()->findOrFail('EDIT')->name)->toBe('After')
        ->and($line->name)->toBe('Office rent')->and($line->budget)->toBe('250.00')->and($line->account_code)->toBe('5302')
        ->and(GlBudgetLine::query()->count())->toBe(1);
});

it('leaves an existing ADISC project alone and refuses a donor project whose code is taken', function () {
    GlProject::query()->create(['code' => 'ADISC', 'name' => 'ADISC', 'restriction' => 'With donor restrictions']);
    GlBudgetLine::query()->create(['key' => 'ADISC|Rent', 'project_code' => 'ADISC', 'name' => 'Rent', 'account_code' => '5301', 'budget' => 100, 'currency' => 'USD']);
    GlProject::query()->create(['code' => 'NEW-OO', 'name' => 'Taken', 'restriction' => 'With donor restrictions']);
    $sheet = fn () => UploadedFile::fake()->createWithContent('adisc.xlsx', file_get_contents(projectBudgetSheet(array_slice(projectAdiscRows(), 0, 4))));

    Livewire::actingAs(projectEditorUser())->test(ProjectEditor::class)
        ->set('projects.0.code', 'NEW')->set('projects.0.name', 'New')
        ->set('sheet', $sheet())->call('importSheet')
        ->call('save')->assertHasErrors(['projects.1.code']);

    expect(GlProject::query()->whereKey('NEW-MCC')->exists())->toBeFalse(); // all or nothing

    Livewire::actingAs(projectEditorUser())->test(ProjectEditor::class)
        ->set('projects.0.code', 'ADISC')->set('projects.0.name', 'ADISC')
        ->set('sheet', $sheet())->call('importSheet')
        ->call('save')->assertHasNoErrors();

    expect(GlBudgetLine::query()->where('project_code', 'ADISC')->count())->toBe(1)
        ->and(GlProject::query()->whereIn('code', ['ADISC-MCC', 'ADISC-OO'])->count())->toBe(2);
});

it('shows an error for a file that is not a budget sheet', function () {
    $file = UploadedFile::fake()->createWithContent('bad.xlsx', 'not a spreadsheet');

    Livewire::actingAs(projectEditorUser())->test(ProjectEditor::class)
        ->set('projects.0.code', 'X')->set('projects.0.name', 'X')
        ->set('sheet', $file)->call('importSheet')
        ->assertSet('importError', __('projects.manage.import_unreadable'));
});

it('takes donors from the Income accounts of the chart and refuses any other name', function () {
    expect(GlAccount::donorNames()->all())->toContain('ACN', 'GIZ')->not->toContain('INCOME', 'Grants – church agencies');

    Livewire::actingAs(projectEditorUser(RoleId::Accountant))->test(ProjectEditor::class)
        ->set('projects.0.code', 'WATER')->set('projects.0.name', 'Water project')->set('projects.0.donor', 'Oxfam')
        ->call('save')
        ->assertHasErrors(['projects.0.donor']);
});
