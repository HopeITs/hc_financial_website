<?php

return [

    // A voucher that is out by no more than this many USD is balanced by a line on the rounding account.
    'rounding_tolerance_usd' => '0.05',

    // Currency exchange differences: where the cents of a rounded voucher go (rule 4).
    'rounding_account' => '5503',

    // Days of the week that are not working days (0 Sunday ... 6 Saturday); the monthly request deadline skips them.
    'weekend_days' => [5, 6],

    // In-transit accounts (11x3, 11x4) and these accounts must be zero before a month is closed (rule 12).
    'in_transit_pattern' => '/^11[1-5][34]$/',
    'clearing_accounts' => ['2901'],

    // A payment voucher not linked to an approved request is refused above this many USD (Financial Manager, round 2).
    'request_limit_usd' => '500.00',

    // The transfer company's dollar pool, and the name written on its lines of a transfer voucher (the account asks for a contact).
    'transfer_company_account' => '1161',
    'transfer_company_contact' => env('LEDGER_TRANSFER_COMPANY', 'Transfer company'),

];
