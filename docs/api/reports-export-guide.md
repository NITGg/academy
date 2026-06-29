# Admin Reports, Assign & Export — API Guide (Phase 4)

Admin reporting, assigning packages, and CSV export over the Flex platform's own data.

Stories: **US-AD-3-1** (lessons & attendance) · **US-AD-3-2** (platform earnings) · **US-AD-3-3**
(package & flex) · **US-AD-3-4** (student flex balance + history) · **US-AD-4-1** (assign package) ·
**US-TR-1-3** (teacher earnings view) · **US-TR-2-1** (export).

JSON reports go through `…/local/academy/api.php?function=NAME&token=TOKEN`. CSV export is a separate
endpoint, `…/local/academy/export.php?type=NAME&token=TOKEN`. Admin functions require the
**`local/academy:manageplatform`** capability; teacher exports act on the token's user.

> Scope: reports cover this plugin's data (lessons, earnings, flex ledger, purchases, payments).
> Moodle course-level activity and login-history reports are outside this plugin.

---

## Assign a package (US-AD-4-1)

### `assign_package` (POST, admin)
Params: `studentid`, `packageid` (active), `amount?` (offline amount paid; defaults to package price),
`method?` (default `offline`), `reference?`, `note?`.
Creates an `admin_assigned` purchase, records a payment, and writes an `assign` row to the Flex ledger.
The student must not already have an active package. The Flex value used for later revenue splits is
`amount / flex_count`, so the entered amount matters.

---

## Reports (admin, GET)

All accept optional filters; only those sent are applied. Common filters: `from`, `to` (unix seconds),
`teacherid`, `studentid`, `status`, `source`. Each returns `{ rows:[…], summary:{…} }` (except
`report_student_flex`, which returns `{ student, balance, history }`).

### `report_lessons` — US-AD-3-1
Filters: `teacherid`, `studentid`, `status`, `from`, `to` (date window on the effective lesson time).
Summary: totals + `by_status` counts + `attendance_rate` (completed ÷ lessons that reached their time).

### `report_platform_earnings` — US-AD-3-2
Filters: `teacherid`, `status` (`active`|`reversed`), `from`, `to` (on the earning's record time).
Rows: lesson, teacher, student, date, flex value, platform %, platform/teacher amount, status.
Summary totals count **active** earnings only (reversed excluded).

### `report_packages` — US-AD-3-3
Filters: `studentid`, `source` (`online`|`admin_assigned`), `from`, `to`.
Rows: purchase with source, price, flex counts, status. Summary: sales, online vs assigned counts,
flex added/consumed/returned, reversals.

### `report_student_flex` — US-AD-3-4
Param: `studentid` (required). Returns the student's current balance (available/reserved/consumed,
active package + expiry) and the full Flex ledger (`purchase`, `assign`, `reserve`, `consume`,
`return`, `expire`, `adjust`) with balance-before/after, related package/lesson, performer, and reason.

### `report_lesson_events` — US-AD-3-1 (action audit trail)
Param: `lessonid` (required). Returns `{ lesson:{id,student_name,teacher_name,subject,status},
events:[{action, actorid, actor_name, role, time}] }`, oldest action first. `time` is the unix time
the action happened, **decrypted on read** — it is stored encrypted (`academy_lesson_events.time_enc`,
base64 over `\core\encryption`) and never persisted in plaintext. `action` ∈ `requested`,
`teacher_accepted`, `teacher_rejected`, `teacher_suggested`, `student_accepted`, `student_rejected`,
`student_suggested`, `started`, `completed`, `student_absent_reported`, `teacher_absent_reported`,
`request_cancelled`, `cancelled_by_student`, `cancelled_by_teacher`, `time_update_requested`,
`time_update_accepted`, `time_update_rejected`. Surfaced in the admin Lessons report via a **Timeline**
button per row. (`time` is `0` if a row can't be decrypted, e.g. after a key change.)

---

## CSV export (US-AD-3-x export, US-TR-2-1)

`export.php?type=NAME&token=TOKEN[&filters]` streams a UTF-8 CSV download (BOM included so Excel reads
Arabic names). Same filters as the JSON reports.

| `type` | Who | Content |
|--------|-----|---------|
| `lessons` | admin | lessons & attendance |
| `platform_earnings` | admin | platform earnings |
| `packages` | admin | package & flex |
| `student_flex` | admin | one student's ledger (needs `studentid`) |
| `my_earnings` | teacher | the teacher's lesson earnings |
| `my_withdrawals` | teacher | the teacher's withdrawals |

Admin types return **403** for a non-`manageplatform` token; a bad token returns **401**.
PDF export is not implemented yet (CSV only) — can be added later via Moodle's dompdf.

---

## Teacher earnings view (US-TR-1-3)

No new endpoint — `get_teacher_wallet` (Phase 3) already returns the summary, `earnings[]` (now with
`student_name` + `lesson_time`), and `withdrawals[]`. The teacher wallet UI renders it and links the two
CSV exports above.

---

## Quick cURL

```bash
BASE=http://localhost:8081/local/academy/api.php
EXP=http://localhost:8081/local/academy/export.php
ADMIN=<admin-token>

# assign a package (offline)
curl -s -X POST "$BASE" --data-urlencode function=assign_package --data-urlencode token=$ADMIN \
  --data-urlencode studentid=4770 --data-urlencode packageid=6 --data-urlencode amount=900 \
  --data-urlencode method=offline --data-urlencode reference=RC-77

# reports
curl -s "$BASE?function=report_platform_earnings&token=$ADMIN&from=0"
curl -s "$BASE?function=report_student_flex&token=$ADMIN&studentid=4770"

# export CSV
curl -s "$EXP?type=packages&token=$ADMIN" -o packages.csv
```

## UI

- **Admin** — *Flex platform reports* (`manage_reports.php`): tabbed Lessons / Platform earnings /
  Packages & Flex / Student Flex, each with filters and an **Export CSV** button. *Assign package to
  student* (`assign_package.php`): student id + package + offline payment details. Both under
  Site administration → Plugins → Local plugins.
- **Teacher** — *My earnings* (`wallet.php`) shows the earnings/withdrawals view and **Export CSV**
  links (US-TR-1-3 / US-TR-2-1).

Postman: `Academy_Reports.postman_collection.json` (this folder).
