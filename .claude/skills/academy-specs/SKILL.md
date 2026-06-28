---
name: academy-specs
description: Read, add, or update Academy platform feature specs / user stories (the Flex tutoring platform — packages, lessons, Flex, wallets, withdrawals). Use whenever the user pastes or edits a user story, mentions a US-ID (e.g. US-AD-1-2, US-LS-3-1, US-FN-1-4), asks what a feature should do, or wants to implement a spec'd feature. The specs live in docs/specs/.
---

# Academy platform specs

The single source of truth for Academy feature requirements is **`docs/specs/`** (not memory, not
this file). This skill is the *procedure* for keeping those docs in sync; the docs hold the content.

## Layout — one file per user story

Each story is its own file under an area subfolder, named `US-<ID>-<slug>.md`:

```
docs/specs/
  README.md            ← index + status table + ID collisions (always read first)
  00-overview.md       ← roles, package catalog, Flex concept, status models, glossary
  admin/               US-AD-*
  teacher/             US-TR-*  + teacher-facing financial (US-FN-1-3, US-FN-2-1)
  student/             US-ST-*, US-PK-*
  lessons/             US-LS-*
  financial/           00-wallet-model.md + US-FN-*
```

ID format: `US-<AREA>-<GROUP>-<SEQ>`, AREA ∈ {AD, TR, ST, PK, LS, FN}. The AREA maps to a folder
(see table above). `PK` lives in `student/`. `FN` is split: teacher-facing financial stories live in
`teacher/`, system/admin financial stories in `financial/` — check `README.md` for the exact placement.

## When the user pastes or edits a story

1. **Read `README.md` first**, then the existing story file if the ID already exists. Match the house
   format; don't create a near-duplicate.
2. **Locate the file by ID.** Find the existing `US-<ID>-*.md` in the right area folder.
   - **Exists** → it's an update. Overwrite that file in place. Keep the same filename unless the title
     changed materially (then rename the file to the new slug and fix links to it).
   - **New** → create `docs/specs/<area>/US-<ID>-<kebab-title>.md`.
3. **Use the per-file template:**
   ```
   # US-XX-Y-Z: Title

   [← spec index](../README.md) · Area: <Area> · **Status:** Spec

   As a … I want … so that …

   ## Flow
   1. <actor emoji> …            (🎓 Student · 👨‍🏫 Teacher · 🔧 Admin · ⚙️ System)

   ## Results / Display / Notes   (only the sections present in the source)
   ```
   Keep the `**Status:**` value (default new = `Spec`; advance to `In progress` / `Built` as work moves).
4. **Update `README.md`**: add/update the row in the matching area table (title link + status). Keep rows
   in ID order.
5. **Watch for ID collisions.** `US-FN-1-3` and `US-FN-2-1` each label two different stories (teacher vs
   financial). They live in different folders with different slugs and cross-link via a `> ⚠️ Duplicate
   ID` note at the top. If a pasted story reuses an existing ID for different content, flag it — don't
   silently overwrite the other one.
6. **Preserve bilingual content.** Some stories (e.g. US-AD-3-1) include Arabic — keep both languages.
7. **Keep cross-links working.** Stories link to related ones with relative paths
   (e.g. `../financial/US-FN-1-4-distribute-lesson-revenue.md`). If you rename a file, grep for links to
   its old name and fix them.

## Conventions

- These are **forward-looking requirements**, not docs of the current Moodle install. Don't assume any
  of it exists in the DB. If asked to implement, confirm the target (Moodle plugin? separate service?).
- Keep stories faithful to the source wording; tidy formatting only, don't invent rules.
- Don't dump spec content into memory or into this SKILL.md — point to `docs/specs/`.

## When the user asks "what should feature X do?"

Read the matching file, answer from it, and cite the US-ID(s). If the spec is silent or contradictory
(duplicate IDs, the `US-LS-2-2` "or something else" reject branch), say so rather than guessing.
