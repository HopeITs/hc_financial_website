# موجّهات المهام (الخطة v2 — 45 مهمة)

مولَّد من [2026-10-21-finance-implementation-plan.ar.html](2026-10-21-finance-implementation-plan.ar.html) بالأمر `python scripts/build_task_prompts.py`، فلا تعدّله يدوياً: عدّل الخطة ثم أعد التوليد. المهام في ClickUp (قائمة Financial WEB) تتبع نفس الترقيم، وتتزامن عبر `scripts/clickup_sync.py`.

**المواعيد:** الرئيسية والشجرة والبرامج والمشاريع بتاريخ 2026-10-30، وكل ما تبقى قبل 2026-11-30. ترتيب التنفيذ بحسب الاعتماديات وليس بالرقم. المهمة الواحدة = فرع Git واحد = Pull Request واحد، والبرومبت يُنسخ كما هو إلى Claude (نصه بالإنكليزية).

---


## المرحلة 0: المرحلة الأولى (حتى 30 أكتوبر): الأساس والرئيسية والشجرة والبرامج والمشاريع · A + B · 22–30 أكتوبر


### 01 · ورشة القرارات وتوقيع القصة المصححة مع المدير المالي

- **المطوّر والتواريخ:** المطوّر A + المطوّر B · التواريخ: 2026-10-22 (صباحًا)
- **الجهد المقدّر:** ساعتان + ساعتان مع المدير المالي
- **الاعتماديات:** None

#### Objective
تثبيت كل ما حُسم من المدير المالي في ملف واحد، وتسليم ما بقي مفتوحًا بقيمه الافتراضية قبل كتابة أي شيفرة. السبب: الخطة بنيت من نموذج الـ mockup ثم عُدّلت بثلاث جولات أسئلة، ويجب ألا يبدأ أي مطوّر من افتراض غير موثق.


#### Implementation scope

- جمع قرارات الجولات الثلاث (قسما 6.8 و6.9 من الخطة) وقرارات D1-D11 وQ-a إلى Q-e في جدول واحد.
- تحديد البنود المفتوحة: تعارض التحويل الشهري من كاش المشروع إلى المدينة، وتأكيد رقم الحساب 5504، واسما المشروعين التالفين في تصدير الـ mockup.
- جلسة قصيرة مع المدير المالي (ساعتان) لتوقيع الملف، ويحضرها المطوّران.


#### Required deliverables

- docs/DECISIONS.md بالتاريخ وصاحب القرار والمهمة التي يفتحها القرار.
- قائمة المدخلات الخارجية المطلوبة (مستخدم FTP للقراءة فقط، الجذر الإنتاجي، أرقام الحسابات الجديدة).


#### Business rules

- أي بند لا يُحسم خلال يوم عمل واحد يُطبَّق عليه الافتراضي الموصى به ويُوسم «قابل للمراجعة».
- القرار الذي يغيّر قاعدة موثّقة في الخطة يُعدَّل فيه قسم الخطة المعني في الفرع نفسه.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases

- غياب المدير المالي: يُطبَّق الافتراضي ويُعاد عرض البند في نقطة التكامل التالية.


#### Acceptance criteria

- كل قرار له حالة «مقرر» أو «افتراضي مطبّق».
- لا مهمة في الأسبوع الأول تعتمد على بند مفتوح.


#### Mistakes to avoid

- البدء بالبرمجة على افتراضات غير موثقة.
- تسجيل القرارات في المحادثة فقط.


#### Output and handoff to the next task
تعتمد عليها: Task 02.

#### Claude prompt

````text
## Role and goal
You are implementing Task 01 of the HC Financial Web plan (ورشة القرارات وتوقيع القصة المصححة مع المدير المالي). Branch: `t0-1-decisions-pack`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-10-22. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Prepare the sign-off pack for the Financial Manager. You write NO application code. Output: `docs/DECISIONS.md` listing every decision taken so far (rounds 1, 2 and 3 of the Financial Manager's answers, plus D1-D11 and Q-a to Q-e from the mockup) with date, owner and the task it unblocks, and a short list of what is still open. Read `docs/PLAN-hcsyria_financial_test.md` sections 6.8 and 6.9, `docs/ANSWERS-CFO-2-AR.md`, `docs/QUESTIONS-CFO-AR.md`, `docs/QUESTIONS-CFO-3-AR.md`. Never reset a decision already recorded as decided; mark each open one with the default that applies after one working day.

## Git
Start from an up-to-date `main`, create branch `t0-1-decisions-pack`, commit with messages starting `T01:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 02 · ربط التطبيق بقاعدة hcsyria_financial_test

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-10-22
- **الجهد المقدّر:** ساعتان
- **الاعتماديات:** Task 01

#### Objective
أن يقرأ التطبيق جداول برنامج ويندوز كما هي، بما فيها العرضان القديمان اللذان يفشلان مع ONLY_FULL_GROUP_BY، وأن تُحفظ الأسماء العربية سليمة.


#### Implementation scope

- DB_DATABASE في .env.example.
- ضبط modes في config/database.php بدل strict => false.
- فحص ترميز الجداول (collation) ومطابقة charset الاتصال.


#### Required deliverables

- تعديل config/database.php و.env.example.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions

- لا يُرفع .env إلى Git.


#### Exceptional and edge cases

- اختلاف ترميز جدول عن آخر: يُوثَّق ولا يُعدَّل الجدول القديم.


#### Acceptance criteria

- php artisan db:show يعرض الجداول القديمة.
- الاستعلام select count(*) from cash_balances يعمل.
- اسم مانح عربي يُطبع سليمًا.


#### Mistakes to avoid

- إطفاء strict كليًا.
- تعديل أي جدول قديم لإصلاح الترميز.


#### Output and handoff to the next task
تعتمد عليها: Task 03، Task 04.

#### Claude prompt

````text
## Role and goal
You are implementing Task 02 of the HC Financial Web plan (ربط التطبيق بقاعدة hcsyria_financial_test). Branch: `t0-2-connect-db`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-10-22. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Set `DB_DATABASE=hcsyria_financial_test` in `.env.example` (and tell the developer to do it in `.env`). In `config/database.php` remove `ONLY_FULL_GROUP_BY` from the MySQL `modes` instead of turning `strict` off; the legacy views `cash_balances` and `tree_view` currently fail with error 1055. Check the legacy tables' collation (`information_schema.tables`) and set the connection charset/collation to match so Arabic text round-trips.

## Done when
`.\php.cmd artisan db:show` lists the legacy tables; a tinker query `DB::table('cash_balances')->count()` works; an Arabic `donor.Name` prints correctly.

## Preconditions
These tasks must be merged first: 01. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t0-2-connect-db`, commit with messages starting `T02:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 03 · اتصال legacy للقراءة فقط ومستخدمو MySQL بأقل صلاحية

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-10-22–23
- **الجهد المقدّر:** نصف يوم
- **الاعتماديات:** Task 02

#### Objective
جعل الكتابة في الجداول القديمة مستحيلة فيزيائيًا: أي كود ترحيل يمر عبر مستخدم لا يملك إلا SELECT.


#### Implementation scope

- اتصال legacy بمستخدم hc_web_legacy (SELECT فقط).
- مستخدم hc_web: صلاحيات كاملة على gl_* وجداول الإطار وSELECT على الباقي، ولا يُستعمل root.
- ملف docs/db-users.sql مع شرح التشغيل محليًا.


#### Required deliverables

- docs/db-users.sql.
- اتصال legacy في الإعدادات.


#### Business rules

- نماذج الجداول القديمة كلها على الاتصال legacy وليس الافتراضي.


#### Security and permissions

- كلمات المرور في .env فقط، ولا تُكتب في الملف المرفوع.
- لا يُنفَّذ db-users.sql على الإنتاج من هذه المهمة.


#### Exceptional and edge cases

- كلمة مرور المستخدمين محليًا تختلف عن الإنتاج: يُشرح ذلك في الملف.


#### Acceptance criteria

- INSERT عبر legacy يفشل.
- الدخول إلى التطبيق ما زال يعمل.


#### Mistakes to avoid

- استعمال root.
- منح hc_web صلاحية كتابة على جداول قديمة.


#### Output and handoff to the next task
تعتمد عليها: Task 06، Task 07، Task 08، Task 09.

#### Claude prompt

````text
## Role and goal
You are implementing Task 03 of the HC Financial Web plan (اتصال legacy للقراءة فقط ومستخدمو MySQL بأقل صلاحية). Branch: `t1-2-legacy-readonly`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-10-23. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Add a second connection `legacy` in `config/database.php` (same database, credentials `LEGACY_DB_USERNAME` / `LEGACY_DB_PASSWORD` from `.env`). Write `docs/db-users.sql` with the statements to create two MySQL users: `hc_web_legacy` (SELECT only on `hcsyria_financial_test.*`) and `hc_web` (SELECT on legacy tables; ALL on tables named `gl_*`, `sessions`, `cache*`, `jobs*`, `failed_jobs`, `migrations`). Do not run it against production; document how to run it locally.

## Done when
with the read-only user, `DB::connection('legacy')->table('donor')->insert([...])` throws; the app still logs in.

## Preconditions
These tasks must be merged first: 02. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t1-2-legacy-readonly`, commit with messages starting `T03:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 04 · منع الأوامر المدمّرة على قواعد hcsyria_*

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-10-22
- **الجهد المقدّر:** ساعة
- **الاعتماديات:** Task 02

#### Objective
ألا يستطيع أحد تنفيذ migrate:fresh ولا db:wipe على قاعدة البرنامج القديم بالخطأ.


#### Implementation scope

- DB::prohibitDestructiveCommands() في AppServiceProvider::boot عندما يبدأ اسم القاعدة بـ hcsyria_.
- اختبار Pest بإعداد وهمي.


#### Required deliverables

- تعديل AppServiceProvider + اختبار.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- php artisan migrate:fresh يُرفض.
- الاختبار ينجح.


#### Mistakes to avoid

- الاعتماد على التنبيه الشفهي بدل الحارس البرمجي.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 04 of the HC Financial Web plan (منع الأوامر المدمّرة على قواعد hcsyria_*). Branch: `t1-3-block-destructive`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-10-22. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
In `AppServiceProvider::boot`, call `DB::prohibitDestructiveCommands()` when the configured database name starts with `hcsyria_`, so `migrate:fresh`, `migrate:refresh`, `migrate:reset` and `db:wipe` refuse to run. Add a Pest test with a fake config proving it.

## Done when
`.\php.cmd artisan migrate:fresh` refuses on this database.

## Preconditions
These tasks must be merged first: 02. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t1-3-block-destructive`, commit with messages starting `T04:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 05 · إزالة بقايا Spatie والتحقق من الإعدادات

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-10-22
- **الجهد المقدّر:** ساعة
- **الاعتماديات:** None

#### Objective
التأكد أن حزمة الصلاحيات لم تترك أثرًا يكرّر جدول role القديم.


#### Implementation scope

- فحص composer.json/composer.lock وconfig/ وbootstrap/cache والترحيلات المنشورة.
- إعادة package:discover.


#### Required deliverables

- تقرير قصير في وصف الدمج بما وُجد وما حُذف.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- grep -ri spatie خارج vendor وdocs لا يجد شيئًا.
- الاختبارات تنجح.


#### Mistakes to avoid

- حذف ملفات لا علاقة لها بالحزمة.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 05 of the HC Financial Web plan (إزالة بقايا Spatie والتحقق من الإعدادات). Branch: `t1-4-spatie-leftovers`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-10-22. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Confirm `spatie/laravel-permission` is gone (composer files, `config/`, `bootstrap/cache`, published migrations). Make sure nothing references `HasRoles`, `Permission` or `Role` facades from Spatie. Re-run `package:discover`.

## Done when
`grep -ri spatie` outside `vendor` and `docs` finds nothing; tests pass.

## Git
Start from an up-to-date `main`, create branch `t1-4-spatie-leftovers`, commit with messages starting `T05:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 06 · الأمر finance:check-db

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-10-26
- **الجهد المقدّر:** ساعتان
- **الاعتماديات:** Task 03

#### Objective
فحص صحي واحد قبل أي عمل: هل القاعدة هي نسخة البرنامج المتوقعة وهل الجداول والعروض كلها موجودة.


#### Implementation scope

- قراءة version.l_version والمطالبة بـ 3.1.1.5 (من config/finance.php).
- قائمة الجداول والعروض المستعملة في الإعدادات وطباعة أعداد صفوفها.


#### Required deliverables

- app/Console/Commands/CheckFinanceDatabase.php + اختبار.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases

- نسخة مختلفة: الأمر يفشل برمز خروج 1 ويطبع النسخة التي وجدها.


#### Acceptance criteria

- الأمر أخضر على القاعدة التجريبية.
- اختبار حالة النسخة الخاطئة بوصلة وهمية.


#### Mistakes to avoid

- كتابة أي شيء في القاعدة من الأمر.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 06 of the HC Financial Web plan (الأمر finance:check-db). Branch: `t1-5-check-db`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-10-26. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Create `app/Console/Commands/CheckFinanceDatabase.php`. It connects through `legacy`, reads `version.l_version` and fails if it is not `3.1.1.5` (value in `config/finance.php`), verifies that every legacy table and view the app uses exists (list in the config), and prints row counts. Exit code 1 on any failure.

## Done when
the command is green on the test database; a Pest test covers the failing version case with a mocked connection.

## Preconditions
These tasks must be merged first: 03. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t1-5-check-db`, commit with messages starting `T06:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 07 · جداول الإطار: الجلسات والتخزين المؤقت والطوابير

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-10-23
- **الجهد المقدّر:** ساعتان
- **الاعتماديات:** Task 03

#### Objective
نقل الجلسات والكاش والطوابير إلى قاعدة البيانات دون إنشاء جدول مستخدمين جديد (الدخول من جدول user القديم).


#### Implementation scope

- SESSION_DRIVER وCACHE_STORE وQUEUE_CONNECTION = database.
- ترحيلات الجداول الستة فقط.


#### Required deliverables

- 6 ترحيلات + مخرجات migrate --pretend في وصف الدمج.


#### Business rules

- نسخة mysqldump قبل التنفيذ الفعلي.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- الجداول الستة موجودة.
- الدخول والخروج يعملان.


#### Mistakes to avoid

- إنشاء users أو password_reset_tokens.
- تشغيل migrate:fresh.


#### Output and handoff to the next task
تعتمد عليها: Task 10.

#### Claude prompt

````text
## Role and goal
You are implementing Task 07 of the HC Financial Web plan (جداول الإطار: الجلسات والتخزين المؤقت والطوابير). Branch: `t1-6-framework-tables`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-10-23. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Set `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`. Create migrations for `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` only (no `users` or `password_reset_tokens`; Fortify uses the legacy `user` table). Run `migrate --pretend` first and attach the SQL to the PR. Take a `mysqldump` backup before the real run.

## Done when
the six tables exist; login and logout still work.

## Preconditions
These tasks must be merged first: 03. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t1-6-framework-tables`, commit with messages starting `T07:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 08 · تقوية الدخول على جدول user القديم

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-10-26
- **الجهد المقدّر:** نصف يوم
- **الاعتماديات:** Task 03

#### Objective
رفض المستخدمين المعطّلين مع الإبقاء على صيغة كلمة المرور التي يقرؤها برنامج ويندوز.


#### Implementation scope

- رفض State غير 1.
- الإبقاء على صيغة base64(salt16 + PBKDF2-SHA256 × 100000، 32 بايت).
- اختبارات: مستخدم معطّل، كلمة مرور خاطئة ومستخدم غير موجود بالرسالة نفسها، وبقاء تحديد المعدل.


#### Required deliverables

- تعديل AuthenticateWithFinanceAccount + اختبارات.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions

- الرسالة واحدة للمستخدم غير الموجود وكلمة المرور الخاطئة.
- الكتابة الوحيدة المسموحة في الجداول القديمة هي تغيير كلمة المرور عبر FinancePassword::make.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- الاختبارات تنجح.


#### Mistakes to avoid

- كتابة صيغة تشفير مختلفة.


#### Output and handoff to the next task
تعتمد عليها: Task 14.

#### Claude prompt

````text
## Role and goal
You are implementing Task 08 of the HC Financial Web plan (تقوية الدخول على جدول user القديم). Branch: `t1-7-harden-login`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-10-26. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
In `app/Actions/Fortify/AuthenticateWithFinanceAccount.php`, reject users whose `State` is not 1. Keep the password format (`base64(salt16 + PBKDF2-SHA256 100000, 32 bytes)`, see `app/Support/FinancePassword.php`). Never write a different format. Add tests: inactive user refused, wrong password and unknown user indistinguishable, throttling still works.

## Done when
tests pass.

## Preconditions
These tasks must be merged first: 03. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t1-7-harden-login`, commit with messages starting `T08:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 09 · نماذج القراءة للجداول القديمة

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-10-23–26 (القراءة الأساسية)، وإتمام الباقي 11-02
- **الجهد المقدّر:** يوم + نصف يوم
- **الاعتماديات:** Task 03

#### Objective
طبقة نماذج Eloquent تقرأ الجداول القديمة وتمنع الكتابة، يعتمد عليها الترحيل والبيانات الأساسية.


#### Implementation scope

- نماذج تحت app/Models/Legacy/ بالمفتاح الأساسي الحقيقي لكل جدول وعلاقات من المفاتيح الأجنبية.
- سمة (trait) مشتركة تجعل save وdelete وupdate ترمي استثناء.
- استثناء عمود الصورة ProfilePicture من الاستعلامات الافتراضية.
- أولوية أكتوبر: Account وCashBalance وTreeView وCity وProgram وProject وDonor أولًا؛ والباقي في 11-02.


#### Required deliverables

- النماذج والـ trait واختبار Pest لكل نموذج.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases

- الجداول بمفتاح ID أو id: يُفحص كل جدول.


#### Acceptance criteria

- لكل نموذج اختبار يحمّل سطرًا حقيقيًا وعلاقة.
- أي كتابة ترمي استثناء.


#### Mistakes to avoid

- ترك $timestamps مفعلًا.


#### Output and handoff to the next task
تعتمد عليها: Task 23، Task 44، Task 45، Task 31.

#### Claude prompt

````text
## Role and goal
You are implementing Task 09 of the HC Financial Web plan (نماذج القراءة للجداول القديمة). Branch: `t2-1-legacy-models`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-10-26. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Create read-only Eloquent models under `app/Models/Legacy/` with `$connection = 'legacy'`, `$timestamps = false`, the real primary key (`ID` or `id`, check each table) and relations taken from the foreign keys: `Action`, `ActionPayment`, `ActionAttachment`, `Payment`, `Beneficiary`, `Account`, `Till`, `TillType`, `CashAccount`, `Donor`, `Line`, `BudgetLine`, `Budget`, `Project`, `SubProject`, `City`, `Employee`, `EmployeeSalary`, `Rate`, `RoleCurrencyLimit`, and view models `CashBalance`, `TreeView`. A shared trait makes `save`, `delete` and `update` throw. Keep `App\Models\User` and `Role` on the default connection (login table) and exclude the `ProfilePicture` blob from default selects.

## Done when
a Pest test per model loads one real row and one relation; writing throws.

## Preconditions
These tasks must be merged first: 03. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t2-1-legacy-models`, commit with messages starting `T09:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 14 · الأدوار والسياسات (بدون Spatie) ودور المدير العام

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-10-23–26 (نسخة مصغّرة للشاشات الأربع)، والكاملة 11-02–03
- **الجهد المقدّر:** يوم + يوم
- **الاعتماديات:** Task 08

#### Objective
تحويل أدوار جدول role القديم إلى صلاحيات شاشات وإجراءات، مع دور ويب جديد للمدير العام.


#### Implementation scope

- النسخة المصغّرة (أكتوبر): RoleId ووسيط can:screen على شاشات الرئيسية والبرامج والمشاريع، ورؤية المشاريع لمدير المشروع بعلاقته.
- RoleId (2 الإدارة العامة، 3 مدير مشروع، 4 المدير المالي، 5 محاسب، 6 أمين صندوق، 7 موظف، 8 مانح) وGeneralManager محفوظ في جدول ويب خاص.
- config/permissions.php (دور ← شاشة ← إجراء) وسياسات Laravel.
- حدود الاعتماد من role_currency_limit مع بديل role.Approval_Limit.
- وسيط can:screen,<slug> على كل مسار.
- الكاملة (نوفمبر): باقي الشاشات ودور المدير العام وحدود الاعتماد.


#### Required deliverables

- التعداد والإعداد والسياسات والوسيط واختبارات الأدوار.


#### Business rules

- المحاسب يُعدّ والمدير المالي يعتمد والإدارة العامة تفعل الاثنين.
- المدير العام يوافق على الطلبات فوق 2000 دولار بعد المدير المالي.


#### Security and permissions

- الصلاحية في الخادم وليس بإخفاء الأزرار.
- جدول role القديم للقراءة فقط.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- كل دور يرى شاشاته فقط.
- المُعدّ لا يعتمد سنده.


#### Mistakes to avoid

- ربط الأسماء بأشخاص في الكود.


#### Output and handoff to the next task
تعتمد عليها: Task 23، Task 44، Task 45، Task 15، Task 16، Task 26، Task 38.

#### Claude prompt

````text
## Role and goal
You are implementing Task 14 of the HC Financial Web plan (الأدوار والسياسات (بدون Spatie) ودور المدير العام). Branch: `t2-6-roles-policies`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-03. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Create enum `RoleId` (2 Head Office, 3 Project Manager, 4 Financial Manager, 5 Accountant, 6 Cashier, 7 Employee, 8 Donor), `config/permissions.php` (role to screen to action: view, create, edit, delete, approve) and Laravel Policies/Gates. Accountant prepares, Financial Manager approves, Head Office both. Approval limits come from legacy `role_currency_limit` (fallback `role.Approval_Limit`). Add a Livewire/route middleware `can:screen,<slug>` applied to every route in `routes/web.php`.

## Done when
tests prove each role sees only its screens and the preparer cannot approve their own voucher.

## Preconditions
These tasks must be merged first: 08. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t2-6-roles-policies`, commit with messages starting `T14:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 23 · الصفحة الرئيسية والشجرة الجانبية (تقرأ من القاعدة القديمة)

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-10-22–28
- **الجهد المقدّر:** 4 أيام
- **الاعتماديات:** Task 09، Task 14

#### Objective
صفحة واحدة تعرض المشاريع الفعالة وأرصدتها بالليرة والدولار مع فلتر المحافظة، وشجرة جانبية قابلة لتغيير العرض. تُسلَّم قبل نهاية أكتوبر من القاعدة القديمة، ثم يُبدَّل مصدرها إلى الدفتر في المهمة 20.


#### Implementation scope

- /home وشعار الموقع: المشاريع الفعالة بأرصدتها بالليرة والدولار وفلتر المحافظة (المدينة).
- واجهة ProjectBalanceSource بتنفيذ أول من الجداول القديمة (أكتوبر) وثانٍ من الدفتر (نوفمبر، المهمة 20).
- شجرة جانبية بمقبض سحب ويُحفظ العرض لكل مستخدم.
- حذف projmap (تم) والتأكد أن الشعار يؤدي إلى الرئيسية.


#### Required deliverables

- صفحة Home + الشجرة + تبويبات المشروع + اختبارات.


#### Business rules

- الأرصدة المعروضة للسندات المعتمدة فقط.


#### Security and permissions

- الأرصدة تظهر حسب صلاحية المستخدم (مدير المشروع يرى مشاريعه).


#### Exceptional and edge cases

- مستخدم بلا مشاريع: رسالة فارغة واضحة وليس صفحة بيضاء.


#### Acceptance criteria

- فلتر المحافظة يغيّر الأرصدة.
- أرصدة المشروع تطابق برنامج ويندوز.
- عرض الشجرة يُحفظ بعد إعادة الدخول.
- لا رابط إلى projmap.


#### Mistakes to avoid

- إنشاء صفحة منفصلة لكل رقم.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 23 of the HC Financial Web plan (الصفحة الرئيسية والشجرة الجانبية (تقرأ من القاعدة القديمة)). Branch: `t4-5-home-and-sidebar`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-10-28. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Rebuild `/home` as the single landing page of the app (also the target of the logo): all active projects with their balances in SYP and USD and a governorate (city) filter. For the October milestone the balances come from the legacy read models (Account, CashBalance, TreeView, City, through the `legacy` connection) behind a `ProjectBalanceSource` interface; in task 20 a second implementation reads the ledger and the binding is switched without changing the page. Add the side tree with a draggable resize handle; remember the width per user (`web_user_preferences` table, or a cookie if the table is not migrated yet). Remove the `projmap` slug from `config/navigation.php` and both `lang/*/navigation.php` files if it is still there. The logo links to Home. Keep RTL and the HC theme. Pest feature tests: city filter, preference persistence, access by role.

## Preconditions
These tasks must be merged first: 09, 14. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t4-5-home-and-sidebar`, commit with messages starting `T23:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 44 · صفحة البرامج مع زر «إضافة برنامج»

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-10-27–29
- **الجهد المقدّر:** 2.5 يوم
- **الاعتماديات:** Task 09، Task 14

#### Objective
صفحة البرامج مع زر الإضافة المطلوب في ملاحظات الـ mockup. الجداول القديمة للقراءة فقط، فيُحفظ البرنامج المُضاف في جدول ويب خاص gl_programs إلى أن يُحسم موقع تحرير البرامج (البند O-7).


#### Implementation scope

- قائمة البرامج من القاعدة القديمة مع عدد المشاريع الفعالة وأرصدتها (دولار وليرة).
- زر «إضافة برنامج» ونموذج (الاسم بالعربية والإنجليزية، الوصف، الحالة).
- جدول gl_programs وشارة تميّز البرنامج المُضاف من الويب.
- ربط مشروع ببرنامج قديم أو مضاف.


#### Required deliverables

- الصفحة + الترحيل + السياسة + اختبارات.


#### Business rules

- لا كتابة في جدول البرامج القديم أبدًا.


#### Security and permissions

- زر الإضافة لصلاحية programs.create فقط (الإدارة العامة والمدير المالي)، والتحقق في الخادم.


#### Exceptional and edge cases

- اسم برنامج مكرر بين القديم والمضاف: تحذير بلا منع.


#### Acceptance criteria

- البرنامج المضاف يظهر في القائمة وفي نموذج المشروع.
- الجدول القديم لم يتغير.


#### Mistakes to avoid

- إنشاء البرنامج في جدول ويندوز.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 44 of the HC Financial Web plan (صفحة البرامج مع زر «إضافة برنامج»). Branch: `t0-44-programs-page`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-10-29. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Build the `programs` page. List the programs from the legacy read model (read only, connection `legacy`) with the number of active projects per program and their balances (USD and SYP) taken from the same service as the Home page. Add an "Add program" button (permission `programs.create`, Head Office and Financial Manager) that opens a form (name EN/AR, description, status) and saves into the web-owned table `gl_programs` (new migration; legacy tables are never written). Show web-created programs beside legacy ones with a badge, and let a project be linked to either. Livewire + Flux, RTL, `en` and `ar` labels, policy per role. Pest tests: list, create, validation, permission denied (403), legacy table untouched.

## Preconditions
These tasks must be merged first: 09, 14. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t0-44-programs-page`, commit with messages starting `T44:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 45 · صفحات المشاريع: القائمة وصفحة المشروع بتبويبات

- **المطوّر والتواريخ:** المطوّر A + المطوّر B · التواريخ: 2026-10-27–30
- **الجهد المقدّر:** 3 أيام
- **الاعتماديات:** Task 09، Task 14

#### Objective
قائمة المشاريع وصفحة مشروع واحدة بتبويبات بدل شاشات متفرقة (projwin وallaccounts وlifetime)، تقرأ من القاعدة القديمة الآن وتنتقل إلى الدفتر في المهمة 20 دون تغيير الواجهة.


#### Implementation scope

- قائمة المشاريع بفلاتر المحافظة والبرنامج والمانح والحالة والبحث.
- صفحة المشروع بتبويبات: نظرة عامة (الأرصدة بكل عملة وحسب صندوق الكاش)، الحسابات، بنود الميزانية، عمر المشروع.
- خدمة الأرصدة خلف واجهة (interface) ليسهل تبديل مصدرها من القديم إلى الدفتر.


#### Required deliverables

- الشاشتان + خدمة الأرصدة + اختبارات.


#### Business rules

- الأرقام في أكتوبر من الجداول القديمة (تطابق برنامج ويندوز تمامًا) وتنتقل للدفتر في نوفمبر.


#### Security and permissions

- إخفاء المبالغ عن الأدوار بلا صلاحية مالية وإبقاء الأسماء.
- مدير المشروع يرى مشاريعه فقط بفحص العلاقة.


#### Exceptional and edge cases

- مشروع بلا حركات: أرصدة صفر بدل خطأ.
- مشروع بحسابات كاش متعددة: عرض كل صندوق على حدة.


#### Acceptance criteria

- أرصدة المشروع تطابق تقرير ويندوز للمشروع نفسه.
- التبويبات الأربعة تعمل.


#### Mistakes to avoid

- كتابة استعلامات الأرصدة داخل مكوّنات الواجهة.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 45 of the HC Financial Web plan (صفحات المشاريع: القائمة وصفحة المشروع بتبويبات). Branch: `t0-45-projects-pages`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-10-30. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Build the projects pages on the legacy read models. (1) Projects list: all projects with city, program, donor, status and balances in USD and SYP (same balance service as Home), filters (city/governorate, program, donor, status, search), server-side pagination. (2) One project page with tabs, replacing the separate screens `projwin`, `allaccounts` and `lifetime`: Overview (balances by currency and by cash account, "where the project's money is"), Accounts, Budget lines, Lifetime (income and expenses over time). Money columns are hidden from roles without finance permission (keep names, hide money). Everything reads through the `legacy` connection; scope project managers (role 3) to their own projects by relationship, not by role only. Livewire + Flux, RTL, `en` and `ar` labels. Pest tests: filters, tab content, 403 on a foreign project, money hidden for a non-finance role. Use the same balance service later for the ledger (task 20), so keep it behind an interface.

## Preconditions
These tasks must be merged first: 09, 14. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t0-45-projects-pages`, commit with messages starting `T45:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

## المرحلة 1: مخطط الدفتر وخدمة الترحيل · A · 2–10 نوفمبر


### 10 · ترحيلات الدفتر: الحسابات والمشاريع وبنود الميزانية

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-11-02
- **الجهد المقدّر:** نصف يوم
- **الاعتماديات:** Task 07

#### Objective
جداول gl_accounts وgl_projects وgl_budget_lines بحسب القسم 3.2، بما فيها حقول العقد وتواريخ المشروع ونسخ الميزانية.


#### Implementation scope

- gl_accounts.code مفتاح نصي أساسي ومفتاح أجنبي ذاتي parent_code.
- gl_projects: contract_no وstart_date وend_date وcurrency.
- gl_budget_lines بنسخ: version وvalid_from وis_current.
- النماذج والتعدادات AccountClass وAccountLevel وNormalSide.


#### Required deliverables

- 3 ترحيلات + نماذج + تعدادات.


#### Business rules

- تنفيذ الترحيلات على مخطط تجريبي منفصل وليس على القاعدة التجريبية.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- الترحيلات تُنفَّذ وتُعكس على المخطط التجريبي.


#### Mistakes to avoid

- تنفيذ migrate على hcsyria_financial_test قبل مراجعة --pretend.


#### Output and handoff to the next task
تعتمد عليها: Task 11، Task 12.

#### Claude prompt

````text
## Role and goal
You are implementing Task 10 of the HC Financial Web plan (ترحيلات الدفتر: الحسابات والمشاريع وبنود الميزانية). Branch: `t2-2-ledger-accounts`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-02. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Create migrations `gl_accounts`, `gl_projects`, `gl_budget_lines` exactly as in section 3.2 of the plan (columns, types, self FK on `parent_code`, indexes, soft deletes). `gl_accounts.code` is a string primary key. Add Eloquent models `GlAccount`, `GlProject`, `GlBudgetLine` with relations and the extra project columns (`currency`, `contract_no`, `start_date`, `end_date`) and budget line versioning (`version`, `valid_from`, `is_current`) and enums `AccountClass`, `AccountLevel`, `NormalSide`.

## Done when
migrations run on a scratch schema (not the test database) and roll back.

## Preconditions
These tasks must be merged first: 07. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t2-2-ledger-accounts`, commit with messages starting `T10:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 11 · ترحيلات الدفتر: السندات وما حولها

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-11-02–03
- **الجهد المقدّر:** يوم
- **الاعتماديات:** Task 10

#### Objective
جداول السندات وأسطرها والتدقيق والترقيم والمرفقات والإقفال والطلبات وتقسيم الرواتب والقروض الصغيرة والمطابقة مع القديم.


#### Implementation scope

- gl_vouchers بالحقلين transfer_kind وrequest_id.
- gl_voucher_lines بحقول اليورو والعمولة والتعويض، وقيد فحص: مدين أو دائن لا الاثنين ولا سالبًا.
- فهارس فريدة على legacy_action_id وlegacy_attachment_id.
- التعدادات VoucherType وVoucherStatus وFundSource.


#### Required deliverables

- ترحيلات 15 جدولًا + نماذج + تعدادات.


#### Business rules

- السند المعتمد لا يُحذف أبدًا، بل يُعكس أو يُستبدل (SoftDeletes للمعلّق والمرفوض فقط).


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases

- سطر بمدين ودائن معًا: يرفضه قيد القاعدة نفسه وليس الكود فقط.


#### Acceptance criteria

- الترحيلات تُنفَّذ وتُعكس على المخطط التجريبي.
- قيد الفحص يرفض سطرًا بمدين ودائن.


#### Mistakes to avoid

- الاعتماد على الكود وحده لمنع السطر المزدوج.


#### Output and handoff to the next task
تعتمد عليها: Task 13، Task 27، Task 28، Task 29.

#### Claude prompt

````text
## Role and goal
You are implementing Task 11 of the HC Financial Web plan (ترحيلات الدفتر: السندات وما حولها). Branch: `t2-3-ledger-vouchers`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-03. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Create `gl_vouchers`, `gl_voucher_lines`, `gl_voucher_audit`, `gl_voucher_sequences`, `gl_voucher_attachments`, `gl_month_closes`, `gl_allocation_rates`, `gl_legacy_map`, `gl_import_batches`, `gl_import_issues`, `gl_cash_till_merges`, `gl_requests`, `gl_payroll_splits`, `gl_micro_loans`, `gl_micro_instalments` per the plan (section 3.2 and 6.8), including the new voucher columns `transfer_kind`, `request_id` and the voucher line columns `eur_amount`, `eur_rate`, `commission_usd`, `is_uncompensated`. Add a database check constraint on `gl_voucher_lines`: debit or credit, never both, never negative, both columns `decimal(18,2)`. Unique index on `gl_vouchers.legacy_action_id` (nullable) and on `gl_voucher_attachments.legacy_attachment_id`. Models and enums `VoucherType` (CRV, CPV, TRF, ALC, JV, OPN), `VoucherStatus` (PendingApproval, Approved, Replaced), `FundSource`.

## Done when
migrations run and roll back on the scratch schema; the check constraint rejects a line with both Dr and Cr.

## Preconditions
These tasks must be merged first: 10. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t2-3-ledger-vouchers`, commit with messages starting `T11:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 12 · بذور دليل الحسابات والمشاريع وبنود الميزانية

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-11-03–04
- **الجهد المقدّر:** يوم
- **الاعتماديات:** Task 10

#### Objective
تعبئة الدليل بالـ156 حسابًا والمشاريع وبنود الميزانية من الـ mockup، مع تغييرات الإصدار 7 والحساب الجديد 5504.


#### Implementation scope

- قراءة كائن البيانات D (acc وproj وbl) من الـ mockup DbZn7RTmZyZBh2w6MqbWxM.
- تقسيم كل حساب عام للمدينة إلى USD وSYP (10 صفوف عامة).
- إضافة 4108 و4306-4308 و1125-1127 و1135 و1145-1147 و1155 وإعادة تسمية 1115 و1171 و1172، وإضافة 5504 عمولة شركة الحوالات.
- حفظ البيانات في database/seeders/data/*.json.


#### Required deliverables

- Seeder + ملفات JSON + اختبار أعداد.


#### Business rules

- الأسماء العربية UTF-8. مشروعان في تصدير الـ mockup ظهرت أسماؤهما بحروف تالفة: يُستعمل General account - <city> ويُعلَّم في وصف الدمج.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases

- تحقق أن الرقم 5504 شاغر في الـ mockup قبل اعتماده.


#### Acceptance criteria

- كل parent_code يحلّ إلى حساب.
- لكل حساب ترحيل صنف وجانب وبند قوائم.


#### Mistakes to avoid

- كتابة البيانات داخل الـ seeder مباشرة بدل JSON.


#### Output and handoff to the next task
تعتمد عليها: Task 13، Task 15، Task 30، Task 31.

#### Claude prompt

````text
## Role and goal
You are implementing Task 12 of the HC Financial Web plan (بذور دليل الحسابات والمشاريع وبنود الميزانية). Branch: `t2-4-seed-chart`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-04. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Open the mockup artifact `DbZn7RTmZyZBh2w6MqbWxM` (data object `D`: `acc`, `proj`, `bl`) and write a seeder with those 156 accounts (34 headers, 122 posting), the 8 projects (F2F, SZ, CORE and the ten general accounts GEN-<city>-USD and GEN-<city>-SYP for ALP, LTK, DAM, HMS, TRT; the mockup has one general account per city, so split each into a USD and an SYP row, decision Q1) and the budget lines. Apply the v7 changes: add 4108, 4306-4308, 1125-1127, 1135, 1145-1147, 1155; rename 1115, 1171, 1172. Keep the Arabic names as UTF-8 (the artifact export shows replacement characters in two project names: use `General account - <city>` and flag it in the PR). Store the data in `database/seeders/data/*.json`, not inline.

## Done when
counts are 156 + 8 accounts' worth of v7 rows as in the mockup, every `parent_code` resolves, every posting account has a class, side and statement line.

## Preconditions
These tasks must be merged first: 10. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t2-4-seed-chart`, commit with messages starting `T12:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 13 · خدمة الترحيل PostVoucher وقواعدها الـ13

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-11-04–10
- **الجهد المقدّر:** 4 أيام (بمسارين Claude متوازيين)
- **الاعتماديات:** Task 11، Task 12

#### Objective
نقطة الدخول الوحيدة لكتابة السندات. كل شاشة وكل ترحيل يمر منها، فتُطبَّق القواعد في مكان واحد.


#### Implementation scope

- معاملة قاعدة بيانات واحدة: تحقق ثم ترقيم ثم حفظ بحالة «بانتظار الموافقة» ثم سطر التدقيق.
- صنف لكل قاعدة من الـ13، ولكل منها اختبار ناجح وآخر فاشل.
- ترقيم CRV-2026-0001 من gl_voucher_sequences بقفل على السطر.


#### Required deliverables

- app/Services/Ledger/PostVoucher.php + أصناف القواعد + مجموعة اختبارات.


#### Business rules

- التوازن بالدولار بالضبط، والسنتات إلى 5503.
- لا فحص لحدّ بند الميزانية (تحذير فقط حسب قرار المدير المالي).
- سند بلا طلب موافق عليه ممنوع فوق 500 دولار للسند الواحد (سند الليرة بسعره).


#### Security and permissions

- المعتمِد ليس المُعدّ، ويُفحص في الخادم.
- الشهر المقفل يمنع أي تعديل.


#### Exceptional and edge cases

- فشل أي قاعدة: لا أسطر ولا رقم مستهلك.


#### Acceptance criteria

- مجموعة القواعد خضراء.
- السند الفاشل لا يترك صفوفًا ولا يستهلك رقمًا.


#### Mistakes to avoid

- تكرار القواعد داخل الشاشات.
- استعمال float في أي حساب.


#### Output and handoff to the next task
تعتمد عليها: Task 16، Task 18، Task 26، Task 30، Task 32.

#### Claude prompt

````text
## Role and goal
You are implementing Task 13 of the HC Financial Web plan (خدمة الترحيل PostVoucher وقواعدها الـ13). Branch: `t2-5-post-voucher`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-10. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Create `app/Services/Ledger/PostVoucher.php`. Input: voucher header and lines. In one DB transaction it validates, numbers (`CRV-2026-0001`, from `gl_voucher_sequences` with a row lock, never reused), saves with status `PendingApproval`, and writes an audit row. Implement and unit-test each rule separately (one class per rule, one passing and one failing test each): only active posting accounts; cash account only in its own currency; debit or credit never both and never negative; balanced exactly in USD (cents go to 5503); required tags filled; budget line belongs to the line's project and fills its account; approver is never the preparer; nothing changes in a closed month; changes to an approved voucher keep old value, new value, user and reason; numbers never reused; a used account keeps code, class and currency; month closes only when 11x3, 11x4 and 2901 are zero; migrated vouchers keep their legacy id.

## Done when
the rules suite is green; a failed rule leaves no rows and no consumed number.

## Preconditions
These tasks must be merged first: 11, 12. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t2-5-post-voucher`, commit with messages starting `T13:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

## المرحلة 2: دليل الحسابات والسندات والاعتماد · B · 2–12 نوفمبر


### 15 · شاشة دليل الحسابات (chart)

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-02–05
- **الجهد المقدّر:** 3 أيام
- **الاعتماديات:** Task 12، Task 14

#### Objective
عرض الشجرة وإدارتها كما في الـ mockup، مع حماية الحسابات المستعملة.


#### Implementation scope

- شجرة بالصنف مع طي وفتح وفلاتر (الصنف، الترحيل فقط، بحث بالكود والاسمين).
- الرصيد (للسندات المعتمدة) والعملة والمدينة والوسوم و«طريقة الاستعمال».
- درج تحرير وإضافة حساب فرعي.


#### Required deliverables

- مكوّنات Livewire + Flux، RTL، واختبارات الميزات.


#### Business rules

- الحذف فقط لحساب لم يُستعمل أبدًا؛ المستعمل يُعطَّل ويحتفظ بكوده وصنفه وعملته.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases

- الحساب الأب بأبناء مستعملين لا يُحذف.


#### Acceptance criteria

- اختبارات الإنشاء والتعديل والتعطيل وقيد الحساب المستعمل.
- فحص يدوي بالعربية والإنجليزية.


#### Mistakes to avoid

- السماح بتغيير الصنف أو العملة لحساب مستعمل.


#### Output and handoff to the next task
تعتمد عليها: Task 39.

#### Claude prompt

````text
## Role and goal
You are implementing Task 15 of the HC Financial Web plan (شاشة دليل الحسابات (chart)). Branch: `t3-1-chart-screen`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-05. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Build the `chart` screen as in the mockup: tree of accounts grouped by class with expand/collapse, filters (class, posting only, search by code/name EN/AR), per-account balance (approved vouchers), currency, city, required tags and "how to use". Editor drawer to add/edit: code, level, EN/AR name, parent, class, normal side, currency, city, cash flag, statement line, tags, usage note. Add child account on headers. Delete only if never used; a used account may only be deactivated and keeps code, class and currency. Livewire + Flux, RTL-aware, responsive. Replace the placeholder route.

## Done when
Pest feature tests for create, edit, deactivate and the "used account" restriction; manual check in English and Arabic.

## Preconditions
These tasks must be merged first: 12, 14. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t3-1-chart-screen`, commit with messages starting `T15:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 16 · قائمة السندات والاعتماد (vouchers)

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-04–06
- **الجهد المقدّر:** 2.5 يوم
- **الاعتماديات:** Task 13، Task 14

#### Objective
متابعة السندات واعتمادها أو رفضها من المدير المالي.


#### Implementation scope

- شرائح الأنواع (CRV وCPV وTRF وALC وJV وOPN) والحالات والتاريخ والبحث وشارة «بانتظار الموافقة».
- تفاصيل السند بأسطره ومرفقاته ومسار تدقيقه.
- اعتماد ورفض: يعيد الاعتماد فحص القواعد وحد الدور.


#### Required deliverables

- شاشة + إجراءات + اختبارات.


#### Business rules

- المرفوض يحتفظ برقمه ويُحذف حذفًا ناعمًا مع السبب.


#### Security and permissions

- المُعدّ لا يعتمد سنده.
- سند من شهر مقفل لا يُعتمد.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- اختبارات الاعتماد والرفض ومنع الاعتماد الذاتي والشهر المقفل.


#### Mistakes to avoid

- تغيير الحالة بكتابة مباشرة بدل الإجراء.


#### Output and handoff to the next task
تعتمد عليها: Task 17.

#### Claude prompt

````text
## Role and goal
You are implementing Task 16 of the HC Financial Web plan (قائمة السندات والاعتماد (vouchers)). Branch: `t3-2-voucher-list`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-06. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Build the `vouchers` list: type chips, status, date range, search, Pending badge in the navigation. Detail view with lines, attachments and audit trail. Approve and reject actions for the Financial Manager (not the preparer); approval re-checks the posting rules and the role limit. Rejected vouchers keep their number and are soft deleted with a reason.

## Done when
feature tests for approve, reject, self-approval refused, closed month refused.

## Preconditions
These tasks must be merged first: 13, 14. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t3-2-voucher-list`, commit with messages starting `T16:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 17 · منشئ السند

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-06–12
- **الجهد المقدّر:** 4 أيام
- **الاعتماديات:** Task 16

#### Objective
واجهة الإدخال الأساسية لكل أنواع السندات مع قائمة فحص حية تطابق قواعد الخادم.


#### Implementation scope

- ترويسة (النوع والتاريخ والوصف) وشبكة أسطر (الحساب والعملة ومدين/دائن والمبلغ والسعر والدولار والمشروع وبند الميزانية ومصدر التمويل وجهة الاتصال والمركز ونوع الليرة).
- اختيار بند الميزانية يملأ حسابه؛ اختيار حساب الكاش يثبّت العملة؛ دفع الليرة يأخذ متوسط سعر المشروع.
- تحذير ناعم عند الصرف بعد تاريخ نهاية المشروع أو فوق المتبقي من بند الميزانية.
- رفع المرفقات إلى القرص الخاص.


#### Required deliverables

- شاشة المنشئ + اختبارات + رفع المرفقات.


#### Business rules

- الحفظ دائمًا عبر PostVoucher.


#### Security and permissions

- فحص نوع الملف وحجمه (SecureUpload) قبل التخزين الخاص، وليس تحت public.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- CRV وCPV وTRF تُعدّ وتظهر معلّقة.
- قائمة الفحص تطابق القواعد في الخادم.


#### Mistakes to avoid

- تكرار منطق القواعد في الواجهة بدل استدعاء الخادم.


#### Output and handoff to the next task
تعتمد عليها: Task 24، Task 26، Task 27، Task 29، Task 37.

#### Claude prompt

````text
## Role and goal
You are implementing Task 17 of the HC Financial Web plan (منشئ السند). Branch: `t3-3-voucher-builder`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-12. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Build the modal/page to create a voucher: header (type, date, description) and a lines grid (account, currency, Dr/Cr, original amount, rate, USD, project, budget line, fund source, contact, center, lira type old/new). Choosing a budget line fills its account; choosing a cash account fixes the currency; SYP payments take the project average rate; show the live check list (balanced, tags, currency, open month). Save through `PostVoucher`. Attachment upload to the private disk (`gl_voucher_attachments`).

## Done when
a CRV, CPV and TRF can be prepared and appear as Pending; the check list matches the server-side rules.

## Preconditions
These tasks must be merged first: 16. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t3-3-voucher-builder`, commit with messages starting `T17:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 18 · كشف الحساب (statement)

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-10–11
- **الجهد المقدّر:** يوم ونصف
- **الاعتماديات:** Task 13

#### Objective
حركة حساب واحد مع الرصيد الجاري والانتقال إلى السند.


#### Implementation scope

- حساب واحد وفلتر مشروع ومدى تاريخ ورصيد افتتاحي ورصيد جارٍ وتصدير CSV.


#### Required deliverables

- الشاشة + التصدير + اختبار التطابق.


#### Business rules

- السندات المعتمدة فقط.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- الرصيد الجاري يساوي ميزان المراجعة للحساب والتاريخ نفسهما.


#### Mistakes to avoid

- إظهار سندات معلّقة.


#### Output and handoff to the next task
تعتمد عليها: Task 19.

#### Claude prompt

````text
## Role and goal
You are implementing Task 18 of the HC Financial Web plan (كشف الحساب (statement)). Branch: `t3-4-account-statement`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-11. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
One account, project filter, date range, opening balance, running Dr/Cr balance, link to each voucher, CSV export. Approved vouchers only.

## Done when
the running balance equals the trial balance for the same account and date (test).

## Preconditions
These tasks must be merged first: 13. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t3-4-account-statement`, commit with messages starting `T18:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

## المرحلة 3: التقارير · B · 10–19 نوفمبر


### 19 · ميزان المراجعة (tb) ومفتاح العملة

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-12–13
- **الجهد المقدّر:** يوم ونصف
- **الاعتماديات:** Task 18

#### Objective
ميزان لكل حساب بتاريخ محدد، مع عرض بالعملتين بسعر يُدخل يدويًا.


#### Implementation scope

- مدين ودائن وصافٍ حسب الصنف، وفحص المجموعين، وتصدير.
- حقل سعر الليرة يُدخل لكل تقرير: الليرة تبقى ليرة والدولار فقط يتحول.


#### Required deliverables

- الشاشة + مكوّن مفتاح العملة المشترك للتقارير.


#### Business rules

- السندات المعتمدة فقط.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- مجموع المدين = مجموع الدائن.


#### Mistakes to avoid

- جدول أسعار للتقارير (قرار المدير المالي: سعر يدوي لكل تقرير).


#### Output and handoff to the next task
تعتمد عليها: Task 20، Task 21، Task 22.

#### Claude prompt

````text
## Role and goal
You are implementing Task 19 of the HC Financial Web plan (ميزان المراجعة (tb) ومفتاح العملة). Branch: `t4-1-trial-balance`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-13. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
As-at date, per account Dr / Cr / net, grouped by class, total Dr = total Cr check, approved vouchers only, export.

## Preconditions
These tasks must be merged first: 18. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t4-1-trial-balance`, commit with messages starting `T19:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 20 · أرصدة المشاريع (projects)

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-13–17
- **الجهد المقدّر:** 2.5 يوم
- **الاعتماديات:** Task 19

#### Objective
رصيد كل مشروع بكل عملاته وموزعًا على حسابات الكاش، ويغذّي الصفحة الرئيسية.


#### Implementation scope

- الإيراد والمصروف (بدون الإهلاك 5703، القرار D10) والكاش ورصيد المقيد.
- الميزانية مقابل الفعلي لكل بند، وعرض بعملة المانح.
- تقرير «أين أموال المشروع»: الرصيد موزعًا على صناديق الكاش.
- تبديل مصدر الرئيسية وصفحات المشاريع من القاعدة القديمة إلى الدفتر بتنفيذ ثانٍ لـ ProjectBalanceSource دون تغيير الواجهة، ومقارنة الأرقام بين المصدرين.


#### Required deliverables

- خدمة الأرصدة + التقرير + اختبارات التطابق.


#### Business rules

- رصيد المشروع لكل عملة ولكل حساب كاش، لا حساب بعملة واحدة.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- الأرصدة تطابق أسطر السندات.


#### Mistakes to avoid

- خلط عملتين في رقم واحد دون تحويل صريح.


#### Output and handoff to the next task
تعتمد عليها: Task 28.

#### Claude prompt

````text
## Role and goal
You are implementing Task 20 of the HC Financial Web plan (أرصدة المشاريع (projects)). Branch: `t4-2-project-balances`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-17. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Per project: income, expenses (exclude 5703 depreciation, decision D10), cash held, restricted fund balance, budget versus actual per budget line, donor-currency (EUR) view using the project rate.

## Preconditions
These tasks must be merged first: 19. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t4-2-project-balances`, commit with messages starting `T20:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 21 · القوائم المالية (statements)

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-17–18
- **الجهد المقدّر:** 2 يوم
- **الاعتماديات:** Task 19

#### Objective
قائمة المركز المالي وقائمة الأنشطة من بنود القوائم في الدليل.


#### Implementation scope

- تجميع حسب statement_line، و5503 تحت المصاريف المالية (D11)، وفترة مقارنة، وتصدير PDF وXLSX بتخطيط RTL.
- استبعاد الحسابات الشخصية (خارج القوائم).


#### Required deliverables

- الشاشة + التصدير.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- الأصول = الالتزامات + صافي الأصول في بيانات الاختبار.


#### Mistakes to avoid

- تضمين الحسابات الشخصية في القوائم.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 21 of the HC Financial Web plan (القوائم المالية (statements)). Branch: `t4-3-financial-statements`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-18. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Statement of Financial Position and Statement of Activities grouped by `statement_line`; 5503 under financial expenses (D11); comparative period; PDF/XLSX export with RTL layout.

## Preconditions
These tasks must be merged first: 19. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t4-3-financial-statements`, commit with messages starting `T21:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 22 · التقارير التشغيلية (rexp وrcash وrbl وrrev وrfixed وrattach)

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-17–19
- **الجهد المقدّر:** 2 يوم
- **الاعتماديات:** Task 19

#### Objective
إعادة بناء تقارير البرنامج القديم على gl_voucher_lines بالفلاتر نفسها لتقارن المجاميع.


#### Implementation scope

- الفلاتر: مشروع ومشروع فرعي ومانح وحساب وعملة ومدى تاريخ.
- rattach يقرأ gl_voucher_attachments فقط.


#### Required deliverables

- 6 شاشات + اختبار مقارنة مع ويندوز.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- مجاميع كل تقرير تطابق برنامج ويندوز لنفس الفلتر.


#### Mistakes to avoid

- القراءة من جدول action القديم.


#### Output and handoff to the next task
تعتمد عليها: Task 41.

#### Claude prompt

````text
## Role and goal
You are implementing Task 22 of the HC Financial Web plan (التقارير التشغيلية (rexp وrcash وrbl وrrev وrfixed وrattach)). Branch: `t4-4-operational-reports`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-19. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Rebuild on `gl_voucher_lines` with the filters of the Windows program (project, sub-project, donor, account, currency, date range). `rattach` reads `gl_voucher_attachments`.

## Preconditions
These tasks must be merged first: 19. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t4-4-operational-reports`, commit with messages starting `T22:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

## المرحلة 4: الخزينة والطلبات وقرارات المدير المالي · B (والمهمة 30 للمطوّر A) · 12–26 نوفمبر


### 24 · وضع الخزينة (treasury)

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-16–17
- **الجهد المقدّر:** 2 يوم
- **الاعتماديات:** Task 17

#### Objective
رؤية الخزينة لكل مدينة بحسابيها العامين (دولار وليرة).


#### Implementation scope

- الليرات المحتفظ بها ومتوسط التكلفة، المشتريات بالجملة، الكاش لكل صندوق وعملة، الطريق (11x3 و11x4)، شركة التحويل 1161، احتياطي 5503، النقد غير المعوَّض.


#### Required deliverables

- الشاشة + خدمة الخزينة + اختبارات.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- الأرقام تطابق ميزان المراجعة للحسابات نفسها.


#### Mistakes to avoid

- دمج الحسابين العامين في رقم واحد.


#### Output and handoff to the next task
تعتمد عليها: Task 25.

#### Claude prompt

````text
## Role and goal
You are implementing Task 24 of the HC Financial Web plan (وضع الخزينة (treasury)). Branch: `t5-1-treasury-position`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-17. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Per city: the two general accounts (GEN-<city>-USD and GEN-<city>-SYP) with SYP held and average cost, bulk purchases (TRF into the SYP safe), cash by safe and currency, in-transit (11x3, 11x4), transfer company 1161, 5503 reserve.

## Preconditions
These tasks must be merged first: 17. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t5-1-treasury-position`, commit with messages starting `T24:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 25 · أسعار التحويل وسندات TRF (alloc)

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-18–20
- **الجهد المقدّر:** 2.5 يوم
- **الاعتماديات:** Task 24

#### Objective
بناء سند التحويل من الحساب العام إلى المشروع بالأنواع الأربعة المعتمدة.


#### Implementation scope

- سعر لكل مدينة بتاريخ سريان: يقترحه المحاسب ويعتمده المدير المالي.
- الأنواع: عام (دولار) ← مشروع، عام (ليرة) ← مشروع مع خصم المقابل بالدولار، مشروع ← عام (إرجاع)، بين مشروعين.
- فرق السعر إلى 5503، وخيار «متوسط التكلفة» مُطفأ افتراضيًا (D9).


#### Required deliverables

- شاشة الأسعار + منشئ التحويل + اختبارات الأخطاء الثلاثة (لا ليرات في العام، ليرات غير كافية، لا سعر معتمد).


#### Business rules

- التحويل بين الحسابات لا يحرّك الكاش الفعلي.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- سند TRF يُرحَّل بفرق 5503 الصحيح.


#### Mistakes to avoid

- استعمال ALC للتحويل (ALC لتوزيع راتب أو فاتورة فقط).


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 25 of the HC Financial Web plan (أسعار التحويل وسندات TRF (alloc)). Branch: `t5-2-transfer-rates`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-20. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Rates per city with effective date: proposed by Accountant, approved by Financial Manager. Build the **transfer** voucher (TRF with `transfer_kind`; ALC now means splitting a salary or invoice over projects, see plan 6.8): Dr project SYP, Cr general account SYP at its average rate, USD legs on 1161, difference to 5503. Errors exactly as in the mockup (no pounds in the general account, not enough pounds, no approved rate). Optional "allocate at average cost" switch, default off (decision D9).

## Preconditions
These tasks must be merged first: 24. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t5-2-transfer-rates`, commit with messages starting `T25:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 26 · وحدة الطلبات (requests): طلب مدير المشروع الشهري

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-18–24
- **الجهد المقدّر:** 4 أيام
- **الاعتماديات:** Task 13، Task 14، Task 17

#### Objective
نقل طلب الصرف من الورق إلى النظام: حالاته وموافقاته وربطه بالسند، بحسب قرارات الجولتين الثانية والثالثة.


#### Implementation scope

- طلب شهري واحد لكل مشروع بأسطر (بند ميزانية، مبلغ)، والمبلغ الإضافي طلب مستقل.
- الحالات: مسودة، مقدَّم، موافَق، مرفوض، مغلق؛ ويعدّل مدير المشروع حتى الموافقة ثم يُقفل.
- موافقة المدير المالي، وفوق 2000 دولار موافقة المدير العام بعده.
- عروض الأسعار (2 أو 3) للصيانات الكبيرة والأصول الثابتة والإلكترونيات.
- ربط السند بالطلب عبر request_id.


#### Required deliverables

- جداول الطلبات والأسطر والموافقات، شاشة requests، سياسات، اختبارات.


#### Business rules

- تجاوز بند الميزانية تحذير وليس منعًا.
- الصرف بلا طلب موافق عليه مسموح تحت 500 دولار للسند.
- سند الليرة يُقارن بسقف الدولار بسعره هو.


#### Security and permissions

- مدير المشروع يرى طلبات مشاريعه فقط (فحص العلاقة وليس الدور وحده).
- كل تعديل بعد التقديم يُسجَّل في التدقيق.
- الملفات خارج public وبفحص نوع وحجم.


#### Exceptional and edge cases

- طلب مغلق لا يُفتح؛ يُقدَّم طلب جديد.
- غياب المدير العام: الطلب يبقى «بانتظار المدير العام» ولا يُعتمد تلقائيًا.
- إلغاء دور المدير العام لمستخدم أثناء طلب معلّق: يعود الطلب إلى طابور الدور.


#### Acceptance criteria

- الموافقتان بالترتيب مفروضتان في الخادم.
- القفل بعد الموافقة مفروض.
- اختبار 403 لكل إجراء.


#### Mistakes to avoid

- اعتماد الطلب بزر مخفي فقط.
- ربط الموافقة بأسماء أشخاص.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 26 of the HC Financial Web plan (وحدة الطلبات (requests): طلب مدير المشروع الشهري). Branch: `t5-3-requests-module`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-24. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Build the `requests` module from plan section 6.9. A project manager (role 3) prepares ONE monthly request per project: a list of lines (budget line, amount) for the routine monthly expenses; an extra amount during the month is a separate request. States: draft, submitted, approved, rejected, closed. The manager edits until approval, then it locks (every edit is audited). The Financial Manager (role 4) approves or rejects; above USD 2,000 (compare in USD, SYP at the line rate) the General Manager role also approves AFTER the Financial Manager (two approvals in order). Large maintenance, fixed assets and electronics requests need 2 or 3 price quotes attached before submit (SecureUpload, private disk). Tables `gl_requests` and `gl_request_lines` (plus approvals table). `gl_vouchers.request_id` links a transfer or payment voucher to an approved request. Exceeding a budget line is a warning only. A voucher without an approved request is allowed only under USD 500 per voucher (enforced in PostVoucher, task 13). Policies per role, Livewire + Flux, RTL, `en` and `ar` labels. Pest tests: state machine, two-step approval, lock after approval, 403 on foreign requests, quote requirement.

## Preconditions
These tasks must be merged first: 13, 14, 17. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t5-3-requests-module`, commit with messages starting `T26:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 27 · توزيع الراتب على المشاريع (ALC) من كشف الرواتب

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-23–24
- **الجهد المقدّر:** 2 يوم
- **الاعتماديات:** Task 17، Task 11

#### Objective
تثبيت مفهوم Allocation كما حدّده المدير المالي: توزيع راتب أو فاتورة على أكثر من مشروع وبند، وليس تحويل الليرات.


#### Implementation scope

- جدول gl_payroll_splits ونموذجه وشاشة تحرير النسب مع كشف الرواتب الشهري.
- اقتراح أسطر سند ALC من النسب في شاشة salaries مع إمكانية التعديل.
- فاتورة مشتركة بين مشاريع: أسطر يدوية بالنوع نفسه.


#### Required deliverables

- الجدول والنموذج والشاشة واختبارات.


#### Business rules

- تغيير النسب أثناء الشهر يسري من الشهر التالي.
- مجموع نسب الموظف 100% لكل تاريخ سريان.
- الكسور الصغيرة تذهب إلى آخر سطر.


#### Security and permissions

- الرواتب لا تظهر إلا لأدوار المالية والإدارة العامة.


#### Exceptional and edge cases

- موظف بلا نسب: يُرفض إنشاء السند مع رسالة واضحة.
- مشروع انتهى تاريخه: تحذير فقط.


#### Acceptance criteria

- مجموع الأسطر = مبلغ الراتب بالدولار.
- اختبار حد الشهر والتقريب.


#### Mistakes to avoid

- استعمال ALC لتحويل ليرات من العام إلى مشروع (هذا TRF).


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 27 of the HC Financial Web plan (توزيع الراتب على المشاريع (ALC) من كشف الرواتب). Branch: `t5-4-payroll-allocation`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-24. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Implement salary allocation (voucher type ALC): one employee's salary split over several projects and budget lines. The split percentages live in `gl_payroll_splits` (employee, project, budget line, percent, valid_from) and come from the monthly payroll sheet; a change during a month applies from the next month (no day-based proration). On the `salaries` screen, propose the voucher lines from the split and let the accountant adjust them; save through PostVoucher as ALC with one line per project/budget line, the cash credit on the paying cash account, and rounding cents to the last line. Percentages of one employee must sum to 100 per valid_from. Also allow the same ALC for a shared invoice (manual lines). Pest tests: percent sum, month boundary, rounding, SYP rate, cannot approve own voucher.

## Preconditions
These tasks must be merged first: 17, 11. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t5-4-payroll-allocation`, commit with messages starting `T27:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 28 · تقارير المتابعة: المطابقة وغير المعوَّض والسلف والقروض

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-20–25
- **الجهد المقدّر:** 3 أيام
- **الاعتماديات:** Task 20، Task 11

#### Objective
أربعة تقارير تتابع ما حدّده المدير المالي: مطابقة مجمّع الحوالات، النقد غير المعوَّض، السلف المفتوحة، تحصيل القروض الصغيرة.


#### Implementation scope

- recon وuncomp وadvances وloans بشريط فلاتر واحد وتصدير CSV.


#### Required deliverables

- 4 شاشات واختبارات الأرقام.


#### Business rules

- السندات المعتمدة فقط.
- السلف بلا مهلة وبلا أعمار؛ التعويض بلا تنبيهات.


#### Security and permissions

- تقييد الرؤية حسب الدور والمشروع.


#### Exceptional and edge cases

- سلفة سُدِّدت بفواتير أكثر من قيمتها: الفرق ذمة على الموظف/المورّد.


#### Acceptance criteria

- كل تقرير يطابق بيانات الاختبار.


#### Mistakes to avoid

- إنشاء ماسحات تنبيه لم يطلبها المدير المالي.


#### Output and handoff to the next task
تعتمد عليها: Task 42.

#### Claude prompt

````text
## Role and goal
You are implementing Task 28 of the HC Financial Web plan (تقارير المتابعة: المطابقة وغير المعوَّض والسلف والقروض). Branch: `t5-5-followup-reports`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-25. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Build four read-only reports on `gl_voucher_lines` (approved vouchers only): (1) `recon` - hawala pool (account 1161) versus the sum of project balances and city general accounts, monthly; (2) `uncomp` - cash received without a project (`is_uncompensated`) not yet compensated, per city with age buckets under 30, 30-90 and over 90 days (no alerts); (3) `advances` - open advances (account 1201) by person (contact tag), no ageing, a settlement difference is a receivable from the employee or supplier; (4) `loans` - micro loan collection and arrears from `gl_micro_loans` and `gl_micro_instalments`. Share one filter bar (date, city, project) and CSV export. Scope by role (project managers see their projects only). Pest tests for each total against fixture vouchers.

## Preconditions
These tasks must be merged first: 20, 11. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t5-5-followup-reports`, commit with messages starting `T28:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 29 · القروض الصغيرة (Micro Projects): القرض والمنحة والأقساط

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-24–26
- **الجهد المقدّر:** 2.5 يوم
- **الاعتماديات:** Task 11، Task 17

#### Objective
تسجيل قرض ومنحة كل مستفيد وجدول أقساطه، مع بقاء الأقساط المستردة تحت قيود المانح الأصلي.


#### Implementation scope

- جدولا gl_micro_loans وgl_micro_instalments وشاشة إدخال المستفيد.
- مولّد جدول أقساط شهري متساوٍ (ليرة أو دولار).
- سند صرف (قرض إلى 1204، منحة إلى مصروف) وسند قسط (CRV).


#### Required deliverables

- الجدولان والشاشة والمولّد واختبارات.


#### Business rules

- القسط المسترد يحمل مصدر التمويل «Recovered loans» بوسم المانح.
- لا صندوق دوّار مستقل (قرار المدير المالي).


#### Security and permissions

- بيانات المستفيدين تظهر لأدوار محددة فقط.


#### Exceptional and edge cases

- دفع جزئي أو أكبر من القسط: يُرحَّل الفائض على القسط التالي.


#### Acceptance criteria

- رصيد 1204 = مجموع القروض − الأقساط المسددة.


#### Mistakes to avoid

- إنشاء حساب منفصل لكل مانح للأقساط.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 29 of the HC Financial Web plan (القروض الصغيرة (Micro Projects): القرض والمنحة والأقساط). Branch: `t5-6-micro-loans`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-26. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Build micro loan entry. For each beneficiary of a micro project record loan amount, grant amount, currency and a repayment schedule (`gl_micro_loans`, `gl_micro_instalments`: due_date, amount, paid_amount, paid_voucher_id). Disbursement posts through PostVoucher (loan part to 1204, grant part to the expense account of the budget line); each instalment received posts a CRV crediting 1204 and marks the instalment paid; instalments recovered carry fund source `Recovered loans` tagged with the original donor and keep that donor's restrictions. Provide a schedule generator (equal monthly instalments, SYP or USD) and an arrears indicator for the `loans` report. The legacy `MicroProject_ID` feeds these tables during the migration (task 32). Pest tests: schedule rounding, partial payment, overpayment, currency.

## Preconditions
These tasks must be merged first: 11, 17. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t5-6-micro-loans`, commit with messages starting `T29:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 30 · إيصال الحوالة: اليورو الأصلي والسعر الفعلي والعمولة

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-11-12
- **الجهد المقدّر:** يوم ونصف
- **الاعتماديات:** Task 13، Task 12

#### Objective
حفظ مبلغ اليورو الأصلي وسعر التحويل الفعلي لكل حوالة، وتسجيل العمولة مصروفًا على المنظمة.


#### Implementation scope

- حقول الإيصال الثلاثة في المنشئ.
- ترحيل العمولة إلى الحساب 5504 بلا وسم مشروع.
- عرض عملة المانح في تقرير المشاريع من اليورو المخزّن.


#### Required deliverables

- تعديل المنشئ وقاعدة التحقق واختبارات.


#### Business rules

- العمولة مصروف المنظمة وليست مصروف المشروع.
- المبلغ الصافي هو الذي يُودَع في رصيد المشروع.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases

- حوالة بالدولار مباشرة: بلا حقول يورو.
- عمولة صفر: لا يُنشأ سطر.


#### Acceptance criteria

- eur_amount × eur_rate − commission = الدولار المستلم بتسامح سنت واحد.


#### Mistakes to avoid

- قسمة العمولة على المشاريع.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 30 of the HC Financial Web plan (إيصال الحوالة: اليورو الأصلي والسعر الفعلي والعمولة). Branch: `t5-7-hawala-receipt`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-12. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Extend the donor-receipt voucher (CRV into the hawala account 1161) with the original EUR amount (`eur_amount`), the actual conversion rate of that transfer (`eur_rate`) and the commission in USD (`commission_usd`). The USD credited to the project is the net received; the commission is posted as an organisation expense to the new account 5504 (no project tag). The per-project `donor_rate` becomes only a default shown in the builder. Validate that eur_amount x eur_rate - commission equals the USD received within one cent. Show the donor-currency view on the project balances report from the stored EUR amounts. Pest tests: EUR and USD receipts, commission posting, rounding.

## Preconditions
These tasks must be merged first: 13, 12. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t5-7-hawala-receipt`, commit with messages starting `T30:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

## المرحلة 5: وحدة الترحيل من برنامج ويندوز · A · 5–20 نوفمبر


### 31 · خطوة المطابقة في dbload

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-11-05–09
- **الجهد المقدّر:** 2.5 يوم (جلسة مطابقة مع المدير المالي 11-09)
- **الاعتماديات:** Task 09، Task 12

#### Objective
ربط أسماء البرنامج القديم بحسابات الدفتر ومشاريعه ومانحيه وبنوده بجلسة مع المدير المالي.


#### Implementation scope

- قراءة الصناديق (الاسم والعملة والمدينة) والمشاريع والمانحين والبنود من القاعدة القديمة.
- تخمين تلقائي وجدول كلمات LINEKW لبنود الميزانية.
- شاشة تأكيد أو تغيير كل ربط وحفظه في gl_legacy_map مع المؤكِّد.


#### Required deliverables

- الشاشة + الخدمة + اختبارات التخمين.


#### Business rules

- لا ترحيل بلا ربط مؤكَّد.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases

- اسم قديم مكرر بعملتين: المفتاح اسم+عملة.


#### Acceptance criteria

- كل اسم قديم له ربط مؤكَّد أو علامة «غير مربوط».


#### Mistakes to avoid

- تأكيد الربط تلقائيًا بلا مراجعة بشرية.


#### Output and handoff to the next task
تعتمد عليها: Task 32.

#### Claude prompt

````text
## Role and goal
You are implementing Task 31 of the HC Financial Web plan (خطوة المطابقة في dbload). Branch: `t6-1-dbload-mapping`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-09. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Read distinct cash accounts (name, currency, city), project accounts, donors and budget lines from the legacy database. Auto-guess targets (cash by name, currency and city; project by name; donor by name; budget line by the keyword table in the mockup `LINEKW`). Screen to confirm or change each mapping; save in `gl_legacy_map` with the confirming user.

## Preconditions
These tasks must be merged first: 09, 12. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t6-1-dbload-mapping`, commit with messages starting `T31:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 32 · تهيئة العمليات وترحيلها إلى سندات

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-11-10–18
- **الجهد المقدّر:** 7 أيام (دفعتان متوازيتان)
- **الاعتماديات:** Task 31، Task 13

#### Objective
تحويل كل عملية قديمة إلى سند أو إلى «مشكلة» موثقة، بحيث تتطابق الأرصدة مع برنامج ويندوز.


#### Implementation scope

- I ← CRV، E ← CPV، A ← CPV على 1201، R ← CRV، X ← TRF، T ← TRF أو ALC/JV بقاعدة التوزيع.
- تجاوز action_deleted وتحويل action_not_approved إلى معلّقة.
- السعر الناقص: سعر بديل مع علامة fb.
- تشغيل على دفعات كوظيفة في الطابور مع شريط تقدم ووضع تجربة (dry-run).
- تغذية جدولي القروض الصغيرة من MicroProject_ID.


#### Required deliverables

- خدمة الترحيل + شاشة التقدم + تقرير المشاكل + اختبار التطابق.


#### Business rules

- قابلة للتكرار (idempotent) عبر legacy_action_id.


#### Security and permissions

- الاتصال legacy للقراءة فقط.


#### Exceptional and edge cases

- إعادة التشغيل بعد انقطاع: لا تكرار ولا سند ناقص.


#### Acceptance criteria

- البروفة تعيد لكل حساب كاش ومشروع أرصدة ويندوز (اختبار التطابق).


#### Mistakes to avoid

- كتابة أي شيء في الجداول القديمة.
- تشغيل بلا نسخة احتياطية.


#### Output and handoff to the next task
تعتمد عليها: Task 33، Task 34، Task 35، Task 36، Task 41، Task 42.

#### Claude prompt

````text
## Role and goal
You are implementing Task 32 of the HC Financial Web plan (تهيئة العمليات وترحيلها إلى سندات). Branch: `t6-2-dbload-posting`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-18. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Per legacy operation produce a voucher or an issue in `gl_import_issues`: I to CRV (Dr cash, Cr donor income), E to CPV (Dr expense, Cr cash, budget line fills account), A to CPV (Dr 1201, contact tag), R to CRV (Cr 1204 if micro-loan else 1201), X to TRF (Dr SYP safe, Cr USD safe), T to TRF (transfer, with `transfer_kind`) or ALC/JV (split) after the allocation rule. Missing rate: use the fallback rate and flag it. Skip `action_deleted`; `action_not_approved` becomes Pending. Idempotent through `legacy_action_id`; run in chunks as a queued job with a progress bar; dry-run mode.

## Done when
a rehearsal on the test copy reproduces, per cash account and project, the Windows program's balances (parity test).

## Preconditions
These tasks must be merged first: 31, 13. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t6-2-dbload-posting`, commit with messages starting `T32:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 33 · نقل المرفقات من FTP

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-11-16–17
- **الجهد المقدّر:** يومان
- **الاعتماديات:** Task 32

#### Objective
نقل 2055 مرفقًا من خادم FTP إلى القرص الخاص مع بصمة لكل ملف.


#### Implementation scope

- قرص attachments_ftp (league/flysystem-ftp) من .env فقط.
- ربط كل ملف بسنده عبر legacy_action_id وحساب sha256.
- تقرير: منسوخ، ملف مفقود، لا سند، عدم تطابق بصمة.


#### Required deliverables

- الأمر + التقرير + اختبارات بقرص وهمي.


#### Business rules

- قابل للتكرار عبر legacy_attachment_id.


#### Security and permissions

- لا تُنسخ بيانات FTP إلى المستودع؛ يُطلب مستخدم قراءة فقط.
- التخزين خارج public.


#### Exceptional and edge cases

- الجذر الإنتاجي غير مؤكد (بدون /test على الأرجح).


#### Acceptance criteria

- عدد الصفوف الداخلة = عدد الملفات الخارجة مع تقرير الاستثناءات.


#### Mistakes to avoid

- تشغيله على الإنتاج قبل تأكيد الجذر.


#### Output and handoff to the next task
تعتمد عليها: Task 43.

#### Claude prompt

````text
## Role and goal
You are implementing Task 33 of the HC Financial Web plan (نقل المرفقات من FTP). Branch: `t6-3-attachment-move`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-17. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Add disk `attachments_ftp` (`league/flysystem-ftp`), configured only from `.env` (`ATTACH_FTP_HOST`, `ATTACH_FTP_USER`, `ATTACH_FTP_PASSWORD`, `ATTACH_FTP_ROOT`; test root `ftp://hcsyria.org/financial/files/test`). For each `action_attachments` row (2,055), find the voucher by `legacy_action_id`, download `<root>/<attach_name>`, store on the private disk, save sha256, create `gl_voucher_attachments`. Report: copied, missing file, no voucher, checksum mismatch. Idempotent by `legacy_attachment_id`. Never commit credentials.

## Done when
count of rows in equals files out, with a report of the exceptions.

## Preconditions
These tasks must be merged first: 32. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t6-3-attachment-move`, commit with messages starting `T33:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 34 · الأرصدة الافتتاحية وإقفال الشهر (opening)

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-11-16–18
- **الجهد المقدّر:** 2.5 يوم
- **الاعتماديات:** Task 32

#### Objective
سندات افتتاحية من أرصدة 31/12/2025 وفحوص إقفال شهرية.


#### Implementation scope

- سندات OPN مع سطر موازنة 3901 لكل مشروع (يجب أن ينتهي صفرًا).
- فحوص الإقفال: الشهر السابق مقفل، لا سندات غير معتمدة، وأرصدة 11x3 و11x4 و2901 أصفار.
- ترحيل نهاية السنة إلى 1/1/2027 (المقيدة إلى 3201 والباقي إلى 3101).


#### Required deliverables

- الشاشة + الخدمة + اختبارات.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions

- إعادة فتح شهر مقفل من الإدارة العامة فقط وتُدقَّق.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- 3901 صفر لكل مشروع.
- الإقفال يُرفض إذا فشل أي فحص.


#### Mistakes to avoid

- إلغاء الإقفال بلا تدقيق.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 34 of the HC Financial Web plan (الأرصدة الافتتاحية وإقفال الشهر (opening)). Branch: `t6-4-opening-close`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-18. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
OPN vouchers for 31/12/2025 from legacy balances with the 3901 balancing line per project (must end at zero). Month close checks (previous month closed, no unapproved vouchers, 11x3, 11x4, 2901 at zero), irreversible except by an audited Head Office reopen. Year-end carry-forward to 1/1/2027 (restricted projects to 3201, others to 3101), replacing the temporary OPN (status Replaced).

## Preconditions
These tasks must be merged first: 32. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t6-4-opening-close`, commit with messages starting `T34:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 35 · تصحيح قيم الدولار (fixusd)

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-11-18–19
- **الجهد المقدّر:** يوم ونصف
- **الاعتماديات:** Task 32

#### Objective
معالجة نحو 4618 عملية بالليرة بسعر مخزن صفر.


#### Implementation scope

- حساب الدولار بمتوسط سعر المشروع في تاريخ العملية (D7).
- عرض القديم مقابل الجديد ثم التطبيق بمعاملة واحدة مع سطر تدقيق.


#### Required deliverables

- الشاشة + الخدمة + اختبارات.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- التطبيق كله أو لا شيء.


#### Mistakes to avoid

- التطبيق دون معاينة.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 35 of the HC Financial Web plan (تصحيح قيم الدولار (fixusd)). Branch: `t6-5-fix-usd`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-19. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Operations in SYP with stored rate 0 (about 4,618): compute the dollar value at the project average rate on the operation date (D7), show old versus new, apply in one transaction with an audit row.

## Preconditions
These tasks must be merged first: 32. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t6-5-fix-usd`, commit with messages starting `T35:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 36 · الصرف القديم ودمج الصناديق (legacy وmerges)

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-11-19–20
- **الجهد المقدّر:** 2 يوم
- **الاعتماديات:** Task 32

#### Objective
معالجة نحو 470 مجموعة صرف قديمة ودمج صندوقين مغلقين.


#### Implementation scope

- سعر واحد لكل مجموعة وسندات TRF بلا سطر 5503 (D8).
- دمج جوليانا في 1142 وشطب النزهة بسند تسوية، مع تأكيد بالكتابة.


#### Required deliverables

- الشاشتان + اختبارات.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions

- التأكيد بالكتابة قبل الترحيل.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- أرصدة الصناديق المدموجة صفر بعد الدمج.


#### Mistakes to avoid

- الدمج بلا سند تسوية.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 36 of the HC Financial Web plan (الصرف القديم ودمج الصناديق (legacy وmerges)). Branch: `t6-6-legacy-exchange-merges`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-20. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Legacy exchange groups (about 470): one rate, TRF vouchers with no 5503 line (D8). Cash till merges: Jouliana into 1142, Al-Nouzha written off with an adjustment voucher; type-to-confirm before posting.

## Preconditions
These tasks must be merged first: 32. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t6-6-legacy-exchange-merges`, commit with messages starting `T36:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

## المرحلة 6: شاشات الإدخال والبيانات الأساسية · B · 19–26 نوفمبر


### 37 · شاشات الإدخال على السندات

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-19–25
- **الجهد المقدّر:** 4 أيام
- **الاعتماديات:** Task 17

#### Objective
إعادة بناء شاشات الإدخال القديمة لتُرحِّل سندات عبر PostVoucher.


#### Implementation scope

- single وmulti وmultiatt وpayments وapproval وsalaries وطباعة السندات.
- الإدخال المتعدد: لصق من Excel، تحقق صف بصف، حفظ كله أو لا شيء.


#### Required deliverables

- الشاشات + اختبارات.


#### Business rules

- لا كتابة في الجداول القديمة أبدًا.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- كل شاشة تُنتج سندًا معلّقًا صحيحًا.


#### Mistakes to avoid

- إبقاء مسار كتابة قديم إلى جدول action.


#### Output and handoff to the next task
تعتمد عليها: Task 42.

#### Claude prompt

````text
## Role and goal
You are implementing Task 37 of the HC Financial Web plan (شاشات الإدخال على السندات). Branch: `t7-1-entry-screens`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-25. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Rebuild `single`, `multi`, `multiatt`, `payments`, `approval`, `salaries`, `vouchers` print so that they post vouchers through `PostVoucher` using the mockup's operation-to-voucher rule. They never write to legacy tables. Multi entry: paste from Excel, row validation, all-or-nothing save.

## Preconditions
These tasks must be merged first: 17. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t7-1-entry-screens`, commit with messages starting `T37:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 38 · شاشات البيانات الأساسية (قراءة فقط عدا الدليل والمشاريع والبرامج)

- **المطوّر والتواريخ:** المطوّر B · التواريخ: 2026-11-25–26
- **الجهد المقدّر:** يوم ونصف (قراءة فقط عدا الدليل والمشاريع والبرامج)
- **الاعتماديات:** Task 14

#### Objective
تحديد ما يُحرَّر في الويب وما يبقى مرجعًا للقراءة فقط لكل شاشة بيانات أساسية.


#### Implementation scope

- donors وcities وprograms وprojwin وbudgetlines وblcats وcurrency وdenoms وemployees وusers وlimits وtills وallaccounts وbonds وlifetime وlog.


#### Required deliverables

- قرار لكل شاشة + تنفيذها.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions

- users يغيّر كلمات المرور عبر FinancePassword::make فقط.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- لكل شاشة قرار مكتوب (تحرير أو قراءة).


#### Mistakes to avoid

- تحرير جداول قديمة مباشرة.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 38 of the HC Financial Web plan (شاشات البيانات الأساسية (قراءة فقط عدا الدليل والمشاريع والبرامج)). Branch: `t7-2-master-data`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-26. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
`donors`, `cities`, `programs`, `projwin`, `budgetlines`, `blcats`, `currency`, `denoms`, `employees`, `users`, `limits`, `tills`, `allaccounts`, `bonds`, `lifetime`, `log`: decide per screen, with the finance team, whether it is read-only reference from legacy or edited in the ledger tables, and implement accordingly. `users` changes passwords only through `FinancePassword::make`.

## Preconditions
These tasks must be merged first: 14. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t7-2-master-data`, commit with messages starting `T38:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

## المرحلة 7: التقوية والاختبار والتحويل النهائي · A + B · 23–30 نوفمبر


### 40 · فرض تغيير كلمة المرور المشتركة — ملغاة

- **الحالة:** ملغاة، لا تُنفَّذ.
- **السبب:** كلمة المرور المشتركة موجودة في بيئة الاختبار فقط (كل المستخدمين test). في قاعدة البيانات الفعلية لكل مستخدم كلمة مرور خاصة به، فلا حاجة لشاشة إجبارية لتغييرها. الفرع `t8-1-reset-shared-password` لا يُنشأ.

---

### 41 · الأداء والفحص الأمني

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-11-23–25
- **الجهد المقدّر:** 2 يوم
- **الاعتماديات:** Task 22، Task 32

#### Objective
فحص كل استعلام تقرير وتأمين الرفع والتنزيل.


#### Implementation scope

- EXPLAIN لكل استعلام تقرير وفهارس gl_* المطلوبة.
- مراجعة OWASP Top 10، ورفع الملفات، ومسارات تنزيل موقّعة، وتحديد المعدل، ورؤوس الأمان.


#### Required deliverables

- تقرير الفحص + الإصلاحات.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions

- لا فهارس على جداول قديمة بلا ترحيل منفصل ومراجع.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- لا استعلام تقرير يتجاوز العتبة المتفق عليها.


#### Mistakes to avoid

- إضافة فهرس على جدول قديم.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 41 of the HC Financial Web plan (الأداء والفحص الأمني). Branch: `t8-2-performance-security`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-25. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
`EXPLAIN` every report query; add `gl_*` indexes where needed (never on legacy tables without a separate reviewed migration). Run the security review (OWASP top 10, upload validation, signed download routes, rate limits, security headers).

## Preconditions
These tasks must be merged first: 22, 32. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t8-2-performance-security`, commit with messages starting `T41:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 42 · الاختبار الشامل وقبول المستخدمين (UAT)

- **المطوّر والتواريخ:** المطوّر A + المطوّر B · التواريخ: 2026-11-25–27 (متزامن مع آخر المهام)
- **الجهد المقدّر:** 3 أيام
- **الاعتماديات:** Task 37، Task 28، Task 32

#### Objective
التأكد أن المسار الكامل يعمل لكل دور، وأن أرصدة الدفتر تطابق برنامج ويندوز قبل التحويل النهائي.


#### Implementation scope

- سيناريو لكل دور مع المدير المالي والمحاسب.
- اختبار التطابق على بروفة استيراد كاملة.
- Pest وPint وPHPStan أخضر.


#### Required deliverables

- docs/UAT-REPORT.md بالنتائج والعيوب.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions
لا ينطبق على هذه المهمة.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- لا عيب حرج مفتوح.
- فروقات التطابق مفسّرة بالكامل.


#### Mistakes to avoid

- اعتماد القبول دون تشغيل التطابق.


#### Output and handoff to the next task
تعتمد عليها: Task 43.

#### Claude prompt

````text
## Role and goal
You are implementing Task 42 of the HC Financial Web plan (الاختبار الشامل وقبول المستخدمين (UAT)). Branch: `t8-3-qa-uat`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-27. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Prepare and run the end-to-end test and the user acceptance test. Write a UAT script per role (Accountant, Financial Manager, Project Manager, General Manager, Head Office) covering: prepare and approve CRV, CPV and TRF; transfer kinds; salary ALC; request with two approvals; hawala receipt in EUR; month close; the follow-up reports; the Home page filter. Run `.\php.cmd artisan test`, Pint and PHPStan; run the parity test on a fresh rehearsal import and record every difference. Output `docs/UAT-REPORT.md` with pass/fail per script step and open defects ranked.

## Preconditions
These tasks must be merged first: 37, 28, 32. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t8-3-qa-uat`, commit with messages starting `T42:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### 43 · دليل التحويل النهائي والإطلاق

- **المطوّر والتواريخ:** المطوّر A · التواريخ: 2026-11-24–25 (الدليل)، والتنفيذ 11-30
- **الجهد المقدّر:** 2 يوم + يوم التنفيذ
- **الاعتماديات:** Task 42، Task 33

#### Objective
تعليمات تنفيذ التحويل النهائي خطوة بخطوة، مع التراجع.


#### Implementation scope

- docs/CUTOVER.md: تجميد ويندوز، نسخة احتياطية، استيراد نهائي إلى hcsyria_financial بجذر FTP الإنتاجي، فحوص التطابق وعدّ المرفقات، توجيه .env، اختبار دخان لكل دور، أرشفة ويندوز والقاعدة القديمة للقراءة فقط، وتدوير بيانات FTP وMySQL.


#### Required deliverables

- docs/CUTOVER.md + بروفة جافة.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions

- تدوير كلمتي مرور FTP وMySQL المكتوبتين في كود ويندوز بعد الإطلاق.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- بروفة جافة كاملة بزمن معروف وخطة تراجع مكتوبة.


#### Mistakes to avoid

- التحويل قبل التجميد.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 43 of the HC Financial Web plan (دليل التحويل النهائي والإطلاق). Branch: `t8-4-cutover-runbook`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Deadline
Target date: 2026-11-30. October items feed the end-of-October demo (Home, side tree, programs, projects); everything else must be done by 2026-11-30.

## Task
Write `docs/CUTOVER.md`: freeze the Windows program, backup, final import into `hcsyria_financial` with the production FTP root, parity checks (balances per cash account and project, attachment counts), point `.env` at the production database, smoke test per role, archive the Windows program and the old database read-only, rotate the FTP and MySQL credentials found in the old source.

## Preconditions
These tasks must be merged first: 42, 33, 40. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t8-4-cutover-runbook`, commit with messages starting `T43:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

## المرحلة 8: خارج نافذة نوفمبر · B · بعد 30 نوفمبر


### 39 · صفحات الجسر (decisions وmapping وrules) — مؤجلة

- **المطوّر والتواريخ:** المطوّر B · التواريخ: بعد 2026-11-30
- **الجهد المقدّر:** يوم
- **الاعتماديات:** Task 15

#### Objective
صفحات قراءة للقرارات والتطابق والقواعد. ليست في طريق التحويل النهائي، فتؤجَّل إلى ما بعد 30 نوفمبر عند الضغط.


#### Implementation scope

- decisions (D1-D11 وQ-a إلى Q-e وجولات الأجوبة) وmapping وrules.


#### Required deliverables

- 3 صفحات.


#### Business rules
لا ينطبق على هذه المهمة.


#### Security and permissions

- ظاهرة للإدارة العامة والمدير المالي فقط.


#### Exceptional and edge cases
لا ينطبق على هذه المهمة.


#### Acceptance criteria

- غير الأدوار المعنية يتلقون 403.


#### Mistakes to avoid

- إظهارها لباقي المستخدمين.


#### Output and handoff to the next task
لا مهمة تعتمد عليها مباشرة.

#### Claude prompt

````text
## Role and goal
You are implementing Task 39 of the HC Financial Web plan (صفحات الجسر (decisions وmapping وrules) — مؤجلة). Branch: `t7-3-bridge-pages`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` (authoritative) and `docs/PLAN-hcsyria_financial_test-AR.md`, especially sections 1, 3.2, 6 and 6.8-6.9.
3. `docs/DECISIONS.md` once task 01 has produced it.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Read-only pages `decisions` (D1-D11, Q-a to Q-e), `mapping` and `rules` from the mockup, visible to Head Office and Financial Manager.

## Preconditions
These tasks must be merged first: 15. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `t7-3-bridge-pages`, commit with messages starting `T39:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

## المرحلة 9: تحسينات خارج الخطة (U · IT · AI) · بلا قرار مالي · لا تتداخل مع المهام 01 إلى 45

مصدرها `docs/improvement-tasks.json`، فعدّلها هناك ثم أعد التوليد. تُستورد إلى ClickUp من ملف CSV، لا عبر `scripts/clickup_sync.py` (الذي يزامن مهام الخطة الـ 45 فقط). الفرع `u-NN-…` أو `it-NN-…` أو `ai-NN-…`، ورسائل الـ commit تبدأ برقم المهمة.


### U-01 · جرس إشعارات داخل البرنامج

- **المجموعة:** تحسينات يحسّها المستخدم · **المجال:** الإشعارات · **الأولوية:** عالية
- **الجهد المقدّر:** يومان
- **الاعتماديات:** لا يوجد
- **الحالة:** خالصة ومدموجة في main

#### Objective
لا يوجد جدول notifications ولا أي إشعار. الموجود فقط رقم بجانب قائمة السندات يعدّ السندات المعلقة (Navigation.php badges، topbar.blade.php:43)، فلا يعرف المحاسب أن سنده رُفض ولا يعرف مدير المشروع أن طلبه اعتُمد.

#### Implementation scope
جرس في الشريط العلوي فيه عدد غير المقروء وآخر الإشعارات، يتحدّث كل دقيقة. جدول خاص web_notifications (بـ migration)، والإشعارات تُرسل من سجل تدقيق السندات والطلبات بعد حفظ التغيير. السند المعلّق يذهب لكل من تسمح له قواعد الاعتماد باعتماده الآن (الدور والحد والدور في سلسلة إيصالات المانحين)، وليس لمن أعدّه أبداً. السند المعتمد أو المرفوض (مع السبب) يذهب لمن أعدّه. الطلب المقدَّم يذهب لمن عليه الدور (مدير البرنامج ثم المدير المالي ثم المدير العام)، والطلب المعتمد أو المرفوض (مع السبب) لمدير المشروع. السندات المرحّلة من البرنامج القديم لا تُرسل إشعاراً. النقر على الإشعار يعلّمه مقروءاً ويفتح السند أو الطلب.

#### Acceptance criteria
رفض سند يُظهر إشعاراً لمُعدّه خلال دقيقة، والنقر عليه يفتح السند.

#### References in the code
- `database/migrations (no notifications table)`
- `app/Support/Navigation.php badges()`
- `resources/views/components/navigation/topbar.blade.php:43`

#### Claude prompt

````text
## Role and goal
You are implementing improvement U-01 of the HC Financial Web plan (In-app notifications bell). Branch: `u-01-notifications`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Add a bell in the top bar with the person's unread count and latest notifications, refreshed every minute (Livewire poll). Send them from the voucher and request audit trails, after the change is committed: a voucher waiting for approval goes to everyone the ApprovalPolicy lets approve it now (role, limit, donor-receipt chain), never the preparer; an approved or rejected voucher (with the reason) goes to its preparer; a submitted request, or one past a step, goes to whoever RequestPolicy::decide allows next; an approved or rejected request (with the reason) goes to the requester. Migrated vouchers notify no one. Table `web_notifications` (migration); texts in lang en/ar built when shown; opening one marks it read and goes to the voucher or request.

## Done when
Rejecting a voucher shows a notification to its preparer within a minute, and clicking it opens the voucher.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `u-01-notifications`, commit with messages starting `U-01:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### U-02 · صفحات أخطاء بالعربية وبهوية البرنامج

- **المجموعة:** تحسينات يحسّها المستخدم · **المجال:** الواجهة · **الأولوية:** عالية
- **الجهد المقدّر:** يوم
- **الاعتماديات:** لا يوجد
- **الحالة:** خالصة ومدموجة في main

#### Objective
لا يوجد مجلد resources/views/errors، فتظهر صفحات Laravel الافتراضية بالإنكليزية عند 403 و404 و419 و500 و503. وعند انتهاء الجلسة يظهر سؤال Livewire الافتراضي بالإنكليزية.

#### Implementation scope
صفحات 403 و404 و419 و500 و503 بالعربية والإنكليزية حسب لغة المستخدم، بنفس تصميم البرنامج، مع زر رجوع للرئيسية. استبدال رسالة انتهاء الجلسة في Livewire برسالة عربية تعيد المستخدم لتسجيل الدخول.

#### Acceptance criteria
كل صفحات الأخطاء الخمس وتنبيه انتهاء الجلسة تظهر بلغة المستخدم وبتصميم البرنامج.

#### References in the code
- `no resources/views/errors directory`
- `bootstrap/app.php:30-34`

#### Claude prompt

````text
## Role and goal
You are implementing improvement U-02 of the HC Financial Web plan (Arabic error pages). Branch: `u-02-error-pages`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Add `resources/views/errors` pages for 403, 404, 419, 500 and 503 in the app's design and the person's language (en/ar), with a link back home. Replace Livewire's default English session-expired prompt with a localized message that sends the person to sign in.

## Done when
All five error pages and the session-expired notice show in the person's language and the app's design.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `u-02-error-pages`, commit with messages starting `U-02:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### U-03 · لوحة «ما ينتظرني» في الصفحة الرئيسية

- **المجموعة:** تحسينات يحسّها المستخدم · **المجال:** الواجهة · **الأولوية:** متوسطة
- **الجهد المقدّر:** 1.5 أيام
- **الاعتماديات:** لا يوجد
- **الحالة:** خالصة ومدموجة في main

#### Objective
الصفحة الرئيسية تعرض أرصدة المشاريع فقط (Home.php:17-39)، ولا تُظهر للمستخدم ما عليه فعله اليوم.

#### Implementation scope
قسم أعلى الصفحة حسب الدور: سندات تنتظر اعتمادي، سنداتي المرفوضة، طلبات تنتظر قراري، طلباتي المعلقة. كل بند يفتح القائمة المفلترة.

#### Acceptance criteria
كل دور يرى عند الدخول عدد الأعمال المعلقة عليه، والنقر يفتح القائمة المفلترة.

#### References in the code
- `app/Livewire/Home.php:17-39`
- `resources/views/livewire/home.blade.php:5-8`

#### Claude prompt

````text
## Role and goal
You are implementing improvement U-03 of the HC Financial Web plan (Home panel: what is waiting for me). Branch: `u-03-waiting-panel`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
At the top of Home, per role: vouchers waiting for my approval, my rejected vouchers, requests waiting for my decision, my pending requests; each opens the filtered list. Use the same approval rules as the bell (U-01) to decide what counts.

## Done when
Each role sees its pending work on sign-in, and a click opens the filtered list.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `u-03-waiting-panel`, commit with messages starting `U-03:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### U-04 · تنبيه قبل مغادرة صفحة فيها تعديلات غير محفوظة

- **المجموعة:** تحسينات يحسّها المستخدم · **المجال:** الواجهة · **الأولوية:** متوسطة
- **الجهد المقدّر:** يوم
- **الاعتماديات:** لا يوجد
- **الحالة:** خالصة ومدموجة في main

#### Objective
لا يوجد wire:dirty ولا beforeunload في أي شاشة (الوحيد wire:confirm عند إغلاق طلب في requests.blade.php:198)، فيضيع قيد مكتوب جزئياً عند النقر على رابط آخر.

#### Implementation scope
تحذير عند مغادرة شاشات القيد المفرد ومنشئ السند والقيد المتعدد والطلبات والرواتب إذا كان فيها تعديل غير محفوظ، ويشمل التنقل عبر wire:navigate.

#### Acceptance criteria
مغادرة نموذج فيه تعديل غير محفوظ تطلب تأكيداً في كل شاشات الإدخال.

#### References in the code
- `resources/views/livewire/requests.blade.php:198`
- `no wire:dirty or beforeunload in resources/`

#### Claude prompt

````text
## Role and goal
You are implementing improvement U-04 of the HC Financial Web plan (Warn before leaving unsaved changes). Branch: `u-04-unsaved-changes`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Warn before leaving single entry, the voucher builder, multi entry, requests and salaries when a form has unsaved changes, including `wire:navigate` links.

## Done when
Leaving a form with unsaved changes asks for confirmation on every entry screen.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `u-04-unsaved-changes`, commit with messages starting `U-04:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### U-05 · تنبيه قبل انتهاء الجلسة

- **المجموعة:** تحسينات يحسّها المستخدم · **المجال:** الواجهة · **الأولوية:** متوسطة
- **الجهد المقدّر:** نصف يوم
- **الاعتماديات:** لا يوجد

#### Objective
الجلسة تنتهي بعد 120 دقيقة (SESSION_LIFETIME في .env.example) دون أي تنبيه، فيفقد المحاسب ما كتبه عند الحفظ.

#### Implementation scope
تنبيه قبل خمس دقائق من انتهاء الجلسة مع زر «متابعة العمل» يجدّدها.

#### Acceptance criteria
يظهر التنبيه قبل انتهاء الجلسة، والنقر على متابعة يمدّها دون فقدان النموذج.

#### References in the code
- `.env.example SESSION_LIFETIME=120`

#### Claude prompt

````text
## Role and goal
You are implementing improvement U-05 of the HC Financial Web plan (Warn before the session expires). Branch: `u-05-session-warning`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Five minutes before `SESSION_LIFETIME` ends, show a notice with a Keep working button that refreshes the session without losing the form.

## Done when
The notice appears before expiry, and Keep working extends the session without losing the form.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `u-05-session-warning`, commit with messages starting `U-05:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### U-06 · بحث شامل بـ Ctrl+K

- **المجموعة:** تحسينات يحسّها المستخدم · **المجال:** الواجهة · **الأولوية:** متوسطة
- **الجهد المقدّر:** يومان
- **الاعتماديات:** لا يوجد
- **الحالة:** خالصة ومدموجة في main

#### Objective
البحث الوحيد هو بحث شجرة الحسابات في الشريط الجانبي (sidebar.blade.php:72-76)، ولا توجد طريقة سريعة للوصول إلى سند برقمه أو مشروع باسمه.

#### Implementation scope
نافذة بحث تُفتح بـ Ctrl+K من أي شاشة: السندات برقمها ووصفها، الحسابات برمزها واسمها، المشاريع، والشاشات. النتائج تحترم صلاحيات المستخدم ونطاق بياناته.

#### Acceptance criteria
كتابة رقم سند في نافذة البحث تفتحه مباشرة، والنتائج لا تُظهر ما لا يحق للمستخدم رؤيته.

#### References in the code
- `resources/views/components/navigation/sidebar.blade.php:72-76`

#### Claude prompt

````text
## Role and goal
You are implementing improvement U-06 of the HC Financial Web plan (Global search (Ctrl+K)). Branch: `u-06-global-search`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
A search dialog opened with Ctrl+K from any screen: vouchers by number and description, accounts by code and name, projects, and screens. Results respect the person's screen permissions and data scope.

## Done when
Typing a voucher number opens it directly, and results never show what the person may not see.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `u-06-global-search`, commit with messages starting `U-06:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### U-07 · اختصارات لوحة المفاتيح في شاشات الإدخال

- **المجموعة:** تحسينات يحسّها المستخدم · **المجال:** الواجهة · **الأولوية:** متوسطة
- **الجهد المقدّر:** يوم
- **الاعتماديات:** لا يوجد
- **الحالة:** خالصة ومدموجة في main

#### Objective
الاختصارات الموجودة للتنقل داخل الحقول فقط (form-fields.js:283، form-segmented.blade.php:59-62)، ولا يوجد اختصار للحفظ أو لإضافة سطر، والمحاسبون معتادون على الكيبورد في البرنامج القديم.

#### Implementation scope
Ctrl+S للحفظ، Ctrl+Enter للحفظ وبدء قيد جديد، Alt+N لإضافة سطر في منشئ السند، Esc لإغلاق النوافذ. لائحة اختصارات تظهر بـ ?.

#### Acceptance criteria
الاختصارات تعمل في القيد المفرد ومنشئ السند والقيد المتعدد، ولائحة الاختصارات تظهر بـ ?.

#### References in the code
- `resources/js/form-fields.js:283`
- `resources/views/components/ui/form-segmented.blade.php:59-62`

#### Claude prompt

````text
## Role and goal
You are implementing improvement U-07 of the HC Financial Web plan (Keyboard shortcuts on entry screens). Branch: `u-07-shortcuts`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Ctrl+S saves, Ctrl+Enter saves and starts a new entry, Alt+N adds a line in the voucher builder, Esc closes dialogs, and ? shows the list of shortcuts.

## Done when
The shortcuts work on single entry, the voucher builder and multi entry, and ? shows the list.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `u-07-shortcuts`, commit with messages starting `U-07:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### U-08 · تثبيت البرنامج على الكمبيوتر كتطبيق (PWA)

- **المجموعة:** تحسينات يحسّها المستخدم · **المجال:** الواجهة · **الأولوية:** متوسطة
- **الجهد المقدّر:** نصف يوم
- **الاعتماديات:** لا يوجد

#### Objective
لا يوجد manifest ولا service worker في public/، وhead.blade.php فيه theme-color فقط. الموظفون معتادون على برنامج له أيقونة ونافذة خاصة.

#### Implementation scope
ملف manifest بالاسم والأيقونات، وservice worker يخزّن ملفات الواجهة فقط دون أي إدخال بلا إنترنت، وزر «تثبيت البرنامج» في قائمة المستخدم.

#### Acceptance criteria
يمكن تثبيت البرنامج من Chrome أو Edge، فيظهر بأيقونة على سطح المكتب وينفتح بنافذة مستقلة.

#### References in the code
- `public/ (no manifest.json, no sw.js)`
- `resources/views/partials/head.blade.php:10-11`

#### Claude prompt

````text
## Role and goal
You are implementing improvement U-08 of the HC Financial Web plan (Install the app on the computer (PWA)). Branch: `u-08-pwa`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Add a web app manifest (name, icons, theme), a service worker that caches static assets only (no offline entry), and an Install the app item in the user menu.

## Done when
Chrome or Edge can install the app; it opens in its own window from a desktop icon.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `u-08-pwa`, commit with messages starting `U-08:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### U-09 · التحقق بخطوتين للإدارة العامة والمدير المالي

- **المجموعة:** تحسينات يحسّها المستخدم · **المجال:** الأمان · **الأولوية:** متوسطة
- **الجهد المقدّر:** يومان
- **الاعتماديات:** لا يوجد

#### Objective
ميزات Fortify كلها مطفأة (config/fortify.php:58)، فيكفي اسم المستخدم وكلمة السر لاعتماد السندات. ولا يمكن حفظ مفتاح التحقق في جدول user التابع للبرنامج القديم.

#### Implementation scope
تحقق بخطوتين بتطبيق مصادقة (TOTP) مع رموز احتياطية. المفتاح يُحفظ مشفراً في جدول ويب خاص. يُفرض على Head Office والمدير المالي ويكون اختيارياً للبقية.

#### Acceptance criteria
المدير المالي لا يدخل إلا برمز التطبيق، ورموز الاحتياط تعمل مرة واحدة لكل رمز.

#### References in the code
- `config/fortify.php:58`
- `app/Providers/FortifyServiceProvider.php:24`

#### Claude prompt

````text
## Role and goal
You are implementing improvement U-09 of the HC Financial Web plan (Two-factor sign-in for Head Office and the Financial Manager). Branch: `u-09-two-factor`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
TOTP with recovery codes through Fortify's two-factor feature. The Windows program's `user` table cannot hold the secret: store it encrypted in a web table. Required for Head Office and the Financial Manager, optional for the others.

## Done when
The Financial Manager cannot sign in without the app's code, and each recovery code works once.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `u-09-two-factor`, commit with messages starting `U-09:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### U-10 · تحميل التقارير الثقيلة تدريجياً مع مؤشر انتظار

- **المجموعة:** تحسينات يحسّها المستخدم · **المجال:** الأداء · **الأولوية:** متوسطة
- **الجهد المقدّر:** يوم
- **الاعتماديات:** لا يوجد
- **الحالة:** خالصة ومدموجة في main

#### Objective
لا يوجد أي مكوّن Livewire يستخدم #[Lazy]، فتنتظر الصفحة حساب التقرير كاملاً قبل أن يظهر أي شيء، وأدوات التصفية لا تُظهر أنها تعمل (wire:loading مستخدم مرة واحدة فقط في المشروع).

#### Implementation scope
تحويل ميزان المراجعة وكشف الحساب والقوائم المالية وأرصدة المشاريع والتقارير التشغيلية إلى تحميل كسول مع هيكل انتظار، ومؤشر تحميل عند تغيير الفلاتر.

#### Acceptance criteria
تظهر الصفحة فوراً بهيكل انتظار ثم يُملأ التقرير، وتغيير الفلتر يُظهر مؤشراً.

#### References in the code
- `app/Livewire (no #[Lazy])`
- `resources/views/livewire/voucher-builder.blade.php:249 (only wire:loading)`

#### Claude prompt

````text
## Role and goal
You are implementing improvement U-10 of the HC Financial Web plan (Load heavy reports progressively). Branch: `u-10-lazy-reports`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Make the trial balance, account statement, financial statements, project balances and operational reports `#[Lazy]` with a skeleton, and show a loading indicator when a filter changes.

## Done when
The page shows at once with a skeleton, then the report; changing a filter shows an indicator.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `u-10-lazy-reports`, commit with messages starting `U-10:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### U-11 · قناة تلغرام اختيارية للإشعارات

- **المجموعة:** تحسينات يحسّها المستخدم · **المجال:** الإشعارات · **الأولوية:** منخفضة
- **الجهد المقدّر:** 1.5 أيام
- **الاعتماديات:** U-01

#### Objective
البريد مطفأ (MAIL_MAILER=log في .env.example) ووصوله غير مضمون، وتلغرام أبسط وأرخص من واتساب.

#### Implementation scope
بوت تلغرام يربطه كل مستخدم بحسابه برمز من صفحة ملفه. يرسل الإشعارات نفسها من الجرس بنص مختصر دون مبالغ، مع رابط للبرنامج. يعتمد على U-01.

#### Acceptance criteria
المستخدم المرتبط يصله إشعار رفض سنده على تلغرام خلال دقيقة.

#### References in the code
- `.env.example MAIL_MAILER=log`

#### Claude prompt

````text
## Role and goal
You are implementing improvement U-11 of the HC Financial Web plan (Optional Telegram channel for notifications). Branch: `u-11-telegram`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
A Telegram bot that each user links from their profile with a one-time code. It sends the same notifications as the bell, as short text with no amounts, plus a link to the app.

## Done when
A linked user gets the rejection of their voucher on Telegram within a minute.

## Preconditions
These must be merged first: U-01. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `u-11-telegram`, commit with messages starting `U-11:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### U-12 · حفظ لغة الواجهة لكل مستخدم

- **المجموعة:** تحسينات يحسّها المستخدم · **المجال:** الواجهة · **الأولوية:** منخفضة
- **الجهد المقدّر:** نصف يوم
- **الاعتماديات:** لا يوجد
- **الحالة:** خالصة ومدموجة في main

#### Objective
اللغة محفوظة في الجلسة فقط (SetLocale.php:14-15، SwitchLocaleController.php:18)، فتضيع عند تسجيل الخروج أو انتهاء الجلسة، ويرجع المستخدم إلى اللغة الافتراضية. والجدول web_user_preferences والصنف UserPreferences موجودان أصلاً لإعدادات كل مستخدم.

#### Implementation scope
عندما يغيّر المستخدم اللغة وهو داخل، تُحفظ له في web_user_preferences بالمفتاح locale عبر UserPreferences. عند تسجيل الدخول تُقرأ لغته المحفوظة وتوضع في الجلسة. إن لم تكن له لغة محفوظة بعد، تُحفظ اللغة التي اختارها في صفحة الدخول. لا يُكتب شيء في جدول user التابع للبرنامج القديم.

#### Acceptance criteria
مستخدم اختار العربية ثم سجّل الخروج، يدخل مرة ثانية ولو من متصفح آخر فتظهر الواجهة بالعربية.

#### References in the code
- `app/Http/Middleware/SetLocale.php:14-30`
- `app/Http/Controllers/SwitchLocaleController.php:18`
- `app/Support/UserPreferences.php`
- `database/migrations/2026_10_22_130000_create_web_user_preferences_table.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement U-12 of the HC Financial Web plan (Remember each user's interface language). Branch: `u-12-user-locale`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
The interface language lives in the session only (`SetLocale`, `SwitchLocaleController`), so it is lost at sign-out. When a signed-in person switches language, save it with `UserPreferences` under the key `locale` (table `web_user_preferences`, never the Windows program's `user` table). On sign-in (Fortify's Login event), put the saved language into the session; if the person has none saved yet, save the language they picked on the sign-in page. Test: switch to Arabic, sign out, sign in again from a fresh session, and see Arabic.

## Done when
A person who chose Arabic, signed out and signed in again, from any browser, sees the app in Arabic.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `u-12-user-locale`, commit with messages starting `U-12:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### IT-01 · نسخ احتياطي يومي لجداول الويب

- **المجموعة:** شغل IT بحت · **المجال:** التشغيل والنسخ الاحتياطي · **الأولوية:** عالية
- **الجهد المقدّر:** يوم
- **الاعتماديات:** لا يوجد

#### Objective
جداول الويب (gl_* وweb_*) هي المصدر الوحيد للسندات الجديدة، ولا توجد أي مهمة مجدولة: routes/console.php فيه أمر inspire فقط، ولا توجد حزمة نسخ احتياطي في composer.json.

#### Implementation scope
أمر artisan يصدّر جداول gl_* وweb_* فقط (دون جداول البرنامج القديم) إلى ملف مضغوط بتاريخه. جدولته يومياً ليلاً مع الاحتفاظ بآخر 14 نسخة. توثيق طريقة الاسترجاع وتجربتها مرة على قاعدة اختبار.

#### Acceptance criteria
تُنشأ نسخة كل ليلة تلقائياً، وتنجح تجربة استرجاع موثقة على قاعدة منفصلة.

#### References in the code
- `routes/console.php:1-8`
- `composer.json (no backup package)`

#### Claude prompt

````text
## Role and goal
You are implementing improvement IT-01 of the HC Financial Web plan (Daily backup of the web tables). Branch: `it-01-backup`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
An artisan command that dumps only the `gl_*` and `web_*` tables (never the Windows program's tables) to a dated compressed file; schedule it nightly, keep the last 14, and document and rehearse a restore into a separate database.

## Done when
A backup is made every night, and a documented restore works on a separate database.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `it-01-backup`, commit with messages starting `IT-01:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### IT-02 · تشغيل الاختبارات والفحوص تلقائياً على GitHub

- **المجموعة:** شغل IT بحت · **المجال:** تجربة المطوّر · **الأولوية:** عالية
- **الجهد المقدّر:** يوم
- **الاعتماديات:** لا يوجد

#### Objective
لا يوجد مجلد .github/workflows، وملف .gitleaks.toml:1 يذكر صراحة أنه لا يوجد CI. الاختبارات تعمل على sqlite في الذاكرة (phpunit.xml:27-28) فيسهل تشغيلها آلياً، وأمر composer test جاهز.

#### Implementation scope
إضافة GitHub Action يعمل على كل Pull Request: Pint وPHPStan (المستوى 7) وPest وgitleaks. منع الدمج في main إذا فشل الفحص.

#### Acceptance criteria
كل Pull Request يُظهر نتيجة الفحوص الأربعة، ولا يُدمج إلا إذا نجحت.

#### References in the code
- `.gitleaks.toml:1`
- `phpunit.xml:27-28`
- `composer.json scripts.test`
- `no .github/workflows directory`

#### Claude prompt

````text
## Role and goal
You are implementing improvement IT-02 of the HC Financial Web plan (Run the tests and checks on GitHub). Branch: `it-02-ci`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
A GitHub Actions workflow on every pull request and on main: Pint --test, PHPStan, Pest (sqlite in memory, as phpunit.xml) and gitleaks. Protect main so a failing check blocks the merge.

## Done when
Every pull request shows the four checks, and none merges while one fails.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `it-02-ci`, commit with messages starting `IT-02:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### IT-03 · توحيد تنسيق وجمع المبالغ بدل الأرقام العشرية العائمة

- **المجموعة:** شغل IT بحت · **المجال:** جودة الكود · **الأولوية:** عالية
- **الجهد المقدّر:** يوم
- **الاعتماديات:** لا يوجد

#### Objective
قاعدة المشروع أن المال يُحسب بـ bcmath وليس float، لكن الصفحة الرئيسية تجمع الأرصدة كـ float (home.blade.php:7-8) وكذلك AccountBalances.php:31. وكل خدمة تقريباً تكتب دالة تنسيق خاصة بها بـ number_format((float)…) (AccountStatement.php:88، TrialBalance.php:92، OperationalReports.php:81، ProjectBalances.php:246، ProjectDetails.php:79).

#### Implementation scope
إضافة Money::format وMoney::sum إلى app/Support/Money.php. استبدال الدوال المكررة والجمع بالـ float بهما. اختبار لحالات التقريب.

#### Acceptance criteria
لا يبقى جمع مبالغ بـ float، ودالة تنسيق واحدة تستخدمها كل التقارير.

#### References in the code
- `resources/views/livewire/home.blade.php:7-8`
- `app/Services/Chart/AccountBalances.php:31`
- `app/Services/Ledger/AccountStatement.php:88`
- `app/Services/Ledger/TrialBalance.php:92`
- `app/Services/Ledger/OperationalReports.php:81`
- `app/Support/Money.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement IT-03 of the HC Financial Web plan (Sum and format money with bcmath only). Branch: `it-03-money-helpers`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Add `Money::format` and `Money::sum` to `app/Support/Money.php`; replace the float sums (home page, `AccountBalances`) and the per-service `number_format((float) ...)` helpers with them; add rounding tests.

## Done when
No money is summed as a float, and every report uses the one formatting helper.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `it-03-money-helpers`, commit with messages starting `IT-03:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### IT-04 · تدوير سجل الأخطاء يومياً

- **المجموعة:** شغل IT بحت · **المجال:** التشغيل والنسخ الاحتياطي · **الأولوية:** متوسطة
- **الجهد المقدّر:** نصف يوم
- **الاعتماديات:** لا يوجد

#### Objective
السجل ملف واحد (LOG_STACK=single في .env.example) يكبر بلا حد على السيرفر.

#### Implementation scope
قناة daily مع الاحتفاظ بآخر 14 يوماً، ومستوى warning في الإنتاج بدل debug.

#### Acceptance criteria
يُنشأ ملف سجل لكل يوم وتُحذف الملفات الأقدم من 14 يوماً تلقائياً.

#### References in the code
- `.env.example LOG_STACK=single, LOG_LEVEL=debug`

#### Claude prompt

````text
## Role and goal
You are implementing improvement IT-04 of the HC Financial Web plan (Rotate the error log daily). Branch: `it-04-log-rotation`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Use the daily log channel with 14 days kept, and the warning level in production instead of debug (`.env.example` and the docs).

## Done when
A log file is made per day, and files older than 14 days are removed.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `it-04-log-rotation`, commit with messages starting `IT-04:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### IT-05 · تنبيه فوري عند أخطاء السيرفر

- **المجموعة:** شغل IT بحت · **المجال:** التشغيل والنسخ الاحتياطي · **الأولوية:** متوسطة
- **الجهد المقدّر:** يوم
- **الاعتماديات:** لا يوجد

#### Objective
الأخطاء تُكتب في السجل فقط (bootstrap/app.php:30-34)، فلا يعرف الفريق بخطأ 500 إلا عندما يشتكي مستخدم.

#### Implementation scope
إرسال ملخص كل خطأ 500 (دون بيانات مالية) إلى قناة الفريق مع تجميع الأخطاء المتكررة، وتقرير يومي بعدد الأخطاء.

#### Acceptance criteria
خطأ 500 في الإنتاج يصل تنبيهه للفريق خلال دقائق، ولا يتكرر للخطأ نفسه أكثر من مرة كل ساعة.

#### References in the code
- `bootstrap/app.php:30-34`
- `.env.example MAIL_MAILER=log`

#### Claude prompt

````text
## Role and goal
You are implementing improvement IT-05 of the HC Financial Web plan (Alert the team on server errors). Branch: `it-05-error-alerts`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Send a short summary of each 500 error (no financial data) to the team's channel, grouping repeats to one per error per hour, plus a daily count.

## Done when
A 500 in production reaches the team within minutes, and at most once an hour per error.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `it-05-error-alerts`, commit with messages starting `IT-05:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### IT-06 · اختبارات للخدمات الحساسة التي لا يختبرها شيء مباشرة

- **المجموعة:** شغل IT بحت · **المجال:** جودة الكود · **الأولوية:** متوسطة
- **الجهد المقدّر:** 1.5 أيام
- **الاعتماديات:** لا يوجد

#### Objective
لا يوجد اختبار يذكر VoucherAttachments.php (رفع الملفات، 135 سطراً) ولا VoucherNumberAllocator.php (ترقيم السندات تحت القفل) ولا HawalaReceiptIsConsistent.php ولا StatementExport.php.

#### Implementation scope
اختبارات Pest: رفض الملفات غير المسموحة وحدودها، عدم تكرار رقم السند حتى مع سند محذوف، قاعدة الحوالة بحالاتها، وتصدير كشف الحساب.

#### Acceptance criteria
كل خدمة من الأربع لها اختبار يغطي الحالة الناجحة وحالة الرفض.

#### References in the code
- `app/Services/Ledger/VoucherAttachments.php`
- `app/Services/Ledger/VoucherNumberAllocator.php`
- `app/Services/Ledger/Rules/HawalaReceiptIsConsistent.php`
- `app/Services/Ledger/StatementExport.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement IT-06 of the HC Financial Web plan (Tests for the sensitive services that have none). Branch: `it-06-service-tests`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Pest tests for VoucherAttachments (types, size, count), VoucherNumberAllocator (no reuse, even with a deleted voucher), HawalaReceiptIsConsistent (each case) and StatementExport.

## Done when
Each of the four services has a passing and a refusing test.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `it-06-service-tests`, commit with messages starting `IT-06:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### IT-07 · دليل تشغيل المشروع محلياً وإصلاح php.cmd

- **المجموعة:** شغل IT بحت · **المجال:** تجربة المطوّر · **الأولوية:** متوسطة
- **الجهد المقدّر:** نصف يوم
- **الاعتماديات:** لا يوجد

#### Objective
README.md هو ملف Laravel الافتراضي، وphp.cmd يبحث عن PHP في C:\php85 فقط (php.cmd:6) فيفشل على جهاز PHP فيه في C:\php\8.5.

#### Implementation scope
README بالعربية: المتطلبات، استيراد نسخة قاعدة الاختبار، ملف .env، المستخدم الثاني للقاعدة القديمة، تشغيل الاختبارات. جعل php.cmd يجرب PATH ومسارات شائعة قبل أن يفشل.

#### Acceptance criteria
مطوّر جديد يشغّل المشروع والاختبارات باتباع README فقط.

#### References in the code
- `README.md:1-20`
- `php.cmd:6-10`

#### Claude prompt

````text
## Role and goal
You are implementing improvement IT-07 of the HC Financial Web plan (Local setup guide, and a php.cmd that finds PHP). Branch: `it-07-readme-php-cmd`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
A README in Arabic: requirements, importing the test database copy, `.env`, the legacy database user, running the tests. Make `php.cmd` try the PATH and common folders (`C:\php\8.5`, `C:\php85`, Herd) before it fails.

## Done when
A new developer runs the app and the tests by following the README alone.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `it-07-readme-php-cmd`, commit with messages starting `IT-07:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### IT-08 · تقليل حجم ملفات الواجهة والخطوط

- **المجموعة:** شغل IT بحت · **المجال:** الأداء · **الأولوية:** منخفضة
- **الجهد المقدّر:** نصف يوم
- **الاعتماديات:** لا يوجد

#### Objective
ملف CSS المبني 450KB، وخطوط Lato بصيغة TTF (حوالي 220KB) بينما خط Somar بصيغة woff2 (public/build).

#### Implementation scope
تحويل Lato إلى woff2 وتحميل الأوزان المستخدمة فقط، ومراجعة ما يدخل CSS من Flux.

#### Acceptance criteria
حجم الخطوط والـ CSS المنقول عند أول فتح أقل بـ 40% على الأقل.

#### References in the code
- `public/build/assets/app-*.css (450KB)`
- `public/build/assets/Lato-*.ttf`

#### Claude prompt

````text
## Role and goal
You are implementing improvement IT-08 of the HC Financial Web plan (Smaller CSS and fonts). Branch: `it-08-assets`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Convert Lato to woff2 and load only the weights in use; review what Flux adds to the CSS.

## Done when
The CSS and fonts loaded on the first visit are at least 40% smaller.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `it-08-assets`, commit with messages starting `IT-08:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### IT-09 · فحص صحة يشمل القاعدتين مع تنبيه

- **المجموعة:** شغل IT بحت · **المجال:** التشغيل والنسخ الاحتياطي · **الأولوية:** منخفضة
- **الجهد المقدّر:** نصف يوم
- **الاعتماديات:** لا يوجد

#### Objective
مسار /up موجود (bootstrap/app.php:14) لكنه لا يفحص الاتصال بالقاعدة القديمة، والأمر finance:check-db موجود لكنه لا يعمل تلقائياً.

#### Implementation scope
ربط فحص القاعدتين بمسار /up، وجدولة finance:check-db كل ساعة مع تنبيه عند الفشل.

#### Acceptance criteria
انقطاع القاعدة القديمة يجعل /up يفشل ويصل تنبيه للفريق.

#### References in the code
- `bootstrap/app.php:14`
- `app/Console/Commands/CheckFinanceDatabase.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement IT-09 of the HC Financial Web plan (Health check of both databases, with an alert). Branch: `it-09-health-check`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Make `/up` check both the ledger and the `legacy` connection; schedule `finance:check-db` hourly and alert on failure.

## Done when
When the legacy database is down, `/up` fails and the team is alerted.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `it-09-health-check`, commit with messages starting `IT-09:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### IT-10 · اختبار يضمن تطابق مفاتيح الترجمة

- **المجموعة:** شغل IT بحت · **المجال:** تجربة المطوّر · **الأولوية:** منخفضة
- **الجهد المقدّر:** نصف يوم
- **الاعتماديات:** لا يوجد

#### Objective
14 ملف ترجمة بكل لغة، والمفاتيح متطابقة اليوم، لكن لا يوجد اختبار يمنع نسيان مفتاح عربي في التعديلات القادمة.

#### Implementation scope
اختبار Pest يقارن مفاتيح lang/en وlang/ar ويفشل عند أي نقص.

#### Acceptance criteria
إضافة مفتاح إنكليزي دون مقابله العربي تُفشل الاختبارات.

#### References in the code
- `lang/en/*.php`
- `lang/ar/*.php`
- `tests (no lang parity test)`

#### Claude prompt

````text
## Role and goal
You are implementing improvement IT-10 of the HC Financial Web plan (A test that the en and ar keys match). Branch: `it-10-lang-parity`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
A Pest test that compares every `lang/en` and `lang/ar` file key by key and fails on any gap.

## Done when
Adding an English key without its Arabic one fails the tests.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `it-10-lang-parity`, commit with messages starting `IT-10:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### IT-11 · فحص تلقائي قبل كل commit

- **المجموعة:** شغل IT بحت · **المجال:** تجربة المطوّر · **الأولوية:** منخفضة
- **الجهد المقدّر:** نصف يوم
- **الاعتماديات:** لا يوجد

#### Objective
لا يوجد pre-commit hook، وgitleaks يُشغَّل يدوياً فقط حسب .gitleaks.toml:1.

#### Implementation scope
hook مشترك في المستودع يشغّل Pint على الملفات المعدلة وgitleaks قبل كل commit، مع أمر تثبيت واحد.

#### Acceptance criteria
commit فيه كلمة سر أو تنسيق خاطئ يُرفض محلياً.

#### References in the code
- `.gitleaks.toml:1`
- `no .git/hooks/pre-commit`

#### Claude prompt

````text
## Role and goal
You are implementing improvement IT-11 of the HC Financial Web plan (Checks before every commit). Branch: `it-11-pre-commit`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
A shared pre-commit hook in the repository that runs Pint on the changed files and gitleaks, with a one-line install command.

## Done when
A commit with a secret or wrong formatting is refused locally.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `it-11-pre-commit`, commit with messages starting `IT-11:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### AI-01 · إكمال أساس الذكاء الاصطناعي: حد التكلفة وسجلها

- **المجموعة:** الذكاء الاصطناعي (يحتاج موافقة الإدارة) · **المجال:** الذكاء الاصطناعي · **الأولوية:** عالية
- **الجهد المقدّر:** 1.5 أيام
- **الاعتماديات:** لا يوجد
- **الحالة:** مبنية جزئياً، والباقي في الهدف أدناه

#### Objective
المساعد موجود (config/assistant.php، AnthropicGateway، GeminiGateway): بلا مفتاح لا يتصل بشيء، وفيه حد للرسائل في الدقيقة وحد لجولات الجواب وسجل للمحادثات. ينقصه حد شهري للتكلفة وسجل لتكلفة كل طلب.

#### Implementation scope
حد شهري للتكلفة يوقف الطلبات عند بلوغه. سجل لكل طلب: المستخدم، الميزة، الرموز الداخلة والخارجة، التكلفة، دون محتوى المستندات. مفتاح تشغيل وإيقاف لكل ميزة ذكاء اصطناعي في شاشة إعدادات لـ Head Office. ولا تُشغَّل ميزة جديدة قبل موافقة الإدارة على ما يُرسل.

#### Acceptance criteria
عند بلوغ الحد الشهري يرفض المساعد برسالة واضحة، وتظهر تكلفة كل طلب في السجل.

#### References in the code
- `composer.json (no AI package)`
- `.env.example`

#### Claude prompt

````text
## Role and goal
You are implementing improvement AI-01 of the HC Financial Web plan (Finish the AI foundation: cost cap and cost log). Branch: `ai-01-cost-controls`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
The assistant already exists (`config/assistant.php`, `AnthropicGateway`, `GeminiGateway`): without a key it calls nothing, and it has a per-minute limit, a cap on rounds per answer and a kept conversation log. Add: a monthly cost cap that stops calls when reached; a log of each call (user, feature, input and output tokens, cost) without document content; an on/off switch per AI feature on a settings screen for Head Office. Management must approve what data is sent before any new AI feature is switched on.

## Done when
When the monthly cap is reached the assistant refuses with a clear message, and each call's cost shows in the log.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `ai-01-cost-controls`, commit with messages starting `AI-01:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### AI-02 · قراءة الفاتورة المرفقة واقتراح حقول القيد

- **المجموعة:** الذكاء الاصطناعي (يحتاج موافقة الإدارة) · **المجال:** الذكاء الاصطناعي · **الأولوية:** عالية
- **الجهد المقدّر:** 3 أيام
- **الاعتماديات:** AI-01

#### Objective
المحاسب يقرأ كل فاتورة ويكتب تاريخها ومبلغها ورقمها يدوياً، والمرفقات بالآلاف. رفع المرفقات موجود أصلاً (app/Services/Ledger/VoucherAttachments.php)، فيمكن قراءة الملف نفسه.

#### Implementation scope
زر «اقرأ الفاتورة» بجانب المرفق. يرسل الصورة أو PDF ويطلب نتيجة بصيغة محددة: التاريخ، المبلغ، العملة، نوع المستند ورقمه، المورّد، وصف بالعربية، وبند من بنود المشروع فقط. تظهر النتيجة كبطاقة اقتراح القيد الموجودة في المساعد (EntryProposals، ProposeEntry)، والفاتورة ظاهرة بجانبها. لا يُحفظ شيء قبل أن يضغط المحاسب «حفظ».

#### Acceptance criteria
على 20 فاتورة حقيقية يُقاس عدد الحقول الصحيحة، ولا يمكن حفظ قيد من الاقتراح دون تأكيد المحاسب.

#### References in the code
- `app/Services/Ledger/VoucherAttachments.php`
- `resources/views/livewire/single-entry.blade.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement AI-02 of the HC Financial Web plan (Read an attached invoice and propose the entry). Branch: `ai-02-invoice-reading`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
From an attached invoice image or PDF, ask the model for a structured result (date, amount, currency, document type and number, supplier, Arabic description, and a budget line from the project's own lines only) and show it as the assistant's entry proposal card (`EntryProposals`, `ProposeEntry`). Nothing is saved until the accountant presses Save; the invoice stays visible beside the fields.

## Done when
On 20 real invoices the correct fields are counted, and no entry is saved from a proposal without the accountant.

## Preconditions
These must be merged first: AI-01. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `ai-02-invoice-reading`, commit with messages starting `AI-02:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### AI-03 · تنبيهات ذكية على شاشة الاعتماد

- **المجموعة:** الذكاء الاصطناعي (يحتاج موافقة الإدارة) · **المجال:** الذكاء الاصطناعي · **الأولوية:** متوسطة
- **الجهد المقدّر:** 3 أيام
- **الاعتماديات:** AI-01

#### Objective
المعتمد يرى السند كما هو، ولا شيء يلفت نظره لسند مكرر أو سعر صرف غريب قبل أن يعتمد.

#### Implementation scope
علامات تحذير بجانب السند المعلق: سند مشابه بنفس المبلغ والحساب خلال أيام، سعر صرف بعيد عن متوسط الشهر، مبلغ أكبر من المتبقي في البند، وصف لا يناسب البند. أغلب هذه الفحوص قواعد عادية بلا ذكاء اصطناعي، والذكاء الاصطناعي يكتب سطراً بالعربية يشرح سبب التنبيه. التنبيه لا يمنع الاعتماد.

#### Acceptance criteria
سند مكرر في بيانات الاختبار يظهر عليه تنبيه مع سبب واضح، ويبقى الاعتماد ممكناً.

#### References in the code
- `app/Livewire/Approval.php`
- `resources/views/livewire/vouchers.blade.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement AI-03 of the HC Financial Web plan (Smart warnings on the approval screen). Branch: `ai-03-approval-hints`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Show warnings on a pending voucher: a similar voucher (same amount and account within a few days), a rate far from the month's average, an amount above what is left on the budget line, a description that does not fit the line. Most are plain rules; the model only writes one Arabic sentence explaining a warning. Warnings never block approval.

## Done when
A duplicate voucher in the test data shows a warning with its reason, and approval stays possible.

## Preconditions
These must be merged first: AI-01. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `ai-03-approval-hints`, commit with messages starting `AI-03:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### AI-04 · أجوبة المساعد: ربط كل رقم بتقريره

- **المجموعة:** الذكاء الاصطناعي (يحتاج موافقة الإدارة) · **المجال:** الذكاء الاصطناعي · **الأولوية:** متوسطة
- **الجهد المقدّر:** يوم
- **الاعتماديات:** لا يوجد
- **الحالة:** مبنية جزئياً، والباقي في الهدف أدناه

#### Objective
المساعد بناه Mai (AssistantPanel وأدوات app/Services/Assistant/Tools): يجيب من أدوات للقراءة فقط ضمن صلاحيات الشخص. ما زال الجواب لا يربط الرقم بالتقرير الذي أُخذ منه.

#### Implementation scope
كل جواب يذكر شاشة التقرير التي أُخذت منها أرقامه مع رابط لها. واختبار يثبت أن مدير المشروع لا يحصل على أرقام مشروع ليس له عبر أي أداة.

#### Acceptance criteria
كل جواب فيه رابط لتقريره، واختبار النطاق ينجح.

#### References in the code
- `app/Services/Ledger/TrialBalance.php`
- `app/Services/Ledger/ProjectBalances.php`
- `app/Services/Ledger/AccountStatement.php`
- `app/Services/Ledger/OperationalReports.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement AI-04 of the HC Financial Web plan (Assistant answers: link each number to its report). Branch: `ai-04-assistant-links`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
The assistant panel (`AssistantPanel`, tools under `app/Services/Assistant/Tools`) already answers from read-only tools within the person's permissions. Remaining: every answer names and links the report screen its numbers come from, and a test proves a project manager cannot get another project's numbers through any tool.

## Done when
Each answer carries a link to its report, and the scope test passes.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `ai-04-assistant-links`, commit with messages starting `AI-04:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### AI-05 · ملخص شهري مكتوب للمدير المالي

- **المجموعة:** الذكاء الاصطناعي (يحتاج موافقة الإدارة) · **المجال:** الذكاء الاصطناعي · **الأولوية:** منخفضة
- **الجهد المقدّر:** يومان
- **الاعتماديات:** AI-01، U-01

#### Objective
المدير المالي يجمع صورة الشهر من عدة تقارير بنفسه.

#### Implementation scope
في نهاية كل شهر يُنشأ ملخص بالعربية من أرقام التقارير: أكبر الحركات، المصاريف حسب المشروع، البنود القريبة من نهايتها، والسندات المعلقة. يعمل كمهمة مجدولة بالدفعات (أرخص)، ويصل كإشعار مع رابط للصفحة.

#### Acceptance criteria
يصل الملخص تلقائياً أول كل شهر، وكل رقم فيه مطابق للتقرير المأخوذ منه.

#### References in the code
- `routes/console.php`
- `app/Services/Ledger`

#### Claude prompt

````text
## Role and goal
You are implementing improvement AI-05 of the HC Financial Web plan (A written monthly summary for the Financial Manager). Branch: `ai-05-monthly-summary`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
At the end of each month a scheduled job writes an Arabic summary from the report numbers (largest movements, spending by project, budget lines near their end, pending vouchers) through the Batch API, and notifies the Financial Manager (U-01) with a link.

## Done when
The summary arrives on the 1st of each month, and every number in it matches its report.

## Preconditions
These must be merged first: AI-01, U-01. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `ai-05-monthly-summary`, commit with messages starting `AI-05:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

## المرحلة 10: طلبات المدير المالي وإجراء الخزينة (F) · 2026-10-30

من ملاحظات المدير المالي (الطلبات 01 إلى 05) ومن مقارنة `CHC-Treasury-Procedure` بالبرنامج. التفاصيل والقرارات المطلوبة في `docs/REVIEW-2026-10-30-treasury-procedure.ar.md`. المهام F-03 وF-05 وF-06 وF-11 تحتاج قراراً قبل البدء. الفرع `f-NN-…`، ورسائل الـ commit تبدأ برقم المهمة.


### F-01 · التنقل بين صفحات الجداول (طلب 01)

- **المجموعة:** طلبات المدير المالي وإجراء الخزينة · **المجال:** الجداول · **الأولوية:** عالية
- **الجهد المقدّر:** نصف يوم
- **الاعتماديات:** لا يوجد
- **الحالة:** خالصة ومدموجة في main

#### Objective
المدير المالي أبلغ أن التنقل بين صفحات القوائم الطويلة لا يعمل، مثل دليل الحسابات. على النسخة الحالية من main يعمل التالي والسابق في دليل الحسابات (1–10 ثم 11–20 من 169)، فالأرجح أن الجهاز الذي جُرّب عليه يحمل JavaScript قديماً (مجلد public/build لا يُرفع إلى git وإصلاح مي للترقيم كان في 28 تشرين الأول)، أو أن الخلل يظهر فقط بعد تحديث Livewire (بحث أو فلتر).

#### Implementation scope
إعادة التجربة بعد npm run build على الجهاز الذي ظهر فيه الخلل. ثم تجربة الترقيم بعد البحث وتغيير الفلتر وبعد wire:navigate في دليل الحسابات وكل جدول يستعمل x-ui.scroll-table. إن ظهر الخلل يُصلَّح في resources/js/paged-table.js، ويُضاف اختبار متصفح (أو اختبار JS) يغطي: صفحة تالية، فلتر يعيد الترقيم إلى الصفحة الأولى، وصفحة بعد التنقل.

#### Acceptance criteria
التالي والسابق يعملان في كل جدول فيه أكثر من صفحة، قبل البحث وبعده وبعد الانتقال بين الشاشات.

#### References in the code
- `resources/js/paged-table.js`
- `resources/views/components/ui/scroll-table.blade.php`
- `resources/views/livewire/chart.blade.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement F-01 of the HC Financial Web plan (Table paging across pages (request 01)). Branch: `f-01-table-paging`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
The Financial Manager reports that paging through long lists does not work, for example the chart of accounts. On current main, Next/Prev on the chart list works in a browser (1–10, then 11–20 of 169), so first rebuild the assets (`npm run build`; `public/build` is not in git) on the machine where it failed and retry. Then test paging after a search, after a filter change, and after arriving with `wire:navigate`, on the chart and every table that uses `x-ui.scroll-table`. Fix `resources/js/paged-table.js` if any case fails (a filter should return to page 1; a Livewire morph must keep the current page valid). Add an automated check of these cases.

## Done when
Next and Prev work on every multi-page table, before and after a search or filter and after navigating between screens.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `f-01-table-paging`, commit with messages starting `F-01:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### F-02 · مكان واحد للعمليات المالية (طلب 02)

- **المجموعة:** طلبات المدير المالي وإجراء الخزينة · **المجال:** الإدخال · **الأولوية:** عالية
- **الجهد المقدّر:** 6 أيام
- **الاعتماديات:** لا يوجد
- **الحالة:** مبنية جزئياً، والباقي في الهدف أدناه

#### Objective
اليوم هناك ثلاثة أماكن للإدخال: الإدخال المفرد (خمسة أنواع: إيراد، مصروف، صرف، سلفة، استرداد)، ومنشئ السندات (أسطر مدين ودائن تُكتب يدوياً)، والإدخال المتعدد (لصق نص مفصول بـ Tab). ويوجد إدخال آخر في شاشة التحويلات وشاشة الرواتب وشاشة القروض. المدير المالي يريد مكاناً واحداً من القائمة العلوية، تختار فيه نوع العملية فتظهر لك قوائم تناسبها، والبرنامج يكتب القيد وحده.

#### Implementation scope
شاشة «العمليات» في القائمة العلوية تحل محل الإدخال المفرد ومنشئ السندات والإدخال المتعدد في القائمة (العناوين القديمة تحوّل إليها). أولاً تختار النوع: CRV استلام، CPV دفع، TRF تحويل بين صناديق أو بين مشاريع، ALC تخصيص من الحساب العام إلى مشروع، EXG تصريف عملة، JV قيد يدوي. لكل نوع نموذج موجّه: الحقول والقوائم تتغير حسب النوع (مثلاً في CPV: المشروع ثم البند ثم الصندوق، والحساب يُملأ من البند)، والقيد المزدوج يُولَّد تلقائياً ويُعرض للمراجعة قبل الحفظ. لا تُختار الحسابات يدوياً إلا في JV، وJV للمدير المالي والإدارة فقط. تُستعمل الخدمات الموجودة: OperationEntry وTransferBuilder وSalaryAllocation وMicroLoans، فلا تتكرر قواعد الترحيل. المسودات (U-04) والاختصارات (U-07) تعمل فيها.

#### Acceptance criteria
كل عملية من الأنواع الستة تُدخل من مكان واحد باختيار من قوائم، والقيد يُكتب تلقائياً ويُعرض قبل الحفظ، ولا يحتاج المستخدم إلى اختيار مدين ودائن إلا في القيد اليدوي.

#### References in the code
- `app/Livewire/SingleEntry.php`
- `app/Livewire/VoucherBuilder.php`
- `app/Livewire/MultiEntry.php`
- `app/Services/Ledger/OperationEntry.php`
- `app/Services/Ledger/TransferBuilder.php`
- `app/Enums/VoucherType.php`
- `config/navigation.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement F-02 of the HC Financial Web plan (One place for financial operations (request 02)). Branch: `f-02-operations-hub`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Build one Operations screen in the top menu that replaces Single entry, the Voucher builder and Multi entry in the navigation (their old addresses redirect to it). Step 1: pick the type: CRV (receipt), CPV (payment), TRF (move cash between tills of one currency, or between projects, with the in-transit accounts 11x3/11x4), ALC (allocation from a city general account to a project, see F-03), EXG (currency exchange), JV (manual entry). Step 2: a guided form per type whose fields and lists depend on the type, e.g. CPV asks project → budget line (fills the expense account) → till → amount → paid to; the person never picks a debit or credit account except in JV. Step 3: show the generated lines (Dr/Cr, owner, USD value) read-only for review, then save as pending. JV is limited to the Financial Manager and Head Office. Reuse the existing builders (`OperationEntry`, `TransferBuilder`, `SalaryAllocation`, `MicroLoans`, `VoucherDraftBuilder`) and the posting rules; do not duplicate them. Keep drafts (U-04) and shortcuts (U-07) working. Depends on the ALC meaning decided in F-03. Status 2026-10-30: the Operations screen is built (`App\Livewire\Operations`, path `entries/operations`, permission key `single`) with CRV, CPV, TRF (project to project, project back to general), ALC, EXG and JV. Left: TRF between two tills of one currency through the in-transit accounts (no builder yet); ALC and EXG still save as TRF with their transfer kind until F-03; Multi entry stays in the menu until F-04 replaces it; the voucher builder itself is still open to accountants (only the JV card is limited).

## Done when
Each of the six types is entered from one screen by picking from lists; the lines are written automatically and shown before saving; only JV asks for debit and credit accounts.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `f-02-operations-hub`, commit with messages starting `F-02:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### F-03 · نوع سند EXG مستقل للتصريف (ALC يبقى للرواتب)

- **المجموعة:** طلبات المدير المالي وإجراء الخزينة · **المجال:** الإدخال · **الأولوية:** عالية
- **الجهد المقدّر:** 1.5 أيام
- **الاعتماديات:** F-02

#### Objective
المدير المالي ذكر EXG بين أنواع السندات الستة، والتصريف اليوم يُحفظ كسند TRF من نوع local_exchange. وقرّر (R5-1، 2026-10-30) أن ALC = الرواتب كما في R1-2، فيبقى التخصيص من الحساب العام إلى المشروع TRF.

#### Implementation scope
نوع سند EXG جديد للتصريف تملكه حسابات المدينة العامة، والفرق إلى 5503 تحت الحساب العام كما اليوم. شاشة العمليات والقالب يحفظان التصريف كـ EXG. السندات المحفوظة سابقاً تبقى بنوعها. لا تغيير على ALC ولا على التخصيص.

#### Acceptance criteria
التصريف يُحفظ كسند EXG، وقواعد الترحيل (الفرق إلى 5503 تحت الحساب العام، متوسط الكلفة) تبقى صحيحة.

#### References in the code
- `app/Enums/VoucherType.php`
- `app/Enums/TransferKind.php`
- `app/Services/Ledger/TransferBuilder.php`
- `app/Services/Payroll/SalaryAllocation.php`
- `app/Services/Bridge/PostingRuleCatalogue.php`
- `docs/DECISIONS.md`

#### Claude prompt

````text
## Role and goal
You are implementing improvement F-03 of the HC Financial Web plan (EXG as its own voucher type for exchange (ALC stays payroll)). Branch: `f-03-exchange-voucher`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Decision R5-1 (docs/DECISIONS.md): ALC stays payroll (the salary split, `SalaryAllocation`) and the allocation from a city general account to a project stays TRF with its sub-type. Add a voucher type EXG for currency exchange: the city general account buys SYP (today `TransferKind::LocalExchange`, saved as TRF), with the difference to 5503 under the general account as today. The Operations screen and the Excel template (F-04) save an exchange as EXG. Saved and approved vouchers keep their type; add a migration only for the enum value. Update the posting-rule catalogue, the voucher numbering (EXG-YYYY-NNNN) and the bridge pages.

## Done when
An exchange saves as EXG, and the existing rules (difference to 5503 under the general account, average cost) still hold.

## Preconditions
These must be merged first: F-02. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `f-03-exchange-voucher`, commit with messages starting `F-03:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### F-04 · قالب Excel يولّده البرنامج لإدخال عدة عمليات

- **المجموعة:** طلبات المدير المالي وإجراء الخزينة · **المجال:** الإدخال · **الأولوية:** عالية
- **الجهد المقدّر:** 3 أيام
- **الاعتماديات:** F-02

#### Objective
الإدخال المتعدد اليوم لصق نص مفصول بـ Tab بأعمدة يجب حفظ ترتيبها، ولا يوجد قالب يُنزَّل ولا رفع ملف. المدير المالي يريد من داخل شاشة العمليات تنزيل نموذج Excel يولّده البرنامج، تُعبّأ فيه العمليات ثم يُرفع.

#### Implementation scope
في شاشة العمليات زر «تنزيل قالب» لكل نوع (أو قالب عام فيه عمود النوع): ملف XLSX فيه ورقة الإدخال بأعمدة النوع وأسماء عربية وإنكليزية، وورقة «قوائم» فيها المشاريع والبنود والصناديق والحسابات المسموحة لهذا المستخدم. ثم «رفع الملف»: يقرأ البرنامج الأسطر، ويعرض معاينة فيها كل سطر مع القيد الذي سيُكتب والأخطاء بجانب كل سطر، ولا يحفظ إلا إذا صحت كل الأسطر (كما اليوم: الكل أو لا شيء، حتى 500 سطر). يُستعمل OpenSpout الموجود في المشروع.

#### Acceptance criteria
المستخدم ينزّل قالباً، يعبّئه، يرفعه، يرى المعاينة والأخطاء لكل سطر، ثم يحفظ كل العمليات دفعة واحدة.

#### References in the code
- `app/Services/Ledger/OperationSheet.php`
- `app/Livewire/MultiEntry.php`
- `app/Services/Ledger/StatementExport.php`
- `app/Services/Projects/BudgetSheetReader.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement F-04 of the HC Financial Web plan (Program-generated Excel template for many operations). Branch: `f-04-excel-template`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
In the Operations screen (F-02) add Download template (XLSX, per type or one sheet with a Type column) and Upload. The template has the entry sheet with bilingual headers and a Lists sheet holding the projects, budget lines, tills and accounts this person may use (data scope). Upload parses the rows with OpenSpout (already installed), shows a preview of every row with the lines it will post and its errors next to it, and saves only when every row is valid, in one transaction, up to `OperationSheet::MAX_ROWS`. Keep the paste box as a fallback. Reuse `OperationSheet` for validation and posting.

## Done when
A person downloads the template, fills it, uploads it, sees each row with its lines and errors, and saves all rows at once.

## Preconditions
These must be merged first: F-02. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `f-04-excel-template`, commit with messages starting `F-04:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### F-05 · صافي الأصول محسوب تلقائياً، والأرصدة الافتتاحية وإقفال السنة (طلب 03)

- **المجموعة:** طلبات المدير المالي وإجراء الخزينة · **المجال:** القوائم المالية · **الأولوية:** عالية
- **الجهد المقدّر:** 3 أيام
- **الاعتماديات:** لا يوجد
- **الحالة:** مبنية جزئياً، والباقي في الهدف أدناه

#### Objective
لا يُرحَّل أي سطر تلقائياً إلى حسابات صافي الأصول 3101 و3201 و3901، فرصيدها في دليل الحسابات وميزان المراجعة صفر، وهذا يربك القارئ. قائمة المركز المالي تحسب «نتيجة غير مقفلة» = الإيرادات − المصاريف وتضيفها، فالمعادلة الأصول = الالتزامات + صافي الأصول متوازنة فعلاً، لكن لا يوجد عرض واضح لصافي الأصول = الأصول − الالتزامات، ولا تقسيم بين المقيد وغير المقيد، ولا إقفال سنة، ولا أرصدة افتتاحية (شاشة opening ما زالت placeholder). وVoucherBuilder يسمح بالترحيل اليدوي إلى 3xxx.

#### Implementation scope
في دليل الحسابات وميزان المراجعة والمركز المالي: سطر «صافي الأصول» محسوب = الأصول − الالتزامات، مقسوم إلى بلا قيود مانحين (3100) ومقيد من المانحين (3200) حسب قيد المشروع، مع ملاحظة تشرح أنه محسوب. منع الترحيل اليدوي إلى 3xxx إلا عبر سند الأرصدة الافتتاحية (OPN) وسند إقفال السنة. شاشة الأرصدة الافتتاحية (لمرة واحدة عند الانتقال، والفرق إلى 3901 ويجب أن يكون صفراً). إقفال السنة يولّد سند JV يغلق الإيرادات والمصاريف إلى 3101/3201.

#### Acceptance criteria
صافي الأصول يظهر في كل مكان مساوياً للأصول ناقص الالتزامات بعد كل عملية، دون أي إدخال يدوي.

#### References in the code
- `app/Services/Ledger/FinancialStatements.php`
- `app/Services/Ledger/TrialBalance.php`
- `app/Services/Chart/AccountBalances.php`
- `database/seeders/data/chart_accounts.json`
- `app/Livewire/VoucherBuilder.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement F-05 of the HC Financial Web plan (Net assets computed automatically, opening balances and year-end close (request 03)). Branch: `f-05-net-assets`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Show net assets as computed, not posted: in the chart of accounts, the trial balance and the statement of financial position, a Net assets line = assets − liabilities from approved vouchers, split into without donor restrictions (3100) and with donor restrictions (3200) by the project's restriction, with a note that it is computed. Refuse manual lines on 3xxx accounts in every entry screen except an opening-balance voucher (OPN) and the year-end closing voucher. Build the opening-balances screen (the `opening` slug is a placeholder): one OPN voucher per city at go-live, the difference to 3901, which must be zero. Add a year-end close that writes a JV closing income and expenses into 3101/3201 per project restriction, and is refused while the year has pending vouchers. Use bcmath. Status 2026-10-30: net assets are computed (`App\Services\Ledger\NetAssets`): total = assets − liabilities, with donor restrictions = income − expenses of restricted lines (the measure ProjectBalances uses), shown on the chart (3000/3100/3200 marked computed), the trial balance and the statements. Rule 16 (`NetAssetsAreComputed`) refuses web lines on 3xxx accounts; migrated vouchers keep theirs. Left: the opening-balances screen (overlaps plan task 34, `opening`) and the year-end close, which waits for the Financial Manager's decision.

## Done when
Net assets equal assets minus liabilities everywhere after every operation, with no manual entry.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `f-05-net-assets`, commit with messages starting `F-05:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### F-06 · مستخدم Super User للاختبار (طلب 04)

- **المجموعة:** طلبات المدير المالي وإجراء الخزينة · **المجال:** الصلاحيات · **الأولوية:** متوسطة
- **الجهد المقدّر:** 1.5 أيام
- **الاعتماديات:** لا يوجد

#### Objective
لا يوجد دور يستطيع كل شيء. الإدارة (Head Office) ترى كل الشاشات وكل الإجراءات وبلا حد للبيانات، لكنها لا تأخذ دور المدير العام في سلسلة الاعتماد، ودور مدير البرنامج في الطلبات دور مستقل، ولا أحد يعتمد ما أعدّه (القاعدة 7)، ولا يأخذ الشخص نفسه خطوتين.

#### Implementation scope
دور ويب جديد «Super User» يُمنح من شاشة المستخدمين، ويعمل فقط عندما يكون FINANCE_SUPER_USER=true في .env، ويُرفض تلقائياً إذا APP_ENV=production. يرى كل الشاشات وكل الإجراءات وكل البيانات، ويأخذ أي خطوة في سلسلة الاعتماد (مدير البرنامج، المدير المالي، المدير العام)، ويستطيع اعتماد ما أعدّه بنفسه أثناء الاختبار. كل إجراء يقوم به يُسجَّل في سجل التدقيق مع علامة «super». يظهر شريط تحذير في أعلى الشاشة أثناء الدخول به.

#### Acceptance criteria
مستخدم واحد يجرّب كل دورة عمل من الإدخال إلى الاعتماد النهائي، والميزة مطفأة تلقائياً على الإنتاج.

#### References in the code
- `app/Enums/RoleId.php`
- `config/permissions.php`
- `app/Models/User.php`
- `app/Policies/ApprovalPolicy.php`
- `app/Policies/RequestPolicy.php`
- `app/Services/Approvals/ApprovalChain.php`
- `app/Services/Ledger/Rules/ApproverIsNotPreparer.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement F-06 of the HC Financial Web plan (Super User for testing (request 04)). Branch: `f-06-super-user`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Add a web-only role SuperUser (next free RoleId, stored in `web_user_roles`, assignable on the Users screen by someone with users.delete). It is active only when `FINANCE_SUPER_USER=true` and never when `APP_ENV=production`. While active it can open every screen with every action, sees all data (no `LegacyDataScope` limit), may take any approval step (Program Manager, Financial Manager, General Manager) in `ApprovalChain`, `ApprovalPolicy` and `RequestPolicy`, and may approve its own voucher or request (bypassing rule 7 `ApproverIsNotPreparer` only for this role). Every action it takes is audited with a `super` marker, and a warning band shows at the top of every page while signed in as it. Tests: the role does nothing when the flag is off or in production.

## Done when
One user can walk every workflow from entry to final approval, and the feature is off by itself in production.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `f-06-super-user`, commit with messages starting `F-06:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### F-07 · الطلب الشهري: «إعادة للتعديل» والتخصيص التلقائي بعد الاعتماد

- **المجموعة:** طلبات المدير المالي وإجراء الخزينة · **المجال:** الطلبات · **الأولوية:** عالية
- **الجهد المقدّر:** 2.5 أيام
- **الاعتماديات:** لا يوجد

#### Objective
إجراء الخزينة يقول إن المراجع يستطيع إعادة الطلب للتعديل، وإن الاعتماد النهائي يولّد التخصيص تلقائياً (الليرة من الحساب العام للمدينة إلى المشروع، والدولار إلى صندوق الدولار). اليوم حالات الطلب: مسودة، مقدّم، معتمد، مرفوض، مغلق، ولا يوجد «أعيد للتعديل»، والاعتماد يغيّر الحالة فقط والتخصيص يُحضَّر يدوياً من شاشة التحويلات.

#### Implementation scope
حالة جديدة «أعيد للتعديل» مع سبب، يرجع بها الطلب لمقدّمه ليعدّله ويعيد تقديمه، وتُسجَّل في سجل التدقيق وتصل كإشعار. وعند الاعتماد النهائي يُولَّد تلقائياً سند تخصيص بانتظار الاعتماد: مجموع الليرة من الحساب العام للمدينة إلى المشروع بسعر المدينة المعتمد، ومجموع الدولار كذلك، والمشروع يدفع قيمة الدولار من حصته في 1161 (كما TransferBuilder اليوم)، ويرتبط السند بالطلب.

#### Acceptance criteria
المراجع يعيد الطلب للتعديل بسبب، والاعتماد النهائي يُنشئ سند التخصيص تلقائياً مربوطاً بالطلب.

#### References in the code
- `app/Services/Requests/RequestWorkflow.php`
- `app/Enums/RequestStatus.php`
- `app/Services/Ledger/TransferBuilder.php`
- `app/Models/AllocationRate.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement F-07 of the HC Financial Web plan (Monthly request: return for changes, and automatic allocation on approval). Branch: `f-07-request-flow`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Add a request status `returned` (with a reason) that sends the request back to its requester to edit and resubmit; audit it and notify (U-01). On final approval in `RequestWorkflow::approve`, create a pending allocation voucher automatically from the approved lines: the SYP total from the city general account to the project at the approved city rate, and the USD total likewise, with the project repaying the dollar value from its 1161 share, exactly as `TransferBuilder` posts `SypToProject` / `UsdToProject` today; link it with `request_id`. If no approved city rate exists, keep the request approved and show what is missing. Migration for the status only.

## Done when
A reviewer can return a request with a reason, and the final approval creates the allocation voucher linked to the request.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `f-07-request-flow`, commit with messages starting `F-07:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### F-08 · شاشة الصناديق: رصيد كل صندوق موزّعاً على أصحابه

- **المجموعة:** طلبات المدير المالي وإجراء الخزينة · **المجال:** الخزينة · **الأولوية:** متوسطة
- **الجهد المقدّر:** يومان
- **الاعتماديات:** لا يوجد

#### Objective
القاعدة الأساسية في الإجراء: رصيد الصندوق = مجموع حصص المالكين (المشاريع + الحساب العام). البرنامج يسجّل المالك في كل سطر، لكن شاشة الصناديق (tills) ما زالت placeholder، وصفحة المشروع تقرأ أرصدة البرنامج القديم.

#### Implementation scope
شاشة الصناديق: لكل صندوق رصيده من السندات المعتمدة، وتحته قائمة الحسابات العامة والمشاريع التي تملك منه، ومجموعها يساوي الرصيد، مع قيمة كل سطر بالدولار. وصفحة المشروع تعرض حصته من كل صندوق من دفتر الويب.

#### Acceptance criteria
كل صندوق يظهر مع أصحابه ومجموع الحصص يساوي رصيده تماماً.

#### References in the code
- `app/Services/Ledger/ProjectBalances.php`
- `app/Services/Ledger/TreasuryPosition.php`
- `app/Livewire/ProjectPage.php`
- `routes/web.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement F-08 of the HC Financial Web plan (Tills screen: each till split by its owners). Branch: `f-08-till-owners`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Build the `tills` screen from approved ledger lines: one block per cash account with its balance, and under it every general account and project that owns part of it, with the USD value of each row; the rows must add up to the till balance exactly (bcmath), and any difference is shown as unowned. Respect data scope. On the project page, show the project's share of each till from the ledger (`ProjectBalances::fundLocations`) next to the legacy figures.

## Done when
Every till shows its owners, and the shares add up to its balance exactly.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `f-08-till-owners`, commit with messages starting `F-08:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### F-09 · إقفال الشهر والمطابقات

- **المجموعة:** طلبات المدير المالي وإجراء الخزينة · **المجال:** الخزينة · **الأولوية:** متوسطة
- **الجهد المقدّر:** 3 أيام
- **الاعتماديات:** F-08

#### Objective
الإجراء يطلب في آخر الشهر: رصيد الصندوق = الجرد النقدي، و1161 = كشف شركة الحوالات، و1171/1172 = كشف البنك، و2901 صفر. في الكود قاعدة الإقفال 12 (حسابات الطريق و2901 صفر) فقط، ولا مكان لإدخال الجرد أو رصيد الكشف، ولا شاشة إقفال.

#### Implementation scope
شاشة إقفال الشهر لكل مدينة: إدخال الجرد النقدي لكل صندوق، ورصيد كشف شركة الحوالات لـ 1161، ورصيد كشف البنك لـ 1171/1172، فيظهر الفرق بجانب كل منها. وفحص حسابات الطريق و2901. الإقفال يُسمح فقط عندما تكون الفروق صفراً أو مشروحة، وبعده لا يُقبل سند بتاريخ داخل الشهر المقفل (القاعدة 12).

#### Acceptance criteria
المدير المالي يقفل الشهر بعد مطابقة كل صندوق والكشوفات، ولا يقبل البرنامج بعدها سنداً بتاريخ ذلك الشهر.

#### References in the code
- `app/Services/Ledger/Rules/MonthClosesOnlyWhenClearingIsZero.php`
- `app/Services/Ledger/FollowUpReports.php`
- `config/ledger.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement F-09 of the HC Financial Web plan (Month-end close and reconciliations). Branch: `f-09-month-end`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Build a month-end screen per city: enter the cash count of each safe, the transfer company statement balance for 1161 and the bank statement balance for 1171/1172; show the ledger balance and the difference for each; check that in-transit accounts and 2901 are zero (rule 12). Closing a month is allowed only when every difference is zero or has a written explanation, stores who closed it and when (own table via migration), and afterwards no voucher dated inside the closed month is accepted (wire the existing rule 12). Head Office can reopen with a reason.

## Done when
The Financial Manager closes a month after every till and statement agrees, and no voucher dated in that month is accepted afterwards.

## Preconditions
These must be merged first: F-08. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `f-09-month-end`, commit with messages starting `F-09:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### F-10 · تقرير الموافقات الشهري لكل المشاريع

- **المجموعة:** طلبات المدير المالي وإجراء الخزينة · **المجال:** التقارير · **الأولوية:** متوسطة
- **الجهد المقدّر:** 1.5 أيام
- **الاعتماديات:** F-07

#### Objective
الإجراء يطلب تقريراً شهرياً: الدولار عند شركة الحوالات أول الشهر، ثم المعتمد بالليرة وكلفته بالدولار، ثم المعتمد بالدولار، ثم الحركات الأخرى، ثم الدولار آخر الشهر، مع الليرة أول الشهر وآخره، وتصدير CSV. غير موجود اليوم.

#### Implementation scope
تقرير جديد في مجموعة التقارير يبني هذه الأعمدة لكل مشروع ولكل شهر من السندات المعتمدة والطلبات المعتمدة، مع تصدير CSV وXLSX.

#### Acceptance criteria
التقرير يطابق رصيد 1161 أول الشهر وآخره، ومجموع المعتمد يطابق الطلبات المعتمدة.

#### References in the code
- `app/Services/Ledger/FollowUpReports.php`
- `app/Livewire/FollowUpReport.php`
- `app/Services/Requests/RequestWorkflow.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement F-10 of the HC Financial Web plan (Monthly approvals report). Branch: `f-10-monthly-approvals-report`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Add a report "Monthly approvals" (all projects, one month): USD at the transfer company (1161) at the start of the month → approved in SYP and its USD cost → approved in USD → other movements → USD at the end, plus SYP held at the start and end, per project and in total, from approved vouchers and approved requests. CSV and XLSX export like the other reports.

## Done when
The report agrees with the 1161 balance at the start and end of the month, and its approved totals match the approved requests.

## Preconditions
These must be merged first: F-07. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `f-10-monthly-approvals-report`, commit with messages starting `F-10:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### F-11 · ورقة المصاريف: رقم الفاتورة والمستفيد والمكان، والحساب المعلّق 2901

- **المجموعة:** طلبات المدير المالي وإجراء الخزينة · **المجال:** الإدخال · **الأولوية:** متوسطة
- **الجهد المقدّر:** 1.5 أيام
- **الاعتماديات:** F-02

#### Objective
الإجراء يطلب في كل سطر مصروف: رقم الفاتورة، المستفيد، والمكان. وإن لم يوجد بند يذهب المصروف إلى 2901 (معلّق) حتى يُصحَّح. اليوم لا توجد هذه الأعمدة، ولا يذهب شيء إلى 2901 تلقائياً. والمصروف خارج فترة التمويل تحذير فقط، بينما الإجراء يقول يُرفض.

#### Implementation scope
إضافة رقم الفاتورة والمستفيد والمكان إلى نموذج CPV والقالب (F-04) وورقة المصاريف. وحسب قرار المدير المالي: مصروف بلا بند يُرحَّل إلى 2901 مع تنبيه، وشاشة متابعة لأرصدة 2901 حتى تصفيرها. والمصروف خارج فترة التمويل يُرفض بدل التحذير (أو يبقى تحذيراً، حسب القرار).

#### Acceptance criteria
كل مصروف يحمل رقم فاتورته ومستفيده، والمصروف بلا بند لا يضيع بل يظهر في 2901 حتى يُصحَّح.

#### References in the code
- `app/Services/Ledger/OperationEntry.php`
- `app/Services/Ledger/VoucherWarnings.php`
- `app/Services/Ledger/OperationSheet.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement F-11 of the HC Financial Web plan (Expenses sheet columns and the 2901 suspense account). Branch: `f-11-expense-sheet`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Add bill number, paid to and place to the CPV form, the Excel template (F-04) and the expense rows (columns on `gl_voucher_lines` or the voucher, via migration). After the Financial Manager decides (review report): post an expense with no budget line to 2901 Suspense with a warning, list open 2901 lines in a follow-up report until they are cleared; and refuse an expense dated outside the contract's funding period instead of warning (today `VoucherWarnings` only warns, and only on the end date).

## Done when
Every expense carries its bill number and payee, and an expense with no budget line shows in 2901 until it is corrected.

## Preconditions
These must be merged first: F-02. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `f-11-expense-sheet`, commit with messages starting `F-11:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### F-12 · سند عكسي للتصحيح، وتسوية السلف

- **المجموعة:** طلبات المدير المالي وإجراء الخزينة · **المجال:** الإدخال · **الأولوية:** متوسطة
- **الجهد المقدّر:** 1.5 أيام
- **الاعتماديات:** F-02

#### Objective
لا توجد طريقة لتصحيح سند معتمد إلا قيد يدوي. والسلفة اليوم تُصرف (مدين 1201) وتُسترد نقداً فقط، ولا توجد «تسوية سلفة» بفواتير (مدين مصروف / دائن 1201)، وهي من أكثر العمليات اليومية في المنظمات.

#### Implementation scope
زر «عكس السند» على السند المعتمد يولّد سنداً عكسياً مربوطاً به (بانتظار الاعتماد) بنفس الأسطر معكوسة، ثم يُدخل الصحيح. وفي CPV خيار «تسوية سلفة»: تختار الموظف وسلفته المفتوحة، وتدخل الفواتير على البنود، والفرق يُسترد نقداً أو يُدفع.

#### Acceptance criteria
السند الخاطئ يُصحَّح بسند عكسي مربوط به، والسلفة تُسوّى بفواتيرها ويظهر رصيدها المتبقي.

#### References in the code
- `app/Services/Ledger/PostVoucher.php`
- `app/Services/Ledger/OperationEntry.php`
- `app/Services/Ledger/FollowUpReports.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement F-12 of the HC Financial Web plan (Reversal vouchers and advance settlement). Branch: `f-12-reversal-and-settlement`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Add Reverse on an approved voucher: it creates a pending voucher with the same lines on the opposite sides, linked to the original (column via migration), with a reason; the original is never edited. Add "Advance settlement" to CPV in the Operations screen: pick the employee and an open advance (account 1201 by contact), enter the bills on budget lines (Dr expense / Cr 1201), and the difference is repaid in cash (CRV) or paid out (CPV). Show open advances per employee (the `advances` report exists).

## Done when
A wrong voucher is corrected by a linked reversal, and an advance is settled with its bills showing what remains open.

## Preconditions
These must be merged first: F-02. If one is missing, stop and report.

## Git
Start from an up-to-date `main`, create branch `f-12-reversal-and-settlement`, commit with messages starting `F-12:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### F-13 · تقرير المانح: الميزانية مقابل الصرف بعملة المانح

- **المجموعة:** طلبات المدير المالي وإجراء الخزينة · **المجال:** التقارير · **الأولوية:** متوسطة
- **الجهد المقدّر:** يومان
- **الاعتماديات:** لا يوجد

#### Objective
المانح يطلب عادة تقريراً بالبنود: الميزانية، المصروف، المتبقي، بعملة المانح وبالسعر الحقيقي للمشروع. البرنامج يحسب هذا جزئياً للتحذيرات، لكن لا يوجد تقرير جاهز يُرسل للمانح، ولا عرض للمستلم بعملة المانح واليورو.

#### Implementation scope
تقرير لكل مشروع: لكل بند الميزانية والمصروف والمتبقي ونسبة الصرف بعملة المانح والدولار، والمبالغ المستلمة من المانح بعملته، وفترة التمويل، مع تصدير XLSX وPDF بالعربي والإنكليزي.

#### Acceptance criteria
تقرير المشروع يُصدَّر جاهزاً للمانح، وأرقامه تطابق القوائم المالية.

#### References in the code
- `app/Services/Ledger/ProjectBalances.php`
- `app/Services/Ledger/VoucherWarnings.php`
- `app/Services/Ledger/StatementExport.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement F-13 of the HC Financial Web plan (Donor report: budget against actual in the donor currency). Branch: `f-13-donor-report`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Add a donor report per project: for each budget line the budget, actual, remaining and percentage spent, in the donor currency (at the project's real rate from its receipts) and USD; money received from the donor in its currency; the funding period. XLSX and PDF export in English and Arabic (reuse `StatementExport`).

## Done when
A project's report exports ready for the donor, and its figures agree with the financial statements.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `f-13-donor-report`, commit with messages starting `F-13:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---

### F-14 · شاشة سجل التدقيق

- **المجموعة:** طلبات المدير المالي وإجراء الخزينة · **المجال:** الرقابة · **الأولوية:** منخفضة
- **الجهد المقدّر:** 1.5 أيام
- **الاعتماديات:** لا يوجد

#### Objective
كل إجراء على السندات والطلبات يُسجَّل (VoucherAudit وRequestAudit)، والدخول يُسجَّل في جدول log، لكن لا توجد شاشة واحدة تعرض من فعل ماذا ومتى، وهي أول ما يطلبه المدقق.

#### Implementation scope
شاشة للمدير المالي والإدارة: كل الإجراءات مع التاريخ والشخص ونوع الإجراء والسند أو الطلب والسبب، مع فلاتر وتصدير CSV.

#### Acceptance criteria
المدقق يجد كل تغيير على أي سند أو طلب مع صاحبه وتاريخه من شاشة واحدة.

#### References in the code
- `app/Models/VoucherAudit.php`
- `app/Models/RequestAudit.php`
- `app/Listeners/LogWebLogin.php`

#### Claude prompt

````text
## Role and goal
You are implementing improvement F-14 of the HC Financial Web plan (Audit log screen). Branch: `f-14-audit-log`. Do only this task.

## Read before writing any code
1. `CLAUDE.md` and `AGENTS.md`.
2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.
3. The files under References in the code above.

## Common rules
Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\php.cmd` (PHP 8.5). The database `hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or `db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an `ar` key in `lang/`. Finish with `.\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull request. No credentials in the repository.

## Task
Add an audit log screen for the Financial Manager and Head Office: voucher audits, request audits and web logins in one list (date, person, action, voucher or request with a link, reason), with filters by person, action and date and a CSV export. Read-only.

## Done when
An auditor finds every change to any voucher or request, with who and when, on one screen.

## Preconditions
None.

## Git
Start from an up-to-date `main`, create branch `f-14-audit-log`, commit with messages starting `F-14:`, push, merge (pull request preferred), push `main`. Do not delete the branch.
````

---
