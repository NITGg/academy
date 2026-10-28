# EAAC mobile API — settings feed, Google & Apple sign-in

Base URL: `https://eaac-learning.com`

---

## 1. `GET /local/multitopics/getsettings.php`

Public, no auth. `Content-Type: application/json`, `Cache-Control: no-store`.

```bash
curl -s -i https://eaac-learning.com/local/multitopics/getsettings.php
```

Every value is a JSON **string** (except `pages`). A key with no usable value is **omitted**.

New / changed keys:

| Key | Example | Notes |
|---|---|---|
| `admin_token` | — | **Not sent** for now. This feed is public, so it does not publish a web-service token. Keep using the token built into the app until a read-only guest token is agreed. |
| `google_client_id` | `"31169484251-lkhb…apps.googleusercontent.com"` | Google **Web** OAuth client id. |
| `app_in_review` | `"0"` / `"1"` | **Always sent**, default `"0"`. `"1"` while a build is in App Store / Play review. Admin: *Site administration → Plugins → Local plugins → Mobile app settings → App is in store review*. |

```json
{
  "data": {
    "google_client_id": "31169484251-lkhbei8fv9eq6tc03u498569l9jobj9r.apps.googleusercontent.com",
    "app_in_review": "0",
    "server_timeout_duration": "10",
    "prevent_screen_recording": "1",
    "watermark": "0",
    "…": "unchanged keys"
  }
}
```

---

## 2. `POST /local/googleauth/token.php` — Google sign-in

Unchanged request / response. Accepted `aud` values now include **both**:

| Platform | Client id |
|---|---|
| Web (used by Android) | `31169484251-lkhbei8fv9eq6tc03u498569l9jobj9r.apps.googleusercontent.com` |
| iOS | `31169484251-750r71bpnfim0t3daedc01ppklhf0pvn.apps.googleusercontent.com` |

The list is the plugin setting *Allowed Google client IDs* (one per line), so another client can be added without a release.
Other checks unchanged: signature, `iss`, `exp`, `email_verified`.

---

## 3. `POST /local/appleauth/token.php` — Sign in with Apple (new)

Exchanges the Apple **identity token** for a Moodle web-service token. Same response shape as Google sign-in, plus `is_new`.

### Request

`Content-Type: application/json` (form-encoded also accepted).

| Field | Required | Notes |
|---|---|---|
| `id_token` | yes | Apple `identityToken` (JWT). `idtoken` also accepted. |
| `nonce` | no | The **raw** nonce the app generated (Apple stores its SHA-256) or the exact value passed to Apple. If sent, it must match. |
| `name` | no | `{"first": "…", "last": "…"}`. Apple gives the name **only on the first authorisation**: send it then. Form-encoded: `firstname`, `lastname`. |
| `service` | no | External service shortname. Default `moodle_mobile_app`. |

```bash
curl -s -X POST https://eaac-learning.com/local/appleauth/token.php \
  -H 'Content-Type: application/json' \
  -d '{"id_token":"eyJraWQiOi…","nonce":"raw-nonce","name":{"first":"Ahmed","last":"Ali"}}'
```

### 200 OK

```json
{
  "token": "0f3c…",
  "privatetoken": "…64 chars…",
  "userid": 412,
  "is_new": true
}
```

`privatetoken` is `null` for site administrators (same policy as `/login/token.php`).

### Server checks

1. RS256 signature against Apple's keys (`https://appleid.apple.com/auth/keys`, cached 24h).
2. `iss` = `https://appleid.apple.com`.
3. `aud` ∈ plugin setting *Allowed Apple client IDs* (iOS **bundle id**; add the **Services ID** if Android/web sign-in is used).
4. `exp` / `iat` (60 s clock tolerance), `sub` present.
5. `nonce` when the app sent one.

### Account mapping

1. Apple `sub` already linked → that account.
2. Else verified e-mail matches an existing account → linked to it.
3. Else a new account is created (`is_new: true`). If Apple hid the e-mail, the account gets a placeholder `apple.<sub>@privaterelay.invalid`, replaced automatically once Apple shares a verified e-mail.

Only accounts whose auth method is in *Linkable auth methods* (default `oauth2`) can be signed into, so a password account cannot be taken over by e-mail.

### Errors

Body: `{"error": "<code>"}`.

| HTTP | `error` | Meaning |
|---|---|---|
| 400 | `idtoken_missing` | No `id_token`. |
| 401 | `invalid_idtoken` | Bad format, wrong algorithm, unknown key, or bad signature. |
| 401 | `invalid_issuer` | `iss` is not Apple. |
| 401 | `invalid_audience` | `aud` not in the allowed list. Body also has `received_aud`. |
| 401 | `token_expired` | Token expired. Get a new one from Apple. |
| 401 | `nonce_mismatch` | `nonce` sent but does not match the token. |
| 403 | `auth_not_linkable` | The matching account uses a non-linkable auth method (e.g. password account). |
| 403 | `user_suspended` / `user_not_confirmed` / `guest_not_allowed` | Account state. |
| 404 | `user_not_found` | Auto-create is off and no account matches. |
| 404 | `service_not_available` | Unknown or disabled `service`. |
| 405 | `method_not_allowed` | Not a POST. |
| 409 | `email_not_unique` | More than one account has that e-mail. |
| 429 | `rate_limited` | Too many requests from this IP (default 20/min). `Retry-After` header. |
| 503 | `plugin_disabled` / `webservices_disabled` / `no_clientids_configured` | Server not configured. |
| 503 | `apple_unavailable` | Apple's key endpoint could not be reached. Retry. |
| 503 | `site_maintenance` | Site in maintenance mode. |
