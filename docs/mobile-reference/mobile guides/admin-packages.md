# Admin — Lesson Packages API

Implements [US-AD-1-1](../specs/admin/US-AD-1-1-create-lesson-package.md) … [US-AD-1-4](../specs/admin/US-AD-1-4-delete-unused-lesson-package.md).

- **Endpoint:** `GET|POST /local/academy/api.php`
- **Plugin:** `local_academy` (tables `mdl_academy_packages`, `mdl_academy_package_purchases`)
- **Auth:** `token={wstoken}` — a Moodle web-service token. The token's user must have the
  `local/academy:managepackages` capability at system context (site admins / managers).
- **Response:** `{ "status": "success", "data": ... }` or `{ "status": "fail", "error": "message" }`

## Functions

| `function`           | Params                                                                                  | Story                  |
| -------------------- | --------------------------------------------------------------------------------------- | ---------------------- |
| `create_package`     | `name`, `flex_count`, `price`, `expiration_days`(=0), `description`(opt), `active`(=1)  | US-AD-1-1              |
| `update_package`     | `id` + any of `name`, `description`, `flex_count`, `price`, `expiration_days`, `status` | US-AD-1-2              |
| `deactivate_package` | `id`                                                                                    | US-AD-1-3              |
| `activate_package`   | `id`                                                                                    | US-AD-1-3 (reactivate) |
| `delete_package`     | `id`                                                                                    | US-AD-1-4              |
| `get_packages`       | `status`(opt: `active`\|`inactive`)                                                     | helper / admin list    |
| `get_package`        | `id`                                                                                    | helper                 |

### Examples

```
# Create
GET /local/academy/api.php?function=create_package&token=TOKEN
  &name=Flex10&description=Ten+lessons&flex_count=10&price=1000&expiration_days=90&active=1
→ { "status":"success", "data":{ "packageid":1 } }

# Update (only sent fields change; applies to future purchases only)
GET /local/academy/api.php?function=update_package&token=TOKEN&id=1&price=1100&flex_count=12

# Deactivate / reactivate
GET /local/academy/api.php?function=deactivate_package&token=TOKEN&id=1
GET /local/academy/api.php?function=activate_package&token=TOKEN&id=1

# Delete (blocked if ever purchased)
GET /local/academy/api.php?function=delete_package&token=TOKEN&id=1
→ fail: "This package has purchase records and cannot be deleted. Deactivate it instead."

# List
GET /local/academy/api.php?function=get_packages&token=TOKEN&status=active
```

## Rules enforced

- `flex_count` > 0; `price` ≥ 0; `expiration_days` ≥ 0 (`0` = unlimited).
- `update_package` only changes the fields present in the request; existing purchases keep their
  snapshot terms (price/flex/expiration recorded at purchase time in `academy_package_purchases`).
- `delete_package` succeeds only when no purchase row references the package; otherwise deactivate.

## Known limitations / TODO

- The HTTP layer requires a web-service token; the manager logic is smoke-tested against the DB, but the
  full token→capability path still needs a manual token test (see "How to test" below).
- `expiration_days` is the rule; the US-AD-1-1 "end date" wording is treated as not applicable (no sale
  window). Revisit if a package sale window is actually wanted.
- No admin UI yet — these are API endpoints only.

## How to test over HTTP (get an admin token)

1. Enable web services: Site admin → Advanced features → **Enable web services**.
2. Enable a protocol: Site admin → Server → Web services → **Manage protocols** → enable **REST**.
3. Create a token: Site admin → Server → Web services → **Manage tokens** → create one for an admin user
   (any service — the token only identifies the user; this API does its own capability check).
4. Call `…/local/academy/api.php?function=get_packages&token=YOUR_TOKEN`.
