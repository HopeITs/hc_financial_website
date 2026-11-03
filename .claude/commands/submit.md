---
description: Send my work (branch, commit, push, pull request). Style-only changes merge by themselves; everything else goes to IT.
---

The user may not be a developer. Do everything below yourself and explain in short, plain language. Avoid git jargon unless something fails.

1. **Role and branch prefix.** Run `node .hc-workflow/check.mjs --whoami` (JSON: `role`, `branchPrefix`). Use that prefix for the branch name.
2. **Check.** Run `git status` and `node .hc-workflow/check.mjs --working`.
   - No changes: say so and stop.
   - Exit code 1 (blocked): do NOT commit or push. Explain the problem in plain language. For files that are not allowed, follow the `/ask-it` steps. For standards problems, fix them with the site components and Tailwind theme classes, then check again.
   - Exit code 0 with "must approve" items: continue, but remember them for step 6.
3. **Branch.** If on `main`, `master` or `staging`, run `git fetch origin` and create `<branchPrefix><short-kebab-summary>` from `origin/main` (or `origin/master`), keeping the working changes. If already on a branch with the right prefix, stay on it. Never switch to or commit on a protected branch.
4. **Build.** If `package.json` has a `build` script, run `npm run build`. If it fails, fix it using only files you are allowed to edit; if you cannot, stop and tell the user IT is needed (follow `/ask-it`). Never run migrations.
5. **Commit and push.** `git add` the changed files, commit with `<role>: <one-line summary>`, then `git push -u origin <branch>`. Never use `--no-verify`. If the guard rejects the push, explain its message in plain language and stop.
6. **Pull request.** Create it with `gh pr create` (base `main`, or `master`) using `.github/pull_request_template.md` as the body. Fill in: what changed in plain language, pages or sections affected, files changed, and a **"Needs IT attention"** section listing every database, migration, existing-backend or auth/route change, with a rollback note. If `gh` is not available, give the user the GitHub "compare" link instead.
7. **Lane.** Run `node .hc-workflow/check.mjs --lane --range origin/main...HEAD` (use `origin/master` if that is the base). It prints `fast` or `review`.
   - `fast` (style-only: style files or class-only Blade edits): merge it yourself with exactly `gh pr merge --auto --squash --delete-branch` (no PR number, no other flags). It merges as soon as the required checks pass, without waiting for IT. Tell the user it is a style-only change that merges by itself.
   - `review` (anything else: routes, new components, controllers, models, migrations, PHP logic...): do not merge. Tell the user IT will review and merge it.
8. **Finish.** Tell the user what was sent, give the link, and what happens next (merged automatically, or waiting for IT). Never merge anything that is not `fast`.
