"""Regenerate docs/TASK-PROMPTS.ar.md from docs/2026-10-21-finance-implementation-plan.ar.html.

  python scripts/build_task_prompts.py
"""
import os
import re

from clickup_sync import parse_plan

OUT = os.path.join(os.path.dirname(__file__), "..", "docs", "TASK-PROMPTS.ar.md")

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
    with open(OUT, "w", encoding="utf-8", newline="\n") as f:
        f.write("\n".join(out) + "\n")
    print(len(tasks), "tasks written")


if __name__ == "__main__":
    main()
