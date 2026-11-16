# IT request: Donors and funding contracts

| | |
|---|---|
| Requested by | CT department (with the finance team) |
| Department | ct |
| Date | 2026-11-16 |
| Website / page | Financial website: new "Donors" and "Funding contracts" screens, projects, budgets, entries, reports |
| Branch | ct/donors-and-contracts |
| Status | Waiting for IT |

This request follows `it-requests/2026-11-13-chart-of-accounts-and-donor-list.md` (merged in #7). That request agreed on the money flow and on one income account. Every point below was agreed with finance, question by question, on 2026-11-16.

## 1. What is wanted (plain language)

### 1.1 Donor list (its own menu, not in the chart of accounts)

Each donor has:

| # | Field | Example |
|---|---|---|
| 1 | Name in English | Caritas Poland |
| 2 | Name in Arabic | كاريتاس بولندا |
| 3 | Short code | CARPL |
| 4 | Donor type | Church agency / Government or public body / Foundation / Church or parish / Individual |
| 5 | Country | Poland |
| 6 | Usual currency | EUR |
| 7 | Contact person, email and phone | |
| 8 | Notes | |
| 9 | Active / inactive | |

Who may do what:
- **Add, edit, deactivate:** Financial Manager, Head Office.
- **View only:** General Manager, Accountant, Program Manager, Project Manager.
- **No access:** Cashier.

The menu already has a placeholder screen `donors` (People group, `people/donors`).

### 1.2 Funding contracts

Each contract has:

| # | Field | Example |
|---|---|---|
| 1 | Our contract number | CON-2026-001 |
| 2 | Donor's contract number | ACN-SY-4471 |
| 3 | Donor | Caritas Poland |
| 4 | Contract title | Education support 2026 |
| 5 | Signing date | 2026-02-01 |
| 6 | Start and end dates | 2026-03-01 – 2027-02-28 |
| 7 | Contract currency | EUR |
| 8 | Total amount | 120,000 |
| 9 | Restriction | Restricted to projects / Unrestricted |
| 10 | Signed contract (PDF attachment) | |
| 11 | Status | Draft / Signed / Running / Ended / Cancelled |
| 12 | Notes | |
| 13 | Fixed contract rate (USD per unit), see 1.5 | 1.15 |

### 1.3 Contract split over projects

- One contract can cover **several projects**.
- The share of each project is entered **as an amount in the contract currency**.
- The shares must add up to the contract total.

### 1.4 Expected instalments

Each contract has a schedule of expected instalments:
- Instalment number
- Expected date
- Expected amount (contract currency)
- Condition (optional, for example "after the interim report is accepted")
- **Conditional: yes / no** (drives income recognition, see 1.6)

For each instalment the program shows **whether it arrived, when, and how much**, and warns when an instalment is late.

### 1.5 Exchange rates (contract not in USD)

Both rates are used:
- **Fixed contract rate:** used for the donor report and the budget, so the project budget in USD never moves with the market.
- **Rate on the day each instalment arrives:** used in the accounts, so the books match what reached the bank.
- **The difference** goes to **5503 Currency exchange differences**.

### 1.6 When income is recognised

- **Unconditional instalments:** recognised **when the contract is signed**: debit **1210 Accounts receivable – donors**, credit **4001 Grant and donation income**.
- **Conditional instalments:** recognised **when the condition is met** (same entry, on that date).
- When the money arrives: debit the bank account (117x) or the transfer company (1161), credit 1210.

### 1.7 A received instalment is split over the projects automatically

- When an instalment arrives, the program proposes the split over the projects **in the same proportions as the contract** (for example 60,000 of a 120,000 contract split 80,000 / 40,000 gives 40,000 / 20,000).
- The user can change the split before saving.

### 1.8 Linking spending to a contract

- When money is spent on a project:
  - **The project has one contract:** the program fills in the contract by itself.
  - **The project has several contracts:** the user must choose the contract.
- So every expense appears in the right donor's report.

### 1.9 Budgets per contract and project

- Budget lines belong to **a contract and a project together**. Each donor sees its own approved budget against actual spending.
- The project's full budget is the sum of its contracts' budgets.
- **Line names are free text** (the donor's own wording, for example "Staff costs" for one donor and "Personnel" for another) and may differ between contracts and projects.
- **Each budget line is linked to exactly one expense posting account** in the chart (as today: `gl_budget_lines.account_code`).
- When a donor line covers several kinds of cost (salaries, insurance, transport), it is entered as **several sub-lines, one account each**. The donor report adds them up under the donor's original line name, so a line needs a "donor line" or group name for the report.

### 1.10 Going over a budget line

- **At 80%** of a line: a warning.
- **Over 100%:** the voucher can be saved, but it is **only approved with a special approval by the Financial Manager**, with a **written reason**.

### 1.11 Budget revisions

- Each revision creates **a new version** of the budget: original, revision 1, revision 2 …
- Each version keeps its date, its reason and the donor's approval (attachment).
- Reports show the original and the revised budget side by side.

### 1.12 Reporting schedule for the donor

Each contract has its list of reports due:
- Type: financial / narrative / both
- Period covered
- Due date
- Status: not sent / sent / accepted / changes requested
- Attachment of the report sent
- Link to the instalment that waits for it, if any

The program sends a notification **14 days before** a due date.

### 1.13 Closing a contract and the unspent balance

All four cases happen:
- **(a) Return to the donor:** posted to **2104 Amounts to return to donors** until it is paid.
- **(b) No-cost extension:** the end date changes, spending continues.
- **(c) Move to another project or contract:** with the donor's written approval.
- **(d) Stays with CHC:** becomes unrestricted funds, when the donor allows it.

A contract can only be closed after choosing what happens to the balance and attaching the donor's approval. After closing, no new spending is accepted on it.

### 1.14 Donations without a contract

- **One-off donations** (a church, an individual, in-kind): posted directly to 4001 with the **donor and the project** on the line. No contract.
- **Regular donors:** one **yearly general contract** per donor (for example "Church X donations 2026"). Each donation during the year is added to it.

So the donor (and, when there is one, the contract) are fields on the voucher line.

### 1.15 Contract permissions

| Action | Who |
|---|---|
| Register and edit a contract (draft) | Accountant, Financial Manager, Head Office |
| Approve a contract | Financial Manager |
| Approve a budget revision | Financial Manager |
| Close a contract | Financial Manager, then General Manager |
| View contracts and their reports | General Manager, Program Manager, Project Manager (own projects only) |
| Cashier | No access |

A contract is only usable in entries and reports **after it is approved**.

## 2. What was found (read-only)

Nothing was coded for this request. CT cannot add tables or change existing backend files. What exists today and what would change:

- **Projects** (`gl_projects`) carry `donor`, `donor_currency`, `donor_rate` and contract dates. These move to the contract. A project keeps its own data (name, city, program, restriction).
- **Budget lines** (`gl_budget_lines`) belong to a project with a free `donor` text and one `account_code`. They need a contract, a version, and a donor-line name for grouping.
- **Donor names** come from `GlAccount::donorNames()` (income accounts). They must come from the new donor list. `ProjectEditor` and `OperationalReport` use it.
- **Voucher lines** (`gl_voucher_lines`) need optional `donor` and `contract` fields, plus the override reason and approval when a budget line is exceeded.
- **Approvals** (`ApprovalChain`, `ApprovalPolicy`) need the extra Financial Manager step for budget overruns, and the contract and closing approvals.
- **Notifications** (`web_notifications`) exist and can carry the late-instalment and report-due alerts.
- **Reports:** `DonorReport` is per project today. It needs a per-contract view: budget (original and revised) against actual spending by donor line, received against expected instalments, and the exchange difference.
- **Permissions:** `config/permissions.php` needs the new screens: donors, contracts.
- **Exchange rate:** `ProjectAverageRate` and the hawala receipt keep euros and a rate on the line. The fixed contract rate is new.

Suggested new tables (names for IT to decide): `gl_donors`, `gl_contracts`, `gl_contract_projects`, `gl_contract_instalments`, `gl_contract_reports`, `gl_budget_versions`.

## 3. Questions for IT (answer BEFORE any work starts)

1. Is this change possible and safe to do? **(Yes / No / Only with changes)**
2. Which backend files must change (controller, model, route, config, migration)? See section 2.
3. Does it affect the database or existing data? Yes, new tables and new columns. The site is starting from zero (#8), so there is no old data to convert, except the donor names already written on projects and budget lines.
4. Does it affect other pages or other websites that share this code?
5. Does the view need new data from the backend? Yes: donors, contracts, instalments, reports schedule, budget versions.
6. Is there a front-end-only alternative? No.
7. Any security or permission impact? See 1.1 and 1.15. The Project Manager sees only the contracts of their own projects.
8. Who does the work: IT directly, or CT under IT supervision? CT can build the screens (new Livewire components, views, translations) once IT has added the tables and models.
9. Needs staging test, deploy window, or rollback plan?
10. Priority / deadline: this is step 1 of the finance plan. Entries, balances and reports depend on it.

Extra questions:

- Should the contract replace the project's donor fields at once, or should both live side by side for a while?
- The budget overrun approval: an extra step in the voucher approval chain, or a separate approval on the line?
- Income for conditional instalments is posted when the condition is met. Should the program post that entry by itself when the user marks the condition as met, or prepare a draft voucher for approval?
- Fixed-rate versus actual-rate difference (1.5): book it automatically on each receipt, or once when the contract closes?

## IT answers

| | |
|---|---|
| Decision | Approved / Approved with changes / Rejected |
| Answered by | |
| Date | |
| Implemented by | |
| Pull request | |

<Notes and answers to the questions above:>
