// Checks a set of changes against the role's rules and the site standards.
//   node .hc-workflow/check.mjs --working              uncommitted + untracked changes
//   node .hc-workflow/check.mjs --range base...head    a commit range (CI)
//   node .hc-workflow/check.mjs --pre-push             called by .githooks/pre-push (reads stdin)
//   node .hc-workflow/check.mjs --whoami               prints the role and branch prefix as JSON
//   node .hc-workflow/check.mjs --working|--range ... --lane   prints "fast" (style-only) or "review" and exits
// Options: --ci (GitHub annotations)  --author <github-login>  --role <role>  --branch <name>
//          --summary <file> (markdown for the PR comment)
//          --approvers a,b,c  (CI: logins that approved the latest commit; a non-style change then needs one of the
//                              "approvers" in roles.json, or, when that list is absent, anyone marked "it")
// Exit code 1 = blocked. "Critical" items do not fail the check; they are listed for IT.
// Lane: "fast" = only style files (cfg.fastLane.paths) or class-only edits in Blade; the author may merge it.
//       "review" = everything else; IT must approve and merge.
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import path from 'node:path';
import {
  git, tryGit, repoRoot, loadConfig, localRole, roleForAuthor, classify, contentFindings,
  isBlade, isProtectedBranch, isFastPath, classOnly, unsafeStyleContent, isRegistered, isApprover, approverList,
} from './lib.mjs';

const args = process.argv.slice(2);
const flag = (n) => args.includes(n);
const value = (n) => (args.includes(n) ? args[args.indexOf(n) + 1] : undefined);
const ci = flag('--ci');

const root = repoRoot();
const cfg = loadConfig(root);
if (!cfg) { console.error('hc-workflow: .hc-workflow/config.json not found'); process.exit(0); }

const roleName = value('--author') !== undefined
  ? roleForAuthor(root, cfg, value('--author'))
  : (value('--role') ?? localRole(root, cfg));

// Strict mode (cfg.strictUsers): a GitHub account that is not listed in roles.json is rejected outright,
// so nobody reaches the fast lane or any other lane before IT has registered them.
if (cfg.strictUsers && value('--author') !== undefined && !flag('--whoami') && !isRegistered(root, cfg, value('--author'))) {
  const msg = `The GitHub account "${value('--author')}" is not registered in .hc-workflow/roles.json. Ask IT to add it (as "it" or as its department) before sending changes.`;
  console.log(ci ? `::error::${msg}` : `\n${msg}\n`);
  if (value('--summary')) writeFileSync(value('--summary'), `### HC Workflow - BLOCKED\n\n${msg}\n`);
  process.exit(1);
}

if (flag('--whoami')) {
  const r = cfg.roles[roleName];
  console.log(JSON.stringify({ role: roleName, branchPrefix: r?.branchPrefix ?? null, protected: cfg.protectedBranches }));
  process.exit(0);
}
if (roleName === 'it') {
  if (flag('--lane')) { console.log('it'); process.exit(0); }
  // With an approvers list in roles.json, an IT account that is not on it gets no free pass: someone on the list
  // must approve its pull request too.
  const list = approverList(root);
  if (list && value('--author') !== undefined && !list.includes(value('--author').toLowerCase()) && flag('--approvers')) {
    const names = (value('--approvers') ?? '').split(',').filter(Boolean);
    if (!names.some((n) => isApprover(root, cfg, n))) {
      const msg = `This pull request needs an approval on its latest commit from one of: ${list.join(', ')}.`;
      console.log(ci ? `::error::${msg}` : `\n${msg}\n`);
      if (value('--summary')) writeFileSync(value('--summary'), `### HC Workflow - BLOCKED\n\nLane: **REVIEW** - ${msg}\n`);
      process.exit(1);
    }
  }
  if (value('--summary')) writeFileSync(value('--summary'), '### HC Workflow\nAuthor is an IT member: role checks skipped.\n');
  process.exit(0);
}
const role = cfg.roles[roleName];
const ZERO = /^0+$/;

function parseNameStatus(text) {
  return text.split('\n').filter(Boolean).map((l) => {
    const [status, a, b] = l.split('\t');
    return { status: status[0], path: b ?? a, oldPath: b ? a : null };
  });
}

// Returns { changes, added(file), removed(file) } for a diff against `range` ('' = working tree vs HEAD).
function collect(range) {
  const target = range ? [range] : ['HEAD'];
  let changes = parseNameStatus(git(['diff', '--name-status', '-M', ...target], root));
  const untracked = range ? [] : git(['ls-files', '--others', '--exclude-standard'], root).split('\n').filter(Boolean);
  changes = changes.concat(untracked.map((p) => ({ status: 'A', path: p, oldPath: null })));
  const added = (file) => {
    if (untracked.includes(file)) {
      const abs = path.join(root, file);
      return existsSync(abs) ? readFileSync(abs, 'utf8').split(/\r?\n/) : [];
    }
    const diff = tryGit(['diff', '-U0', ...target, '--', file], root) ?? '';
    return diff.split('\n').filter((l) => l.startsWith('+') && !l.startsWith('+++')).map((l) => l.slice(1));
  };
  const removed = (file) => {
    if (untracked.includes(file)) return [];
    const diff = tryGit(['diff', '-U0', ...target, '--', file], root) ?? '';
    return diff.split('\n').filter((l) => l.startsWith('-') && !l.startsWith('---')).map((l) => l.slice(1));
  };
  return { changes, added, removed };
}

function evaluate({ changes, added, removed }, branch) {
  const res = { forbidden: [], critical: [], standards: [], branch: null, approval: null, lane: 'review', files: changes };
  let allFast = changes.length > 0;
  const seen = new Set();
  const add = (list, item) => {
    const key = `${list}|${item.path}|${item.why ?? item.line}`;
    if (!seen.has(key)) { seen.add(key); res[list].push(item); }
  };

  if (branch) {
    if (isProtectedBranch(branch, cfg)) res.branch = `"${branch}" is a protected branch. Work on a "${role.branchPrefix}..." branch and open a pull request.`;
    else if (!branch.startsWith(role.branchPrefix)) res.branch = `Branch "${branch}" must start with "${role.branchPrefix}" for the ${role.label} department.`;
  }

  for (const c of changes) {
    // Fast lane: a style file (new or edited), or a Blade edit that only changes plain class attributes.
    let fast = false;
    if (c.status === 'A' || c.status === 'M') {
      if (isFastPath(c.path, cfg)) fast = !unsafeStyleContent(c.path, added(c.path));
      else if (cfg.fastLane?.bladeClassOnly && c.status === 'M' && isBlade(c.path, cfg)) fast = classOnly(removed(c.path), added(c.path));
    }
    if (!fast) allFast = false;

    const items = c.status === 'R' ? [[c.oldPath, 'D'], [c.path, 'A']] : [[c.path, c.status]];
    for (const [p, s] of items) {
      const k = classify(p, s, roleName, cfg);
      if (k.outcome === 'forbidden' || k.outcome === 'critical') add(k.outcome, { path: p, why: k.why });
    }
    const gone = c.status === 'D' ? c.path : c.status === 'R' ? c.oldPath : null;
    if (gone && isBlade(gone, cfg)) {
      add(role.modifyExisting, { path: gone, why: 'view deleted or renamed (a controller may still use it)' });
    }
    if (c.status === 'D') continue;
    // A file that is already off-limits needs no deeper scan.
    if (classify(c.path, c.status === 'R' ? 'A' : c.status, roleName, cfg).outcome === 'forbidden') continue;
    const f =contentFindings(c.path, added(c.path), roleName, cfg, root, { isNew: c.status === 'A' });
    f.forbidden.forEach((i) => add('forbidden', i));
    f.critical.forEach((i) => add('critical', i));
    f.standards.forEach((i) => add('standards', i));
  }
  if (allFast && !res.forbidden.length && !res.critical.length && !res.standards.some((s) => s.level === 'error') && !res.branch) res.lane = 'fast';

  // CI gate: anything that is not style-only needs a recorded approval from someone listed as "it".
  if (flag('--approvers') && res.lane !== 'fast') {
    const names = (value('--approvers') ?? '').split(',').filter(Boolean);
    if (!names.some((n) => isApprover(root, cfg, n))) {
      const who = approverList(root)?.join(', ') ?? 'a person marked "it" in .hc-workflow/roles.json';
      res.approval = `This is not a style-only change, so IT must approve it first: one of ${who}, approving the latest commit.`;
    }
  }
  return res;
}

const stdErrors = (r) => r.standards.filter((s) => s.level === 'error');
const isBlocked = (r) => r.forbidden.length || stdErrors(r).length || r.branch || r.approval;

function report(r) {
  const out = (msg, file) => console.log(ci ? `::error${file ? ` file=${file}` : ''}::${msg}` : `  - ${msg}`);
  const warn = (msg, file) => console.log(ci ? `::warning${file ? ` file=${file}` : ''}::${msg}` : `  - ${msg}`);

  console.log(`\nLane: ${r.lane === 'fast' ? 'FAST - style-only, you can merge it yourself' : 'REVIEW - IT must approve and merge it'}`);
  if (r.branch) { console.log('\nBranch problem:'); out(r.branch); }
  if (r.approval) { console.log('\nWaiting for IT:'); out(r.approval); }
  if (r.forbidden.length) {
    console.log(`\nNot allowed for ${role.label} - IT must answer first:`);
    r.forbidden.forEach((f) => out(`${f.path}: ${f.why}`, f.path));
    console.log(`  What to do: ask Claude to run /ask-it (a questions file for IT in ${cfg.itRequestsDir}/).`);
  }
  if (stdErrors(r).length) {
    console.log('\nBreaks the site standards (components / Tailwind):');
    stdErrors(r).forEach((s) => out(`${s.path}: "${s.line.slice(0, 80)}" -> ${s.why}`, s.path));
  }
  const warns = r.standards.filter((s) => s.level !== 'error');
  if (warns.length) {
    console.log('\nStandards warnings (not blocking):');
    warns.forEach((s) => warn(`${s.path}: "${s.line.slice(0, 80)}" -> ${s.why}`, s.path));
  }
  if (r.critical.length) {
    console.log('\nAllowed, but IT must approve (will be flagged on the pull request):');
    r.critical.forEach((c) => warn(`${c.path}: ${c.why}`, c.path));
  }
  if (!isBlocked(r) && !r.critical.length && !warns.length) console.log(`HC Workflow (${role.label}): everything is within your area. OK.`);
  else if (!isBlocked(r)) console.log(`\nHC Workflow (${role.label}): no blockers.`);
  else console.log('');
}

function section0(L, title, rows) { if (rows.length) { L.push(`**${title}**`); rows.forEach((x) => L.push(`- ${x}`)); L.push(''); } }

function markdown(r, blocked) {
  const L = [];
  L.push(`### HC Workflow - ${role.label} department ${blocked ? '- BLOCKED' : '- passed'}`);
  const n = (s) => r.files.filter((f) => f.status === s).length;
  L.push(r.lane === 'fast'
    ? 'Lane: **FAST** - style-only (style files or class-only edits). The author may merge it; no IT approval needed.'
    : 'Lane: **REVIEW** - not style-only. IT must approve and merge it.');
  L.push(`Files: **${n('A')} new**, **${n('M')} modified**, **${n('D')} deleted**, **${n('R')} renamed**\n`);
  section0(L, 'Waiting for IT', r.approval ? [r.approval] : []);
  const section = (title, rows) => { if (rows.length) { L.push(`**${title}**`); rows.forEach((x) => L.push(`- ${x}`)); L.push(''); } };
  section('Branch', r.branch ? [r.branch] : []);
  section('Not allowed (IT must answer first)', r.forbidden.map((f) => `\`${f.path}\` - ${f.why}`));
  section('Needs IT approval (review closely)', r.critical.map((c) => `\`${c.path}\` - ${c.why}`));
  section('Breaks site standards', stdErrors(r).map((s) => `\`${s.path}\`: \`${s.line.slice(0, 80)}\` - ${s.why}`));
  section('Standards warnings', r.standards.filter((s) => s.level !== 'error').map((s) => `\`${s.path}\`: \`${s.line.slice(0, 80)}\` - ${s.why}`));
  if (!r.branch && !r.forbidden.length && !r.critical.length && !r.standards.length) L.push('Everything is within the department area.');
  L.push('\n_Style-only changes (fast lane) can be merged by their author. Everything else is approved and merged by IT._');
  return L.join('\n');
}

let blocked = false;
let last = null;

if (flag('--working')) {
  last = evaluate(collect(''), null);
  if (flag('--lane')) { console.log(last.lane); process.exit(0); }
  report(last);
  blocked = !!isBlocked(last);
} else if (flag('--range')) {
  last = evaluate(collect(value('--range')), value('--branch') ?? null);
  if (flag('--lane')) { console.log(last.lane); process.exit(0); }
  report(last);
  blocked = !!isBlocked(last);
} else if (flag('--pre-push')) {
  const lines = readFileSync(0, 'utf8').split('\n').filter(Boolean);
  for (const line of lines) {
    const [, localSha, remoteRef, remoteSha] = line.split(' ');
    if (ZERO.test(localSha)) continue; // deleting a remote branch
    const branch = remoteRef.replace('refs/heads/', '');
    let range = null;
    if (!ZERO.test(remoteSha) && tryGit(['cat-file', '-e', `${remoteSha}^{commit}`], root) !== null) {
      range = `${remoteSha}..${localSha}`;
    } else {
      const base = ['origin/main', 'origin/master'].find((b) => tryGit(['rev-parse', '--verify', b], root));
      if (base) range = `${base}...${localSha}`;
    }
    if (!range) {
      if (isProtectedBranch(branch, cfg)) { console.log(`\nHC Workflow: you cannot push to "${branch}". Use /submit.\n`); blocked = true; }
      else console.log('hc-workflow: no base branch found, skipping local check (CI will still check).');
      continue;
    }
    last = evaluate(collect(range), branch);
    report(last);
    blocked = !!isBlocked(last) || blocked;
  }
} else {
  console.error('Usage: check.mjs --working | --range <a>...<b> | --pre-push | --whoami');
  process.exit(2);
}

if (value('--summary') && last) writeFileSync(value('--summary'), markdown(last, blocked));
process.exit(blocked ? 1 : 0);
