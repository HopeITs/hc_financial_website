# IT request: Follow-up — what is still open after #7, #8 and #9

| | |
|---|---|
| Requested by | CT department (with the finance team) |
| Department | ct |
| Date | 2026-11-16 |
| Website / page | Financial website: chart of accounts, entries, treasury, month end, projects, reports |
| Branch | ct/it-follow-up |
| Status | Waiting for IT |

## 1. What is wanted (plain language)

IT merged #7, #8 and #9 and ran `migrate` and `seed`. Only #7 contained a change to the program (the chart of accounts and its data migration). **#8 and #9 were written requests**: merging them does not do the work, and `migrate` / `seed` do not do it either. Finance still sees things that were asked for and not done. This file lists everything still open, in the order it should be done.

### Step 1: Clean up the data (request #8, `it-requests/2026-11-13-chart-of-accounts-and-donor-list.md`, "Start from zero")

The live site holds **test data**. Finance wants to start from zero.

1. Take a full database backup.
2. Delete all financial transactions:
   - All vouchers, **including opening balances**, with lines, audit, attachments (rows and stored files), till merges.
   - Voucher numbering restarts at 0001.
   - Payment requests with lines, quotes (and stored files), approvals and audit.
   - Micro loans and instalments.
   - Month-end counts, closes and the close log.
   - Notifications, saved drafts and assistant proposals that only point at the deleted items.
3. **Remove the old accounts that the #7 migration only deactivated.**
   - The migration ran while the old accounts still had transactions, so it deactivated them instead of removing them. Running `migrate` again will not touch them.
   - After step 2, soft-delete every account that is **inactive and has no lines**: the old income accounts and groups (4100–4602: Caritas, OO, MCC …), the in-transit accounts (11x3, 11x4), the old city groups (1110–1150) and the old bank group 1170.
   - **Done by CT on this branch, for IT to approve and run:** `database/migrations/2026_11_16_090000_remove_old_deactivated_accounts.php`, with `tests/Feature/RemoveOldDeactivatedAccountsTest.php`. It only soft-deletes an old account that is inactive, has no lines and no remaining child. Anything still in use is left alone.
   - **Finance confirmed on 2026-11-16 that the transactions were deleted**, so this migration can run now.
4. Check that the new accounts exist: 1101, 1102, 1165–1168, 1173–1178, 1210, 4001.
5. Decide whether the legacy import tables (`gl_legacy_map`, `gl_import_batches`, `gl_import_issues`) are cleared too.

**Kept:** chart of accounts, projects, programs, budget lines, cities, currencies, allocation rates, payroll splits, users, roles and permissions.

**How finance checks it:** on the chart of accounts screen, Income shows only "4001 Grant and donation income", with no inactive accounts under it.

### Step 2: Make the program match the new chart (listed in #7)

1. **In-transit accounts are gone.**
   - The code still expects them: `config/ledger.php` `in_transit_pattern`, month-end rule 12 (`MonthClosesOnlyWhenClearingIsZero`), `TreasuryPosition` (in-transit column), `Operations`, `Transfers`.
   - Remove or adapt that logic.
2. **Donor drop-downs.**
   - `GlAccount::donorNames()` reads the income accounts, so the project editor and the operational report only offer "Grant and donation income".
   - Existing projects with a donor such as "ACN" fail validation when saved.
   - Fixed for good by the donor list (step 3). Until then, a temporary fix is needed so projects can be saved.
3. **Donor money received in the banks abroad.**
   - The hawala receipt (`Voucher::isHawalaReceipt`, rule 15 `HawalaReceiptIsConsistent`, `Operations`) assumes a donor receipt debits 1161.
   - It must also work into the banks 1173–1178 (USD or EUR), followed by a withdrawal from the bank into 1161.
   - The new banks have no city, and `Operations` / `Transfers` pick cash accounts by city, so they are not offered today.
4. **Month end:** the new bank accounts (1173–1178) join the bank reconciliation list.
5. **Hide inactive accounts** on the chart of accounts screen by default, with a "Show inactive accounts" switch (`app/Livewire/Chart.php`, `resources/views/livewire/chart.blade.php`).
6. **Bridge pages:** CT updated the mapping text (`lang/*/bridge.php`: donor → donor list and 4001; tills → 1101 / 1102 groups) and its test. The text of rule 12 (in-transit accounts) is left for IT, as it follows the code.
7. **Tests:** run the full suite (it could not be run in the CT session) and fix the tests that still use removed accounts:
   - `TreasuryPositionTest` (1113/1114)
   - `TransfersTest` (1113)
   - `ApprovalAmendAccountAndCloseRulesTest` (rule 12)
   - `OperationsTest` (4501)
   - `BridgePagesTest` with `lang/*/bridge.php` ("Income account 4101 to 4602")
   - `ProjectEditorTest` (`donorNames`)

### Step 3: The base for donors and funding contracts (request #9, `it-requests/2026-11-16-donors-and-funding-contracts.md`)

1. New tables and models: donors, contracts, contract–project split, expected instalments, reports to the donor, budget versions.
2. New columns:
   - `gl_budget_lines`: contract, version, donor-line name.
   - `gl_voucher_lines`: optional donor and contract, plus the budget overrun reason and approval.
3. Move the donor fields of `gl_projects` to the contract.
4. `GlAccount::donorNames()` reads the new donor list.
5. Permissions in `config/permissions.php` for the new screens (see 1.1 and 1.15 of the request).
6. Approval chain: the budget overrun step, and the contract and closing approvals.
7. Answer the questions in section 3 of that request.

**After step 3, CT builds the Donors and Funding contract screens** as in the approved preview (`it-requests/2026-11-16-donors-and-funding-contracts-preview.html`). CT cannot start them before: the screens need the tables and models, which only IT may add.

## 2. What was found (read-only)

- `origin/main` after #9: the only code change since #6 is #7 (`chart_accounts.json`, the migration `2026_11_13_090000_restructure_chart_of_accounts.php`, `DemoVouchersSeeder`, translations, tests). #8 and #9 added files under `it-requests/` only.
- `DatabaseSeeder` only runs `ChartOfAccountsSeeder`, which **adds missing accounts and never removes or changes existing ones**. So `seed` cannot remove the old accounts.
- The #7 migration deactivates a removed account that has lines and soft-deletes one that has none. It has already run, so it will not run again.

## 3. Questions for IT (answer BEFORE any work starts)

1. Finance says the deletion of #8 was done. Was a backup taken first?
2. Which of the steps above will IT do, and in what order?
3. For step 1.3: a one-off command (repeatable on staging first) or by hand on the database?
4. For step 2.2: what temporary fix for the donor drop-down until the donor list exists?
5. For step 3: can CT start building the screens on a branch once the models exist, before the whole of step 3 is finished?
6. Priority / deadline:

## IT answers

| | |
|---|---|
| Decision | Approved / Approved with changes / Rejected |
| Answered by | |
| Date | |
| Implemented by | |
| Pull request | |

<Notes and answers to the questions above:>
