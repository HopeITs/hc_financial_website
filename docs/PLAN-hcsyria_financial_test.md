# Plan: HC Financial Web on `hcsyria_financial_test` (v3)

v3 replaces the earlier drafts. Decisions confirmed: the 8 new SYP tills are approved; attachments are read over FTP; the migration reads the legacy database directly (no CSV upload); the Windows program is retired for new entries after go-live; Spatie is removed (done); legacy attachments are moved into the ledger. It follows the **Chart of Accounts mockup** (artifact `DbZn7RTmZyZBh2w6MqbWxM`, "Hope Center Finance"): a double-entry ledger (chart of accounts, vouchers, Dr/Cr lines, tags) that is **migrated from** the Windows program's single-entry data.

## 0. Answers to the open questions

| Question | Answer |
|----------|--------|
| **Password hash** | Confirmed, from `PasswordHelper.cs` (already ported in `app/Support/FinancePassword.php`, tested in `tests/Unit/FinancePasswordTest.php`) and from the data: `base64( salt[16] + PBKDF2-HMAC-SHA256(utf8 password, salt, 100000 iterations, 32 bytes) )` = 48 bytes = 64 base64 chars. All 14 stored values are 64 chars. **Finding:** all 14 users have the *identical* stored value, so they share one salt and one password. Fix before production (see 7.4). |
| **`deleted_at`** | Checked the real data. `action.deleted_at` is set on **0** rows, while `action_deleted` holds 12 rows and `action_not_approved` 22 rows (`action.IsApproved = 0` is also 0 rows). So the Windows program does **not** use `deleted_at`; it moves rows to side tables. For the new ledger the question disappears (see 1): the web app never edits `action`. The new ledger tables use Laravel `SoftDeletes` (`deleted_at`) for vouchers, and approved vouchers are never deleted, only reversed or replaced. |
| **Spatie** | **Removed** (`composer remove spatie/laravel-permission`; it was not used anywhere in the code). Roles come from the legacy `role` table, see sections 5 and 8. |
| **Attachments** | **Moved** to voucher attachments (section 3.2 `gl_voucher_attachments`, step 6.4). |

## 1. The big decision: two layers

| Layer | What | Web app may |
|-------|------|-------------|
| **Legacy** (Windows program, 55 tables) | `action`, `payment`, `till`, `account`, `budget_line`, `donor`, `employee`... | **Read only** (source of the migration and of master data). Never edit. |
| **Ledger** (new, `gl_*` tables) | chart of accounts, vouchers, voucher lines, month closes, allocation rates | Read and write |

Why: the mockup's accounts (1111 "Aleppo main safe - USD", 4101 "ACN", 5405...) are not the legacy `account` table (that one is the project accounts: the unit that *owns money*). Mapping per the mockup's own "WPF to chart mapping":

| Windows program | Ledger |
|-----------------|--------|
| Till (account x currency) | Cash account 111x USD / 112x SYP; the project becomes a **Project tag** on the line |
| `account` (project account) | **Project** (owner) with donor, restriction, city, currency, rate |
| `donor` | Income account 4101-4602 |
| `line` / `budget_line` | **Budget line** tag, linked to one expense account 51xx-58xx |
| Operation I / E / X / A / R / T | Voucher CRV / CPV / TRF / CPV(1201) / CRV / ALC or JV |
| `IsApproved`, role limit | Voucher approval by Finance Manager; the preparer never approves |

Consequence for deletes and approvals: `action_deleted` and `action_not_approved` are only read during the migration (deleted rows are skipped, not-approved rows become `Pending approval` vouchers).

> Confirmed: after go-live the Windows program stops being used for new entries. The import therefore runs as a rehearsal on the test copy, then once for the final cut-over (after a freeze of the Windows program). It stays idempotent so a rehearsal can be repeated safely.

## 2. Connection

| # | Step | Done when |
|---|------|-----------|
| 2.1 | `.env` and `.env.example`: `DB_DATABASE=hcsyria_financial_test`. | `php artisan db:show` lists the legacy tables |
| 2.2 | `config/database.php`: the legacy views (`cash_balances`, `tree_view`) fail under `ONLY_FULL_GROUP_BY` (verified: error 1055). Set `'modes'` without that flag instead of `strict => false`. | `select count(*) from cash_balances` works |
| 2.3 | Add connection `legacy` = same database, **read-only MySQL user** (`SELECT` only). Legacy models use `$connection = 'legacy'`, so the migration code physically cannot write to legacy tables. | An `INSERT` through `legacy` fails |
| 2.4 | Default connection user `hc_web`: full rights on `gl_*`, `sessions`, `cache`, `jobs`, `migrations` only; `SELECT` on the rest. Not `root`. | App runs without root |
| 2.5 | Check collations (Arabic names), match connection charset. | Arabic round-trips |
| 2.6 | `php artisan finance:check-db`: connects, reads `version.l_version` (expects `3.1.1.5`), checks the tables/views the app needs, prints row counts. | Green |

Auth keeps working unchanged: `user` is the account table (`ID, Username, Password, Role, State`). Add `State = 1` check and log the login.

## 3. Migrations (all new tables, nothing legacy is altered)

Run order. Back up first (`mysqldump`), run `migrate --pretend`, never `migrate:fresh` on `hcsyria_*` (guard in `AppServiceProvider`).

### 3.1 Framework
`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`. No `users` table.

### 3.2 Ledger (`gl_`) , from the mockup's data model

| Table | Columns (from the mockup) |
|-------|---------------------------|
| `gl_accounts` | `code` (PK, string 4, e.g. 1111), `parent_code` (self FK), `level` (Header/Posting), `class` (Assets, Liabilities, Net assets, Income, Expenses), `name_en`, `name_ar`, `statement_line`, `normal_side` (Debit/Credit), `currency` (USD/SYP/Any/null for headers), `city`, `is_cash`, `required_tags` (set: Project, Budget line, Fund source, Contact, Center), `usage_note`, `is_active`, `timestamps`, `deleted_at`. Seeded with the 156 accounts (34 headers, 122 posting; 12 USD, 12 SYP cash accounts) plus v7 changes (4108, 4306-4308, 1125-1127, 1135, 1145-1147, 1155, renames of 1115, 1171, 1172). |
| `gl_projects` | `code` (F2F, SZ, CORE, GEN-ALP, GEN-LTK, GEN-DAM, GEN-HMS, GEN-TRT), `name`, `donor`, `restriction` (With / Without donor restrictions), `city`, `donor_currency` (USD/EUR), `donor_rate`, `legacy_account_id` (maps to legacy `account.ID`), `is_general_account`. |
| `gl_budget_lines` | `key` (project\|name), `project_code`, `donor`, `name`, `account_code` (51xx-58xx), `budget` amount, `currency`, `legacy_budget_line_id`. |
| `gl_vouchers` | `id` (string, `CRV-2026-0001`), `type` (CRV, CPV, TRF, ALC, JV, OPN), `date`, `description`, `status` (Pending approval, Approved, Replaced), `prepared_by`, `approved_by`, `approved_at`, `is_carry_forward`, `is_temporary`, `source` (web / migration), `legacy_action_id`, `timestamps`, `deleted_at`. |
| `gl_voucher_lines` | `voucher_id`, `line_no`, `account_code`, `currency`, `debit_orig`, `credit_orig`, `rate`, `debit_usd`, `credit_usd`, `donor_currency`, `debit_donor`, `credit_donor`, `project_code`, `donor`, `budget_line_key`, `fund_source` (Donor grant / CHC own funds / Treasury / Own income / Recovered loans), `city`, `center`, `contact`, `restriction`, `reason`, `lira_type` (old / new, decision D1). Check constraint: a line is a debit or a credit, never both, never negative. |
| `gl_voucher_audit` | every change of an approved voucher: old value, new value, user, reason, date (rule 9). |
| `gl_voucher_sequences` | `type`, `year`, `last_number`; numbers are never reused (rule 10). |
| `gl_month_closes` | `month` (YYYY-MM), `closed_at`, `closed_by`. |
| `gl_allocation_rates` | `city`, `rate`, `effective_from`, `status` (Pending / Approved), `proposed_by`, `approved_by`; plus a flag "allocate at average cost" (decision D9, default off). |
| `gl_legacy_map` | `kind` (cash, project, donor, line), `legacy_key` (name\|currency), `target` (account code or project code), `confirmed_by`. The saved result of the mapping step in "Load from test database". |
| `gl_import_batches` / `gl_import_issues` | one row per run (type: opening, operations), counts, status; and the unmapped / skipped / rule-failure rows (see 6.3). |
| `gl_voucher_attachments` | `voucher_id`, `original_name`, `stored_path` (private disk, never under `public`), `mime`, `size`, `sha256`, `legacy_attachment_id` (unique, from `action_attachments.id`), `uploaded_by`, `timestamps`, `deleted_at`. |
| `gl_cash_till_merges` | `from_account`, `into_account`, `voucher_id`, `confirmed_by` (decision Q-b, Q-c). |

Indexes: `gl_voucher_lines(account_code, project_code)`, `(voucher_id)`, `gl_vouchers(date, status)`, `gl_vouchers(legacy_action_id)` unique when not null (makes the migration idempotent).

### 3.3 Web-only
`web_screen_permissions` is **not** created (see section 8). `web_user_preferences` (locale, theme) optional.

## 4. Models and domain code

- Legacy (read-only, connection `legacy`, `$timestamps = false`, PascalCase columns): `Action`, `ActionPayment`, `Payment`, `Beneficiary`, `Account`, `Till`, `TillType`, `CashAccount`, `Donor`, `Line`, `BudgetLine`, `Budget`, `Project`, `SubProject`, `City`, `Employee`, `EmployeeSalary`, `Rate`, `Role`, `User`, view models `CashBalance`, `TreeView`. Use the schema FKs for relations. `User` stays on the default connection (it is the login table) and reads `Password` only.
- Ledger: `GlAccount`, `GlProject`, `GlBudgetLine`, `Voucher`, `VoucherLine`, `VoucherAudit`, `MonthClose`, `AllocationRate`, `LegacyMap`, `ImportBatch`.
- Enums: `VoucherType`, `VoucherStatus`, `AccountClass`, `FundSource`, `RoleId`.
- Money: `decimal(18,2)` columns, `bcmath` in PHP, never floats. USD books; each line keeps its own currency and rate. A voucher must balance **exactly in USD**; cents go to 5503 (rule 4).
- **Posting service** `PostVoucher`: validates the 13 posting rules (below), numbers the voucher, writes lines in one DB transaction, writes the audit row.
- **Posting rules** (from the mockup "Posting rules"; enforce all, test each): only active posting accounts; cash account only in its own currency; debit or credit never both; balanced in USD; required tags filled; budget line only with its own project and fills its account; approver is never the preparer; nothing changes in a closed month; changes to approved vouchers keep old/new/who/why; numbers never reused; a used account keeps code, class and currency; month closes only when in-transit (11x3, 11x4) and suspense (2901) are zero; migrated vouchers keep their legacy id.
- **Treasury service**: general account per city (`GEN-ALP`, ...), SYP holdings and average cost, allocation voucher builder `ALC` (errors: no pounds in general account, not enough pounds, no approved rate), 5503 for rate difference.
- **Carry-forward / hybrid start** (decision D2): books start 1/1/2027 on temporary OPN vouchers, 2026 is loaded later, reconciled and closed, then the OPN carry-forward replaces the temporary one (status `Replaced`).

## 5. Authentication and roles (no Spatie)

Roles come from legacy `role`: 2 Head Office, 3 Project Manager, 4 Financial Manager, 5 Accountant, 6 Cashier, 7 Employee, 8 Donor. A `RoleId` enum plus one `config/permissions.php` map (role to screens to actions) and Laravel Policies. The mockup's "Acting as" switch has two personas, **Accountant** (prepares) and **Finance Manager** (approves); map Accountant = role 5, Finance Manager = role 4, Head Office = both.
Approval limits: `role_currency_limit` (per role and currency) is read from the legacy table when approving.

## 6. Screens (windows) and functionality

Navigation is `config/navigation.php` (37 slugs). The mockup renames/replaces some; the table gives the final behaviour.

### 6.1 Chart module

| Slug | Window | Functionality (from the mockup) |
|------|--------|--------------------------------|
| `chart` | Chart of accounts | Tree of 156 accounts by class (Assets, Liabilities, Net assets, Income, Expenses); filters: class, posting only, search; per account the balance, currency, city, required tags and "how to use". Editor drawer: code, level, EN/AR names, parent, class, normal side, currency, city, cash flag, statement line, tags, usage. Add child account; delete only if never used. A used account keeps code, class and currency and can only be deactivated. |
| `vouchers` | Vouchers | List with type chips (CRV, CPV, TRF, ALC, JV, OPN), status (Pending approval badge in the menu), date/search filters. **Voucher builder** modal: header (type, date, description), lines grid (account, currency, Dr/Cr, original amount, rate, USD, project, budget line, fund source, contact, center, lira type), live check list (balanced, tags filled, currency, month open), save as Pending. Approve / reject by Finance Manager, never the preparer. |
| `statement` | Account statement | One account, project filter, date range, running Dr/Cr balance, opening balance, drill-down to the voucher. |

### 6.2 Reports

| Slug | Window | Functionality |
|------|--------|---------------|
| `tb` | Trial balance | Per account Dr / Cr / net as at a date (`asAt`), approved vouchers only, by class, check Dr = Cr. |
| `projects` | Project balances | Per project (owner): income, expenses (5703 depreciation excluded, decision D10), cash held, restricted fund balance; budget versus actual per budget line; donor-currency view (EUR rate). |
| `statements` | Financial statements | Statement of Financial Position and Statement of Activities built from `statement_line`; 5503 exchange differences under financial expenses (D11); export. |
| existing report slugs (`rexp`, `rcash`, `rbl`, `rrev`, `rfixed`, `rattach`) | Expense, cash, budget line, revenue, fixed assets, attachments | Rebuilt on `gl_voucher_lines` (not on `action`), same filters as the Windows program so totals can be compared. `rattach` reads `gl_voucher_attachments` only. |

### 6.3 Treasury

| Slug | Window | Functionality |
|------|--------|---------------|
| `treasury` | Treasury position | Per city: general account (e.g. GEN-ALP) SYP held, USD paid, average rate, bulk purchases (TRF to 1112 etc.), allocations (ALC), 5503 reserve, cash by safe/currency, in-transit and transfer-company (1161) balances. |
| `alloc` | Allocation rates | Per city rate with effective date; proposed by Accountant, **approved by Finance Manager**; allocation basis choice (approved rate or average cost, optional switch, default off). |
| `tills`, `currency`, `denoms`, `cities`, `donors` ... | Master data | Kept as the mockup's CRUD screens, but master data of the ledger (cash accounts, donors as income accounts, cities) is edited in the chart/projects; legacy lists are read-only reference. |

### 6.4 Migration module (the heart of the cut-over)

| Slug | Window | Functionality |
|------|--------|---------------|
| `dbload` | Load from test database | Replaces the mockup's CSV upload: read the legacy database directly (`legacy` connection) using the same columns as the mockup's SQL scripts (opening: `CashAccountID, CashAccountName, City, TillCurrency, AccountID, AccountName, Balance`; operations: `ID, ActionDate, Type, TillCurrency, AccountID, AccountName, SubprojectName, City, Amount, Rate, RateGroup, Note, E_Note, IsReceipt, MicroProject_ID, LineName, DonorName, CashAccountName, ObserveAccountName, MirrorAmount, MirrorTillCurrency, MirrorCashAccountName`). Step 1 mapping screen with auto-guess (cash account by name+currency+city, project by name, donor by name, budget line by keyword table) saved in `gl_legacy_map`. Step 2 staging: each operation becomes a voucher or an issue (`map` = unmapped name, `rule` = needs a rule, e.g. type T). Posting: I to CRV (Dr cash, Cr donor income), E to CPV (Dr expense, Cr cash; budget line fills account), A to CPV (Dr 1201), R to CRV (Cr 1204 if micro-loan else 1201), X to TRF (Dr SYP safe, Cr USD safe), T to ALC/JV after the allocation rule. Missing rate: use fallback rate and flag it (`fb`). Idempotent via `legacy_action_id`. Skips `action_deleted`; `action_not_approved` become Pending. |
| `attach` (step inside `dbload`) | Move attachments | For each `action_attachments` row (2,055; column `attach_name`), find the voucher by `legacy_action_id`, copy the file to the private disk, store its sha256, create the `gl_voucher_attachments` row. Report: copied, missing source file, no matching voucher (deleted or skipped action), checksum mismatch. Idempotent by `legacy_attachment_id`. Files are read over FTP, see section 10. |
| `opening` | Opening and month close | OPN vouchers from legacy balances at 31/12/2025 with 3901 balancing line per project (must be zero afterwards); hybrid start; per-month **close checks** (previous month closed, no unapproved vouchers, 11x3, 11x4 and 2901 zero); year-end carry-forward to 1/1/2027 (restricted projects to 3201, others to 3101). |
| `fixusd` | Fix USD report | Operations in SYP with stored rate 0 (about 4,618): fix the dollar value at the project average rate of the operation date (D7); report new versus old value; apply in a transaction. |
| `legacy` | Legacy exchange | About 470 old exchange groups: one rate, generate TRF vouchers with no 5503 line (D8). |
| `merges` | Cash till merges | Merge a closed till into another (Jouliana to 1142, Q-b) and write off Al-Nouzha (Q-c) with an adjustment voucher; type-to-confirm. |

### 6.5 Bridge pages (read-only, useful during the project)
`decisions` (the decisions log D1-D11, Q-a to Q-e), `mapping` (WPF to chart mapping), `rules` (posting rules with Live/Described flag). Add to the navigation as a "Bridge" group, visible to Head Office and Financial Manager only.

### 6.6 Other mockup screens (donors, employees, salaries, single/multi entry, payments, bonds, approval, home...)
These stay as in the existing navigation. Rule: any screen that today writes to `action` (`single`, `multi`, `multiatt`, `payments`, `approval`, `salaries`) is rebuilt to **post a voucher** through `PostVoucher` using the mockup's "operation to voucher" rule (the posting rule for every desktop operation). They never write to legacy tables.

## 7. Delivery phases

| Phase | Content | Exit criteria |
|-------|---------|---------------|
| 1 | Section 2 (connection), 3.1 framework tables, legacy read models, `finance:check-db` | Login on `hcsyria_financial_test`; `/home` loads |
| 2 | Section 3.2 migrations + seeders (156 accounts, 8 projects, 10+ budget lines), `PostVoucher` with all 13 rules, Pest tests per rule | Every rule has a passing and a failing test |
| 3 | `chart`, `vouchers` (builder, approval), `statement` | Accountant prepares, Finance Manager approves, balances update |
| 4 | `tb`, `projects`, `statements`, rebuilt `r*` reports | Balances tie to the voucher lines |
| 5 | `treasury`, `alloc` | ALC voucher posts with the right 5503 difference |
| 6 | `dbload` (with attachment move), `opening`, `fixusd`, `legacy`, `merges` on the test copy | Imported totals per cash account and project equal the Windows program's balances (parity test); attachment count in = count out |
| 7 | Entry screens rebuilt on vouchers, master data screens, Bridge pages | UAT |
| 8 | Hardening (7.4); freeze the Windows program; final import into `hcsyria_financial`; point `.env` at it; archive the Windows program and old database read-only | Sign-off |

### 7.4 Security fixes before production
- All 14 users share one stored password: force a reset on first web login (write with `FinancePassword::make`, which uses a fresh random salt per user; the Windows program reads the same format).
- `hc_web` MySQL user, read-only `legacy` connection, throttled login (exists).
- Audit: every voucher change in `gl_voucher_audit`; month close is irreversible without a Head Office reopen that is itself audited.

## 8. Spatie (removed)

`spatie/laravel-permission` kept its own roles/permissions tables, which would have duplicated the legacy `role` table. It was removed from `composer.json` and `composer.lock` (nothing used it). If managers later need to tick permissions per role without a code change, add that as a small `web_role_screens` table then.

## 9. Testing
- A separate schema `hcsyria_financial_testing` (legacy structure + a small anonymised seed) for Pest; never `RefreshDatabase` on `hcsyria_financial_test`.
- Unit: each posting rule, average cost / allocation maths, carry-forward, password format.
- Parity: the staged opening and operations must reproduce the Windows program's balances per cash account and project (the key acceptance test).
- Feature: policies per role, "preparer cannot approve", closed month blocks edits, idempotent re-import.
- PHPStan (config exists) and Pint.

## 10. Decisions closed and what is left

- **Q3 (attachment location) answered from the Windows source.** The files are on an **FTP server**, not a network share: `Login.cs` sets `OnlineRoot = ftp://hcsyria.org/financial/files/test` for the test setup, `Document.SaveDocument` uploads each file as `<OnlineRoot>/<attach_name>`, and `attach_name` is the file name stored in `action_attachments` (for example `Action_id_<id>_<name>.pdf`; 2,044 PDF, 10 JPG, 1 JPEG). The move in 6.4 therefore: read the file over FTP from `<root>/<attach_name>` with a Laravel `ftp` disk (`league/flysystem-ftp`), settings in `.env` only (`ATTACH_FTP_HOST`, `ATTACH_FTP_USER`, `ATTACH_FTP_PASSWORD`, `ATTACH_FTP_ROOT`), then store on the private disk. The production root is probably `.../financial/files` without `/test`: confirm at cut-over.
- **Security finding:** the Windows source contains the FTP user and password in clear text (`Helper/Document.cs`) and the online MySQL password (`Login.cs`). They are deliberately not copied into this repository. Ask for a dedicated read-only FTP user for the migration, and rotate those credentials after go-live.
- **Q4 closed:** the migration reads the live database directly through the read-only `legacy` connection; the Windows program is frozen before the final run.
- **Q5 closed:** the eight new SYP tills (1125-1127, 1135, 1145-1147, 1155) and the rename of 1115 to Science Zone Aleppo are approved as listed in the mockup and go into the seed.
- **Still open:** a read-only FTP user for the migration, and the production FTP root.

## 11. Git workflow: one task, one branch

Rule: **every task (T1.1, T1.2, ...) gets its own branch from an up-to-date `main`, and nothing else is done on that branch.** When the task is finished it is committed, pushed, merged into `main`, and only then the next task starts on a new branch.

| Step | Command (PowerShell) |
|------|----------------------|
| 1. Start from a clean, current main | `git switch main; git pull origin main` (`git status` must be clean) |
| 2. New branch named after the task | `git switch -c t1-1-connect-db` (pattern `t<phase>-<n>-<short-name>`) |
| 3. Work, run the checks | `.\php.cmd artisan test`, `vendorin\pint --test`, `vendorin\phpstan analyse` |
| 4. Commit (one or more commits, message starts with the task id) | `git add -A; git commit -m "T1.1: point app at hcsyria_financial_test"` |
| 5. Push the branch | `git push -u origin t1-1-connect-db` |
| 6. Merge into main | Preferred: open a pull request and merge it (squash or merge commit). Without review: `git switch main; git merge --no-ff t1-1-connect-db -m "Merge T1.1"; git push origin main` |
| 7. Clean up | `git branch -d t1-1-connect-db; git push origin --delete t1-1-connect-db` |
| 8. Next task | go back to step 1 with the next task id |

Rules:
- Never start a task on top of an unmerged task branch. If a task depends on another, the other is merged first (the **Depends on** line in `TASK-PROMPTS.md`).
- Never commit to `main` directly, never force-push `main`, never skip hooks.
- Never commit `.env`, passwords, FTP credentials or database dumps.
- A task is "done" only when its **Done when** check passes and the branch is merged. Tests, Pint and PHPStan must be green before the merge.
- Parallel tasks (for example T1.1 and T1.4) are allowed only in separate branches that touch different files; merge them one at a time and rebase the second on `main` before merging.
- If a merge conflicts, resolve it on the task branch (`git merge main`), re-run the checks, then merge.
- **Prerequisite:** this repository has no `origin` remote yet. Create the remote repository (GitHub or other) and run `git remote add origin <url>; git push -u origin main` before the first task, otherwise steps 1 and 5 cannot run.
