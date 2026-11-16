# IT request: Set the cloud session's department to finance (matches roles.json)

| | |
|---|---|
| Requested by | shadi93mk-coder (finance, registered by IT in `.hc-workflow/roles.json`) |
| Department | finance (the cloud session still runs as ct) |
| Date | 2026-11-16 |
| Website / page | Financial website, Claude Code cloud sessions |
| Branch | ct/session-role-finance |
| Status | Waiting for IT |

## 1. What is wanted (plain language)

IT registered `shadi93mk-coder` in the **finance** department, with full file access and self-merge (kit 3.7.0 and 3.8.0, #13).

The **local** guard in the Claude Code cloud session still runs as **ct**:
- `node .hc-workflow/check.mjs --whoami` answers `ct`.
- So it blocks the edits finance is now allowed to make, for example `config/navigation.php` and `config/permissions.php` for request #12.

The local role comes from `HC_WORKFLOW_ROLE` or `git config hcworkflow.role` (`.hc-workflow/lib.mjs`, `localRole`). The guard refuses to let the session change either one itself, and that is right: IT sets it.

**Please set the role to `finance`** for this person's cloud sessions, so that the local guard matches what CI already decides from `roles.json`. Either way works:
- **Environment variable `HC_WORKFLOW_ROLE=finance`** on their Claude Code cloud environment. It takes effect from the next session.
- **Or** IT's own setup step that runs `git config --global hcworkflow.role finance` on that machine.

Until then, request #12 (Funding contracts in the menu, and the donor and contract permissions) stays with IT.

## 2. What was found (read-only)

- `.hc-workflow/roles.json` on `main`: `"shadi93mk-coder": "finance"`.
- `.hc-workflow/config.json` on `main`: finance has `fullAccess: true` and `selfMerge: true`.
- In the cloud session:
  - `--whoami` → `{"role":"ct","branchPrefix":"ct/"}`.
  - Editing `config/navigation.php` is blocked as "protected area (IT only)".
  - `git config hcworkflow.role finance` is blocked as "changing the safety settings".
- Nothing was changed. No workaround was tried.

## 3. Questions for IT (answer BEFORE any work starts)

1. Is this change possible and safe to do? **(Yes / No / Only with changes)**
2. Which way: environment variable on the cloud environment, or IT's machine setup?
3. Does it affect the database or existing data? No.
4. Does it affect other people? Only if the environment is shared. In that case, set it on this person's own environment.
5. If IT prefers not to change the session role: will IT apply #12 directly?
6. Priority / deadline: high. Finance cannot start entering contracts until #12 is done.

## IT answers

| | |
|---|---|
| Decision | Approved / Approved with changes / Rejected |
| Answered by | |
| Date | |
| Implemented by | |
| Pull request | |

<Notes and answers to the questions above:>
