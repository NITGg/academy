# Lessons & Flex Engine — API Guide (Phase 2)

Covers the lesson lifecycle and the Flex (lesson-credit) engine.

Stories: **US-LS-1-1** (request) · **US-LS-2-1/2-2/2-3** (negotiate) · **US-LS-3-1..3-4**
(start / complete / absence) · **US-LS-4-1/4-2** (cancel) · **US-LS-5-1/5-2** (time update) ·
**US-TR-1-2 / US-ST-2-2** (view lessons) · **US-FN-1-2** (reserve) · **US-FN-1-3** (return).

All calls go through the single dispatcher:

```
…/local/academy/api.php?function=NAME&token=TOKEN
```

Response is always `{ "status":"success", "data":… }` or `{ "status":"fail", "error":"msg" }`.
Every **state-changing** call below requires **POST**. Reads (`get_*`) use GET. The token identifies
the acting user (`$userid`); there is no admin capability gate on these functions — each manager
verifies the caller is the lesson's student or teacher.

> All times are **unix timestamps** (seconds). A lesson is **1 hour** (`duration = 60`). One **Flex =
> one lesson**.

---

## Lesson status machine

```
pending ─┬─ teacher accept ───────────────► confirmed ─► in_progress ─┬─► completed
         │                                      ▲                      ├─► student_absent
         ├─ teacher suggest ► waiting_student ──┤                      └─► teacher_absent
         │                          │ student suggest                  
         │                          ▼                                  confirmed can also go to:
         │                    waiting_teacher ──┘ teacher accept        ├─► cancelled            (student)
         ├─ teacher reject ──────────────────────► rejected             └─► cancelled_teacher    (teacher)
         └─ student withdraw ───────────────────► cancelled
```

`waiting_student` reject → `cancelled` (negotiation ended by student). A `reschedule` proposal on a
`confirmed` lesson does **not** change status until accepted (then only `confirmed_time` moves).

## Flex accounting

A purchase row (`academy_package_purchases`) tracks three counters:

| counter | meaning |
|---------|---------|
| `remaining_flex` | available to reserve |
| `reserved_flex`  | held for confirmed lessons (not yet earnings) |
| `consumed_flex`  | permanently spent |

| event | effect | ledger `type` |
|-------|--------|---------------|
| lesson **confirmed** | `remaining −1`, `reserved +1` | `reserve` |
| lesson **completed** | `reserved −1`, `consumed +1` | `consume` |
| **student absent** | `reserved −1`, `consumed +1` | `consume` |
| **late** student cancel | `reserved −1`, `consumed +1` | `consume` |
| **early** student cancel | `reserved −1`, `remaining +1` | `return` |
| **teacher** cancel / **teacher absent** | `reserved −1`, `remaining +1` | `return` |

Every movement is appended to `academy_flex_tx` (auditable via `get_flex_history`).
"Early" vs "late" is decided by `cancel_deadline_minutes` (lesson settings, US-AD-2-1).

---

## Endpoints

### Request a lesson — `request_lesson` (POST) · student
Params: `teacherid`, `subject`, `requested_time`, `note?`
Checks: teacher offers the subject; student has an active package with available Flex; the time is at
least `min_booking_minutes` away. No Flex is reserved yet. → status `pending`.

### Teacher responds — `teacher_respond_lesson` (POST) · teacher
Params: `lessonid`, `action` = `accept` | `reject` | `suggest`, plus `suggested_time?`, `reject_reason?`
- from `pending`: `accept` → `confirmed` (+reserve at `requested_time`); `reject` → `rejected`;
  `suggest` → `waiting_student` (needs `suggested_time`).
- from `waiting_teacher`: `accept` → `confirmed` (+reserve at the student's latest suggested time);
  `reject` → `rejected`.

### Student responds — `student_respond_lesson` (POST) · student
Params: `lessonid`, `action` = `accept` | `reject` | `suggest`, plus `suggested_time?`, `reject_reason?`
Only from `waiting_student`. `accept` → `confirmed` (+reserve); `reject` → `cancelled`;
`suggest` → `waiting_teacher`.

### Withdraw a request — `cancel_lesson_request` (POST) · student
Params: `lessonid`, `reason?`. Allowed while `pending` / `waiting_student` / `waiting_teacher`
(no Flex reserved). → `cancelled`.

### Start — `start_lesson` (POST) · teacher
Params: `lessonid`. From `confirmed`, not before `start_allowed_minutes` ahead of `confirmed_time`.
→ `in_progress`, records `actual_start`. Also creates the lesson's Jitsi meeting room (US-LS-3-1; see
**Meeting room & joining** below) — requires `lessons_courseid` to be set, else fails with
`err_nolessonscourse`.

### Complete — `complete_lesson` (POST) · teacher
Params: `lessonid`, `note?`. From `confirmed`/`in_progress`. Consumes the reserved Flex and closes the
meeting room. → `completed`, records `actual_end`. *(Revenue split US-FN-1-4 is Phase 3.)*

### Student absent — `report_student_absent` (POST) · teacher
Params: `lessonid`. From `confirmed`/`in_progress`, after `absence_report_minutes` past start.
Consumes the Flex and closes the meeting room. → `student_absent`.

### Teacher absent — `report_teacher_absent` (POST) · student
Params: `lessonid`. From `confirmed`/`in_progress`, after `absence_report_minutes` past start.
Returns the Flex and closes the meeting room. → `teacher_absent`.

### Student cancel — `cancel_lesson_student` (POST) · student
Params: `lessonid`, `reason?`. From `confirmed`. Early (≤ `cancel_deadline_minutes` ahead) returns the
Flex; late consumes it. → `cancelled`.

### Teacher cancel — `cancel_lesson_teacher` (POST) · teacher
Params: `lessonid`, `reason` (required). From `confirmed`. Returns the Flex. → `cancelled_teacher`.

### Request a time update — `request_time_update` (POST) · student or teacher
Params: `lessonid`, `proposed_time`. From `confirmed`, before `update_deadline_minutes`. Only one
pending update at a time. Lesson stays `confirmed`; no extra Flex reserved.

### Respond to a time update — `respond_time_update` (POST) · the other party
Params: `lessonid`, `action` = `accept` | `reject`. `accept` moves `confirmed_time` to the proposed
time; `reject` leaves the original time. Lesson stays `confirmed`.

### View my lessons — `get_my_lessons` (GET) · student or teacher
Params: `role?` = `student` | `teacher` (default: both), `status?`. Open/negotiating lessons first,
then upcoming confirmed by soonest, then history. Each item includes `my_role` and `actions`
(status-dependent available actions).

### View one lesson — `get_lesson` (GET) · participant
Params: `lessonid`. Returns the lesson plus its `proposals[]` and available `actions`.

### Flex history — `get_flex_history` (GET) · student
The caller's full Flex ledger (`reserve` / `consume` / `return`) with running balance.

---

## Meeting room & joining (US-LS-3-1)

`start_lesson` spins up a per-lesson **Jitsi** room in the course named by `lessons_courseid` (see
settings below) and links it to a live session so only the assigned teacher and student can enter.
`complete_lesson` / `report_student_absent` / `report_teacher_absent` close it again.

Every lesson object (`get_lesson`, `get_my_lessons`, and the lifecycle responses) therefore carries
four room fields:

| field | type | meaning |
|-------|------|---------|
| `can_join` | bool | `true` while the lesson is `in_progress` (a room exists); `false` otherwise |
| `join_url` | string | `…/mod/jitsi/view.php?id={cmid}` while `can_join`; empty string otherwise |
| `cmid` | int | course-module id of the lesson's Jitsi activity (`0` before start) |
| `sessionid` | int | the linked live-session row (`0` before start) |

While `in_progress`, `actions` includes **`join`** for *both* the teacher and the student.

**Mobile app:** show a **"Join Lesson"** button whenever `can_join` is `true` — for teacher and student
alike. Open `join_url` with the caller's token appended (`{{join_url}}&token={{token}}`) so Moodle logs
the user straight into the Jitsi room. When `can_join` is `false`, hide the button (`join_url` is empty).

---

## Quick cURL walkthrough (happy path)

```bash
BASE=http://localhost:8081/local/academy/api.php
STUDENT=<student-token>; TEACHER=<teacher-token>

# 1) student requests (e.g. 2 days out)
WHEN=$(($(date +%s) + 2*86400))
curl -s -X POST "$BASE" --data-urlencode function=request_lesson --data-urlencode token=$STUDENT \
  --data-urlencode teacherid=16 --data-urlencode subject=Math --data-urlencode requested_time=$WHEN

# 2) teacher accepts → confirmed, 1 Flex reserved
curl -s -X POST "$BASE" --data-urlencode function=teacher_respond_lesson --data-urlencode token=$TEACHER \
  --data-urlencode lessonid=<id> --data-urlencode action=accept

# 3) teacher starts, then completes → Flex consumed
curl -s -X POST "$BASE" --data-urlencode function=start_lesson    --data-urlencode token=$TEACHER --data-urlencode lessonid=<id>
curl -s -X POST "$BASE" --data-urlencode function=complete_lesson --data-urlencode token=$TEACHER --data-urlencode lessonid=<id>

# inspect the ledger
curl -s "$BASE?function=get_flex_history&token=$STUDENT"
```

> `start_lesson` enforces the start window; for a smoke test set `start_allowed_minutes` high
> (via `update_lesson_settings`) or book the lesson near-term and adjust the deadlines.

## Settings that gate these flows (US-AD-2-1)

`min_booking_minutes` · `cancel_deadline_minutes` · `update_deadline_minutes` ·
`start_allowed_minutes` · `absence_report_minutes` · `lessons_courseid`. Read them with
`get_lesson_settings`; change them with `update_lesson_settings` (admin).

`lessons_courseid` is the Moodle course that hosts the per-lesson Jitsi rooms — **it must be set before
`start_lesson` will work** (otherwise start fails with `err_nolessonscourse`).

## Postman & UI

- Collection: `Academy_Lessons_Flex.postman_collection.json` (this folder). Run **Auth → Login as
  Student/Teacher/Admin**, then **Setup → Purchase Flex package**, then the lesson folders. A
  collection pre-request script keeps `{{future_time}}` / `{{future_time2}}` valid.
- Teacher UI: `my_lessons.php` (US-TR-1-2) — Preferences → User account → **"My lessons"** (teachers
  only). The student-facing UI is the separate frontend project.
