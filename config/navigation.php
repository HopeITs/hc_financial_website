<?php

/*
|--------------------------------------------------------------------------
| Main navigation
|--------------------------------------------------------------------------
|
| The groups and screens of the main menu, in their order. Each screen is a
| slug; its label is lang `navigation.screens.<slug>` and a group's label is
| `navigation.groups.<group>`. A screen that is not built yet opens the
| placeholder page.
|
| The slug stays the permission key (config/permissions.php). The address a
| person sees is the screen's path below, and its route name is that path
| with dots (`reports/trial-balance` is `reports.trial-balance`). The old
| `screens/<slug>` addresses redirect to it.
|
*/

return [

    'groups' => [
        // The organisation's financial operations in one place (finance request of 2026-11-24): its page, then the
        // requests and purchases, the advances, the salaries and the moves between accounts.
        'operations' => ['operations', 'requests', 'purchases', 'recurring', 'advance', 'payroll', 'salaries', 'transfers'],
        // Micro projects in one place (finance request of 2026-11-23).
        'micro' => ['microhome', 'microfiles', 'payments', 'loanfunds', 'loans'],
        'funding' => ['donors', 'contracts', 'rdonor'],
        // The treasury's daily and monthly work (finance decision of 2026-11-25); its set-up screens are in settings.
        'treasury' => ['treasury', 'tills', 'alloc', 'monthend'],
        // The accounting: the vouchers (with those waiting for approval), the manual entry and Excel. The payment and
        // receipt vouchers are made by the operations and stay out of the menu (see `hidden`).
        'entries' => ['vouchers', 'journal', 'multi', 'approval', 'single', 'receipt'],
        // The reports' own page, in three parts (ReportsHome), then each report.
        'reports' => ['reports', 'tb', 'statements', 'statement', 'rexp', 'rrev', 'rfixed', 'rattach', 'recon', 'uncomp', 'xreserve', 'rcash', 'advances', 'mapprovals'],
        'chart' => ['chart'],
        'settings' => ['users', 'cities', 'currency', 'denoms', 'opening', 'auditlog', 'employees', 'log'],
    ],

    'paths' => [
        'operations' => 'operations',
        'recurring' => 'operations/recurring',
        'reports' => 'reports',
        'payroll' => 'operations/payroll',

        'donors' => 'funding/donors',
        'contracts' => 'funding/agreements',
        'rdonor' => 'funding/donor-report',

        'single' => 'entries/payment',
        'receipt' => 'entries/receipt',
        'journal' => 'entries/journal',
        'advance' => 'entries/advances',
        'multi' => 'entries/multi',
        'microhome' => 'micro-projects',
        'microfiles' => 'entries/micro-files',
        'payments' => 'entries/micro-loans',
        'loanfunds' => 'entries/instalment-funds',
        'vouchers' => 'entries/vouchers',
        'approval' => 'entries/approval',
        'requests' => 'entries/requests',
        'purchases' => 'entries/purchases',
        'salaries' => 'entries/salaries',

        'tills' => 'treasury/daily-count',
        'monthend' => 'treasury/month-end',
        'opening' => 'treasury/opening-balances',
        'treasury' => 'treasury/position',
        'alloc' => 'treasury/exchange-rates',
        'transfers' => 'treasury/transfers',
        'currency' => 'treasury/currencies',
        'denoms' => 'treasury/denominations',

        'rexp' => 'reports/expenses',
        'rcash' => 'reports/cash-balances',
        'rrev' => 'reports/revenue',
        'rfixed' => 'reports/fixed-assets',
        'rattach' => 'reports/attachments',
        'tb' => 'reports/trial-balance',
        'statement' => 'reports/account-statement',
        'statements' => 'reports/financial-statements',
        'recon' => 'reports/hawala-reconciliation',
        'mapprovals' => 'reports/monthly-approvals',
        'uncomp' => 'reports/uncompensated-cash',
        'xreserve' => 'reports/exchange-difference-reserve',
        'advances' => 'reports/open-advances',
        'loans' => 'reports/loan-collection',

        'chart' => 'chart',

        'users' => 'settings/users',
        'employees' => 'settings/employees',
        'cities' => 'settings/cities',
        'log' => 'settings/log',
        'auditlog' => 'settings/audit-log',
    ],

    /*
     * Screens that keep their address and permission but are not in the menu (finance decisions of 2026-11-24/25):
     * the payment and receipt vouchers, made by the operations and the manual entry; the vouchers waiting for approval,
     * a tab of the vouchers; the salary split, a tab of the payroll; and screens another one covers: the old staff list
     * and log (the payroll's staff, the audit log), the cash balances (the liquidity position), the open advances (the
     * advances screen) and the old program's monthly approvals.
     */
    'hidden' => ['single', 'receipt', 'approval', 'salaries', 'employees', 'log', 'rcash', 'advances', 'mapprovals'],

];
