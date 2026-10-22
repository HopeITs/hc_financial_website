# Design standard (read before any UI task)

Mode: **Operate**. Financial staff complete tasks; clarity beats expression. This file is the UI rule for every task; the plan (section 12) and `docs/TASK-PROMPTS.md` point here.

## Rules
1. **Tables**: wrap in `.fin-table-wrap`, use `<table class="fin-table">`. Plain `<th>` and `<td>`; number columns get `class="fin-num"` on both. Empty state: `<td colspan="N" class="fin-empty">`. No `px-*`/`py-*` utilities on cells.
2. **Amounts**: always `<x-money :amount="$value" />`. It right-aligns with tabular digits, shows the minus sign, colours losses red and dims zero. Never `number_format` in a view. Pass `currency="USD"`/`"SYP"` to print the code beside the number, or put it in the column header.
3. **Same spirit as HC Tracking**: every page starts with `<x-ui.page-header>` (bold title, count badge, one-line subtitle, actions). Key figures are `<x-ui.stat-tile>` (label, number, currency unit, icon) in a grid at the top. A table, list or form lives in `<x-ui.panel>` (title, count, toolbar) or `.fin-table-wrap`. Empty lists use `<x-ui.empty-state>`. These components are copied from the tracking app and are static: no hover lift. Do not nest panels.
4. **Status**: `<flux:badge size="sm">`, green = active, zinc = closed/inactive, amber = needs attention. Text is always present.
5. **Names are links**: a project or account name in a list links to its page.
6. **Forms and controls**: use the tracking components, never raw `flux:input/select/textarea`: `<x-ui.form-input>`, `<x-ui.form-textarea>`, `<x-ui.form-select>` (dropdown with `<option>` children, searchable above 7 options), `<x-ui.form-multiselect>`, `<x-ui.form-segmented>`, `<x-ui.form-section>` for long forms, `<x-ui.button tone="primary|secondary|ghost|action">`, `<x-ui.modal>` / `<x-ui.confirm-modal>` for dialogs, `<x-ui.view-toggle>`. Label above the field, required marker, help and error lines come built in. One primary button, then Cancel. Checkboxes and switches stay `flux:checkbox` / `flux:switch` (already themed in `flux-overrides.css`).
7. **Filters**: search and `x-ui.form-select` fields in a panel above the table, applied live.
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
