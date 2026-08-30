# Fawaterk payment provider

Fawaterk (Fawaterak) is a payment gateway subplugin of `local_payments`, living at
`public/local/payments/provider/fawaterk/`. It sits behind the same
`provider_interface` as Kashier, so nothing above the gateway layer knows which
one is charging the card.

It supports two flows:

| Flow | Endpoint | Who picks the payment method | Use it for |
|------|----------|------------------------------|------------|
| **Server-to-server** (recommended) | `POST /api/v2/invoiceInitPay` | your app, from a list you fetch | the mobile app, and any UI that renders its own method picker |
| Hosted invoice link | `POST /api/v2/createInvoiceLink` | Fawaterk, on its own page | the web checkout, or as a fallback |

The flow is chosen per checkout: pass a `payment_method_id` and you get the
server-to-server flow; omit it (or pass `0`) and you get the hosted link.

---

## 1. Configuration

**Site admin → Plugins → Local plugins → Payments → Provider settings → Fawaterk**

| Setting | Notes |
|---------|-------|
| Sandbox mode | On → `https://staging.fawaterk.com`, off → `https://app.fawaterk.com` |
| Vendor key (API key) | Bearer token for every API call **and** the HMAC secret for webhook signatures |
| Provider key | Only needed for Fawaterk's JS iframe; leave empty |
| Live / Sandbox API base URL | Overridable in case Fawaterk moves hosts |
| Fallback phone / address | Sent when the buyer's Moodle profile has neither (both are mandatory to Fawaterk) |
| Email / SMS the invoice | Lets Fawaterk notify the buyer directly |

Then **Manage providers** → enable *Fawaterk* (and set its priority above Kashier
if it should be the default pick).

### Webhook

In the Fawaterk dashboard set the webhook URL to:

```
https://<your-site>/local/payments/webhook_json.php
```

The `_json` suffix is required — it is how Fawaterk decides to POST a JSON body
rather than form fields. That is also why Fawaterk gets its own endpoint file
instead of the shared `webhook.php?provider=…`.

The webhook is what actually enrols the buyer. Everything the app does after
starting a payment is polling for the result of this call.

---

## 2. Mobile API

Three calls, all on the standard Moodle web-service endpoint
(`/webservice/rest/server.php`) with `moodlewsrestformat=json` and the user's token.

### 2.1 List payment methods

```
wsfunction = local_payments_get_provider_payment_methods
```

| Param | Type | Notes |
|-------|------|-------|
| `courseid` | int | The course being bought. `0` for a subscription. |
| `country` | string | ISO-2 from the app (optional — falls back to the profile country) |
| `currency` | string | Only used when `courseid=0` |
| `alang` | string | `en` / `ar` |

```jsonc
{
  "provider": "fawaterk",
  "supports_payment_methods": true,
  "methods": [
    { "id": 2, "name_en": "Visa-Mastercard", "name_ar": "فيزا -ماستر كارد",
      "logo": "https://…/mastercard-visa.png", "redirect": true },
    { "id": 3, "name_en": "Fawry", "name_ar": "فوري",
      "logo": "https://…/fawry.png", "redirect": false },
    { "id": 4, "name_en": "Meeza", "name_ar": "ميزا",
      "logo": "https://…/MeezaDigitalSmall.png", "redirect": false }
  ]
}
```

> **`supports_payment_methods: false`** means the gateway that would handle this
> purchase (e.g. Kashier) shows its own picker. Skip the picker screen entirely,
> call `create_checkout` with no `payment_method_id`, and open `checkout_url`.
>
> The list comes from the Fawaterk account, so it changes when methods are
> enabled or disabled there. Fetch it at checkout time; don't hard-code ids.

### 2.2 Start the payment

```
wsfunction = local_payments_create_checkout                       // a course
wsfunction = local_nit_subscriptions_create_subscription_checkout // a plan
```

Both take the same new parameter:

| Param | Type | Notes |
|-------|------|-------|
| `payment_method_id` | int | `id` from the list above. `0` = hosted page. |

…alongside what they already took (`courseid` / `subscriptionid`, `country`,
`alang`, `coupon_code`, and for subscriptions `type`, `seats`, `return_url`).

Both now return a `payment_data` object on top of their existing fields:

```jsonc
{
  "order_id": "PAY-2026-04471382",
  "checkout_url": "https://staging.fawaterk.com/link/I0PAH",
  "expires_at": 1756550400,
  "provider": "fawaterk",
  "transaction_id": 4471,
  "amount": 350.0,
  "original_amount": 500.0,
  "currency": "EGP",
  "payment_data": {
    "type": "redirect",
    "redirect_url": "https://staging.fawaterk.com/link/I0PAH",
    "reference": "",
    "reference_expires_at": ""
  }
}
```

`payment_data.type` is the only thing the app needs to branch on:

| `type` | What it means | What the app does |
|--------|---------------|-------------------|
| `redirect` | Card / 3-D Secure, or a hosted page | Open `redirect_url` in a web view. Watch for a return to `/local/payments/callback.php`, then go to step 2.3. |
| `reference` | Fawry / Meeza / wallet code | Show `reference` (and `reference_expires_at` if set) with copy + share. **Do not** open a web view. The buyer pays at an outlet or in their wallet app, possibly hours later. |
| `none` | No extra step | Go straight to step 2.3. |

Example of a `reference` response (Fawry, `payment_method_id: 3`):

```jsonc
{
  "order_id": "PAY-2026-04471382",
  "checkout_url": "",
  "provider": "fawaterk",
  "transaction_id": 4472,
  "amount": 350.0,
  "currency": "EGP",
  "payment_data": {
    "type": "reference",
    "redirect_url": "",
    "reference": "981335305",
    "reference_expires_at": "2026-09-02 15:53:41"
  }
}
```

Note `checkout_url` is **empty** here. An app that only reads `checkout_url` will
show a blank web view — always branch on `payment_data.type` first.

### 2.3 Confirm the payment

```
wsfunction = local_payments_verify_payment    // params: order_id
```

Fawaterk's webhook is the authoritative path — it is what completes the
transaction and enrols the student. `verify_payment` reads that result and, if
the webhook hasn't landed yet, falls back to asking Fawaterk directly.

- **`redirect` payments:** poll every ~3 s for up to ~2 min after the web view
  returns.
- **`reference` payments:** don't block the UI. The order stays `PENDING` until
  the buyer pays the code (or the checkout TTL expires — default 30 min, raise
  `local_payments | payment_ttl` if you sell via Fawry). Re-check on app
  foreground, or show it under *Payment history*.

---

## 3. Full mobile sequence

```
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
                              status = completed → enrolled
```

---

## 4. Behaviour worth knowing

**Reusing a pending checkout.** A second `create_checkout` for the same course
returns the existing pending session only if the price *and* the payment method
match. Switching from Fawry to card retires the old session and opens a new one,
so the buyer never gets a code for a charge they've changed their mind about.

**Amounts are re-read, not trusted.** Fawaterk's `paid` webhook carries no
amount, and the amount check is what gates enrolment — so on a valid paid webhook
the gateway calls `getInvoiceData` and uses the API's figures. A webhook whose
signature is fine but whose invoice isn't actually paid is rejected.

**Refunds.** Fawaterk's v2 API has no refund endpoint, so `supports_refund()` is
`false`. Refunds are raised in the Fawaterk dashboard. Their refund webhook is
unsigned and carries no invoice reference, so it is recorded in
`local_payments_webhooks` but never applied automatically — reconcile it by hand.

**Currency.** The provider row allows `EGP, USD, SAR, AED`, but the Fawaterk
*account* decides what it will actually accept. A currency the account doesn't
support comes back as an HTTP 400 at `createInvoiceLink` / `invoiceInitPay`.

---

## 5. Troubleshooting

Every API call is logged to `local_payments_logs` with the full response body:

```sql
SELECT timecreated, level, message, context
  FROM mdl_local_payments_logs
 WHERE provider_id = (SELECT id FROM mdl_local_payments_providers WHERE name = 'fawaterk')
 ORDER BY id DESC LIMIT 20;
```

Webhook bodies (including ones that failed signature checks) are in
`mdl_local_payments_webhooks`.

| Symptom | Usual cause |
|---------|-------------|
| `HTTP 400` on checkout | A rejected field. The message now includes Fawaterk's own validation text — read it. Most often: a currency the account doesn't support, a phone that isn't `01XXXXXXXXX`, or a missing address. Phone and address already fall back to the configured placeholders. |
| `HTTP 401` | Wrong vendor key, or a live key used while sandbox mode is on (and vice versa). |
| Payment succeeds but no enrolment | The webhook isn't arriving. Check the dashboard URL ends in `webhook_json.php`, and look for `signature_valid = 0` rows — that means the vendor key in Moodle differs from the one signing the webhook. |
| `no redirect URL or reference` | The account doesn't have that `payment_method_id` enabled. Re-fetch the method list. |
