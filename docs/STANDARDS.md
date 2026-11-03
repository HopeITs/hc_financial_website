# 08-Financial_WEB: standards for departments

> Written from the code by `/init-standards` on 2026-11-03 and **waiting for IT review**. The project's own UI rules in [`DESIGN.md`](../DESIGN.md) stay the source of truth for design; this file adds structure, permissions and the HC Workflow rules. Check anything here against the code before relying on it.

## 1. Stack and layout
- Laravel 13.17, PHP ^8.5, Livewire 4.1 (**class-based**), Flux 2.13, Tailwind 4 (tokens in CSS, no `tailwind.config`), Vite 8, Pest, Pint, PHPStan/Larastan.
- Run PHP through the project wrappers, not bare `php`: `./php.cmd artisan ...`, `./composer.cmd ...`. After editing PHP: `./php.cmd vendor/bin/pint --format agent`. Tests: `./php.cmd artisan test --compact tests/Feature/SomeTest.php` (run the narrowest test).
- Laravel Boost guidelines and skills are installed for Claude Code (`CLAUDE.md`, `.claude/skills`); follow them together with this file.
- Pages render inside `<x-layouts.app>` (`resources/views/components/layouts/app.blade.php`: top bar, sidebar, `flux:main`, content centred up to `max-w-[90rem]`). Other layouts: `layouts/auth`, `layouts/error`.
- A page is a Livewire class in `app/Livewire/<Name>.php` plus a view in `resources/views/livewire/<name>.blade.php`. Pages that are not built yet use `resources/views/pages/screen.blade.php`.
- Shared behaviour is in traits `app/Livewire/Concerns/`: `HasMonthFilter`, `HasReportCurrency`, `HasFormDraft`, `DefersLoading`. Reuse them before writing new state.
- Two languages: **English and Arabic (RTL)**. `<html dir>` comes from `App\Enums\Locale`. Text lives in `lang/en/<group>.php` and `lang/ar/<group>.php`, always both.
- Money and amounts in USD and SYP. The data comes from the finance program's database (a `legacy` connection) plus the `web_*` tables; tests build small in-memory schemas (`createFinanceSchema()` in `tests/Pest.php`).

## 2. Components (`resources/views/components`)
Use them as `<x-name>` or `<x-ui.name>`. **Reuse one before writing a new one.**

| Component | Use it for | Main props |
|---|---|---|
| `x-ui.page-header` | Start of every page: title, count badge, subtitle, actions | `title`, `count`, `subtitle` |
| `x-ui.panel` | Box for a table, list or form (never nest panels) | `title`, `subtitle`, `count` |
| `x-ui.stat-tile` | Key figures in a grid at the top | `label`, `value`, `unit`, `tone`, `icon` |
| `x-ui.empty-state` | Empty lists | `icon`, `heading`, `body` |
| `x-money` | **Every amount** | `amount`, `currency` |
| `x-ui.button` | Buttons | `tone`: `primary` `secondary` `ghost` `action` |
| `x-ui.form-input` / `form-textarea` | Text fields (label, required mark, help, error built in) | `label`, `id`, `type`, `prefix`, `suffix`, `required`, `help` |
| `x-ui.form-select` / `form-multiselect` | Dropdowns (searchable above 7 options) | `label`, `placeholder`, `required`, `help`, `disabled`, `searchable` |
| `x-ui.form-segmented` | Few exclusive choices | `label`, `options`, `value` |
| `x-ui.form-section` | Section of a long form | `title`, `hint` |
| `x-ui.modal` / `x-ui.confirm-modal` | Dialogs | `title`, `submit`, `close`, `size` / `confirm`, `confirmLabel`, `tone` |
| `x-ui.view-toggle` | Switch between views | `wireModel`, `options`, `active` |
| `x-ui.header-filter-select` / `header-filter-multiselect` | Filters in a table header | see the files |
| `x-ui.scroll-table` | Scrolling, paged table wrapper | `perPage`, `paginate` |
| `x-ui.month-filter`, `currency-switch` | Report filters | - |
| `x-ui.breadcrumb`, `x-navigation.topbar`, `x-navigation.sidebar` | Navigation | `current` |
| `x-ui.loader`, `report-loading`, `report-skeleton`, `saved-notice`, `form-draft`, `kbd` | Loading, saved and draft states | see the files |
| `x-net-assets`, `x-notification-item`, `x-home.*` | Home and notifications | see the files |

**Never use raw HTML or raw Flux inputs when a component exists** (DESIGN.md rule 6): not `<flux:input>`, `<flux:select>`, `<flux:textarea>`, and not a bare `<input>`, `<select>`, `<textarea>`. `flux:checkbox` and `flux:switch` are fine (already themed in `flux-overrides.css`). Status uses `<flux:badge size="sm">` (green active, zinc closed, amber needs attention), always with text.

## 3. Styling
- Tokens are in `resources/css/tokens.css` (`@theme`). `app.css` imports `tokens`, `base`, `typography`, `flux-overrides`, `forms`, `finance`, `shell`, `loader`, `operations`. Add feature styles after those.
- Colours: brand `primary` (#be1f24) only for the main action and the active nav item; `secondary-dark/medium/light/soft` greys; status `status-good/warn/critical/active` (+ `-soft`) only for status and sign; `funding`, `series-1..6` for charts; `ground`, `board`, `surface-0..3`, `ink*`, `line*` for surfaces and text. **Do not write hex colours in views.**
- Spacing scale: `ui-2xs` ... `ui-3xl` and `control`, `card`, `section`, `page`. Fonts: Lato (English) and Somar Sans (Arabic). Dark mode: the `.dark` class; every page must work in light and dark.
- Tables: `<div class="fin-table-wrap"><table class="fin-table">`, plain `<th>`/`<td>`, `fin-num` on number columns (header and cells), empty row `<td colspan="N" class="fin-empty">`. No `px-*`/`py-*` on cells.
- Amounts: `<x-money :amount="$value" />` (right aligned, tabular digits, minus sign, red losses, dim zero). Pass `currency="USD"`/`"SYP"` or put the currency in the column header. **Never `number_format` in a view.**
- RTL: use `start`/`end`, `ms`/`me`, `ps`/`pe`; never `left`/`right`/`ml`/`mr`/`pl`/`pr`. Digits stay LTR (`x-money` handles it). Keep pages under 90rem, text at least 14px (13px for table headers and captions).
- Motion: none beyond hover tint and Flux's own 120ms fades. No entrances, slides, scale, blur or view transitions; respect `prefers-reduced-motion`.
- Labels say the action ("Save voucher"), not "Submit". Names of projects and accounts in lists are links. One primary button, then Cancel.

## 4. Screens and permissions
- A **screen** is a page or area of the app that has its own permissions (`home`, `projects`, `vouchers`, `treasury`, reports ...). Its slug lives in `config/navigation.php`, which also gives its URL path; the route name is that path with dots (`reports/trial-balance` is `reports.trial-balance`).
- `config/permissions.php` says, for each role, which screens it sees and which actions it may do on each: `view`, `create`, `edit`, `delete`, `approve` (`App\Enums\ScreenAction`). Roles are in `App\Enums\RoleId` (Head office, Project manager, Financial manager, Accountant, Cashier, Employee, Donor, General manager, Program manager, Super user).
- Protect every route of a signed-in page with the `access` middleware inside the `auth` group:
  - `->middleware('access:vouchers')` needs `view` on the `vouchers` screen.
  - `->middleware('access:vouchers,create')` needs `create` on it.
  - With no slug it reads the route's `{screen}` value, as existing routes do with `->defaults('screen', 'projects')`.
- `screen:` is the same middleware under its original name. Existing routes keep it; **do not rename them**. Use `access:` in new code. (`App\Http\Middleware\EnsureScreenAccess`, aliases in `bootstrap/app.php`.)
- A **new screen** needs its slug added to `config/navigation.php` and `config/permissions.php`. Departments cannot edit `config/`, so ask IT with `/ask-it`: say which roles should see it and what they may do.
- Old `screens/<slug>` addresses redirect (301) to the new paths; keep those redirects.

## 5. Routes and where files go
- `routes/web.php` holds the existing routes: one `Route::middleware('auth')->group(...)` with `screen:` on every route. Departments do **not** edit it.
- New pages go in a new file `routes/modules/<name>.php`. Those files are loaded with the `web` middleware only, so each must wrap its routes in `Route::middleware('auth')->group(...)` **and** give every route an `access:` middleware. HC Workflow flags a new route file that has no `auth`.
- New code goes in new files: Livewire class `app/Livewire/`, trait in `app/Livewire/Concerns/`, view `resources/views/livewire/`, component `resources/views/components/`, enum `app/Enums/`, text in `lang/en` **and** `lang/ar`, test in `tests/Feature/`.
- Naming: classes StudlyCase (`TrialBalance`), views kebab-case (`trial-balance.blade.php`), translation groups lowercase (`lang/en/vouchers.php`).

## 6. Text and translations
Every visible string, label and message goes through `__('group.key')` with the key added to **both** `lang/en/<group>.php` and `lang/ar/<group>.php`. About 1,300 `__()` calls exist today; do not hard-code text.

## 7. Tests
Pest, in `tests/Feature/*Test.php`. Every change gets a test (the project's rule). Look at a neighbouring test and at `tests/Pest.php` helpers before writing one. Do not delete tests.

## 8. Do and don't (measured in the code today)
| Count in views | Rule | Status |
|---|---|---|
| `number_format(` x62 | Amounts must use `<x-money>` (some counts may be legitimate non-money numbers) | **deviation**: do not add more |
| raw `<flux:input/select/textarea>` x4 | Use `x-ui.form-*` | **deviation**: do not add more |
| `<flux:button>` x10 vs `<x-ui.button>` x112 | Use `x-ui.button` | prefer the component |
| arbitrary values like `w-[..]` x36 | Prefer the spacing and colour tokens | avoid new ones |
| static `style="..."` x7, hex colours x2 | No inline styles or hex in views | do not add more |
| `ml/mr/pl/pr/left/right` x0 | RTL-safe classes only | **clean: keep it at 0** |
| `<x-money>` x99, `__()` x1310 | Amounts and text through helpers | normal use |

Other rules: no new animation; check every page in Arabic (RTL), dark mode and a 375px phone; empty, error and loading states; keyboard focus visible; never nest panels; one primary button per screen.

## 9. Departments (HC Workflow)
All departments work the same way (only the branch prefix differs: `ct/...`, `finance/...`). **IT reviews and merges everything except style-only changes, and every question about this website goes to IT** (`/ask-it`).
- **Free:** views, components, CSS, lang, images, tests. **New files** (pages, Livewire classes, controllers, models, services, module route files) are free to create.
- **Needs IT approval in the pull request:** changing an existing backend file, existing routes, any migration or seeder, code that writes or deletes data. Never run migrations.
- **Forbidden (ask IT first):** `.env`, `config/`, `composer.json`, `package.json`, `bootstrap/`, the guard files, `docs/`, data files (csv, xlsx, sql), and here also `scripts/`, `AGENTS.md`, `boost.json`, `.mcp.json`, `phpunit.xml`, `pint.json`, `phpstan.neon`, `.gitleaks.toml`, `.npmrc`.
- Use `/submit` to send work. Style-only changes (style files, or Blade edits that only change plain `class="..."` values) merge by themselves.

## 10. Local sample data (no real finance data on department machines)
- **Tests are safe by design.** They build small in-memory SQLite schemas (`createFinanceSchema()` in `tests/Pest.php`) and never touch a real database. Run them freely.
- **To see pages in a browser** the app needs a MySQL **test copy** of the finance database (`.env.example` points at `hcsyria_financial_test` on 127.0.0.1). IT prepares the copy and the local `.env` and hands both to the department outside Git. Production or real finance credentials (`LEGACY_DB_*`, the live database) never go on a department machine.
- **Invented data.** `php artisan db:seed --class=DemoVouchersSeeder` adds `[DEMO]` vouchers (pending, approved and rejected) on a **test copy only** (the seeder refuses to run anywhere else). `RemoveDemoVouchersSeeder` takes them out. `ChartOfAccountsSeeder` seeds the chart of accounts. Claude cannot run `db:*` or `migrate` for departments (blocked): IT runs them, or the person runs them in their own terminal.

## 11. Decisions already applied for this project (for IT to review)
In `.hc-workflow/config.json`:
1. `<input>` maps to `ui.form-input`, `<select>` to `ui.form-select`, `<textarea>` to `ui.form-textarea`, `<button>` to `ui.button`.
2. New warnings on added lines: `number_format(` and `<flux:input|select|textarea>`. The 62 and 4 existing uses are left alone.
3. `bladeLogicGlobs` is empty: the logic is in `app/Livewire/*.php`, so the normal Blade checks apply to the views (adding `@php`, `DB::` and similar needs IT approval).

Elsewhere:
4. Laravel Boost is installed for **Claude Code only** (`boost.json` has `agents: claude_code`). `CLAUDE.md` and `AGENTS.md` hold the generated guidelines and `.claude/skills` the skills. Refresh with `./php.cmd artisan boost:update --no-discover --no-interaction`.
5. Screens: the `access` name stays, and a new screen is always requested from IT.
