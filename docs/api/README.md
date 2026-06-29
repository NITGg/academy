# Academy Flex Platform — API docs index

All APIs live in the `local_academy` plugin behind one dispatcher:

```
http://localhost:8081/local/academy/api.php?function=NAME&token=TOKEN
```

Response is always `{ "status":"success", "data":… }` or `{ "status":"fail", "error":"msg" }`.
Reads are GET; state-changing actions are POST. CSV export uses a separate endpoint,
`…/local/academy/export.php?type=NAME&token=TOKEN`.

## Getting a token

```
POST http://localhost:8081/login/token.php   body: username, password, service=moodle_mobile_app
```
Local logins: admin `admin/123456`, teacher `mohamedmekhammr@gmail.com/123456`,
student `hmprep02-001/123456`. Admin-only functions require the `local/academy:manageplatform` (or
`:managepackages` for package CRUD) capability; teacher/student functions act on the token's user.

## Guides & Postman collections by phase

| Phase | Area | Guide | Postman |
|-------|------|-------|---------|
| 1 | Admin packages | `admin-packages.md` | `Academy_Packages.postman_collection.json` |
| 1 | Student packages | `student-packages-mobile-guide.md` | (in Packages) |
| 1 | Settings / teacher profile / browse | `platform-apis-postman-guide.md` | `Academy_Platform.postman_collection.json` |
| 2 | Lessons + Flex engine | `lessons-flex-guide.md` | `Academy_Lessons_Flex.postman_collection.json` |
| 3 | Financial (earnings, withdrawals) | `financial-guide.md` | `Academy_Financial.postman_collection.json` |
| 4 | Reports, assign, export | `reports-export-guide.md` | `Academy_Reports.postman_collection.json` |

Import all five collections into Postman. Each has an **Auth** folder — run the relevant Login first
(it saves the token into a collection variable), then send any request.

## End-to-end happy path (one student, one teacher)

1. **Admin** creates a package (Packages collection) — or assign one directly in step 3.
2. **Student** purchases it (`purchase_package`) — or **Admin** assigns it (`assign_package`).
3. **Student** requests a lesson (`request_lesson`).
4. **Teacher** accepts (`teacher_respond_lesson` → reserves 1 Flex).
5. **Teacher** starts then completes (`start_lesson`, `complete_lesson` → consumes the Flex and
   distributes revenue 40/60).
6. **Teacher** withdraws earnings (`request_withdrawal`); **Admin** approves + pays
   (`process_withdrawal`).
7. **Admin** reviews `report_*` and exports CSV; **Teacher** views `get_teacher_wallet` and exports.

## In-site UI (admin + teacher only; student UI is a separate frontend)

**Admin** — Site administration → Plugins → Local plugins:
Manage lesson packages · Lesson settings · Teacher withdrawals · Assign package to student ·
Flex platform reports.

**Teacher** — avatar → Preferences → User account:
Edit my teacher profile · My lessons · My earnings.

## Status / model cheat-sheet

- **Lesson:** pending → (waiting_student ⇄ waiting_teacher) → confirmed → in_progress → completed |
  student_absent | teacher_absent | cancelled | cancelled_teacher | rejected.
- **Flex:** reserve on confirm; consume on complete / student-absent / late student-cancel; return on
  teacher-cancel / teacher-absent / early student-cancel; admin reversal returns a consumed Flex.
- **Money:** flex_value = price_paid / flex_count; teacher_amount = flex_value × teacher_percent/100;
  platform_amount = flex_value − teacher_amount. Available balance = active teacher earnings −
  (pending + approved + paid withdrawals).
- **Settings (US-AD-2-1):** min_booking_minutes, cancel_deadline_minutes, update_deadline_minutes,
  start_allowed_minutes, absence_report_minutes, teacher_percent + platform_percent (= 100).

## Not implemented yet

- PDF export (CSV only; PDF can be added via Moodle dompdf).
- Notifications (specs mention "notify"; events are state transitions only so far).
- Moodle course-level activity / login-history reports (outside this plugin's scope).
