# API changes — course offers & course coupons (2026-07-18)

Two backend changes that close the gap between what the **web** does and what the **mobile API**
exposed for **courses**. Packages and subscriptions were already complete; only the course flow was
missing these.

For the full integration guide, see
[`coupons-offers-mobile-guide.md`](coupons-offers-mobile-guide.md).

> **No field is removed, renamed, or retyped in either change** — existing mobile builds will not
> crash or fail to parse.
>
> - **Change 2 is purely additive**: a new *optional* request parameter, unchanged response.
> - **Change 1 adds one field (`offer_name`) but also corrects the values of three existing ones**
>   (`is_sale_active`, `sale_price`, `discount_percentage`) for courses that have an active offer.
>   Those values were previously *wrong* — the catalog reported "no sale" while checkout charged the
>   discounted amount. See the table in Change 1 for the exact before/after.

---

## Change 1 — automatic offers now appear in the course catalog

**Endpoint:** `local_payments_get_courses_with_pricing`
**File:** [`get_courses_with_pricing.php:99-116`](../../src/local/payments/classes/external/get_courses_with_pricing.php#L99)

### The problem

The endpoint returned country-resolved pricing from `price_resolver::resolve()` but never consulted
`local_academy`'s automatic offers. The web catalog *does* — via
[`price_resolver::display_fields()`](../../src/local/payments/classes/price_resolver.php#L232).

Result: the app showed the full price, but the student was **charged the lower offer price at
checkout**. Display and payment disagreed.

### The fix

After resolving country pricing, each course now folds in
`\local_academy\discount_manager::offer_summary('course', $courseid, $resolved->price)` — the exact
same call the web makes.

### What changed in the response

When an active offer applies to a course:

| Field | Before | After |
|---|---|---|
| `is_sale_active` | only price-table sales | `true` for offers too |
| `sale_price` | price-table sale, or `0` | **offer-adjusted final price** — the real charge |
| `discount_percentage` | vs price-table sale | recalculated against `original_price` |
| `price` | *(unchanged)* | *(unchanged — still pre-offer, for the "was → now" strike-through)* |
| **`offer_name`** | — | 🆕 e.g. `"Flash Sale + Summer Discount"`, or `""` when no offer |

**No offer active ⇒ the response is byte-identical to before, except `offer_name: ""`.**

### Why the values were wrong in the first place

`local_payments_create_checkout` has **always** applied automatic offers — with or without a coupon.
[`apply_academy_discount()`](../../src/local/payments/classes/manager.php#L518) runs on every checkout
and stacks offers unconditionally; the coupon code is only consulted when one is supplied.

So the discount was never missing from the *payment* — it was missing from the *display*:

| | Offers applied? | Price shown / charged |
|---|---|---|
| `get_courses_with_pricing` (catalog) | ❌ **was not** | 1000 |
| `local_payments_create_checkout` (payment) | ✅ always was | **700** |

Change 1 doesn't add a new discount. It makes the catalog report the price the student was already
going to be charged.

### App-side action

Display **`sale_price`** whenever `is_sale_active` is true — not `price`. Render `offer_name` as a
promo tag when non-empty. See §4a of the guide.

⚠️ **One thing to verify on the app side:** `offer_name` is a *new* key in the course object. Most
Dart deserializers (`json_serializable` by default) ignore unknown fields, so this is harmless — but
if the course model was generated with strict/unknown-field-rejecting settings, parsing could throw.
Quick check before deploying.

---

## Change 2 — coupon codes now work at course checkout

**Endpoint:** `local_payments_create_checkout`
**Files:** [`create_checkout.php`](../../src/local/payments/classes/external/create_checkout.php),
[`services.php:21`](../../src/local/payments/db/services.php#L21)

### The problem

The mobile developer asked whether the web's coupon box uses a new API. **It does not.** The web flow
is a plain HTML form:

1. [`buy.php:82-94`](../../src/local/payments/buy.php#L82) renders a `<form method="get">` with a
   `coupon_code` field and the *"تطبيق وشراء"* button.
2. It submits to [`checkout.php`](../../src/local/payments/checkout.php) — a **server-rendered page**,
   not a web service.
3. That page calls `manager::create_checkout($courseid, $USER->id, null, $lang, $coupon)` and
   302-redirects to the Kashier URL.

So `\local_payments\manager::create_checkout()` has accepted a `$coupon_code` argument all along
([`manager.php:87`](../../src/local/payments/classes/manager.php#L87)). The **web-service wrapper**
just never forwarded it — it called the manager with 4 arguments, so `$coupon_code` silently
defaulted to `''`.

This also made courses inconsistent with the other two flows, which already forwarded it
([`api.php:471`](../../src/local/academy/api.php#L471), [`api.php:588`](../../src/local/academy/api.php#L588)):

| Flow | Endpoint | Coupon before | Coupon after |
|---|---|---|---|
| Subscriptions | `create_subscription_checkout` | ✅ | ✅ |
| Packages | `create_package_checkout` | ✅ | ✅ |
| Courses | `local_payments_create_checkout` | ❌ | ✅ |

### The fix

Added an optional `coupon_code` parameter (`PARAM_TEXT`, defaults to `''`) and passed it through to
the manager. No change to the manager, the discount engine, or the response shape.

### New request

```
POST {BASE_URL}/webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken=TOKEN
&wsfunction=local_payments_create_checkout
&moodlewsrestformat=json
&courseid=61
&country=EG              ← optional (unchanged)
&lang=ar                 ← optional (unchanged)
&coupon_code=SAVE50      ← 🆕 optional
```

Response shape is **unchanged** (`order_id`, `checkout_url`, `expires_at`, `provider`,
`transaction_id`).

### Error handling

Unlike `preview_discount` — which returns `status: "success"` plus a `coupon_error` field — an invalid
coupon **throws** here:

```json
{
  "exception": "moodle_exception",
  "errorcode": "err_couponexpired",
  "message": "This coupon has expired."
}
```

Codes: `err_couponnotfound`, `err_couponinactive`, `err_couponnotstarted`, `err_couponexpired`,
`err_couponnotapplicable`, `err_couponusedup`, `err_couponcoderequired`.

**Recommended app flow:** validate with `preview_discount` first (soft errors, live total), then send
the same code to `create_checkout` and treat a thrown exception as "the coupon expired between
preview and pay" → re-preview and let the user retry.

### ⚠️ Known limitation — pending transaction reuse

`manager::create_checkout()` short-circuits and returns an **existing pending transaction** for the
same user + course if one is still within its TTL
([`manager.php:111`](../../src/local/payments/classes/manager.php#L111)) — and that return happens
*before* the coupon is applied.

> If the user creates a course checkout **without** a coupon, abandons it, then retries **with** one
> inside the 30-minute window, they get the original un-discounted `checkout_url` and the coupon is
> silently ignored.

This is pre-existing behavior, not introduced by this change, and it affects the web equally. Not
fixed here because the correct fix (invalidate-and-recreate when the resolved amount differs) is a
behavioral change to the shared manager that also affects the web checkout — worth doing, but as its
own scoped change.

---

## Deployment notes

- The `coupon_code` parameter is read from `execute_parameters()` at **runtime**, so it works as soon
  as the code is deployed — no DB migration needed.
- The `description` text in `db/services.php` is cached in the `external_functions` table. Bump
  `local_payments` `version.php` and run the upgrade if you want the updated description to show in
  *Site administration → Server → Web services → API Documentation*. Purely cosmetic.
- Purge caches after deploying.

## Files touched

| File | Change |
|---|---|
| [`get_courses_with_pricing.php`](../../src/local/payments/classes/external/get_courses_with_pricing.php) | Fold in `offer_summary()`; add `offer_name` to params + return schema |
| [`create_checkout.php`](../../src/local/payments/classes/external/create_checkout.php) | Add `coupon_code` param; forward to `manager::create_checkout()` |
| [`db/services.php`](../../src/local/payments/db/services.php) | Update `local_payments_create_checkout` description |

## Still open

`local_payments_get_course_price` (single-course detail endpoint) has the **same offer gap Change 1
fixed for the catalog** — it maps `price_resolver::resolve()` output directly without folding in
offers. If the app uses it to back a course-detail screen, that screen will contradict the catalog
list. Patching it means applying the identical fold-in from `get_courses_with_pricing.php`.
