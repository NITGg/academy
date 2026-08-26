# Profile (view & edit) — Mobile API

The app's profile screens map onto two website pages:

| Website page | What the app used before | New |
|---|---|---|
| `/user/profile.php` — view | `core_user_get_users_by_field` | `local_profilefields_get_profile` |
| `/user/edit.php` — edit | *(nothing — the page was opened in a WebView)* | `local_profilefields_get_profile_form` + `local_profilefields_update_profile` |

**Why they changed.**

`core_user_get_users_by_field` answers "tell me about this user" for a
participants list: a fixed handful of columns, custom profile fields filtered by
what the *caller* is allowed to see, and none of the sections the profile page
actually shows.

For editing there was nothing usable at all. Moodle's only write function is
`core_user_update_users`, and it requires the **`moodle/user:update`**
capability — a site-management permission that an ordinary student does not
have. A student editing their own profile is simply refused. That is why the app
has been opening `/user/edit.php` in a WebView.

The three functions below are the same profile as the website — same fields,
same order, same labels, same locks, same validation, same error messages, and
the same email-change confirmation.

---

## Transport

All three require login: call them with the user's own `wstoken`.

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
> other service, fix it on the server with:
>
> ```
> php public/local/profilefields/cli/ws_add_signup_functions.php --token=THE_TOKEN --add
> ```

---

## 1) `local_profilefields_get_profile` — the view screen

| Param | Type | Notes |
|---|---|---|
| `userid` | int | Optional. `0` (default) = the calling user |

**Returns**

| Key | Type | Notes |
|---|---|---|
| `id`, `fullname`, `firstname`, `lastname` | | |
| `username` | string | Only filled in for the caller's **own** profile; `""` for anyone else |
| `email` | string | `""` when this viewer is not allowed to see it (the owner's *email display* setting) |
| `city`, `country`, `countryname` | string | `country` is the ISO code, `countryname` is already localised |
| `timezone`, `lang` | string | |
| `description`, `descriptionformat` | string / int | "About me", already formatted and with file URLs resolved — render as HTML |
| `interests` | string | Comma separated |
| `profileimageurl` / `profileimageurlsmall` | url | 200px / 100px |
| `firstaccess`, `lastaccess` | int | Unix timestamps, `0` when never |
| `canedit` | bool | **Show the Edit button only when this is `true`** |
| `editurl` | url | The web edit page, if the app would rather open a browser |
| `customfields` | array | The custom profile fields this viewer may see |
| `categories` | array | The profile page itself, section by section |
| `warnings` | array | Always empty here |

Each entry of `customfields`:

| Key | Notes |
|---|---|
| `shortname` | Field shortname |
| `name` | The label the site shows, already localised |
| `datatype` | `text`, `textarea`, `menu`, `checkbox`, `datetime`, `phone`, … |
| `value` | The **stored** value — this is exactly what `update_profile` takes back |
| `displayvalue` | The value as the profile page prints it (a phone reads `+20 1012345678`, a datetime reads a date) |
| `categoryname` | The category the field belongs to |

`categories` is the real profile page tree — the same one
`/user/profile.php` renders, so anything a plugin contributes to the profile
appears here too. Each category is `{name, title, nodes[]}` and each node is
`{name, title, content, url, classes}`. Use `title` for the row text, `content`
for anything underneath it, and `url` to navigate (`""` = not a link).

Show `customfields` for the user's own details, and `categories` for the
sections below them (*Course details*, *Miscellaneous*, *Reports*, *Login
activity*). Both are already filtered for this viewer — nothing needs hiding
client-side.

---

## 2) `local_profilefields_get_profile_form` — the edit screen

Call this every time the edit screen opens. Everything it returns is live
configuration — do not hard-code the field list.

| Param | Type | Notes |
|---|---|---|
| `userid` | int | Optional. `0` (default) = the calling user |

Fails with an exception when the caller may not edit that profile — the same
check `canedit` reports in step 1, so gate on `canedit` first.

**Returns**

| Key | Type | Notes |
|---|---|---|
| `userid` | int | |
| `fields` | array | The fields to show, **in the order to show them** |
| `sections` | array | `[{name, label}]` — the form's headings, in order |
| `emailchangepending` | string | An address the user has been asked to confirm by email. While this is set, the account still carries the **old** address — show a "check your inbox" notice |
| `profileimageurl` / `profileimageurlsmall` | url | The current picture |
| `warnings` | array | Always empty here |

Each entry of `fields`:

| Key | Notes |
|---|---|
| `name` | **Submit the answer under this name.** Custom fields keep their `profile_field_` prefix; the "About me" box is called `description` |
| `shortname` | The name without the `profile_field_` prefix |
| `type` | `text`, `email`, `select`, `checkbox`, `editor`, `tags`, `datetime`, `menu`, `phone`, … |
| `label` | Already localised and already carrying any admin rename — show it as-is |
| `description` | Help text, when the field has any |
| `required` | Whether the user must fill it in |
| `locked` | **Show read-only.** Either the auth plugin forbids changing the field, or it is a `file` field whose value lives in a file area this API cannot set. A value sent for a locked field is ignored, exactly as the web form ignores it |
| `iscustom` | `true` for a custom profile field |
| `section` / `sectionlabel` | Which heading the field sits under |
| `value` | What the field holds now — pre-fill the box with it |
| `format` | For `description`, the format its value is in (`1` = HTML, `2` = plain). `0` for every other field |
| `options` | `[{value, label, dialcode}]` for anything the user picks from. Empty for free-text fields |

**Value shapes**, on the way out *and* on the way back in:

| `type` | `value` |
|---|---|
| `phone` | `"EG:1012345678"` — `options` is the country list, `dialcode` its `+…` prefix |
| `datetime` | A unix timestamp |
| `checkbox` | `"0"` / `"1"` |
| `tags` (interests) | A comma separated list |
| everything else | The plain string |

`locked` is the one thing a client cannot work out for itself, and this site does
use it: *Site administration → Users → Sign-up and profile field layout* writes
the auth-plugin field locks that produce it. A locked field must be rendered
disabled, not hidden — the user should see their name and still understand they
cannot change it here.

### Example response (trimmed)

```json
{
  "userid": 20,
  "emailchangepending": "",
  "profileimageurl": "https://…/pluginfile.php/…/f1",
  "sections": [
    {"name": "moodle", "label": "General"},
    {"name": "moodle_optional", "label": "Optional"}
  ],
  "fields": [
    {"name": "firstname", "shortname": "firstname", "type": "text",
     "label": "First name", "description": "", "required": true, "locked": false,
     "iscustom": false, "section": "moodle", "sectionlabel": "General",
     "value": "Ahmed", "format": 0, "options": []},
    {"name": "email", "shortname": "email", "type": "email",
     "label": "Email address", "required": true, "locked": true,
     "iscustom": false, "section": "moodle", "sectionlabel": "General",
     "value": "ahmed@example.com", "format": 0, "options": []},
    {"name": "country", "shortname": "country", "type": "select",
     "label": "Select a country", "required": false, "locked": false,
     "iscustom": false, "section": "moodle", "sectionlabel": "General",
     "value": "EG", "format": 0,
     "options": [{"value": "EG", "label": "Egypt", "dialcode": "+20"}]},
    {"name": "profile_field_phone", "shortname": "phone", "type": "phone",
     "label": "رقم الهاتف", "required": true, "locked": false,
     "iscustom": true, "section": "category_1", "sectionlabel": "Other fields",
     "value": "EG:1012345678", "format": 0,
     "options": [{"value": "EG", "label": "Egypt", "dialcode": "+20"}]}
  ],
  "warnings": []
}
```

---

## 3) `local_profilefields_update_profile` — save

| Param | Type | Notes |
|---|---|---|
| `fields` | array | `[{name, value}]` — `name` is the field's `name` from step 2 |
| `userid` | int | Optional. `0` (default) = the calling user |
| `descriptionformat` | int | Optional. The format the `description` value is in: `1` = HTML (default), `2` = plain text |
| `consent` | bool | Optional, `0` (default). Records acceptance of the site policies. Only needed when finishing a Google sign-up — see [oauth-completion-mobile-api.md](oauth-completion-mobile-api.md). Ignored otherwise |

**A submission is a partial update.** Only the fields you send are changed, so
the app can save one screen of a multi-step profile without blanking the rest.
Sending a field with an empty value *does* clear it (and fails validation if the
field is required); leaving it out means "don't touch".

**Validation follows what you send.** On the web every box is posted at once, so
Moodle can refuse the whole form over a required field the user never scrolled
to. Here, a field you leave out is neither changed nor complained about — a
screen that saves only the address is not blocked by a phone number it does not
show. Post the whole `fields` list and you get exactly the web page's
all-or-nothing behaviour.

A name that is not on the form comes back as an exception
(`invalid_parameter_exception`) — that is a client bug, not a user error. A
`locked` field is accepted and silently ignored, matching the web form.

**Two things this call also does when it is finishing a Google sign-up.** If the
account still owes the sign-up form answers, a save that covers *all* of them
marks the registration complete, and the phone's "country must match your
location" rule is applied. Neither affects an ordinary profile edit. Details in
[oauth-completion-mobile-api.md](oauth-completion-mobile-api.md).

```json
{
  "fields": [
    {"name": "firstname", "value": "Ahmed"},
    {"name": "city", "value": "Cairo"},
    {"name": "country", "value": "EG"},
    {"name": "profile_field_phone", "value": "EG:1012345678"},
    {"name": "description", "value": "<p>Hello.</p>"}
  ],
  "descriptionformat": 1
}
```

**Returns**

```json
{ "success": true, "emailchangepending": "", "warnings": [] }
```

**Field errors** come back with `success: false`, nothing written, and one
warning per field:

```json
{
  "success": false,
  "emailchangepending": "",
  "warnings": [
    {"item": "email", "itemid": 0, "warningcode": "fielderror",
     "message": "This email address is already registered…"},
    {"item": "profile_field_phone", "itemid": 0, "warningcode": "fielderror",
     "message": "Required"}
  ]
}
```

`item` is the field's `name` from step 2 — attach the `message` to that box. The
messages are already localised for the request language and are plain text (no
HTML to strip).

### Changing the email address

When the site has *email change confirmation* on (it does), a new address is
**not** applied straight away:

- `success` is `true`, but `emailchangepending` holds the new address;
- the account still carries the **old** one until the user clicks the link
  Moodle has just emailed to the new address;
- until then, `get_profile_form` keeps reporting it in `emailchangepending`.

Show a "we've sent a confirmation link to *new address*" notice, and keep
displaying the old address as the current one.

### The profile picture

Not part of this function. Use core's existing pair, which already handles the
file upload:

1. `core_files_upload` (or the app's usual upload path) → a `draftitemid`
2. `core_user_update_picture` with that `draftitemid`, or with `delete: 1` to
   remove the picture

`get_profile_form` and `get_profile` both return the current
`profileimageurl` so the screen can show it.

---

## Migration checklist

1. Profile screen: replace `core_user_get_users_by_field` with
   `local_profilefields_get_profile`. Render `customfields` for the details and
   `categories` for the sections below.
2. Show the **Edit** button only when `canedit` is `true`.
3. Edit screen: drop the WebView. Call
   `local_profilefields_get_profile_form` and build the screen from `fields` +
   `sections` instead of a hard-coded list.
4. Render `locked: true` fields disabled, not hidden.
5. Render `type: "phone"` as country + number and send back `"EG:1012345678"`.
6. Save with `local_profilefields_update_profile`, sending only the fields the
   user touched, and map `warnings[].item` back onto the boxes.
7. Handle `emailchangepending` on the way back: the address is not live yet.
8. Keep using `core_user_update_picture` for the photo.

## Notes for the server side

- All three functions live in `public/local/profilefields/` —
  `classes/external/` for the web-service layer, `classes/profile_api.php` for
  the flow itself. No Moodle core file is touched.
- `get_profile_form` builds the **real** `user_edit_form` and reads it back,
  finalised — so the auth-plugin locks, the custom fields, and anything an admin
  changes on *Sign-up and profile field layout* reach the app on the next call,
  with nothing to update here.
- `get_profile` serialises the **real** `core_user\output\myprofile` tree, which
  is the profile page itself.
- `update_profile` runs `user_edit_form::validation()`'s checks and then the
  exact save sequence from `/user/edit.php`, including
  `\core\event\user_updated`.
- The sign-up side is documented separately in
  [registration-mobile-api.md](registration-mobile-api.md); the two share
  `signup::CUSTOM_PREFIX` and the same phone value format, so a value round-trips
  between them unchanged.
