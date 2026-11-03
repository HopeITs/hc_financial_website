---
description: Create a new page or section the way this site does it (components, Tailwind, module routes)
---

Create the page or section the user describes. Follow the site standards exactly; do not invent a new style.

1. **Read first:** `docs/STANDARDS.md`, the layout used by the most similar existing page, and `resources/views/components/` (list the components you can reuse). Copy the structure of the closest existing page.
2. **Plan in plain language** (3-5 lines): the route URL, the view, and whether it needs a Livewire component or a controller. Prefer a Livewire component or a single-action controller as the site already does.
3. **Create only NEW files:**
   - View: `resources/views/...` using the site layout and `<x-...>` components. Tailwind theme classes only: no inline styles, no hard-coded colors, no arbitrary values.
   - Controller or Livewire component: new file under `app/Http/Controllers` or `app/Livewire`.
   - Route: a new file `routes/modules/<module-name>.php`. Never edit `routes/web.php`. Pages that are not clearly public must use the `auth` middleware (and the permission middleware the site already uses).
   - Text: add translation keys in `lang/` instead of hard-coding strings, if the site is multilingual (support RTL/Arabic if it does).
4. **Data:** only read data with existing models, through new classes. Do not change existing models, tables or migrations. If a new table or column is needed, write the migration but never run it, mention it under "Needs IT attention", and use `/ask-it` so IT answers the questions before anything is applied to a real database.
5. **Check:** run `node .hc-workflow/check.mjs --working`, fix anything it reports, and run `npm run build` if available.
6. Tell the user what you created and offer `/submit`.
