# Decisions log (Task 01 sign-off pack)

Prepared 2026-10-22 for the sign-off session with the Financial Manager (both developers attend). Source of truth for each row is `docs/PLAN-hcsyria_financial_test.md` (sections 3.2, 6.5-6.9, 10). Nothing already decided is reopened here.

**Status values:** `Decided` (confirmed by the Financial Manager or the IT manager), `Default applied` (not yet confirmed; the default applies after one working day and the item is marked *reviewable*), `Open` (needs input before the task that depends on it).

**Owner:** FM = Financial Manager, IT = IT manager, Dev = the two developers.

## 1. Round 1 (`QUESTIONS-CFO-AR-answered.docx`, plan 6.8)

| ID | Decision | Date | Owner | Status | Unblocks |
|----|----------|------|-------|--------|----------|
| R1-1 | Two general accounts per city, one USD and one SYP (`GEN-xxx-USD`, `GEN-xxx-SYP`) | round 1 | FM | Decided | Projects seed (T2.x), Home, treasury |
| R1-2 | `ALC` = split of a salary or invoice over projects; general-to-project move = `TRF` with a sub-type | round 1 | FM | Decided | `PostVoucher`, transfers (T5.2) |
| R1-2b | Salary split percentages live with the monthly payroll, editable | round 1 | FM | Decided | `gl_payroll_splits`, `salaries` |
| R1-3 | A project balance exists per currency and per cash account | round 1 | FM | Decided | Project balances, "where are the project funds" report |
| R1-4 | Project gets contract number, start date, end date, currency; no separate grant entity | round 1 | FM | Decided | `gl_projects` migration |
| R1-4b | Spending after the end date is a warning only | round 1 | FM | Decided | Voucher builder |
| R1-5 | Keep all budget versions | round 1 | FM | Decided | `gl_budget_lines` versioning |
| R1-6 | Hawala commission is an organisation expense, not a project expense | round 1 | FM | Decided | Chart seed (account in R3) |
| R1-6b | Keep original EUR amount, actual rate and commission per transfer | round 1 | FM | Decided | `gl_voucher_lines` columns |
| R1-7 | Monthly hawala pool reconciliation report | round 1 | FM | Decided | Reports group |
| R1-8 | Cash received without a project goes to the general account first, compensated later; report per city | round 1 | FM | Decided | `is_uncompensated`, report |
| R1-9 | Compensation at the city rate approved by the FM; difference to 5503 (this is D9) | round 1 | FM | Decided | Allocation rates screen |
| R1-10 | General-to-project move without cash is a `TRF` | round 1 | FM | Decided | `PostVoucher` |
| R1-11 | Project manager requests live inside the system with statuses, linked to the voucher | round 1 | FM | Decided | `requests` module |
| R1-12 | Income-producing project has its own cash box and income account; income is unrestricted; cash to the city till by manual voucher | round 1 | FM | Decided (monthly frequency: see open item O-1) | Chart seed, `TRF` |
| R1-13 | Micro projects: loan, grant, repayment schedule, collection report | round 1 | FM | Decided | `gl_micro_loans`, `gl_micro_instalments` |
| R1-14 | Church hand-over is an expense at hand-over; church admin cost is a fixed amount per church | round 1 | FM | Decided | Budget lines, no advance tracking |
| R1-15 | Recovered instalments: fund source "Recovered loans" tagged with the donor, still under the donor's restrictions | round 1 | FM | Decided | Voucher line rules |
| R1-16 | Advances have no deadline; open-advances report only; difference is a receivable | round 1 | FM | Decided | Reports group |
| R1-17 | Personal accounts outside the financial statements | round 1 | FM | Decided | Chart seed, `statements` |
| R1-18 | Money-changer difference and commission both go to 5503 | round 1 | FM | Decided | `PostVoucher` |
| R1-19 | The four mockup sheets are working sheets only; no new "Allocation & Projects" report | round 1 | FM | Decided | Scope |
| R1-20 | Reports in both currencies | round 1 | FM | Decided | Report screens |

## 2. Rounds 2 and 3 (`ANSWERS-CFO-2-AR.md`, `QUESTIONS-CFO-3-AR.docx`, plan 6.9)

| ID | Decision | Date | Owner | Status | Unblocks |
|----|----------|------|-------|--------|----------|
| R2-1 | Four transfer types: general USD to project; general SYP to project (USD equivalent deducted); project back to general; project to project. Project cash to city cash is not a transfer type | round 2 | FM | Decided | `gl_vouchers.transfer_kind`, `PostVoucher` |
| R2-2 | Cash without a project is compensated after one to three months; no fixed period, no alerts; report with buckets under 30, 30-90, over 90 days | round 2 | FM | Decided | Uncompensated report |
| R2-3 | SYP reports show both currencies; the SYP rate is typed per report; only USD is converted | round 2 | FM | Decided | `tb`, `projects`, `statements` |
| R2-4 | One personal-accounts chart account with a mandatory Contact tag, outside the statements | round 2 | FM | Decided | Chart seed |
| R2-5 | Report "where are the project funds": balance split by cash box | round 2 | FM | Decided | Reports group |
| R2-6 | Salary split percentages come from the monthly payroll sheet; a mid-month change applies from the next month; no day proration | round 2 | FM | Decided | `salaries` |
| R3-1 | New account 5504 "Hawala company commission" under financial expenses, next to 5503 | round 3 | FM | Decided (number confirmation: see O-2) | Chart seed (T2.4) |
| R3-2 | One monthly request per project (amount per budget line); an extra amount is a separate request; 2 or 3 quotes for large maintenance, fixed assets and electronics; edits locked at approval | round 3 | FM | Decided | `requests` module |
| R3-3 | Spending without an approved request is allowed under USD 500 per voucher (SYP at its own voucher rate); exceeding a budget line is a warning only | round 3 | FM | Decided | `PostVoucher` |
| R3-4 | FM approves; above USD 2,000 the CEO approves after the FM; the CEO is a new role "General Manager" held in a web-only table (legacy `role` stays read-only) | round 3 | FM | Decided | `RoleId::GeneralManager`, approvals |
| R3-5 | Personal accounts: opening balance zero, no legacy balances carried | round 3 | FM | Decided | Opening / `dbload` |
| R3-6 | Monthly project-to-city cash transfer is an ordinary `TRF` prepared by the Accountant; rare ("only by chance"); no automatic month-end voucher | round 3 | FM | Decided (see O-1) | Nothing to build |

## 3. Mockup decisions D1-D11 and Q-a to Q-e

The mockup's own decisions page is not in this repository. Only the decisions the plan quotes are written out; the others are listed so the FM can sign them in the session. **Do not treat an empty wording as decided.**

| ID | Decision (as the plan states it) | Owner | Status | Unblocks |
|----|----------------------------------|-------|--------|----------|
| D1 | Each voucher line carries `lira_type` (old / new) | FM | Decided (in plan 3.2) | `gl_voucher_lines` migration |
| D2 | Hybrid start: books start 1/1/2027 on temporary `OPN` vouchers; 2026 is loaded later, reconciled and closed; then the carry-forward replaces the temporary voucher (status `Replaced`) | FM | Decided (in plan 3.2) | `opening`, `dbload` |
| D3 | Wording not in the repository | FM | Open: read from the mockup `decisions` page and sign | to be set |
| D4 | Wording not in the repository | FM | Open: as D3 | to be set |
| D5 | Wording not in the repository | FM | Open: as D3 | to be set |
| D6 | Wording not in the repository | FM | Open: as D3 | to be set |
| D7 | Operations in SYP with stored rate 0 (about 4,618) get their dollar value at the project average rate of the operation date | FM | Decided (in plan 6.5) | `fixusd` |
| D8 | About 470 old exchange groups: one rate, `TRF` vouchers with no 5503 line | FM | Decided (in plan 6.5) | `legacy` |
| D9 | Compensation at the approved city rate; "allocate at average cost" switch stays off | FM | Decided (= R1-9) | Allocation rates |
| D10 | Project reports exclude 5703 depreciation from expenses | FM | Decided (in plan 6.5) | `projects` |
| D11 | 5503 exchange differences are shown under financial expenses | FM | Decided (in plan 6.5) | `statements` |
| Q-a | Wording not in the repository | FM | Open: read from the mockup and sign | to be set |
| Q-b | Merge the closed Jouliana till into 1142 | FM | Decided (in plan 6.5) | `merges` |
| Q-c | Write off the Al-Nouzha till with an adjustment voucher | FM | Decided (in plan 6.5) | `merges` |
| Q-d | Wording not in the repository | FM | Open: as Q-a | to be set |
| Q-e | Wording not in the repository | FM | Open: as Q-a | to be set |

## 4. IT and technical decisions (plan section 10)

| ID | Decision | Owner | Status | Unblocks |
|----|----------|-------|--------|----------|
| T-1 | Migration reads the live legacy database through the read-only `legacy` connection; Windows program frozen before the final run | IT, Dev | Decided | `dbload` |
| T-2 | Eight new SYP tills (1125-1127, 1135, 1145-1147, 1155) and the rename of 1115 to Science Zone Aleppo are approved | FM | Decided | Chart seed |
| T-3 | Attachments are read over FTP (`ATTACH_FTP_*` in `.env`), moved to the private disk; Windows program retired for new entries at go-live | IT | Decided | `attach` step |
| T-4 | Spatie removed; roles come from the legacy `role` table | Dev | Decided (done) | Permissions |

## 5. Still open (applies after one working day, then marked *reviewable*)

| ID | Item | Default if unanswered | Blocks |
|----|------|-----------------------|--------|
| O-1 | Conflict: round 1 (Q12) says project cash goes to the city till **monthly**; round 3 says it happens only "by chance" | Ordinary manual `TRF`, no monthly schedule, no reminder | Nothing in week 1 |
| O-2 | Confirm 5504 is not used by another account in the mockup chart | Use 5504; if taken, the next free number next to 5503 and update the plan | T2.4 chart seed |
| O-3 | The two project names corrupted in the mockup export | Seed the projects with their codes and the names found in the legacy `account` table; fix the names later | Projects seed |
| O-4 | Wording of D3-D6, Q-a, Q-d, Q-e (not in this repository) | Cannot default: someone must paste them from the mockup. No task in week 1 depends on them | Nothing identified yet |
| O-5 | Read-only FTP user for the attachment migration | Start rehearsal on the test root with the current account; change its password after go-live | `attach` (week 6) |
| O-6 | Production FTP root (only `ftp://hcsyria.org/financial/files/test` is known) | Assume `.../financial/files`; IT confirms by comparing file names with `action_attachments` | `attach` (week 6) |
| O-7 | Rotate the FTP and MySQL passwords found in the Windows source | After go-live | Security |
| O-8 | Revision of the 156-account seed: the new account numbers to add (5504, personal-accounts account, project cash boxes under 111x/112x) | Numbers proposed by Dev A at T2.4 and shown to the FM | T2.4 |

## 6. External inputs required

- Read-only FTP user for attachments, and the production FTP root (O-5, O-6).
- Production database host and a least-privilege MySQL user (script in `docs/db-users.sql`).
- Numbers for the new chart accounts (O-2, O-8).
- The mockup's `decisions` page text for O-4, and the two corrupted project names (O-3).

## 7. Sign-off

| Name | Role | Date | Signature |
|------|------|------|-----------|
| | Financial Manager | | |
| | Developer A | | |
| | Developer B | | |
