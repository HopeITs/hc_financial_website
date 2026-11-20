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
        'funding' => ['donors', 'contracts', 'rdonor'],
        'entries' => ['single', 'receipt', 'journal', 'advance', 'vouchers', 'approval', 'requests', 'payments', 'loanfunds', 'salaries', 'multi'],
        'treasury' => ['treasury', 'tills', 'transfers', 'alloc', 'monthend', 'opening', 'currency', 'denoms'],
        'reports' => ['rexp', 'rcash', 'rrev', 'rfixed', 'rattach', 'tb', 'statement', 'statements', 'recon', 'mapprovals', 'uncomp', 'xreserve', 'advances', 'loans'],
        'chart' => ['chart'],
        'settings' => ['users', 'employees', 'cities', 'log', 'auditlog'],
    ],

    'paths' => [
        'donors' => 'funding/donors',
        'contracts' => 'funding/agreements',
        'rdonor' => 'funding/donor-report',

        'single' => 'entries/payment',
        'receipt' => 'entries/receipt',
        'journal' => 'entries/journal',
        'advance' => 'entries/advances',
        'multi' => 'entries/multi',
        'payments' => 'entries/micro-loans',
        'loanfunds' => 'entries/instalment-funds',
        'vouchers' => 'entries/vouchers',
        'approval' => 'entries/approval',
        'requests' => 'entries/requests',
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

];
