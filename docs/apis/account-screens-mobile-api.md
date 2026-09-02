# Account screens — Mobile API

The five web pages behind the account area, as web-service functions, so the app
can draw them natively instead of opening a WebView.

| Website page | Function(s) |
|---|---|
| `/local/profilefields/account.php` | `local_profilefields_get_account_profile` + `local_profilefields_update_profile` |
| `/local/profilefields/account.php?section=security` | `local_profilefields_get_security` + `local_academy_change_password` |
| `/mod/customcert/my_certificates.php` | `local_academy_get_my_certificates` + `local_academy_get_certificate_pdf` |
| `/local/payments/history.php` | `local_payments_get_transactions` |
| `/local/profilefields/deleteaccount.php` | `local_profilefields_get_delete_account_info` + `local_profilefields_delete_account` |

All five pages share one shell — a list down the left, one pane to the right of
it. `local_profilefields_get_account_menu` returns that list.

Every function acts on the **token's own user**. None of them takes a user id:
the web pages do not either, by design. An administrator acting on somebody else
does it from Moodle's user management, where it is audited as an administrative
act.

---

## Transport

Same as every other guide here:

```
GET|POST {WWWROOT}/webservice/rest/server.php
  ?wstoken=TOKEN
  &wsfunction=FUNCTION_NAME
  &moodlewsrestformat=json
  &<params>
```

- `WWWROOT` staging/prod: `https://academy2026.nitg-eg.com/moodle-new`
- Read functions may be `GET`; write functions (`update_profile`,
  `request_email_change`, `change_password`, `delete_account`) should be `POST`.
- Pass `lang=ar` (or `alang=ar`) where a function accepts it to get Arabic
  labels; otherwise the token user's own language is used.

**Two habits worth keeping throughout.** Branch on machine values (`status`,
`key`, `name`, `type`), never on the localised label beside them — the labels are
translated and get reworded. And field problems come back in `warnings`, not as
exceptions: `warnings[].item` is the field name, `warnings[].message` is the
localised sentence to attach to that box. An exception means something the user
could not have typed their way out of.

---

## 0) `local_profilefields_get_account_menu` — the shell

```
wsfunction=local_profilefields_get_account_menu&active=profile
```

`active` is one of `profile`, `security`, `mylearning`, `certificates`,
`invoices`, `delete`.

```json
{ "items": [
  {"key":"profile",      "label":"Profile",      "url":"…", "active":true,  "danger":false},
  {"key":"security",     "label":"Security",     "url":"…", "active":false, "danger":false},
  {"key":"mylearning",   "label":"My learning",  "url":"…", "active":false, "danger":false},
  {"key":"certificates", "label":"Certificates", "url":"…", "active":false, "danger":false},
  {"key":"invoices",     "label":"Invoices",     "url":"…", "active":false, "danger":false},
  {"key":"delete",       "label":"Delete my account","url":"…","active":false,"danger":true}
], "warnings": [] }
```

Ask for it rather than hard-coding six tabs: `certificates` and `invoices` are
present only when the plugin behind each is installed, and a site without
certificates would otherwise get a tab that leads nowhere. `danger: true` marks
the one entry that destroys something — the web screen draws it apart from the
rest, below a rule, in red.

---

## 1) Profile pane — `local_profilefields_get_account_profile`

No parameters.

```json
{
  "userid": 41,
  "fullname": "Tarek NITTest",
  "email": {
    "address": "tarek@example.com",
    "masked": "t****k@example.com",
    "label": "Email address",
    "canchange": true,
    "lockedreason": "",
    "help": "Changing your email address requires your password…",
    "pending": ""
  },
  "picture": {
    "enabled": true,
    "url": "…/pluginfile.php/…/f1",
    "urlsmall": "…/pluginfile.php/…/f2",
    "hasownpicture": false,
    "label": "Profile picture",
    "help": "JPG or PNG, up to 2 MB.",
    "maxbytes": 2097152,
    "acceptedtypes": [".jpg", ".jpeg", ".png"]
  },
  "sections": [
    {"name": "core",        "label": "Profile"},
    {"name": "cat_1",       "label": "Additional details"},
    {"name": "cat_2",       "label": "Instructor Fields"},
    {"name": "preferences", "label": "Preferences"}
  ],
  "fields": [
    {
      "name": "firstname", "shortname": "firstname", "type": "text",
      "label": "First name", "description": "", "required": true,
      "locked": false, "iscustom": false,
      "section": "core", "sectionlabel": "Profile",
      "value": "Tarek", "displayvalue": "Tarek", "format": 0,
      "help": "", "options": []
    },
    {
      "name": "profile_field_phone", "shortname": "phone", "type": "phone",
      "label": "Phone number", "required": true, "locked": false,
      "section": "cat_1", "sectionlabel": "Additional details",
      "value": "EG:1012345678", "displayvalue": "+20 101 234 5678",
      "options": [{"value":"EG","label":"Egypt","dialcode":"+20"}, …]
    }
  ],
  "warnings": []
}
```

### This is not the same list as `get_profile_form`

[`local_profilefields_get_profile_form`](profile-mobile-api.md) describes
`/user/edit.php`, which has around forty boxes. **This** function describes the
account screen, which is what the design shows: only the core fields the
administrator has placed on the profile, the custom fields grouped under their
category headings, and the language menu. On the current site that is 29 fields,
not 44 — and `city`/`country` are *not* among them, because they are set to
hidden in *Site administration → Profile fields*.

Build the screen from `sections` + `fields`. Do not hard-code the list: a field
an administrator adds, renames, reorders or locks appears here on the next call.

### Drawing a field

- `type` — `text`, `select`, `menu`, `checkbox`, `datetime`, `textarea`, `phone`,
  `file`. `phone` is drawn as a country picker + number and sent back as
  `"EG:1012345678"`; `datetime` is a unix timestamp; `checkbox` is `0`/`1`.
- `options` is non-empty for anything the user picks from; `dialcode` is filled
  in for country options.
- `locked: true` → show `displayvalue` read-only with a padlock. Do **not** hide
  it, and do not draw a disabled input the user can tap. A value sent for a
  locked field is ignored, exactly as the web form ignores it.
- `displayvalue` is the value written out for a reader — a country as its name, a
  menu as its chosen option, `"Not set"` for an empty one. `value` is what goes
  in the control.
- `help` is a sentence the screen owes the reader about that field (why a
  corrected name does not reissue a certificate; why the country decides the
  prices quoted). Show it under the field.

### Saving

`local_profilefields_update_profile` — documented in
[profile-mobile-api.md](profile-mobile-api.md). Send only the fields the user
touched; anything left out keeps its value.

```
POST wsfunction=local_profilefields_update_profile
     fields[0][name]=firstname&fields[0][value]=Tarek
     fields[1][name]=lang&fields[1][value]=ar
```

`lang` is accepted even though no form reports it as an element: core shows the
language menu only while an account is being *created*, but the account screen
offers it, so the save path takes it by name. A language pack the site does not
have comes back as a warning on `lang`, and nothing is stored.

The two things `update_profile` does **not** do:

- **the picture** — `core_files_upload` → `core_user_update_picture` (or
  `delete: 1`). `picture.enabled`, `maxbytes` and `acceptedtypes` above tell you
  what the screen may offer and what the upload will accept;
- **the email address** — see next.

### `local_profilefields_request_email_change` — the "Change" button

The address is deliberately not an editable field. Changing it starts with
proving the account is yours:

```
POST wsfunction=local_profilefields_request_email_change
     newemail=new@example.com&password=CURRENT_PASSWORD
```

```json
{ "sent": true,
  "pending": "new@example.com",
  "message": "We have sent a confirmation link to new@example.com…",
  "warnings": [] }
```

Nothing has changed yet. The account keeps its old address until the link sent to
the new one is opened; until then `email.pending` keeps reporting it. Show the
old address as current, with a "waiting for confirmation" note.

- Wrong password / address already taken / malformed address → `sent: false` and
  a `warnings` entry on `password` or `newemail`.
- `email.canchange` is `false` → do not draw the button at all; show
  `email.lockedreason`. Calling anyway throws, and there is nothing the user
  could type to get past it. Two different causes, two different sentences: the
  administrator has locked the field, or the account signs in through Google and
  has no password here to confirm with.

`update_profile` will also move an address, and for this button you must not use
it — it asks for no password. An unattended signed-in phone would otherwise be
enough to move somebody's account to an address the finder controls, and
confirming that address is how an account is taken over.

---

## 2) Security pane — `local_profilefields_get_security`

No parameters.

```json
{
  "userid": 41,
  "auth": "manual",
  "authname": "Manual accounts",
  "canchangepassword": true,
  "passwordlastchanged": 1783900800,
  "lastchangedtext": "Last changed 12 March 2026",
  "changenote": "Requires the current password, and terminates all other active sessions.",
  "passwordpolicy": "The password must have at least 8 characters…",
  "warnings": []
}
```

- `canchangepassword: false` → draw **no** button and show `lastchangedtext`,
  which in that case says the password lives with the external account. An
  account created through Google has no password here; a client that draws the
  button regardless sends that user to a form they can never satisfy.
- `passwordlastchanged: 0` does **not** mean "never changed". Core records the
  date only while `$CFG->passwordreuselimit` is above zero, so 0 means the site
  does not keep it. `lastchangedtext` already says the difference — show that
  string rather than formatting the timestamp yourself.
- `passwordpolicy` is plain text for under the new-password box; `""` when the
  site enforces none.
- `changenote` is worth showing *before* the change, not discovering afterwards
  when every session has dropped.

### `local_academy_change_password`

```
POST wsfunction=local_academy_change_password
     currentpassword=OLD&newpassword=NEW
```

```json
{ "changed": true, "warnings": [] }
```

Errors are exceptions with a stable `errorcode`:

| `errorcode` | Meaning |
|---|---|
| `err_wrongpassword` | The current password is not correct. |
| `err_weakpassword` | The new one fails the site policy — the message *is* the policy. |
| `err_authnochange` | This account's password is not ours to change. |

**On success the caller's own token is dead.** Changing a password destroys every
session and every web-service token the account holds, this one included
(AC-4.5.2) — every other device finds out on its next call, which comes back
`errorcode: "invalidtoken"`. Sign out locally and sign in again with the new
password; do not retry with the old token.

> The same call is also available as
> `/local/academy/api.php?function=change_password` (same parameters, JSON
> envelope). One implementation, two front doors — use whichever fits the screen
> you are on.

---

## 3) Certificates — `local_academy_get_my_certificates`

```
wsfunction=local_academy_get_my_certificates&page=0&perpage=10&lang=en
```

```json
{
  "certificates": [{
    "issueid": 812,
    "certificateid": 34,
    "cmid": 901,
    "courseid": 12,
    "name": "Course completion certificate",
    "coursename": "Management and Business 1",
    "code": "aBc123XyZ0",
    "timecreated": 1783900800,
    "verifyurl": "…/mod/customcert/verify_certificate.php?contextid=…&code=aBc123XyZ0",
    "downloadurl": "…/mod/customcert/my_certificates.php?certificateid=34&downloadcert=1"
  }],
  "total": 1, "page": 0, "perpage": 10, "available": true, "warnings": []
}
```

- `available: false` means this site has no certificate module. Hide the screen
  rather than showing an empty list. (The account menu leaves the tab out too.)
- `verifyurl` is public — no login, and it keeps working after the account is
  deleted. That is the link to share with an employer or put behind a QR code.
- `downloadurl` needs a browser session, so it is no use to a token client.
  Fetch the bytes instead:

### `local_academy_get_certificate_pdf`

```
wsfunction=local_academy_get_certificate_pdf&certificateid=34
```

```json
{ "filename": "Course completion certificate.pdf",
  "mimetype": "application/pdf",
  "filesize": 184213,
  "content": "JVBERi0xLjcK…",
  "warnings": [] }
```

`content` is base64. A certificate is **rendered on demand** from a template, not
stored as a file, so there is no `pluginfile.php` URL to fetch and nothing
`webservice/pluginfile.php` could serve — the bytes come back with the answer,
the same way `local_payments_get_invoice` returns an invoice.

Only ever the caller's own certificates. An id the user holds no issue for and an
id that does not exist give the same error (`err_nocertificateissue`), so the ids
cannot be walked.

> Why `local_academy_*` and not `mod_customcert_*`: `mod_customcert` is upstream
> code we do not modify, and its one listing function
> (`mod_customcert_list_issues`) is the *teacher's* list — it needs
> `mod/customcert:viewallcertificates`, so a learner asking for their own
> certificates is refused by it.

---

## 4) Payment history — `local_payments_get_transactions`

```
wsfunction=local_payments_get_transactions
  &page=0&perpage=20
  &q=PAY-2026&status=completed&courseid=12
  &datefrom=2026-01-01&dateto=2026-12-31
  &lang=ar
```

Every filter is optional. `q` searches the order reference **and** the invoice
number (the number on the PDF the student is holding). Dates are `YYYY-MM-DD` and
include the whole day, in the user's own timezone; anything that is not a date is
ignored rather than refused.

```json
{
  "transactions": [{
    "transaction_id": 3,
    "order_id": "PAY-2026-11721449",
    "courseid": 12,
    "item_type": "course",
    "item_name": "Management and Business 1",
    "amount": 80.0,
    "original_amount": 100.0,
    "currency": "EGP",
    "status": "completed",
    "status_label": "مكتمل",
    "provider": "Kashier",
    "payment_method": "card",
    "invoice_number": "INV-2026-0031",
    "timecreated": 1783900800,
    "can_download_invoice": true,
    "can_refund": true,
    "refund_pending": false,
    "refund_instant": true,
    "refund_label": "Refund now"
  }],
  "total": 3, "page": 0, "perpage": 20,
  "filtered": false,
  "filters": {
    "statuses": [{"value":"pending","label":"قيد الانتظار"}, …],
    "courses":  [{"id":12,"name":"Management and Business 1"}]
  },
  "warnings": []
}
```

- `total` is the count **after** filtering — page with it.
- `filters.statuses` and `filters.courses` are what to put in the filter
  controls. The course list holds only courses this account has actually paid
  for; a list of every course on the site would be mostly dead options.
- `filtered` tells the empty state which sentence to use: `true` → "nothing
  matches your filters", `false` → "you have not paid for anything yet". Saying
  the second to somebody whose filter is merely too narrow sends them looking for
  a fault that is not there.
- `item_type` is `course` or `subscription`; `item_name` names whichever it is, so
  a plan purchase does not show a blank where a course name would go.
- `can_refund` / `refund_instant` / `refund_label` — the button says what will
  actually happen: an instant refund inside the window, a request outside it.
  `refund_pending: true` means one is already waiting for a decision, so show a
  badge instead of a button.

Then the existing functions do the rest:

- **Invoice** → `local_payments_get_invoice` with `transaction_id` (and `lang=en`
  or `ar` — offer both outright; a student reads the site in Arabic but may need
  the English copy for an employer). Returns the PDF base64 in `pdf_base64`; pass
  `include_pdf=0` for the details only. Only call it when
  `can_download_invoice` is true.
- **Refund** → `local_payments_get_refund_options` then
  `local_payments_submit_refund`.

> `local_payments_get_payment_history` still exists and still works, but it
> cannot build this screen: no total, no filters, no per-row state. Use it only
> for a summary.

---

## 5) Delete account

### `local_profilefields_get_delete_account_info` — ask first

No parameters.

```json
{
  "allowed": true,
  "refusedreason": "",
  "title": "Delete my account",
  "cannotbeundone": "This cannot be undone",
  "warning": "Deleting your account removes your access to every course you have purchased and to the certificates you have earned. This cannot be undone.",
  "retained": "Financial records are retained. Certificates already issued remain publicly verifiable.",
  "passwordlabel": "Enter your password to confirm",
  "confirmword": "DELETE",
  "confirmlabel": "Type DELETE to confirm",
  "warnings": []
}
```

- `allowed: false` → show `refusedreason` and no form. Three accounts are refused:
  the guest account, an administrator (a site nobody can administer is a worse
  outcome than an inconvenient account), and an account that signs in through
  Google, which has no password here to give the required confirmation with.
- Show **all three** sentences. `retained` matters most to somebody hesitating: a
  learner who believes deletion revokes the certificate they earned will not
  click, and that would be the wrong reason to stay.
- `confirmword` is localised — an Arabic interface asks for the Arabic word. Read
  it from here; do not hard-code `"DELETE"`.

### `local_profilefields_delete_account` — do it

```
POST wsfunction=local_profilefields_delete_account
     password=CURRENT_PASSWORD&confirmword=DELETE
```

```json
{ "deleted": true, "message": "Your account has been deleted.", "warnings": [] }
```

- Wrong password or wrong word → `deleted: false` with `warnings` on `password`
  and/or `confirmword`. Nothing is destroyed.
- Both confirmations are required, and for different reasons: the password rules
  out an unattended signed-in phone; the typed word is because this is the one
  action on the site with no undo, and a single tap behind a saved password is not
  really a decision.
- On success **every token the account held is gone, this one included**. Clear
  local state and go to the sign-in screen — do not make another call.

What actually happens is an anonymisation, not a hard delete: the row survives so
that financial records stay intact and issued certificates stay publicly
verifiable, while the personal data, the custom profile fields, the picture,
every session, every remembered device and every token go.

---

## Where this lives

| | |
|---|---|
| `public/local/profilefields/classes/account_api.php` | the account screen as data — the shared source for the web pages and these functions |
| `public/local/profilefields/classes/external/` | `get_account_menu`, `get_account_profile`, `get_security`, `request_email_change`, `get_delete_account_info`, `delete_account` |
| `public/local/payments/classes/history_api.php` | the payment list, its filters and per-row state — used by both `history.php` and `get_transactions` |
| `public/local/payments/classes/external/get_transactions.php` | the web-service layer over it |
| `public/local/academy/classes/certificates_api.php` | the certificate list and PDF, reading `mod_customcert` without modifying it |
| `public/local/academy/classes/external/` | `get_my_certificates`, `get_certificate_pdf`, `change_password` |

No Moodle core file and no upstream plugin file is touched. Each screen's field
list, labels, locks and validation are read from the live configuration, so
anything an administrator changes reaches the app on the next call.

## Related

- [profile-mobile-api.md](profile-mobile-api.md) — `/user/profile.php` and
  `/user/edit.php`, and the `update_profile` reference
- [registration-mobile-api.md](registration-mobile-api.md) — sign-up, which
  shares the phone value format and the custom-field prefix
- [subscriptions-coupons-mobile-guide.md](subscriptions-coupons-mobile-guide.md)
  — plans, checkout and the payment functions this screen links out to
- [forgot-password-mobile-api.md](forgot-password-mobile-api.md) — the *forgotten*
  password flow, which is a different thing from the change here
