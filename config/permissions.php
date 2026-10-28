<?php

use App\Enums\RoleId;

/*
|--------------------------------------------------------------------------
| Role permissions
|--------------------------------------------------------------------------
|
| role id => screen slug => actions (view, create, edit, delete, approve).
| A role sees only the screens listed for it. Slugs are those of config/navigation.php,
| plus `home` (the landing page), `requests` (the project managers' requests) and `project-money`
| (whether the project pages show balances and amounts).
|
| The accountant prepares (create, edit), the financial manager approves,
| Head Office does both. The General Manager only approves requests, after the financial manager.
|
*/

$view = ['view'];
$prepare = ['view', 'create', 'edit'];
$all = ['view', 'create', 'edit', 'delete', 'approve'];

$everyScreen = array_merge(...array_values((require __DIR__.'/navigation.php')['groups']));

$screens = static fn (array $slugs, array $actions): array => array_fill_keys($slugs, $actions);

$reports = ['rexp', 'rcash', 'rbl', 'rrev', 'rfixed', 'rattach', 'tb', 'projbal', 'statement', 'statements', 'projects', 'recon', 'uncomp', 'xreserve', 'advances', 'loans'];
$projectScreens = ['projwin', 'allaccounts', 'budgetlines', 'blcats', 'lifetime', 'programs'];
$treasury = ['tills', 'treasury', 'alloc', 'cities', 'currency', 'denoms'];
$entries = ['single', 'multi', 'multiatt', 'payments', 'vouchers', 'bonds'];

return [

    // Approvals above this amount (USD) need the General Manager after the financial manager.
    'general_manager_threshold_usd' => '2000.00',

    'roles' => [

        RoleId::HeadOffice->value => $screens(['home', 'requests', 'project-money', ...$everyScreen], $all),

        RoleId::FinancialManager->value => array_merge(
            $screens(['home', 'chart', 'project-money', ...$projectScreens, ...$treasury, ...$reports, 'donors', 'employees', 'salaries', 'users', 'log', 'single', 'multi', 'multiatt', 'payments', 'bonds'], $view),
            $screens(['vouchers', 'approval', 'requests', 'alloc'], ['view', 'approve']),
            $screens(['limits'], ['view', 'edit']),
            $screens(['programs'], ['view', 'create']),
        ),

        RoleId::Accountant->value => array_merge(
            $screens(['home', 'project-money', ...$projectScreens, ...$reports, 'tills', 'treasury', 'cities', 'currency', 'denoms', 'donors', 'employees', 'requests'], $view),
            $screens(['chart', ...$entries, 'alloc', 'salaries'], $prepare),
        ),

        RoleId::ProjectManager->value => array_merge(
            $screens(['home', 'projwin', 'budgetlines', 'programs', 'projects', 'rexp', 'rbl', 'advances', 'loans'], $view),
            $screens(['requests'], $prepare),
        ),

        RoleId::Cashier->value => array_merge(
            $screens(['home', 'tills', 'treasury', 'rcash'], $view),
            $screens(['single'], ['view', 'create']),
        ),

        RoleId::Employee->value => $screens(['home'], $view),

        RoleId::Donor->value => $screens(['home'], $view),

        RoleId::GeneralManager->value => array_merge(
            $screens(['home', 'chart', 'project-money', ...$projectScreens, ...$treasury, ...$reports, 'vouchers', 'approval'], $view),
            $screens(['requests'], ['view', 'approve']),
        ),

    ],

];
