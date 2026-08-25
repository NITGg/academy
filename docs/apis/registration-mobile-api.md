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

Both functions are **pre-login**. Two ways to call them, pick one:

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

`type: "phone"` is a **composite** field: `options` is the country list, where
`value` is the ISO code, `label` the country name and `dialcode` its `+…`
prefix. Render a country picker plus a number box.

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
  "consent": {
    "required": true,
    "label": "I agree to the <a href=\"…\">Terms of use</a> and the <a href=\"…\">Privacy policy</a>.",
    "documents": [
      {"name": "Terms of use", "url": "https://…/admin/tool/policy/view.php?policyid=1&versionid=1", "policyid": 1, "versionid": 1}
    ]
  },
  "fields": [
    {"name": "firstname", "type": "text",     "label": "First name",   "required": true,  "iscustom": false, "options": []},
    {"name": "lastname",  "type": "text",     "label": "Last name",    "required": true,  "iscustom": false, "options": []},
    {"name": "email",     "type": "email",    "label": "Email address","required": true,  "iscustom": false, "options": []},
    {"name": "password",  "type": "password", "label": "Password",     "required": true,  "iscustom": false, "options": []},
    {"name": "profile_field_phone", "type": "phone", "label": "Phone", "required": true, "iscustom": true,
     "options": [{"value": "EG", "label": "🇪🇬 +20 Egypt", "dialcode": "+20"}, …]},
    {"name": "profile_field_nationality", "type": "menu", "label": "Nationality", "required": false, "iscustom": true,
     "options": [{"value": "Egyptian", "label": "Egyptian", "dialcode": ""}, …]},
    {"name": "consent",   "type": "consent",  "label": "I agree to the …", "required": true, "iscustom": false, "options": []}
  ],
  "warnings": []
}
```

Note what is **not** there: no `username`, no `email2`, no `city`, no `country`.
The site fills those in. Do not send them and do not show boxes for them.

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
attempt before confirmation fails with `errorcode: "usernotconfirmed"`.

---

## 3) `local_profilefields_get_policy_documents` — the Terms text

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
