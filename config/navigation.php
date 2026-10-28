<?php

/*
|--------------------------------------------------------------------------
| Main navigation
|--------------------------------------------------------------------------
|
| The groups and screens of the "Hope Center Finance" mockup, in its order.
| Each screen is a slug; its label is lang `navigation.screens.<slug>` and a
| group's label is `navigation.groups.<group>`. A screen that is not built yet
| opens the placeholder page.
|
| The slug stays the permission key (config/permissions.php). The address a
| person sees is the screen's path below, and its route name is that path
| with dots (`reports/trial-balance` is `reports.trial-balance`). The old
| `screens/<slug>` addresses redirect to it.
|
*/

return [

    'groups' => [
        'chart' => ['chart'],
        'projects' => ['projwin', 'allaccounts', 'budgetlines', 'blcats', 'lifetime', 'programs', 'projects'],
        'entries' => ['single', 'multi', 'multiatt', 'payments', 'vouchers', 'bonds', 'approval', 'requests'],
        'treasury' => ['tills', 'treasury', 'alloc', 'cities', 'currency', 'denoms'],
        'reports' => ['rexp', 'rcash', 'rbl', 'rrev', 'rfixed', 'rattach', 'tb', 'projbal', 'statement', 'statements', 'recon', 'uncomp', 'xreserve', 'advances', 'loans'],
        'people' => ['donors', 'employees', 'salaries', 'users', 'limits', 'log'],
        'migration' => ['dbload', 'opening', 'fixusd', 'legacy', 'merges'],
    ],

    'paths' => [
        'chart' => 'chart',

        'projwin' => 'projects/list',
        'allaccounts' => 'projects/accounts',
        'budgetlines' => 'projects/budget-lines',
        'blcats' => 'projects/line-categories',
        'lifetime' => 'projects/lines-lifetime',
        'programs' => 'projects/programs',
        'projects' => 'projects/balances',

        'single' => 'entries/single',
        'multi' => 'entries/multi',
        'multiatt' => 'entries/attachments',
        'payments' => 'entries/micro-loans',
        'vouchers' => 'entries/vouchers',
        'bonds' => 'entries/bonds',
        'approval' => 'entries/approval',
        'requests' => 'entries/requests',

        'tills' => 'treasury/tills',
        'treasury' => 'treasury/position',
        'alloc' => 'treasury/allocation-rates',
        'cities' => 'treasury/cities',
        'currency' => 'treasury/currencies',
        'denoms' => 'treasury/denominations',

        'rexp' => 'reports/expenses',
        'rcash' => 'reports/cash-balances',
        'rbl' => 'reports/budget-lines',
        'rrev' => 'reports/revenue',
        'rfixed' => 'reports/fixed-assets',
        'rattach' => 'reports/attachments',
        'tb' => 'reports/trial-balance',
        'projbal' => 'reports/project-balances',
        'statement' => 'reports/account-statement',
        'statements' => 'reports/financial-statements',
        'recon' => 'reports/hawala-reconciliation',
        'uncomp' => 'reports/uncompensated-cash',
        'xreserve' => 'reports/exchange-difference-reserve',
        'advances' => 'reports/open-advances',
        'loans' => 'reports/loan-collection',

        'donors' => 'people/donors',
        'employees' => 'people/employees',
        'salaries' => 'people/salaries',
        'users' => 'people/users',
        'limits' => 'people/approval-limits',
        'log' => 'people/log',

        'dbload' => 'migration/load-database',
        'opening' => 'migration/opening-close',
        'fixusd' => 'migration/fix-usd',
        'legacy' => 'migration/legacy-exchange',
        'merges' => 'migration/till-merges',
    ],

];
