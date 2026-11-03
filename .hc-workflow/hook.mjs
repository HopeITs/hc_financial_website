// Claude Code hook. Usage (from .claude/settings.json):
//   node hook.mjs session   -> SessionStart: activate git hooks, tell Claude the user's role and limits
//   node hook.mjs files     -> PreToolUse Edit/Write: classify the file and its new content
//   node hook.mjs bash      -> PreToolUse Bash: block risky shell commands
// Exit code 2 = block, and stderr is shown to Claude so it can explain to the user.
import { readFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import {
  repoRoot, loadConfig, localRole, classify, contentFindings, addedLines, tryGit, today,
} from './lib.mjs';

const mode = process.argv[2];
let input = {};
try { input = JSON.parse(readFileSync(0, 'utf8')); } catch { /* no stdin */ }

const root = repoRoot(process.env.CLAUDE_PROJECT_DIR || input.cwd || process.cwd());
const cfg = loadConfig(root);
if (!cfg) process.exit(0);
const roleName = localRole(root, cfg);
if (roleName === 'it') process.exit(0);
const role = cfg.roles[roleName];

function block(message) {
  process.stderr.write(`${message}\n`);
  process.exit(2);
}

const askIT = (what) => `
${what}
Do NOT try to work around this (no shell redirects, no other tool, no renamed path).
Instead:
1. Tell the user, in plain non-technical language, that this part needs the IT department.
2. Create an IT request file: ${cfg.itRequestsDir}/${today()}-<short-topic>.md, copied from
   ${cfg.itRequestsDir}/_TEMPLATE.md. Fill in sections 1 and 2 and the "Questions for IT"
   (add any specific questions of your own). Do not change any backend file.
3. Continue with the part of the task that is allowed, if there is one.`;

if (mode === 'session') {
  tryGit(['config', 'core.hooksPath', '.githooks'], root);
  const list = (a) => (a.length ? a.join(', ') : '(none)');
  process.stdout.write(
    `HC Workflow is active. Your role: ${role.label}. Work only on a branch starting with "${role.branchPrefix}", ` +
    `never on main, and never merge (IT merges).\n` +
    `Free to create and edit: ${list(role.free)}\n` +
    `New files only (existing files need ${role.modifyExisting === 'critical' ? 'IT approval' : 'IT'}): ${list(role.addOnly)}\n` +
    `Needs IT approval in the pull request: ${list(role.critical)}\n` +
    `Not allowed: ${list(role.forbidden)}\n` +
    `Follow docs/STANDARDS.md (components + Tailwind). For anything not allowed, use /ask-it.\n`,
  );
  process.exit(0);
}

if (mode === 'files') {
  const ti = input.tool_input ?? {};
  const target = ti.file_path ?? ti.notebook_path;
  if (!target) process.exit(0);

  const abs = path.resolve(input.cwd ?? root, target);
  const rel = path.relative(root, abs).split(path.sep).join('/');
  if (rel.startsWith('..') || path.isAbsolute(rel)) process.exit(0); // outside the project

  const tracked = tryGit(['ls-files', '--error-unmatch', '--', rel], root) !== null;
  const status = tracked ? 'M' : 'A';

  const k = classify(rel, status, roleName, cfg);
  if (k.outcome === 'forbidden') {
    block(askIT(`BLOCKED by the HC Workflow (${role.label}): "${rel}" - ${k.why}.`));
  }

  // Judge only the lines this edit adds.
  const edits = ti.edits ?? [{ old_string: ti.old_string, new_string: ti.new_string }];
  const next = ti.content ?? edits.map((e) => e.new_string ?? '').join('\n');
  const prev = ti.content !== undefined
    ? (existsSync(abs) ? readFileSync(abs, 'utf8') : '')
    : edits.map((e) => e.old_string ?? '').join('\n');
  const found = contentFindings(rel, addedLines(prev, next), roleName, cfg, root, {
    isNew: input.tool_name === 'Write' && !tracked,
  });

  if (found.forbidden.length) {
    const list = found.forbidden.map((f) => `  - ${f.why}`).join('\n');
    block(askIT(`BLOCKED by the HC Workflow (${role.label}): this change to "${rel}" goes beyond what you may do:\n${list}`));
  }

  const stdErrors = found.standards.filter((s) => s.level === 'error');
  if (stdErrors.length) {
    const list = stdErrors.map((s) => `  - "${s.line.slice(0, 90)}" -> ${s.why}`).join('\n');
    block(
      `BLOCKED by the HC Workflow: this change breaks the site standards (see docs/STANDARDS.md):\n${list}\n` +
      'Rewrite it using the existing components and Tailwind theme classes, then try again. ' +
      'If no suitable component exists, tell the user and create an IT request with /ask-it.',
    );
  }

  const notes = [];
  if (k.outcome === 'critical') notes.push(`"${rel}" is a sensitive area: ${k.why}.`);
  for (const c of found.critical) notes.push(c.why);
  if (notes.length) {
    process.stdout.write(JSON.stringify({
      hookSpecificOutput: {
        hookEventName: 'PreToolUse',
        additionalContext:
          `HC Workflow note: this edit is allowed but IT must approve it in the pull request (${notes.join('; ')}). ` +
          'Keep it minimal, never run migrations yourself, and describe it under "Needs IT attention" when you submit.',
      },
    }));
  }
  process.exit(0);
}

if (mode === 'bash') {
  const cmd = input.tool_input?.command ?? '';
  const re = (p) => new RegExp(p, 'i');

  // Merging is for IT, except style-only changes (fast lane): those may be merged by their author,
  // and only the pull request of the branch that is checked out (no PR number, URL or branch name).
  if (/\bgh\s+pr\s+merge\b/i.test(cmd)) {
    const rest = (cmd.split(/\bgh\s+pr\s+merge\b/i)[1] ?? '').trim().split(/\s+/).filter(Boolean);
    const base = ['origin/main', 'origin/master'].find((b) => tryGit(['rev-parse', '--verify', b], root));
    let lane = 'review';
    if (base && rest.every((t) => t.startsWith('-'))) {
      try {
        lane = execFileSync(process.execPath, [path.join(root, '.hc-workflow', 'check.mjs'), '--lane', '--range', `${base}...HEAD`], {
          cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'],
        }).trim();
      } catch { /* anything unexpected counts as "review" */ }
    }
    if (lane !== 'fast') {
      block(
        'BLOCKED by the HC Workflow: this pull request is not a style-only change (or the command names another pull request), so IT must approve and merge it.\n' +
        'Tell the user in plain language that the request was sent to IT and that IT will merge it. Do not try to merge it any other way.',
      );
    }
  }

  for (const { pattern, why } of cfg.blockedCommands) {
    if (re(pattern).test(cmd)) {
      block(askIT(`BLOCKED by the HC Workflow: this command is not allowed (${why}).`));
    }
  }
  process.exit(0);
}

process.exit(0);
