# Developer task prompts

Companion to [PLAN-hcsyria_financial_test.md](PLAN-hcsyria_financial_test.md). Each task is a self-contained prompt: hand it to a developer or to an AI coding agent as written. Do them in order; a task starts only when its **Depends on** tasks are merged.

Common rules for every task (paste at the top of each prompt):

> Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5), not the machine default 8.4. Read `CLAUDE.md`, `AGENTS.md` and `docs/PLAN-hcsyria_financial_test.md` first. The database `hcsyria_financial_test` belongs to the Windows program: **never alter or write legacy tables** (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. Do not put credentials in the repository.

Git rule for every task (see section 11 of the plan): start from an up-to-date `main`, create the branch named in the task (`t<phase>-<n>-<name>`), do only that task, commit with messages starting with the task id, push the branch, merge it into `main` (pull request preferred), push `main`, delete the branch, and only then start the next task on a new branch.

Status key: T = task, P = phase of the plan.

---

## Phase 1: Connection and foundations

### T1.1 Point the app at `hcsyria_financial_test`
**Depends on:** none
**Prompt:** Set `DB_DATABASE=hcsyria_financial_test` in `.env.example` (and tell the developer to do it in `.env`). In `config/database.php` remove `ONLY_FULL_GROUP_BY` from the MySQL `modes` instead of turning `strict` off; the legacy views `cash_balances` and `tree_view` currently fail with error 1055. Check the legacy tables' collation (`information_schema.tables`) and set the connection charset/collation to match so Arabic text round-trips.
**Done when:** `.\php.cmd artisan db:show` lists the legacy tables; a tinker query `DB::table('cash_balances')->count()` works; an Arabic `donor.Name` prints correctly.

### T1.2 Read-only `legacy` connection and least-privilege user
**Depends on:** T1.1
**Prompt:** Add a second connection `legacy` in `config/database.php` (same database, credentials `LEGACY_DB_USERNAME` / `LEGACY_DB_PASSWORD` from `.env`). Write `docs/db-users.sql` with the statements to create two MySQL users: `hc_web_legacy` (SELECT only on `hcsyria_financial_test.*`) and `hc_web` (SELECT on legacy tables; ALL on tables named `gl_*`, `sessions`, `cache*`, `jobs*`, `failed_jobs`, `migrations`). Do not run it against production; document how to run it locally.
**Done when:** with the read-only user, `DB::connection('legacy')->table('donor')->insert([...])` throws; the app still logs in.

### T1.3 Block destructive commands
**Depends on:** T1.1
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
**Prompt:** Create migrations `gl_accounts`, `gl_projects`, `gl_budget_lines` exactly as in section 3.2 of the plan (columns, types, self FK on `parent_code`, indexes, soft deletes). `gl_accounts.code` is a string primary key. Add Eloquent models `GlAccount`, `GlProject`, `GlBudgetLine` with relations and enums `AccountClass`, `AccountLevel`, `NormalSide`.
**Done when:** migrations run on a scratch schema (not the test database) and roll back.

### T2.3 Ledger migrations: vouchers
**Depends on:** T2.2
**Prompt:** Create `gl_vouchers`, `gl_voucher_lines`, `gl_voucher_audit`, `gl_voucher_sequences`, `gl_voucher_attachments`, `gl_month_closes`, `gl_allocation_rates`, `gl_legacy_map`, `gl_import_batches`, `gl_import_issues`, `gl_cash_till_merges` per the plan. Add a database check constraint on `gl_voucher_lines`: debit or credit, never both, never negative, both columns `decimal(18,2)`. Unique index on `gl_vouchers.legacy_action_id` (nullable) and on `gl_voucher_attachments.legacy_attachment_id`. Models and enums `VoucherType` (CRV, CPV, TRF, ALC, JV, OPN), `VoucherStatus` (PendingApproval, Approved, Replaced), `FundSource`.
**Done when:** migrations run and roll back on the scratch schema; the check constraint rejects a line with both Dr and Cr.

### T2.4 Seed the chart of accounts, projects and budget lines
**Depends on:** T2.2
**Prompt:** Open the mockup artifact `DbZn7RTmZyZBh2w6MqbWxM` (data object `D`: `acc`, `proj`, `bl`) and write a seeder with those 156 accounts (34 headers, 122 posting), the 8 projects (F2F, SZ, CORE, GEN-ALP, GEN-LTK, GEN-DAM, GEN-HMS, GEN-TRT) and the budget lines. Apply the v7 changes: add 4108, 4306-4308, 1125-1127, 1135, 1145-1147, 1155; rename 1115, 1171, 1172. Keep the Arabic names as UTF-8 (the artifact export shows replacement characters in two project names: use `General account - <city>` and flag it in the PR). Store the data in `database/seeders/data/*.json`, not inline.
**Done when:** counts are 156 + 8 accounts' worth of v7 rows as in the mockup, every `parent_code` resolves, every posting account has a class, side and statement line.

### T2.5 Posting service and the 13 rules
**Depends on:** T2.3, T2.4
**Prompt:** Create `app/Services/Ledger/PostVoucher.php`. Input: voucher header and lines. In one DB transaction it validates, numbers (`CRV-2026-0001`, from `gl_voucher_sequences` with a row lock, never reused), saves with status `PendingApproval`, and writes an audit row. Implement and unit-test each rule separately (one class per rule, one passing and one failing test each): only active posting accounts; cash account only in its own currency; debit or credit never both and never negative; balanced exactly in USD (cents go to 5503); required tags filled; budget line belongs to the line's project and fills its account; approver is never the preparer; nothing changes in a closed month; changes to an approved voucher keep old value, new value, user and reason; numbers never reused; a used account keeps code, class and currency; month closes only when 11x3, 11x4 and 2901 are zero; migrated vouchers keep their legacy id.
**Done when:** the rules suite is green; a failed rule leaves no rows and no consumed number.

### T2.6 Roles and policies (no Spatie)
**Depends on:** T1.7
**Prompt:** Create enum `RoleId` (2 Head Office, 3 Project Manager, 4 Financial Manager, 5 Accountant, 6 Cashier, 7 Employee, 8 Donor), `config/permissions.php` (role to screen to action: view, create, edit, delete, approve) and Laravel Policies/Gates. Accountant prepares, Financial Manager approves, Head Office both. Approval limits come from legacy `role_currency_limit` (fallback `role.Approval_Limit`). Add a Livewire/route middleware `can:screen,<slug>` applied to every route in `routes/web.php`.
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
**Prompt:** Build the modal/page to create a voucher: header (type, date, description) and a lines grid (account, currency, Dr/Cr, original amount, rate, USD, project, budget line, fund source, contact, center, lira type old/new). Choosing a budget line fills its account; choosing a cash account fixes the currency; SYP payments take the project average rate; show the live check list (balanced, tags, currency, open month). Save through `PostVoucher`. Attachment upload to the private disk (`gl_voucher_attachments`).
**Done when:** a CRV, CPV and TRF can be prepared and appear as Pending; the check list matches the server-side rules.

### T3.4 Account statement (`statement`)
**Depends on:** T2.5
**Prompt:** One account, project filter, date range, opening balance, running Dr/Cr balance, link to each voucher, CSV export. Approved vouchers only.
**Done when:** the running balance equals the trial balance for the same account and date (test).

---

## Phase 4: Reports

### T4.1 Trial balance (`tb`)
**Depends on:** T3.4
**Prompt:** As-at date, per account Dr / Cr / net, grouped by class, total Dr = total Cr check, approved vouchers only, export.

### T4.2 Project balances (`projects`)
**Depends on:** T4.1
**Prompt:** Per project: income, expenses (exclude 5703 depreciation, decision D10), cash held, restricted fund balance, budget versus actual per budget line, donor-currency (EUR) view using the project rate.

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
**Prompt:** Per city: general account (GEN-ALP, GEN-LTK, GEN-DAM, GEN-HMS, GEN-TRT) SYP held and average cost, bulk purchases (TRF into the SYP safe), cash by safe and currency, in-transit (11x3, 11x4), transfer company 1161, 5503 reserve.

### T5.2 Allocation rates and ALC vouchers (`alloc`)
**Depends on:** T5.1
**Prompt:** Rates per city with effective date: proposed by Accountant, approved by Financial Manager. Build the ALC voucher: Dr project SYP, Cr general account SYP at its average rate, USD legs on 1161, difference to 5503. Errors exactly as in the mockup (no pounds in the general account, not enough pounds, no approved rate). Optional "allocate at average cost" switch, default off (decision D9).

---

## Phase 6: Migration module (on the test copy first)

### T6.1 Mapping step of `dbload`
**Depends on:** T2.1, T2.4
**Prompt:** Read distinct cash accounts (name, currency, city), project accounts, donors and budget lines from the legacy database. Auto-guess targets (cash by name, currency and city; project by name; donor by name; budget line by the keyword table in the mockup `LINEKW`). Screen to confirm or change each mapping; save in `gl_legacy_map` with the confirming user.

### T6.2 Staging and posting of operations
**Depends on:** T6.1, T2.5
**Prompt:** Per legacy operation produce a voucher or an issue in `gl_import_issues`: I to CRV (Dr cash, Cr donor income), E to CPV (Dr expense, Cr cash, budget line fills account), A to CPV (Dr 1201, contact tag), R to CRV (Cr 1204 if micro-loan else 1201), X to TRF (Dr SYP safe, Cr USD safe), T to ALC/JV after the allocation rule. Missing rate: use the fallback rate and flag it. Skip `action_deleted`; `action_not_approved` becomes Pending. Idempotent through `legacy_action_id`; run in chunks as a queued job with a progress bar; dry-run mode.
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

## Inputs still needed from the business
1. A read-only FTP user for the attachment move, and the production FTP folder.
2. Confirmation of the two project names whose Arabic text was damaged in the mockup export (general account per city).
3. For T7.2, which master-data screens are edited in the web app versus read-only reference.
