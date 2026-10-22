# Design standard (read before any UI task)

Mode: **Operate**. Financial staff complete tasks; clarity beats expression. This file is the UI rule for every task; the plan (section 12) and `docs/TASK-PROMPTS.md` point here.

## Rules
1. **Tables**: wrap in `.fin-table-wrap`, use `<table class="fin-table">`. Plain `<th>` and `<td>`; number columns get `class="fin-num"` on both. Empty state: `<td colspan="N" class="fin-empty">`. No `px-*`/`py-*` utilities on cells.
2. **Amounts**: always `<x-money :amount="$value" />`. It right-aligns with tabular digits, shows the minus sign, colours losses red and dims zero. Never `number_format` in a view. Always show the currency in the column header or next to the value (USD, SYP, SYP new).
3. **Key figures** (a balance on a detail page): `<dl>` of `.fin-figure` boxes with `<dt>` label and `<dd>` value. Do not nest cards.
4. **Status**: `<flux:badge size="sm">`, green = active, zinc = closed/inactive, amber = needs attention. Text is always present.
5. **Names are links**: a project or account name in a list links to its page.
6. **Forms**: one column on phones, labels above fields, the primary action is one `variant="primary"` button, then Cancel. Errors name the problem and the fix.
7. **Filters**: search and selects above the table, applied live; show the active count of results.
8. **Layout**: page title (`flux:heading size="xl"`) + one-line subheading, then content. Keep pages under 90rem. Minimum text 14px (13px for table headers and captions only).
9. **Colour**: brand red only for the primary action and the active nav item. Status colours only for status and sign. Light and dark must both work.
10. **Motion**: none beyond hover tint and Flux's own 120ms fades. Do not add entrances, slides, scale, blur, or view transitions. Respect `prefers-reduced-motion`.
11. **RTL**: use `start`/`end`, `ms`/`me`, `ps`/`pe`; never `left`/`right`. Digits stay LTR (the money component does this).
12. **Copy**: every label has `en` and `ar` keys. Buttons say the action ("Save voucher"), not "Submit".

## Checklist (paste into every UI pull request)
- [ ] Tables use `.fin-table`, amounts use `<x-money>`
- [ ] Currency visible for every amount
- [ ] Empty, error and loading state checked
- [ ] Checked in Arabic (RTL), dark mode and a 375px phone
- [ ] No new animation or transition
- [ ] Keyboard: tab order sensible, focus visible
