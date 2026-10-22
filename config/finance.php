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

    // A payment voucher not linked to an approved request is refused above this many USD (Financial Manager, round 2).
    'request_limit_usd' => '500.00',

];
