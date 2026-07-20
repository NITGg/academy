# Renew vs. Scheduled Enrolment — Mobile Developer Guide

A student can show up as **not actively enrolled** (`is_enrolled: false`) in a course they
already registered for, for two completely different reasons. The API now tells you which one
it is so the app can show the right message instead of a generic/misleading one.

| State | What happened | What the app should show |
|-------|----------------|---------------------------|
| **Renew** | A subscription/package the student bought has a real end date, and that date has passed. Their old enrolment record still exists but access has lapsed. | "Renew your subscription" + a way to buy/subscribe again. |
| **Scheduled** | The student registered (e.g. via a `program`‑plugin allocation) but the admin set access to open on a **future date**. They haven't started yet — nothing has expired. | "Access starts on `<date>`" (or "Access starts soon"). **No** buy/renew button — they already have a spot. |

Never show "Renew your subscription" for the scheduled case — that's the bug this feature
fixes. The two are distinguished server‑side, not by the app.

---

## 1. Where the flags come from

Every endpoint that reports a course's access status now includes three fields:

| Field | Type | Meaning |
|-------|------|---------|
| `can_renew` | bool | `true` → lapsed subscription/package. Show the renew hint. |
| `is_scheduled` | bool | `true` → registered, access starts later. Show the scheduled hint. |
| `scheduled_starts_at` | int (unix timestamp) | When `is_scheduled` is `true`, the access start time. `0` if not yet known — show a generic "starts soon" instead of a date. |

At most one of `can_renew` / `is_scheduled` is ever `true` for the same course at the same
time; when both are `false` the course is either not touched at all, or `is_enrolled`/
`is_purchased`/`is_free` already covers it (see the priority order in
[`educational-courses-mobile-guide.md` §4](educational-courses-mobile-guide.md#4-card-state--mirror-the-website-exactly)).

---

## 2. Catalogue — `local_payments_get_courses_with_pricing`

Each course object in the `courses` array now includes the three fields above:

```json
{
  "id": 61,
  "fullname": "Physics — Grade 11",
  "is_free": false,
  "is_purchased": false,
  "is_enrolled": false,
  "can_renew": false,
  "is_scheduled": true,
  "scheduled_starts_at": 1755648000,
  "...": "...other pricing fields (see educational-courses-mobile-guide.md §3)"
}
```

**Card decision tree** (check these first, before free/purchase/sale logic):

```
if is_enrolled:            → "Enrolled" badge, open course
elif can_renew:             → "Renew your subscription" hint (+ normal buy/subscribe button
                               below it — renewing goes through the same checkout flow)
elif is_scheduled:          → "Access starts on {date}" hint, NO buy button
else:                        → fall through to free / purchased / sale / price rows
                               (see educational-courses-mobile-guide.md §4)
```

Format `scheduled_starts_at` client‑side as a localized date; treat `0` as "no date known yet"
and show "Access starts soon" instead.

---

## 3. Course detail page — `buy.php` parity

When the student taps into a course that isn't free/purchased/enrolled, the web app's
`/local/payments/buy.php?courseid=<id>` page does this:

1. If actively enrolled → redirect straight into the course.
2. If `is_scheduled` → show **only** the "Access starts on …" message with a "back to home"
   action. No pricing, no buy button, no coupon field — they don't need to pay.
3. Otherwise → show the normal purchase page. If `can_renew` is also true, a
   "Renew your subscription" hint appears above the price/buy button (renewing is just a normal
   purchase — same `create_checkout` / `verify_payment` flow as a first‑time buy).

Reproduce the same order in the app's course‑detail screen using `local_payments_get_course_access`
(§4 below) before deciding whether to show the checkout button at all.

---

## 4. Course detail refresh — `local_payments_get_course_access`

This was already used to refresh status right before checkout; it now also returns the two
flags so you don't have to fall back on the (now stale) catalogue data:

```json
{
  "courseid": 61,
  "is_enrolled": false,
  "is_purchased": false,
  "can_renew": false,
  "is_scheduled": true,
  "scheduled_starts_at": 1755648000,
  "payment_status": "",
  "order_id": "",
  "has_pending_payment": false,
  "is_free": false
}
```

If `is_scheduled` is `true`, do not proceed to `create_checkout` — there's nothing to buy; the
student already has a spot waiting for its start date.

---

## 5. Where this comes from server‑side (context, not an API you call)

For engineers curious why a course can be "enrolled but not active" for two different reasons:

- A **renewal** case is a real Moodle enrolment with a `timeend` in the past (set by
  `local_academy` when granting subscription/package access with a real expiry).
- A **scheduled** case is a registration whose access window hasn't started yet — either an
  active enrolment with `timestart` in the future, or a `program`‑plugin (`enrol_programs`)
  allocation configured with a scheduled start date (see the plugin's
  `program_allocation.php` admin screen).

The classification logic lives in `local_payments\enrollment_handler::enrolment_state()` and is
shared by every endpoint listed above — the app never needs to compute this itself.

---

## 6. Quick reference

| Field | Where it appears |
|-------|-------------------|
| `can_renew` | `local_payments_get_courses_with_pricing` (per course), `local_payments_get_course_access` |
| `is_scheduled` | same as above |
| `scheduled_starts_at` | same as above |

See [`educational-courses-mobile-guide.md`](educational-courses-mobile-guide.md) for the full
catalogue/checkout flow these fields plug into, and [`payments-api.md`](payments-api.md) for
the complete request/response schema of every `local_payments_*` function.
