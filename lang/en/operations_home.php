<?php

return [
    'intro' => 'The organisation\'s money, step by step: received from the donors, moved between its accounts, spent, and received back. Open a card to work on it.',
    'pending_vouchers' => '{1} 1 voucher waits for approval.|[2,*] :count vouchers wait for approval.',
    'none' => 'No operation is open to you here.',
    'sections' => [
        'donors' => ['title' => 'Received from the donors', 'hint' => 'The donors\' instalments, received on their projects.'],
        'moves' => ['title' => 'Between the organisation\'s accounts', 'hint' => 'From the bank to the transfer company and the cities\' cash, and from one currency to another.'],
        'spending' => ['title' => 'Spending', 'hint' => 'Running costs once a month for each project; assets, large repairs, electronics and rent when needed; micro projects; salaries.'],
        'income' => ['title' => 'Money received', 'hint' => 'The beneficiaries\' monthly instalments and what the projects earn.'],
    ],
    'cards' => [
        'donor_receipts' => ['title' => 'Donor instalments', 'hint' => 'Open the agreement and receive the instalment that came.'],
        'transfers' => ['title' => 'Transfers and exchange', 'hint' => 'Move money between banks, the transfer company and the cities\' cash.'],
        'requests' => ['title' => 'Project requests', 'hint' => 'The monthly request for running costs, or a request when an asset, repair or rent is needed.'],
        'purchases' => ['title' => 'Purchases', 'hint' => 'Quotes, purchase order, receiving with the invoice, and payment.'],
        'recurring' => ['title' => 'Rent and recurring payments', 'hint' => 'Paid by themselves on their day.'],
        'advances' => ['title' => 'Advances', 'hint' => 'Give the purchasing officer or a staff member an advance, and settle it with the bills.'],
        'micro' => ['title' => 'Micro projects', 'hint' => 'The beneficiaries\' files, their purchases and loans or grants.'],
        'salaries' => ['title' => 'Salaries', 'hint' => 'The monthly payroll of each project.'],
        'other_payments' => ['title' => 'Other payments', 'hint' => 'Only what has no purchase: bank fees, money given back to a donor…'],
        'instalments' => ['title' => 'Beneficiaries\' instalments', 'hint' => 'Receive a monthly instalment of a micro-project loan.'],
        'other_receipts' => ['title' => 'Other receipts', 'hint' => 'Only what has no operation: bank interest, a refund, a deposit returned…'],
        'revenues' => ['title' => 'Project revenues', 'hint' => 'Sales, students\' subscriptions, internet and paid courses received into a cash account.'],
    ],
];
