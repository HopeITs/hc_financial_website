# IT request: New chart of accounts layout and a separate donor list

| | |
|---|---|
| Requested by | CT department |
| Department | ct |
| Date | 2026-11-13 |
| Website / page | Financial website: chart of accounts, projects, operations, reports |
| Branch | ct/chart-of-accounts-update |
| Status | Waiting for IT |

## 1. What is wanted (plain language)

Finance wants the chart of accounts reorganised:

1. **Cash grouped by currency first, then city.** Cash → "Cash – USD" (1101) → each city's USD safe; Cash → "Cash – SYP" (1102) → each city's SYP safe and tills. The city groups 1110–1150 go away.
2. **Transfer company in its own group** directly under Assets (1160, with 1161), no longer inside "Cash and cash equivalents".
3. **The "in transit from the transfer company" accounts (11x3, 11x4) are removed.**
4. **Income is a single account, 4001 "Accounts receivable".** All grant, donation and other income accounts (4100–4602) are removed.
5. **A separate donor list, in its own menu.** Because the donors were the income accounts, finance wants a donor list of its own. It must **not** be part of the chart of accounts: it gets its own item in the main menu (for example "Donors"), where donors are added, renamed and deactivated. The user links a donor to a transaction **when needed**. The donor is an optional choice on a transaction line, usually next to the project on lines that post to Accounts receivable (4001), and it is never required.

## 2. What was found (read-only) and what is already on the branch

Already done on the branch (needs IT review):
- `database/seeders/data/chart_accounts.json`: the new layout (125 accounts). **Account codes do not change**, so 1161 and the safes keep their numbers.
- `database/migrations/2026_11_13_090000_restructure_chart_of_accounts.php`: applies the same layout to the live chart. A removed account that already has lines is **deactivated**. An unused one is **soft-deleted**. Its `down()` does nothing, so **take a backup before running it**.
- `lang/en|ar/ledger.php`: statement line "Accounts receivable".
- `DemoVouchersSeeder` and the tests now use 4001 in place of 4101/4301/4107. City header 1110 became 1101 in the tests. `ChartOfAccountsSeederTest` matches the new layout.
- **The tests could not be run in this session.** Installing the PHP packages is blocked here, so please run the full suite.

Still needed from IT (backend, so not done by CT):
- **Donor list:** a new table, model and its own page with a main-menu entry, separate from the chart of accounts screen. It could start from the current income account names: ACN, GIZ, Caritas Poland … It also needs an optional `donor` field on voucher lines that the user fills in when needed (not required). `GlAccount::donorNames()` (used by `ProjectEditor` and `OperationalReport`) must read the new list. **Until this exists, the donor drop-downs only show "Accounts receivable" and projects with a donor such as "ACN" fail validation. Do not merge the income part before the donor list.**
- **Donor report** (`DonorReport`) and the financial statements currently split income by account and statement line. They need to split by the donor on the line.
- **In-transit removal:** `config/ledger.php` `in_transit_pattern` (`/^11[1-5][34]$/`), the month-end rule 12 (`MonthClosesOnlyWhenClearingIsZero`), `TreasuryPosition` (in-transit column), `Operations` and `Transfers` still expect these accounts. Decide whether to remove that logic.
- Tests that still use in-transit accounts or the removed income account 4501 need IT's decision: `TreasuryPositionTest` (1113/1114), `TransfersTest` (1113), `ApprovalAmendAccountAndCloseRulesTest` (rule 12 uses 1113), `OperationsTest` ("asks for the center", uses 4501), `BridgePagesTest` / `lang/*/bridge.php` ("Income account 4101 to 4602").
- `config/ledger.php` comments and `app/` doc comments that mention 11x3/11x4 or the city groups.

## 3. Questions for IT (answer BEFORE any work starts)

1. Is this change possible and safe to do? **(Yes / No / Only with changes)**
2. Which backend files must change (controller, model, route, config, migration)? See the list above.
3. Does it affect the database or existing data? Yes. The migration re-parents accounts and deactivates or deletes the removed ones, so take a backup first.
4. Does it affect other pages or other websites that share this code?
5. Does the view need new data from the backend? Yes: the donor list, and a donor choice on transaction lines that post to 4001.
6. Is there a front-end-only alternative? No.
7. Any security or permission impact? Who may edit the donor list?
8. Who does the work: IT directly, or the department under IT supervision?
9. Needs staging test, deploy window, or rollback plan? Recommended (backup before the migration).
10. Priority / deadline:

Extra questions:

- If live vouchers already post to grant accounts (4101 …), should their history be moved to 4001 with the donor filled in, or stay on the old (deactivated) accounts?
- If an in-transit account has a balance today, should it be cleared before the migration runs?
- Should the transfer company (1161) still count as "Cash and cash equivalents" on the financial statements? The branch keeps it there and only moves it in the tree.

## IT answers

| | |
|---|---|
| Decision | Approved / Approved with changes / Rejected |
| Answered by | |
| Date | |
| Implemented by | |
| Pull request | |

<Notes and answers to the questions above:>
