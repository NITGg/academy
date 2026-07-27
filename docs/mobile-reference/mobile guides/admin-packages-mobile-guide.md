# Lesson Packages API — Mobile Developer Guide

Admin-only endpoints to manage lesson (Flex) packages. Stories US-AD-1-1 … US-AD-1-4.

## 1. Base URL

```
{BASE_URL}/local/academy/api.php
```

- Local dev: `http://localhost:8081`
- Public/staging: your domain (e.g. `https://academy2026.nitg-eg.com`)

> `localhost` only works on the same machine. On a real device/emulator use the public domain.

## 2. Authentication

Every request needs a `token` (a Moodle web-service token). Get it once per user:

```
POST {BASE_URL}/login/token.php
Content-Type: application/x-www-form-urlencoded

username=ADMIN_USER&password=PASSWORD&service=moodle_mobile_app
→ { "token": "abc123...", "privatetoken": "" }
```

Send that value as `token` on every call below.

⚠️ **These endpoints are admin-only.** The token's user must have the _manage packages_ permission
(site admin / manager). A normal student/teacher token returns `{"status":"fail","error":"Permission denied"}`.

## 3. Request format

- Method: `GET` or `POST` (params in query string or form body — both work).
- Always include: `function` (which action) + `token`.
- Response is always JSON:
  - Success → `{ "status": "success", "data": { ... } }`
  - Failure → `{ "status": "fail", "error": "message" }`

## 4. Endpoints

| Action         | `function`           | Required params               | Optional params                                                                  |
| -------------- | -------------------- | ----------------------------- | -------------------------------------------------------------------------------- |
| Create package | `create_package`     | `name`, `flex_count`, `price` | `description`, `expiration_days` (default 0 = unlimited), `active` (default 1)   |
| Update package | `update_package`     | `id`                          | any of `name`, `description`, `flex_count`, `price`, `expiration_days`, `status` |
| Deactivate     | `deactivate_package` | `id`                          | —                                                                                |
| Reactivate     | `activate_package`   | `id`                          | —                                                                                |
| Delete         | `delete_package`     | `id`                          | —                                                                                |
| List packages  | `get_packages`       | —                             | `status` = `active` \| `inactive` (omit = all)                                   |
| Get one        | `get_package`        | `id`                          | —                                                                                |

### Field reference (package object)

| Field                          | Type           | Notes                                          |
| ------------------------------ | -------------- | ---------------------------------------------- |
| `id`                           | int            | package id                                     |
| `name`                         | string         |                                                |
| `description`                  | string         |                                                |
| `flex_count`                   | int            | lessons in the package (> 0)                   |
| `price`                        | decimal string | e.g. `"1000.00"` (EGP)                         |
| `expiration_days`              | int            | days valid after purchase; `0` = never expires |
| `status`                       | string         | `active` or `inactive`                         |
| `timecreated` / `timemodified` | int            | unix timestamps                                |

## 5. Examples (curl)

```bash
BASE=http://localhost:8081
TOK=YOUR_ADMIN_TOKEN

# Create
curl "$BASE/local/academy/api.php?function=create_package&token=$TOK\
&name=Flex10&description=Ten+lessons&flex_count=10&price=1000&expiration_days=90&active=1"
# → {"status":"success","data":{"packageid":1}}

# List active packages
curl "$BASE/local/academy/api.php?function=get_packages&token=$TOK&status=active"
# → {"status":"success","data":[{"id":"1","name":"Flex10","flex_count":"10","price":"1000.00",...}]}

# Update (send only what changes)
curl "$BASE/local/academy/api.php?function=update_package&token=$TOK&id=1&price=1100&flex_count=12"
# → {"status":"success","data":{"id":1}}

# Deactivate / reactivate
curl "$BASE/local/academy/api.php?function=deactivate_package&token=$TOK&id=1"
curl "$BASE/local/academy/api.php?function=activate_package&token=$TOK&id=1"

# Delete (only if never purchased)
curl "$BASE/local/academy/api.php?function=delete_package&token=$TOK&id=1"
# → {"status":"success","data":{"id":1,"deleted":true}}
```

## 6. Errors to handle

| Message                                                                           | Meaning                                   |
| --------------------------------------------------------------------------------- | ----------------------------------------- |
| `Authentication required`                                                         | missing token                             |
| `Invalid token`                                                                   | bad/expired token                         |
| `Permission denied`                                                               | token user is not an admin/manager        |
| `Package not found`                                                               | bad `id`                                  |
| `Flex count must be greater than zero`                                            | `flex_count` ≤ 0                          |
| `Price cannot be negative`                                                        | `price` < 0                               |
| `Status must be "active" or "inactive"`                                           | bad `status` on update                    |
| `This package has purchase records and cannot be deleted. Deactivate it instead.` | delete blocked — use `deactivate_package` |

## 7. Rules the app should reflect

- `update_package` changes only the fields you send. Edits apply to **future** purchases — already-purchased packages keep their original price/flex/expiration.
- A package that was ever purchased **cannot be deleted** — only deactivated. Show "Deactivate" instead of "Delete" for such packages.
- Inactive packages should be hidden from students (student-facing list comes later via `get_packages?status=active`).

## 8. Notes / not done yet

- HTTP token path needs a token created on the server (Site admin → Server → Web services → Manage tokens). The endpoint logic is verified against the DB.
- "End date / sale window" from the story is **not** implemented — only `expiration_days` (per-purchase validity).
