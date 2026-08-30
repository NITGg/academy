# Registration (sign-up) — Mobile API

The app used to register users with Moodle's stock pair:

| Old (stock Moodle) | New |
|---|---|
| `auth_email_get_signup_settings` | `local_profilefields_get_signup_form` |
| `auth_email_signup_user` | `local_profilefields_signup_user` |

**Why they changed.** The academy's sign-up page is no longer stock Moodle's.
The username box is gone (the site builds the username from the email address),
"Email (again)" is off, City and Country are filled in by the server, the phone
field decides the country, an **agreement checkbox is required**, and an admin
can rename, reorder, hide or require any field from
*Site administration → Users → Sign-up and profile field layout*.

The two core functions know none of that: they demand a username nobody is asked
for any more, never see the agreement checkbox (Moodle only runs the plugin
sign-up callbacks on the web form), and store an empty country. The two
functions below are the same sign-up as the website — same fields, same order,
same validation, same error messages.

---

## Transport

All four functions are **pre-login**. Two ways to call them, pick one:

**With the Registration API token** (the same token used by the forgot-password
endpoints — see [forgot-password-mobile-api.md](forgot-password-mobile-api.md)):

```
POST /webservice/rest/server.php
  ?wstoken=<REGISTRATION_TOKEN>
  &wsfunction=<name>
  &moodlewsrestformat=json
```

**With no token at all:**

```
POST /lib/ajax/service-nologin.php?info=<name>
[{"index":0,"methodname":"<name>","args":{ ... }}]
```

Responses are plain Moodle REST: the result object on success, or
`{"exception":"...","errorcode":"...","message":"..."}` on failure. Field-level
problems are **not** exceptions — they come back in `warnings` (see below).

> **`errorcode: accessexception` on the REST path** means the function is not
> listed in the external service the token belongs to — not a bad token and not
> a missing function (an uninstalled function returns `invalidrecord`). Fix it
> on the server with:
>
> ```
> php public/local/profilefields/cli/ws_add_signup_functions.php --token=THE_TOKEN --add
> ```
>
> or by hand in *Site administration → Server → Web services → External
> services → \<the service\> → Functions → Add functions*. The no-token
> transport above is not affected by this.

---

## 1) `local_profilefields_get_signup_form`

Call this first, every time the sign-up screen opens. Everything it returns is
live configuration — do not hard-code the field list.

**Parameters:** none.

**Returns**

| Key | Type | Notes |
|---|---|---|
| `fields` | array | The fields to show, **in the order to show them** |
| `usernamefromemail` | bool | `true` → never ask for a username; the server builds it |
| `usernamesource` | string | `email` (whole address) or `localpart` |
| `countryfromphone` | bool | `true` → the country comes from the phone field |
| `ipmatchphone` | bool | `true` → sign-up is refused when the caller's IP country differs from the phone country |
| `consent` | object | `{required, label, documents[]}` — the agreement checkbox |
| `passwordpolicy` | string (HTML) | Show under the password box |
| `passwordrules` | object | The same policy as numbers, to check the box while it is typed — see [Validating before submit](#validating-before-submit) |
| `defaultcity` / `defaultcountry` | string | What the server stores when the form does not ask |
| `extendedusernamechars` | bool | Site setting, informational |
| `recaptchapublickey` | string | Present only when a captcha is required |
| `warnings` | array | Always empty here |

Each entry of `fields`:

| Key | Notes |
|---|---|
| `name` | **Submit the answer under this name.** A plain name (`firstname`) is a top-level parameter; a `profile_field_*` name goes in `customprofilefields`; `consent` is the agreement checkbox |
| `shortname` | The name without the `profile_field_` prefix |
| `type` | `text`, `email`, `password`, `select`, `menu`, `checkbox`, `textarea`, `datetime`, `phone`, `consent`, … |
| `label` | Already localised and already carrying any admin rename — show it as-is |
| `description` | Help text, when the field has any |
| `required` | Whether the user must fill it in |
| `iscustom` | `true` for a custom profile field |
| `defaultvalue` | Pre-fill with this when non-empty |
| `options` | `[{value, label, dialcode}]` for anything the user picks from. Empty for free-text fields |
| `minlength` / `maxlength` | Character limits. **Absent when the field has no such limit** — see [Validating before submit](#validating-before-submit) |
| `pattern` / `patternmessage` | A shape rule and the sentence to show when it fails. Both absent together |

`type: "phone"` is a **composite** field: `options` is the country list, where
`value` is the ISO code, `label` the country name and `dialcode` its `+…`
prefix. Render a country picker plus a number box.

> **Read the `+…` prefix from `dialcode`, never by slicing `label`.** As of
> `profilefield_phone` 1.1.0 the label leads with the country name
> (`Egypt +20 🇪🇬`, previously `🇪🇬 +20 Egypt`), so any parser that assumed the
> old order will break.
>
> The same release checks the **number length per country** — Egypt 10 digits,
> Saudi Arabia 9, Kuwait 8, and so on; countries not in the table keep the old
> 4–15 range. A wrong length now comes back as a field error naming the expected
> count ("The phone number for this country must be 10 digits."), so show the
> message as it is. See
> [oauth-completion-mobile-api.md](oauth-completion-mobile-api.md#phone-field-changes).

### Example response (today's configuration)

```json
{
  "usernamefromemail": true,
  "usernamesource": "email",
  "countryfromphone": true,
  "ipmatchphone": false,
  "defaultcity": "",
  "defaultcountry": "EG",
  "passwordpolicy": "The password must have at least 8 characters…",
  "passwordrules": {"minlength": 8, "mindigits": 1, "minlower": 1, "minupper": 1, "minnonalpha": 0},
  "consent": {
    "required": true,
    "label": "I agree to the <a href=\"…\">Terms of use</a> and the <a href=\"…\">Privacy policy</a>.",
    "documents": [
      {"name": "Terms of use", "url": "https://…/admin/tool/policy/view.php?policyid=1&versionid=1", "policyid": 1, "versionid": 1}
    ]
  },
  "fields": [
    {"name": "firstname", "type": "text",     "label": "First name",   "required": true,  "iscustom": false, "options": [],
     "minlength": 2, "maxlength": 50,
     "pattern": "^[\\p{L}\\p{M} '’\\-]+$",
     "patternmessage": "Only letters, spaces, hyphens and apostrophes are allowed."},
    {"name": "lastname",  "type": "text",     "label": "Last name",    "required": true,  "iscustom": false, "options": [],
     "minlength": 2, "maxlength": 50,
     "pattern": "^[\\p{L}\\p{M} '’\\-]+$",
     "patternmessage": "Only letters, spaces, hyphens and apostrophes are allowed."},
    {"name": "email",     "type": "email",    "label": "Email address","required": true,  "iscustom": false, "options": [],
     "maxlength": 100,
     "pattern": "^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$",
     "patternmessage": "Please enter a valid email address, for example name@example.com."},
    {"name": "password",  "type": "password", "label": "Password",     "required": true,  "iscustom": false, "options": [],
     "maxlength": 128},
    {"name": "profile_field_phone", "type": "phone", "label": "Phone", "required": true, "iscustom": true,
     "pattern": "^\\+?[0-9 ()\\-]+$", "patternmessage": "Please enter digits only.",
     "options": [{"value": "EG", "label": "Egypt +20 🇪🇬", "dialcode": "+20"}, …]},
    {"name": "profile_field_nationality", "type": "menu", "label": "Nationality", "required": false, "iscustom": true,
     "options": [{"value": "Egyptian", "label": "Egyptian", "dialcode": ""}, …]},
    {"name": "consent",   "type": "consent",  "label": "I agree to the …", "required": true, "iscustom": false, "options": []}
  ],
  "warnings": []
}
```

Note what is **not** there: no `username`, no `email2`, no `city`, no `country`.
The site fills those in. Do not send them and do not show boxes for them.

### Validating before submit

`passwordrules` and the four per-field keys exist so the app can validate while
the user types — a one-letter first name showing "between 2 and 50 characters"
under the box, Create Account disabled until every rule passes — without a
second copy of this site's rules living in Dart.

They are **descriptions of what the server will do**, read from the same
configuration `local_profilefields_signup_user` enforces: a limit an
administrator changes reaches the app on the next call. They are not a
replacement for handling the errors that function returns — uniqueness, the
per-country phone length, reCAPTCHA and the location check can only be decided
by the server.

**Per field.** A key is **absent** when the field has no such rule; it is never
sent as `0`, `null` or `""`. So test for presence, not for truthiness.

| Key | Meaning |
|---|---|
| `minlength` | Fewest characters, counted as characters, not bytes (`value.characters.length`, or `runes` — a name in Arabic must not be measured in UTF-8 bytes) |
| `maxlength` | Most characters. Use it as the input's own `maxLength` as well |
| `pattern` | An **anchored** regular expression the whole value must match |
| `patternmessage` | The sentence to show when `pattern` fails, already translated per `moodlewssettinglang`. Always sent with `pattern`, never without it |

There is no message for the length rules: compose those yourself from the
numbers, so the wording matches the rest of your screen.

**The pattern is written to be used unchanged.** It uses no lookahead,
lookbehind, backreference, named group or inline flag — only what PHP and Dart
both accept. Compile it with Unicode mode on, or `\p{L}` is a syntax error:

```dart
final ok = RegExp(field.pattern!, unicode: true).hasMatch(value);
```

Trim leading and trailing spaces before testing, as the server does.

**Passwords.** `passwordrules` gives the policy as five numbers; keep printing
`passwordpolicy` under the box as the human sentence. A rule of `0` does not
apply — with `minnonalpha: 0` a password of letters and digits is fine. Count
each class over the whole string:

| Key | Passes when |
|---|---|
| `minlength` | the password has at least this many characters |
| `mindigits` | it contains at least this many digits |
| `minlower` / `minupper` | at least this many lower- / upper-case letters |
| `minnonalpha` | at least this many characters that are none of the above |

The password box's own `maxlength` (128) arrives as a field key like any other.

**One gap to know about.** The phone number's length is checked **per country**
against the dialling code picked — Egypt 10 digits, Saudi Arabia 9, Kuwait 8 —
so it cannot be sent as a single `minlength`/`maxlength` and is not sent at all.
The `pattern` on that field only says what it may be made of. A wrong length is
still returned by `signup_user` as a field error naming the expected count; show
it as it is.

---

## 2) `local_profilefields_signup_user`

Creates the account and sends the confirmation email — the same account the
website's form would have created from the same answers.

| Param | Type | Required | Notes |
|---|---|---|---|
| `email` | string | ✔ | Also becomes the username |
| `password` | string | ✔ | Must satisfy `passwordpolicy` |
| `firstname` | string | ✔ | |
| `lastname` | string | ✔ | |
| `consent` | bool | when `consent.required` | `1` = the user ticked the box |
| `customprofilefields` | array | per field | `[{type, name, value}]` — `name` is the field's `name` from step 1 |
| `email2` | string | — | Only when step 1 lists an `email2` field (off by default); it is then checked against `email` |
| `city` | string | — | Omit; the site default is used |
| `country` | string | — | Omit; it follows the phone field or the site default |
| `username` | string | — | Ignored while `usernamefromemail` is `true` |
| `recaptcharesponse` | string | when a captcha is set | |
| `redirect` | string | — | Local URL to land on after confirmation |

**Phone values.** Send either an encoded JSON object or the stored string form:

```json
{"type": "phone", "name": "profile_field_phone", "value": "{\"country\":\"EG\",\"number\":\"1012345678\"}"}
{"type": "phone", "name": "profile_field_phone", "value": "EG:1012345678"}
```

The number is normalised server-side: spaces, dashes, a leading `0` and a typed
dialling code are all stripped, so `+20 101 234 5678`, `0101 234 5678` and
`1012345678` with country `EG` all store the same value.

**Returns**

```json
{ "success": true, "username": "someone@example.com", "warnings": [] }
```

Keep `username` — it is what the user logs in with (`/login/token.php`), and it
is **not** always the email: when the address is already taken the site appends
a number.

**Field errors** come back with `success: false` and one warning per field:

```json
{
  "success": false,
  "username": "",
  "warnings": [
    {"item": "email",    "itemid": 0, "warningcode": "fielderror",
     "message": "This email address is already registered…"},
    {"item": "consent",  "itemid": 0, "warningcode": "fielderror",
     "message": "You must agree to the policies before you can create an account."}
  ]
}
```

`item` is the field's `name` from step 1 (`consent` for the checkbox, or
`recaptcharesponse`) — attach the `message` to that box. The messages are
already localised for the request language, and plain text (no HTML tags to
strip) — show them as they are. A multi-rule password message arrives as one
string with a line break per rule.

**After a successful sign-up** the account exists but is **unconfirmed**: Moodle
has emailed a confirmation link. Tell the user to check their inbox. A login
attempt before confirmation fails with `errorcode: "usernotconfirmed"`. Show the
confirmation step next, with a Resend button — section 3.

---

## 3) `local_profilefields_resend_confirmation` — the Resend button

What the confirmation screen's **Resend** button calls. Also pre-login (the
account being confirmed cannot log in yet — that is the point).

**Parameters**

| Param | Type | Notes |
|---|---|---|
| `email` | string, required | The address entered at sign-up. Trimmed and lower-cased before matching, exactly as `signup_user` stored it |

**Returns — one shape, always**

```json
{"success": true,  "message": null, "errorcode": null, "retryafter": 60}
{"success": false, "message": "Please wait 47 seconds before requesting another email.",
 "errorcode": "toomanyrequests", "retryafter": 47}
```

| Key | Notes |
|---|---|
| `retryafter` | Seconds until the button may be tapped again. **Always present — drive your countdown from this and nothing else.** 60 after an accepted request; the time left in the window after a refusal |
| `success` | Whether the request was accepted |
| `errorcode` | `toomanyrequests`, or `null` on success. The only code this function has |
| `message` | The refusal, already translated per `moodlewssettinglang`. `null` on success |

Start the countdown at `retryafter` on **every** reply, success or refusal.
Do not hard-code 60 — the wait is an admin setting, and after a refusal the
number is not 60 at all.

**When you will see each reply**

| Situation | Reply |
|---|---|
| A new link was sent | `success: true`, `retryafter: 60` |
| Under 60 s since the last send — **including the sign-up email itself** | `toomanyrequests`, `retryafter` = seconds left |
| 6th request within one rolling hour | `toomanyrequests`, `retryafter` ≈ what is left of the hour, message: "Too many requests. Please try again in one hour." / «تم إرسال عدد كبير من الطلبات. يرجى المحاولة مرة أخرى بعد ساعة.» |

Note the second row: the sign-up email counts as the first send, so a Resend
tapped straight after Create Account is refused. That is why the app's countdown
should start at 60 the moment the confirmation screen opens, not when the button
is first tapped.

> **This function will not tell you whether an account exists.** An address
> nobody registered, and one that is already confirmed, are answered exactly like
> a successful send — same keys, same values, same rate limiting on repeat calls.
> There is no reply that means "no such account", and none is coming: it would be
> an account-enumeration endpoint open to the whole internet. Do not try to infer
> registration status from this call, and do not show the user anything other
> than "we have sent it, check your inbox".

**The email language** follows `moodlewssettinglang`, so send the same header you
send everywhere else and the learner gets the confirmation mail in the language
they are using the app in.

**What happens to the old link.** Each send invalidates every link issued before
it, so only the newest email works. The three outcomes on the confirmation page:

| The link | The learner sees |
|---|---|
| Newest, under 24 h old | Account confirmed and logged in |
| Superseded by a resend, or over 24 h old | "This confirmation link is no longer valid. Please request a new one." — with a Resend button on the page |
| Newest, but the account is already confirmed | "Your account is already confirmed. Please log in." — not an error |

---

## 4) `local_profilefields_get_policy_documents` — the Terms text

For showing the terms / privacy policy **without a WebView**. Returns the same
documents `consent.documents` lists, with their text.

| Param | Type | Notes |
|---|---|---|
| `versionid` | int | Optional. `0` (default) returns every sign-up document; pass a `versionid` from `consent.documents` for just one |

```json
{
  "documents": [
    {
      "policyid": 1,
      "versionid": 3,
      "name": "شروط الاستخدام",
      "url": "https://…/admin/tool/policy/view.php?policyid=1&versionid=3",
      "content": "<h3>…</h3><p>…</p>",
      "contentformat": 1
    }
  ],
  "warnings": []
}
```

`content` is HTML written by the admin in Moodle's Policies tool — render it in
whatever HTML widget the app uses, with `name` as the screen title. Pre-login,
like the other two.

If the document embeds images, their URLs point at `pluginfile.php`; when
calling over REST with a token, append `&token=<the same token>` to load them.

Prefer this over the `url`. The `url` is still there for a browser view — add
`&nitembed=1` to it and theme_nit drops the header, footer and menus, which is
what the in-app WebView wants.

---

## Migration checklist

1. Replace `auth_email_get_signup_settings` → `local_profilefields_get_signup_form`,
   and build the screen from `fields` instead of a hard-coded list.
2. Replace `auth_email_signup_user` → `local_profilefields_signup_user`.
3. Delete the username box and whatever the app was inventing for `username`;
   stop sending `email2`, `city` and `country`.
4. Add the agreement checkbox when `consent.required` is `true`, linking the
   documents in `consent.documents`, and send `consent: 1`.
5. Render `type: "phone"` as country + number, and send the composite value.
6. On `success: true`, store the returned `username` for the login call.

## Notes for the server side

- Both functions live in `public/local/profilefields/` — `classes/external/` for
  the web-service layer, `classes/signup_api.php` for the flow itself. No Moodle
  core file is touched.
- `get_signup_form` builds the **real** `login_signup_form` and reads it back,
  so anything an admin changes on the management page reaches the app on the
  next call, with nothing to update here.
- The older `local_profilefields_get_profile_fields` still works and is
  unchanged, but it only lists custom fields and needs a token — prefer
  `get_signup_form`.
