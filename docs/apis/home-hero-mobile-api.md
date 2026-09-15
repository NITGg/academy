# Home hero text — Mobile API

One function, `local_nit_category_get_home_hero`, returns the words of the
front-page hero — the two-line heading ("Learn Today / Advance Tomorrow") and
the paragraph under it — so the app's home screen can show the same headline
the web site shows.

| Website | Function |
|---|---|
| The hero at the top of `/` (the front page) | `local_nit_category_get_home_hero` |
| Editor: the front-page **NIT Section** block that holds the hero (Site home → Edit mode → block → Configure) | — |

The admin edits that block; the web site and the app both read it. **Do not
hard-code the sentence in the app**: a re-worded hero changes both surfaces at
once, with no app release.

---

## Transport

```
GET {WWWROOT}/webservice/rest/server.php
  ?wstoken=TOKEN
  &wsfunction=local_nit_category_get_home_hero
  &moodlewsrestformat=json
  &lang=ar
```

- `WWWROOT` staging/prod: `https://academy2026.nitg-eg.com/moodle-new`
- **Callable before login.** The hero is the first thing a visitor sees, so it
  is reachable with the shared guest/registration token — the same token and
  the same rule as `local_profilefields_get_footer`.
- `lang` (or `alang`) is optional: `en` or `ar`. Omit it and the caller's own
  language is used.

**Cache it.** One read of one block — cheap, but it changes only when an
administrator edits the hero. Fetch it with the footer at start-up (and again
when the user switches language) rather than on every visit to the home tab.

---

## Response

```json
{
  "found": true,
  "source": "block",
  "lang": "en",
  "headline": ["Learn Today", "Advance Tomorrow"],
  "title": "Learn Today Advance Tomorrow",
  "description": "Enjoy a unique learning experience that gives you the skills of the future. Join more than 10,000 learners and start empowering your professional future with outstanding experts today.",
  "warnings": []
}
```

Arabic (`lang=ar`):

```json
{
  "found": true,
  "source": "block",
  "lang": "ar",
  "headline": ["تعلّــــم اليوم", "تقــدّم غدًا"],
  "title": "تعلّــــم اليوم تقــدّم غدًا",
  "description": "استمتع بتجربة تعليمية فريدة تمنحك مهارات المستقبل. انضم لأكثر من ١٠,٠٠٠ طالب وطالبة وابدأ رحلة تمكين مستقبلك المهني مع خبراء متميزين اليوم.",
  "warnings": []
}
```

### Field by field

| Field | What to do with it |
|---|---|
| `found` | `false` means no hero could be read anywhere — **draw nothing** (or your own placeholder). The text fields are then empty, so you do not have to guard each one. |
| `source` | `block` = read from the live front-page block (the normal case); `file` = read from the packaged copy because the site has no hero block yet; `""` when not found. Diagnostic only — never branch on it for layout. |
| `lang` | The language the text actually came back in. |
| `headline[]` | The heading, **one entry per line, in display order**. The web page paints the **last** line in the brand accent colour (`--nit-brand-accentwords`) and the others in the primary text colour. Render each entry on its own line; never split or join them yourself. |
| `title` | The same lines joined with a space — for a client that wants one string (an accessibility label, a share sheet). |
| `description` | The paragraph under the heading, one flat string. Let it wrap; it is plain text with no line breaks of its own. |

### Language

Every string comes back **already in one language**; there is no `{mlang}`
markup to parse and no `_en` / `_ar` pair to choose between. A line the
administrator wrote in only one language falls back to that one rather than
coming back empty.

Ask again with the other `lang` when the user switches language in the app.

### Do not assume two lines

Today the heading has two lines. That is how the block is written, not a rule
of the API: an editor could make it one line or three. Lay `headline[]` out as
a list, and colour the last entry as the accent line.

### Colours

The heading and the paragraph carry no colours — take them from the design
system feed (`theme/nit/design_system.php`, doc: `design-system-api.md`):
`textprimary` for the first line(s), `accentwords` for the last line,
`textsecondary` for the paragraph, `background` behind it all.

---

## Errors

There is no per-field failure mode here — it is a read of one block.
`warnings` is present for consistency with the rest of the API and is always
empty today. An exception means the transport failed (bad token, function not in
the service), not that the content is wrong:

- `accessexception` — the token's service does not carry this function yet.
  On the server: `php public/local/multitopics/cli/app_guest_token.php --create`
  adds it to the guest service.
- `invalidfunction` / `invalidrecord` — the plugin has not been upgraded on
  that server (`admin/cli/upgrade.php`).
