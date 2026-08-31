# Login — Mobile API

Signs a user in and returns a web-service token. Same job as Moodle's
`/login/token.php`, with one difference that is the whole reason it exists: when
an account has been **temporarily blocked** after repeated failed sign-ins, this
endpoint says so. `token.php` reports every failure as "Invalid login", so a
blocked learner is told their password is wrong and keeps retrying for the whole
lockout period (SRS AC-4.3.4).

```
POST /local/academy/api.php?function=login
```

**Auth:** pre-login → call with the shared **Registration API token**, the same
token the app uses for signup and forgot-password.

**Method: POST.** A GET is refused.

| Param | Type | Notes |
|-------|------|-------|
| `token` | string | Registration API token |
| `username` | string | the username, or the email address (the site has *Allow login via email* on) |
| `password` | string | the password as typed |
| `service` | string | web-service shortname, e.g. `moodle_mobile_app` |

### Success

```json
{ "status": "success",
  "data": { "token": "d445c94593eeeb017e49dd3022b2e48e",
            "privatetoken": null,
            "userid": 12 } }
```

`privatetoken` is only returned over **https** and never for an administrator —
identical to `token.php`. Use `token` for every subsequent API call.

### Failure

```json
{ "status": "fail",
  "error": "<message to show the user>",
  "errorcode": "<stable code to branch on>" }
```

**Show `error`. Branch on `errorcode`.** The message is translated and will be
reworded whenever the copy improves, so matching on its text breaks the first
time that happens — or the first time a user has a different language. The code
never changes.

| `errorcode` | When | `error` (English) |
|-------------|------|-------------------|
| `invalidlogin` | wrong password, **or** no such account | Invalid login, please try again |
| `accountlocked` | blocked after repeated failed attempts | Your account has been temporarily blocked after 5 failed sign-in attempts. Please try again in 15 mins, or use the unlock link we have just emailed you. |
| `usernotconfirmed` | password correct, email not confirmed yet | Your account has not been confirmed yet. Please open the confirmation link we emailed you, then sign in again. |
| `passwordisexpired` | password past its expiry | core wording |
| `noguest` | the guest account | core wording |
| `sitemaintenance` | site in maintenance, no maintenance access | core wording |
| `servicenotavailable` | `service` unknown or disabled | core wording |
| `enablewsdescription` | web services switched off site-wide | core wording |
| `restoredaccountresetpassword` | restored account, must reset first | core wording |
| `invalidtoken` / `authrequired` | the Registration API token is missing or bad | Invalid token |
| `postrequired` | `GET` used instead of `POST` | This action requires POST |

These are the same code values `/login/token.php` reports in its own `errorcode`
field, so one table covers both endpoints. `accountlocked` is the one addition —
`token.php` has no such case, which is the reason this endpoint exists.

Two behaviours worth knowing:

- Wrong password and unknown account both return `invalidlogin`, so the endpoint
  never reveals which usernames exist.
- An **unconfirmed** account with the **wrong** password also returns
  `invalidlogin`, not `usernotconfirmed`. The account state is only disclosed
  once the password has been proved correct.

Messages follow the request language: add `&lang=ar` for Arabic. The
`errorcode` is identical in every language.

## When the blocked message appears

The account is blocked **on** the fifth failed attempt, and the fifth response
already says so:

```
attempt 1..4  →  Invalid login, please try again
attempt 5     →  Your account has been temporarily blocked ...
attempt 6+    →  Your account has been temporarily blocked ...
correct password while blocked → still blocked
```

Five, thirty minutes and fifteen minutes are the site defaults, not constants:
they come from **Site administration › Security › Site security settings**
(*Account lockout threshold*, *Account lockout window*, *Account lockout duration*).
The message quotes whatever is configured.

The learner also gets an unlock link by email, and a successful password reset
clears the block.

## Migrating from `/login/token.php`

Change the URL and read the response envelope; nothing else differs.

| | `token.php` | this endpoint |
|---|---|---|
| URL | `/login/token.php` | `/local/academy/api.php?function=login` |
| Extra param | — | `token` (Registration API token) |
| Success shape | `{"token":…,"privatetoken":…}` | `{"status":"success","data":{…}}` |
| Failure shape | `{"error":…,"errorcode":…}` | `{"status":"fail","error":…,"errorcode":…}` |
| Blocked account | `Invalid login, please try again` | the real reason |

`token.php` is untouched and still works, so older builds of the app keep
signing in normally — but they keep the old, unhelpful message.

## Example

```bash
curl -X POST "https://SITE/local/academy/api.php?function=login" \
  -d "token=REGISTRATION_TOKEN" \
  -d "username=student@example.com" \
  -d "password=secret" \
  -d "service=moodle_mobile_app"
```

## Implementation note

`\local_academy\login_manager` mirrors `login/token.php` check for check —
web-services enabled, restored account, authentication, maintenance access,
guest, confirmed, password expiry, enrolment plugins, service enabled, token
generation, token-request logging. Nothing is relaxed and nothing is
reimplemented; each step calls the same core function core calls. It exists as a
copy only because `token.php` is core and we do not edit core.

**On every Moodle upgrade, re-read `login/token.php` against it** — if core adds
a guard, this file does not inherit it. Also noted in `CLAUDE.md`.
