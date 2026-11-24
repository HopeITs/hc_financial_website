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

// `reports` is the reports' own page (2026-11-25).
// The old program's monthly approvals left on 2026-11-26 (role review); Head Office keeps every screen.
$reports = ['reports', 'rexp', 'rcash', 'rdonor', 'rrev', 'rfixed', 'rattach', 'tb', 'statement', 'statements', 'recon', 'uncomp', 'xreserve', 'advances', 'loans'];
$treasury = ['tills', 'treasury', 'alloc', 'transfers', 'currency'];
$monthEnd = ['monthend'];
// `single` is the payment voucher and `receipt` the receipt voucher. The old `multiatt` and `bonds` screens left the
// menu and stay with Head Office only until their code is removed (role review of 2026-11-26).
$entries = ['single', 'receipt', 'journal', 'advance', 'multi', 'microfiles', 'payments', 'loanfunds', 'vouchers', 'purchases', 'recurring', 'revenues'];
// The micro-projects section's own page, for everyone who works on micro projects (2026-11-23).
$microHome = ['microhome'];
// The financial operations' page (2026-11-24): each of its parts shows only to those who see its screen.
$operations = 'operations';
// Everything waiting for one's approval in one place (2026-11-25), for those who approve.
$inbox = 'inbox';

return [

    // Approvals above this amount (USD) need the General Manager after the financial manager.
    'general_manager_threshold_usd' => '2000.00',

    'roles' => [

        RoleId::HeadOffice->value => $screens(['home', 'requests', 'project-money', 'multiatt', 'bonds', ...$everyScreen], $all),

        RoleId::FinancialManager->value => array_merge(
            $screens(['home', 'chart', 'project-money', ...$treasury, ...$reports, 'salaries', 'users', 'auditlog', 'opening', 'single', 'receipt', 'advance', 'multi', 'payments', 'recurring', 'revenues', $operations, $inbox, ...$microHome], $view),
            $screens(['vouchers', 'approval', 'requests'], ['view', 'approve']),
            // The Financial Manager approves the daily rates, and a rate they type themselves is approved at once.
            $screens(['alloc'], ['view', 'create', 'approve']),
            // A donor with no agreement can be deleted (finance request of 2026-11-20).
            $screens(['donors'], [...$prepare, 'delete']),
            $screens(['contracts'], ['view', 'create', 'edit', 'approve', 'delete']),
            // Only the Financial Manager exchanges an instalment fund's pounds with the organisation (2026-11-22).
            $screens(['loanfunds'], ['view', 'approve']),
            // Micro-project files: the payment order is approved by the Program Manager, the Financial Manager and,
            // above the threshold, the General Manager (2026-11-23).
            $screens(['microfiles'], ['view', 'approve']),
            // Purchase files: the Financial Manager approves the purchase order, the General Manager after above the threshold (2026-11-24).
            $screens(['purchases'], ['view', 'approve']),
            // The Financial Manager sets the payroll's rate and approves it (2026-11-24).
            $screens(['payroll'], ['view', 'edit', 'approve']),
            $screens(['currency'], $prepare),
            $screens($monthEnd, ['view', 'edit', 'approve']),
        ),

        RoleId::Accountant->value => array_merge(
            $screens(['home', 'project-money', ...$reports, 'currency', 'donors', 'requests', $operations, ...$microHome], $view),
            // `tills` is the daily cash count, `treasury` the liquidity position with the month's revaluation.
            $screens(['chart', ...$entries, 'alloc', 'transfers', 'tills', 'treasury', 'salaries', 'payroll', 'contracts'], $prepare),
            // Role review of 2026-11-26: the accountant prepares the opening balances and counts the month's end; the
            // Financial Manager approves the opening voucher and closes the month (one prepares, another approves).
            $screens(['opening'], ['view', 'create']),
            $screens(['mytill'], $view),
            $screens($monthEnd, ['view', 'edit']),
        ),

        RoleId::ProjectManager->value => array_merge(
            $screens(['home', 'rexp', 'advances', 'loans', 'donors', 'contracts', $operations, ...$microHome], $view),
            // The project manager prepares the micro-project files and their payment orders.
            $screens(['requests', 'microfiles', 'purchases'], $prepare),
        ),

        RoleId::Cashier->value => array_merge(
            // "My till": the cashier's day in one page (role review of 2026-11-26).
            $screens(['home', 'mytill', 'treasury', 'rcash', 'purchases', 'recurring', $operations], $view),
            // The cashier also receives the beneficiaries' instalments in cash (role review of 2026-11-26).
            $screens(['single', 'receipt', 'advance', 'tills', 'revenues', 'payments'], ['view', 'create']),
        ),

        RoleId::Employee->value => $screens(['home'], $view),

        RoleId::Donor->value => $screens(['home'], $view),

        RoleId::GeneralManager->value => array_merge(
            $screens(['home', 'chart', 'project-money', ...$treasury, ...$reports, 'donors', $operations, $inbox, ...$microHome], $view),
            $screens(['requests', 'vouchers', 'approval', 'contracts', 'microfiles', 'purchases'], ['view', 'approve']),
        ),

        RoleId::ProgramManager->value => array_merge(
            $screens(['home', 'project-money', 'donors', 'contracts', 'purchases', $operations, $inbox, ...$microHome], $view),
            $screens(['requests', 'microfiles'], ['view', 'approve']),
        ),

    ],

];
