# Finishing a Google sign-up — Mobile API

The academy's sign-up form asks for a **phone number**, a **nationality** and the
**terms checkbox**. "Log in with Google" asks for none of them.

| Path into the site | Who collects phone / country / terms |
|---|---|
| `/login/signup.php` (web) | the sign-up form |
| `local_profilefields_signup_user` (app) | the app's own sign-up screen |
| **"Log in with Google" (`auth_oauth2`)** | **nobody** |

**Why this matters.** Moodle's OAuth2 plugin builds the account straight from
Google's claims and never renders a sign-up form, so everything the academy
added to that form is skipped. The account exists and works — it is simply
half-answered.

Nationality is the expensive one. `local_payments\country_detector` resolves
every course price from the user's `country`, and a brand-new account does not
have an *empty* country: `user_create_user()` stamps it with the site's default
country (`user/lib.php:104`). So a learner in Riyadh who signs in with Google is
priced as if they were in Cairo, and nothing in the data looks wrong.

The website closes this with a blocking page (`/local/profilefields/complete.php`)
that no signed-in user can get past. The app needs the same thing on its own
screen — the WebView is deliberately **excluded** from the web redirect, so the
app is never bounced into a browser form.

One new function does the asking. Saving reuses
`local_profilefields_update_profile`, which the app already calls — there is no
second writer, so the two can never drift.

Related: [registration-mobile-api.md](registration-mobile-api.md) ·
[profile-mobile-api.md](profile-mobile-api.md)

---

## Transport

Requires login: call with the user's own `wstoken`.

```
POST /webservice/rest/server.php
  ?wstoken=<USER_TOKEN>
  &wsfunction=<name>
  &moodlewsrestformat=json
```

Responses are plain Moodle REST: the result object on success, or
`{"exception":"...","errorcode":"...","message":"..."}` on failure. Field-level
problems on save are **not** exceptions — they come back in `warnings`.

> **`errorcode: accessexception`** means the function is not listed in the
> external service the token belongs to. Functions registered for
> `MOODLE_OFFICIAL_MOBILE_SERVICE` are added automatically on upgrade; for any
> other service, add it in *Site administration → Server → Web services →
> External services → \<the service\> → Functions*.

**Versions this describes**

| Plugin | Version |
|---|---|
| `local_profilefields` | 2.5.0 (`2026082602`) |
| `profilefield_phone` | 1.1.0 (`2026082601`) |

---

## Where this sits in the sign-in flow

1. **User taps "Continue with Google".** Moodle creates the account. If the
   issuer has *Require email confirmation* on (Moodle's default), a confirmation
   email is sent and **the user is not logged in until they follow that link** —
   `auth/oauth2/classes/auth.php` exits before `complete_user_login()`. So this
   whole flow happens *after* the confirmation email, never before.
2. **Session established.** Everything below runs on the ordinary mobile token.
3. **App calls `local_profilefields_get_completion_status`.**
4. **If `complete` is `false`** → show a blocking completion screen with the
   fields it returned.
5. **App calls `local_profilefields_update_profile`** with all of them.
6. **Carry on** — and invalidate anything cached that depends on the country,
   course prices above all.

**Call it on every login, not just the first.** Google accounts created before
this feature shipped are also unanswered, and they get the same screen once.

---

## 1) `local_profilefields_get_completion_status` — what is still outstanding

Read-only. **No parameters** — it always answers about the token's own user.

**Returns**

| Key | Type | Notes |
|---|---|---|
| `complete` | bool | `true` = nothing outstanding, skip the screen |
| `gateenabled` | bool | Whether the site is enforcing this at all. An admin can switch it off |
| `countryfromphone` | bool | When true, mirror the phone's chosen country into the country picker, as the web form does |
| `fields` | array | The fields still to be answered, **in sign-up order** |
| `consent` | object | `required`, `label`, `documents[]` |

Each entry in `fields` is the **same shape** `local_profilefields_get_profile_form`
returns (step 2 of [profile-mobile-api.md](profile-mobile-api.md)) — it is built
from the same describe() call. If the app already renders the profile form,
reuse that widget.

| Key | Type | Notes |
|---|---|---|
| `name` | string | Send this back to `update_profile` verbatim |
| `shortname` | string | Field shortname |
| `type` | string | `text`, `select`, `checkbox`, `editor`, `datetime`, `phone`, `tags` |
| `label` | string | Visible label — **admin-renameable, never hardcode it** |
| `description` | string | Help text, may be empty |
| `required` | bool | Always `true` here |
| `locked` | bool | Read-only: render disabled, do not send it back |
| `iscustom` | bool | Custom profile field vs. built-in Moodle field |
| `value` | string | What the account currently holds — see the warning below |
| `options` | array | `[{value, label, dialcode}]` for pick-from fields, `[]` otherwise |

### Example response

```json
{
  "complete": false,
  "gateenabled": true,
  "countryfromphone": true,
  "fields": [
    {
      "name": "profile_field_phone",
      "shortname": "phone",
      "type": "phone",
      "label": "Phone",
      "description": "",
      "required": true,
      "locked": false,
      "iscustom": true,
      "value": "",
      "options": [
        {"value": "EG", "label": "Egypt +20 🇪🇬",        "dialcode": "+20"},
        {"value": "SA", "label": "Saudi Arabia +966 🇸🇦", "dialcode": "+966"}
      ]
    },
    {
      "name": "country",
      "shortname": "country",
      "type": "select",
      "label": "Nationality",
      "description": "",
      "required": true,
      "locked": false,
      "iscustom": false,
      "value": "EG",
      "options": [{"value": "EG", "label": "Egypt", "dialcode": "+20"}]
    }
  ],
  "consent": {
    "required": true,
    "label": "I agree to the <a href=\"...\">Terms and Conditions</a>.",
    "documents": [
      {"name": "Terms and Conditions", "url": "https://…", "policyid": 1, "versionid": 4}
    ]
  }
}
```

> ### `value` is **not** proof the user answered
>
> On a fresh Google account `country` arrives already holding the site's default
> country. That value came from `user_create_user()`, not from a human.
>
> **Prefill the picker with `value` so the user confirms rather than retypes —
> but never treat a non-empty `value` as "already answered" and hide the field.**
> Doing that reintroduces exactly the mispricing this endpoint exists to fix.
>
> The server makes the same distinction by writing down that an account has been
> asked (a `local_profilefields_completed` user preference), because the data
> alone cannot tell the two apart.

### Rendering by `type`

| `type` | Widget | Notes |
|---|---|---|
| `phone` | Country picker + number box | Options carry `dialcode` — show that |
| `select` / `menu` | Dropdown | Submit `options[].value` |
| `text` | Single-line input | — |
| `datetime` | Date picker | Submit a unix timestamp |
| `checkbox` | Switch | Submit `"0"` or `"1"` |

### The consent block

`consent.label` is **HTML** with the document links already in it. A client that
cannot render HTML should use `consent.documents[]` instead: open `url` in a
browser view, or fetch the text with `local_profilefields_get_policy_documents`
using `versionid`.

---

## 2) `local_profilefields_update_profile` — save the answers

The same function the profile screen already uses. Full reference in
[profile-mobile-api.md](profile-mobile-api.md); this section covers only what is
new or specific to completion.

### New parameter

| Param | Type | Default | Notes |
|---|---|---|---|
| `consent` | bool | `0` | Records acceptance of the site policies. Send `1` **only** when `consent.required` was `true`. Ignored otherwise, and only ever moves an account from "not agreed" to "agreed" |

### Example request

```json
{
  "fields": [
    {"name": "profile_field_phone", "value": "SA:512345678"},
    {"name": "country",             "value": "SA"}
  ],
  "consent": 1
}
```

### Value formats

| Field type | Send |
|---|---|
| `phone` | `"SA:512345678"` — ISO code, colon, national number **without** the dialling code. `{"country":"SA","number":"512345678"}` as JSON is also accepted |
| `select` / country | The `options[].value` string, e.g. `"SA"` |
| `datetime` | Unix timestamp as a string |
| `tags` / interests | Comma-separated list |

### Completion is recorded automatically

There is no "mark done" call.

The server stamps the account as answered when a successful `update_profile`
covered **every** field the status call listed, plus consent when it was
required. It is deliberately strict: a partial save that merely happens to
validate does **not** count, or an account could slip past questions it was
never asked.

**Send the whole outstanding set in one call.** Otherwise `complete` stays
`false` and the user sees the screen again on the next login.

---

## Phone field changes

Both of these ship in `profilefield_phone` 1.1.0 and affect the app wherever it
shows a phone box — the completion screen, the sign-up screen and the profile
screen alike.

### Number length is now checked per country

The old rule was a blanket 4–15 digits, so an Egyptian number missing a digit
was accepted and only failed later, when an OTP never arrived. About 60
countries now carry their real national-number length; anything not listed keeps
the old 4–15 range, so an unchecked country can never reject a real number.

| Country | Digits | Example |
|---|---|---|
| Egypt | 10 | `1012345678` |
| Saudi Arabia, UAE, Jordan | 9 | `512345678` |
| Kuwait, Qatar, Bahrain, Oman, Tunisia | 8 | `51234567` |
| Lebanon | 7–8 | `3123456` |
| Iraq, Turkey, US, UK | 10 | `2025550123` |
| Not listed | 4–15 | unchanged |

The full table is `profilefield_phone\dialcodes::LENGTHS`.

Two new messages can arrive in `warnings[].message`. Both name the expected
count, so **show them as they are** rather than substituting a generic "invalid
number":

```
The phone number for this country must be 9 digits.
The phone number for this country must be between 7 and 8 digits.
```

### Country option labels were reordered

| | Label |
|---|---|
| Before | `🇪🇬 +20 Egypt` |
| After | `Egypt +20 🇪🇬` |

The name now leads, so keyboard type-ahead on the web jumps to the right
country. (The old label opened with the flag emoji, which is built from two
regional-indicator code points standing for the ISO code — so pressing `g` never
found Germany.)

> **If the app parses the label to extract the dialling code, it will break.**
> Use the `dialcode` key on each option. It has always been there and is the only
> supported way to read the code.

### Phone country must match the user's location

If the admin has *match IP to phone country* on, the rule now also applies while
a registration is being completed. Previously a Google account was the way
around it: register a Saudi number from an Egyptian address and nothing stopped
you, though the web sign-up form would have refused.

It fires **only** while the phone is one of the outstanding completion fields —
an ordinary profile edit later is untouched, so a user travelling abroad can
still edit their details.

```json
{"item": "profile_field_phone", "itemid": 0, "warningcode": "fielderror",
 "message": "<the site's IP-mismatch message>"}
```

---

## Not the app's problem

| Change in this release | Why it doesn't reach the app |
|---|---|
| Password strength meter on sign-up | Web only. To mirror it natively, the site's policy already comes down in `local_profilefields_get_signup_form` → `passwordpolicy` |
| Show/hide password toggle | Web only |
| The web completion page redirect | The in-app WebView is explicitly excluded (`local_profilefields\hook_callbacks::is_app_request`), so a learner reading an activity inside the app is never bounced to a web form |
| `local_profilefields_signup_user` | Unchanged. Accounts created through it answer everything up front and are never gated |

---

## Migration checklist

- [ ] `get_completion_status` is called after **every** successful login, including silent token refresh
- [ ] The completion screen blocks: no skip, no back gesture out, no way to reach the rest of the app
- [ ] Fields render in the order returned; labels come from `label`, never hardcoded
- [ ] Country picker is prefilled from `value` but still **shown** for confirmation
- [ ] All outstanding fields go up in a **single** `update_profile` call
- [ ] `consent: 1` is sent whenever `consent.required` was `true`
- [ ] Warnings are matched to inputs by `item` and shown verbatim
- [ ] Dialling codes read from `options[].dialcode`, not parsed out of `label`
- [ ] Cached course prices are invalidated after completion — the country may have changed
- [ ] Tested with an **existing** Google account, not only a freshly created one
- [ ] Tested the length errors (Egyptian number with 9 digits) and, if the rule is on, the IP-mismatch error

---

## Notes for the server side

- The whole gate can be switched off from *Site administration → Users →
  Sign-up and profile field layout* → **Hold incomplete accounts**. When it is
  off, `gateenabled` comes back `false` and `complete` is `true` for everyone.
- The requirement list is derived from the same configuration the sign-up form
  uses (`local_profilefields\manager`): field order, which fields are shown, and
  which are required. Change it on that page and both the website and this
  endpoint follow — there is no separate list to maintain.
- The rules described here are enforced in
  `local/profilefields/classes/completion.php` and in the two consumers
  (`complete.php` for the web, `external/update_profile.php` for the app). The
  client cannot loosen them.
- Site administrators are never gated, so an admin repairing a misconfiguration
  is not locked out by it.
