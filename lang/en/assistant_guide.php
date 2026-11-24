<?php

/*
 * The built-in guide (finance request of 2026-11-26): what the assistant answers without an AI service. Each topic has
 * the words it is found by, a title and the answer. A question is matched against the words of both languages, so
 * these lists need only English. Keep the keys the same as lang/ar/assistant_guide.php.
 */

return [
    'intro' => 'I am the program\'s guide: ask me how to do any operation (payments, purchases, payroll, transfers, instalments…) and I explain the steps and open the screen. I work without the internet.',
    'not_found' => 'I found no answer to that in the program\'s guide. Try other words, or pick a topic:',
    'related' => 'Related topics',
    'open' => 'Open: :screen',
    'screens_found' => 'Screens that may help:',
    'live' => [
        'inbox_none' => 'Nothing is waiting for your approval right now.',
        'inbox_some' => 'Waiting for your approval now: :count',
        'inbox_kinds' => ['vouchers' => 'vouchers', 'requests' => 'requests', 'purchases' => 'purchase orders', 'payrolls' => 'payrolls', 'micro' => 'micro-project payment orders'],
        'rates_title' => 'Approved exchange rates today (:day):',
        'rate_line' => ':currency: daily :daily · monthly :monthly',
        'rate_none' => 'none',
    ],
    'suggestions' => ['payment', 'purchases', 'payroll', 'approvals', 'transfers', 'inbox'],

    'topics' => [
        'operations' => [
            'title' => 'Where are the financial operations?',
            'keywords' => ['operations', 'operation', 'where do i start', 'start', 'menu'],
            'answer' => "Every operation is in one place: **Financial operations** in the top menu. It has four parts:\n1. **From donors**: donor instalments on the agreements.\n2. **Between the organisation's accounts**: transfers and exchange.\n3. **Spending**: requests, purchases, rent, advances, micro projects, payroll and other payments.\n4. **Money in**: beneficiaries' instalments and project revenues.\n\nThe numbers on the cards say how much is waiting.",
        ],
        'payment' => [
            'title' => 'How do I record an expense (payment voucher)?',
            'keywords' => ['payment', 'expense', 'pay', 'invoice', 'cpv', 'spend'],
            'answer' => "Most expenses are recorded by their operation: **Purchases** for buying, **Payroll** for salaries, **Recurring payments** for rent, and **Advances** for what is spent from an advance.\n\nFor anything without an operation (bank fees, money returned to a donor…): Financial operations → **Other payments**:\n1. Pick the date and the cash or bank account it was paid from.\n2. For each line: the project and budget line (or account), the amount and a description.\n3. Attach the invoice.\n4. Save. The voucher is **pending approval** and counts in no balance until approved.\n\nThe program remembers the last cash account and project you used.",
        ],
        'receipt' => [
            'title' => 'How do I record money received (receipt voucher)?',
            'keywords' => ['receipt', 'received', 'deposit', 'crv', 'money in'],
            'answer' => "It depends on the money:\n- **A donor instalment**: from the agreement (Financial operations → Donor instalments).\n- **A beneficiary's instalment**: from **Loans and instalments**.\n- **Sales, courses and subscriptions**: from **Project revenues**.\n- Anything else: a receipt voucher from **Vouchers** or the manual entry.\n\nEvery voucher is saved pending approval.",
        ],
        'donor' => [
            'title' => 'How do I receive a donor instalment?',
            'keywords' => ['donor', 'grant', 'agreement', 'funding', 'contract'],
            'answer' => "1. Open **Agreements** (Funding → Agreements) and pick the agreement.\n2. On the instalments tab, pick the instalment that arrived and press **Receive**.\n3. Set the bank or cash account, the date and the amount.\n4. A receipt voucher is saved pending approval: the Financial Manager, then the General Manager above 2,000 USD.\n\nThe budget and its lines are on the budget tab; the donor report is under **Donor report**.",
        ],
        'approvals' => [
            'title' => 'Who approves, and how do approvals work?',
            'keywords' => ['approve', 'approval', 'approvals', 'financial manager', 'general manager', 'who approves'],
            'answer' => "- Every voucher is saved **pending approval**, and its preparer never approves it.\n- The **Financial Manager** approves; above **2,000 USD** the **General Manager** approves after them.\n- **Requests** and **micro-project** payment orders: the Program Manager first, then the Financial Manager, then the General Manager above 2,000 USD.\n- **Purchase orders**: the Financial Manager, then the General Manager above 2,000 USD.\n- **Payrolls**: the Financial Manager.\n\nEverything waiting for you is on **Waiting for my approval**, approved with one click. Balances and reports count approved vouchers only.",
        ],
        'inbox' => [
            'title' => 'What is waiting for my approval?',
            'keywords' => ['waiting', 'inbox', 'pending', 'my approval', 'waiting for me'],
            'answer' => "**Waiting for my approval** gathers everything waiting for you: vouchers, requests, purchase orders, payrolls and micro-project payment orders. Approve each with one click, or open it to send it back or reject it.",
        ],
        'rejected' => [
            'title' => 'My voucher was rejected or sent back: what now?',
            'keywords' => ['rejected', 'reject', 'returned', 'sent back', 'edit voucher', 'mistake', 'correct'],
            'answer' => "- A **pending voucher** is changed from **Vouchers**.\n- A **rejected voucher** leaves the books. Find it on **Vouchers** with \"Show rejected vouchers\". Record it again once corrected.\n- A **request, purchase order or payroll sent back** shows the **reason**: change it and send it again.\n- An approved voucher is not changed: correct it with a manual entry.",
        ],
        'journal' => [
            'title' => 'How do I make a manual journal entry?',
            'keywords' => ['journal', 'jv', 'manual entry', 'adjust', 'adjustment', 'debit', 'credit'],
            'answer' => "Accounting → **Manual entry**:\n1. The date and description.\n2. Add the lines: the account from the chart, the project if needed, debit or credit.\n3. It cannot be saved until debits equal credits.\n4. It is saved pending approval.\n\nFor many vouchers at once use **Import from Excel**.",
        ],
        'requests' => [
            'title' => 'How does a project request work?',
            'keywords' => ['request', 'requests', 'monthly request', 'extra request', 'project manager'],
            'answer' => "**Requests** (Financial operations → Project requests):\n1. The project manager prepares a **monthly request** for running costs, or an **extra request** when needed (an asset, maintenance, rent…).\n2. They add the lines and amounts, then **send** it.\n3. Approval: the Program Manager, then the Financial Manager, then the General Manager above 2,000 USD.\n4. A request can be sent back with a reason, or rejected.\n5. Once approved it is spent through purchases or advances.",
        ],
        'purchases' => [
            'title' => 'What is the purchasing cycle?',
            'keywords' => ['purchase', 'purchases', 'buy', 'procurement', 'supplier', 'po', 'pr', 'purchase order'],
            'answer' => "**Purchases** (Financial operations → Purchases), one file per purchase:\n1. **PR purchase request**: what is bought, quantities, project, goods or services.\n2. **Quotes** (RFQ): one quote up to 500 USD, two from 500 to 2,000, three above 2,000.\n3. **Comparison** (CBA) and the choice of supplier with a reason.\n4. **PO purchase order**: sent for approval, the Financial Manager then the General Manager above 2,000 USD.\n5. **Receipt**: GRN for goods or SRN for services, with the supplier's invoice (it must equal the order total).\n6. **Payment**: in cash to the supplier, or settled from a purchasing advance.\n\nEvery form prints on the organisation's templates from the print button.",
        ],
        'quotes' => [
            'title' => 'How many quotes do I need?',
            'keywords' => ['quote', 'quotes', 'rfq', 'how many quotes'],
            'answer' => "By the purchase's value in USD:\n- Up to **500**: one quote.\n- From **500 to 2,000**: two quotes.\n- Above **2,000**: three quotes.\n\nThe program works out the value and does not let the purchase order go before the quotes are complete.",
        ],
        'receiving' => [
            'title' => 'How do I receive a purchase and pay the supplier?',
            'keywords' => ['receive', 'receiving', 'grn', 'srn', 'supplier invoice', 'pay supplier'],
            'answer' => "Once the purchase order is approved, in the purchase file:\n1. **Receipt**: the date and place, the quantities received, the supplier's invoice number, date and amount. The amount must equal the order total.\n2. Print the **GRN** (goods) or **SRN** (services).\n3. **Payment**: pick cash (from a cash account) or a **purchasing advance**. The voucher is created pending approval.",
        ],
        'advances' => [
            'title' => 'How do I give and settle an advance?',
            'keywords' => ['advance', 'advances', 'settle', 'settlement'],
            'answer' => "**Advances** (Financial operations → Advances):\n1. **Give an advance**: the employee or purchasing officer, the amount, the cash account and the attachments.\n2. **Settle it**: enter the invoices spent from it on the projects and budget lines; what was not spent goes back to the cash account.\n3. Unsettled advances show in the open advances list.\n\nA purchase can also be paid from an advance straight from its file.",
        ],
        'payroll' => [
            'title' => 'How do I run the month\'s payroll?',
            'keywords' => ['payroll', 'salary', 'salaries', 'payslip', 'wages'],
            'answer' => "**Payroll** (Financial operations → Payroll), per project each month:\n1. **Open the month's payroll** for the project and add its staff from the staff tab.\n2. For each employee: the bonus, absent days and unpaid leave (deducted as days × basic ÷ 30) and other deductions.\n3. The **Financial Manager** sets the rate (salaries are fixed in USD and usually paid in SYP) and approves.\n4. **Payment**: pick the cash account and date; an allocation entry (ALC) is posted over the projects and budget lines.\n5. Print the summary and the payslips.\n\nMany changes? Use Excel on the same screen.",
        ],
        'staff' => [
            'title' => 'How do I add an employee or change a salary?',
            'keywords' => ['staff', 'employee', 'employees', 'add employee', 'contract'],
            'answer' => "Payroll → **Staff** tab:\n- Add the employee: name, number, project, position, basic pay and its currency.\n- To change many: **export to Excel**, edit the file, then **import** it.\n- **Salary split** sets each employee's share over projects and budget lines.",
        ],
        'recurring' => [
            'title' => 'How do I record rent and recurring payments?',
            'keywords' => ['rent', 'recurring', 'repeat', 'monthly payment'],
            'answer' => "**Recurring payments** (Financial operations):\n1. Add the payment: payee, amount, currency, cash account, project and budget line, every how many months (1, 2, 3, 6, 12) and the first date.\n2. The program pays it **by itself** on its day (each morning at 6) as a payment voucher pending approval.\n3. It can be paused or changed at any time.",
        ],
        'transfers' => [
            'title' => 'How do I move money between accounts or exchange currency?',
            'keywords' => ['transfer', 'transfers', 'move money', 'exchange', 'hawala', 'bank'],
            'answer' => "**Transfers and exchange** (Financial operations):\n1. Pick where from (bank, transfer company, a city's cash) and where to.\n2. The amount and currency. Two different currencies make an **exchange**: enter both amounts and the program shows the rate.\n3. The fee, if any.\n4. A transfer voucher (TRF) is saved pending approval.\n\nThe transfer company's statement is matched in **Hawala reconciliation**.",
        ],
        'rates' => [
            'title' => 'How do exchange rates work?',
            'keywords' => ['rate', 'rates', 'exchange rate', 'dollar', 'pound', 'syp', 'euro'],
            'answer' => "**Exchange rates** (Treasury):\n- The **monthly rate**: for the month's invoices and expenses.\n- The **daily rate**: for beneficiaries' instalments and currency receipts. The accountant proposes it and the **Financial Manager** approves it (their own is approved at once).\n- A rate is units of the currency per dollar.\n- Rates can be entered from Excel.",
        ],
        'cashcount' => [
            'title' => 'How do I do the daily cash count?',
            'keywords' => ['cash count', 'count', 'till', 'safe', 'cash'],
            'answer' => "**Daily count** (Treasury):\n1. For each cash account, safe or bank: enter what is actually there.\n2. The program compares it with the books and shows the difference.\n3. Differences go to account 5505.\n\nWhere is the money now? **Liquidity position**.",
        ],
        'monthend' => [
            'title' => 'How do I close the month?',
            'keywords' => ['month end', 'close month', 'closing', 'revaluation'],
            'answer' => "**Month end** (Treasury):\n1. Count each cash account and match it with the books.\n2. Save each row.\n3. **Revaluation**: pounds are carried at book value; at the month's end they are revalued and the difference goes to the exchange reserve (5503).\n4. The Financial Manager approves the closing.",
        ],
        'micro' => [
            'title' => 'How does a micro-project file work?',
            'keywords' => ['micro', 'micro project', 'beneficiary', 'loan file', 'protocol'],
            'answer' => "**Beneficiary files** (Micro projects):\n1. **New file**: the beneficiary's details, profession, purchase list and quotes.\n2. **Payment order**: sent for approval: the Program Manager, the Financial Manager and the General Manager above 2,000 USD.\n3. **Purchases**: record the invoices (in cash or from an advance).\n4. **Receipt protocol**: once signed, the loan opens.\n5. Part of the amount is a **grant** and part a **loan** repaid in instalments (the recovery percent).\n\nEvery form prints on the original templates.",
        ],
        'instalments' => [
            'title' => 'How do I receive a beneficiary\'s instalment?',
            'keywords' => ['instalment', 'installment', 'instalments', 'collection', 'late', 'arrears'],
            'answer' => "**Loans and instalments** (Micro projects):\n1. Open the loan's card.\n2. **Receive an instalment**: the date, amount, currency and cash account. An instalment in pounds uses the **daily rate**.\n3. You can also reschedule, write off, or change the details.\n\nArrears are in the **Loan collection** report.",
        ],
        'funds' => [
            'title' => 'What are the instalment funds?',
            'keywords' => ['fund', 'instalment fund', 'funds', 'funder'],
            'answer' => "**Instalment funds** hold the repaid instalments of each funder in each governorate, at today's value, to fund new beneficiaries. Only the Financial Manager exchanges a fund's pounds with the organisation or gives them back to the donor.",
        ],
        'revenues' => [
            'title' => 'How do I record project revenues?',
            'keywords' => ['revenue', 'revenues', 'sales', 'sell', 'courses', 'subscription', 'internet'],
            'answer' => "**Project revenues** (Financial operations):\n1. Set up each project's **price list** (sales, subscriptions, internet, courses…).\n2. Each day: open the day's sheet and enter quantities and prices.\n3. Save: **one receipt voucher** is created into the cash account, on the revenue account of each kind.\n4. The monthly summary is on the same screen.",
        ],
        'reports' => [
            'title' => 'Where are the reports?',
            'keywords' => ['report', 'reports', 'trial balance', 'statement', 'balance', 'balances', 'financial statements'],
            'answer' => "**Reports** in the menu: one page with every report in three parts:\n- **Financial statements**: trial balance, financial statements, account statement.\n- **Projects**: expenses against budget, revenue, fixed assets, attachments, donor report.\n- **Follow-up**: hawala reconciliation, uncompensated cash, exchange reserve, loan collection.\n\nReports count **approved** vouchers only.",
        ],
        'search' => [
            'title' => 'How do I find something quickly?',
            'keywords' => ['search', 'find', 'ctrl+k', 'look up'],
            'answer' => "Press **Ctrl+K** (or the search button at the top) and type a number or a word: it finds vouchers, purchase files (PR-0012), requests, staff, beneficiary files, accounts, projects and screens.",
        ],
        'excel' => [
            'title' => 'How do I use Excel?',
            'keywords' => ['excel', 'import', 'export', 'spreadsheet', 'file'],
            'answer' => "Most screens have **Excel** buttons: **Export** downloads the data, **Template** an empty file, and **Import** uploads it once filled. The program checks the file and shows the mistakes before saving.\n\nFor many vouchers: Accounting → **Import from Excel**.",
        ],
        'users' => [
            'title' => 'How do I add a user or change their permissions?',
            'keywords' => ['user', 'users', 'permission', 'permissions', 'role', 'roles', 'password'],
            'answer' => "Settings → **Users**:\n1. **New user**: name, password and main role.\n2. Pick a user to change: main role, extra roles, may sign in or not, and permissions for them alone on each screen (view, create, edit, delete, approve).\n3. Locked ticks come from a role.\n4. The **Roles** tab explains what each role allows.",
        ],
        'opening' => [
            'title' => 'What are the opening balances?',
            'keywords' => ['opening', 'opening balance', 'go live', 'start balances'],
            'answer' => "Settings → **Opening balances**: one opening voucher per city on the day work on the program starts: the cash, bank and other balances, and the program writes the net assets. It is used once.",
        ],
        'print' => [
            'title' => 'How do I print the forms?',
            'keywords' => ['print', 'form', 'forms', 'pdf'],
            'answer' => "Purchase files, beneficiary files and payrolls have a **Print** button for each form: PR, RFQ, CBA, PO, GRN, SRN, payment order, summary and payslips. They print on the organisation's letterhead; choose \"Save as PDF\" in the print window to keep one.",
        ],
        'budget' => [
            'title' => 'How much of a project\'s budget is left?',
            'keywords' => ['budget', 'budget line', 'remaining', 'left'],
            'answer' => "- Each agreement's budget and lines are in **Agreements** → the budget tab (it can be imported from Excel).\n- Spending against budget per line is in the **Expenses report**.\n- What the donor sent and how it was spent is in the **Donor report**.",
        ],
    ],
];
