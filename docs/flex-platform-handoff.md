# Flex Platform — Build Handoff (for the next AI/dev)

This summarizes everything built so far for the **Flex tutoring platform** and how to continue.
Read this top-to-bottom before writing code.

---

## 1. What the project is

- A **Moodle 3.11.8** site (theme `edumy`) running in Docker. We are adding a brand-new
  "Flex tutoring platform" (student ↔ teacher lesson marketplace) as a **custom Moodle local plugin**.
- **Home plugin:** `src/local/academy/` (component `local_academy`). All new platform code lives here.
- The requirements live as user stories in `docs/specs/` (one file per story). **The user asked NOT to
  modify `docs/specs/`** — treat it as read-only source of truth. Put all API explanations in `docs/api/`.

## 2. Run / environment

```bash
# from repo root (D:\My work\NIT\Projects\Academy\academy)
docker compose up -d app db        # only these two services are needed locally
# Moodle:  http://localhost:8081     DB: MariaDB on 3307 (root/root, db academy2022_moodle, prefix mdl_)
```
- Windows + Git Bash. When running `docker exec ... <container-path>`, set `export MSYS_NO_PATHCONV=1`
  first or Git Bash mangles `/var/www/...` paths.
- App container: `academy_app`; DB container: `academy_db`. Source is bind-mounted (`./src` → `/var/www/html`),
  so PHP edits are live.

## 3. Logins & tokens (local)

| Role | Username | Password | Notes |
|------|----------|----------|-------|
| Admin | `admin` | `123456` | site admin |
| Teacher | `mohamedmekhammr@gmail.com` | `123456` | user id 16, editingteacher, has a Flex teacher profile |
| Student | `hmprep02-001` | `123456` | simple username |

Web services are **enabled** (REST + mobile service). Get a token:
```
POST http://localhost:8081/login/token.php   body: username, password, service=moodle_mobile_app
```
Current working tokens (stable per user): admin `f65e3a453f23cdf4f550dedc23fc2c31`,
teacher `ea6d941746294c7b034d5d1ed5dbd385`. (Regenerate via login if needed.)

## 4. API design / conventions (FOLLOW THESE)

- **One dispatcher:** `src/local/academy/api.php`. URL `…/local/academy/api.php?function=NAME&token=TOKEN`.
- **Auth:** token → user via `webservice::authenticate_user()`. Also note: **core file `lib/setup.php`
  has a project patch** (~line 1070) that authenticates any `?token=` globally and renders an HTML error
  page for a bad token *before* our code runs — so an invalid token returns HTML, not JSON. Clients must
  treat non-JSON as "re-login".
- **Capability gate:** a `$capmap` in api.php maps admin-only functions to a capability. Two caps exist
  (`db/access.php`): `local/academy:managepackages` (package CRUD) and `local/academy:manageplatform`
  (settings/reports/assign/withdrawals). Student/teacher functions need only a valid token and act on
  `$userid` (the token's user).
- **Response shape:** always `{ "status":"success", "data":... }` or `{ "status":"fail", "error":"msg" }`.
  Use the `academy_respond()` helper. Wrap handlers in the existing try/catch.
- **HTTP verbs:** reads = GET; **state-changing actions = POST** (enforced via
  `$_SERVER['REQUEST_METHOD']` check). Already applied to `purchase_package` and `update_teacher_profile`.
- **Errors:** throw `\moodle_exception('err_key','local_academy')`; add the string to
  `lang/en/local_academy.php` so `getMessage()` is human-readable.
- **Business logic in manager classes** under `classes/` (one per domain). Keep api.php thin.
- **Schema:** define new tables in `db/install.xml` AND add an upgrade step in `db/upgrade.php`
  (the DB already exists, so install.xml alone won't create them on existing installs). Bump
  `version.php` each schema/cap/settings change.

## 5. Critical gotchas (these wasted time — don't repeat)

1. **Opcache:** Apache has opcache with `revalidate_freq=60`. After editing a PHP file that's hit over
   HTTP (api.php, the pages), reset it or changes won't show for up to 60s:
   `printf '<?php opcache_reset();' > src/local/academy/_op.php; curl -s localhost:8081/local/academy/_op.php; rm src/local/academy/_op.php`.
   (CLI runs always recompile, so CLI smoke tests can mislead you.)
2. **Upgrades are slow** on this big DB and the CLI sometimes times out, leaving a stale lock so the
   whole site shows "Site is being upgraded, please retry later." Run upgrades in the **background**, wait
   for the process to finish (`ps aux | grep admin/cli/upgrade`), then clear the lock:
   `UPDATE mdl_config SET value=0 WHERE name='upgraderunning';` and re-run upgrade to finalize.
3. After schema/cap/lang/nav/settings changes: `php admin/cli/upgrade.php --non-interactive` (for version
   bumps) and/or `php admin/cli/purge_caches.php`.
4. **edumy theme** overrides the user **profile page** and does NOT render Moodle's standard profile
   categories — so `myprofile_navigation` sections don't show there. UI links for users go on the
   **Preferences page** instead (see lib.php `extend_navigation_user_settings`, attached to the
   `useraccount` node).

## 6. What's BUILT and verified (end-to-end over HTTP)

All in `local_academy`. Tables created (prefix `mdl_`):
`academy_packages`, `academy_package_purchases`, `academy_payments`,
`academy_teacher_profiles`, `academy_teacher_subjects`, `academy_teacher_hours`.

Managers: `package_manager`, `purchase_manager`, `settings_manager`, `teacher_manager`.

| Story | Functions (api.php) | Method |
|-------|---------------------|--------|
| US-AD-1-1..1-4 Packages CRUD | create_package, update_package, deactivate_package, activate_package, delete_package, get_packages, get_package | GET (writes should later move to POST) |
| US-PK-1-1/1-2/2-1 + US-FN-1-1 Student packages | get_available_packages, purchase_package(**POST**), get_my_packages, get_payment_history | mixed |
| US-AD-2-1 Lesson settings | get_lesson_settings, update_lesson_settings | GET |
| US-TR-1-1 Teacher profile | get_teacher_profile, update_teacher_profile(**POST**) | mixed |
| US-ST-2-1 Browse teachers | browse_teachers, get_teacher | GET |

**Moodle UI pages built** (for in-site testing; the real product UI is a separate frontend project):
- `manage_packages.php` — admin packages page (Site admin → Plugins → Local plugins → Manage lesson packages).
- `manage_settings.php` — admin lesson settings (Site admin → Plugins → Local plugins → Lesson settings).
- `teacher_profile.php` — teacher self profile editor. Reached via **Preferences → User account → "Edit my
  teacher profile"** (link added in `lib.php`). These pages mint a token for the logged-in user via
  `external_generate_token_for_current_user` and call api.php from JS.

Lesson settings keys + defaults (stored as plugin config): min_booking_minutes=60, cancel_deadline_minutes=120,
update_deadline_minutes=120, start_allowed_minutes=30, absence_report_minutes=15, teacher_percent=40,
platform_percent=60 (teacher+platform must total 100).

Purchases store a **snapshot** (price/flex/expiration) + `remaining_flex`, `expires_at`. One active package
per student enforced. `academy_payments` holds payment history (gateway skipped — purchase assumes paid).

## 7. Docs already written (in docs/api/)

- `admin-packages.md` (frontend guide) + `Academy_Packages.postman_collection.json`
- `student-packages-mobile-guide.md`
- `platform-apis-postman-guide.md` (settings/profile/browse) + `Academy_Platform.postman_collection.json`

Specs index: `docs/specs/README.md` (statuses marked Built for the above). **Do not edit docs/specs.**
Note: the two teacher financial stories were renamed: `US-FN-1-3`→`US-TR-1-3`, `US-FN-2-1`→`US-TR-2-1`
(so all IDs are now unique).

## 8. REMAINING WORK — phased plan

Build APIs only (UI is the separate frontend project), document each in `docs/api/`, and **stop after
each phase until the user says continue** (the user works phase-by-phase).

### Phase 2 — Lessons + flex engine ✅ BUILT (verified end-to-end over HTTP)
New tables: `academy_lessons`, `academy_lesson_proposals`, `academy_flex_tx`; added `reserved_flex` /
`consumed_flex` columns to `academy_package_purchases` (version `2026062804`). New managers:
`lesson_manager`, `flex_manager`. Doc: `docs/api/lessons-flex-guide.md`. Endpoints (all writes POST):
`request_lesson`, `teacher_respond_lesson`, `student_respond_lesson`, `cancel_lesson_request`,
`start_lesson`, `complete_lesson`, `report_student_absent`, `report_teacher_absent`,
`cancel_lesson_student`, `cancel_lesson_teacher`, `request_time_update`, `respond_time_update`,
`get_my_lessons` (GET), `get_lesson` (GET), `get_flex_history` (GET). Flex: reserve on confirm,
consume on complete / student-absent / late student-cancel, return on teacher-cancel / teacher-absent /
early student-cancel. Revenue split on Complete is deferred to Phase 3 (hook noted in
`lesson_manager::complete_lesson`). Status `waiting_student` reject → `cancelled`.
**Teacher UI:** `my_lessons.php` (US-TR-1-2) — teacher manages lessons (respond/start/complete/
absence/cancel/reschedule), reached via **Preferences → User account → "My lessons"** (teachers only,
gated like the profile page). Postman: `docs/api/Academy_Lessons_Flex.postman_collection.json`.
NOTE: admin/teacher UI pages ARE part of this backend project (only the *student* UI is the separate
frontend) — build the relevant admin/teacher Moodle pages alongside each phase's APIs. Admin lessons/
attendance UI is its own story (US-AD-3-1, Phase 4).

#### Phase 2 — original notes
Stories: US-LS-1-1 (request), US-LS-2-1/2-2/2-3 (accept/reject/suggest negotiation),
US-LS-3-1 start, 3-2 complete, 3-3 student absent, 3-4 teacher absent, 4-1 student cancel,
4-2 teacher cancel, 5-1 update time, 5-2 respond update; plus US-TR-1-2 / US-ST-2-2 (view lessons);
and the flex engine US-FN-1-2 (reserve) / US-FN-1-3 (return).
Suggested tables: `academy_lessons` (studentid, teacherid, subject, status, requested_time,
confirmed_time, duration, note, reject_reason, cancel_reason, purchaseid, actual_start, actual_end,
timecreated, timemodified), `academy_lesson_proposals` (lessonid, proposed_by, role, proposed_time,
type=suggest|reschedule, status), `academy_flex_tx` (ledger: userid, purchaseid, lessonid, type
[reserve|consume|return|expire|adjust], amount, balance_before, balance_after, performedby, reason, timecreated).
Status machine: see `docs/specs/00-overview.md` (Pending → Waiting for Student/Teacher → Confirmed →
In Progress → Completed | Student Absent | Teacher Absent | Cancelled / Cancelled by Teacher | Rejected).
Reserve 1 flex on Confirm (US-FN-1-2); return on teacher cancel/absence or early student cancel (US-FN-1-3);
honor settings deadlines (min_booking, cancel_deadline, update_deadline, start_allowed, absence_report).

### Phase 3 — Financial
US-FN-1-4 distribute revenue on Complete (teacher_percent/platform_percent of flex value),
US-FN-1-5 admin reversal of a consumed flex, US-FN-2-1 teacher withdrawal request, US-FN-2-2 admin process
withdrawal; teacher wallet + platform wallet model (see `docs/specs/financial/00-wallet-model.md`).
Suggested tables: `academy_earnings` (lessonid, teacherid, flex_value, teacher_amount, platform_amount,
percents, status active|reversed), `academy_withdrawals` (teacherid, amount, method, account, reference,
status Pending|Approved|Rejected|Paid, reason, processedby). Wallet balance = active earnings − reserved/paid
withdrawals. Flex value = purchase price_paid ÷ flex_count.

### Phase 4 — Admin reports + assign + teacher earnings/export
US-AD-3-1 lessons/attendance reports, US-AD-3-2 platform earnings, US-AD-3-3 package/flex reports,
US-AD-3-4 student flex balance + history (reads academy_flex_tx), US-AD-4-1 assign package to student
(admin, offline payment → reuse purchase logic with source=admin_assigned), US-TR-1-3 teacher earnings/
withdrawals view, US-TR-2-1 export (CSV/PDF).

### Final
One consolidated Postman collection + "how to use" doc covering all phases (extend
`platform-apis-postman-guide.md` / a new collection). CSV/PDF export: CSV via simple text; PDF can use
Moodle's `\core\dompdf` or defer.

## 9. How to verify (pattern used here)

`curl` the endpoint with a token; for POST use `-X POST --data-urlencode`. Reset opcache after editing
api.php. For UI pages, script a login (grab `logintoken` from /login/index.php, POST creds) then curl the
page and grep for expected markup. Clean up any test rows you create in the DB.
