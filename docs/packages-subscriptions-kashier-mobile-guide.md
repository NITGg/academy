# Packages & Subscriptions — Kashier Checkout (Mobile Guide)

How to buy a **Flex package** or a **subscription** from the app with a real Kashier payment.
This replaces the old "payment assumed paid" flow described in
[`student-packages-mobile-guide.md`](student-packages-mobile-guide.md) and
[`subscriptions-mobile-guide.md`](subscriptions-mobile-guide.md).

> ⚠️ **Why this doc exists.** The web frontend (`student.php`) was switched to real Kashier
> checkout on 2026-07-04. `purchase_package` / `purchase_subscription` (the old endpoints) still
> exist and still work, but they **skip payment entirely** — they activate the package/subscription
> for free. Don't call them for a real purchase anymore. Use `create_package_checkout` /
> `create_subscription_checkout` below instead, exactly like course purchases already do
> (see [`payments-api.md`](../payments-api.md)).

---

## 1. What stays the same

Everything else in the two mobile guides is unchanged and still applies:

- `get_available_packages`, `get_my_packages`, `get_payment_history` (packages)
- `get_available_subscriptions`, `get_my_subscriptions`, `get_subscription_payment_history` (subscriptions)
- Base URL, auth (`login/token.php`), request/response conventions, error shape.

Only the **buy** step changes.

## 2. What changes — the buy step

| | Old (assumed paid) | New (Kashier) |
|---|---|---|
| Endpoint | `purchase_package` / `purchase_subscription` | `create_package_checkout` / `create_subscription_checkout` |
| Where | `local/academy/api.php` (custom, `?token=`) | same |
| What it does | Activates immediately, records a "success" payment with no money moved | Opens a real Kashier payment session, returns a `checkout_url` |
| After payment | n/a | Call `local_payments_verify_payment` (real Moodle web service) to confirm + activate |

The checkout-creation calls are **not** registered Moodle web services (there's no
`local_academy` `db/services.php`) — they're only reachable through the same custom
`api.php?function=...&token=...` dispatcher the rest of the packages/subscriptions API uses. The
**confirmation** call, `local_payments_verify_payment`, *is* a proper Moodle web service (under
`moodle_mobile_app`), reachable at `/webservice/rest/server.php` with your normal mobile `wstoken`
— the same token you already get from `login/token.php`.

---

## 3. Flow

```
1. get_available_packages / get_available_subscriptions
        │ user taps "Buy"
        ▼
2. create_package_checkout(packageid)  /  create_subscription_checkout(subscriptionid)
        │  POST to local/academy/api.php
        ▼  returns { order_id, checkout_url, expires_at, provider, transaction_id }
3. Open checkout_url in a WebView
        │  user pays on Kashier (card + 3DS)
        ▼
   Kashier fires a server-to-server webhook → server activates the package /
   enrolls the subscription's courses automatically (this is the source of truth)
        │
   Kashier redirects the WebView to /local/payments/callback.php?order_id=...&paymentStatus=SUCCESS|FAILED
        │  detect this URL, read paymentStatus, close the WebView
        ▼
4. local_payments_verify_payment(order_id)   — real Moodle WS, POST /webservice/rest/server.php
        │  returns { success, status, enrolled }
        ▼
   success → get_my_packages / get_my_subscriptions to refresh the UI
   fail    → show error + retry (call create_*_checkout again)
```

This is the identical pattern already used for course purchases
(see [`payments-api.md`](../payments-api.md) §Mobile Payment Flow and
[`kashier-payment-flow.md`](../kashier-payment-flow.md)) — packages and subscriptions share the
same `local_payments` transaction/webhook engine as courses, just with `item_type = package` or
`subscription` instead of `course`.

---

## 4. `create_package_checkout` — buy a package

```
POST /local/academy/api.php
Content-Type: application/x-www-form-urlencoded

function=create_package_checkout&token=TOKEN&packageid=6
```

**Success**
```json
{ "status": "success", "data": {
  "order_id": "PAY-2026-00012345",
  "checkout_url": "https://checkout.kashier.io/...",
  "expires_at": 1790413057,
  "provider": "kashier",
  "transaction_id": 12
} }
```

**Failure** (`{"status":"fail","error":"..."}`)

| `error` | Meaning |
|---|---|
| `You already have an active package` | one-active-package rule (same as `purchase_package`) |
| `Payment could not be initiated: ...` | Kashier API error — show "payment unavailable, try later" |
| `This action requires POST` | sent as GET |

- `checkout_url` expires after `expires_at` (unix seconds, ~30 min). Past that, call
  `create_package_checkout` again to get a fresh session.
- Reusing the same `packageid` while a session is still pending returns the **same**
  `checkout_url`/`order_id` (no duplicate Kashier sessions are created).

## 5. `create_subscription_checkout` — buy a subscription

```
POST /local/academy/api.php
Content-Type: application/x-www-form-urlencoded

function=create_subscription_checkout&token=TOKEN&subscriptionid=1
```

**Success** — identical shape to packages:
```json
{ "status": "success", "data": {
  "order_id": "PAY-2026-00012346",
  "checkout_url": "https://checkout.kashier.io/...",
  "expires_at": 1790413057,
  "provider": "kashier",
  "transaction_id": 13
} }
```

**Failure**

| `error` | Meaning |
|---|---|
| `You already have an active subscription` | one-active-subscription rule |
| `Payment could not be initiated: ...` | Kashier API error |
| `This action requires POST` | sent as GET |

## 6. Open the checkout URL in a WebView

Same pattern as course purchases:

```dart
// Flutter example
NavigationDelegate(
  onNavigationRequest: (request) {
    if (request.url.contains('/local/payments/callback.php')) {
      final uri = Uri.parse(request.url);
      final status = uri.queryParameters['paymentStatus']; // 'SUCCESS' or 'FAILED'
      final orderId = uri.queryParameters['order_id'];
      // Close the WebView, then call local_payments_verify_payment(orderId)
      return NavigationDecision.prevent;
    }
    return NavigationDecision.navigate;
  },
)
```

`paymentStatus=FAILED` means the user cancelled/failed on Kashier's side — you can skip verify and
just show a retry option. On anything else, always call `verify_payment` before trusting the
result (the redirect URL itself is never authoritative — see below).

## 7. `local_payments_verify_payment` — confirm + activate

This is a **real Moodle web service**, not the custom `api.php`. Call it via the standard REST
endpoint with your existing mobile `wstoken`:

```
POST /webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken=TOKEN&wsfunction=local_payments_verify_payment&moodlewsrestformat=json&order_id=PAY-2026-00012345
```

**Response**
```json
{ "success": true, "status": "completed", "courseid": 0, "enrolled": false }
```

- `success: true` + `status: "completed"` → the package/subscription is active. Refresh with
  `get_my_packages` / `get_my_subscriptions`.
- `enrolled` and `courseid` are only meaningful for **course** purchases — for packages/subscriptions
  they'll be `false` / `0`; ignore them and just check `success` + `status`.
- In almost all cases the server-to-server webhook already activated the purchase before you even
  call this — `verify_payment` just reads that result. It only does the extra Kashier
  round-trip itself if the webhook hasn't landed yet, so it's safe (and fast) to call every time the
  WebView closes.
- `success: false` → payment failed or still pending. Show an error with a retry button that calls
  `create_package_checkout` / `create_subscription_checkout` again.

## 8. Quick test (curl)

```bash
B=http://localhost:8081; T=YOUR_TOKEN

# 1. Start a package checkout
curl -X POST "$B/local/academy/api.php" -d "function=create_package_checkout&token=$T&packageid=6"
# → { "status": "success", "data": { "order_id": "...", "checkout_url": "...", ... } }

# 2. (pay on checkout_url in a browser/WebView)

# 3. Verify — real Moodle WS, note the different endpoint + wstoken param name
curl -X POST "$B/webservice/rest/server.php" \
  -d "wstoken=$T&wsfunction=local_payments_verify_payment&moodlewsrestformat=json&order_id=PAY-2026-00012345"

# 4. Refresh
curl "$B/local/academy/api.php?function=get_my_packages&token=$T"
```

Subscriptions: same steps, swap `create_package_checkout`/`packageid` for
`create_subscription_checkout`/`subscriptionid`, and `get_my_packages` for `get_my_subscriptions`.

## 9. Notes

- Money fields, time fields, "one active package/subscription at a time" — all unchanged from the
  original guides ([packages](student-packages-mobile-guide.md#7-notes--field-reference),
  [subscriptions](subscriptions-mobile-guide.md#7-notes--field-reference)).
- Currency is always `EGP` for packages/subscriptions today (no multi-country pricing yet, unlike
  courses).
- If you built against `purchase_package` / `purchase_subscription` for testing/staging without a
  real Kashier sandbox, those endpoints still work for that purpose — just don't ship them as the
  production "Buy" action.

## 10. Related docs

- [`student-packages-mobile-guide.md`](student-packages-mobile-guide.md) — full package browsing/history API (buy step now superseded by this doc).
- [`subscriptions-mobile-guide.md`](subscriptions-mobile-guide.md) — full subscription browsing/history API (buy step now superseded by this doc).
- [`payments-api.md`](../payments-api.md) — the same Kashier checkout pattern for course purchases.
- [`kashier-payment-flow.md`](../kashier-payment-flow.md) — end-to-end backend architecture (webhook, signature verification, status machine).
