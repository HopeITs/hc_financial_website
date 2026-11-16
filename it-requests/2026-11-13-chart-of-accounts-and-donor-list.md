# IT request: New chart of accounts layout, donor list and funding contracts

| | |
|---|---|
| Requested by | CT department |
| Department | ct |
| Date | 2026-11-13 (updated 2026-11-16) |
| Website / page | Financial website: chart of accounts, projects, operations, reports |
| Branch | ct/chart-of-accounts-update |
| Status | Waiting for IT |

## 1. What is wanted (plain language)

### How the money moves (confirmed by finance on 2026-11-16)

1. A donor signs a **funding contract** with CHC. One contract can cover **several projects**.
2. The donor sends the money. It arrives in a **bank account abroad** (China or Lebanon, in USD or EUR). Sometimes the donor hands it **directly to the transfer company**.
3. Money is withdrawn from the bank and deposited with the **transfer company**, which holds **USD only**. There is no bank transfer from Lebanon to Syria at present.
4. The transfer company delivers it to the **city safes**.
5. Every receipt from a donor and every payment is linked to the **funding contract** details.

| Step | Debit | Credit |
|---|---|---|
| Funding contract signed | 1210 Accounts receivable – donors | 4001 Grant and donation income |
| Money arrives at the bank (or the transfer company) | Bank 117x (or 1161) | 1210 Accounts receivable – donors |
| Withdrawn from the bank, deposited with the transfer company | 1161 | Bank 117x |
| Sent to a city | City safe | 1161 |

### The chart of accounts

1. **Cash grouped by currency first, then city.** Cash → "Cash – USD" (1101) → each city's USD safe; Cash → "Cash – SYP" (1102) → each city's SYP safe and tills. The city groups 1110–1150 go away.
2. **Transfer company in its own group** directly under Assets (1160, with 1161). USD only.
3. **Bank accounts in their own group** directly under Assets (1165), by currency first:
   - 1166 USD: Fransabank (1171), Bank of Ruifeng China (1173), Bloom Bank Lebanon (1174), Bank of Beirut Lebanon (1175)
   - 1167 EUR: Bank of Ruifeng (1176), Bloom Bank (1177), Bank of Beirut (1178)
   - 1168 SYP: Fransabank (1172)
   - The old group 1170 "Bank accounts in Syria" goes away.
4. **The "in transit from the transfer company" accounts (11x3, 11x4) are removed.** Finance does not want an in-transit account between the bank and the transfer company either: the money shows in the bank balance until it is deposited.
5. **Income is a single account, 4001 "Grant and donation income".** All grant, donation and other income accounts (4100–4602) are removed.
6. **1210 "Accounts receivable – donors"** (an asset, under 1200) holds what each donor still owes under a signed contract.

### Donors and funding contracts

7. **A separate donor list, in its own menu.** It must **not** be part of the chart of accounts. Donors are added, renamed and deactivated there.
8. **Funding contracts.** A contract belongs to one donor, has an amount, a currency, dates and instalments, and covers one or more projects with a share for each. Receipts from the donor and payments are linked to the contract.
9. The donor and the contract are chosen on a transaction **when needed** (optional, never required for unrelated lines).

## 2. What was found (read-only) and what is already on the branch

Already done on the branch (needs IT review):
- `database/seeders/data/chart_accounts.json`: the new layout (135 accounts). **Existing account codes do not change**, so 1161, 1171, 1172 and the safes keep their numbers.
- `database/migrations/2026_11_13_090000_restructure_chart_of_accounts.php`: applies the same layout to the live chart. A removed account that already has lines is **deactivated**. An unused one is **soft-deleted**. Its `down()` does nothing, so **take a backup before running it**.
- `lang/en|ar/ledger.php`: statement line "Grants and donations".
- `DemoVouchersSeeder` and the tests now use 4001 in place of 4101/4301/4107. City header 1110 became 1101 in the tests. `ChartOfAccountsSeederTest`, `ChartTest` and `TreasuryPositionTest` match the new layout. `MultiCurrencyTest` builds its test EUR account as 1199, because 1173 is now a real account.
- **The tests could not be run in this session.** Installing the PHP packages is blocked here, so please run the full suite.

Notes on the numbering:
- The bank group uses 1165–1168 for its headers so that Fransabank can keep 1171/1172 and every parent still has a smaller code than its children. If the database holds no real transactions yet, IT may prefer to renumber the banks into a clean block (for example 1300). That is IT's choice.
- If someone already added an account with a code from 1173 to 1178 or 1210 in the live chart, the migration skips that code. Please check before running it.

Still needed from IT (backend, so not done by CT):
- **Donor list:** a new table, model and its own page with a main-menu entry (the menu already has a placeholder "donors" under People). It could start from the current income account names: ACN, GIZ, Caritas Poland … `GlAccount::donorNames()` (used by `ProjectEditor` and `OperationalReport`) must read the new list. **Until this exists, the donor drop-downs only show "Grant and donation income" and projects with a donor such as "ACN" fail validation. Do not merge the income part before the donor list.**
- **Funding contracts:** a new table for contracts and a contract–project table (share per project). Today the project itself carries the donor, the donor currency and rate, and the contract dates (`gl_projects`). With one contract over several projects these move to the contract.
- **Donor and contract on voucher lines:** optional fields on `gl_voucher_lines`, offered on lines that post to 4001 and 1210, and on payments.
- **Donor receipt flow:** the "hawala receipt" (`Voucher::isHawalaReceipt`, rule 15 `HawalaReceiptIsConsistent`, `Operations`) assumes a donor receipt debits 1161 directly. The receipt must also be possible into a bank account abroad, in USD or EUR, followed by a withdrawal from the bank into 1161. `Operations` and `Transfers` pick cash accounts by city, and these banks have no city.
- **Reports:** `DonorReport` and the financial statements split income by account and statement line. They need to split by the donor and contract on the line. A new report "contract: signed, received, still owed, spent" would come from 1210 and 4001.
- **In-transit removal:** `config/ledger.php` `in_transit_pattern` (`/^11[1-5][34]$/`), the month-end rule 12 (`MonthClosesOnlyWhenClearingIsZero`), `TreasuryPosition` (in-transit column), `Operations` and `Transfers` still expect these accounts. Decide whether to remove that logic.
- **Month end:** `MonthEnd` counts each bank against its statement. The six new bank accounts join that list.
- Tests that still use in-transit accounts or the removed income account 4501 need IT's decision: `TreasuryPositionTest` (1113/1114), `TransfersTest` (1113), `ApprovalAmendAccountAndCloseRulesTest` (rule 12 uses 1113), `OperationsTest` ("asks for the center", uses 4501), `BridgePagesTest` / `lang/*/bridge.php` ("Income account 4101 to 4602"), `ProjectEditorTest` (donorNames).
- `config/ledger.php` comments and `app/` doc comments that mention 11x3/11x4, 1170 or the city groups.

### Old donor accounts still showing on the live site (reported 2026-11-16)

Finance still sees **Caritas, OO and MCC** under Income on the live chart of accounts. Two reasons:

1. **This branch is not merged and the migration has not run yet**, so the live chart is still the old one.
2. **After the migration they would still show.** An income account that already has transactions is only deactivated (to keep its history), and the chart screen lists inactive accounts with an "Inactive" badge. Caritas, OO and MCC most likely have transactions.

Finance wants **no donor accounts under Income at all**. Suggested way, for IT to decide:

- **Move the balances:** before deactivating, post one approved reclassification journal (JV) per old income account: debit the old account, credit 4001, same project, with the donor filled in once the donor field exists. The old accounts then have a zero balance, and all income sits on 4001. Past vouchers are not rewritten.
- **Hide inactive accounts:** the chart screen hides inactive accounts by default, with a "Show inactive accounts" switch to see them for history and audit. This changes `app/Livewire/Chart.php` and `resources/views/livewire/chart.blade.php`.

## 3. Questions for IT (answer BEFORE any work starts)

1. Is this change possible and safe to do? **(Yes / No / Only with changes)**
2. Which backend files must change (controller, model, route, config, migration)? See the list above.
3. Does it affect the database or existing data? Yes. The migration re-parents accounts, adds new ones and deactivates or deletes the removed ones, so take a backup first.
4. Does it affect other pages or other websites that share this code?
5. Does the view need new data from the backend? Yes: the donor list, the funding contracts, and a donor and contract choice on transaction lines.
6. Is there a front-end-only alternative? No.
7. Any security or permission impact? Who may edit the donor list and the contracts?
8. Who does the work: IT directly, or the department under IT supervision?
9. Needs staging test, deploy window, or rollback plan? Recommended (backup before the migration).
10. Priority / deadline:

Extra questions:

- Live vouchers already post to old income accounts (at least Caritas, OO and MCC). Do you agree with the reclassification journal to 4001 above, rather than rewriting past vouchers? Which date should it carry?
- If an in-transit account has a balance today, should it be cleared before the migration runs?
- The transfer company (1161) and the banks still count as "Cash and cash equivalents" on the financial statements. Is that right for IT's reports too?
- Income is recognised when the contract is signed (debit 1210). Should a contract in EUR keep its receivable in EUR and book the exchange difference when the money arrives?
- Bank charges on the accounts abroad go to 5501 "Bank charges and wire transfer fees". With or without a project?

## IT answers

| | |
|---|---|
| Decision | Approved / Approved with changes / Rejected |
| Answered by | |
| Date | |
| Implemented by | |
| Pull request | |

<Notes and answers to the questions above:>
