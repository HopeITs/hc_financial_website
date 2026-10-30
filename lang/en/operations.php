<?php

return [
    'many' => 'Many operations at once: download the Excel template, fill it and upload it',
    'intro' => 'Pick the type of operation and fill in its form. The program writes the debit and credit lines; check them, then save the voucher for approval.',
    'step_type' => 'Type of operation',
    'step_form' => ':type details',
    'step_review' => 'Lines the program writes',
    'no_types' => 'Your role cannot enter operations.',
    'mode' => 'Kind',
    'save' => 'Save for approval',
    'owner' => 'Owner',
    'preview_empty' => 'Type the amount to see the lines.',
    'budget_line_help' => 'Choosing the line fills the project and the expense kind.',
    'expense_kind' => 'Expense kind',
    'income_kind' => 'Income kind',
    'filled_from_line' => 'Filled from the budget line.',
    'cash_in' => 'Received into',
    'cash_out' => 'Paid from',
    'contact_from' => 'Received from',
    'jv_note' => 'A manual entry is written line by line, with the debit and credit accounts chosen by hand. Use it only when no other type fits; it opens the voucher builder.',
    'open_builder' => 'Open the voucher builder',

    'types' => [
        'CRV' => ['name' => 'Receipt', 'hint' => 'Money comes into a till: a donor grant, income or an advance paid back.'],
        'CPV' => ['name' => 'Payment', 'hint' => 'Money leaves a till: an expense on a budget line or an advance to a person.'],
        'TRF' => ['name' => 'Transfer', 'hint' => 'Move money from one project to another, or back to the city general account.'],
        'ALC' => ['name' => 'Allocation', 'hint' => 'Give a project money from the city general account, in dollars or pounds.'],
        'EXG' => ['name' => 'Exchange', 'hint' => 'The general account sells dollars for pounds at the day rate.'],
        'JV' => ['name' => 'Manual entry', 'hint' => 'Debit and credit lines typed by hand. Financial Manager and Head Office only.'],
    ],

    'modes' => [
        'income' => 'Income or grant',
        'repayment' => 'Advance paid back',
        'expense' => 'Expense',
        'advance' => 'Advance to a person',
        'between_projects' => 'Project to project',
        'return' => 'Project back to the general account',
        'usd_to_project' => 'Dollars to a project',
        'syp_to_project' => 'Pounds to a project',
        'local_exchange' => 'Dollars to pounds',
    ],
];
