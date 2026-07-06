# Booking modal — day/time picker (student mobile app)

**Goal:** reproduce, in the mobile app, the "Book a lesson" tab's picker modal that the web UI shows
when a student taps **Request lesson** on a teacher card.

Read together with [lessons-flex-guide.md](lessons-flex-guide.md) (lesson lifecycle, Flex, endpoints).
This guide only covers the piece that's easy to get wrong: **how the day/time picker itself is built**,
because there is **no server endpoint that returns "available slots"** — the web client computes them
locally, and the mobile app must replicate the same logic.

Reference implementation (web): `local/academy/student.php`, inline script around lines 262–770 (there
is no AMD module or Mustache template for this feature — it's plain JS in the page).

---

## The key fact: slots are computed client-side

`browse_teachers` and `get_teacher` already return everything needed to build the picker for a teacher —
there is nothing else to fetch once you have that object. The three relevant fields:

```jsonc
{
  "userid": 16,
  "hours": [
    { "dayofweek": 1, "starttime": "09:00", "endtime": "17:00" }
    // dayofweek: 0=Sunday .. 6=Saturday. Empty array if the teacher hasn't set hours.
  ],
  "busy_times": [
    [1795000000, 1795003600]   // [startUnix, endUnix] pairs — teacher's existing active lessons
  ]
}
```

The app must generate slots itself from `hours` + `busy_times`, exactly like the web modal does. There
is no `get_available_slots` call — don't build the UI around one.

---

## Step-by-step flow

### 1. Open the "Book a lesson" tab

`GET api.php?function=browse_teachers&token=TOKEN[&subject=SUBSTRING]`

Returns approved + available teachers, each with `subjects[]`, `years[]`, `hours[]`, `busy_times[]`
already embedded (see full shape below). **No further call is needed to open the modal** — the app
already has everything for that teacher in memory.

### 2. Student taps "Request lesson" on a teacher card

Open the picker modal using the teacher object already in memory. Fields to show:
- `subject` — a picker populated from `teacher.subjects[].subject` (must match one exactly; the server
  rejects anything else).
- day/time picker — built per the algorithm below.
- `note` — free text, **required** (server rejects empty notes).

> If the app re-opens the picker later for a "suggest a different time" flow on an existing lesson,
> refresh first with `GET api.php?function=get_teacher&teacherid=ID` — hours/busy_times can be stale by
> then. Same object shape as one `browse_teachers` entry.

### 3. Build the day picker (client-side, no API call)

For each of the next **14 calendar days** (today + 13):
- Look up that day's `dayofweek` (0=Sun..6=Sat) in `teacher.hours`.
- If the teacher has no `hours` rows at all, fall back to a default working window of **08:00–20:00**
  every day.
- A day is selectable if it has at least one non-disabled slot (see step 4). Grey out days with none.

### 4. Build the time-slot picker for the selected day (client-side, no API call)

- Slice the day's working-hour interval(s) into **60-minute blocks** (`SLOT_MINUTES = 60`, matches the
  fixed lesson `duration`).
- Fetch `min_booking_minutes` once at page load: `GET api.php?function=get_lesson_settings` (default
  `60`). Disable any slot starting before `now + min_booking_minutes`.
- Disable any slot whose `[start, start+60min)` window overlaps any pair in `teacher.busy_times`.
- Selecting a slot just needs to produce a unix timestamp for the next step — no call yet.

### 5. Student fills subject + note and confirms

`POST api.php?function=request_lesson`

| param | type | notes |
|---|---|---|
| `teacherid` | int | |
| `subject` | string | must exactly match one of `teacher.subjects[].subject` |
| `requested_time` | int | unix seconds of the chosen slot start |
| `note` | string | **required**, non-empty after trim |

Response `data` is the created lesson object, `status: "pending"`. From here the flow joins the normal
lesson lifecycle documented in [lessons-flex-guide.md](lessons-flex-guide.md) (teacher accepts/rejects/
suggests, etc.).

The server is the final authority — it re-checks everything the client already filtered for. If the
app's local slot cache is stale (e.g. someone else booked the teacher in the meantime), `request_lesson`
fails and the app should refresh (`get_teacher`) and re-render the picker.

---

## Server-side validation on `request_lesson` (mirror these client-side to fail fast)

1. `subject` non-empty → `err_subjectrequired`.
2. `note` required, non-empty → `err_noterequired`.
3. `teacherid` must be an actual teacher → `err_teachernotfound`.
4. Can't book yourself → `err_selfbooking`.
5. Teacher must actually offer that subject (case-sensitive) → `err_subjectunsupported`.
6. Student must have an active package with `remaining_flex >= 1` → `err_noflex`. (Flex is only
   **reserved** later, when the lesson becomes `confirmed`.)
7. `requested_time >= now + min_booking_minutes*60` → `err_minbooking`.
8. No overlap with another active lesson (`pending|waiting_student|waiting_teacher|confirmed|
   in_progress`) on that teacher → `err_timeconflict` ("The teacher already has a lesson scheduled at
   this time.").

---

## Full `Teacher` object (from `browse_teachers` / `get_teacher`)

```jsonc
{
  "userid": 16,
  "fullname": "Jane Doe",
  "phone": "+201234567890",
  "headline": "Math tutor",
  "bio": "...",
  "experience": "5 years",
  "photourl": "https://.../pluginfile.php/...",
  "rating": 4.8,
  "approved": 1,
  "available": 1,
  "subjects": [ { "subject": "Math", "specialization": "Algebra" } ],
  "years": ["Grade 10", "Grade 11"],
  "hours": [ { "dayofweek": 1, "starttime": "09:00", "endtime": "17:00" } ],
  "busy_times": [ [1795000000, 1795003600] ]
}
```

`email` is stripped from these public browse/get responses.

## Full `Lesson` object (returned by `request_lesson` and the rest of the lifecycle)

```jsonc
{
  "id": 123,
  "studentid": 5, "student_name": "...",
  "teacherid": 16, "teacher_name": "...",
  "subject": "Math",
  "status": "pending", // pending|waiting_student|waiting_teacher|confirmed|in_progress|
                        // completed|student_absent|teacher_absent|cancelled|cancelled_teacher|rejected
  "requested_time": 1795006000, "confirmed_time": 0, "effective_time": 1795006000,
  "duration": 60,
  "note": "...", "reject_reason": null, "cancel_reason": null,
  "flex_state": "none", // none|reserved|consumed|returned
  "actual_start": 0, "actual_end": 0,
  "sessionid": 0, "cmid": 0,
  "timecreated": 1794999000, "timemodified": 1794999000,
  "can_join": false, "join_url": "",
  "jitsi_session": null,
  "my_role": "student",   // only when the endpoint knows the viewer
  "actions": ["view"],
  "proposals": []          // only on get_lesson / withdetail=true
}
```

---

## Settings that drive the picker (`get_lesson_settings`, GET, any authenticated user)

| setting | default | used for |
|---|---|---|
| `min_booking_minutes` | 60 | disables slots too close to "now" |
| `cancel_deadline_minutes` | 120 | not the picker — see lifecycle guide |
| `update_deadline_minutes` | 120 | not the picker — see lifecycle guide |
| `start_allowed_minutes` | 30 | not the picker — see lifecycle guide |
| `complete_allowed_minutes` | 180 | not the picker — see lifecycle guide |
| `absence_report_minutes` | 15 | not the picker — see lifecycle guide |

Only `min_booking_minutes` affects the day/time picker itself; the rest gate later lifecycle actions.

## Quick cURL walkthrough

```bash
BASE="https://academy2026.nitg-eg.com/local/academy/api.php"
STUDENT=<student-token>

# 1) open the tab — get teachers with hours + busy_times already embedded
curl -s "$BASE?function=browse_teachers&token=$STUDENT" | jq '.data[0] | {userid, hours, busy_times}'

# 2) read the min-notice window used to grey out near-term slots
curl -s "$BASE?function=get_lesson_settings&token=$STUDENT" | jq '.data.min_booking_minutes'

# 3) confirm a booking for a slot the app computed locally
WHEN=$(($(date +%s) + 2*86400))   # e.g. 2 days out
curl -s -X POST "$BASE" --data-urlencode function=request_lesson --data-urlencode token=$STUDENT \
  --data-urlencode teacherid=16 --data-urlencode subject=Math \
  --data-urlencode requested_time=$WHEN --data-urlencode note="First lesson, please go slow"
```

## Postman

`Academy_Lessons_Flex.postman_collection.json` (`docs/api/`) — same collection as the lifecycle guide;
`browse_teachers` / `get_teacher` are in the same folder set.
