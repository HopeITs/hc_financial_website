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
*/

return [

    'groups' => [
        'chart' => ['chart'],
        'projects' => ['projwin', 'allaccounts', 'budgetlines', 'blcats', 'lifetime', 'programs', 'projects'],
        'entries' => ['single', 'multi', 'multiatt', 'payments', 'vouchers', 'bonds', 'approval', 'requests'],
        'treasury' => ['tills', 'treasury', 'alloc', 'cities', 'currency', 'denoms'],
        'reports' => ['rexp', 'rcash', 'rbl', 'rrev', 'rfixed', 'rattach', 'tb', 'projbal', 'statement', 'statements'],
        'people' => ['donors', 'employees', 'salaries', 'users', 'limits', 'log'],
        'migration' => ['dbload', 'opening', 'fixusd', 'legacy', 'merges'],
    ],

];
