# Payments — mobile API

Everything the app needs to sell a course or a subscription plan, and to show a
receipt afterwards.

## The one thing to know first

**The API does not change when the gateway changes.** Kashier and Fawaterk sit
behind the same endpoints; the app calls `local_payments_create_checkout` either
way and never learns which gateway handled it beyond a name in the response. If
the site switches provider, or runs two at once and picks per country, the app
needs no release.

There is exactly one extra call compared to the old Kashier-only flow — a
payment-method list — and it is optional. Skip it and you get the gateway's own
payment page, which is what Kashier always did.

## Transport

All calls go to the standard Moodle web-service endpoint:

```
POST {site}/webservice/rest/server.php
     ?wstoken={token}
     &moodlewsrestformat=json
     &wsfunction={function}
```

Parameters are normal form fields. Every function takes an optional `alang`
(`en` / `ar`) that sets the language of any text it returns.

Errors come back as `{"exception": "...", "errorcode": "...", "message": "..."}`.
Show `message`: it is already translated and already written for a buyer.

---

## 1. Price a course

```
wsfunction = local_payments_get_course_price
```

| Param | Notes |
|---|---|
| `courseid` | required |
| `country` | ISO-2 from the device; optional, falls back to the profile country |
| `alang` | `en` / `ar` |

Returns the resolved price, the pre-discount price and the currency. Show
`amount`, and show `original_amount` struck through only when it differs.

> A user with no country on their profile has no price. That is deliberate:
> pricing is per country. The call returns an error whose `message` tells them to
> set it — surface it and link to the profile.

---

## 2. Offer the payment methods (optional)

```
wsfunction = local_payments_get_provider_payment_methods
```

| Param | Notes |
|---|---|
| `courseid` | the course being bought, or `0` for a subscription |
| `country` | optional |
| `currency` | only used when `courseid = 0` |
| `alang` | `en` / `ar` |

```jsonc
{
  "provider": "fawaterk",
  "supports_payment_methods": true,
  "methods": [
    { "id": 2, "name_en": "Visa-Mastercard", "name_ar": "فيزا -ماستر كارد",
      "logo": "https://…/mastercard-visa.png", "redirect": true },
    { "id": 3, "name_en": "Fawry", "name_ar": "فوري",
      "logo": "https://…/fawry.png", "redirect": false }
  ]
}
```

- `supports_payment_methods: false` — the gateway shows its own picker (Kashier
  does). **Skip the picker screen entirely** and go to step 3 with no
  `payment_method_id`.
- The list comes from the merchant account and changes when methods are switched
  on or off there. Fetch it at checkout; never hard-code ids.
- An empty `methods` array with `supports_payment_methods: true` means the
  account has not published its list. Go to step 3 without a method — the server
  picks a sensible one.

---

## 3. Start the payment

```
wsfunction = local_payments_create_checkout                       // a course
wsfunction = local_nit_subscriptions_create_subscription_checkout // a plan
```

| Param | Course | Subscription | Notes |
|---|---|---|---|
| `courseid` | ✔ | — | |
| `subscriptionid` | — | ✔ | |
| `payment_method_id` | ✔ | ✔ | from step 2; `0` = server picks, `-1` = gateway's own page |
| `coupon_code` | ✔ | ✔ | optional |
| `country` | ✔ | ✔ | optional |
| `alang` | ✔ | ✔ | language of the gateway screen |
| `type`, `seats` | — | ✔ | `normal` \| `b2b`, and the seat count for B2B |
| `return_url` | — | ✔ | optional |

Both return the same shape:

```jsonc
{
  "order_id": "PAY-2026-04471382",
  "checkout_url": "https://app.fawaterk.com/ts/a1b2c",
  "expires_at": 1756550400,
  "provider": "fawaterk",
  "transaction_id": 4471,
  "amount": 350.0,
  "original_amount": 500.0,
  "currency": "EGP",
  "payment_data": {
    "type": "redirect",
    "redirect_url": "https://app.fawaterk.com/ts/a1b2c",
    "reference": "",
    "reference_expires_at": "",
    "method_name": "Visa-Mastercard",
    "qr": ""
  }
}
```

**Branch on `payment_data.type`, not on `checkout_url`.** For a reference payment
`checkout_url` is empty, and an app that opens it shows a blank web view.

| `type` | What to do |
|---|---|
| `redirect` | Open `redirect_url` in a web view. It returns to `…/local/payments/callback.php` when done — that is your cue to close it and go to step 4. |
| `reference` | Show `reference` large, with copy and share. Add `reference_expires_at` if set, and render `qr` as a QR code if non-empty (mobile wallets). **Do not open a web view.** The buyer pays at an outlet or in a wallet app, often the next day. |
| `none` | Nothing to show. Go straight to step 4. |

Keep `order_id` — it is the handle for everything afterwards.

---

## 4. Confirm

```
wsfunction = local_payments_verify_payment    // params: order_id
```

The gateway's webhook is what actually completes the order and enrols the
student. This call reads that result, and asks the gateway directly if the
webhook has not landed yet.

- **After a `redirect` payment:** poll every ~3 s for up to ~2 minutes once the
  web view returns.
- **After a `reference` payment:** do not block. The order stays pending until
  the code is paid, which may be tomorrow. Re-check when the app comes to the
  foreground, and show it in the payment history meanwhile.

---

## 5. Afterwards

```
wsfunction = local_payments_get_purchased_courses   // what they own
wsfunction = local_payments_get_course_access       // one course: enrolled? paid?
wsfunction = local_payments_get_payment_history     // the list for a receipts screen
wsfunction = local_payments_get_invoice             // one order's invoice details
```

### Invoice PDF

Not a web service — a plain authenticated URL that returns
`application/pdf`. Open it in a browser/downloader with the user's session, or
attach the token:

```
{site}/local/payments/invoice.php?transaction_id={id}
{site}/local/payments/invoice.php?transaction_id={id}&lang=ar
```

Omit `lang` and it follows the site language. Pass `en` or `ar` to force one —
useful because someone using the Arabic app may still need the English invoice
for an employer, so offering both is worth a small menu rather than a single
button.

Only completed and refunded orders have one; anything else returns an error page.

---

## 6. Refunds

Two calls. Full detail, including where staff configure the policy, is in
[refunds.md](refunds.md) — this is what the app needs.

### Ask what is on offer

```
wsfunction = local_payments_get_refund_options    // params: transaction_id
```

```jsonc
{
  "action": "refund",
  "reason_required": false,
  "message": "",
  "paid": 36.0, "fee": 3.6, "fee_percent": 10.0, "net": 32.4, "currency": "EGP",
  "window_hours": 48,
  "deadline": 1756900000,
  "policy": "Refundable within 48 hours of purchase, less a 10.00% (3.60 EGP) fee."
}
```

Switch on `action` — one field, rather than three booleans to combine:

| `action` | Show |
|---|---|
| `refund` | A **Refund** button. Reason optional. |
| `request` | A **Request refund** button. Reason **required**. |
| `pending` | "Waiting on a decision" — already asked. |
| `none` | Nothing. `message` says why, already translated. |

Show `net` as the headline figure; `paid` and `fee` explain it. The fee is always
a percentage of what was paid — `fee_percent` is that percentage, `fee` is what it
comes to for this payment — so nothing needs converting per currency. `policy` is
a ready-made sentence if you would rather not compose one.

### Do it

```
wsfunction = local_payments_submit_refund         // params: transaction_id, reason
```

```jsonc
{ "outcome": "refunded", "message": "Refunded 32.40 EGP…", "amount": 32.4, "currency": "EGP" }
```

`outcome` is `refunded`, `requested`, or `failed`.

**One endpoint for both routes, on purpose.** Which one applies depends on a
window that can close between drawing the screen and pressing the button, so the
server decides and tells you what it did. Do not pick an endpoint from a cached
`action`, and do not hide the button because a countdown expired locally — call
and let the server answer.

After `refunded`, the buyer has lost access: refresh whatever you cache about
enrolment. After `requested`, nothing changes yet; they are notified when a
decision is made.

---

## The whole flow

```
get_course_price(courseid)
        │
        ▼
get_provider_payment_methods(courseid)
        │
        ├── supports_payment_methods = false ──► create_checkout(courseid)
        │                                        └─► open checkout_url
        │
        └── show picker ──► create_checkout(courseid, payment_method_id: <id>)
                                    │
                    ┌───────────────┼───────────────┐
              type=redirect    type=reference     type=none
                    │                │                │
            open redirect_url   show the code         │
                    │                │                │
                    └──────► verify_payment(order_id) ◄┘
                                     │
                              completed → enrolled
                                     │
                              invoice.php?transaction_id=…
```

---

## Testing notes

- `expires_at` is when the order dies. For a card that is ~30 minutes; for a
  Fawry code it is days. Do not hard-code a countdown from the response time.
- Calling `create_checkout` twice for the same course returns the **same**
  pending order, unless the price or the payment method changed — so a retry
  after a dropped connection is safe and will not double-charge.
- `amount` is what will be charged. `original_amount` is the pre-discount price.
  Show `amount` as the price to pay; the difference is the discount.
