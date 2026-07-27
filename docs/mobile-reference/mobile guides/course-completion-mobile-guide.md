# Course Completion & Progress — Mobile Developer Guide

How to reproduce the web **course completion** experience (the one on
`https://academy2026.nitg-eg.com/course/view.php?id=62`) inside the mobile app.

Platform: **Moodle 3.11.8**.

## TL;DR — use the app's existing endpoint

The mobile app already loads the course through the custom endpoint
**`/local/multitopics/getalltopics.php`**. That endpoint has been extended so **one call
returns 7 of the 8 points** below (course structure + progress + per-activity completion +
restrictions + expected dates). See **§A** for the full response and field mapping — this is
the recommended path.

The **only** point it cannot do is **#2's write action** (ticking a manual checkbox), because
`getalltopics.php` is read-only. For that one action call the core function
`core_completion_update_activity_completion_status_manually` (see **§2**).

| #   | Feature                             | From `getalltopics.php` (§A)                          | Core WS fallback                                                 |
| --- | ----------------------------------- | ----------------------------------------------------- | ---------------------------------------------------------------- |
| 1   | View Activity Completion            | activity `completionstate`, `hascompletion`           | `core_completion_get_activities_completion_status`               |
| 2   | Manual Completion — **display**     | activity `completion == 1`                            | (same)                                                           |
| 2   | Manual Completion — **tick/untick** | ✗ not possible (read-only)                            | `core_completion_update_activity_completion_status_manually`     |
| 3   | Automatic Completion                | activity `isautomatic`, `completiondetails[]`         | `core_completion_get_activities_completion_status` → `details[]` |
| 4   | Course Progress (%)                 | top-level `progress`                                  | `core_course_get_enrolled_courses_by_timeline_classification`    |
| 5   | Course Completion Status            | top-level `course_completed`, `completion_criteria[]` | `core_completion_get_course_completion_status`                   |
| 6   | Restricted Activities               | activity/section `locked`, `availabilityinfo`         | `core_course_get_contents` → `uservisible` / `availabilityinfo`  |
| 7   | Expected Completion Date            | activity `completionexpected`                         | `core_course_get_course_module` (`completionexpected`)           |
| 8   | Completion Dashboard                | the whole response — one call                         | Combine 1 + 4 + 5                                                |

Sections **§1–§10** document the underlying core functions in detail. Even when you use
`getalltopics.php`, read them for **field meanings** (state values, rule names, error codes) —
they are identical, because `getalltopics.php` is built on the same Moodle completion APIs.

---

## 0. Transport basics

All calls go to the standard Moodle web-service REST endpoint:

```
POST /webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded
```

Every request must include:

```
wstoken            = <user token>        # from /login/token.php, service = moodle_mobile_app
moodlewsrestformat = json
wsfunction         = <function name>
```

Get the token once at login, then read `userid` once:

```
POST /login/token.php
  username = <username>
  password = <password>
  service  = moodle_mobile_app
→ { "token": "abc123..." }

wsfunction = core_webservice_get_site_info   → { "userid": 501, ... }
```

> Throughout this guide `courseid = 62` and `userid = 501` are placeholders — use the
> real course id from the screen and the `userid` from `core_webservice_get_site_info`.

**Important precondition:** completion features only return data when **completion
tracking is enabled** for the course (Course settings → _Enable completion tracking = Yes_).
If it is disabled, `core_completion_get_course_completion_status` throws
`nocriteriaset` and `completiondata` is simply omitted from activities. Always handle
that gracefully (see §9).

---

## A. All-in-one: `/local/multitopics/getalltopics.php`

This is the custom endpoint the app already uses to render the course screen. It now
returns everything needed for points **1, 3, 4, 5, 6, 7 and 8** in a single request.

### Request

```
GET /local/multitopics/getalltopics.php?courseid=62&wstoken=<token>
```

- `wstoken` — same mobile token as everywhere else (validated against `external_tokens`).
- `courseid` — the course id.
- No `userid` needed — the endpoint derives the user from the token, so all completion,
  progress and restriction values are already computed **for the logged-in student**.

### Response (completion-relevant fields)

```jsonc
{
  "courseid": 62,
  "fullname": "Mathematics — Grade 10",
  "shortname": "MATH10",
  "format": "multitopics",
  "isavailable": true,
  "status": "available",

  // ── Course completion (points 4 & 5) ───────────────────────────────
  "completion_enabled": true, // false → course doesn't track completion; hide the UI
  "progress": 42.86, // point 4 — percentage 0..100 (float), or null
  "course_completed": false, // point 5 — true / false / null(when disabled)
  "completion_criteria": [
    // point 5 — one row per course-completion rule
    {
      "type": 4, // 4 activity · 6 date · 8 grade · 1 self · 0 self-role ...
      "title": "Quiz: Final exam",
      "complete": true,
      "timecompleted": 1721300000, // unix ts, 0 if not complete
    },
  ],

  "other_fields": {
    /* live Jitsi session, unchanged */
  },

  "parents": [
    {
      "id": "12",
      "sectionnum": 1,
      "name": "Unit 1",
      "parent": true,
      // ── Restricted sections (point 6) ──────────────────────────────
      "uservisible": true,
      "locked": false, // true → section gated; show availabilityinfo
      "availabilityinfo": "", // restriction message (HTML), empty when open
      "activities": [
        {
          "id": "1450",
          "modname": "quiz",
          "name": "Chapter 2 quiz",
          "sectionnum": "1",
          "visible": true,
          "uservisible": false,

          // ── Restricted activity (point 6) ──────────────────────────
          "locked": true, // true → greyed-out / not tappable
          "availabilityinfo": "Not available unless: You achieve a required score in <b>Chapter 1 quiz</b>",

          "url": "https://.../mod/quiz/view.php?id=1450",
          "modicon": "https://.../icon",
          "resourcetype": "",
          "fileurl": "", // NOTE: empty for locked activities (no content leak)

          // ── Activity completion (points 1, 3, 7) ───────────────────
          "completion": 2, // 0 none · 1 manual · 2 automatic
          "completionexpected": 1722384000, // point 7 — unix ts, 0 when not set
          "hascompletion": true, // point 1 — completion configured for this activity
          "isautomatic": true, // point 3 — true=auto · false=manual(point 2)
          "completionstate": 0, // point 1 — 0 incomplete·1 complete·2 pass·3 fail
          "completiondetails": [
            // point 3 — automatic rules (empty for manual)
            {
              "rulename": "completionview",
              "status": 1,
              "description": "View",
            },
            {
              "rulename": "completionpassgrade",
              "status": 0,
              "description": "Receive a passing grade",
            },
          ],
        },
      ],
      "topics": [
        {
          "id": "18",
          "sectionnum": 2,
          "name": "Sub-topic 1.1",
          "uservisible": true,
          "locked": false,
          "availabilityinfo": "",
          "activities": [
            /* same activity shape as above */
          ],
        },
      ],
    },
  ],
}
```

### Field mapping — the 8 points

| #   | Point                         | Where in the response                                                                                              |
| --- | ----------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| 1   | View Activity Completion      | each activity: `hascompletion`, `completionstate` (0·1·2·3), `completion`                                          |
| 2   | Manual Completion — _display_ | each activity: `completion == 1` (or `hascompletion && !isautomatic`)                                              |
| 2   | Manual Completion — _tick_    | **not here** → call `core_completion_update_activity_completion_status_manually` (§2), then re-fetch this endpoint |
| 3   | Automatic Completion          | each activity: `isautomatic == true`, `completiondetails[]` (rule + `status` 0/1)                                  |
| 4   | Course Progress               | top-level `progress` (0..100 or null); render a bar only when not null                                             |
| 5   | Course Completion Status      | top-level `course_completed` + `completion_criteria[]`                                                             |
| 6   | Restricted Activities         | activity/section `locked == true` + `availabilityinfo` text                                                        |
| 7   | Expected Completion Date      | each activity: `completionexpected` (unix ts; 0 = none)                                                            |
| 8   | Completion Dashboard          | the whole payload — build the screen from this one call                                                            |

### Rendering rules (mirror the website)

- **Activity tick** — drive from `completionstate`: `0` empty box · `1`/`2` green tick · `3`
  red (completed-fail). Show no completion UI when `hascompletion == false`.
- **Manual vs automatic** — `completion == 1` (manual) → tappable checkbox that calls §2.
  `completion == 2` / `isautomatic == true` → read-only; list `completiondetails` as a
  sub-checklist (each `status: 1` met, `0` not met).
- **Locked (point 6)** — when `locked == true`, render the activity/section greyed-out and
  show `availabilityinfo` as the reason; do **not** navigate into it. `fileurl` and any
  meeting token are intentionally blank for locked activities.
- **Expected date (point 7)** — if `completionexpected > 0`, show "Expected by <date>"; if it
  is in the past and `completionstate == 0`, mark it overdue.
- **Progress (point 4)** — show the bar only when `progress != null`. `progress` is already
  rounded server-side to Moodle's own calculation, so don't recompute it.
- **Course status (point 5)** — `course_completed == true` → "Course completed" badge; list
  `completion_criteria[]` as the criteria checklist (each `complete` drives its tick).

### The one gap — manual toggle

`getalltopics.php` is **read-only**: it shows whether an activity is manual and its current
state, but it cannot change it. When the student taps a manual checkbox:

```
POST /webservice/rest/server.php
  wstoken            = <token>
  moodlewsrestformat = json
  wsfunction         = core_completion_update_activity_completion_status_manually
  cmid               = 1450          # the activity "id" from getalltopics
  completed          = 1             # 1 complete · 0 incomplete
→ { "status": true, "warnings": [] }
```

Then re-call `getalltopics.php` (or update the tick optimistically) so `progress` and
`completionstate` refresh. Full details of this call are in **§2**.

---

## 1. View Activity Completion

Shows, per activity, whether the student has completed it — this is the checkbox / tick
you see next to each activity on the course page.

**Function:** `core_completion_get_activities_completion_status`

### Request

```
wsfunction = core_completion_get_activities_completion_status
courseid   = 62
userid     = 501
```

### Response

```jsonc
{
  "statuses": [
    {
      "cmid": 1450, // course-module id → use for manual toggle (§2)
      "modname": "quiz",
      "instance": 88,
      "state": 1, // 0 incomplete · 1 complete · 2 complete-pass · 3 complete-fail
      "timecompleted": 1721300000, // unix ts, 0 if not completed
      "tracking": 2, // 0 none · 1 manual · 2 automatic
      "overrideby": null, // teacher userid if state was overridden, else null
      "valueused": true, // true = other activities' access depends on this one (see §6)
      "hascompletion": true, // completion is configured for this activity
      "isautomatic": true, // true → automatic (§3), false → manual (§2)
      "istrackeduser": true, // completion is being tracked for this user
      "uservisible": true, // false → activity is restricted/hidden for this user (§6)
      "details": [
        /* completion rules — see §3 */
      ],
    },
  ],
  "warnings": [],
}
```

### How to render each activity's tick

| Show                       | When                                              |
| -------------------------- | ------------------------------------------------- |
| Empty checkbox             | `state == 0`                                      |
| Green tick                 | `state == 1` or `2`                               |
| Red / failed tick          | `state == 3` (completed but did not pass)         |
| Manual (tappable) checkbox | `tracking == 1` (`isautomatic == false`) → see §2 |
| Auto (read-only) badge     | `tracking == 2` (`isautomatic == true`) → see §3  |
| No completion UI at all    | `hascompletion == false`                          |

---

## 2. Manual Completion

Activities set to **"Students can manually mark the activity as completed."** The student
taps the checkbox to toggle it. Only manual activities can be toggled by the app.

Detect a manual activity from §1: `tracking == 1` (equivalently `isautomatic == false`
while `hascompletion == true`).

**Function:** `core_completion_update_activity_completion_status_manually`

### Request — mark complete

```
wsfunction = core_completion_update_activity_completion_status_manually
cmid       = 1450        // the cmid from §1
completed  = 1           // 1 = mark complete, 0 = mark incomplete
```

### Response

```jsonc
{ "status": true, "warnings": [] }
```

### Notes

- Requires capability `moodle/course:togglecompletion` (every enrolled student has it by
  default).
- Calling this on an **automatic** activity returns the error `cannotmanualctrack` —
  never offer a tappable checkbox for `tracking == 2`.
- The call only returns `status`; it does **not** return the new state. After a successful
  toggle, either flip your local UI optimistically, or re-fetch §1 to get the authoritative
  `state` / `timecompleted`.

### UX flow

```
tap checkbox (manual activity)
  → update_activity_completion_status_manually(cmid, completed = !current)
  → on status:true  → update tick + refresh course progress (§4)
  → on error        → revert tick, show message
```

---

## 3. Automatic Completion

Activities that Moodle marks complete on its own when the configured **rules** are met
(e.g. "view the activity", "receive a grade", "make a forum post", "pass the quiz").
These are **read-only** for the app — the student cannot tap them; you display _why_ they
are (or aren't) complete.

Detect from §1: `isautomatic == true` (`tracking == 2`).

The rules live in the `details[]` array of each activity in §1 (and identically inside
`completiondata.details` from `core_course_get_contents`, §6/§7):

```jsonc
"details": [
  {
    "rulename": "completionview",
    "rulevalue": { "status": 1, "description": "View" }         // status 1 = this rule met
  },
  {
    "rulename": "completionusegrade",
    "rulevalue": { "status": 0, "description": "Receive a grade" } // status 0 = not yet met
  },
  {
    "rulename": "completionpassgrade",
    "rulevalue": { "status": 0, "description": "Receive a passing grade" }
  }
]
```

`rulevalue.status`: `1` = requirement satisfied, `0` = not satisfied.
Common `rulename` values: `completionview`, `completionusegrade`, `completionpassgrade`,
`completionminattempts`, `completionsubmit`, plus per-module ones (e.g.
`completionpostsforum`, `completiondiscussionsforum`, `completionrepliesforum`).

### How to render

Show the overall `state` as the activity tick, and list each `details` rule as a small
checklist so the student sees exactly what remains:

```
Quiz: Chapter 1  ⟳ (auto)
  ✓ View
  ○ Receive a passing grade
```

---

## 4. Course Progress

The **percentage bar** ("42% complete") shown on the course card / course header. Moodle
computes it from the ratio of completed _activities that count toward completion_.

There are two ways to get it.

### 4a. Direct % (recommended for lists) — `core_course_get_enrolled_courses_by_timeline_classification`

Returns the student's courses, each already carrying a computed `progress`:

```
wsfunction     = core_course_get_enrolled_courses_by_timeline_classification
classification = all          // all | inprogress | future | past | favourites
limit          = 0
offset         = 0
```

```jsonc
{
  "courses": [
    {
      "id": 62,
      "fullname": "Mathematics — Grade 10",
      "progress": 42,          // 0..100, integer; null when not applicable
      "hasprogress": true,     // false → don't render a bar for this course
      "enddate": 1735680000,
      ...
    }
  ],
  "nextoffset": 1
}
```

Render a progress bar only when `hasprogress == true` and `progress != null`.

### 4b. Compute it yourself (for the single course screen)

If you already fetched §1 for the open course, compute:

```
tracked   = statuses where hascompletion == true
completed = tracked where state == 1 || state == 2   // pass counts; fail (3) does NOT
progress% = round(completed / tracked * 100)          // guard tracked == 0
```

This matches Moodle's own calculation and avoids a second round-trip.

---

## 5. Course Completion Status

Whether the **whole course** is complete, and the breakdown of each completion
**criterion** the teacher configured (activities to finish, a grade to reach, a date, a
manual self-mark, etc.). This is what the web "Course completion" report block shows.

**Function:** `core_completion_get_course_completion_status`

### Request

```
wsfunction = core_completion_get_course_completion_status
courseid   = 62
userid     = 501
```

### Response

```jsonc
{
  "completionstatus": {
    "completed": false, // whole-course complete?
    "aggregation": 1, // 1 = ALL criteria required · 2 = ANY criterion
    "completions": [
      {
        "type": 4, // criteria type (4 = activity, 6 = date, 8 = grade, 1 = self, ...)
        "title": "Quiz: Final exam",
        "status": "Yes", // human string: "Yes" / "No" / a % / a number
        "complete": true, // boolean form of status
        "timecompleted": 1721300000,
        "details": {
          "type": "Activity completion",
          "criteria": "Quiz: Final exam",
          "requirement": "Marked complete",
          "status": "",
        },
      },
    ],
  },
  "warnings": [],
}
```

- `completed` → the big "Course completed ✓ / In progress" state.
- `aggregation` → `1` means the student must satisfy **all** rows; `2` means **any one** row.
- Each `completions[]` row is one criterion → render as a checklist with `complete` driving
  the tick and `details.requirement` explaining what's needed.

**Error to handle:** if completion tracking or criteria aren't set up, this throws
`nocriteriaset` (or `notenroled` if the user isn't enrolled). Treat `nocriteriaset` as
"this course doesn't use course-completion criteria" and hide that section — it does **not**
mean the app is broken. See §9.

---

## 6. Restricted Activities

Activities gated by **access restrictions** ("Not available unless: you belong to group X",
"available from 1 Aug", "requires Quiz 1 complete", etc.). The web page greys these out and
prints the restriction text.

**Function:** `core_course_get_contents`

### Request

```
wsfunction = core_course_get_contents
courseid   = 62
```

### Relevant fields per module

```jsonc
{
  "id": 12,
  "name": "Section 2",
  "modules": [
    {
      "id": 1450,
      "name": "Quiz: Chapter 2",
      "modname": "quiz",
      "uservisible": false, // ← the key flag
      "availabilityinfo": "Not available unless: You achieve a required score in <b>Quiz: Chapter 1</b>",
      "availability": "{\"op\":\"&\",\"c\":[...],\"showc\":[...]}", // raw rule tree, teachers only
      "completion": 2,
      "completiondata": {
        /* same shape as §1/§3 details */
      },
      "dates": [
        /* see §7 */
      ],
    },
  ],
}
```

### How to render

| `uservisible` | `availabilityinfo` | Render                                                                             |
| ------------- | ------------------ | ---------------------------------------------------------------------------------- |
| `true`        | empty              | Normal, tappable activity                                                          |
| `true`        | non-empty          | Tappable, **plus** show the restriction hint (partial restriction, e.g. date-only) |
| `false`       | non-empty          | **Greyed out / locked**, show `availabilityinfo` text, not tappable                |
| `false`       | empty              | Hidden entirely — do not render                                                    |

- `availabilityinfo` is ready-to-display HTML (already formatted for this user) — render it
  as the restriction message.
- `availability` is the raw JSON rule tree and is only returned to users who can edit the
  course; **ignore it in the student app** and rely on `uservisible` + `availabilityinfo`.
- The same `uservisible` flag also appears in §1 (`core_completion_get_activities_completion_status`),
  so you can cross-check.

---

## 7. Expected Completion Date

Two different "expected dates" exist in Moodle — be clear which one the screen wants:

### 7a. Activity-level "Expected completed on" (`completionexpected`)

Set per activity by the teacher; the web course page shows "Expected completed on <date>".

**Best source — `core_course_get_contents`** returns a `dates[]` array per module. When the
teacher set an expected completion date, it appears as a labelled date:

```jsonc
"dates": [
  { "label": "Opened:",   "timestamp": 1719792000 },
  { "label": "Closes:",   "timestamp": 1722384000 }
]
```

> ⚠️ In 3.11, the activity `dates[]` array carries the module's **open/close** dates. The
> dedicated **`completionexpected`** value is most reliably read from
> `core_course_get_course_module`:

```
wsfunction = core_course_get_course_module
cmid       = 1450
```

```jsonc
{
  "cm": {
    "id": 1450,
    "completion": 2,
    "completionexpected": 1722384000,   // unix ts, 0/absent when not set
    ...
  }
}
```

Render: if `completionexpected > 0`, show "Expected by <formatted date>". If it is in the
past and the activity is still incomplete (§1 `state == 0`), highlight it as overdue.

### 7b. Course-level expected date criterion

If the teacher added a **"Date"** course-completion criterion, it shows up as a row in §5
(`core_completion_get_course_completion_status`) with `type == 6` (date) and its
`timecompleted` / `details.requirement` describing the required date. Use §5 for this one —
not §7a.

---

## 8. Completion Dashboard

A single screen summarising the student's standing in a course.

> **Simplest path:** one call to **`/local/multitopics/getalltopics.php`** (§A) already
> returns the whole dashboard — progress, course status, criteria, and every activity's
> completion/restriction/expected-date. Prefer that. The recipe below is the equivalent
> built from core functions, for reference or if you're not on the custom endpoint.

There is no core "dashboard" API — you assemble it from the calls above. Minimal recipe:

```
On open course dashboard (courseid, userid):
  A. core_course_get_enrolled_courses_by_timeline_classification   → progress %  (§4a)
        (or compute from B — §4b)
  B. core_completion_get_activities_completion_status(courseid,userid) → per-activity ticks,
        manual vs auto, restriction visibility, rule details        (§1 §2 §3)
  C. core_completion_get_course_completion_status(courseid,userid)  → overall course status
        + criteria checklist                                        (§5)
  D. core_course_get_contents(courseid)                             → section layout,
        restrictions (uservisible/availabilityinfo), expected dates (§6 §7)
```

### Suggested dashboard layout

```
┌───────────────────────────────────────────────┐
│  Mathematics — Grade 10                        │
│  ▓▓▓▓▓▓▓▓░░░░░░░░  42%   (from A)               │
│  Course status: In progress   (from C.completed)│
├───────────────────────────────────────────────┤
│  Completion criteria            (from C)        │
│   ✓ Quiz: Final exam — Marked complete          │
│   ○ Reach grade 80% — 62% so far                │
├───────────────────────────────────────────────┤
│  Activities                     (from B + D)    │
│   ✓ Lesson 1            (auto, viewed)           │
│   ○ Quiz: Chapter 2     🔒 locked — needs Ch.1   │
│      Expected by 31 Jul (from D)                │
│   ☐ Reflection          (manual — tap to tick)  │
└───────────────────────────────────────────────┘
```

Cache B/C/D per course and refresh after any manual toggle (§2) so progress and criteria
stay in sync.

### Calls you can batch

`core_course_get_contents` (D) + `core_completion_get_activities_completion_status` (B)
overlap: D already embeds `completiondata` for every module. If you want the fewest calls,
you can build the whole activity list from **D alone** (it has `uservisible`,
`availabilityinfo`, `completion`, `completiondata`, `dates`) and use B only if you prefer its
flatter shape. C is always a separate call.

---

## 9. Error & edge-case handling

| Situation                                   | What you see                                                   | App behaviour                                           |
| ------------------------------------------- | -------------------------------------------------------------- | ------------------------------------------------------- |
| Completion tracking off for course          | §5 throws `nocriteriaset`; activities have no `completiondata` | Hide the completion/criteria UI; still show activities  |
| No course-completion criteria set           | §5 throws `nocriteriaset`                                      | Show progress (§4) but hide the "criteria" checklist    |
| User not enrolled                           | §5 throws `notenroled` / `usernotenroled`                      | Prompt to enrol / block the dashboard                   |
| Manual toggle on auto activity              | §2 error `cannotmanualctrack`                                  | Never render a tappable box for `tracking == 2`         |
| Restricted activity                         | §6 `uservisible == false`                                      | Lock + show `availabilityinfo`; do not fetch its detail |
| `progress == null` / `hasprogress == false` | §4a                                                            | Don't render a progress bar                             |

All web-service errors come back as:

```jsonc
{
  "exception": "moodle_exception",
  "errorcode": "nocriteriaset",
  "message": "...",
}
```

Switch on `errorcode`, not on the localized `message`.

**`getalltopics.php` (§A) does not throw** for these cases — it degrades gracefully:

| Situation                   | What the endpoint returns                                                                                                                                      |
| --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Completion tracking off     | `completion_enabled: false`, `progress: null`, `course_completed: null`, `completion_criteria: []`; activities omit completion fields (`hascompletion: false`) |
| No trackable activities     | `progress: null`                                                                                                                                               |
| Restricted activity/section | included with `locked: true` + `availabilityinfo`; content fields (`fileurl`, meeting tokens) blank                                                            |
| User not enrolled           | HTTP 403 `{ "errorcode": "nopermissions" }`                                                                                                                    |
| Bad/expired token           | HTTP 401 `{ "errorcode": "invalidtoken" }`                                                                                                                     |

So on the app side, gate the completion UI on `completion_enabled` and the progress bar on
`progress != null` — no exception handling needed for the normal "completion is off" case.

---

## 10. Quick reference — functions used

| Function                                                      | Purpose                                                                                                  | Key inputs            |
| ------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------- | --------------------- |
| **`GET /local/multitopics/getalltopics.php`**                 | **all-in-one: structure + progress + completion + restrictions + expected dates (points 1,3,4,5,6,7,8)** | `courseid`, `wstoken` |
| `core_webservice_get_site_info`                               | get `userid`                                                                                             | —                     |
| `core_completion_get_activities_completion_status`            | per-activity completion (§1/§3)                                                                          | `courseid`, `userid`  |
| `core_completion_update_activity_completion_status_manually`  | tick/untick manual activity (§2)                                                                         | `cmid`, `completed`   |
| `core_completion_get_course_completion_status`                | overall course status + criteria (§5/§7b)                                                                | `courseid`, `userid`  |
| `core_course_get_enrolled_courses_by_timeline_classification` | course progress % (§4)                                                                                   | `classification`      |
| `core_course_get_contents`                                    | sections, restrictions, expected dates (§6/§7a)                                                          | `courseid`            |
| `core_course_get_course_module`                               | single cm incl. `completionexpected` (§7a)                                                               | `cmid`                |

All are core Moodle 3.11 functions, enabled in the `moodle_mobile_app` service. No custom
Academy plugin endpoints are needed for course completion.
