# Moodle Core Web Services — Teacher CRUD Guide

Core Moodle REST web services for managing teacher accounts and role assignments.
These are **built-in Moodle functions** — no custom plugin required.

> For the custom academy teacher APIs (`get_all_teachers`, `browse_teachers`, `get_teacher_profile`, etc.)
> see [platform-apis-postman-guide.md](platform-apis-postman-guide.md) and the
> `Academy_Platform.postman_collection.json` collection.

---

## 1. Basics

- **Endpoint:** `{BASE_URL}/webservice/rest/server.php`
- **Required params on every call:**
  - `wstoken` — Moodle web-service token (obtain via `login/token.php`)
  - `wsfunction` — name of the function to call
  - `moodlewsrestformat=json` — get JSON back (omit = XML)
- **Auth token:**
  ```
  POST {BASE_URL}/login/token.php
  username=admin&password=PASS&service=moodle_mobile_app
  → { "token": "abc123..." }
  ```
- **HTTP method:** all calls can be sent as GET (params in query string) or POST
  (params in body). POST is safer for calls that write data.
- **Error shape:**
  ```json
  { "exception": "...", "errorcode": "...", "message": "..." }
  ```
- **Capabilities needed:** most write operations require admin or a token issued
  for a service that includes the function. The `moodle_mobile_app` service
  includes read functions; for `create/update/delete` you may need a token from
  a custom service with those functions whitelisted.

---

## 2. Default Teacher Role IDs

| roleid | shortname | Description |
|--------|-----------|-------------|
| 3 | `editingteacher` | Teacher (can edit course content) |
| 4 | `teacher` | Non-editing teacher |

Confirm these on your instance with `core_role_get_roles` (see §7).

---

## 3. Get Teachers

### 3.1 Academy custom — `get_all_teachers` (recommended for admin use)

This is the purpose-built academy endpoint. It returns **every Moodle user who holds
a `teacher` or `editingteacher` role** — regardless of whether they have saved an
academy profile yet. Teachers without a profile appear with empty fields and default
values (`approved=1`, `available=1`).

**Endpoint:** `{BASE_URL}/local/academy/api.php`
**Method:** GET
**Auth:** admin token (`local/academy:manageplatform`)

```
GET /local/academy/api.php
  ?function=get_all_teachers
  &token=ADMIN_TOKEN
  [&approved=1]
  [&available=1]
  [&subject=Math]
  [&year=Year+10]
  [&courseid=2]
  [&categoryid=5]
  [&search=ahmed]
  [&page=0]
  [&perpage=20]
```

All filter params are optional and are AND-ed together.

| Param | Type | Description |
|-------|------|-------------|
| `approved` | `0` / `1` | Match `approved` field. Omit to return all. Teachers with no profile default to `1`. |
| `available` | `0` / `1` | Match `available` field. Omit to return all. Teachers with no profile default to `1`. |
| `subject` | string | Partial, case-insensitive match on any of the teacher's saved subjects. |
| `year` | string | Partial, case-insensitive match on any of the teacher's saved year/grade levels (e.g. `Year 10`, `Grade 5`, `KG2`). |
| `courseid` | int | Teacher must hold a teacher/editingteacher role in this specific course. |
| `categoryid` | int | Teacher must teach in at least one course inside this category. |
| `search` | string | Partial, case-insensitive match on `firstname`, `lastname`, or `email`. |
| `page` | int | 0-based page index. Default `0`. |
| `perpage` | int | Results per page. Default `20`, max `200`. |

**Response:**
```json
{ "status": "success", "data": {
  "total": 42,
  "page": 0,
  "perpage": 20,
  "teachers": [
    {
      "userid": 16,
      "fullname": "Dr. Mohamed Ali",
      "email": "m.ali@example.com",
      "headline": "Senior Math Teacher",
      "bio": "10 years in education",
      "experience": "10 years",
      "photourl": "",
      "rating": 4.5,
      "approved": 1,
      "available": 1,
      "subjects": [ { "subject": "Math", "specialization": "Algebra" } ],
      "years":    [ "Year 10", "Year 11" ],
      "hours":    [ { "dayofweek": 1, "starttime": "09:00", "endtime": "12:00" } ]
    }
  ]
} }
```

**Pagination:** use `total ÷ perpage` to compute the number of pages. Results are ordered `lastname ASC, firstname ASC`.

**Errors:** `{"status":"fail","error":"Permission denied"}` if the token does not belong to an admin.

**Postman:** **Admin — All Teachers** folder in `Academy_Platform.postman_collection.json`.

---

### 3.2 Get all enrolled teachers in a course (core Moodle)

```
GET /webservice/rest/server.php
  ?wsfunction=core_enrol_get_enrolled_users
  &wstoken=TOKEN
  &moodlewsrestformat=json
  &courseid=2
  &options[0][name]=roleid
  &options[0][value]=3
```

| Param | Value | Notes |
|-------|-------|-------|
| `courseid` | e.g. `2` | The course to query |
| `options[0][name]` | `roleid` | Filter by role |
| `options[0][value]` | `3` or `4` | 3 = editingteacher, 4 = non-editing teacher |

**Response (excerpt):**
```json
[
  {
    "id": 16,
    "username": "teacher1",
    "firstname": "Mohamed",
    "lastname": "Ali",
    "email": "teacher@example.com",
    "roles": [{ "roleid": 3, "shortname": "editingteacher", "name": "Teacher" }]
  }
]
```

---

### 3.3 Search / filter users broadly (core Moodle)

Use when you need to find a teacher by email, username, or any profile field.

```
GET /webservice/rest/server.php
  ?wsfunction=core_user_get_users
  &wstoken=TOKEN
  &moodlewsrestformat=json
  &criteria[0][key]=email
  &criteria[0][value]=teacher@example.com
```

Supported `key` values: `id`, `idnumber`, `username`, `email`, `auth`, `confirmed`,
`firstname`, `lastname`, `city`, `country`, `phone1`, `phone2`, `department`,
`institution`, `interests`, `firstaccess`, `lastaccess`.

To get **all users** (no filter): omit `criteria` entirely — requires admin token
and returns every user; paginate with `limitfrom` / `limitnum` if needed.

---

### 3.4 Get specific users by field (core Moodle)

```
GET /webservice/rest/server.php
  ?wsfunction=core_user_get_users_by_field
  &wstoken=TOKEN
  &moodlewsrestformat=json
  &field=id
  &values[0]=16
  &values[1]=17
```

`field` can be: `id`, `idnumber`, `username`, `email`.

---

## 4. Create a Teacher

Creating a teacher is **two steps**: create the Moodle user, then enrol them in a
course with the teacher role.

### Step 1 — Create user

```
POST /webservice/rest/server.php
```

Body (url-encoded):

| Key | Example | Notes |
|-----|---------|-------|
| `wsfunction` | `core_user_create_users` | |
| `wstoken` | `TOKEN` | admin token |
| `moodlewsrestformat` | `json` | |
| `users[0][username]` | `newteacher` | must be unique, lowercase |
| `users[0][password]` | `Abc123!@#` | must meet site password policy |
| `users[0][firstname]` | `Ahmed` | |
| `users[0][lastname]` | `Hassan` | |
| `users[0][email]` | `ahmed@example.com` | must be unique |
| `users[0][auth]` | `manual` | or `email`, `oauth2`, etc. |
| `users[0][lang]` | `ar` | optional, e.g. `en`, `ar` |
| `users[0][timezone]` | `Africa/Cairo` | optional |

**Response:**
```json
[{ "id": 42, "username": "newteacher" }]
```

### Step 2 — Enrol in course as teacher

```
POST /webservice/rest/server.php
```

| Key | Example |
|-----|---------|
| `wsfunction` | `enrol_manual_enrol_users` |
| `wstoken` | `TOKEN` |
| `moodlewsrestformat` | `json` |
| `enrolments[0][roleid]` | `3` (editingteacher) |
| `enrolments[0][userid]` | `42` |
| `enrolments[0][courseid]` | `2` |

**Response:** empty array `[]` on success.

---

## 5. Update a Teacher

Update any user profile field. Only send the fields you want to change.
`id` is always required.

```
POST /webservice/rest/server.php
```

| Key | Example |
|-----|---------|
| `wsfunction` | `core_user_update_users` |
| `wstoken` | `TOKEN` |
| `moodlewsrestformat` | `json` |
| `users[0][id]` | `42` |
| `users[0][firstname]` | `Ahmed` |
| `users[0][lastname]` | `Ibrahim` |
| `users[0][email]` | `newemail@example.com` |
| `users[0][suspended]` | `0` (unsuspend) / `1` (suspend) |
| `users[0][password]` | `NewPass!1` (optional) |

**Response:** empty array `[]` on success.

---

## 6. Delete / Remove a Teacher

### 6.1 Delete user account (soft-delete / suspend)

Moodle does not truly delete users by default — it marks them deleted.

```
POST /webservice/rest/server.php
```

| Key | Example |
|-----|---------|
| `wsfunction` | `core_user_delete_users` |
| `wstoken` | `TOKEN` |
| `moodlewsrestformat` | `json` |
| `userids[0]` | `42` |

**Response:** empty array `[]` on success.

> Prefer **suspending** (`update_users` with `suspended=1`) over deletion to
> preserve lesson history, financial records, and audit logs.

### 6.2 Remove teacher from a course (unenrol)

```
POST /webservice/rest/server.php
```

| Key | Example |
|-----|---------|
| `wsfunction` | `enrol_manual_unenrol_users` |
| `wstoken` | `TOKEN` |
| `moodlewsrestformat` | `json` |
| `enrolments[0][userid]` | `42` |
| `enrolments[0][courseid]` | `2` |

### 6.3 Remove teacher role without unenrolling

```
POST /webservice/rest/server.php
```

| Key | Example |
|-----|---------|
| `wsfunction` | `core_role_unassign_roles` |
| `wstoken` | `TOKEN` |
| `moodlewsrestformat` | `json` |
| `unassignments[0][roleid]` | `3` |
| `unassignments[0][userid]` | `42` |
| `unassignments[0][contextlevel]` | `course` |
| `unassignments[0][instanceid]` | `2` (courseid) |

---

## 7. Utility — List All Roles

Use this to confirm role IDs on your specific Moodle instance.

```
GET /webservice/rest/server.php
  ?wsfunction=core_role_get_roles
  &wstoken=TOKEN
  &moodlewsrestformat=json
```

**Response:**
```json
[
  { "id": 3, "name": "", "shortname": "editingteacher", "description": "...", "sortorder": 3 },
  { "id": 4, "name": "", "shortname": "teacher",        "description": "...", "sortorder": 4 }
]
```

---

## 8. Function Reference Table

| Function | Endpoint | Operation | Needs admin? |
|----------|----------|-----------|-------------|
| `get_all_teachers` | `local/academy/api.php` | List all academy teachers with filters + pagination | Yes (`manageplatform`) |
| `core_enrol_get_enrolled_users` | `webservice/rest/server.php` | List teachers in a course | No (enrolled user) |
| `core_user_get_users` | `webservice/rest/server.php` | Search all users | Yes (admin) |
| `core_user_get_users_by_field` | `webservice/rest/server.php` | Get specific users by id/email | Yes |
| `core_user_create_users` | `webservice/rest/server.php` | Create user account | Yes |
| `enrol_manual_enrol_users` | `webservice/rest/server.php` | Enrol user in course with role | Yes |
| `core_user_update_users` | `webservice/rest/server.php` | Update user profile fields | Yes |
| `enrol_manual_unenrol_users` | `webservice/rest/server.php` | Remove user from course | Yes |
| `core_role_assign_roles` | `webservice/rest/server.php` | Assign role in a context | Yes |
| `core_role_unassign_roles` | `webservice/rest/server.php` | Remove role from a context | Yes |
| `core_user_delete_users` | `webservice/rest/server.php` | Delete user account | Yes |
| `core_role_get_roles` | `webservice/rest/server.php` | List all roles and their IDs | No |

---

## 9. Common Errors

| Error code | Meaning | Fix |
|------------|---------|-----|
| `invalidtoken` | Token expired or wrong | Re-login to get a fresh token |
| `accessdenied` | Function not in the token's service | Add function to the web service |
| `nopermission` | User lacks the capability | Use an admin token |
| `invalidparameter` | Wrong param name or format | Check param names carefully (array bracket syntax) |
| `usernotfound` | User id doesn't exist | Verify the userid |
