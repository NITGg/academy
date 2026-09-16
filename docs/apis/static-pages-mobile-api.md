# Static pages (About, Contact, Terms, Privacy, Refund, FAQ) — Mobile API

Two functions. `local_profilefields_get_static_pages` lists the site's published
static pages for a menu; `local_profilefields_get_static_page` returns one page
as data. The **About** page returns an extra `about` block — hero picture, hero
video, tagline, facts, principles, milestones — so the app can draw the same
screen natively instead of showing the web page.

| Website page | Function |
|---|---|
| `/local/profilefields/manage.php?tab=page<slug>` (the editor) | — |
| `/local/profilefields/page.php?page=<slug>` | `local_profilefields_get_static_page` |
| The footer's page links | `local_profilefields_get_static_pages` |

The admin edits one tab; the web site and the app both read it. **Do not
hard-code any of this in the app.**

---

## Transport

```
GET {WWWROOT}/webservice/rest/server.php
  ?wstoken=TOKEN
  &wsfunction=local_profilefields_get_static_page
  &moodlewsrestformat=json
  &page=about
  &lang=ar
```

- `WWWROOT` staging/prod: `https://academy2026.nitg-eg.com/moodle-new`
- **Callable before login** — the shared registration token, or no token at all
  through `/lib/ajax/service-nologin.php`, like `local_profilefields_get_footer`.
- `page` (required): `about | contact | terms | privacy | refund | faq`.
- `lang` (or `alang`, optional): `en` or `ar`. Omit it and the caller's own
  language is used. A field written in one language only comes back in that
  language — the same fallback the web page uses — never empty.

**Cache it** per language; it changes only when an administrator saves the tab.

---

## `local_profilefields_get_static_pages` — the menu

```json
[
  {"slug": "about",   "kind": "content", "title": "About EAAC",  "url": "https://…/page.php?page=about"},
  {"slug": "contact", "kind": "contact", "title": "Contact us",  "url": "https://…/page.php?page=contact"},
  {"slug": "terms",   "kind": "policy",  "title": "Terms and conditions", "url": "…"},
  {"slug": "faq",     "kind": "faq",     "title": "Frequently asked questions", "url": "…"}
]
```

Unpublished pages are absent. `kind` says how to draw the page (below).

---

## `local_profilefields_get_static_page` — one page

```json
{
  "slug": "about",
  "kind": "content",
  "published": true,
  "title": "Who we are",
  "content": "<p>EAAC was set up by engineers …</p><h2>How we teach</h2>…",
  "url": "https://academy2026.nitg-eg.com/moodle-new/local/profilefields/page.php?page=about",
  "policyname": "",
  "policyversionid": 0,
  "contact": [],
  "social": [],
  "mapembed": "",
  "maplink": "",
  "faq": [],
  "about": {
    "tagline": "Engineering and industry, taught by practitioners",
    "lede": "EAAC trains engineers and technicians for the plant floor: short, practical courses …",
    "heroimage": "https://…/pluginfile.php/1/local_profilefields/pagehero/0/hero.jpg",
    "coursesurl": "https://academy2026.nitg-eg.com/moodle-new/local/nit_category/catalogue.php",
    "contacturl": "https://academy2026.nitg-eg.com/moodle-new/local/profilefields/page.php?page=contact",
    "video": {
      "provider": "file",
      "url": "https://…/pluginfile.php/1/local_profilefields/pagevideo/0/workshop.mp4",
      "embed": "https://…/pluginfile.php/1/local_profilefields/pagevideo/0/workshop.mp4",
      "mimetype": "video/mp4"
    },
    "facts": [
      {"value": "2009",     "label": "Founded"},
      {"value": "5,000+",   "label": "Learners"},
      {"value": "ISO 9001", "label": "Accredited"},
      {"value": "24/7",     "label": "Support"}
    ],
    "pillars": [
      {"icon": "fa-solid fa-gears", "title": "Hands on",
       "text": "Every course is built around a real task on real equipment, not a slide deck."}
    ],
    "milestones": [
      {"year": "2009", "title": "Founded in Cairo",
       "text": "Three workshops, one classroom and a first cohort of forty technicians."}
    ]
  },
  "warnings": []
}
```

### Common fields (every page)

| Field | Use |
|---|---|
| `published` | `false` means the page is switched off — hide the entry; every other field is then empty. |
| `title` | Screen title, in the requested language. |
| `content` | The body as **formatted HTML** with image URLs already resolved. Render in a WebView or an HTML widget; it may be empty on a page that is only its lists. |
| `url` | The page on the web site — for a "share" action or a WebView fallback. |
| `kind` | `content` (About), `contact`, `policy` (Terms / Privacy / Refund), `faq`. |
| `policyname`, `policyversionid` | Legal pages only: the tool_policy document being shown and its exact revision (matches `local_profilefields_get_policy_documents`). |
| `contact`, `social`, `mapembed`, `maplink` | Contact page only — the same rows as the footer, plus the map. Prefer `maplink` (opens the device's map app) over embedding `mapembed`. |
| `faq` | FAQ page only: `{id, question, answer(HTML)}` in display order. |

### `about` — About page only

Absent on every other page (not `null`, not `{}` — the key is missing), so
check `"about" in response`.

| Field | Use |
|---|---|
| `tagline` | One short line drawn **above** the title, in the accent colour. May be empty — draw nothing. |
| `lede` | Two or three sentences under the title, plain text. May be empty. |
| `heroimage` | The hero picture URL, or `""`. Landscape; the web crops it to 5:4. |
| `video` | The hero video. `provider` is `"file"` when there is one, `""` when there is none. `url` is a direct `pluginfile.php` address of the uploaded file (`mimetype` says which — mp4 / webm / ogv), playable by the native player (AVPlayer / ExoPlayer / `video_player`). `embed` equals `url`. **When both `heroimage` and `video` are set, show the picture with a play button and start the video only when it is pressed, using the picture as the poster** — that is what the web does. Do not autoplay. |
| `facts` | The strip of figures under the picture: `{value, label}`, up to 6, in display order. Draw as cells — small label over a large value. Empty list → no strip. |
| `pillars` | "What we stand for": `{icon, title, text}`, up to 6. `icon` is a FontAwesome 6 class string (`fa-solid fa-gears`) or `""`; map it to your icon set, or draw a tick when empty. |
| `milestones` | The history: `{year, title, text}`, up to 10, **in the order the administrator listed them** (do not sort). |
| `coursesurl` | Where the page's **Browse courses** button goes on the web — the course catalogue. A native client opens its own catalogue screen; keep the URL for a WebView fallback. |
| `contacturl` | Where **Contact us** goes — the Contact static page (`page=contact`). `""` when that page is unpublished — hide the button. |

### Screen order on the web (mirror it)

1. Tagline → title → lede → [Browse courses] [Contact us]
2. Hero picture / video with the facts strip under it
3. "Our story" — `content`
4. "What we stand for" — `pillars` grid
5. "Milestones" — `milestones` list
6. Closing card: "Ready to start learning?" → Browse courses

The section headings are the app's own strings (`Our story`, `What we stand
for`, `Milestones`) — they are not returned, and a section whose list is empty
is not drawn.

### Text handling

`title`, `tagline`, `lede`, fact/pillar/milestone strings are **plain text**
(already run through `format_string`, entities decoded — do not HTML-decode
again). `content` and `faq[].answer` are HTML.
