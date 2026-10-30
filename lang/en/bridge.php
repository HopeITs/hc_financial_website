<?php

return [
    'decisions' => [
        'intro' => 'Read as it is today from',
        'language_note' => 'The log is kept in English in the repository. A row marked Open still needs the Financial Manager.',
        'search' => 'Search the log',
        'decided' => ':count decided',
        'open' => ':count open',
        'other' => ':count other',
        'empty' => 'The decisions log was not found.',
        'no_match' => 'No row matches the search.',
    ],

    'mapping' => [
        'intro' => 'How each part of the Windows program is carried into the ledger. The migration (load test database) applies this mapping.',

        'concepts_title' => 'What becomes what',
        'col_legacy' => 'Windows program',
        'col_ledger' => 'Ledger',
        'concepts' => [
            ['Till (account and currency)', 'Cash account of that currency (111x USD, 112x SYP, one per currency added); the project becomes a Project tag on the line'],
            ['Project account (account)', 'Project (owner) with donor, restriction, city, currency and rate'],
            ['Donor', 'Income account 4101 to 4602'],
            ['Line and budget line', 'Budget line tag, linked to one expense account 51xx to 58xx'],
            ['Operation I, E, X, A, R, T', 'Voucher CRV, CPV, EXG, CPV (1201), CRV, and TRF, ALC or JV'],
            ['Approval and role limit', 'Voucher approval by the Financial Manager; the preparer never approves'],
        ],

        'operations_title' => 'Operations and the vouchers they become',
        'col_letter' => 'Code',
        'col_operation' => 'Operation',
        'col_voucher' => 'Voucher',
        'col_lines' => 'Lines',
        'operations' => [
            ['I', 'Income', 'CRV', 'Debit cash, credit the donor\'s income account'],
            ['E', 'Expense', 'CPV', 'Debit the expense account of the budget line, credit cash'],
            ['A', 'Advance', 'CPV', 'Debit advances 1201 with the person as contact, credit cash'],
            ['R', 'Returned', 'CRV', 'Credit 1204 when it repays a micro loan, otherwise 1201'],
            ['X', 'Exchange', 'EXG', 'Debit the SYP cash account, credit the USD cash account'],
            ['T', 'Transfer', 'TRF, ALC or JV', 'A transfer between accounts with its kind; a salary split over projects is an ALC'],
        ],

        'statuses_title' => 'Operation states',
        'col_state' => 'In the Windows program',
        'col_becomes' => 'In the ledger',
        'statuses' => [
            ['Deleted', 'Not migrated'],
            ['Not approved', 'A voucher pending approval'],
            ['Approved', 'An approved voucher that keeps the operation id (rule 13)'],
            ['SYP with rate 0', 'Dollar value at the project average rate of its date (D7)'],
        ],
    ],

    'rules' => [
        'intro' => 'Every voucher is checked against these rules before it is posted. The status comes from the code itself: a rule is live when its check runs from a screen that is built.',
        'col_rule' => 'Rule',
        'col_state' => 'Status',
        'col_where' => 'Runs from',
        'nowhere' => 'No screen yet',
        'state' => [
            'live' => 'Live',
            'code' => 'In code, no screen yet',
            'described' => 'Described only',
        ],
        'count' => [
            'live' => ':count live',
            'code' => ':count in code only',
            'described' => ':count described only',
        ],
        'items' => [
            1 => ['title' => 'Only active posting accounts', 'detail' => 'A line posts only to an account that exists, is active and is a posting account, not a header.'],
            2 => ['title' => 'Each currency in its own cash account', 'detail' => 'A line is in a currency the books accept, and an account fixed to one currency takes only that currency.'],
            3 => ['title' => 'Debit or credit, never both', 'detail' => 'A line is a debit or a credit with a positive amount, never both and never negative.'],
            4 => ['title' => 'Balanced in USD', 'detail' => 'A voucher balances exactly in USD; the cents left by rounding go to 5503.'],
            5 => ['title' => 'Required tags filled', 'detail' => 'Every tag the account requires (project, budget line, fund source, contact, center) is filled.'],
            6 => ['title' => 'Budget line with its own project and account', 'detail' => 'A budget line is used only with its own project, and the line posts to the account the budget line fills.'],
            7 => ['title' => 'The preparer never approves', 'detail' => 'The person who prepared a voucher never approves or rejects it, whatever their role.'],
            8 => ['title' => 'Nothing changes in a closed month', 'detail' => 'Nothing is posted into, or changed in, a month that has been closed.'],
            9 => ['title' => 'Changes to an approved voucher are kept', 'detail' => 'A change to an approved voucher keeps the old value, the new value, who changed it and why.'],
            10 => ['title' => 'Numbers are never reused', 'detail' => 'Voucher numbers (CRV-2026-0001) only go up, and a number used once, even on a deleted voucher, is skipped.'],
            11 => ['title' => 'A used account keeps its identity', 'detail' => 'Once a voucher line uses an account, its code, class and currency never change; it can still be renamed or deactivated.'],
            12 => ['title' => 'A month closes only when clearing is zero', 'detail' => 'A month closes only when the in-transit accounts (11x3, 11x4) and the suspense account (2901) are zero.'],
            13 => ['title' => 'Migrated vouchers keep their legacy id', 'detail' => 'A voucher migrated from the Windows program keeps the id of its operation, once.'],
            14 => ['title' => 'A request is needed above USD 500', 'detail' => 'A payment not linked to an approved request is refused above USD 500 per voucher (R3-3).'],
            15 => ['title' => 'A hawala receipt is consistent', 'detail' => 'A donor receipt through the transfer company keeps the amount sent, the real rate and the commission; the dollars received equal amount times rate minus commission, and the commission is posted to 5504 (R1-6b, R4-6).'],
            16 => ['title' => 'Net assets are computed', 'detail' => 'Net assets are assets minus liabilities, worked out by the program. No line typed in an entry screen may post to a 3xxx account; migrated vouchers keep theirs, and opening balances and the year-end close write them through their own vouchers.'],
        ],
    ],
];
