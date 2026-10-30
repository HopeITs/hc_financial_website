"""Regenerate docs/TASK-PROMPTS.ar.md from docs/2026-10-21-finance-implementation-plan.ar.html, then add the
improvements outside the plan (U, IT, AI) from docs/improvement-tasks.json as phase 9.

  python scripts/build_task_prompts.py
"""
import json
import os
import re

from clickup_sync import parse_plan

OUT = os.path.join(os.path.dirname(__file__), "..", "docs", "TASK-PROMPTS.ar.md")
IMPROVEMENTS = os.path.join(os.path.dirname(__file__), "..", "docs", "improvement-tasks.json")

COMMON_RULES = ("Project: Laravel 13, Livewire, Flux, Fortify, Pest, PHPStan. Run PHP with `.\\php.cmd` (PHP 8.5). The database "
                "`hcsyria_financial_test` belongs to the Windows program: never alter or write legacy tables (the only allowed write "
                "is a password change on `user`, through `FinancePassword::make`), never run `migrate:fresh`, `migrate:refresh` or "
                "`db:wipe` against it. Money is `decimal(18,2)` and `bcmath`, never float. Every user-facing label has an `en` and an "
                "`ar` key in `lang/`. Finish with `.\\php.cmd artisan test`, Pint and PHPStan green. One task = one branch = one pull "
                "request. No credentials in the repository.")

GROUPS = {"user": "تحسينات يحسّها المستخدم", "it": "شغل IT بحت", "ai": "الذكاء الاصطناعي (يحتاج موافقة الإدارة)",
          "finance": "طلبات المدير المالي وإجراء الخزينة"}
PRIORITY = {"High": "عالية", "Normal": "متوسطة", "Low": "منخفضة"}
STATUS = {"done": "خالصة ومدموجة في main", "built": "مبنية على فرعها، بانتظار الدمج", "partial": "مبنية جزئياً، والباقي في الهدف أدناه"}


def days(x):
    return {0.5: "نصف يوم", 1: "يوم", 2: "يومان"}.get(x, f"{x:g} أيام")


PHASE_10 = ["\n## المرحلة 10: طلبات المدير المالي وإجراء الخزينة (F) · 2026-10-30\n",
            "من ملاحظات المدير المالي (الطلبات 01 إلى 05) ومن مقارنة `CHC-Treasury-Procedure` بالبرنامج. التفاصيل والقرارات "
            "المطلوبة في `docs/REVIEW-2026-10-30-treasury-procedure.ar.md`. المهام F-03 وF-05 وF-06 وF-11 تحتاج قراراً قبل البدء. "
            "الفرع `f-NN-…`، ورسائل الـ commit تبدأ برقم المهمة.\n"]


def improvements():
    """Phases 9 and 10: the improvements outside the plan (U, IT, AI), then the Financial Manager's requests (F)."""
    if not os.path.exists(IMPROVEMENTS):
        return []
    tasks = json.load(open(IMPROVEMENTS, encoding="utf-8"))
    out = ["\n## المرحلة 9: تحسينات خارج الخطة (U · IT · AI) · بلا قرار مالي · لا تتداخل مع المهام 01 إلى 45\n",
           "مصدرها `docs/improvement-tasks.json`، فعدّلها هناك ثم أعد التوليد. تُستورد إلى ClickUp من ملف CSV، لا عبر "
           "`scripts/clickup_sync.py` (الذي يزامن مهام الخطة الـ 45 فقط). الفرع `u-NN-…` أو `it-NN-…` أو `ai-NN-…`، "
           "ورسائل الـ commit تبدأ برقم المهمة.\n"]
    finance_started = False
    for t in tasks:
        if t["group"] == "finance" and not finance_started:
            finance_started = True
            out.extend(PHASE_10)
        deps = "، ".join(t["depends_on"]) or "لا يوجد"
        out.append(f"\n### {t['id']} · {t['title_ar']}\n")
        out.append(f"- **المجموعة:** {GROUPS[t['group']]} · **المجال:** {t['area_ar']} · **الأولوية:** {PRIORITY[t['priority']]}")
        out.append(f"- **الجهد المقدّر:** {days(t['effort_days'])}")
        out.append(f"- **الاعتماديات:** {deps}")
        if t["status"]:
            out.append(f"- **الحالة:** {STATUS[t['status']]}")
        out.append(f"\n#### Objective\n{t['why_ar']}\n")
        out.append(f"#### Implementation scope\n{t['what_ar']}\n")
        out.append(f"#### Acceptance criteria\n{t['done_when_ar']}\n")
        out.append("#### References in the code\n" + "\n".join(f"- `{e}`" for e in t["evidence"]) + "\n")
        preconditions = (f"These must be merged first: {', '.join(t['depends_on'])}. If one is missing, stop and report."
                         if t["depends_on"] else "None.")
        prompt = "\n".join([
            "## Role and goal",
            f"You are implementing improvement {t['id']} of the HC Financial Web plan ({t['title_en']}). Branch: `{t['branch']}`. Do only this task.",
            "", "## Read before writing any code",
            "1. `CLAUDE.md` and `AGENTS.md`.",
            "2. `docs/PLAN-hcsyria_financial_test.md` and `docs/DECISIONS.md`.",
            "3. The files under References in the code above.",
            "", "## Common rules", COMMON_RULES,
            "", "## Task", t["prompt_en"],
            "", "## Done when", t["done_when_en"],
            "", "## Preconditions", preconditions,
            "", "## Git",
            f"Start from an up-to-date `main`, create branch `{t['branch']}`, commit with messages starting `{t['id']}:`, push, merge (pull request preferred), push `main`. Do not delete the branch.",
        ])
        out.append("#### Claude prompt\n\n````text\n" + prompt + "\n````\n\n---")
    return out

HEAD = """# موجّهات المهام (الخطة v2 — 45 مهمة)

مولَّد من [2026-10-21-finance-implementation-plan.ar.html](2026-10-21-finance-implementation-plan.ar.html) بالأمر `python scripts/build_task_prompts.py`، فلا تعدّله يدوياً: عدّل الخطة ثم أعد التوليد. المهام في ClickUp (قائمة Financial WEB) تتبع نفس الترقيم، وتتزامن عبر `scripts/clickup_sync.py`.

**المواعيد:** الرئيسية والشجرة والبرامج والمشاريع بتاريخ 2026-10-30، وكل ما تبقى قبل 2026-11-30. ترتيب التنفيذ بحسب الاعتماديات وليس بالرقم. المهمة الواحدة = فرع Git واحد = Pull Request واحد، والبرومبت يُنسخ كما هو إلى Claude (نصه بالإنكليزية).

---
"""


def main():
    tasks = parse_plan()
    out = [HEAD]
    last = None
    for t in tasks.values():
        if t["phase"] != last:
            last = t["phase"]
            out.append("\n## " + re.sub(r"^(المرحلة \d+)", r"\1: ", last) + "\n")
        m = t["meta"]
        out.append(f"\n### {t['num']} · {t['title']}\n")
        out.append(f"- **المطوّر والتواريخ:** {m.get('Owner', '')}")
        out.append(f"- **الجهد المقدّر:** {m.get('Estimated effort', '')}")
        out.append(f"- **الاعتماديات:** {m.get('Dependencies', '')}")
        out.append("\n" + re.sub(r"^#### ", "#### ", t["body"], flags=re.M) + "\n")
        out.append("#### Claude prompt\n\n````text\n" + t["prompt"] + "\n````\n\n---")
    extra = improvements()
    out.extend(extra)
    with open(OUT, "w", encoding="utf-8", newline="\n") as f:
        f.write("\n".join(out) + "\n")
    print(len(tasks), "plan tasks and", sum(1 for line in extra if line.startswith("\n### ")), "improvements written")


if __name__ == "__main__":
    main()
