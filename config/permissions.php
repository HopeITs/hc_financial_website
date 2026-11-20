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
| Head Office does both. The General Manager approves requests and donor receipts after the financial manager; the Program Manager approves requests first.
|
*/

$view = ['view'];
$prepare = ['view', 'create', 'edit'];
$all = ['view', 'create', 'edit', 'delete', 'approve'];

$everyScreen = array_merge(...array_values((require __DIR__.'/navigation.php')['groups']));

$screens = static fn (array $slugs, array $actions): array => array_fill_keys($slugs, $actions);

$reports = ['rexp', 'rcash', 'rdonor', 'rrev', 'rfixed', 'rattach', 'tb', 'statement', 'statements', 'recon', 'mapprovals', 'uncomp', 'xreserve', 'advances', 'loans'];
$treasury = ['tills', 'treasury', 'alloc', 'transfers', 'cities', 'currency', 'denoms'];
$monthEnd = ['monthend'];
// `single` is the payment voucher and `receipt` the receipt voucher. `multiatt` and `bonds` are old screens that left
// the menu; their keys stay until their code is removed.
$entries = ['single', 'receipt', 'journal', 'advance', 'multi', 'multiatt', 'payments', 'loanfunds', 'vouchers', 'bonds'];

return [

    // Approvals above this amount (USD) need the General Manager after the financial manager.
    'general_manager_threshold_usd' => '2000.00',

    'roles' => [

        RoleId::HeadOffice->value => $screens(['home', 'requests', 'project-money', 'multiatt', 'bonds', ...$everyScreen], $all),

        RoleId::FinancialManager->value => array_merge(
            $screens(['home', 'chart', 'project-money', ...$treasury, ...$reports, 'employees', 'salaries', 'users', 'log', 'auditlog', 'single', 'receipt', 'advance', 'multi', 'multiatt', 'payments', 'bonds'], $view),
            $screens(['vouchers', 'approval', 'requests'], ['view', 'approve']),
            // The Financial Manager approves the daily rates, and a rate they type themselves is approved at once.
            $screens(['alloc'], ['view', 'create', 'approve']),
            // A donor with no agreement can be deleted (finance request of 2026-11-20).
            $screens(['donors'], [...$prepare, 'delete']),
            $screens(['contracts'], ['view', 'create', 'edit', 'approve', 'delete']),
            // Only the Financial Manager exchanges an instalment fund's pounds with the organisation (2026-11-22).
            $screens(['loanfunds'], ['view', 'approve']),
            $screens(['currency'], $prepare),
            $screens($monthEnd, ['view', 'edit', 'approve']),
        ),

        RoleId::Accountant->value => array_merge(
            $screens(['home', 'project-money', ...$reports, 'cities', 'currency', 'denoms', 'donors', 'employees', 'requests'], $view),
            // `tills` is the daily cash count, `treasury` the liquidity position with the month's revaluation.
            $screens(['chart', ...$entries, 'alloc', 'transfers', 'tills', 'treasury', 'salaries', 'contracts'], $prepare),
        ),

        RoleId::ProjectManager->value => array_merge(
            $screens(['home', 'rexp', 'advances', 'loans', 'donors', 'contracts'], $view),
            $screens(['requests'], $prepare),
        ),

        RoleId::Cashier->value => array_merge(
            $screens(['home', 'treasury', 'rcash'], $view),
            $screens(['single', 'receipt', 'advance', 'tills'], ['view', 'create']),
        ),

        RoleId::Employee->value => $screens(['home'], $view),

        RoleId::Donor->value => $screens(['home'], $view),

        RoleId::GeneralManager->value => array_merge(
            $screens(['home', 'chart', 'project-money', ...$treasury, ...$reports, 'donors'], $view),
            $screens(['requests', 'vouchers', 'approval', 'contracts'], ['view', 'approve']),
        ),

        RoleId::ProgramManager->value => array_merge(
            $screens(['home', 'project-money', 'donors', 'contracts'], $view),
            $screens(['requests'], ['view', 'approve']),
        ),

    ],

];
