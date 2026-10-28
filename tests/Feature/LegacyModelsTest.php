<?php

use App\Models\Legacy\Account;
use App\Models\Legacy\Action;
use App\Models\Legacy\ActionAttachment;
use App\Models\Legacy\ActionPayment;
use App\Models\Legacy\Beneficiary;
use App\Models\Legacy\Budget;
use App\Models\Legacy\BudgetLine;
use App\Models\Legacy\CashAccount;
use App\Models\Legacy\CashBalance;
use App\Models\Legacy\City;
use App\Models\Legacy\Donor;
use App\Models\Legacy\Employee;
use App\Models\Legacy\EmployeeSalary;
use App\Models\Legacy\EmployeeSubProject;
use App\Models\Legacy\LegacyModel;
use App\Models\Legacy\Line;
use App\Models\Legacy\Payment;
use App\Models\Legacy\Project;
use App\Models\Legacy\Rate;
use App\Models\Legacy\SubProject;
use App\Models\Legacy\Till;
use App\Models\Legacy\TillType;
use App\Models\Legacy\TreeView;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every table gets one row whose columns are all 1, so every foreign key points at the row
 * of the related table and each relation finds exactly one record.
 */
const LEGACY_TEST_TABLES = [
    'action' => ['ID', 'Till_ID', 'ObserveTill_ID', 'currency_tilltype_id', 'BudgetLine_ID', 'Donor_ID', 'cashaccount_id', 'Rate_ID', 'Employee_Salary_ID'],
    'action_payment' => ['id', 'action_id', 'payment_id'],
    'action_attachments' => ['id', 'action_id', 'attach_name'],
    'payment' => ['ID', 'Beneficiary_ID'],
    'beneficiary' => ['ID', 'Account_ID', 'currency_tilltype_id'],
    'account' => ['ID', 'city_id', 'SubProject_ID', 'ObserveAccount_ID'],
    'account_donor' => ['id', 'Account_id', 'Donor_id'],
    'till' => ['ID', 'Account_ID', 'Type_ID'],
    'tilltypes' => ['ID', 'Name'],
    'cashaccount' => ['id', 'city_id', 'currency_tilltype_id'],
    'donor' => ['ID', 'Name'],
    'line' => ['ID', 'Name'],
    'budget_line' => ['ID', 'Budget_ID', 'Line_ID'],
    'budget' => ['ID', 'Account_ID', 'Budget_Type_ID'],
    'project' => ['ID', 'Name'],
    'line_project' => ['id', 'line_id', 'project_id'],
    'subproject' => ['ID', 'Project_ID', 'City_ID'],
    'subproject_donor' => ['SubProject_ID', 'Donor_ID'],
    'city' => ['ID', 'Name'],
    'employee' => ['id', 'FirstName'],
    'employee_subproject' => ['id', 'employee_id', 'subproject_id', 'line_id'],
    'employee_salary' => ['id', 'employee_subproject_id'],
    'rate' => ['ID', 'Account_ID', 'FromTill', 'ToTill', 'ObserveRate_ID'],
    'role' => ['ID', 'Name'],
    'cash_balances' => ['Account_ID', 'CashAccount_id', 'Account_Net'],
    'tree_view' => ['CityID', 'ProjectID', 'SubprojectID', 'AccountID'],
];

beforeEach(function () {
    config(['database.connections.legacy' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy');

    foreach (LEGACY_TEST_TABLES as $table => $columns) {
        Schema::connection('legacy')->create($table, function ($blueprint) use ($columns) {
            foreach ($columns as $column) {
                $blueprint->text($column)->nullable();
            }
        });
        DB::connection('legacy')->table($table)->insert(array_fill_keys($columns, 1));
    }

    // The role table is on the default connection (it is the login table); a relation to it
    // from a legacy model runs on the legacy connection, so it must exist there too.
    createFinanceSchema();
    DB::table('role')->insert(['ID' => 1, 'Name' => 'Financial manager']);
});

/**
 * Reads the single row of a model: by key when it has one, otherwise the only row.
 */
function legacyRow(string $model): LegacyModel
{
    /** @var LegacyModel $row */
    $row = (new $model)->getKeyName() === null ? $model::query()->firstOrFail() : $model::query()->findOrFail(1);

    return $row;
}

dataset('legacy relations', [
    'Action.till' => [Action::class, 'till', Till::class],
    'Action.observeTill' => [Action::class, 'observeTill', Till::class],
    'Action.tillType' => [Action::class, 'tillType', TillType::class],
    'Action.budgetLine' => [Action::class, 'budgetLine', BudgetLine::class],
    'Action.donor' => [Action::class, 'donor', Donor::class],
    'Action.cashAccount' => [Action::class, 'cashAccount', CashAccount::class],
    'Action.rate' => [Action::class, 'rate', Rate::class],
    'Action.employeeSalary' => [Action::class, 'employeeSalary', EmployeeSalary::class],
    'Action.attachments' => [Action::class, 'attachments', ActionAttachment::class],
    'Action.payments' => [Action::class, 'payments', Payment::class],
    'ActionPayment.action' => [ActionPayment::class, 'action', Action::class],
    'ActionPayment.payment' => [ActionPayment::class, 'payment', Payment::class],
    'ActionAttachment.action' => [ActionAttachment::class, 'action', Action::class],
    'Payment.beneficiary' => [Payment::class, 'beneficiary', Beneficiary::class],
    'Payment.actionPayments' => [Payment::class, 'actionPayments', ActionPayment::class],
    'Beneficiary.account' => [Beneficiary::class, 'account', Account::class],
    'Beneficiary.tillType' => [Beneficiary::class, 'tillType', TillType::class],
    'Beneficiary.payments' => [Beneficiary::class, 'payments', Payment::class],
    'Account.city' => [Account::class, 'city', City::class],
    'Account.subProject' => [Account::class, 'subProject', SubProject::class],
    'Account.observeAccount' => [Account::class, 'observeAccount', Account::class],
    'Account.tills' => [Account::class, 'tills', Till::class],
    'Account.donors' => [Account::class, 'donors', Donor::class],
    'Till.account' => [Till::class, 'account', Account::class],
    'Till.type' => [Till::class, 'type', TillType::class],
    'TillType.tills' => [TillType::class, 'tills', Till::class],
    'CashAccount.city' => [CashAccount::class, 'city', City::class],
    'CashAccount.tillType' => [CashAccount::class, 'tillType', TillType::class],
    'Donor.accounts' => [Donor::class, 'accounts', Account::class],
    'Donor.subProjects' => [Donor::class, 'subProjects', SubProject::class],
    'Line.budgetLines' => [Line::class, 'budgetLines', BudgetLine::class],
    'BudgetLine.budget' => [BudgetLine::class, 'budget', Budget::class],
    'BudgetLine.line' => [BudgetLine::class, 'line', Line::class],
    'Budget.account' => [Budget::class, 'account', Account::class],
    'Budget.type' => [Budget::class, 'type', TillType::class],
    'Budget.budgetLines' => [Budget::class, 'budgetLines', BudgetLine::class],
    'Project.subProjects' => [Project::class, 'subProjects', SubProject::class],
    'Project.lines' => [Project::class, 'lines', Line::class],
    'SubProject.project' => [SubProject::class, 'project', Project::class],
    'SubProject.city' => [SubProject::class, 'city', City::class],
    'SubProject.accounts' => [SubProject::class, 'accounts', Account::class],
    'SubProject.donors' => [SubProject::class, 'donors', Donor::class],
    'City.subProjects' => [City::class, 'subProjects', SubProject::class],
    'City.accounts' => [City::class, 'accounts', Account::class],
    'City.cashAccounts' => [City::class, 'cashAccounts', CashAccount::class],
    'Employee.assignments' => [Employee::class, 'assignments', EmployeeSubProject::class],
    'EmployeeSubProject.employee' => [EmployeeSubProject::class, 'employee', Employee::class],
    'EmployeeSubProject.subProject' => [EmployeeSubProject::class, 'subProject', SubProject::class],
    'EmployeeSubProject.line' => [EmployeeSubProject::class, 'line', Line::class],
    'EmployeeSubProject.salaries' => [EmployeeSubProject::class, 'salaries', EmployeeSalary::class],
    'EmployeeSalary.assignment' => [EmployeeSalary::class, 'assignment', EmployeeSubProject::class],
    'Rate.account' => [Rate::class, 'account', Account::class],
    'Rate.fromTillType' => [Rate::class, 'fromTillType', TillType::class],
    'Rate.toTillType' => [Rate::class, 'toTillType', TillType::class],
    'Rate.observeRate' => [Rate::class, 'observeRate', Rate::class],
    'CashBalance.account' => [CashBalance::class, 'account', Account::class],
    'CashBalance.cashAccount' => [CashBalance::class, 'cashAccount', CashAccount::class],
    'TreeView.city' => [TreeView::class, 'city', City::class],
    'TreeView.project' => [TreeView::class, 'project', Project::class],
    'TreeView.subProject' => [TreeView::class, 'subProject', SubProject::class],
    'TreeView.account' => [TreeView::class, 'account', Account::class],
]);

dataset('legacy models', [
    Action::class, ActionPayment::class, ActionAttachment::class, Payment::class, Beneficiary::class,
    Account::class, Till::class, TillType::class, CashAccount::class, Donor::class, Line::class,
    BudgetLine::class, Budget::class, Project::class, SubProject::class, City::class, Employee::class,
    EmployeeSubProject::class, EmployeeSalary::class, Rate::class,
    CashBalance::class, TreeView::class,
]);

it('loads a row of each legacy model through the legacy connection', function (string $model) {
    $row = legacyRow($model);

    expect($row)->toBeInstanceOf($model)
        ->and($row->getConnectionName())->toBe('legacy')
        ->and($row->usesTimestamps())->toBeFalse();
})->with('legacy models');

it('follows the relation of each legacy model', function (string $model, string $relation, string $related) {
    $result = legacyRow($model)->{$relation};

    if ((new $model)->{$relation}() instanceof BelongsTo) {
        expect($result)->toBeInstanceOf($related);
    } else {
        expect($result)->toHaveCount(1)->and($result->first())->toBeInstanceOf($related);
    }
})->with('legacy relations');

it('refuses to write a legacy model', function (string $model) {
    $row = legacyRow($model);

    expect(fn () => $row->save())->toThrow(LogicException::class)
        ->and(fn () => $row->update(['x' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $row->delete())->toThrow(LogicException::class)
        ->and(fn () => $row->forceDelete())->toThrow(LogicException::class)
        ->and(fn () => $model::forceCreate(['x' => 1]))->toThrow(LogicException::class);
})->with('legacy models');

it('refuses the writes that skip save', function () {
    $city = legacyRow(City::class);

    expect(fn () => $city->increment('ID'))->toThrow(LogicException::class)
        ->and(fn () => $city->push())->toThrow(LogicException::class)
        ->and(DB::connection('legacy')->table('city')->count())->toBe(1);
});

it('leaves the profile picture out of a default user query', function () {
    Schema::table('user', fn ($table) => $table->binary('ProfilePicture')->nullable());
    $id = createFinanceUser('maya', 'secret');
    DB::table('user')->where('ID', $id)->update(['ProfilePicture' => 'blob']);

    $user = User::query()->findOrFail($id);

    expect($user->getAttributes())->not->toHaveKey('ProfilePicture')
        ->and($user->username)->toBe('maya')
        ->and($user->getAuthPassword())->not->toBeEmpty()
        ->and(User::query()->select('ProfilePicture')->first()->getAttributes())->toHaveKey('ProfilePicture');
});
