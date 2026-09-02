# Site footer — Mobile API

One function, `local_profilefields_get_footer`, returns everything the footer
band at the bottom of every web page says — contact details, the two link
columns, the social links, the logo and the copyright line — so the app can draw
the same footer natively.

| Website page | Function |
|---|---|
| `/local/profilefields/manage.php?tab=footer` (the editor) | — |
| The footer on every page of the site | `local_profilefields_get_footer` |

The admin edits that one tab; the web site and the app both read it. **Do not
hard-code any of this in the app**: an administrator who changes the phone
number, adds a link or switches the footer off changes both surfaces at once,
with no app release.

---

## Transport

```
GET {WWWROOT}/webservice/rest/server.php
  ?wstoken=TOKEN
  &wsfunction=local_profilefields_get_footer
  &moodlewsrestformat=json
  &lang=ar
```

- `WWWROOT` staging/prod: `https://academy2026.nitg-eg.com/moodle-new`
- **Callable before login.** The footer is on the site's public pages, so it is
  reachable with the shared registration token, or with no token at all through
  `/lib/ajax/service-nologin.php` — the same as
  `local_profilefields_get_signup_form` and
  `local_profilefields_get_policy_documents`.
- `lang` (or `alang`) is optional: `en` or `ar`. Omit it and the caller's own
  language is used.

**Cache it.** This is one read of site config — cheap, but it changes only when
an administrator edits the tab. Fetch it once at start-up (and again when the
user switches language) rather than on every screen.

---

## Response

```json
{
  "enabled": true,
  "contactheading": "Contact information",
  "contact": [
    {"key": "address", "icon": "fa-solid fa-location-dot",
     "text": "12 El-Nasr Road, Nasr City, Cairo", "url": ""},
    {"key": "phone",   "icon": "fa-solid fa-phone",
     "text": "+20 100 123 4567", "url": "tel:+201001234567"},
    {"key": "hours",   "icon": "fa-solid fa-clock",
     "text": "Sun–Thu, 9:00–17:00", "url": ""},
    {"key": "email",   "icon": "fa-solid fa-envelope",
     "text": "info@nitg-eg.com", "url": "mailto:info@nitg-eg.com"}
  ],
  "columns": [
    {"key": "col2", "heading": "Explore", "links": [
      {"label": "About us",   "url": "https://…/local/nit_core/page.php?page=about"},
      {"label": "Courses",    "url": "https://…/course/"},
      {"label": "Contact us", "url": "https://…/local/nit_core/page.php?page=contact"}
    ]},
    {"key": "col3", "heading": "Useful links", "links": [
      {"label": "Terms and conditions", "url": "https://…/local/nit_core/page.php?page=terms"},
      {"label": "Privacy policy",       "url": "https://…/local/nit_core/page.php?page=privacy"},
      {"label": "Refund policy",        "url": "https://…/local/nit_core/page.php?page=refund"}
    ]}
  ],
  "social": [
    {"network": "facebook",  "name": "Facebook",  "icon": "fa-brands fa-facebook-f",
     "url": "https://facebook.com/…"},
    {"network": "instagram", "name": "Instagram", "icon": "fa-brands fa-instagram",
     "url": "https://instagram.com/…"}
  ],
  "logourl": "https://…/pluginfile.php/1/core_admin/logocompact/200x200/logo.png",
  "sitename": "NIT Academy",
  "copyright": "© 2026 NIT Academy. All rights reserved.",
  "warnings": []
}
```

### Field by field

| Field | What to do with it |
|---|---|
| `enabled` | `false` means the site has switched the footer off — **draw nothing**. The other fields are then empty, so you do not have to guard each one. |
| `contactheading` | Heading over the contact column. May be `""` — then draw no heading. |
| `contact[]` | The contact rows, already in display order. |
| `contact[].key` | `address`, `phone`, `hours` or `email`. **Branch on this**, never on the label or the icon. |
| `contact[].icon` | FontAwesome 6 classes, for a client that renders FA. Otherwise pick your own icon from `key`. |
| `contact[].text` | The line to show, in the requested language. |
| `contact[].url` | `tel:…` on the phone row, `mailto:…` on the email row, `""` on the rest. Non-empty ⇒ make the row tappable. |
| `columns[]` | The link columns, in display order. |
| `columns[].key` | `col2` or `col3` — stable identifiers, in case you lay the two out differently. |
| `columns[].heading` | May be `""`. |
| `columns[].links[].url` | **Already absolute.** Never prepend the wwwroot. |
| `social[]` | The social links, in display order. |
| `social[].network` | `facebook`, `instagram`, `linkedin`, `twitter`, `youtube`, `tiktok`, `whatsapp`, `telegram`. Choose your icon asset from this. |
| `social[].name` | The brand's own name (`LinkedIn`, `X`, `TikTok`) — use it as the accessibility label on an icon-only button. |
| `logourl` | The site logo, already resolved. Same image the web footer shows. |
| `sitename` | Site full name, shown beside the logo. |
| `copyright` | One finished sentence, with the year already substituted in. Do not build it yourself. |

### Empty means absent, not blank

Anything an administrator left empty is **missing from the list**, not present
with an empty value:

- a contact row with no text set → not in `contact[]`
- a link column with no heading and no links → not in `columns[]`
- a network with no URL set → not in `social[]`

So render whatever arrives, in the order it arrives. Never assume four contact
rows, two columns, or eight social icons — today's site has some of each, and
that is an editorial decision that will change.

### Language

Every string comes back **already in one language**; there is no `{mlang}`
markup to parse and no `_en` / `_ar` pair to choose between. A field the
administrator wrote in only one language falls back to that one rather than
coming back empty, so a half-translated footer still reads.

Ask again with the other `lang` when the user switches language in the app.

---

## Errors

There is no per-field failure mode here — it is a read of site settings.
`warnings` is present for consistency with the rest of the API and is always
empty today. An exception means the transport failed (bad token, function not in
the service), not that the content is wrong.
