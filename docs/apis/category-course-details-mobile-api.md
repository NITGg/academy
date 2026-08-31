# Category & Course Details — Mobile API Guide

Everything the app needs for **(a) category images/icons** and **(b) the extra
course information** (prerequisites, ILOs, hours, audience, skills, certificate…)
that the web course page shows but stock Moodle web services do not describe.

Reference screen (this is exactly what the doc maps to):
<https://academy2026.nitg-eg.com/moodle-new/course/view.php?id=9>

You already call:

| You call | It gives you | It does **not** give you |
|----------|--------------|--------------------------|
| `core_course_get_categories` | the category tree (id, name, parent, path, depth, description, `coursecount`) | **the category image**, **the category icon** |
| `GET /local/multitopics/getalltopics.php` | sections / topics / activities of a course you are **enrolled in** | any course-detail field — `other_fields` is always `{}` |

So two more calls are needed. Both are documented below.

- **Owner plugins:** `local_nit_category` (category media), `theme_nit` (course detail page), core course custom fields
- **Site wwwroot used in the examples:** `https://academy2026.nitg-eg.com/moodle-new` — read it from your existing base-URL config, never hard-code it.

---

## 0. Cheat sheet — what you need → where you get it

| Data on the screen | Source |
|---|---|
| Category name / tree / parent | `core_course_get_categories` |
| **Category image (hero)** | `GET /local/nit_category/home.php?function=get_categories` → `image` |
| **Category icon (small glyph or emoji)** | same call → `icon` |
| Course title, summary, category name | `core_course_get_courses_by_field` |
| **Course cover image** | `core_course_get_courses_by_field` → `overviewfiles[0].fileurl` |
| Instructors (name + role) | `core_course_get_courses_by_field` → `contacts[]` |
| **Prerequisites / Target audience / ILOs / Skills / Hours / Language / Certificate / Free** | `core_course_get_courses_by_field` → `customfields[]` (see §2.2) |
| Modules & activities | `getalltopics.php` (enrolled only) |
| Price / free flag | `local_payments_get_course_price`, `local_academy` `is_course_free` |

---

# 1. Categories

## 1.1 What `core_course_get_categories` returns

```
id, name, idnumber, description, descriptionformat, parent, sortorder,
coursecount, visible, visibleold, timemodified, depth, path, theme
```

There is **no image and no icon field**, and there never will be: the Moodle
`course_categories` table has no image column and categories are not a
custom-field area. Our plugin `local_nit_category` stores the image and the icon
in its own file areas inside the category context, and exposes them through the
feed below.

Keep using `core_course_get_categories` for the **tree** (parents, depth, path,
`coursecount`) — just merge the image/icon in from §1.2 by `id`.

## 1.2 Category image + icon feed

```
GET {wwwroot}/local/nit_category/home.php?function=get_categories&limit=50&alang=ar
```

| Param | Type | Default | Meaning |
|-------|------|---------|---------|
| `function` | string | — | must be `get_categories` |
| `limit` | int | `12` | max rows, clamped to **1..50** |
| `alang` | `ar` \| `en` | session lang | language the names come back in |

**Auth:** none. No token, no cookie, no sesskey. (If an admin ever turns
`forcelogin` on site-wide, this endpoint starts requiring a session — treat a
redirect/HTML response as "not available" and fall back to the category name
only.)

**Method:** `GET` only. `Content-Type: application/json; charset=utf-8`.

### Response

```jsonc
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "البرمجة",
      "url": "https://academy2026.nitg-eg.com/moodle-new/local/nit_category/index.php?id=1",
      "coursecount": 8,
      "image": "https://academy2026.nitg-eg.com/moodle-new/pluginfile.php/3/local_nit_category/categoryimage/%D8%B5%D9%88%D8%B1-%D8%B3%D9%8A%D8%A7%D8%B1%D8%A7%D8%AA-7.jpg",
      "icon":  "https://academy2026.nitg-eg.com/moodle-new/pluginfile.php/3/local_nit_category/categoryicon/%D8%B5%D9%88%D8%B1-%D8%B3%D9%8A%D8%A7%D8%B1%D8%A7%D8%AA-41.jpg"
    }
  ]
}
```

Errors use the same envelope: `{"status":"error","error":"…"}`.

| Field | Type | Notes |
|-------|------|-------|
| `id` | int | matches `core_course_get_categories.id` — this is your join key |
| `name` | string | already localised and already `{mlang}`-resolved for `alang` |
| `url` | string | the **web** category page; open in a WebView or ignore |
| `coursecount` | int | **recursive** — courses in this category *and its subcategories*, visibility-filtered |
| `image` | string | absolute URL, or `""` when the category has no image anywhere up the tree |
| `icon` | string | absolute URL **or a single emoji** or `""` — see below |

### Reading `icon` correctly

`icon` is deliberately overloaded, because an admin may set either an uploaded
glyph or just an emoji:

```dart
final isUrl = icon.startsWith('http');
// isUrl  → load as image
// else if icon.isNotEmpty → render as text (emoji), font-size ≈ 26, square 32×32
// else   → no icon; show the name alone
```

The uploaded file always wins over the emoji. Icons are **not** inherited from
parent categories — an icon identifies one category.

### How `image` is resolved (server side)

The URL you get is the first hit of this chain, so you do not need to implement
any of it — but you should know why a category can show its parent's picture:

1. the image uploaded on *Category → Category image & icon*
2. else the **first `<img>` inside the category description**
3. else the same two steps on the nearest **ancestor** category (branding is inherited)
4. else `""` → the app should fall back to the site logo, same as the web does

### Fetching the image bytes

`image` / `icon` are plain `pluginfile.php` URLs and are **publicly readable** —
no `token=` query parameter, no cookie. A plain `GET` works, and the server sends
`Cache-Control` for 1 hour. Do **not** rewrite them to
`/webservice/pluginfile.php`, and do **not** re-encode them: the filenames are
already percent-encoded UTF-8 (`%D8%B5…`), so encoding again gives a 404.

### Known limits of this feed

- **Top-level categories only.** A subcategory never appears here.
- **Empty categories are skipped** (`coursecount == 0`) — they are dead ends on the home grid.
- Hard cap of 50 rows.

For a **subcategory** image, do what the web does: walk up `path` /
`parent` from `core_course_get_categories` to the top-level ancestor and use that
ancestor's `image`. That is the same picture the website would show. (If a
subcategory has its own picture pasted into its *description*, you can also pull
the first `<img src>` out of the `description` HTML that
`core_course_get_categories` already returns.)

## 1.3 The other two functions on this endpoint

`home.php` also answers `function=get_my_courses` and `function=get_continue`.
They identify the user from the **web session cookie**, not from a web-service
token, so a token client gets an empty list rather than an error. Use
`core_enrol_get_users_courses` / your existing enrolment calls instead.

---

# 2. Course details

## 2.1 One call gets the whole detail screen

```
GET {wwwroot}/webservice/rest/server.php
    ?wstoken={token}
    &wsfunction=core_course_get_courses_by_field
    &moodlewsrestformat=json
    &field=id&value=9
    &moodlewssettingfilter=1
    &moodlewssettinglang=ar
```

Works for a course the user is **not** enrolled in — this is the call for the
product/preview screen. (`getalltopics.php` answers `403 nopermissions` when the
user is not enrolled, so it cannot back that screen.)

Relevant parts of the response:

```jsonc
{
  "courses": [{
    "id": 9,
    "fullname": "test",
    "displayname": "test",
    "shortname": "test",
    "categoryid": 1,
    "categoryname": "Programming",
    "summary": "<p>…</p>",
    "summaryformat": 1,
    "overviewfiles": [
      { "filename": "cover.jpg",
        "fileurl": "https://…/webservice/pluginfile.php/…/course/overviewfiles/cover.jpg",
        "filesize": 123456, "mimetype": "image/jpeg", "timemodified": 1770000000 }
    ],
    "contacts": [ { "id": 2, "fullname": "Admin User" } ],
    "enrollmentmethods": [ "manual", "self" ],
    "customfields": [
      { "name": "Prerequisites", "shortname": "prerequisites",
        "type": "text", "value": "No Prerequisites", "valueraw": "No Prerequisites" }
    ]
  }],
  "warnings": []
}
```

- **Course cover:** `overviewfiles[0].fileurl`. This one **is** a
  `/webservice/pluginfile.php` URL, so append `&token={wstoken}` (use `?` if the
  URL has no query string yet) before loading it.
- **Instructors:** `contacts[]` gives id + fullname without needing enrolment.
  For the full instructor profile (bio, photo, subjects, courses taught) call
  `GET /local/academy/api.php?function=get_teacher&teacherid={id}&token={token}`.

## 2.2 The custom-field catalogue

These are course custom fields under the **"Other fields"** category. They are
the entire content of the branded course page; the app should render them the
same way.

| `shortname` | `type` | Web course page | Render as |
|---|---|---|---|
| `course_fields` | text | hero subject line + **"Skills you'll gain"** | one **list** — split (see §2.4, with commas) |
| `total_number_of_hours` | number | **Duration** row ("5 hours") | number + "hour(s)" |
| `language` | text | **Language** row | single string |
| `certificate` | checkbox | **Certificate** row + hero badge | bool |
| `free` | checkbox | hero **Free** badge | bool |
| `target_audience` | text | **"Who this course is for"** card | list |
| `prerequisites` | text | **Prerequisites** card | list |
| `ilos` | text | **"What you'll learn"** → intended learning outcomes | list (✓ bullets) |
| `by_the_end_of_training` | text | **"What you'll learn"** → by the end of this program | list (✓ bullets) |

Rules that match the web exactly:

- **A field with no value is not rendered at all.** No em-dash, no "N/A", no
  empty card. If every field of a band is empty, drop the whole band (and its
  tab from the tab bar).
- A field missing from `customfields[]` is the same as empty.
- **Skills fall back to course tags**: when `course_fields` is empty, the web
  shows the course tags instead (`core_tag`/course tags). Do the same, or show
  nothing.

## 2.3 `value` vs `valueraw` — which one to use

| type | use | why |
|---|---|---|
| `checkbox` | **`valueraw`** (`"1"` / `"0"`) | `value` is the *localised word* `"Yes"` / `"No"` — do not compare against it |
| `number` | **`valueraw`** (float, e.g. `5` or `5.0`) | `value` may carry a configured prefix/suffix/decimal formatting. Print whole numbers without `.0` |
| `text` | **`value`** | `value` is `format_string()`-ed (multilang resolved when `moodlewssettingfilter=1`); `valueraw` is the raw stored string |

## 2.4 Parsing rule 1 — multi-value text fields

A single text field can hold several items. The web splits on:

- **`|`** (the canonical separator — the course-edit "chips" editor joins entries with it)
- a newline
- the bullet `•`
- **and additionally `,` and the Arabic comma `،` for `course_fields` only**

Then trims each part and drops empties.

```dart
List<String> chips(String v, {bool alsoCommas = false}) {
  final re = alsoCommas ? RegExp(r'[|\n•،,]+') : RegExp(r'[|\n•]+');
  return v.split(re).map((s) => s.trim()).where((s) => s.isNotEmpty).toList();
}
```

On course 9 the raw `course_fields` value is literally
`programming|problem solving` → two chips: `programming`, `problem solving`.

> `alsoCommas` is **only** for `course_fields`. Turning it on for
> `prerequisites` or `ilos` would shred sentences that legitimately contain a
> comma.

## 2.5 Parsing rule 2 — bilingual `{mlang}` values

Admins author bilingual content with the multilang syntax (the
`local_nit_mlang` editor writes it for them):

```
{mlang en}Certificate of Success{mlang}{mlang ar}شهادة نجاح{mlang}
```

Web-service calls have **filters OFF by default** (`moodlewssettingfilter=0`), so
these tags come back *raw* unless you ask for them to be resolved. Two things to
do, both of them:

1. Always send `&moodlewssettingfilter=1&moodlewssettinglang=ar|en` on
   `core_course_get_courses_by_field` (the site's multilang filter is installed
   and enabled, so this resolves them server-side).
2. **Still** run this fallback client-side, because a value can reach you
   unfiltered from other endpoints. It mirrors the web renderer exactly:

   - find every `{mlang <langs>}…{mlang}` block (langs is a comma list, case-insensitive)
   - if any block lists the current language → concatenate those blocks
   - else if any block lists `other` → concatenate those
   - else → use the **first** block (never show nothing)
   - if the string has no `{mlang` at all → use it as-is

   Do this **before** splitting into chips (§2.4), which is the order the web uses.

## 2.6 Course detail screen — field-by-field map of `course/view.php?id=9`

| Band on the web | Data |
|---|---|
| Breadcrumb "Browse › Programming" | `categoryname` + the category chain from `core_course_get_categories` |
| Hero: provider label | top-level ancestor category name |
| Hero: title | `fullname` |
| Hero: subject line | `course_fields` (raw string, not split) |
| Hero: `Free` badge | `free` checkbox |
| Hero facts: Instructor / Enrolled / Starts | `contacts[0].fullname` (+ "and N more"), enrolled count, `startdate` |
| Hero CTA | Enroll → your enrolment/checkout flow |
| **At a glance**: Modules | count of visible sections (from `getalltopics.php`, enrolled) or `numsections` |
| At a glance: Duration | `total_number_of_hours` |
| At a glance: Assessments | count of `assign` + `quiz` + `workshop` + `lesson` activities |
| At a glance: Language | `language` |
| At a glance: Certificate | `certificate` checkbox → "Shareable certificate" |
| **What you'll learn** | `ilos` chips + `by_the_end_of_training` chips |
| **Skills you'll gain** | `course_fields` chips (commas included), else course tags |
| **Requirements** → Who this course is for | `target_audience` chips |
| **Requirements** → Prerequisites | `prerequisites` chips |
| **Instructors** | `contacts[]`, enriched via `local_academy` `get_teacher` |
| **Offered by** | category name + category image/icon from §1.2 |
| **Modules** accordion | `getalltopics.php` → `parents[] → topics[] → activities[]` |

---

# 3. `getalltopics.php` — what it does and does not carry

```
GET {wwwroot}/local/multitopics/getalltopics.php?courseid=9&wstoken={token}
```

- **`other_fields` is always an empty object `{}`.** It is a reserved slot. Do
  not look for `prerequisites` / `ilos` / hours there — they come from
  `core_course_get_courses_by_field` (§2.1).
- It requires the caller to be **enrolled** (or hold `moodle/course:view`),
  otherwise `403 nopermissions`. The preview/product screen for a course the
  user has not bought must therefore be built from §2.
- What it *does* give you, and is worth re-reading if you have not: `parents[]`
  (top-level sections) each with `topics[]` (child sections) and `activities[]`,
  and per-activity `mediatype` / `fileurl` / `isvdocipher` + `otpurl` /
  `iscertificate` + `downloadurl` / `nativerender.requires` (quiz question types)
  / `restricted` + `restrictioninfo` / `submissionstatus` + `submissionwindow`
  (assignments).

---

# 4. Gotchas checklist

- [ ] `core_course_get_categories` has **no image** — always merge §1.2 in by `id`.
- [ ] `icon` may be an **emoji**, not a URL. Check `startsWith('http')`.
- [ ] Category image/icon URLs are `pluginfile.php` and need **no token**; course
      `overviewfiles` URLs are `webservice/pluginfile.php` and **do** need `&token=`.
- [ ] Never re-encode the percent-encoded Arabic filenames in those URLs.
- [ ] The category feed returns **top-level, non-empty** categories only; inherit
      the ancestor's image for subcategories.
- [ ] `certificate` / `free` → read `valueraw` (`"1"`/`"0"`), never `value`
      (`"Yes"`/`"No"`, localised).
- [ ] `total_number_of_hours` → `valueraw`, print `5` not `5.0`.
- [ ] Split multi-value text fields on `| \n •`; add `, ،` **only** for `course_fields`.
- [ ] Resolve `{mlang}` before splitting, and send
      `moodlewssettingfilter=1&moodlewssettinglang=…`.
- [ ] Empty field → render nothing at all (no placeholder, no empty card, no tab).
- [ ] `getalltopics.php` `other_fields` is `{}` by design.

---

# 5. Where this lives in the code

| Thing | File |
|---|---|
| Category image/icon storage, fallback chain, pluginfile serving | `public/local/nit_category/lib.php` |
| Category media admin page | `public/local/nit_category/image.php` |
| The `get_categories` JSON feed | `public/local/nit_category/home.php`, `public/local/nit_category/classes/home.php` |
| Course-detail page (the reference rendering, incl. chip/mlang parsing) | `public/theme/nit/classes/output/format_topics_renderer.php` |
| Chips editor that writes `|`-joined values | `public/local/nit_core/classes/hook/output_callbacks.php` |
| Bilingual field editor | `public/local/nit_mlang/README.md` |
| Course structure feed | `public/local/multitopics/getalltopics.php` |
