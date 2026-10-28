<?php

use App\Contracts\Approvable;
use App\Enums\RoleId;
use App\Models\User;
use App\Models\WebUserRole;
use App\Services\Approvals\ApprovalChain;
use App\Support\Navigation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    createFinanceSchema();

    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');
    createLegacyHomeSchema();
    createMasterDataSchema();

    (require database_path('migrations/2026_10_24_110000_add_request_and_transfer_columns_to_gl_vouchers.php'))->up();
    (require database_path('migrations/2026_10_25_090000_create_request_module_tables.php'))->up();
});

/**
 * The General Manager is web-only: such a user has a legacy role of Employee plus a `web_user_roles` row.
 */
function userWithRole(RoleId $role, string $name = 'Person'): User
{
    $legacy = $role->isWebOnly() ? RoleId::Employee : $role;
    $id = createFinanceUser($name, 'secret pass', $legacy->name, $legacy->value);

    if ($role->isWebOnly()) {
        WebUserRole::create(['user_id' => $id, 'role_id' => $role]);
    }

    return User::query()->findOrFail($id);
}

function voucher(int $preparedBy, string $amount = '100.00'): Approvable
{
    return new class($preparedBy, $amount) implements Approvable
    {
        public function __construct(private int $by, private string $amount) {}

        public function preparedBy(): int
        {
            return $this->by;
        }

        public function approvalAmount(): string
        {
            return $this->amount;
        }
    };
}

it('maps the legacy role ids', function () {
    expect(RoleId::HeadOffice->value)->toBe(2)
        ->and(RoleId::ProjectManager->value)->toBe(3)
        ->and(RoleId::FinancialManager->value)->toBe(4)
        ->and(RoleId::Accountant->value)->toBe(5)
        ->and(RoleId::Cashier->value)->toBe(6)
        ->and(RoleId::Employee->value)->toBe(7)
        ->and(RoleId::Donor->value)->toBe(8)
        ->and(RoleId::GeneralManager->isWebOnly())->toBeTrue();
});

it('shows each role only its screens', function (RoleId $role) {
    $user = userWithRole($role);
    $navigation = collect(config('navigation.groups'))->flatten();
    $permitted = collect(config("permissions.roles.{$role->value}"))
        ->filter(fn (array $actions) => in_array('view', $actions, true))
        ->keys();

    foreach ($navigation as $screen) {
        $response = $this->actingAs($user)->get(route(Navigation::routeName($screen)));

        $permitted->contains($screen) ? $response->assertOk() : $response->assertForbidden();
    }

    $this->actingAs($user)->get('/home')->assertOk();

    expect(collect(Navigation::visibleGroups($user))->flatten()->all())
        ->toEqualCanonicalizing($navigation->intersect($permitted)->values()->all());
})->with(fn () => RoleId::cases());

it('keeps the migration screens for Head Office only', function () {
    foreach (RoleId::cases() as $role) {
        $visible = collect(Navigation::visibleGroups(userWithRole($role, 'P'.$role->value)))->flatten();

        expect($visible->contains('dbload'))->toBe($role === RoleId::HeadOffice);
    }
});

it('answers 404 for an unknown screen and sends guests to sign in', function () {
    $this->actingAs(userWithRole(RoleId::HeadOffice))->get('/screens/nope')->assertNotFound();

    auth()->logout();
    $this->get('/home')->assertRedirect(route('login'));
});

it('refuses a user whose role is not one of the web roles', function () {
    $id = DB::table('user')->insertGetId(['Username' => 'Nobody', 'Password' => 'x', 'Role' => 1]);

    $this->actingAs(User::query()->findOrFail($id))->get('/home')->assertForbidden();
});

it('lets the accountant prepare and the financial manager approve, Head Office both', function () {
    $accountant = userWithRole(RoleId::Accountant, 'A');
    $manager = userWithRole(RoleId::FinancialManager, 'F');
    $head = userWithRole(RoleId::HeadOffice, 'H');

    expect(Gate::forUser($accountant)->allows('screen', ['vouchers', 'create']))->toBeTrue()
        ->and(Gate::forUser($accountant)->allows('screen', ['vouchers', 'approve']))->toBeFalse()
        ->and(Gate::forUser($manager)->allows('screen', ['vouchers', 'approve']))->toBeTrue()
        ->and(Gate::forUser($manager)->allows('screen', ['vouchers', 'create']))->toBeFalse()
        ->and(Gate::forUser($head)->allows('screen', ['vouchers', 'create']))->toBeTrue()
        ->and(Gate::forUser($head)->allows('screen', ['vouchers', 'approve']))->toBeTrue()
        ->and(Gate::forUser($head)->allows('screen', ['vouchers', 'bogus']))->toBeFalse();
});

it('never lets the preparer approve their own voucher', function (RoleId $role) {
    $user = userWithRole($role);

    expect(Gate::forUser($user)->allows('approve', voucher((int) $user->getKey())))->toBeFalse();
})->with([RoleId::FinancialManager, RoleId::HeadOffice]);

it('lets the financial manager approve a voucher prepared by someone else', function () {
    $manager = userWithRole(RoleId::FinancialManager);
    $accountant = userWithRole(RoleId::Accountant, 'A');

    expect(Gate::forUser($manager)->allows('approve', voucher((int) $accountant->getKey(), '500.00')))->toBeTrue()
        ->and(Gate::forUser($accountant)->allows('approve', voucher((int) $manager->getKey())))->toBeFalse();
});

it('holds the General Manager in the web table and leaves the legacy role alone', function () {
    $gm = userWithRole(RoleId::GeneralManager, 'CEO');

    expect($gm->roleIds())->toContain(RoleId::GeneralManager)
        ->and(Gate::forUser($gm)->allows('screen', ['requests', 'approve']))->toBeTrue()
        ->and(Gate::forUser($gm)->allows('screen', ['vouchers', 'approve']))->toBeTrue() // for the second step of a donor receipt only
        ->and(DB::table('role')->where('ID', '>=', 9)->count())->toBe(0);
});

it('needs the General Manager after the financial manager above USD 2,000', function () {
    $chain = new ApprovalChain;

    expect($chain->stepsFor('2000.00'))->toBe([RoleId::FinancialManager])
        ->and($chain->stepsFor('2000.01'))->toBe([RoleId::FinancialManager, RoleId::GeneralManager])
        ->and($chain->nextStep('5000.00', []))->toBe(RoleId::FinancialManager)
        ->and($chain->nextStep('5000.00', [RoleId::FinancialManager]))->toBe(RoleId::GeneralManager)
        ->and($chain->nextStep('5000.00', [RoleId::FinancialManager, RoleId::GeneralManager]))->toBeNull()
        ->and($chain->mayApproveNext('5000.00', [], [RoleId::GeneralManager]))->toBeFalse()
        ->and($chain->mayApproveNext('5000.00', [], [RoleId::FinancialManager]))->toBeTrue()
        ->and($chain->mayApproveNext('5000.00', [RoleId::FinancialManager], [RoleId::HeadOffice]))->toBeFalse()
        ->and($chain->mayApproveNext('5000.00', [RoleId::FinancialManager], [RoleId::GeneralManager]))->toBeTrue()
        ->and($chain->mayApproveNext('100.00', [RoleId::FinancialManager], [RoleId::GeneralManager]))->toBeFalse();
});

it('guards every signed-in route with a screen permission', function () {
    $guarded = 0;

    foreach (app('router')->getRoutes() as $route) {
        $middleware = $route->gatherMiddleware();

        if (! in_array('auth', $middleware, true)) {
            continue;
        }

        $guarded++;
        expect(collect($middleware)->contains(fn ($m) => is_string($m) && $m === 'screen' || str_starts_with($m, 'screen:')))
            ->toBeTrue("{$route->uri()} has no screen middleware");
    }

    expect($guarded)->toBeGreaterThan(0);
});
