# Platform APIs — Guide & Postman (Phase 1)

Covers the `local_academy` platform endpoints built **beyond packages**. This phase:

| Story | Area | Endpoints |
|-------|------|-----------|
| US-AD-2-1 | Admin | `get_lesson_settings`, `update_lesson_settings` |
| US-TR-1-1 | Teacher | `get_teacher_profile`, `update_teacher_profile` |
| US-ST-2-1 | Student | `browse_teachers`, `get_teacher` |

> This file grows as later phases (lessons, financial, reports) are built. Packages have their own
> guide: [admin-packages.md](admin-packages.md) (frontend) and
> [student-packages-mobile-guide.md](student-packages-mobile-guide.md) (mobile).

---

## 1. Basics

- **Endpoint:** `{BASE_URL}/local/academy/api.php` — the action is the `function` param.
- **Base URL:** local `http://localhost:8081`; staging/prod = your domain.
- **Auth:** every call needs `token` (a Moodle web-service token). Get one:
  ```
  POST {BASE_URL}/login/token.php
  username=USER&password=PASS&service=moodle_mobile_app  → { "token": "..." }
  ```
- **Response:** JSON `{ "status": "success", "data": ... }` or `{ "status": "fail", "error": "..." }`.
  A bad/expired token returns an **HTML** page instead of JSON — treat that as "re-login".
- **Who can call what:**
  - `update_lesson_settings` → **admin** (capability `local/academy:manageplatform`).
  - `get_lesson_settings`, `browse_teachers`, `get_teacher` → any logged-in user.
  - `get_teacher_profile`, `update_teacher_profile` → the logged-in **teacher** (acts on their own profile).

---

## 2. Admin — Lesson Settings (US-AD-2-1)

### `get_lesson_settings` — GET
```
GET /local/academy/api.php?function=get_lesson_settings&token=TOKEN
```
```json
{ "status": "success", "data": {
  "min_booking_minutes": 60, "cancel_deadline_minutes": 120, "update_deadline_minutes": 120,
  "start_allowed_minutes": 30, "absence_report_minutes": 15,
  "teacher_percent": 40, "platform_percent": 60 } }
```

### `update_lesson_settings` — GET (admin)
Send only the fields you want to change.
```
GET /local/academy/api.php?function=update_lesson_settings&token=ADMIN_TOKEN
    &teacher_percent=45&platform_percent=55&cancel_deadline_minutes=180
```
| Field | Meaning |
|-------|---------|
| `min_booking_minutes` | minimum lead time to book a lesson |
| `cancel_deadline_minutes` | student cancellation deadline before start |
| `update_deadline_minutes` | time-update (reschedule) deadline before start |
| `start_allowed_minutes` | how early a lesson may start / link is visible |
| `absence_report_minutes` | wait before an absence can be reported |
| `teacher_percent` / `platform_percent` | revenue split — **must total 100** |

Rules: every value ≥ 0; `teacher_percent + platform_percent = 100`. Errors:
`Setting values must be zero or greater`, `Teacher percentage and platform percentage must total 100`,
`Permission denied` (non-admin token).

---

## 3. Teacher — Profile (US-TR-1-1)

### `get_teacher_profile` — GET (own profile)
```
GET /local/academy/api.php?function=get_teacher_profile&token=TEACHER_TOKEN
```

### `update_teacher_profile` — **POST** (own profile)
```
POST /local/academy/api.php
Content-Type: application/x-www-form-urlencoded

function=update_teacher_profile&token=TEACHER_TOKEN
&headline=Senior Math Teacher&bio=10 years teaching&experience=10 years&available=1
&subjects=[{"subject":"Math","specialization":"Algebra"},{"subject":"Physics","specialization":""}]
&hours=[{"dayofweek":1,"starttime":"09:00","endtime":"12:00"},{"dayofweek":1,"starttime":"14:00","endtime":"16:00"}]
```
Response (same shape from both endpoints):
```json
{ "status": "success", "data": {
  "userid": 16, "fullname": "Dr. Mohamed Mekhammer", "email": "...",
  "headline": "Senior Math Teacher", "bio": "...", "experience": "10 years",
  "photourl": "", "rating": 0, "approved": 1, "available": 1,
  "subjects": [ { "subject": "Math", "specialization": "Algebra" } ],
  "hours": [ { "dayofweek": 1, "starttime": "09:00", "endtime": "12:00" } ] } }
```
Notes:
- **POST only.** Send any subset of the simple fields.
- `subjects` and `hours` are **JSON arrays sent as text**; sending them **replaces the whole set**
  (omit them to leave existing ones unchanged).
- `dayofweek`: `0`=Sunday … `6`=Saturday; times are `HH:MM`.
- Working hours **must not overlap** within a day → error `Working hours must not overlap`
  (`Working hours are invalid...` if times are malformed or end ≤ start).

---

## 4. Student — Browse Teachers (US-ST-2-1)

### `browse_teachers` — GET
```
GET /local/academy/api.php?function=browse_teachers&token=TOKEN&subject=Math
```
Returns **approved + available** teachers (public fields, no email). `subject` is an optional filter.
```json
{ "status": "success", "data": [
  { "userid": 16, "fullname": "Dr. Mohamed Mekhammer", "headline": "Senior Math Teacher",
    "experience": "10 years", "photourl": "", "rating": 0, "available": 1,
    "subjects": [ {"subject":"Math","specialization":"Algebra"} ],
    "hours": [ {"dayofweek":1,"starttime":"09:00","endtime":"12:00"} ] } ] }
```

### `get_teacher` — GET
```
GET /local/academy/api.php?function=get_teacher&token=TOKEN&teacherid=16
```
One teacher's public profile by user id. Error `Teacher not found` if the id isn't an approved teacher.

> A teacher only appears in browse after they save a profile via `update_teacher_profile`
> (and while `approved=1` and `available=1`). Existing Moodle teachers don't appear until they do.

---

## 5. Postman quick start

1. Postman → **Import** → **File** → choose `docs/api/Academy_Platform.postman_collection.json`.
2. In the **Auth** folder, run **Login as Admin** and **Login as Teacher** once — they save tokens into
   `{{admin_token}}` and `{{teacher_token}}` automatically.
3. Use the folders:
   - **Admin — Lesson Settings** (uses `{{admin_token}}`)
   - **Teacher — Profile** (uses `{{teacher_token}}`; Update is a POST with JSON `subjects`/`hours`)
   - **Student — Browse Teachers** (any token)

Collection variables (collection → *Variables* tab): `base_url`, `admin_token`, `teacher_token`,
`teacherid`. Tokens are pre-filled with current local ones and refreshed by the Login requests.

## 6. Quick test (curl)
```bash
B=http://localhost:8081
AT=$(curl -s -X POST "$B/login/token.php" -d "username=admin&password=123456&service=moodle_mobile_app" | sed -E 's/.*"token":"([a-f0-9]+)".*/\1/')
TT=$(curl -s -X POST "$B/login/token.php" -d "username=mohamedmekhammr@gmail.com&password=123456&service=moodle_mobile_app" | sed -E 's/.*"token":"([a-f0-9]+)".*/\1/')

curl "$B/local/academy/api.php?function=get_lesson_settings&token=$AT"
curl "$B/local/academy/api.php?function=update_lesson_settings&token=$AT&teacher_percent=40&platform_percent=60"
curl "$B/local/academy/api.php?function=get_teacher_profile&token=$TT"
curl -X POST "$B/local/academy/api.php" --data-urlencode "function=update_teacher_profile" --data-urlencode "token=$TT" --data-urlencode "headline=Senior Math Teacher" --data-urlencode 'subjects=[{"subject":"Math","specialization":"Algebra"}]'
curl "$B/local/academy/api.php?function=browse_teachers&token=$AT&subject=Math"
```
