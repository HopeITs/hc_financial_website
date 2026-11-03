---
description: (IT) Scan this site and write docs/STANDARDS.md - the component and Tailwind rules everyone must follow
---

This is for IT. Study the site (read-only except for `docs/STANDARDS.md`) and write `docs/STANDARDS.md` so every department, and Claude, build consistently.

Cover, with real examples copied from the code:
1. **Stack and layout:** Laravel, Livewire, Alpine and Tailwind versions; the main layouts (`resources/views/layouts`), how pages extend them, RTL/multilingual handling.
2. **Components:** a table of every Blade/Livewire component in `resources/views/components` and `app/View/Components` (name, purpose, props, a one-line usage example). State which raw HTML elements must never be used because a component exists (button, input, select, table...).
3. **Tailwind:** the theme from `tailwind.config.*` or the `@theme` CSS (colors, fonts, spacing scale, breakpoints). The class patterns the site uses for cards, sections, headings, spacing and forms. What to avoid (arbitrary values, hex colors, inline styles, `@apply` sprawl).
4. **Structure and naming:** where views, components, Livewire classes and routes live; naming conventions; how routes are grouped and which middleware protects them; how modules should be added (`routes/modules/*.php`).
5. **Do / Don't list** of the 10 most common mistakes you can see in the code.
6. **Suggested guard changes:** if the config in `.hc-workflow/config.json` (component list, patterns) does not match this site, list the exact edits to make there. Do not edit it yourself.

Keep it under 300 lines, concrete, and in plain language. Finish by telling IT what they should review before approving it.
