"""Sync the ClickUp list with docs/2026-10-21-finance-implementation-plan.ar.html (plan v2, 45 tasks).

For every task in the plan it sets: name ("NN · title"), description (scope, rules, acceptance criteria and the
Claude prompt), tags (phase, developer), dependencies and due date. Tasks missing in ClickUp are created.
Existing tasks named with the old ids ("T2.5 ...") are matched through LEGACY and renamed.

  $env:CLICKUP_TOKEN = "pk_..."          # never commit it
  python scripts/clickup_sync.py --list <list id> --dry-run
  python scripts/clickup_sync.py --list <list id>

Safe to re-run. Due dates are the last day of each task's range in the plan.
"""
import argparse, datetime as dt, html, json, os, re, sys, time, urllib.error, urllib.parse, urllib.request

API = "https://api.clickup.com/api/v2"
PLAN = os.path.join(os.path.dirname(__file__), "..", "docs", "2026-10-21-finance-implementation-plan.ar.html")

LEGACY = {"T1.1": "02", "T1.2": "03", "T1.3": "04", "T1.4": "05", "T1.5": "06", "T1.6": "07", "T1.7": "08",
          "T2.1": "09", "T2.2": "10", "T2.3": "11", "T2.4": "12", "T2.5": "13", "T2.6": "14",
          "T3.1": "15", "T3.2": "16", "T3.3": "17", "T3.4": "18", "T4.1": "19", "T4.2": "20", "T4.3": "21",
          "T4.4": "22", "T5.1": "24", "T5.2": "25", "T7.3b": "26", "T7.3c": "27", "T7.3d": "28", "T7.3e": "29",
          "T7.3f": "30", "T6.1": "31", "T6.2": "32", "T6.3": "33", "T6.4": "34", "T6.5": "35", "T6.6": "36",
          "T7.1": "37", "T7.2": "38", "T7.3": "39", "T8.1": "40", "T8.2": "41", "T8.3": "43"}

DUE = {"01": "2026-10-22", "02": "2026-10-22", "03": "2026-10-23", "04": "2026-10-22", "05": "2026-10-22",
       "06": "2026-10-26", "07": "2026-10-23", "08": "2026-10-26", "09": "2026-11-02", "10": "2026-11-02",
       "11": "2026-11-03", "12": "2026-11-04", "13": "2026-11-10", "14": "2026-11-03", "15": "2026-11-05",
       "16": "2026-11-06", "17": "2026-11-12", "18": "2026-11-11", "19": "2026-11-13", "20": "2026-11-17",
       "21": "2026-11-18", "22": "2026-11-19", "23": "2026-10-28", "24": "2026-11-17", "25": "2026-11-20",
       "26": "2026-11-24", "27": "2026-11-24", "28": "2026-11-25", "29": "2026-11-26", "30": "2026-11-12",
       "31": "2026-11-09", "32": "2026-11-18", "33": "2026-11-17", "34": "2026-11-18", "35": "2026-11-19",
       "36": "2026-11-20", "37": "2026-11-25", "38": "2026-11-26", "39": None, "40": "2026-11-23",
       "41": "2026-11-25", "42": "2026-11-27", "43": "2026-11-30", "44": "2026-10-29", "45": "2026-10-30"}


def parse_plan():
    s = open(PLAN, encoding="utf-8").read()
    strip = lambda x: html.unescape(re.sub("<[^>]+>", "", x))
    phases = [(m.start(), strip(m.group(1)).strip()) for m in re.finditer(r'<h2 class="phase"[^>]*>(.*?)</h2>', s)]
    tasks = {}
    for m in re.finditer(r'<article class="task" id="t(\d+)" data-owners="([^"]*)".*?</article>', s, re.S):
        a, n = m.group(0), m.group(1)
        title = strip(re.search(r'<h3 id="t\d+-h">(.*?)</h3>', a).group(1)).replace(f"Task {n} — ", "").strip()
        meta = {strip(k): strip(v).strip() for k, v in re.findall(r"<dt>(.*?)</dt><dd>(.*?)</dd>", a)}
        prompt = html.unescape(re.search(r'<pre class="prompt"[^>]*>(.*?)</pre>', a, re.S).group(1)).strip()
        body = a.split('<div class="task-body">')[1]
        body = re.sub(r"<h4>(.*?)</h4>", lambda x: "\n\n#### " + x.group(1) + "\n", body)
        body = re.sub(r"<li>", "\n- ", body)
        body = strip(re.sub(r"</p>|</ul>", "\n", body)).strip()
        phase = [p for pos, p in phases if pos < m.start()][-1]
        owners = m.group(2).split()
        tasks[n] = dict(
            num=n, title=title, meta=meta, phase=phase, prompt=prompt, body=body,
            deps=re.findall(r"Task (\d+)", meta.get("Dependencies", "")),
            tags=[re.match(r"(المرحلة \d+)", phase).group(1), "Dev " + "+".join(owners)])
    return tasks


def description(t):
    m = t["meta"]
    branch = re.search(r"Branch: `([^`]+)`", t["prompt"])
    head = [f"**المطوّر والتواريخ:** {m.get('Owner', '')}", f"**الجهد المقدّر:** {m.get('Estimated effort', '')}",
            f"**الاعتماديات:** {m.get('Dependencies', '')}"]
    if branch:
        head.append(f"**الفرع:** `{branch.group(1)}`")
    return "\n\n".join(head) + "\n\n" + t["body"] + "\n\n#### Claude prompt\n\n```\n" + t["prompt"] + "\n```"


class Api:
    def __init__(self, token, dry):
        self.token, self.dry, self.calls = token, dry, 0

    def __call__(self, method, path, body=None, write=False):
        if write and self.dry:
            return {}
        self.calls += 1
        time.sleep(0.62)
        req = urllib.request.Request(API + path, method=method,
                                     headers={"Authorization": self.token, "Content-Type": "application/json"},
                                     data=None if body is None else json.dumps(body).encode())
        for _ in range(6):
            try:
                with urllib.request.urlopen(req) as r:
                    return json.load(r)
            except urllib.error.HTTPError as e:
                if e.code == 429:
                    time.sleep(30)
                    continue
                sys.exit(f"{method} {path} -> {e.code} {e.read().decode()[:300]}")
        sys.exit("rate limited too long")


def existing_tasks(api, list_id):
    found, page = {}, 0
    while True:
        d = api("GET", f"/list/{list_id}/task?page={page}&include_closed=true&subtasks=true")
        for t in d["tasks"]:
            m = re.match(r"(\d{2}) · ", t["name"]) or re.match(r"(T\d+\.\d+[a-z]?) ", t["name"])
            if m:
                found[LEGACY.get(m.group(1), m.group(1))] = t
        if d.get("last_page", True) or not d["tasks"]:
            return found
        page += 1


def ms(iso):
    d = dt.date.fromisoformat(iso)
    return int(dt.datetime(d.year, d.month, d.day, 12, tzinfo=dt.timezone.utc).timestamp() * 1000)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--list", required=True)
    ap.add_argument("--dry-run", action="store_true")
    a = ap.parse_args()
    api = Api(os.environ.get("CLICKUP_TOKEN") or sys.exit("set CLICKUP_TOKEN first"), a.dry_run)
    plan = parse_plan()
    assert len(plan) == 45 and set(plan) == set(DUE), "plan and DUE table disagree"
    found = existing_tasks(api, a.list)
    print(f"plan: {len(plan)} tasks; in ClickUp: {len(found)}; to create: {sorted(set(plan) - set(found))}")

    ids = {n: t["id"] for n, t in found.items()}
    for n, t in plan.items():
        payload = {"name": f"{n} · {t['title']}", "markdown_description": description(t)}
        if n in found:
            api("PUT", f"/task/{ids[n]}", payload, write=True)
        else:
            print(f"create {payload['name']}")
            ids[n] = api("POST", f"/list/{a.list}/task", payload, write=True).get("id", "dry-" + n)
        have = {x["name"] for x in found[n]["tags"]} if n in found else set()
        for tag in have - set(t["tags"]):
            api("DELETE", f"/task/{ids[n]}/tag/{urllib.parse.quote(tag)}", write=True)
        for tag in set(t["tags"]) - have:
            api("POST", f"/task/{ids[n]}/tag/{urllib.parse.quote(tag)}", write=True)
        print(f"{n} {t['title'][:50]}  tags={t['tags']}")

    for n, t in plan.items():
        current = api("GET", f"/task/{ids[n]}")["dependencies"] if n in found else []
        have = {d["depends_on"] for d in current if d["task_id"] == ids[n]}
        want = {ids[d] for d in t["deps"]}
        for d in have - want:
            api("DELETE", f"/task/{ids[n]}/dependency?depends_on={d}", write=True)
        for d in want - have:
            api("POST", f"/task/{ids[n]}/dependency", {"depends_on": d}, write=True)
        print(f"{n} waits on {t['deps'] or '-'}  (+{len(want - have)} -{len(have - want)})")

    # ClickUp moves dates when a dependency changes, so clear starts and set due dates last, twice
    for _ in range(2):
        for n in sorted(plan):
            api("PUT", f"/task/{ids[n]}", {"start_date": None}, write=True)
            due = {"due_date": ms(DUE[n]), "due_date_time": False} if DUE[n] else {"due_date": None}
            api("PUT", f"/task/{ids[n]}", due, write=True)
        if a.dry_run:
            break
    print(f"done, {api.calls} API calls")


if __name__ == "__main__":
    main()
