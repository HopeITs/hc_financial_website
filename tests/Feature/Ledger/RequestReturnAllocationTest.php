<?php

use App\Enums\RequestStatus;
use App\Enums\RoleId;
use App\Models\GlAccount;
use App\Models\GlBudgetLine;
use App\Models\GlProject;
use App\Models\RequestAudit;
use App\Models\User;
use App\Models\Voucher;
use App\Models\WebUserRole;
use App\Services\Ledger\BuiltTransfer;
use App\Services\Ledger\TransferBuilder;
use App\Services\Ledger\VoucherDraft;
use App\Services\Requests\RequestException;
use App\Services\Requests\RequestWorkflow;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    createFinanceSchema();
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();

    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
    (require database_path('migrations/2026_11_02_090000_add_return_reason_to_gl_requests.php'))->up();
    (new ChartOfAccountsSeeder)->run();

    GlProject::query()->create(['code' => 'F2F', 'name' => 'F2F', 'restriction' => 'restricted', 'city' => 'Aleppo', 'legacy_account_id' => 77]);
    GlBudgetLine::query()->create(['key' => 'F2F|Maintenance', 'project_code' => 'F2F', 'name' => 'Maintenance', 'account_code' => GlAccount::query()->where('class', 'expenses')->where('level', 'posting')->value('code'), 'budget' => 5000, 'currency' => 'USD']);
    DB::connection('legacy')->table('account')->insert(['ID' => 77, 'SubProject_ID' => 7, 'Name' => 'F2F']);
});

function rtUser(RoleId $role, string $name): User
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

/** A submitted request of USD 300 by a project manager linked to F2F. */
function rtSubmitted(): array
{
    $manager = rtUser(RoleId::ProjectManager, 'Pam');
    DB::table('web_project_managers')->insert(['user_id' => $manager->getKey(), 'account_id' => 77]);
    $workflow = app(RequestWorkflow::class);
    $request = $workflow->create($manager, ['project' => 'F2F', 'type' => 'monthly', 'month' => '2026-11', 'category' => null, 'description' => 'November'], [['budget_line' => 'F2F|Maintenance', 'amount' => '300.00', 'currency' => 'USD']]);

    return [$manager, $workflow->submit($manager, $request)];
}

it('returns a request with a reason, lets the requester edit and resubmit, and starts the approvals again', function () {
    [$manager, $request] = rtSubmitted();
    $workflow = app(RequestWorkflow::class);
    $program = rtUser(RoleId::ProgramManager, 'Paula');

    $workflow->approve($program, $request);
    $fm = rtUser(RoleId::FinancialManager, 'Fatima');

    expect(fn () => $workflow->returnForChanges($fm, $request, '  '))->toThrow(RequestException::class);

    $returned = $workflow->returnForChanges($fm, $request, 'Add the second quote');

    expect($returned->status)->toBe(RequestStatus::Returned->value)
        ->and($returned->return_reason)->toBe('Add the second quote')
        ->and($returned->approvals()->count())->toBe(0)
        ->and($workflow->isEditable($returned))->toBeTrue()
        ->and(RequestAudit::query()->where('request_id', $request->id)->where('action', 'return')->value('reason'))->toBe('Add the second quote');

    $again = $workflow->submit($manager, $returned);

    expect($again->status)->toBe(RequestStatus::Submitted->value)
        ->and($workflow->pendingRole($again))->toBe(RoleId::ProgramManager);
});

it('keeps the request approved and says what is missing when the allocation cannot be built', function () {
    [, $request] = rtSubmitted();
    $workflow = app(RequestWorkflow::class);

    $workflow->approve(rtUser(RoleId::ProgramManager, 'Paula'), $request);
    $approved = $workflow->approve(rtUser(RoleId::FinancialManager, 'Fatima'), $request);

    expect($approved->status)->toBe(RequestStatus::Approved->value)
        ->and(Voucher::query()->where('request_id', $request->id)->count())->toBe(0)
        ->and(RequestAudit::query()->where('request_id', $request->id)->where('action', 'allocation_missing')->exists())->toBeTrue();
});

it('creates the pending allocation voucher linked to the request on final approval', function () {
    $this->mock(TransferBuilder::class, fn ($mock) => $mock->shouldReceive('build')->once()->andReturnUsing(
        function () {
            $draft = ledgerReceipt();

            return new BuiltTransfer(new VoucherDraft($draft->type, $draft->date, $draft->preparedBy, $draft->lines, $draft->description, transferKind: 'usd_to_project'), []);
        },
    ));

    [, $request] = rtSubmitted();
    $workflow = app(RequestWorkflow::class);

    $workflow->approve(rtUser(RoleId::ProgramManager, 'Paula'), $request);
    $workflow->approve(rtUser(RoleId::FinancialManager, 'Fatima'), $request);

    $voucher = Voucher::query()->where('request_id', $request->id)->first();

    expect($voucher)->not->toBeNull()
        ->and($voucher->status->value)->toBe('pending_approval')
        ->and(RequestAudit::query()->where('request_id', $request->id)->where('action', 'allocation')->exists())->toBeTrue()
        ->and($workflow->allocate(rtUser(RoleId::FinancialManager, 'Fay'), $request->refresh()))->toBe([])
        ->and(Voucher::query()->where('request_id', $request->id)->count())->toBe(1);
});
