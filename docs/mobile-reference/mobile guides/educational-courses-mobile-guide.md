# "Educational Courses" Section — Mobile Developer Guide

How the home‑screen **“Educational courses — Choose the educational course and start with
us”** section works, and how to reproduce it in the mobile app.

This is the **course catalogue** grid. Each card shows a course with its price/sale, and a
call‑to‑action that changes depending on the student's relationship to that course
(free · buy · already purchased · already enrolled · covered by an active subscription ·
renew a lapsed subscription).

The web front page renders these cards from `local_payments\price_resolver::card_context()`.
The mobile app renders the **same states** from the fields returned by
`local_payments_get_courses_with_pricing`. This guide maps the two so the app behaves
identically to the website.

> Full field-by-field reference for every endpoint below lives in
> [`payments-api.md`](payments-api.md). This document focuses on the *screen flow*.

---

## 1. Transport basics

All calls go to the standard Moodle web‑service REST endpoint:

```
POST /webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded
```

Every request must include:

```
wstoken            = <user token>       # from /login/token.php, service = moodle_mobile_app
moodlewsrestformat = json
wsfunction         = <function name>
```

Get the token once at login:

```
POST /login/token.php
  username = <username>
  password = <password>
  service  = moodle_mobile_app
→ { "token": "abc123..." }
```

Then call `core_webservice_get_site_info` once to obtain `userid` (needed elsewhere in the app).

---

## 2. Screen flow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Open "Educational courses" screen                         │
│    → local_payments_get_courses_with_pricing                 │
│      (one call returns every course + its pricing + status)  │
└───────────────┬─────────────────────────────────────────────┘
                │  render one card per course (see §4 for state logic)
                ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. Tap a card → course detail screen                         │
│    → local_payments_get_course_access   (fresh status)       │
│    → local_payments_get_course_price    (fresh price)        │
└───────────────┬─────────────────────────────────────────────┘
                │  student taps "Subscribe / Buy now"
                ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Start checkout                                            │
│    → local_payments_get_payment_methods (optional: choose)   │
│    → local_payments_create_checkout → { checkout_url }       │
│      Open checkout_url in an in-app browser / WebView         │
└───────────────┬─────────────────────────────────────────────┘
                │  provider redirects back after payment
                ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Confirm result                                           │
│    → local_payments_verify_payment(order_id)                 │
│      { success, enrolled } → enter course when enrolled=true │
└─────────────────────────────────────────────────────────────┘
```

The country used for pricing is auto‑detected server‑side, but the app **may** override it by
passing a `country` (ISO‑3166‑1 alpha‑2, e.g. `EG`) on the pricing/checkout calls.

---

## 3. Listing the catalogue — `local_payments_get_courses_with_pricing`

One call fills the whole grid. It is a thin wrapper over
`core_course_external::get_courses_by_field`, so you get every standard course field **plus**
the pricing/status fields below.

**Request**

| Field   | Type       | Required | Notes |
|---------|------------|----------|-------|
| field   | alpha      | no       | `id` \| `ids` \| `shortname` \| `idnumber` \| `category`. Empty = **all** courses. |
| value   | raw        | no       | Value for `field`. For `ids`, comma‑separated integers. |
| country | alpha      | no       | Overrides auto‑detected country for pricing. |

Examples:
- All courses in a category: `field=category&value=7`
- Whole catalogue: send `field` and `value` empty.

**Response (per course — pricing fields only shown here)**

```json
{
  "id": 42,
  "fullname": "Mathematics — Grade 10",
  "summary": "...",
  "overviewfiles": [ { "fileurl": "https://.../pluginfile.php/..." } ],

  "pricing_country":     "EG",
  "currency":            "EGP",
  "price":               450.0,     // effective price (sale price if a sale is active)
  "sale_price":          450.0,     // 0 when no active sale
  "original_price":      600.0,
  "discount_percentage": 25,
  "is_sale_active":      true,
  "sale_ends_at":        1725148800,
  "is_free":             false,     // true → no pricing rule → open/free course
  "is_purchased":        false,     // this user already completed a purchase
  "is_enrolled":         false      // this user has ACTIVE access right now
}
```

> `is_enrolled` reflects **active** access only. A student whose subscription/package has
> lapsed comes back as `is_enrolled: false` even though the course still lives in their
> "My courses" list — that is the "renew" case (see §4).

Use `overviewfiles[0].fileurl` for the card image (append `?token=<wstoken>` or use the
`pluginfile`‑with‑token pattern to load protected files).

---

## 4. Card state — mirror the website exactly

Decide each card's badge/button from the pricing fields, in this priority order. This is the
same decision tree `price_resolver::card_context()` uses on the web.

| # | Condition | Card shows | Button / action |
|---|-----------|-----------|-----------------|
| 1 | `is_enrolled == true` | ✅ **Enrolled** badge | Open course |
| 2 | `is_free == true` | **Free** badge | **Join** → open course |
| 3 | `is_purchased == true` | 🛍️ **Purchased** badge | Open course |
| 4 | `is_sale_active == true` | `-{discount_percentage}%`, strike `original_price`, show `sale_price` | **Buy now** → checkout |
| 5 | otherwise (paid, no sale) | `price currency` | **Buy now** → checkout |

**Renew hint (lapsed access).** On the web, a course that is still in the student's
"My courses" but whose enrolment has expired shows a small **"Renew your subscription"**
note above the price. The catalogue endpoint reports such a course as
`is_enrolled: false` + `is_purchased: false` and it will fall into rows 4/5 above. To
reproduce the exact hint, cross‑reference the student's enrolled‑courses list
(`core_enrol_get_users_courses`) with `is_enrolled`: a course that appears there but returns
`is_enrolled: false` is a **renew** case — render a "Renew your subscription" note and point
the button at the normal checkout / subscription flow. (This mainly affects the "My courses"
screen, not the catalogue.)

**Subscription coverage.** If the student holds an active subscription that includes a course
they are not yet enrolled in, the web shows an **Enroll** button (free enrolment, no payment).
The catalogue endpoint does not expose this flag directly; the subscription flow and its
"enroll into a covered course" step are documented in
[`api/subscriptions-mobile-guide.md`](api/subscriptions-mobile-guide.md).

---

## 5. Course detail — refresh before buying

When the user opens a course, re‑fetch its live status/price (the grid data may be stale, and
a pending payment may exist):

### `local_payments_get_course_access`
`courseid` → returns:

```json
{
  "courseid": 42,
  "is_enrolled": false,
  "is_purchased": false,
  "payment_status": "",
  "order_id": "",
  "has_pending_payment": false,   // a checkout is already in progress
  "is_free": false
}
```

If `has_pending_payment` is true, resume/verify that order instead of starting a new one.

### `local_payments_get_course_price`
`courseid`, optional `country` → same pricing fields as a catalogue row (currency, price,
sale_price, original_price, discount_percentage, is_sale_active, sale_ends_at, is_free,
is_purchased, is_enrolled).

---

## 6. Checkout

### (optional) `local_payments_get_payment_methods`
Returns the payment providers available for the user's country/currency. Skip if you always
use the default provider.

### `local_payments_create_checkout`

**Request**

| Field    | Type  | Required | Notes |
|----------|-------|----------|-------|
| courseid | int   | yes      | Course being purchased. |
| country  | alpha | no       | Override country. |
| lang     | alpha | no       | `en` or `ar` for the hosted checkout page. Default `en`. |

**Response**

```json
{
  "order_id": "PMT-...",
  "checkout_url": "https://checkout.kashier.io/...",
  "expires_at": 1725148800,
  "provider": "kashier",
  "transaction_id": 1234
}
```

Open `checkout_url` in an in‑app browser / WebView. Keep `order_id` — you need it to verify.
The session expires at `expires_at` (default 30 min).

> Requires the `local/payments:purchasecourse` capability (students have it by default).

---

## 7. Confirm the result — `local_payments_verify_payment`

After the provider redirects back (or when the user returns to the app), verify:

**Request:** `order_id`

**Response**

```json
{
  "success": true,
  "status": "completed",
  "courseid": 42,
  "enrolled": true
}
```

- `success && enrolled` → payment done and access granted; route the student into the course.
- `status == "pending"` → not finished yet; poll again shortly or show "processing".
- `success == false` → show failure; the student can retry (`create_checkout` again).

Do **not** rely solely on the redirect URL — always confirm with `verify_payment`, since the
server also processes the provider's server‑to‑server callback.

---

## 8. After purchase / My courses

- `local_payments_get_purchased_courses` — courses the student has bought.
- `core_enrol_get_users_courses` — courses the student is enrolled in (used to build the
  "My courses" screen; combine with `is_enrolled` per §4 to detect the **renew** state).

---

## 9. Endpoint quick reference

| Purpose | Function | Type |
|---------|----------|------|
| List catalogue + pricing | `local_payments_get_courses_with_pricing` | read |
| Live access/status for a course | `local_payments_get_course_access` | read |
| Live price for a course | `local_payments_get_course_price` | read |
| Available payment providers | `local_payments_get_payment_methods` | read |
| Start a checkout | `local_payments_create_checkout` | write |
| Verify a payment | `local_payments_verify_payment` | write |
| Purchase history | `local_payments_get_payment_history` | read |
| Purchased courses | `local_payments_get_purchased_courses` | read |
| Invoice details | `local_payments_get_invoice` | read |

All are registered on the `moodle_mobile_app` service — the same `wstoken` from login works
for every one. See [`payments-api.md`](payments-api.md) for full request/response schemas and
error handling, and [`api/subscriptions-mobile-guide.md`](api/subscriptions-mobile-guide.md)
for the subscription (course‑bundle) flow.
