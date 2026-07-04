# Registration API — Guide (Standard Moodle Web Service)

How to register users (create accounts) through the **standard Moodle REST web service**
`core_user_create_users`, and how to set the **Year** field at the same time.

> **Why this approach?** The old custom endpoints (`sign_up`, `sign_up_new`, `signUpParent`
> in `academy/academyApi/json.php`, and the `/login/signup.php` page) are tied to
> per-teacher folders and a `userdata=14` cookie/tenant hack. They are fragile and
> **not recommended**. Use the core web service below — it's the officially supported,
> integration-friendly way.

---

## 1. What you need

- **Web services + REST** enabled (already on for this site: `enablewebservices=1`,
  `webserviceprotocols=rest`).
- An **external service** that exposes `core_user_create_users`.
- An **admin/manager token** whose user has the `moodle/user:create` capability
  (setting profile fields also needs `moodle/user:update` — a manager/admin has both).

### One-time setup (admin, in the Moodle UI)

1. **Site administration → Server → Web services → External services** → *Add*.
   - Name: `Registration API`, Shortname: `registration_api`, **Enabled** ✔,
     **Authorised users only** ✔ (recommended).
2. Open the new service's **Functions** → add **`core_user_create_users`**
   (add `core_role_assign_roles` too if you want to assign the student role — see §5).
3. **Authorised users** → add the admin/manager account that will own the token.
4. **Site administration → Server → Web services → Manage tokens** → *Create token*
   for that user + the `Registration API` service. Copy the token → this is your
   `{{admin_token}}`.

> Security: keep this token server-side (in your CRM / eCommerce backend). It can create
> accounts, so never ship it in a mobile app or browser.

---

## 2. Create a user (with the Year field)

- **Endpoint:** `{BASE_URL}/webservice/rest/server.php`
- **Method:** `GET` or `POST` (POST recommended — cleaner for the array params).
- **Format:** `moodlewsrestformat=json`.

### Parameters (per user, index `[0]`, `[1]`, …)

| Param                               | Required | Notes |
|-------------------------------------|----------|-------|
| `wstoken`                           | ✅       | The admin token from §1 |
| `wsfunction`                        | ✅       | `core_user_create_users` |
| `moodlewsrestformat`                | ✅       | `json` |
| `users[0][username]`                | ✅       | Lowercase; unique |
| `users[0][password]`                | ✅*      | Must meet the site password policy. *Or use `createpassword=1` to email one, or `auth=nologin` |
| `users[0][firstname]`               | ✅       | |
| `users[0][lastname]`                | ✅       | |
| `users[0][email]`                   | ✅       | Unique, valid |
| `users[0][auth]`                    | optional | Defaults to `manual` |
| `users[0][customfields][0][type]`   | for Year | The profile-field **shortname** → `year` |
| `users[0][customfields][0][value]`  | for Year | One of the Year options (see below) |

### The `Year` field

`year` is a Moodle **custom profile field** (menu). Set it via the `customfields` array,
where `type` is the field shortname (`year`) and `value` is the **exact option text**:

```
primary 1, primary 2, primary 3, primary 4, primary 5, primary 6,
preparatory 1, preparatory 2, preparatory 3,
Secondary 1, Secondary 2, Secondary 3
```

> Unlike the old custom API (which took an integer 1–12), the standard API expects the
> **text value** of the option, e.g. `Secondary 1`.

### Example — GET (flat URL)

```
GET {BASE_URL}/webservice/rest/server.php
  ?wstoken={{admin_token}}
  &wsfunction=core_user_create_users
  &moodlewsrestformat=json
  &users[0][username]=ahmed.ali
  &users[0][password]=SecurePass123!
  &users[0][firstname]=Ahmed
  &users[0][lastname]=Ali
  &users[0][email]=ahmed.ali@example.com
  &users[0][auth]=manual
  &users[0][customfields][0][type]=year
  &users[0][customfields][0][value]=Secondary 1
```

### Example — POST (application/x-www-form-urlencoded)

```
wstoken={{admin_token}}
wsfunction=core_user_create_users
moodlewsrestformat=json
users[0][username]=ahmed.ali
users[0][password]=SecurePass123!
users[0][firstname]=Ahmed
users[0][lastname]=Ali
users[0][email]=ahmed.ali@example.com
users[0][customfields][0][type]=year
users[0][customfields][0][value]=Secondary 1
```

### Success response

```json
[
  { "id": 1234, "username": "ahmed.ali" }
]
```

### Common errors

| Response snippet | Meaning / fix |
|------------------|---------------|
| `accessexception` / `Access control exception` | Token user lacks `moodle/user:create`, or the function isn't in the service |
| `Username already exists` | Duplicate `username` |
| `Email address already exists` | Duplicate `email` |
| `invalidparameter` + password policy text | Password too weak — meet the site policy |
| `Invalid parameter value detected ... customfields` | Wrong `type` (must be `year`) or a `value` not in the option list |
| HTML page instead of JSON | Bad/expired token, or web services disabled |

---

## 3. Register several users at once

Just add more indexes:

```
users[0][username]=ahmed.ali   ... users[0][customfields][0][type]=year ...
users[1][username]=sara.hassan ... users[1][customfields][0][type]=year ...
```

---

## 4. Verify a created user

```
GET {BASE_URL}/webservice/rest/server.php
  ?wstoken={{admin_token}}
  &wsfunction=core_user_get_users_by_field
  &moodlewsrestformat=json
  &field=username
  &values[0]=ahmed.ali
```
The response includes `customfields` with the `year` value.

---

## 5. (Optional) Make them a student

`core_user_create_users` only creates the account. To grant the **student** role at the
system level (mirrors what the old custom flow did), add `core_role_assign_roles` to your
service and call:

```
GET {BASE_URL}/webservice/rest/server.php
  ?wstoken={{admin_token}}
  &wsfunction=core_role_assign_roles
  &moodlewsrestformat=json
  &assignments[0][roleid]=5           # 5 = student
  &assignments[0][userid]=1234
  &assignments[0][contextid]=1        # 1 = system context
```

To instead **enrol** them into a specific course, use `enrol_manual_enrol_users`
(`courseid`, `roleid=5`, `userid`).

---

## 6. Get profile fields (with option values)

The custom `local_profilefields` plugin exposes a single endpoint that returns every
custom profile field defined on the site, including the **exact option texts** for
`menu` fields — so you always know which values to send to `core_user_create_users`.

- **Function:** `local_profilefields_get_profile_fields`
- **Method:** `GET`
- **No parameters**

```
GET {BASE_URL}/webservice/rest/server.php
  ?wstoken={{admin_token}}
  &wsfunction=local_profilefields_get_profile_fields
  &moodlewsrestformat=json
```

### Response

```json
[
  {
    "id": 1,
    "shortname": "year",
    "name": "Year",
    "datatype": "menu",
    "description": "",
    "required": true,
    "visible": 2,
    "defaultvalue": "",
    "categoryid": 1,
    "categoryname": "Other fields",
    "options": [
      "primary 1", "primary 2", "primary 3", "primary 4", "primary 5", "primary 6",
      "preparatory 1", "preparatory 2", "preparatory 3",
      "Secondary 1", "Secondary 2", "Secondary 3"
    ]
  }
]
```

| Field | Description |
|-------|-------------|
| `shortname` | Pass this as `customfields[N][type]` when creating a user |
| `datatype` | `text`, `textarea`, `menu`, `checkbox`, `datetime` |
| `required` | Whether the field is mandatory on signup |
| `visible` | `0` = hidden, `1` = hidden in profile, `2` = visible |
| `options` | Exact values accepted by `customfields[N][value]` — only populated for `menu` fields |

> **Plugin:** Requires `local/profilefields` installed. After placing the folder under
> `local/profilefields/`, visit **Site admin → Notifications** once to complete the install.
> The function is automatically added to the Moodle mobile web service — no extra token setup.

---

## 7. Migrating off the old custom endpoints

| Old (custom, deprecated)                     | New (standard) |
|----------------------------------------------|----------------|
| `json.php?function=sign_up`                  | `core_user_create_users` |
| `json.php?function=sign_up_new`              | `core_user_create_users` (+ `core_role_assign_roles`) |
| `json.php?function=signUpParent`             | `core_user_create_users` with `roleid=9` in the role assign step |
| `/login/signup.php` page                     | Your own frontend calling the API, or Moodle's built-in email-based self-registration |
| `year` as int 1–12                           | `customfields[0][type]=year`, `value=<option text>` |

See [Registration_API.postman_collection.json](Registration_API.postman_collection.json)
for ready-to-run requests (includes the "Get profile fields" request).
