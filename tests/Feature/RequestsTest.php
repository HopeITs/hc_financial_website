<?php

use App\Enums\RequestStatus;
use App\Enums\RoleId;
use App\Enums\VoucherType;
use App\Livewire\Requests;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\LedgerRequest;
use App\Models\RequestAudit;
use App\Models\User;
use App\Models\WebUserRole;
use App\Services\Ledger\LedgerContext;
use App\Services\Ledger\LineDraft;
use App\Services\Ledger\Rules\RequestRequiredAboveLimit;
use App\Services\Requests\RequestException;
use App\Services\Requests\RequestWorkflow;
use App\Services\Requests\SubmissionDeadline;
use Carbon\CarbonImmutable;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
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
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    // The seeded budget lines are in the donor's currency; the requests use USD and SYP.
    GlBudgetLine::query()->where('project_code', 'F2F')->update(['currency' => 'USD']);
    GlProject::query()->whereKey('F2F')->update(['legacy_account_id' => 77]);
    DB::connection('legacy')->table('account')->insert(['ID' => 77, 'SubProject_ID' => 7, 'Name' => 'F2F']);
    Storage::fake('local');
});

function rqUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    // The Windows program limits a financial manager to the sub-projects given to them.
    if ($role === RoleId::FinancialManager) {
        DB::connection('legacy')->table('user_accounts')->insert(['user_id' => $id, 'subproject_id' => 7]);
    }

    return User::query()->findOrFail($id);
}

/** A project manager linked to the F2F project (legacy account 77). */
function rqManager(string $name = 'Pam', int $account = 77): User
{
    $user = rqUser(RoleId::ProjectManager, $name);
    DB::table('web_project_managers')->insert(['user_id' => $user->getKey(), 'account_id' => $account]);

    return $user;
}

/** @return list<array<string, string>> */
function rqLines(string $amount = '300.00', string $key = 'F2F|Maintenance'): array
{
    return [['budget_line' => $key, 'amount' => $amount, 'currency' => 'USD']];
}

function rqHeader(array $override = []): array
{
    return $override + ['project' => 'F2F', 'type' => 'monthly', 'month' => '2026-11', 'category' => null, 'description' => 'November costs'];
}

function rqWorkflow(): RequestWorkflow
{
    return app(RequestWorkflow::class);
}

function rqDraft(?User $manager = null, string $amount = '300.00', array $header = []): LedgerRequest
{
    return rqWorkflow()->create($manager ?? rqManager(), rqHeader($header), rqLines($amount));
}

function rqSubmitted(string $amount = '300.00', ?User $manager = null): LedgerRequest
{
    $manager ??= rqManager();

    return rqWorkflow()->submit($manager, rqDraft($manager, $amount));
}

/** The Program Manager's step, which comes before the Financial Manager's. */
function rqProgram(LedgerRequest $request, string $name = 'Paula'): LedgerRequest
{
    return rqWorkflow()->approve(rqUser(RoleId::ProgramManager, $name), $request);
}

function rqPdf(string $name = 'quote.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 20, 'application/pdf');
}

it('creates a draft with lines, a USD total and an audit entry', function () {
    $manager = rqManager();
    $request = rqWorkflow()->create($manager, rqHeader(), [
        ['budget_line' => 'F2F|Maintenance', 'amount' => '300.00', 'currency' => 'USD'],
        ['budget_line' => 'F2F|Monitoring visits', 'amount' => '3000000', 'currency' => 'SYP', 'rate' => '15000'],
    ]);

    expect($request->status)->toBe('draft')
        ->and($request->type)->toBe('monthly')
        ->and($request->month->format('Y-m'))->toBe('2026-11')
        ->and((string) $request->amount)->toBe('500.00')
        ->and($request->lines)->toHaveCount(2)
        ->and($request->lines[1]->amount_usd)->toBe('200.00')
        ->and($request->requested_by)->toBe((int) $manager->getKey())
        ->and(RequestAudit::query()->where('request_id', $request->id)->pluck('action')->all())->toBe(['create']);
});

it('refuses bad lines: a foreign budget line, a duplicate, no amount, an SYP line without a rate', function (array $lines, string $message) {
    expect(fn () => rqWorkflow()->create(rqManager(), rqHeader(), $lines))->toThrow(RequestException::class, $message);
})->with([
    'foreign budget line' => [[['budget_line' => 'SZ|Nothing', 'amount' => '5']], 'choose a budget line'],
    'duplicate' => [[['budget_line' => 'F2F|Maintenance', 'amount' => '5'], ['budget_line' => 'F2F|Maintenance', 'amount' => '6']], 'already listed'],
    'zero amount' => [[['budget_line' => 'F2F|Maintenance', 'amount' => '0']], 'above zero'],
    'SYP without a rate' => [[['budget_line' => 'F2F|Maintenance', 'amount' => '1000', 'currency' => 'SYP']], 'enter the rate'],
    'no lines' => [[], 'at least one line'],
]);

it('allows one monthly request per project and month, extra requests any time, and a new one after a rejection', function () {
    $manager = rqManager();
    $first = rqDraft($manager);

    expect(fn () => rqDraft($manager))->toThrow(RequestException::class, 'already has a monthly request')
        ->and(rqWorkflow()->create($manager, rqHeader(['type' => 'extra']), rqLines('50.00'))->type)->toBe('extra')
        ->and(rqDraft($manager, header: ['month' => '2026-12']))->toBeInstanceOf(LedgerRequest::class);

    $first->update(['status' => 'rejected']);

    expect(rqDraft($manager))->toBeInstanceOf(LedgerRequest::class);
});

it('moves through the states only in order: draft, submitted, approved, closed', function () {
    $manager = rqManager();
    $fm = rqUser(RoleId::FinancialManager, 'Fatima');
    $request = rqDraft($manager);

    expect(fn () => rqWorkflow()->approve($fm, $request))->toThrow(RequestException::class, 'not waiting for a decision')
        ->and(fn () => rqWorkflow()->close($fm, $request))->toThrow(RequestException::class, 'Only an approved request');

    rqWorkflow()->submit($manager, $request);

    expect(fn () => rqWorkflow()->submit($manager, $request))->toThrow(RequestException::class, 'Only a draft')
        ->and(fn () => rqWorkflow()->close($fm, $request))->toThrow(RequestException::class, 'Only an approved request');

    rqProgram($request);

    expect(rqWorkflow()->approve($fm, $request)->status)->toBe('approved')
        ->and(rqWorkflow()->close($fm, $request)->status)->toBe('closed');

    $closed = $request->refresh();

    expect($closed->closed_at)->not->toBeNull()
        ->and(fn () => rqWorkflow()->approve($fm, $closed))->toThrow(RequestException::class)
        ->and(fn () => rqWorkflow()->update($manager, $closed, rqHeader(), rqLines('1.00')))->toThrow(RequestException::class, 'can no longer be changed')
        ->and(fn () => rqWorkflow()->submit($manager, $closed))->toThrow(RequestException::class);
});

it('needs one approval up to USD 2,000 and the General Manager after the Financial Manager above it', function () {
    $manager = rqManager();
    $fm = rqUser(RoleId::FinancialManager, 'Fatima');
    $gm = rqUser(RoleId::GeneralManager, 'Gina');

    $small = rqWorkflow()->submit($manager, rqDraft($manager, '2000.00'));
    rqProgram($small);
    expect(rqWorkflow()->approve($fm, $small)->status)->toBe('approved');

    $large = rqWorkflow()->submit($manager, rqDraft($manager, '2000.01', ['month' => '2026-12']));
    rqProgram($large, 'Pia');
    expect(fn () => rqWorkflow()->approve($gm, $large))->toThrow(AuthorizationException::class);

    $afterFirst = rqWorkflow()->approve($fm, $large);

    expect($afterFirst->status)->toBe('submitted')
        ->and(rqWorkflow()->pendingRole($afterFirst))->toBe(RoleId::GeneralManager)
        ->and(fn () => rqWorkflow()->approve($fm, $afterFirst))->toThrow(AuthorizationException::class);

    $done = rqWorkflow()->approve($gm, $afterFirst);

    expect($done->status)->toBe('approved')
        ->and($done->decided_by)->toBe((int) $gm->getKey())
        ->and($done->approvals->pluck('role_id')->all())->toBe([RoleId::ProgramManager, RoleId::FinancialManager, RoleId::GeneralManager])
        ->and(rqWorkflow()->pendingRole($done))->toBeNull();
});

it('compares an SYP request in USD at the line rate', function () {
    $manager = rqManager();
    $fm = rqUser(RoleId::FinancialManager, 'Fatima');

    $request = rqWorkflow()->create($manager, rqHeader(), [['budget_line' => 'F2F|Maintenance', 'amount' => '45000000', 'currency' => 'SYP', 'rate' => '15000']]);
    rqWorkflow()->submit($manager, $request);
    rqProgram($request);

    expect((string) $request->refresh()->amount)->toBe('3000.00')
        ->and(rqWorkflow()->approve($fm, $request)->status)->toBe('submitted');
});

it('does not approve by itself when the General Manager is away, and any holder of the role can take over', function () {
    $manager = rqManager();
    $fm = rqUser(RoleId::FinancialManager, 'Fatima');
    $gone = rqUser(RoleId::GeneralManager, 'Gone');
    $request = rqWorkflow()->submit($manager, rqDraft($manager, '5000.00'));
    rqProgram($request);
    rqWorkflow()->approve($fm, $request);

    expect($request->refresh()->status)->toBe('submitted');

    WebUserRole::query()->where('user_id', $gone->getKey())->delete();
    $successor = rqUser(RoleId::GeneralManager, 'Successor');

    expect(fn () => rqWorkflow()->approve($gone->refresh()->unsetRelation('webRoles'), $request))->toThrow(AuthorizationException::class)
        ->and(rqWorkflow()->approve($successor, $request)->status)->toBe('approved');
});

it('keeps the request editable until a decision, audits every edit, then locks it', function () {
    $manager = rqManager();
    $fm = rqUser(RoleId::FinancialManager, 'Fatima');
    $request = rqSubmitted('300.00', $manager);

    $edited = rqWorkflow()->update($manager, $request, rqHeader(['description' => 'Changed']), rqLines('450.00'));

    expect((string) $edited->amount)->toBe('450.00')
        ->and($edited->status)->toBe('submitted');

    $fields = RequestAudit::query()->where('request_id', $request->id)->where('action', 'edit')->pluck('new_value', 'field');
    expect($fields->keys()->all())->toContain('description', 'lines', 'total_usd')
        ->and($fields['total_usd'])->toBe('450.00');

    rqProgram($request);

    expect(fn () => rqWorkflow()->update($manager, $request, rqHeader(), rqLines('10.00')))->toThrow(RequestException::class, 'can no longer be changed')
        ->and(fn () => rqWorkflow()->addQuote($manager, $request, rqPdf()))->toThrow(RequestException::class, 'can no longer be changed');

    // A request waiting for the second approval is locked too.
    $large = rqWorkflow()->submit($manager, rqDraft($manager, '3000.00', ['month' => '2026-12']));
    rqProgram($large, 'Pia');
    rqWorkflow()->approve($fm, $large);

    expect(fn () => rqWorkflow()->update($manager, $large, rqHeader(['month' => '2026-12']), rqLines('10.00')))->toThrow(RequestException::class, 'can no longer be changed');
});

it('refuses a project manager anything on a project that is not theirs', function () {
    $owner = rqManager('Owner');
    $foreign = rqManager('Foreign', 99);
    $request = rqDraft($owner);

    expect(fn () => rqWorkflow()->update($foreign, $request, rqHeader(), rqLines('1.00')))->toThrow(AuthorizationException::class)
        ->and(fn () => rqWorkflow()->submit($foreign, $request))->toThrow(AuthorizationException::class)
        ->and(fn () => rqWorkflow()->create($foreign, rqHeader(['month' => '2027-01']), rqLines()))->toThrow(AuthorizationException::class)
        ->and(fn () => rqWorkflow()->addQuote($foreign, $request, rqPdf()))->toThrow(AuthorizationException::class)
        ->and(fn () => rqWorkflow()->approve($foreign, rqWorkflow()->submit($owner, $request)))->toThrow(AuthorizationException::class);

    Livewire::actingAs($foreign)->test(Requests::class)->assertDontSee('#'.$request->id);
    Livewire::actingAs($owner)->test(Requests::class)->assertSee('#'.$request->id);

    $this->actingAs($foreign)->get(route('entries.requests', ['request' => $request->id]))->assertNotFound();
});

it('answers 403 to every action the role does not have', function () {
    $manager = rqManager();
    $request = rqSubmitted('300.00', $manager);
    $accountant = rqUser(RoleId::Accountant, 'Ann');
    $cashier = rqUser(RoleId::Cashier, 'Cora');

    foreach ([$accountant, $manager, $cashier] as $person) {
        expect(fn () => rqWorkflow()->approve($person, $request))->toThrow(AuthorizationException::class)
            ->and(fn () => rqWorkflow()->reject($person, $request, 'No'))->toThrow(AuthorizationException::class);
    }

    foreach ([$accountant, $cashier] as $person) {
        expect(fn () => rqWorkflow()->create($person, rqHeader(['month' => '2027-02']), rqLines()))->toThrow(AuthorizationException::class)
            ->and(fn () => rqWorkflow()->update($person, $request, rqHeader(), rqLines()))->toThrow(AuthorizationException::class);
    }

    expect(fn () => rqWorkflow()->close($manager, $request))->toThrow(AuthorizationException::class);

    $this->actingAs($cashier)->get(route('entries.requests'))->assertForbidden();
    $this->actingAs($accountant)->get(route('entries.requests'))->assertOk();
});

it('does not let a person decide on their own request or decide twice', function () {
    $head = rqUser(RoleId::HeadOffice, 'Hana');
    $own = rqWorkflow()->create($head, rqHeader(), rqLines());
    rqWorkflow()->submit($head, $own);

    expect(fn () => rqWorkflow()->approve($head, $own))->toThrow(AuthorizationException::class);

    $fm = rqUser(RoleId::FinancialManager, 'Fatima');
    $large = rqWorkflow()->submit($head, rqWorkflow()->create($head, rqHeader(['month' => '2026-12']), rqLines('4000.00')));
    rqProgram($large);
    rqWorkflow()->approve($fm, $large);

    expect(fn () => rqWorkflow()->approve($fm, $large))->toThrow(AuthorizationException::class);
});

it('rejects with a reason, keeps it, and never reopens a rejected request', function () {
    $manager = rqManager();
    $fm = rqUser(RoleId::FinancialManager, 'Fatima');
    $request = rqProgram(rqSubmitted('300.00', $manager));

    expect(fn () => rqWorkflow()->reject($fm, $request, '  '))->toThrow(RequestException::class, 'Write the reason');

    $rejected = rqWorkflow()->reject($fm, $request, 'Over the budget');

    expect($rejected->status)->toBe('rejected')
        ->and($rejected->reject_reason)->toBe('Over the budget')
        ->and($rejected->approvals->last()->decision)->toBe('rejected')
        ->and(fn () => rqWorkflow()->approve($fm, $rejected))->toThrow(RequestException::class)
        ->and(fn () => rqWorkflow()->update($manager, $rejected, rqHeader(), rqLines()))->toThrow(RequestException::class, 'can no longer be changed');
});

it('needs two or three price quotes before a maintenance, fixed asset or electronics request is submitted', function (string $category) {
    $manager = rqManager();
    $request = rqWorkflow()->create($manager, rqHeader(['category' => $category]), rqLines('900.00'));

    expect(fn () => rqWorkflow()->submit($manager, $request))->toThrow(RequestException::class, 'price quotes');

    rqWorkflow()->addQuote($manager, $request, rqPdf('one.pdf'));
    expect(fn () => rqWorkflow()->submit($manager, $request))->toThrow(RequestException::class, 'price quotes');

    $quote = rqWorkflow()->addQuote($manager, $request, rqPdf('two.pdf'));
    Storage::disk('local')->assertExists($quote->stored_path);
    expect(rqWorkflow()->submit($manager, $request)->status)->toBe('submitted');
})->with(['maintenance', 'fixed_assets', 'electronics']);

it('takes at most three quotes, keeps them off the public disk and never drops below two once submitted', function () {
    $manager = rqManager();
    $request = rqWorkflow()->create($manager, rqHeader(['category' => 'electronics']), rqLines('900.00'));

    $first = rqWorkflow()->addQuote($manager, $request, rqPdf('a.pdf'));
    rqWorkflow()->addQuote($manager, $request, rqPdf('b.pdf'));
    rqWorkflow()->addQuote($manager, $request, rqPdf('c.pdf'));

    expect(fn () => rqWorkflow()->addQuote($manager, $request, rqPdf('d.pdf')))->toThrow(RequestException::class, 'at most 3')
        ->and($first->stored_path)->toStartWith('request-quotes/'.$request->id.'/');

    rqWorkflow()->submit($manager, $request);
    rqWorkflow()->removeQuote($manager, $request, $first->id);

    expect(fn () => rqWorkflow()->removeQuote($manager, $request, $request->quotes()->first()->id))->toThrow(RequestException::class, 'price quotes');

    $this->actingAs($manager)->get(route('entries.requests.quote', $request->quotes()->first()))->assertOk();
    $this->actingAs(rqManager('Other', 99))->get(route('entries.requests.quote', $request->quotes()->first()))->assertForbidden();
});

it('needs no quotes for a routine request', function () {
    expect(rqSubmitted()->status)->toBe('submitted');
});

it('only warns when a request goes over the budget line', function () {
    $manager = rqManager();
    $fm = rqUser(RoleId::FinancialManager, 'Fatima');
    GlBudgetLine::query()->whereKey('F2F|Maintenance')->update(['budget' => '200.00']);
    $request = rqSubmitted('300.00', $manager);

    $warnings = rqWorkflow()->budgetWarnings($request->load('lines'));

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0]['key'])->toBe('F2F|Maintenance')
        ->and(rqProgram($request)->status)->toBe('submitted')
        ->and(rqWorkflow()->approve($fm, $request)->status)->toBe('approved');
});

it('links an approved request to a transfer or payment voucher and refuses one that is not approved', function () {
    $approved = LedgerRequest::query()->create(['type' => 'monthly', 'project_code' => 'F2F', 'amount' => '900.00', 'currency' => 'USD', 'status' => 'approved', 'requested_by' => 3]);
    $submitted = LedgerRequest::query()->create(['type' => 'monthly', 'project_code' => 'F2F', 'amount' => '900.00', 'currency' => 'USD', 'status' => 'submitted', 'requested_by' => 3]);

    $transfer = fn (?int $requestId) => ledgerReceipt(['type' => VoucherType::Transfer, 'requestId' => $requestId], [
        new LineDraft('1121', 'USD', debit: 900, projectCode: 'F2F'),
        new LineDraft('1111', 'USD', credit: 900, projectCode: 'F2F'),
    ]);
    $check = fn ($draft) => count((new RequestRequiredAboveLimit)->check($draft, LedgerContext::forDraft($draft)));

    expect($check($transfer(null)))->toBe(0)
        ->and($check($transfer($approved->id)))->toBe(0)
        ->and($check($transfer($submitted->id)))->toBe(1)
        ->and($check($transfer(999)))->toBe(1);
});

it('takes a request through the screen: draft, submit, both approvals', function () {
    $manager = rqManager();
    $fm = rqUser(RoleId::FinancialManager, 'Fatima');
    $gm = rqUser(RoleId::GeneralManager, 'Gina');

    $page = Livewire::actingAs($manager)->test(Requests::class)
        ->call('newRequest')
        ->set('fProject', 'F2F')
        ->set('fMonth', '2026-11')
        ->set('fLines.0.budget_line', 'F2F|Maintenance')
        ->set('fLines.0.amount', '2500.00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('failures', []);

    $request = LedgerRequest::query()->firstOrFail();
    expect($request->status)->toBe('draft')->and((string) $request->amount)->toBe('2500.00');

    $page->assertSee('2,500.00')->call('submit')->assertSee(RequestStatus::Submitted->label());

    $program = rqUser(RoleId::ProgramManager, 'Paula');

    Livewire::actingAs($program)->withQueryParams(['request' => $request->id])->test(Requests::class)
        ->assertSee(__('requests.waiting_for', ['role' => RoleId::ProgramManager->label()]))
        ->call('approve')
        ->assertSee(__('requests.waiting_for', ['role' => RoleId::FinancialManager->label()]));

    Livewire::actingAs($fm)->withQueryParams(['request' => $request->id])->test(Requests::class)
        ->assertSee(__('requests.waiting_for', ['role' => RoleId::FinancialManager->label()]))
        ->call('approve')
        ->assertSee(__('requests.waiting_for', ['role' => RoleId::GeneralManager->label()]))
        ->assertDontSeeHtml('data-test="approve-request"');

    Livewire::actingAs($gm)->withQueryParams(['request' => $request->id])->test(Requests::class)
        ->call('approve')
        ->assertSee(RequestStatus::Approved->label());

    expect($request->refresh()->status)->toBe('approved');
});

it('rejects through the screen only with a reason', function () {
    $request = rqProgram(rqSubmitted());
    $fm = rqUser(RoleId::FinancialManager, 'Fatima');

    Livewire::actingAs($fm)->withQueryParams(['request' => $request->id])->test(Requests::class)
        ->call('openReject')
        ->set('rejectReason', '')
        ->call('reject')
        ->assertHasErrors('rejectReason')
        ->set('rejectReason', 'Not needed this month')
        ->call('reject')
        ->assertSee('Not needed this month');

    expect($request->refresh()->status)->toBe('rejected');
});

it('shows the request screen in the menu and both languages', function () {
    $manager = rqManager();

    $this->actingAs($manager)->get('/home')->assertSee(route('entries.requests'));
    $this->actingAs($manager)->withSession(['locale' => 'ar'])->get(route('entries.requests'))->assertOk();

    expect(array_keys(trans('requests', [], 'ar')))->toEqual(array_keys(trans('requests', [], 'en')));
});

// R4-3

it('puts the Program Manager before the Financial Manager in a monthly and an extra request', function (string $type) {
    $manager = rqManager();
    $fm = rqUser(RoleId::FinancialManager, 'Fatima');
    $program = rqUser(RoleId::ProgramManager, 'Paula');
    $request = rqWorkflow()->submit($manager, rqDraft($manager, '300.00', ['type' => $type]));

    expect(rqWorkflow()->pendingRole($request))->toBe(RoleId::ProgramManager)
        ->and(fn () => rqWorkflow()->approve($fm, $request))->toThrow(AuthorizationException::class);

    $afterProgram = rqWorkflow()->approve($program, $request);

    expect($afterProgram->status)->toBe('submitted')
        ->and(rqWorkflow()->pendingRole($afterProgram))->toBe(RoleId::FinancialManager)
        ->and(fn () => rqWorkflow()->approve($program, $afterProgram))->toThrow(AuthorizationException::class);

    $done = rqWorkflow()->approve($fm, $afterProgram);

    expect($done->status)->toBe('approved')
        ->and($done->approvals->pluck('role_id')->all())->toBe([RoleId::ProgramManager, RoleId::FinancialManager]);
})->with(['monthly', 'extra']);

it('lets the Program Manager reject a request and Head Office stand in only for the Financial Manager', function () {
    $manager = rqManager();
    $head = rqUser(RoleId::HeadOffice, 'Hana');
    $request = rqWorkflow()->submit($manager, rqDraft($manager));

    expect(fn () => rqWorkflow()->approve($head, $request))->toThrow(AuthorizationException::class);

    $rejected = rqWorkflow()->reject(rqUser(RoleId::ProgramManager, 'Paula'), $request, 'Not this month');

    expect($rejected->status)->toBe('rejected')
        ->and($rejected->approvals->first()->role_id)->toBe(RoleId::ProgramManager);
});

it('does not add a Program Manager step to vouchers', function () {
    expect(app(App\Services\Approvals\ApprovalChain::class)->stepsFor('100.00'))->toBe([RoleId::FinancialManager]);
});

// R4-10

it('works out the monthly deadline: the last working day of the month before, weekends skipped', function () {
    $deadline = app(SubmissionDeadline::class);
    $december = CarbonImmutable::parse('2026-12-01');

    // November 2026 ends on a Monday; Fridays and Saturdays are the weekend, so the five last working days are 30, 29, 26, 25 and 24.
    expect($deadline->dueBy($december)->toDateString())->toBe('2026-11-30')
        ->and($deadline->windowOpens($december)->toDateString())->toBe('2026-11-24')
        ->and($deadline->isLate($december, CarbonImmutable::parse('2026-11-30 17:00')))->toBeFalse()
        ->and($deadline->isLate($december, CarbonImmutable::parse('2026-12-01 08:00')))->toBeTrue();

    // A Friday or Saturday at the end of the month is not a working day: October 2026 ends on a Saturday, so the day is Thursday 29.
    expect($deadline->dueBy(CarbonImmutable::parse('2026-11-01'))->toDateString())->toBe('2026-10-29');
});

it('warns about a late monthly request but does not block it', function () {
    $manager = rqManager();
    Carbon\Carbon::setTestNow('2026-12-02 09:00:00');
    $request = rqWorkflow()->submit($manager, rqDraft($manager, header: ['month' => '2026-12']));

    expect($request->status)->toBe('submitted');

    Livewire::actingAs($manager)->test(Requests::class)
        ->set('requestId', $request->id)
        ->assertSeeHtml('data-test="late-warning"');

    Carbon\Carbon::setTestNow();
});

it('shows no late warning for a request submitted in time or for an extra request', function () {
    $manager = rqManager();
    Carbon\Carbon::setTestNow('2026-11-25 09:00:00');
    $onTime = rqWorkflow()->submit($manager, rqDraft($manager, header: ['month' => '2026-12']));
    Carbon\Carbon::setTestNow('2026-12-02 09:00:00');
    $extra = rqWorkflow()->submit($manager, rqDraft($manager, header: ['month' => '2026-12', 'type' => 'extra']));

    foreach ([$onTime, $extra] as $request) {
        Livewire::actingAs($manager)->test(Requests::class)
            ->set('requestId', $request->id)
            ->assertDontSeeHtml('data-test="late-warning"');
    }

    Carbon\Carbon::setTestNow();
});
