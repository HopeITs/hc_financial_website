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
        'operations' => ['operations', 'requests', 'purchases', 'advance', 'salaries', 'transfers'],
        'funding' => ['donors', 'contracts', 'rdonor'],
        // The accounting: the vouchers, the manual entry and Excel. The payment and receipt vouchers are made by the
        // operations and stay out of the menu (see `hidden`).
        'entries' => ['vouchers', 'approval', 'journal', 'multi', 'single', 'receipt'],
        // Micro projects in one place (finance request of 2026-11-23).
        'micro' => ['microhome', 'microfiles', 'payments', 'loanfunds', 'loans'],
        'treasury' => ['treasury', 'tills', 'alloc', 'monthend', 'opening', 'currency', 'denoms'],
        'reports' => ['rexp', 'rcash', 'rrev', 'rfixed', 'rattach', 'tb', 'statement', 'statements', 'recon', 'mapprovals', 'uncomp', 'xreserve', 'advances'],
        'chart' => ['chart'],
        'settings' => ['users', 'employees', 'cities', 'log', 'auditlog'],
    ],

    'paths' => [
        'operations' => 'operations',

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
     * Screens that keep their address and permission but are not in the menu: the payment and receipt vouchers are
     * made by the operations (purchases, revenues…) and the manual entry (finance request of 2026-11-24).
     */
    'hidden' => ['single', 'receipt'],

];
