# Master data screens: what is edited in the web app and what is only shown (task 38)

Rule: the web app never writes to a table of the Windows program. The only exception is a password in `user`, written
through `FinancePassword::make` (and noted in `log`, without the password). Master data of the ledger (the chart, the
programs, the projects) is edited in the `gl_*` tables on its own screens.

| Screen | Decision | Source | Why |
|--------|----------|--------|-----|
| `chart` | Edited in the web app | `gl_accounts` | The ledger's master data (task 15). Cash accounts and donors (income accounts) are edited here. |
| `programs` | Edited in the web app | `gl_programs` | The ledger's master data (task 44). |
| `projects` | Edited in the web app | `gl_projects`, `gl_budget_lines` | The ledger's master data (task 45): the project page. |
| `projwin` | Merged into the project page | `projects` | Same list of owners; the old window is the project list. |
| `allaccounts` | Merged into the project page | `projects` | A project account is a project; its accounts are a tab of the project page. |
| `budgetlines` | Merged into the project page | `projects` | The budget lines of a project are a tab of its page; all of them are in the report `rbl`. |
| `lifetime` | Merged into the project page | `projects` | The lifetime of income and expenses is a tab of the project page. |
| `blcats` | Read only | `linecategory`, `linecategorytype` | Categories of the old program; the ledger groups expenses by account, so nothing edits them. |
| `donors` | Read only | `donor` | In the ledger a donor is an income account (4101 and the others), edited in the chart. |
| `cities` | Read only | `city` | Shown with the projects and cash boxes of each city; cities are fixed in the ledger (`city` of accounts and projects). |
| `currency` | Read only | `tilltypes` | The ledger works in USD and SYP. |
| `denoms` | Read only | `denominations` | Note and coin values for the cash count of the old program. |
| `tills` | Read only | `cashaccount`, `tilltypes`, `city` | In the ledger a till is a cash account, edited in the chart. The mapping between the two is made by the migration. |
| `employees` | Read only | `employee` | Names and dates only. Salaries are on `salaries`, for the finance roles. |
| `bonds` | Read only | `fiscalbond`, `account` | Fiscal bonds of the old program. |
| `limits` | Read only | `role_currency_limit`, `role` | Approval limits are read from the old program when a voucher is approved. |
| `log` | Read only | `log` | What was done in the old program and the web app (sign-ins, password changes). |
| `users` | Read only, but a password can be changed | `user` | Passwords through `FinancePassword::make` only, so one password works in both programs. Roles and states stay in the old program. Only Head Office may change a password. |

The decision of each reference screen is also printed on the screen itself (`lang/*/master.php`, `master.decision.*`).
Read only here means: until the Windows program is retired. After go-live a screen that still matters can be moved to a
ledger table with its own task.
