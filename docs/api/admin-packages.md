# Lesson Packages API — Frontend Developer Guide

Admin-only endpoints to manage lesson (Flex) packages.
Implements user stories [US-AD-1-1](../specs/admin/US-AD-1-1-create-lesson-package.md) … [US-AD-1-4](../specs/admin/US-AD-1-4-delete-unused-lesson-package.md).

- **Plugin:** `local_academy` · tables `mdl_academy_packages`, `mdl_academy_package_purchases`
- **Audience:** the admin web dashboard (React/Vue/plain JS, etc.).

> 📮 **Use Postman?** Don't build requests by hand — import the ready collection
> [`Academy_Packages.postman_collection.json`](Academy_Packages.postman_collection.json)
> (Postman → Import → File). It has every request below (admin + student) with variables already set.
> See [§11 Postman](#11-postman-quick-start).

---

## 1. Endpoint & base URL

One endpoint handles everything; pick the action with the `function` parameter:

```
{BASE_URL}/local/academy/api.php
```

- Local dev: `http://localhost:8081`
- Staging/prod: your domain (e.g. `https://academy2026.nitg-eg.com`)

If your admin dashboard is served from the **same origin** as Moodle, calls are simple same-origin
requests. If it runs on a **different origin** (e.g. a separate Vite dev server), you'll hit CORS —
either proxy `/local/academy` through your dev server, or have the backend send `Access-Control-Allow-Origin`.

---

## 2. Authentication

Every request needs a `token` (a Moodle web-service token tied to a user).

Get one by exchanging admin credentials:

```
POST {BASE_URL}/login/token.php
Content-Type: application/x-www-form-urlencoded

username=admin&password=PASSWORD&service=moodle_mobile_app
→ { "token": "abc123...", "privatetoken": null }
```

Store the returned `token` and send it as a `token` param on every call below.

⚠️ **Admin-only.** The token's user must have the `local/academy:managepackages` capability
(site admin / manager). A normal student/teacher token returns `{"status":"fail","error":"Permission denied"}`.

---

## 3. Request & response format

- **Method:** `GET` or `POST` — params go in the query string or form body (both work).
- **Always send:** `function` + `token`.
- **Response (valid token):** always JSON
  - Success → `{ "status": "success", "data": { ... } }`
  - State-changing actions also include a localised `message`, e.g.
    `{ "status": "success", "message": "Package created.", "data": { "packageid": 12 } }`
  - Failure → `{ "status": "fail", "error": "message" }`
- ⚠️ **Invalid/expired token → HTML, not JSON** (see §7). Treat any non-JSON response as "auth failed".

---

## 3.1 Localization (`?lang`) & multilang content

**System messages** (`error` and `message`) are localised. Add `lang=en` or `lang=ar` to any request
and the response text comes back in that language:

```
GET {BASE_URL}/local/academy/api.php?function=get_packages&token=TOKEN&lang=ar
```

- Valid values: any installed language code (`en`, `ar`). Invalid/omitted → the caller's default language,
  so existing clients are unaffected.
- Applies to both `error` (failures) and the new `message` (successes).

**Multilang content fields** (`name`, `description`). To offer a package name/description in more than
one language, store a **multilang** value in the field — both languages in one string, using the
**Multi-Language Content (v2)** filter syntax the site runs:

```
{mlang en}Gold Package{mlang}{mlang ar}الحزمة الذهبية{mlang}
```

Send that exact string as `name` when you `create_package` / `update_package` (the field accepts it).
Behaviour on read:

- **Admin reads** (`get_packages`, `get_package`) return the **raw** multilang string, so your admin
  editor can show and edit every language.
- **Student reads** (`get_available_packages`, `get_my_packages`, `get_payment_history`) return the
  name/description already **resolved** to the request language (via `?lang=`), so the app shows a single
  clean value.

Requires the site **Multi-Language Content (v2)** filter (`filter_multilang2`) to be enabled and the Arabic language pack installed.

---

## 4. Functions

| Action | `function` | Required params | Optional params | Story |
|--------|-----------|-----------------|-----------------|-------|
| Create | `create_package` | `name`, `flex_count`, `price` | `description`, `expiration_days` (default `0` = unlimited), `active` (default `1`) | US-AD-1-1 |
| Update | `update_package` | `id` | any of `name`, `description`, `flex_count`, `price`, `expiration_days`, `status` | US-AD-1-2 |
| Deactivate | `deactivate_package` | `id` | — | US-AD-1-3 |
| Reactivate | `activate_package` | `id` | — | US-AD-1-3 |
| Delete | `delete_package` | `id` | — | US-AD-1-4 |
| List | `get_packages` | — | `status` = `active` \| `inactive` (omit = all) | helper |
| Get one | `get_package` | `id` | — | helper |

### Package object
| Field | Type | Notes |
|-------|------|-------|
| `id` | int | package id |
| `name` | string | |
| `description` | string | |
| `flex_count` | int | lessons in the package (> 0) |
| `price` | decimal string | e.g. `"1000.00"` (EGP) — note it's a string in JSON |
| `expiration_days` | int | days valid after purchase; `0` = never expires |
| `status` | string | `active` or `inactive` |
| `timecreated` / `timemodified` | int | unix timestamps (seconds) |

---

## 5. Usage from the browser (fetch)

A small wrapper handles the token, query building, and the JSON-vs-HTML auth check:

```js
const BASE_URL = "http://localhost:8081";
let TOKEN = localStorage.getItem("ws_token");

// One-time: exchange admin credentials for a token.
async function login(username, password) {
  const body = new URLSearchParams({ username, password, service: "moodle_mobile_app" });
  const res = await fetch(`${BASE_URL}/login/token.php`, { method: "POST", body });
  const data = await res.json();
  if (!data.token) throw new Error(data.error || "Login failed");
  TOKEN = data.token;
  localStorage.setItem("ws_token", TOKEN);
  return TOKEN;
}

// Call any package function. Returns `data` on success, throws on failure.
async function packageApi(func, params = {}) {
  const qs = new URLSearchParams({ function: func, token: TOKEN, ...params });
  const res = await fetch(`${BASE_URL}/local/academy/api.php?${qs}`);
  const text = await res.text();
  let json;
  try { json = JSON.parse(text); }
  catch { throw new Error("AUTH_FAILED"); } // invalid token → HTML, not JSON (see §7)
  if (json.status !== "success") throw new Error(json.error || "Request failed");
  return json.data;
}
```

### Examples

```js
// Create
const { packageid } = await packageApi("create_package", {
  name: "Flex10", description: "Ten lessons",
  flex_count: 10, price: 1000, expiration_days: 90, active: 1,
});

// List active packages (for a table)
const packages = await packageApi("get_packages", { status: "active" });

// Update (send only what changed)
await packageApi("update_package", { id: packageid, price: 1100, flex_count: 12 });

// Deactivate / reactivate (toggle)
await packageApi("deactivate_package", { id: packageid });
await packageApi("activate_package",   { id: packageid });

// Delete — may fail if the package was ever purchased
try {
  await packageApi("delete_package", { id: packageid });
} catch (e) {
  // e.message: "This package has purchase records and cannot be deleted. Deactivate it instead."
}
```

### Quick check with curl
```bash
B=http://localhost:8081; T=YOUR_TOKEN
curl "$B/local/academy/api.php?function=get_packages&token=$T"
curl "$B/local/academy/api.php?function=create_package&token=$T&name=Flex10&flex_count=10&price=1000&expiration_days=90&active=1"
```

---

## 6. Errors to handle

| `error` message | Cause | UI hint |
|-----------------|-------|---------|
| `Authentication required` | missing `token` | redirect to login |
| `Invalid token` | bad/expired token | redirect to login |
| `Permission denied` | token user is not admin/manager | show "no access" |
| `Package not found` | bad `id` | refresh the list |
| `Flex count must be greater than zero` | `flex_count` ≤ 0 | inline form validation |
| `Price cannot be negative` | `price` < 0 | inline form validation |
| `Status must be "active" or "inactive"` | bad `status` on update | — |
| `This package has purchase records and cannot be deleted. Deactivate it instead.` | delete blocked | offer "Deactivate" instead of "Delete" |

---

## 7. Behaviour the UI must reflect

- **Invalid/expired token returns an HTML error page, not JSON.** The platform authenticates `?token=`
  globally (a core patch in `lib/setup.php`), so a bad token errors before the endpoint runs. Detect a
  failed `JSON.parse` (as in the wrapper) and treat it as "session expired → re-login".
- **Edits apply to future purchases only.** `update_package` changes only the fields you send; packages
  already purchased keep their original price / flex / expiration (a snapshot is stored at purchase time).
- **Purchased packages can't be deleted — only deactivated.** Show "Deactivate" instead of "Delete" when
  a package has purchases (or just handle the delete error and fall back to deactivate).
- **Inactive packages should be hidden from students.** The student-facing list (later) uses
  `get_packages?status=active`.

---

## 8. Rules enforced server-side
- `flex_count` > 0; `price` ≥ 0; `expiration_days` ≥ 0 (`0` = unlimited).
- `delete_package` succeeds only when no purchase row references the package.

## 9. Not implemented yet
- No admin UI is shipped — these are API endpoints only (this doc is the contract for building one).
- The US-AD-1-1 "end date / sale window" wording is **not** implemented — only `expiration_days`
  (per-purchase validity). Revisit if a purchasable-window is actually needed.

## 10. Server setup needed once (per environment)
Web services + a token must exist on the server:
1. Site admin → Advanced features → **Enable web services**.
2. Site admin → Server → Web services → **Manage protocols** → enable **REST**.
3. Site admin → Server → Web services → **Manage tokens** → create a token for an admin user.

(On local dev this is already done.)

---

## 11. Postman quick start

A ready collection is in the repo — you do **not** build requests from this doc by hand.

1. Open Postman → **Import** (top-left) → **File** → choose
   `docs/api/Academy_Packages.postman_collection.json` → **Import**.
2. A collection **"Academy — Packages API"** appears with two folders: *Admin — Packages* and
   *Student — Packages*, plus a **"0. Login (get token)"** request.
3. Run **0. Login (get token)** once. It logs in as `admin` / `123456` and **auto-saves** the returned
   token into the collection variable `{{token}}` — every other request then just works.
4. Click any request → **Send**.

**Collection variables** (edit via the collection's *Variables* tab):
- `base_url` — default `http://localhost:8081` (change for staging/prod).
- `token` — filled automatically by the Login request (or paste one manually).
- `packageid` — the id used by Get/Update/Deactivate/Delete (set it to a real id from *List Packages*).

Notes:
- GET requests carry params in the URL query; **Purchase Package** is a **POST** with a urlencoded body
  (already configured).
- A bad/expired token returns an HTML page instead of JSON — re-run **0. Login**.
