// Shared helpers for the HC Workflow. Plain Node, no dependencies.
import { execFileSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import path from 'node:path';

export function git(args, cwd) {
  return execFileSync('git', ['-c', 'core.quotepath=false', ...args], {
    cwd,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
    maxBuffer: 64 * 1024 * 1024,
  }).replace(/\r?\n$/, '');
}

export function tryGit(args, cwd) {
  try { return git(args, cwd); } catch { return null; }
}

export function repoRoot(cwd = process.cwd()) {
  return tryGit(['rev-parse', '--show-toplevel'], cwd) ?? cwd;
}

function loadJson(root, name) {
  const file = path.join(root, '.hc-workflow', name);
  return existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : null;
}
// Every department shares ONE profile (departmentProfile); a department is just a label and a branch prefix.
// Older installs that still list full per-role blocks under "roles" keep working unchanged.
export function expandConfig(cfg) {
  if (cfg?.departments && cfg.departmentProfile) {
    cfg.roles = Object.fromEntries(Object.entries(cfg.departments).map(([name, d]) => [name, { ...cfg.departmentProfile, ...d }]));
  }
  return cfg;
}
export const loadConfig = (root) => {
  const cfg = loadJson(root, 'config.json');
  return cfg ? expandConfig(cfg) : null;
};
export const loadRoles = (root) => loadJson(root, 'roles.json');

// ---- roles -----------------------------------------------------------------

// Local role: IT sets it once per machine with  git config --global hcworkflow.role <role>.
// This is a convenience only; CI decides the real role from roles.json (GitHub login).
export function localRole(root, cfg) {
  const v = process.env.HC_WORKFLOW_ROLE || tryGit(['config', '--get', 'hcworkflow.role'], root);
  return v === 'it' || cfg.roles[v] ? v : cfg.defaultRole;
}

export function roleForAuthor(root, cfg, login) {
  const roles = loadRoles(root) ?? { default: cfg.defaultRole, users: {} };
  const users = Object.fromEntries(Object.entries(roles.users ?? {}).map(([k, v]) => [k.toLowerCase(), v]));
  const v = users[(login ?? '').toLowerCase()] ?? roles.default;
  return v === 'it' || cfg.roles[v] ? v : cfg.defaultRole;
}

// Is this GitHub login listed in roles.json at all? (used by strict mode: unlisted accounts are rejected)
export function isRegistered(root, cfg, login) {
  const users = Object.keys(loadRoles(root)?.users ?? {}).map((k) => k.toLowerCase());
  return users.includes((login ?? '').toLowerCase());
}

// ---- paths -----------------------------------------------------------------

export function globToRegex(glob) {
  let r = '';
  for (let i = 0; i < glob.length; i++) {
    const c = glob[i];
    if (c === '*') {
      if (glob[i + 1] === '*') {
        i++;
        if (glob[i + 1] === '/') { i++; r += '(?:.*/)?'; } else r += '.*';
      } else r += '[^/]*';
    } else if (c === '?') r += '[^/]';
    else r += c.replace(/[.+^${}()|[\]\\]/g, '\\$&');
  }
  return new RegExp(`^${r}$`, 'i');
}

export const matchAny = (rel, globs = []) => globs.some((g) => globToRegex(g).test(rel));
export const isBlade = (rel, cfg) => globToRegex(cfg.bladeGlob).test(rel);

export function isProtectedBranch(name, cfg) {
  return matchAny(name, cfg.protectedBranches);
}

// Decide what happens to one changed path: ok | critical | forbidden.
// status: A added, M modified, D deleted.
export function classify(rel, status, roleName, cfg) {
  const role = cfg.roles[roleName];
  if (matchAny(rel, cfg.forbiddenAnywhere)) return { outcome: 'forbidden', why: 'data or secret file (never committed)' };
  if (matchAny(rel, role.forbidden)) return { outcome: 'forbidden', why: 'protected area (IT only)' };
  if (matchAny(rel, role.addOnly)) {
    if (status === 'A') return { outcome: 'ok' };
    return { outcome: role.modifyExisting, why: 'existing backend file changed (only new files are free)' };
  }
  if (matchAny(rel, role.critical)) return { outcome: 'critical', why: 'sensitive area, IT must approve' };
  if (matchAny(rel, role.free)) return { outcome: 'ok' };
  return { outcome: 'forbidden', why: `outside the ${role.label} area` };
}

// ---- content scans -----------------------------------------------------------

// Lines in `next` that do not already exist in `prev` (so we only judge what is being added).
export function addedLines(prev, next) {
  const seen = new Set((prev ?? '').split(/\r?\n/).map((l) => l.trim()));
  return (next ?? '').split(/\r?\n/).filter((l) => l.trim() && !seen.has(l.trim()));
}

export function scan(lines, rules) {
  const hits = [];
  for (const rule of rules) {
    const re = new RegExp(rule.pattern, 'i');
    const skip = rule.skipIf ? new RegExp(rule.skipIf, 'i') : null;
    for (const line of lines) {
      if (re.test(line) && !(skip && skip.test(line))) hits.push({ ...rule, line: line.trim() });
    }
  }
  return hits;
}

// `tag` is the Blade component name as written after "x-": "button" or "ui.button" (= components/ui/button.blade.php).
function componentExists(root, tag) {
  const base = path.join(root, 'resources', 'views', 'components', ...tag.split('.'));
  return existsSync(`${base}.blade.php`) || existsSync(path.join(base, 'index.blade.php'));
}

export function checkStandards(rel, lines, cfg, root) {
  const s = cfg.standards;
  if (!s || !matchAny(rel, [s.applyTo]) || matchAny(rel, s.exempt)) return [];
  const out = scan(lines, s.rules).map((h) => ({ line: h.line, why: h.why, level: h.level }));
  for (const c of s.components ?? []) {
    const tag = c.tag ?? c.component;
    if (!componentExists(root, tag)) continue; // the site has no such component, nothing to enforce
    const re = new RegExp(c.raw, 'i');
    for (const line of lines) {
      if (re.test(line)) out.push({ line: line.trim(), why: `use <x-${tag}> instead of raw HTML (site component exists)`, level: c.level });
    }
  }
  return out;
}

const trim = (s) => (s.length > 90 ? `${s.slice(0, 90)}...` : s);

// Everything we can learn from the *content* of added lines of one file.
export function contentFindings(rel, lines, roleName, cfg, root, { isNew = false } = {}) {
  const role = cfg.roles[roleName];
  const out = { forbidden: [], critical: [], standards: [] };
  const push = (mode, why) => {
    if (mode === 'forbidden') out.forbidden.push({ path: rel, why });
    else if (mode === 'critical') out.critical.push({ path: rel, why });
  };

  if (isBlade(rel, cfg)) {
    if (matchAny(rel, cfg.bladeLogicGlobs ?? [])) {
      // Livewire single-file page: PHP and markup share the file. A brand-new page is expected to contain
      // component code (only data writes and similar are flagged); in an existing page, any added logic is.
      const hits = [...scan(lines, cfg.phpCriticalPatterns), ...(isNew ? [] : scan(lines, cfg.logicFilePatterns ?? []))];
      for (const h of hits) push(role.bladeRisky ?? 'forbidden', `"${trim(h.line)}" - ${h.why} (PHP logic inside a page file)`);
      // The markup part of the page is judged like any Blade file (forms, external scripts, unescaped output).
      for (const h of scan(lines, cfg.riskyBladePatterns.filter((p) => p.markup))) push(role.bladeRisky ?? 'forbidden', `"${trim(h.line)}" - ${h.why}`);
    } else {
      for (const h of scan(lines, cfg.riskyBladePatterns)) push(role.bladeRisky ?? 'forbidden', `"${trim(h.line)}" - ${h.why}`);
    }
    for (const s of checkStandards(rel, lines, cfg, root)) out.standards.push({ path: rel, ...s });
  } else if (rel.toLowerCase().endsWith('.php')) {
    if (matchAny(rel, [cfg.migrationsGlob])) {
      for (const h of scan(lines, cfg.migrationDestructivePatterns)) push('critical', `"${trim(h.line)}" - ${h.why}`);
    } else if (!matchAny(rel, cfg.phpScanExclude)) {
      for (const h of scan(lines, cfg.phpCriticalPatterns)) push('critical', `"${trim(h.line)}" - ${h.why}`);
    }
  }

  if (isNew && matchAny(rel, [cfg.routeModulesGlob]) && !lines.some((l) => /auth/i.test(l))) {
    push('critical', 'new route file without any "auth" middleware - the pages may be public');
  }
  return out;
}

// ---- fast lane: style-only changes ------------------------------------------

export const isFastPath = (rel, cfg) => matchAny(rel, cfg.fastLane?.paths ?? []);

// Only a plain, static class="..." counts (no {{ }}, {!! !!} or @ inside), so logic can never hide in it.
const PLAIN_CLASS = /\bclass\s*=\s*("[^"{}@]*"|'[^'{}@]*')/gi;

// True when every changed line differs from its old version ONLY inside a plain class attribute.
export function classOnly(removed, added) {
  if (!removed.length || removed.length !== added.length) return false;
  const norm = (l) => l.replace(PLAIN_CLASS, 'class=""').replace(/\s+/g, ' ').trim();
  return removed.every((r, i) => norm(r) === norm(added[i]));
}

// Style files can still pull things in from outside or hide script; those never take the fast lane.
export function unsafeStyleContent(rel, lines) {
  if (/\.svg$/i.test(rel)) return lines.some((l) => /<script|\son\w+\s*=|javascript:/i.test(l));
  if (/\.(css|scss|sass)$/i.test(rel)) {
    return lines.some((l) => /@import\s+(url\()?\s*["']?(https?:)?\/\//i.test(l) || /url\(\s*["']?(https?:)?\/\//i.test(l) || /expression\s*\(|javascript:/i.test(l));
  }
  return false;
}

export function today() {
  return new Date().toISOString().slice(0, 10);
}
