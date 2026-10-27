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
