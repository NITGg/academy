# Forgot / Reset Password — Mobile API

How the Flutter app lets a user reset a forgotten password.

Moodle handles password reset in **two stages**:

1. **App → API:** the user enters their email (or username); the app calls one
   web-service function, and Moodle emails them a reset link.
2. **Email link → Web:** the user taps the link in the email, which opens a
   Moodle web page where they set a new password.

The app is only involved in **stage 1**. There is no "set the new password from
the app" call — Moodle deliberately does the actual reset on a signed link so the
new password is never sent through the app.

```
[App] enter email ──► core_auth_request_password_reset ──► Moodle sends email
                                                             │
[User] opens email ─────────────────────────────────────────┘
        └► taps reset link ──► Moodle web page ──► sets new password ──► done
```

---

## The function

| | |
|---|---|
| **Function** | `core_auth_request_password_reset` |
| **Auth** | Pre-login — no user token (the user is locked out). Use the shared **Registration API** token (the same one the app uses for signup). |
| **Endpoint** | `POST /webservice/rest/server.php` |
| **HTTP method** | POST |

> ⚠️ **One-time setup:** the function must be added to the **Registration API**
> external service (Site admin → Server → Web services → External services →
> Registration API → Functions → add `core_auth_request_password_reset`).
> It's `loginrequired=false`, so the shared token can call it. Also, **outgoing
> email (SMTP) must be configured** or no reset email is sent — this is already
> set up on the server.

### Request parameters

Send **one** of these (email is recommended):

| Param | Type | Notes |
|-------|------|-------|
| `email` | string | The account's email address. |
| `username` | string | The account's username (alternative to email). |

Plus the standard web-service params:

| Param | Value |
|-------|-------|
| `wstoken` | the Registration API token |
| `wsfunction` | `core_auth_request_password_reset` |
| `moodlewsrestformat` | `json` |

### Full URL example

```
POST https://academy2026.nitg-eg.com/moodle-new/webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken=<REGISTRATION_API_TOKEN>&wsfunction=core_auth_request_password_reset&moodlewsrestformat=json&email=student@example.com
```

---

## Response

```json
{
  "status": "emailresetconfirmsent",
  "notice": "If you supplied a correct username or email address then an email should have been sent to you.",
  "warnings": []
}
```

### `status` values you may get

| status | Meaning | Suggested app behaviour |
|--------|---------|-------------------------|
| `emailresetconfirmsent` | Reset email sent. | ✅ Show success: "Check your email for the reset link." |
| `emailpasswordconfirmsent` | Sent (generic, when the site hides whether the account exists). | ✅ Same success message. |
| `emailalreadysent` | A reset link was already sent recently. | ✅ Tell the user to check their inbox (and spam). |
| `emailpasswordconfirmnoemail` | The account has no email on file. | ⚠️ Ask them to contact support. |
| `emailpasswordconfirmnotsent` | Could not send (no match / mail issue). | ⚠️ Show `notice`. |
| `dataerror` | Bad input (e.g. empty email). | ⚠️ Validate input, show `notice`. |

> **Always display the `notice` field to the user** — it's the human-readable,
> localized message Moodle intends to show, and it already handles the
> privacy-safe wording (the site may intentionally not reveal whether an email
> exists). Treat any `*sent*` status as success.

### Errors (HTTP-level)

If the token/function is misconfigured you'll get the standard web-service error
shape instead:

```json
{ "exception": "webservice_access_exception", "errorcode": "accessexception",
  "message": "Access control exception" }
```

- `accessexception` → the function isn't attached to the Registration API service
  (see the setup note above), or the token is wrong.

---

## Stage 2 — the email link (no app work)

The email contains a link like:

```
https://academy2026.nitg-eg.com/moodle-new/login/forgot_password.php?token=<reset-token>
```

Tapping it opens a Moodle web page where the user chooses a new password. After
that they return to the app and log in normally (`/login/token.php` or your
existing login call). Nothing else is required from the app.

> If you want the reset page to open **inside** the app, load that URL in an
> in-app browser/WebView. Once the user finishes, send them back to the login
> screen.

---

## Dart / Flutter example

```dart
Future<String> requestPasswordReset(String email) async {
  final uri = Uri.parse(
    'https://academy2026.nitg-eg.com/moodle-new/webservice/rest/server.php',
  );
  final res = await http.post(uri, body: {
    'wstoken': kRegistrationApiToken,               // shared pre-login token
    'wsfunction': 'core_auth_request_password_reset',
    'moodlewsrestformat': 'json',
    'email': email,
  });

  final data = jsonDecode(res.body) as Map<String, dynamic>;

  if (data['exception'] != null) {
    throw Exception(data['message'] ?? 'Request failed');
  }
  // Show this message to the user regardless of the exact status.
  return data['notice'] as String? ?? 'Please check your email.';
}
```

---

## Testing checklist

1. Confirm SMTP works: `docker compose exec moodle php public/local/payments/cli/mail_test.php --to=you@example.com` → `TRUE`.
2. Ensure `core_auth_request_password_reset` is on the **Registration API** service.
3. Call the function with a real account's email → expect a `*sent*` status.
4. Check the inbox (and **spam**) for the reset email → tap the link → set a new password → log in.
