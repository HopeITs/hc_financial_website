<?php

use App\Enums\NotificationKind;
use App\Enums\RoleId;
use App\Enums\VoucherSource;
use App\Livewire\NotificationBell;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\User;
use App\Models\WebNotification;
use App\Models\WebUserRole;
use App\Services\Ledger\PostVoucher;
use App\Services\Ledger\VoucherApprovals;
use App\Services\Requests\RequestWorkflow;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();

    Schema::connection('legacy')->create('role_currency_limit', function ($table) {
        $table->integer('Role_ID');
        $table->integer('TillType_ID');
        $table->decimal('Approval_Limit', 18, 2);
    });
    DB::connection('legacy')->table('tilltypes')->insert(['ID' => 2, 'Name' => 'USD']);
    DB::connection('legacy')->table('role_currency_limit')->insert(['Role_ID' => RoleId::FinancialManager->value, 'TillType_ID' => 2, 'Approval_Limit' => 6818]);

    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    // This test's own project and budget line; the chart seeder adds no projects.
    GlProject::query()->create([
        'code' => 'F2F', 'name' => 'Family to Family 2026', 'donor' => 'Caritas Poland', 'restriction' => 'With donor restrictions',
        'city' => 'Aleppo', 'donor_currency' => 'USD', 'donor_rate' => 1, 'is_general_account' => false, 'legacy_account_id' => 77,
    ]);
    GlBudgetLine::query()->create([
        'key' => 'F2F|Maintenance', 'project_code' => 'F2F', 'donor' => 'Caritas Poland', 'name' => 'Maintenance',
        'account_code' => '5405', 'budget' => 10000, 'currency' => 'USD',
    ]);
    DB::connection('legacy')->table('account')->insert(['ID' => 77, 'SubProject_ID' => 7, 'Name' => 'F2F']);
});

function ntUser(RoleId $role, string $name): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    if ($role === RoleId::FinancialManager) {
        DB::connection('legacy')->table('user_accounts')->insert(['user_id' => $id, 'subproject_id' => 7]);
    }

    return User::query()->findOrFail($id);
}

function ntManager(): User
{
    $user = ntUser(RoleId::ProjectManager, 'Pam');
    DB::table('web_project_managers')->insert(['user_id' => $user->getKey(), 'account_id' => 77]);

    return $user;
}

/** @return list<array{kind: string, subject: string, data: array<string, string>}> */
function ntBell(User $user): array
{
    return WebNotification::query()->for($user)->orderBy('id')->get()
        ->map(fn (WebNotification $n): array => ['kind' => $n->kind->value, 'subject' => $n->subject, 'data' => $n->data])->all();
}

it('tells whoever may approve a new voucher, and nobody else', function () {
    $accountant = ntUser(RoleId::Accountant, 'Acc');
    $manager = ntUser(RoleId::FinancialManager, 'Fm');
    $cashier = ntUser(RoleId::Cashier, 'Cas');

    $voucher = app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => $accountant->getKey()]));

    expect(ntBell($manager))->toBe([['kind' => 'voucher_pending', 'subject' => $voucher->id, 'data' => ['amount' => '100.00']]])
        ->and(ntBell($accountant))->toBe([])
        ->and(ntBell($cashier))->toBe([]);
});

it('tells the preparer when the voucher is approved, or rejected with the reason', function () {
    $accountant = ntUser(RoleId::Accountant, 'Acc');
    $manager = ntUser(RoleId::FinancialManager, 'Fm');
    $approvals = app(VoucherApprovals::class);

    $approved = app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => $accountant->getKey()]));
    $approvals->approve($approved, $manager);

    $rejected = app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => $accountant->getKey()]));
    $approvals->reject($rejected, $manager, 'فاتورة ناقصة');

    expect(ntBell($accountant))->toBe([
        ['kind' => 'voucher_approved', 'subject' => $approved->id, 'data' => []],
        ['kind' => 'voucher_rejected', 'subject' => $rejected->id, 'data' => ['reason' => 'فاتورة ناقصة']],
    ]);
});

it('tells no one about a voucher migrated from the Windows program', function () {
    $manager = ntUser(RoleId::FinancialManager, 'Fm');

    app(PostVoucher::class)->post(ledgerReceipt(['preparedBy' => 0, 'source' => VoucherSource::Migration, 'legacyActionId' => 501]));

    expect(ntBell($manager))->toBe([]);
});

it('walks a request through the program manager and the financial manager, telling each in turn', function () {
    $pm = ntManager();
    $program = ntUser(RoleId::ProgramManager, 'Paula');
    $finance = ntUser(RoleId::FinancialManager, 'Fm');
    $workflow = app(RequestWorkflow::class);

    $request = $workflow->submit($pm, $workflow->create($pm, ['project' => 'F2F', 'type' => 'monthly', 'month' => '2026-11'], [['budget_line' => 'F2F|Maintenance', 'amount' => '300.00', 'currency' => 'USD']]));
    $pending = ['kind' => 'request_pending', 'subject' => (string) $request->id, 'data' => ['project' => 'F2F', 'amount' => '300.00']];

    expect(ntBell($program))->toBe([$pending])->and(ntBell($finance))->toBe([]);

    $workflow->approve($program, $request);
    expect(ntBell($finance))->toBe([$pending]);

    $workflow->approve($finance, $request->refresh());
    expect(ntBell($pm))->toBe([['kind' => 'request_approved', 'subject' => (string) $request->id, 'data' => ['project' => 'F2F', 'amount' => '300.00']]]);
});

it('tells the project manager when a request is rejected, with the reason', function () {
    $pm = ntManager();
    $program = ntUser(RoleId::ProgramManager, 'Paula');
    $workflow = app(RequestWorkflow::class);

    $request = $workflow->submit($pm, $workflow->create($pm, ['project' => 'F2F', 'type' => 'monthly', 'month' => '2026-11'], [['budget_line' => 'F2F|Maintenance', 'amount' => '300.00', 'currency' => 'USD']]));
    $workflow->reject($program, $request, 'Too early');

    expect(ntBell($pm))->toBe([['kind' => 'request_rejected', 'subject' => (string) $request->id, 'data' => ['project' => 'F2F', 'amount' => '300.00', 'reason' => 'Too early']]]);
});

it('shows a person only their own notifications, opens one and marks them read', function () {
    $me = ntUser(RoleId::Accountant, 'Acc');
    $other = ntUser(RoleId::Accountant, 'Ben');
    $mine = WebNotification::query()->create(['user_id' => $me->getKey(), 'kind' => NotificationKind::VoucherRejected, 'subject' => 'CRV-2026-0001', 'data' => ['reason' => 'No invoice']]);
    WebNotification::query()->create(['user_id' => $me->getKey(), 'kind' => NotificationKind::VoucherApproved, 'subject' => 'CRV-2026-0002', 'data' => []]);
    $theirs = WebNotification::query()->create(['user_id' => $other->getKey(), 'kind' => NotificationKind::VoucherApproved, 'subject' => 'CRV-2026-0099', 'data' => []]);

    $bell = Livewire::actingAs($me)->test(NotificationBell::class)
        ->assertSeeHtml('data-test="bell-count">2<')
        ->assertSee('CRV-2026-0001')
        ->assertSee('No invoice')
        ->assertDontSee('CRV-2026-0099');

    $bell->call('open', $mine->id)->assertRedirect($mine->url());
    expect($mine->refresh()->read_at)->not->toBeNull()
        ->and($mine->url())->toContain('voucher=CRV-2026-0001')->toContain('rejected=1');

    Livewire::actingAs($me)->test(NotificationBell::class)->call('open', $theirs->id)->assertNotFound();

    Livewire::actingAs($me)->test(NotificationBell::class)->assertSeeHtml('data-test="bell-count">1<')
        ->call('markAllRead')->assertDontSeeHtml('data-test="bell-count"');
    expect(WebNotification::query()->for($me)->whereNull('read_at')->count())->toBe(0)
        ->and($theirs->refresh()->read_at)->toBeNull();
});

it('puts the bell in the top bar', function () {
    $this->actingAs(ntUser(RoleId::HeadOffice, 'Ho'))->get('/bridge/decisions')->assertOk()->assertSee('data-test="notification-bell"', false);
});

it('has every notification text in English and Arabic', function () {
    $keys = fn (string $locale) => collect(require lang_path("$locale/notifications.php"))->dot()->keys()->sort()->values()->all();

    expect($keys('ar'))->toBe($keys('en'))
        ->and(collect(NotificationKind::cases())->every(fn (NotificationKind $kind) => in_array('kinds.'.$kind->value, $keys('en'), true)))->toBeTrue();
});
