# Developer task prompts

Companion to [PLAN-hcsyria_financial_test.md](PLAN-hcsyria_financial_test.md). Each task is a self-contained prompt: hand it to a developer or to an AI coding agent as written. Do them in order; a task starts only when its **Depends on** tasks are merged.

Common rules for every task (paste at the top of each prompt):

> Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5), not the machine default 8.4. Read `CLAUDE.md`, `AGENTS.md` and `docs/PLAN-hcsyria_financial_test.md` first. The database `hcsyria_financial_test` belongs to the Windows program: **never alter or write legacy tables** (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. Do not put credentials in the repository. For any screen you add or change, follow `DESIGN.md` (tables `.fin-table`, amounts `<x-money>`, no new animation, check Arabic, dark mode and 375px) and tick its checklist in the pull request.

Git rule for every task (see section 11 of the plan): start from an up-to-date `main`, create the branch named in the task (`t<phase>-<n>-<name>`), do only that task, commit with messages starting with the task id, push the branch, merge it into `main` (pull request preferred), push `main`, keep the branch (never delete task branches, local or remote, yours or a co-worker's), and only then start the next task on a new branch.

Status key: T = task, P = phase of the plan.

---

## Phase 1: Connection and foundations

### T0-2 Point the app at `hcsyria_financial_test`
**Depends on:** none
**Prompt:** Set `DB_DATABASE=hcsyria_financial_test` in `.env.example` (and tell the developer to do it in `.env`). In `config/database.php` remove `ONLY_FULL_GROUP_BY` from the MySQL `modes` instead of turning `strict` off; the legacy views `cash_balances` and `tree_view` currently fail with error 1055. Check the legacy tables' collation (`information_schema.tables`) and set the connection charset/collation to match so Arabic text round-trips.
**Done when:** `.\php.cmd artisan db:show` lists the legacy tables; a tinker query `DB::table('cash_balances')->count()` works; an Arabic `donor.Name` prints correctly.

### T1.2 Read-only `legacy` connection and least-privilege user
**Depends on:** T0-2
**Prompt:** Add a second connection `legacy` in `config/database.php` (same database, credentials `LEGACY_DB_USERNAME` / `LEGACY_DB_PASSWORD` from `.env`). Write `docs/db-users.sql` with the statements to create two MySQL users: `hc_web_legacy` (SELECT only on `hcsyria_financial_test.*`) and `hc_web` (SELECT on legacy tables; ALL on tables named `gl_*`, `sessions`, `cache*`, `jobs*`, `failed_jobs`, `migrations`). Do not run it against production; document how to run it locally.
**Done when:** with the read-only user, `DB::connection('legacy')->table('donor')->insert([...])` throws; the app still logs in.

### T1.3 Block destructive commands
**Depends on:** T0-2
**Prompt:** In `AppServiceProvider::boot`, call `DB::prohibitDestructiveCommands()` when the configured database name starts with `hcsyria_`, so `migrate:fresh`, `migrate:refresh`, `migrate:reset` and `db:wipe` refuse to run. Add a Pest test with a fake config proving it.
**Done when:** `.\php.cmd artisan migrate:fresh` refuses on this database.

### T1.4 Remove Spatie leftovers and check config
**Depends on:** none
**Prompt:** Confirm `spatie/laravel-permission` is gone (composer files, `config/`, `bootstrap/cache`, published migrations). Make sure nothing references `HasRoles`, `Permission` or `Role` facades from Spatie. Re-run `package:discover`.
**Done when:** `grep -ri spatie` outside `vendor` and `docs` finds nothing; tests pass.

### T1.5 Command `finance:check-db`
**Depends on:** T1.2
**Prompt:** Create `app/Console/Commands/CheckFinanceDatabase.php`. It connects through `legacy`, reads `version.l_version` and fails if it is not `3.1.1.5` (value in `config/finance.php`), verifies that every legacy table and view the app uses exists (list in the config), and prints row counts. Exit code 1 on any failure.
**Done when:** the command is green on the test database; a Pest test covers the failing version case with a mocked connection.

### T1.6 Framework tables
**Depends on:** T1.2
**Prompt:** Set `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`. Create migrations for `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` only (no `users` or `password_reset_tokens`; Fortify uses the legacy `user` table). Run `migrate --pretend` first and attach the SQL to the PR. Take a `mysqldump` backup before the real run.
**Done when:** the six tables exist; login and logout still work.

### T1.7 Harden login against the legacy `user` table
**Depends on:** T1.2
**Prompt:** In `app/Actions/Fortify/AuthenticateWithFinanceAccount.php`, reject users whose `State` is not 1. Keep the password format (`base64(salt16 + PBKDF2-SHA256 100000, 32 bytes)`, see `app/Support/FinancePassword.php`). Never write a different format. Add tests: inactive user refused, wrong password and unknown user indistinguishable, throttling still works.
**Done when:** tests pass.

---

## Phase 2: Ledger schema and posting engine

### T2.1 Legacy read models
**Depends on:** T1.2
**Prompt:** Create read-only Eloquent models under `app/Models/Legacy/` with `$connection = 'legacy'`, `$timestamps = false`, the real primary key (`ID` or `id`, check each table) and relations taken from the foreign keys: `Action`, `ActionPayment`, `ActionAttachment`, `Payment`, `Beneficiary`, `Account`, `Till`, `TillType`, `CashAccount`, `Donor`, `Line`, `BudgetLine`, `Budget`, `Project`, `SubProject`, `City`, `Employee`, `EmployeeSalary`, `Rate`, `RoleCurrencyLimit`, and view models `CashBalance`, `TreeView`. A shared trait makes `save`, `delete` and `update` throw. Keep `App\Models\User` and `Role` on the default connection (login table) and exclude the `ProfilePicture` blob from default selects.
**Done when:** a Pest test per model loads one real row and one relation; writing throws.

### T2.2 Ledger migrations: accounts, projects, budget lines
**Depends on:** T1.6
**Prompt:** Create migrations `gl_accounts`, `gl_projects`, `gl_budget_lines` exactly as in section 3.2 of the plan (columns, types, self FK on `parent_code`, indexes, soft deletes). `gl_accounts.code` is a string primary key. Add Eloquent models `GlAccount`, `GlProject`, `GlBudgetLine` with relations and the extra project columns (`currency`, `contract_no`, `start_date`, `end_date`) and budget line versioning (`version`, `valid_from`, `is_current`) and enums `AccountClass`, `AccountLevel`, `NormalSide`.
**Done when:** migrations run on a scratch schema (not the test database) and roll back.

### T2.3 Ledger migrations: vouchers
**Depends on:** T2.2
**Prompt:** Create `gl_vouchers`, `gl_voucher_lines`, `gl_voucher_audit`, `gl_voucher_sequences`, `gl_voucher_attachments`, `gl_month_closes`, `gl_allocation_rates`, `gl_legacy_map`, `gl_import_batches`, `gl_import_issues`, `gl_cash_till_merges`, `gl_requests`, `gl_payroll_splits`, `gl_micro_loans`, `gl_micro_instalments` per the plan (section 3.2 and 6.8), including the new voucher columns `transfer_kind`, `request_id` and the voucher line columns `eur_amount`, `eur_rate`, `commission_usd`, `is_uncompensated`. Add a database check constraint on `gl_voucher_lines`: debit or credit, never both, never negative, both columns `decimal(18,2)`. Unique index on `gl_vouchers.legacy_action_id` (nullable) and on `gl_voucher_attachments.legacy_attachment_id`. Models and enums `VoucherType` (CRV, CPV, TRF, ALC, JV, OPN), `VoucherStatus` (PendingApproval, Approved, Replaced), `FundSource`.
**Done when:** migrations run and roll back on the scratch schema; the check constraint rejects a line with both Dr and Cr.

### T2.4 Seed the chart of accounts, projects and budget lines
**Depends on:** T2.2
**Prompt:** Open the mockup artifact `DbZn7RTmZyZBh2w6MqbWxM` (data object `D`: `acc`, `proj`, `bl`) and write a seeder with those 156 accounts (34 headers, 122 posting), the 8 projects (F2F, SZ, CORE and the ten general accounts GEN-<city>-USD and GEN-<city>-SYP for ALP, LTK, DAM, HMS, TRT; the mockup has one general account per city, so split each into a USD and an SYP row, decision Q1) and the budget lines. Apply the v7 changes: add 4108, 4306-4308, 1125-1127, 1135, 1145-1147, 1155; rename 1115, 1171, 1172. Keep the Arabic names as UTF-8 (the artifact export shows replacement characters in two project names: use `General account - <city>` and flag it in the PR). Store the data in `database/seeders/data/*.json`, not inline. Also add the new expense account `5504` "Hawala company commission" under the financial expenses next to 5503 (confirm the number is free in the mockup; if not, use the next free code and flag it in the PR), and one account for personal accounts (deposits held for people and organization accounts in a person's name): mandatory `Contact` tag, excluded from `statements`, opening balance zero (Financial Manager, rounds 2 and 3).
**Done when:** counts are 156 + 8 accounts' worth of v7 rows as in the mockup, every `parent_code` resolves, every posting account has a class, side and statement line.

### T2.5 Posting service and the 13 rules
**Depends on:** T2.3, T2.4
**Prompt:** Create `app/Services/Ledger/PostVoucher.php`. Input: voucher header and lines. In one DB transaction it validates, numbers (`CRV-2026-0001`, from `gl_voucher_sequences` with a row lock, never reused), saves with status `PendingApproval`, and writes an audit row. Implement and unit-test each rule separately (one class per rule, one passing and one failing test each): only active posting accounts; cash account only in its own currency; debit or credit never both and never negative; balanced exactly in USD (cents go to 5503); required tags filled; budget line belongs to the line's project and fills its account; approver is never the preparer; nothing changes in a closed month; changes to an approved voucher keep old value, new value, user and reason; numbers never reused; a used account keeps code, class and currency; month closes only when 11x3, 11x4 and 2901 are zero; migrated vouchers keep their legacy id. Two decisions from the Financial Manager (plan 6.9): the budget line limit is NOT a posting rule (exceeding it is only a warning shown in the builder and at approval); and a voucher that is not linked to an approved request (`request_id`) is refused above USD 500 per voucher, an SYP voucher being compared at its own voucher rate.
**Done when:** the rules suite is green; a failed rule leaves no rows and no consumed number.

### T2.6 Roles and policies (no Spatie)
**Depends on:** T1.7
**Prompt:** Create enum `RoleId` (2 Head Office, 3 Project Manager, 4 Financial Manager, 5 Accountant, 6 Cashier, 7 Employee, 8 Donor), `config/permissions.php` (role to screen to action: view, create, edit, delete, approve) and Laravel Policies/Gates. Accountant prepares, Financial Manager approves, Head Office both. Approval limits come from legacy `role_currency_limit` (fallback `role.Approval_Limit`). Add a Livewire/route middleware `can:screen,<slug>` applied to every route in `routes/web.php`. Also add the web-only role `GeneralManager` (the CEO; it is not in the legacy `role` table, so keep it in a web table such as `web_user_roles` and leave `role` read only). The General Manager approves requests above USD 2,000 AFTER the Financial Manager (two approvals in order).
**Done when:** tests prove each role sees only its screens and the preparer cannot approve their own voucher.

---

## Phase 3: Chart module

### T3.1 Chart of accounts screen (`chart`)
**Depends on:** T2.4, T2.6
**Prompt:** Build the `chart` screen as in the mockup: tree of accounts grouped by class with expand/collapse, filters (class, posting only, search by code/name EN/AR), per-account balance (approved vouchers), currency, city, required tags and "how to use". Editor drawer to add/edit: code, level, EN/AR name, parent, class, normal side, currency, city, cash flag, statement line, tags, usage note. Add child account on headers. Delete only if never used; a used account may only be deactivated and keeps code, class and currency. Livewire + Flux, RTL-aware, responsive. Replace the placeholder route.
**Done when:** Pest feature tests for create, edit, deactivate and the "used account" restriction; manual check in English and Arabic.

### T3.2 Voucher list and approval (`vouchers`)
**Depends on:** T2.5, T2.6
**Prompt:** Build the `vouchers` list: type chips, status, date range, search, Pending badge in the navigation. Detail view with lines, attachments and audit trail. Approve and reject actions for the Financial Manager (not the preparer); approval re-checks the posting rules and the role limit. Rejected vouchers keep their number and are soft deleted with a reason.
**Done when:** feature tests for approve, reject, self-approval refused, closed month refused.

### T3.3 Voucher builder
**Depends on:** T3.2
**Prompt:** Build the modal/page to create a voucher: header (type, date, description) and a lines grid (account, currency, Dr/Cr, original amount, rate, USD, project, budget line, fund source, contact, center, lira type old/new). Choosing a budget line fills its account; choosing a cash account fixes the currency; SYP payments take the project average rate; show the live check list (balanced, tags, currency, open month). Save through `PostVoucher`. Attachment upload to the private disk (`gl_voucher_attachments`). Show two soft warnings, never blocks: spending after the project's end date, and exceeding the remaining amount of the budget line.
**Done when:** a CRV, CPV and TRF can be prepared and appear as Pending; the check list matches the server-side rules.

### T3.4 Account statement (`statement`)
**Depends on:** T2.5
**Prompt:** One account, project filter, date range, opening balance, running Dr/Cr balance, link to each voucher, CSV export. Approved vouchers only.
**Done when:** the running balance equals the trial balance for the same account and date (test).

---

## Phase 4: Reports

### T4.1 Trial balance (`tb`)
**Depends on:** T3.4
**Prompt:** As-at date, per account Dr / Cr / net, grouped by class, total Dr = total Cr check, approved vouchers only, export. Reports show both currencies: the SYP rate is typed in per report (no rate table), SYP stays SYP and only USD is converted. Build one shared currency-switch component and reuse it in every later report (`projects`, `statements`, `r*`).

### T4.2 Project balances (`projects`)
**Depends on:** T4.1
**Prompt:** Per project: income, expenses (exclude 5703 depreciation, decision D10), cash held, restricted fund balance, budget versus actual per budget line, donor-currency (EUR) view using the project rate. Also add the report "where are the project funds": the project balance split by cash account, per currency (a project may hold money in several cash accounts). Home and the project pages read balances through a `ProjectBalanceSource` interface so the source can move from the legacy tables to the ledger without changing the screens.

### T4.3 Financial statements (`statements`)
**Depends on:** T4.1
**Prompt:** Statement of Financial Position and Statement of Activities grouped by `statement_line`; 5503 under financial expenses (D11); comparative period; PDF/XLSX export with RTL layout.

### T4.4 Operational reports (`rexp`, `rcash`, `rbl`, `rrev`, `rfixed`, `rattach`)
**Depends on:** T4.1
**Prompt:** Rebuild on `gl_voucher_lines` with the filters of the Windows program (project, sub-project, donor, account, currency, date range). `rattach` reads `gl_voucher_attachments`.

---

## Phase 5: Treasury

### T5.1 Treasury position (`treasury`)
**Depends on:** T3.3
**Prompt:** Per city: the two general accounts (GEN-<city>-USD and GEN-<city>-SYP) with SYP held and average cost, bulk purchases (TRF into the SYP safe), cash by safe and currency, in-transit (11x3, 11x4), transfer company 1161, 5503 reserve.

### T5.2 Transfer rates and TRF vouchers (`alloc`)
**Depends on:** T5.1
**Prompt:** Rates per city with effective date: proposed by Accountant, approved by Financial Manager. Build the **transfer** voucher (TRF with `transfer_kind`; ALC now means splitting a salary or invoice over projects, see plan 6.8): Dr project SYP, Cr general account SYP at its average rate, USD legs on 1161, difference to 5503. Errors exactly as in the mockup (no pounds in the general account, not enough pounds, no approved rate). Optional "allocate at average cost" switch, default off (decision D9). The four transfer kinds are: general USD to project; general SYP to project with the USD equivalent deducted; project back to general (return); between two projects. Moving project cash to city cash is NOT a transfer kind (it is an ordinary manual `TRF`, rare). The rate is the city rate approved by the Financial Manager and the difference goes to 5503.

---

## Phase 6: Migration module (on the test copy first)

### T6.1 Mapping step of `dbload`
**Depends on:** T2.1, T2.4
**Prompt:** Read distinct cash accounts (name, currency, city), project accounts, donors and budget lines from the legacy database. Auto-guess targets (cash by name, currency and city; project by name; donor by name; budget line by the keyword table in the mockup `LINEKW`). Screen to confirm or change each mapping; save in `gl_legacy_map` with the confirming user.

### T6.2 Staging and posting of operations
**Depends on:** T6.1, T2.5
**Prompt:** Per legacy operation produce a voucher or an issue in `gl_import_issues`: I to CRV (Dr cash, Cr donor income), E to CPV (Dr expense, Cr cash, budget line fills account), A to CPV (Dr 1201, contact tag), R to CRV (Cr 1204 if micro-loan else 1201), X to TRF (Dr SYP safe, Cr USD safe), T to TRF (transfer, with `transfer_kind`) or ALC/JV (split) after the allocation rule. Missing rate: use the fallback rate and flag it. Skip `action_deleted`; `action_not_approved` becomes Pending. Idempotent through `legacy_action_id`; run in chunks as a queued job with a progress bar; dry-run mode. Also feed `gl_micro_loans` and `gl_micro_instalments` from the legacy `MicroProject_ID`, and mark cash received without a project as `is_uncompensated`.
**Done when:** a rehearsal on the test copy reproduces, per cash account and project, the Windows program's balances (parity test).

### T6.3 Attachment move
**Depends on:** T6.2
**Prompt:** Add disk `attachments_ftp` (`league/flysystem-ftp`), configured only from `.env` (`ATTACH_FTP_HOST`, `ATTACH_FTP_USER`, `ATTACH_FTP_PASSWORD`, `ATTACH_FTP_ROOT`; test root `ftp://hcsyria.org/financial/files/test`). For each `action_attachments` row (2,055), find the voucher by `legacy_action_id`, download `<root>/<attach_name>`, store on the private disk, save sha256, create `gl_voucher_attachments`. Report: copied, missing file, no voucher, checksum mismatch. Idempotent by `legacy_attachment_id`. Never commit credentials.
**Done when:** count of rows in equals files out, with a report of the exceptions.

### T6.4 Opening balances and month close (`opening`)
**Depends on:** T6.2
**Prompt:** OPN vouchers for 31/12/2025 from legacy balances with the 3901 balancing line per project (must end at zero). Month close checks (previous month closed, no unapproved vouchers, 11x3, 11x4, 2901 at zero), irreversible except by an audited Head Office reopen. Year-end carry-forward to 1/1/2027 (restricted projects to 3201, others to 3101), replacing the temporary OPN (status Replaced).

### T6.5 Fix USD (`fixusd`)
**Depends on:** T6.2
**Prompt:** Operations in SYP with stored rate 0 (about 4,618): compute the dollar value at the project average rate on the operation date (D7), show old versus new, apply in one transaction with an audit row.

### T6.6 Legacy exchange (`legacy`) and till merges (`merges`)
**Depends on:** T6.2
**Prompt:** Legacy exchange groups (about 470): one rate, TRF vouchers with no 5503 line (D8). Cash till merges: Jouliana into 1142, Al-Nouzha written off with an adjustment voucher; type-to-confirm before posting.

---

## Phase 7: Entry and master-data screens

### T7.1 Entry screens on vouchers
**Depends on:** T3.3
**Prompt:** Rebuild `single`, `multi`, `multiatt`, `payments`, `approval`, `salaries`, `vouchers` print so that they post vouchers through `PostVoucher` using the mockup's operation-to-voucher rule. They never write to legacy tables. Multi entry: paste from Excel, row validation, all-or-nothing save.

### T7.3b Requests module (`requests`)
**Depends on:** T3.3, T2.3, T2.6
**Prompt:** Build the `requests` module (plan 6.9). A project manager (role 3) prepares ONE monthly request per project: lines (budget line, amount) for the routine monthly expenses; an extra amount during the month is a separate request. States: draft, submitted, approved, rejected, closed. The manager edits until approval, then it locks (every edit audited). The Financial Manager approves or rejects; above USD 2,000 (SYP compared at the line rate) the General Manager also approves AFTER the Financial Manager. Large maintenance, fixed assets and electronics need 2 or 3 price quotes attached before submit (SecureUpload, private disk). Tables `gl_requests`, `gl_request_lines` and an approvals table; `gl_vouchers.request_id` links the voucher. Exceeding a budget line is only a warning. Pest tests: state machine, two-step approval, lock, 403 on foreign requests, quote requirement.
**Done when:** a request goes draft to approved with both approvals and is linked to a transfer voucher.

### T7.3c Salary allocation (ALC) from the payroll split
**Depends on:** T3.3, T2.3
**Prompt:** On the `salaries` screen build the ALC voucher: one employee's salary split over several projects and budget lines from `gl_payroll_splits` (employee, project, budget line, percent, valid_from), which follows the monthly payroll sheet; a change during a month applies from the next month (no day proration). Percentages of one employee sum to 100 per valid_from; cents go to the last line; the accountant can adjust the proposed lines. The same ALC serves a shared invoice with manual lines. Salaries are visible only to finance roles and Head Office. Pest tests: percent sum, month boundary, rounding, SYP rate.
**Done when:** the lines sum to the salary in USD.

### T7.3d Follow-up reports (`recon`, `uncomp`, `advances`, `loans`)
**Depends on:** T4.2, T2.3
**Prompt:** Four read-only reports on `gl_voucher_lines` (approved vouchers only): `recon` (hawala pool 1161 versus project and city general balances, monthly), `uncomp` (cash received without a project and not yet compensated, per city, age buckets under 30 / 30-90 / over 90 days, no alerts), `advances` (open advances 1201 by person, no ageing, a settlement difference is a receivable from the employee or supplier), `loans` (micro loan collection and arrears). One shared filter bar (date, city, project) and CSV export; project managers see their own projects only.
**Done when:** each report matches the fixture vouchers.

### T7.3e Micro loans (`gl_micro_loans`)
**Depends on:** T2.3, T3.3
**Prompt:** Micro loan entry: per beneficiary loan amount, grant amount, currency and a repayment schedule (`gl_micro_instalments`: due_date, amount, paid_amount, paid_voucher_id). Disbursement posts through PostVoucher (loan to 1204, grant to the budget line's expense account); each instalment received posts a CRV crediting 1204 with fund source `Recovered loans` tagged with the original donor, which keeps that donor's restrictions (no separate revolving fund). Schedule generator (equal monthly instalments, SYP or USD) and an arrears indicator for the `loans` report. Pest tests: rounding, partial payment, overpayment.
**Done when:** the 1204 balance equals loans minus paid instalments.

### T7.3f Hawala receipt fields
**Depends on:** T2.5, T2.4
**Prompt:** Extend the donor-receipt voucher (CRV into 1161) with the original EUR amount (`eur_amount`), the actual rate of that transfer (`eur_rate`) and the commission (`commission_usd`). The project is credited with the net USD received; the commission is an organization expense posted to 5504 with no project tag. The per-project `donor_rate` is only a default. Validate `eur_amount x eur_rate - commission` equals the USD received within one cent. Pest tests: EUR and USD receipts, commission posting, rounding.
**Done when:** a EUR receipt posts the project credit and the 5504 expense correctly.

### T7.2 Master data screens
**Depends on:** T2.6
**Prompt:** `donors`, `cities`, `programs`, `projwin`, `budgetlines`, `blcats`, `currency`, `denoms`, `employees`, `users`, `limits`, `tills`, `allaccounts`, `bonds`, `lifetime`, `log`: decide per screen, with the finance team, whether it is read-only reference from legacy or edited in the ledger tables, and implement accordingly. `users` changes passwords only through `FinancePassword::make`.

### T7.3 Bridge pages
**Depends on:** T3.1
**Prompt:** Read-only pages `decisions` (D1-D11, Q-a to Q-e), `mapping` and `rules` from the mockup, visible to Head Office and Financial Manager.

---

## Phase 8: Hardening and cut-over

### T8.1 Reset the shared password
**Depends on:** T1.7
**Prompt:** All 14 users currently share one stored password. Add a forced password change at first web login; write with `FinancePassword::make` (random salt per user). Test that the Windows program format still verifies.

### T8.2 Performance and security pass
**Depends on:** T4.4, T6.2
**Prompt:** `EXPLAIN` every report query; add `gl_*` indexes where needed (never on legacy tables without a separate reviewed migration). Run the security review (OWASP top 10, upload validation, signed download routes, rate limits, security headers).

### T8.3 Cut-over runbook
**Depends on:** all
**Prompt:** Write `docs/CUTOVER.md`: freeze the Windows program, backup, final import into `hcsyria_financial` with the production FTP root, parity checks (balances per cash account and project, attachment counts), point `.env` at the production database, smoke test per role, archive the Windows program and the old database read-only, rotate the FTP and MySQL credentials found in the old source.

---

## Phase 9: Improvements outside the plan (U, IT, AI)

None of these needs a finance decision or overlaps tasks 01 to 45. The source is `docs/improvement-tasks.json` (the Arabic prompts are generated from it). They go to ClickUp by CSV import, not through `scripts/clickup_sync.py`. Branches are `u-NN-…`, `it-NN-…` or `ai-NN-…`, and commit messages start with the task id. The AI tasks need management approval before any data is sent out.

### U-01 In-app notifications bell
**Group:** Users · **Priority:** High · **Effort:** 2 days · **Branch:** `u-01-notifications` · **Depends on:** none · **Status:** done and merged into main
**Prompt:** Add a bell in the top bar with the person's unread count and latest notifications, refreshed every minute (Livewire poll). Send them from the voucher and request audit trails, after the change is committed: a voucher waiting for approval goes to everyone the ApprovalPolicy lets approve it now (role, limit, donor-receipt chain), never the preparer; an approved or rejected voucher (with the reason) goes to its preparer; a submitted request, or one past a step, goes to whoever RequestPolicy::decide allows next; an approved or rejected request (with the reason) goes to the requester. Migrated vouchers notify no one. Table `web_notifications` (migration); texts in lang en/ar built when shown; opening one marks it read and goes to the voucher or request.
**Done when:** Rejecting a voucher shows a notification to its preparer within a minute, and clicking it opens the voucher.

### U-02 Arabic error pages
**Group:** Users · **Priority:** High · **Effort:** 1 day · **Branch:** `u-02-error-pages` · **Depends on:** none · **Status:** done and merged into main
**Prompt:** Add `resources/views/errors` pages for 403, 404, 419, 500 and 503 in the app's design and the person's language (en/ar), with a link back home. Replace Livewire's default English session-expired prompt with a localized message that sends the person to sign in.
**Done when:** All five error pages and the session-expired notice show in the person's language and the app's design.

### U-03 Home panel: what is waiting for me
**Group:** Users · **Priority:** Normal · **Effort:** 1.5 days · **Branch:** `u-03-waiting-panel` · **Depends on:** none · **Status:** done and merged into main
**Prompt:** At the top of Home, per role: vouchers waiting for my approval, my rejected vouchers, requests waiting for my decision, my pending requests; each opens the filtered list. Use the same approval rules as the bell (U-01) to decide what counts.
**Done when:** Each role sees its pending work on sign-in, and a click opens the filtered list.

### U-04 Warn before leaving unsaved changes
**Group:** Users · **Priority:** Normal · **Effort:** 1 day · **Branch:** `u-04-unsaved-changes` · **Depends on:** none · **Status:** done and merged into main
**Prompt:** Warn before leaving single entry, the voucher builder, multi entry, requests and salaries when a form has unsaved changes, including `wire:navigate` links.
**Done when:** Leaving a form with unsaved changes asks for confirmation on every entry screen.

### U-05 Warn before the session expires
**Group:** Users · **Priority:** Normal · **Effort:** half a day · **Branch:** `u-05-session-warning` · **Depends on:** none
**Prompt:** Five minutes before `SESSION_LIFETIME` ends, show a notice with a Keep working button that refreshes the session without losing the form.
**Done when:** The notice appears before expiry, and Keep working extends the session without losing the form.

### U-06 Global search (Ctrl+K)
**Group:** Users · **Priority:** Normal · **Effort:** 2 days · **Branch:** `u-06-global-search` · **Depends on:** none · **Status:** done and merged into main
**Prompt:** A search dialog opened with Ctrl+K from any screen: vouchers by number and description, accounts by code and name, projects, and screens. Results respect the person's screen permissions and data scope.
**Done when:** Typing a voucher number opens it directly, and results never show what the person may not see.

### U-07 Keyboard shortcuts on entry screens
**Group:** Users · **Priority:** Normal · **Effort:** 1 day · **Branch:** `u-07-shortcuts` · **Depends on:** none · **Status:** done and merged into main
**Prompt:** Ctrl+S saves, Ctrl+Enter saves and starts a new entry, Alt+N adds a line in the voucher builder, Esc closes dialogs, and ? shows the list of shortcuts.
**Done when:** The shortcuts work on single entry, the voucher builder and multi entry, and ? shows the list.

### U-08 Install the app on the computer (PWA)
**Group:** Users · **Priority:** Normal · **Effort:** half a day · **Branch:** `u-08-pwa` · **Depends on:** none
**Prompt:** Add a web app manifest (name, icons, theme), a service worker that caches static assets only (no offline entry), and an Install the app item in the user menu.
**Done when:** Chrome or Edge can install the app; it opens in its own window from a desktop icon.

### U-09 Two-factor sign-in for Head Office and the Financial Manager
**Group:** Users · **Priority:** Normal · **Effort:** 2 days · **Branch:** `u-09-two-factor` · **Depends on:** none
**Prompt:** TOTP with recovery codes through Fortify's two-factor feature. The Windows program's `user` table cannot hold the secret: store it encrypted in a web table. Required for Head Office and the Financial Manager, optional for the others.
**Done when:** The Financial Manager cannot sign in without the app's code, and each recovery code works once.

### U-10 Load heavy reports progressively
**Group:** Users · **Priority:** Normal · **Effort:** 1 day · **Branch:** `u-10-lazy-reports` · **Depends on:** none · **Status:** done and merged into main
**Prompt:** Make the trial balance, account statement, financial statements, project balances and operational reports `#[Lazy]` with a skeleton, and show a loading indicator when a filter changes.
**Done when:** The page shows at once with a skeleton, then the report; changing a filter shows an indicator.

### U-11 Optional Telegram channel for notifications
**Group:** Users · **Priority:** Low · **Effort:** 1.5 days · **Branch:** `u-11-telegram` · **Depends on:** U-01
**Prompt:** A Telegram bot that each user links from their profile with a one-time code. It sends the same notifications as the bell, as short text with no amounts, plus a link to the app.
**Done when:** A linked user gets the rejection of their voucher on Telegram within a minute.

### U-12 Remember each user's interface language
**Group:** Users · **Priority:** Low · **Effort:** half a day · **Branch:** `u-12-user-locale` · **Depends on:** none · **Status:** done and merged into main
**Prompt:** The interface language lives in the session only (`SetLocale`, `SwitchLocaleController`), so it is lost at sign-out. When a signed-in person switches language, save it with `UserPreferences` under the key `locale` (table `web_user_preferences`, never the Windows program's `user` table). On sign-in (Fortify's Login event), put the saved language into the session; if the person has none saved yet, save the language they picked on the sign-in page. Test: switch to Arabic, sign out, sign in again from a fresh session, and see Arabic.
**Done when:** A person who chose Arabic, signed out and signed in again, from any browser, sees the app in Arabic.

### IT-01 Daily backup of the web tables
**Group:** IT · **Priority:** High · **Effort:** 1 day · **Branch:** `it-01-backup` · **Depends on:** none
**Prompt:** An artisan command that dumps only the `gl_*` and `web_*` tables (never the Windows program's tables) to a dated compressed file; schedule it nightly, keep the last 14, and document and rehearse a restore into a separate database.
**Done when:** A backup is made every night, and a documented restore works on a separate database.

### IT-02 Run the tests and checks on GitHub
**Group:** IT · **Priority:** High · **Effort:** 1 day · **Branch:** `it-02-ci` · **Depends on:** none
**Prompt:** A GitHub Actions workflow on every pull request and on main: Pint --test, PHPStan, Pest (sqlite in memory, as phpunit.xml) and gitleaks. Protect main so a failing check blocks the merge.
**Done when:** Every pull request shows the four checks, and none merges while one fails.

### IT-03 Sum and format money with bcmath only
**Group:** IT · **Priority:** High · **Effort:** 1 day · **Branch:** `it-03-money-helpers` · **Depends on:** none
**Prompt:** Add `Money::format` and `Money::sum` to `app/Support/Money.php`; replace the float sums (home page, `AccountBalances`) and the per-service `number_format((float) ...)` helpers with them; add rounding tests.
**Done when:** No money is summed as a float, and every report uses the one formatting helper.

### IT-04 Rotate the error log daily
**Group:** IT · **Priority:** Normal · **Effort:** half a day · **Branch:** `it-04-log-rotation` · **Depends on:** none
**Prompt:** Use the daily log channel with 14 days kept, and the warning level in production instead of debug (`.env.example` and the docs).
**Done when:** A log file is made per day, and files older than 14 days are removed.

### IT-05 Alert the team on server errors
**Group:** IT · **Priority:** Normal · **Effort:** 1 day · **Branch:** `it-05-error-alerts` · **Depends on:** none
**Prompt:** Send a short summary of each 500 error (no financial data) to the team's channel, grouping repeats to one per error per hour, plus a daily count.
**Done when:** A 500 in production reaches the team within minutes, and at most once an hour per error.

### IT-06 Tests for the sensitive services that have none
**Group:** IT · **Priority:** Normal · **Effort:** 1.5 days · **Branch:** `it-06-service-tests` · **Depends on:** none
**Prompt:** Pest tests for VoucherAttachments (types, size, count), VoucherNumberAllocator (no reuse, even with a deleted voucher), HawalaReceiptIsConsistent (each case) and StatementExport.
**Done when:** Each of the four services has a passing and a refusing test.

### IT-07 Local setup guide, and a php.cmd that finds PHP
**Group:** IT · **Priority:** Normal · **Effort:** half a day · **Branch:** `it-07-readme-php-cmd` · **Depends on:** none
**Prompt:** A README in Arabic: requirements, importing the test database copy, `.env`, the legacy database user, running the tests. Make `php.cmd` try the PATH and common folders (`C:\php\8.5`, `C:\php85`, Herd) before it fails.
**Done when:** A new developer runs the app and the tests by following the README alone.

### IT-08 Smaller CSS and fonts
**Group:** IT · **Priority:** Low · **Effort:** half a day · **Branch:** `it-08-assets` · **Depends on:** none
**Prompt:** Convert Lato to woff2 and load only the weights in use; review what Flux adds to the CSS.
**Done when:** The CSS and fonts loaded on the first visit are at least 40% smaller.

### IT-09 Health check of both databases, with an alert
**Group:** IT · **Priority:** Low · **Effort:** half a day · **Branch:** `it-09-health-check` · **Depends on:** none
**Prompt:** Make `/up` check both the ledger and the `legacy` connection; schedule `finance:check-db` hourly and alert on failure.
**Done when:** When the legacy database is down, `/up` fails and the team is alerted.

### IT-10 A test that the en and ar keys match
**Group:** IT · **Priority:** Low · **Effort:** half a day · **Branch:** `it-10-lang-parity` · **Depends on:** none
**Prompt:** A Pest test that compares every `lang/en` and `lang/ar` file key by key and fails on any gap.
**Done when:** Adding an English key without its Arabic one fails the tests.

### IT-11 Checks before every commit
**Group:** IT · **Priority:** Low · **Effort:** half a day · **Branch:** `it-11-pre-commit` · **Depends on:** none
**Prompt:** A shared pre-commit hook in the repository that runs Pint on the changed files and gitleaks, with a one-line install command.
**Done when:** A commit with a secret or wrong formatting is refused locally.

### AI-01 Finish the AI foundation: cost cap and cost log
**Group:** AI (needs management approval) · **Priority:** High · **Effort:** 1.5 days · **Branch:** `ai-01-cost-controls` · **Depends on:** none · **Status:** partly built; the prompt says what is left
**Prompt:** The assistant already exists (`config/assistant.php`, `AnthropicGateway`, `GeminiGateway`): without a key it calls nothing, and it has a per-minute limit, a cap on rounds per answer and a kept conversation log. Add: a monthly cost cap that stops calls when reached; a log of each call (user, feature, input and output tokens, cost) without document content; an on/off switch per AI feature on a settings screen for Head Office. Management must approve what data is sent before any new AI feature is switched on.
**Done when:** When the monthly cap is reached the assistant refuses with a clear message, and each call's cost shows in the log.

### AI-02 Read an attached invoice and propose the entry
**Group:** AI (needs management approval) · **Priority:** High · **Effort:** 3 days · **Branch:** `ai-02-invoice-reading` · **Depends on:** AI-01
**Prompt:** From an attached invoice image or PDF, ask the model for a structured result (date, amount, currency, document type and number, supplier, Arabic description, and a budget line from the project's own lines only) and show it as the assistant's entry proposal card (`EntryProposals`, `ProposeEntry`). Nothing is saved until the accountant presses Save; the invoice stays visible beside the fields.
**Done when:** On 20 real invoices the correct fields are counted, and no entry is saved from a proposal without the accountant.

### AI-03 Smart warnings on the approval screen
**Group:** AI (needs management approval) · **Priority:** Normal · **Effort:** 3 days · **Branch:** `ai-03-approval-hints` · **Depends on:** AI-01
**Prompt:** Show warnings on a pending voucher: a similar voucher (same amount and account within a few days), a rate far from the month's average, an amount above what is left on the budget line, a description that does not fit the line. Most are plain rules; the model only writes one Arabic sentence explaining a warning. Warnings never block approval.
**Done when:** A duplicate voucher in the test data shows a warning with its reason, and approval stays possible.

### AI-04 Assistant answers: link each number to its report
**Group:** AI (needs management approval) · **Priority:** Normal · **Effort:** 1 day · **Branch:** `ai-04-assistant-links` · **Depends on:** none · **Status:** partly built; the prompt says what is left
**Prompt:** The assistant panel (`AssistantPanel`, tools under `app/Services/Assistant/Tools`) already answers from read-only tools within the person's permissions. Remaining: every answer names and links the report screen its numbers come from, and a test proves a project manager cannot get another project's numbers through any tool.
**Done when:** Each answer carries a link to its report, and the scope test passes.

### AI-05 A written monthly summary for the Financial Manager
**Group:** AI (needs management approval) · **Priority:** Low · **Effort:** 2 days · **Branch:** `ai-05-monthly-summary` · **Depends on:** AI-01, U-01
**Prompt:** At the end of each month a scheduled job writes an Arabic summary from the report numbers (largest movements, spending by project, budget lines near their end, pending vouchers) through the Batch API, and notifies the Financial Manager (U-01) with a link.
**Done when:** The summary arrives on the 1st of each month, and every number in it matches its report.

## Phase 10: The Financial Manager's requests and the treasury procedure (F)

From the Financial Manager's notes of 2026-10-30 (requests 01 to 05) and the comparison of `CHC-Treasury-Procedure` with the program. The findings and the decisions still needed are in `docs/REVIEW-2026-10-30-treasury-procedure.ar.md`. F-03, F-05, F-06 and F-11 need a decision before work starts. Branches are `f-NN-…`, and commit messages start with the task id.

### F-01 Table paging across pages (request 01)
**Group:** Financial Manager's requests · **Priority:** High · **Effort:** half a day · **Branch:** `f-01-table-paging` · **Depends on:** none · **Status:** done and merged into main
**Prompt:** The Financial Manager reports that paging through long lists does not work, for example the chart of accounts. On current main, Next/Prev on the chart list works in a browser (1–10, then 11–20 of 169), so first rebuild the assets (`npm run build`; `public/build` is not in git) on the machine where it failed and retry. Then test paging after a search, after a filter change, and after arriving with `wire:navigate`, on the chart and every table that uses `x-ui.scroll-table`. Fix `resources/js/paged-table.js` if any case fails (a filter should return to page 1; a Livewire morph must keep the current page valid). Add an automated check of these cases.
**Done when:** Next and Prev work on every multi-page table, before and after a search or filter and after navigating between screens.

### F-02 One place for financial operations (request 02)
**Group:** Financial Manager's requests · **Priority:** High · **Effort:** 6 days · **Branch:** `f-02-operations-hub` · **Depends on:** none · **Status:** partly built; the prompt says what is left
**Prompt:** Build one Operations screen in the top menu that replaces Single entry, the Voucher builder and Multi entry in the navigation (their old addresses redirect to it). Step 1: pick the type: CRV (receipt), CPV (payment), TRF (move cash between tills of one currency, or between projects, with the in-transit accounts 11x3/11x4), ALC (allocation from a city general account to a project, see F-03), EXG (currency exchange), JV (manual entry). Step 2: a guided form per type whose fields and lists depend on the type, e.g. CPV asks project → budget line (fills the expense account) → till → amount → paid to; the person never picks a debit or credit account except in JV. Step 3: show the generated lines (Dr/Cr, owner, USD value) read-only for review, then save as pending. JV is limited to the Financial Manager and Head Office. Reuse the existing builders (`OperationEntry`, `TransferBuilder`, `SalaryAllocation`, `MicroLoans`, `VoucherDraftBuilder`) and the posting rules; do not duplicate them. Keep drafts (U-04) and shortcuts (U-07) working. Depends on the ALC meaning decided in F-03. Status 2026-10-30: the Operations screen is built (`App\Livewire\Operations`, path `entries/operations`, permission key `single`) with CRV, CPV, TRF (project to project, project back to general), ALC, EXG and JV. Left: TRF between two tills of one currency through the in-transit accounts (no builder yet); ALC and EXG still save as TRF with their transfer kind until F-03; Multi entry stays in the menu until F-04 replaces it; the voucher builder itself is still open to accountants (only the JV card is limited).
**Done when:** Each of the six types is entered from one screen by picking from lists; the lines are written automatically and shown before saving; only JV asks for debit and credit accounts.

### F-03 EXG as its own voucher type for exchange (ALC stays payroll)
**Group:** Financial Manager's requests · **Priority:** High · **Effort:** 1.5 days · **Branch:** `f-03-exchange-voucher` · **Depends on:** F-02 · **Status:** done and merged into main
**Prompt:** Decision R5-1 (docs/DECISIONS.md): ALC stays payroll (the salary split, `SalaryAllocation`) and the allocation from a city general account to a project stays TRF with its sub-type. Add a voucher type EXG for currency exchange: the city general account buys SYP (today `TransferKind::LocalExchange`, saved as TRF), with the difference to 5503 under the general account as today. The Operations screen and the Excel template (F-04) save an exchange as EXG. Saved and approved vouchers keep their type; add a migration only for the enum value. Update the posting-rule catalogue, the voucher numbering (EXG-YYYY-NNNN) and the bridge pages.
**Done when:** An exchange saves as EXG, and the existing rules (difference to 5503 under the general account, average cost) still hold.

### F-04 Program-generated Excel template for many operations
**Group:** Financial Manager's requests · **Priority:** High · **Effort:** 3 days · **Branch:** `f-04-excel-template` · **Depends on:** F-02 · **Status:** done and merged into main
**Prompt:** In the Operations screen (F-02) add Download template (XLSX, per type or one sheet with a Type column) and Upload. The template has the entry sheet with bilingual headers and a Lists sheet holding the projects, budget lines, tills and accounts this person may use (data scope). Upload parses the rows with OpenSpout (already installed), shows a preview of every row with the lines it will post and its errors next to it, and saves only when every row is valid, in one transaction, up to `OperationSheet::MAX_ROWS`. Keep the paste box as a fallback. Reuse `OperationSheet` for validation and posting.
**Done when:** A person downloads the template, fills it, uploads it, sees each row with its lines and errors, and saves all rows at once.

### F-05 Net assets computed automatically, opening balances and year-end close (request 03)
**Group:** Financial Manager's requests · **Priority:** High · **Effort:** 3 days · **Branch:** `f-05-net-assets` · **Depends on:** none · **Status:** partly built; the prompt says what is left
**Prompt:** Show net assets as computed, not posted: in the chart of accounts, the trial balance and the statement of financial position, a Net assets line = assets − liabilities from approved vouchers, split into without donor restrictions (3100) and with donor restrictions (3200) by the project's restriction, with a note that it is computed. Refuse manual lines on 3xxx accounts in every entry screen except an opening-balance voucher (OPN) and the year-end closing voucher. Build the opening-balances screen (the `opening` slug is a placeholder): one OPN voucher per city at go-live, the difference to 3901, which must be zero. Add a year-end close that writes a JV closing income and expenses into 3101/3201 per project restriction, and is refused while the year has pending vouchers. Use bcmath. Status 2026-10-30: net assets are computed (`App\Services\Ledger\NetAssets`): total = assets − liabilities, with donor restrictions = income − expenses of restricted lines (the measure ProjectBalances uses), shown on the chart (3000/3100/3200 marked computed), the trial balance and the statements. Rule 16 (`NetAssetsAreComputed`) refuses web lines on 3xxx accounts; migrated vouchers keep theirs. Decisions R5-2 and R5-3 (2026-10-30): opening balances yes (one OPN voucher per city at go-live, the difference to 3901 must be zero); no year-end close, net assets stay computed. Left: only the opening-balances screen (plan task 34, `opening`).
**Done when:** Net assets equal assets minus liabilities everywhere after every operation, with no manual entry.

### F-06 Super User for testing (request 04)
**Group:** Financial Manager's requests · **Priority:** Normal · **Effort:** 1.5 days · **Branch:** `f-06-super-user` · **Depends on:** none · **Status:** done and merged into main
**Prompt:** Add a web-only role SuperUser (next free RoleId, stored in `web_user_roles`, assignable on the Users screen by someone with users.delete). It is active only when `FINANCE_SUPER_USER=true` and never when `APP_ENV=production`. While active it can open every screen with every action, sees all data (no `LegacyDataScope` limit), may take any approval step (Program Manager, Financial Manager, General Manager) in `ApprovalChain`, `ApprovalPolicy` and `RequestPolicy`, and may approve its own voucher or request (bypassing rule 7 `ApproverIsNotPreparer` only for this role). Every action it takes is audited with a `super` marker, and a warning band shows at the top of every page while signed in as it. Tests: the role does nothing when the flag is off or in production.
**Done when:** One user can walk every workflow from entry to final approval, and the feature is off by itself in production.

### F-07 Monthly request: return for changes, and automatic allocation on approval
**Group:** Financial Manager's requests · **Priority:** High · **Effort:** 2.5 days · **Branch:** `f-07-request-flow` · **Depends on:** none · **Status:** done and merged into main
**Prompt:** Add a request status `returned` (with a reason) that sends the request back to its requester to edit and resubmit; audit it and notify (U-01). On final approval in `RequestWorkflow::approve`, create a pending allocation voucher automatically from the approved lines: the SYP total from the city general account to the project at the approved city rate, and the USD total likewise, with the project repaying the dollar value from its 1161 share, exactly as `TransferBuilder` posts `SypToProject` / `UsdToProject` today; link it with `request_id`. If no approved city rate exists, keep the request approved and show what is missing. Migration for the status only.
**Done when:** A reviewer can return a request with a reason, and the final approval creates the allocation voucher linked to the request.

### F-08 Tills screen: each till split by its owners
**Group:** Financial Manager's requests · **Priority:** Normal · **Effort:** 2 days · **Branch:** `f-08-till-owners` · **Depends on:** none · **Status:** done and merged into main
**Prompt:** Build the `tills` screen from approved ledger lines: one block per cash account with its balance, and under it every general account and project that owns part of it, with the USD value of each row; the rows must add up to the till balance exactly (bcmath), and any difference is shown as unowned. Respect data scope. On the project page, show the project's share of each till from the ledger (`ProjectBalances::fundLocations`) next to the legacy figures.
**Done when:** Every till shows its owners, and the shares add up to its balance exactly.

### F-09 Month-end close and reconciliations
**Group:** Financial Manager's requests · **Priority:** Normal · **Effort:** 3 days · **Branch:** `f-09-month-end` · **Depends on:** F-08 · **Status:** done and merged into main
**Prompt:** Build a month-end screen per city: enter the cash count of each safe, the transfer company statement balance for 1161 and the bank statement balance for 1171/1172; show the ledger balance and the difference for each; check that in-transit accounts and 2901 are zero (rule 12). Closing a month is allowed only when every difference is zero or has a written explanation, stores who closed it and when (own table via migration), and afterwards no voucher dated inside the closed month is accepted (wire the existing rule 12). Head Office can reopen with a reason.
**Done when:** The Financial Manager closes a month after every till and statement agrees, and no voucher dated in that month is accepted afterwards.

### F-10 Monthly approvals report
**Group:** Financial Manager's requests · **Priority:** Normal · **Effort:** 1.5 days · **Branch:** `f-10-monthly-approvals-report` · **Depends on:** F-07
**Prompt:** Add a report "Monthly approvals" (all projects, one month): USD at the transfer company (1161) at the start of the month → approved in SYP and its USD cost → approved in USD → other movements → USD at the end, plus SYP held at the start and end, per project and in total, from approved vouchers and approved requests. CSV and XLSX export like the other reports.
**Done when:** The report agrees with the 1161 balance at the start and end of the month, and its approved totals match the approved requests.

### F-11 Expenses sheet columns and the 2901 suspense account
**Group:** Financial Manager's requests · **Priority:** Normal · **Effort:** 1.5 days · **Branch:** `f-11-expense-sheet` · **Depends on:** F-02
**Prompt:** Add bill number, paid to and place to the CPV form, the Excel template (F-04) and the expense rows (columns on `gl_voucher_lines` or the voucher, via migration). After the Financial Manager decides (review report): post an expense with no budget line to 2901 Suspense with a warning, list open 2901 lines in a follow-up report until they are cleared; and refuse an expense dated outside the contract's funding period instead of warning (today `VoucherWarnings` only warns, and only on the end date).
**Done when:** Every expense carries its bill number and payee, and an expense with no budget line shows in 2901 until it is corrected.

### F-12 Reversal vouchers and advance settlement
**Group:** Financial Manager's requests · **Priority:** Normal · **Effort:** 1.5 days · **Branch:** `f-12-reversal-and-settlement` · **Depends on:** F-02
**Prompt:** Add Reverse on an approved voucher: it creates a pending voucher with the same lines on the opposite sides, linked to the original (column via migration), with a reason; the original is never edited. Add "Advance settlement" to CPV in the Operations screen: pick the employee and an open advance (account 1201 by contact), enter the bills on budget lines (Dr expense / Cr 1201), and the difference is repaid in cash (CRV) or paid out (CPV). Show open advances per employee (the `advances` report exists).
**Done when:** A wrong voucher is corrected by a linked reversal, and an advance is settled with its bills showing what remains open.

### F-13 Donor report: budget against actual in the donor currency
**Group:** Financial Manager's requests · **Priority:** Normal · **Effort:** 2 days · **Branch:** `f-13-donor-report` · **Depends on:** none
**Prompt:** Add a donor report per project: for each budget line the budget, actual, remaining and percentage spent, in the donor currency (at the project's real rate from its receipts) and USD; money received from the donor in its currency; the funding period. XLSX and PDF export in English and Arabic (reuse `StatementExport`).
**Done when:** A project's report exports ready for the donor, and its figures agree with the financial statements.

### F-14 Audit log screen
**Group:** Financial Manager's requests · **Priority:** Low · **Effort:** 1.5 days · **Branch:** `f-14-audit-log` · **Depends on:** none
**Prompt:** Add an audit log screen for the Financial Manager and Head Office: voucher audits, request audits and web logins in one list (date, person, action, voucher or request with a link, reason), with filters by person, action and date and a CSV export. Read-only.
**Done when:** An auditor finds every change to any voucher or request, with who and when, on one screen.

---

## Inputs still needed from the business
1. A read-only FTP user for the attachment move, and the production FTP folder.
2. Confirmation of the two project names whose Arabic text was damaged in the mockup export (general account per city).
3. For T7.2, which master-data screens are edited in the web app versus read-only reference.
