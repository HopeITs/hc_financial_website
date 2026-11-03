---
description: Ask IT before changing anything outside the front end (creates a questions file)
---

The HC Workflow blocked a change, or the user needs something their department may not do alone (existing backend files, database, config, packages, login/permissions, forms that send data, etc.).

1. **Do not change any backend file.** Read-only investigation is fine: look at which files would be involved and why.
2. Copy `it-requests/_TEMPLATE.md` to `it-requests/<YYYY-MM-DD>-<short-topic>.md`.
3. Fill in:
   - Section 1: what the user wants, in their own words, plus the page/site.
   - Section 2: what you found (files that would need to change and why the front end alone is not enough). Keep it factual; do not write the code.
   - Section 3: keep the standard questions and add specific questions of your own that IT needs to answer before any work starts.
   Leave "IT answers" empty.
4. Tell the user in plain language: what you could not do, that a questions file was created, and that IT will answer it. Offer to continue with the part that is purely front end.
5. If the user wants to send it, run the `/submit` steps (the request file is allowed to be committed).
