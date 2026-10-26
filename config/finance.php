<?php

return [

    // The version of the Windows program's database this app is built against (`version`.`l_version`).
    'legacy_version' => '3.1.1.5',

    // Legacy tables and views the app reads. `finance:check-db` fails if one is missing.
    'legacy_tables' => [
        'version', 'user', 'role', 'log',
        'action', 'payment', 'till', 'account', 'budget_line', 'donor', 'employee',
    ],

    'legacy_views' => ['cash_balances', 'tree_view'],

    // Roles whose projects and cash accounts are limited to what the Windows program gives them (RoleId case names, comma separated).
    // A person holding any role not listed here is not limited. Only project managers were limited before the role data scope;
    // add FinancialManager, Cashier, Accountant and Employee once the Financial Manager approves.
    'scoped_roles' => array_filter(array_map('trim', explode(',', (string) env('FINANCE_SCOPED_ROLES', 'ProjectManager')))),

];
