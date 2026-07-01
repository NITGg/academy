# Academy Payments API — Mobile Reference

Base URL: `https://your-domain.com`  
All web service calls go to: `POST /webservice/rest/server.php`  
Content-Type: `application/x-www-form-urlencoded`

Every request must include:
```
wstoken              = <user token>
moodlewsrestformat   = json
wsfunction           = <function name>
```

---

## Authentication

### Get Token
```
POST /login/token.php
```
| Field | Value |
|---|---|
| username | user's Moodle username |
| password | user's password |
| service | `moodle_mobile_app` |

**Response**
```json
{ "token": "abc123..." }
```
Store this token and pass it as `wstoken` in every subsequent request.

---

## Mobile Payment Flow

```
1. App opens course screen
        ↓
2. local_payments_get_course_access
        ↓
   is_enrolled = true  ──────────────────→  Show course content
        ↓ false
   is_purchased = true ──────────────────→  Show "Processing..." (webhook pending)
        ↓ false
   has_pending_payment = true ───────────→  Show "Payment in progress"
        ↓ false
3. local_payments_get_course_price
        ↓
   Show price UI + "Buy" button
        ↓ user taps Buy
4. local_payments_create_checkout
        ↓  returns checkout_url + order_id
5. Open checkout_url in WebView
        ↓
   User completes payment on Kashier
        ↓
   Kashier fires webhook → server enrolls user automatically
        ↓
   Kashier redirects WebView to /local/payments/callback.php
        ↓  detect this URL, read ?paymentStatus=SUCCESS|FAILED
6. Close WebView
        ↓
7. local_payments_verify_payment  (with saved order_id)
        ↓
   enrolled = true  ────────────────────→  Navigate to course content
   enrolled = false ────────────────────→  Show failure + retry
```

---

## API Reference

---

### 1. `local_payments_get_course_access`
Check enrollment and payment status. Call this every time the course screen loads.

**Parameters**
| Field | Type | Required |
|---|---|---|
| courseid | int | yes |

**Response**
```json
{
  "courseid": 59,
  "is_enrolled": false,
  "is_purchased": false,
  "has_pending_payment": false,
  "payment_status": "",
  "order_id": ""
}
```

| Field | Description |
|---|---|
| `is_enrolled` | User has access to course content |
| `is_purchased` | Payment completed (webhook confirmed) |
| `has_pending_payment` | A checkout was started and hasn't expired yet |
| `payment_status` | `"completed"` or `""` |
| `order_id` | Set when `is_purchased=true` |

---

### 2. `local_payments_get_course_price`
Get the price to display before purchase.

**Parameters**
| Field | Type | Required | Notes |
|---|---|---|---|
| courseid | int | yes | |
| country | string | no | ISO 3166-1 alpha-2. Auto-detected from user profile if omitted. |

**Response**
```json
{
  "courseid": 59,
  "country": "EG",
  "currency": "EGP",
  "price": 150.00,
  "original_price": 200.00,
  "sale_price": 150.00,
  "is_sale_active": true,
  "discount_percentage": 25,
  "sale_ends_at": 1751385600,
  "is_enrolled": false,
  "is_purchased": false
}
```

| Field | Description |
|---|---|
| `price` | The amount to charge (sale price if active, otherwise original) |
| `original_price` | Price before discount |
| `sale_price` | 0 if no sale |
| `is_sale_active` | Show strikethrough on `original_price` and countdown if true |
| `discount_percentage` | 0–100 |
| `sale_ends_at` | Unix timestamp of sale end, 0 if no sale |

---

### 3. `local_payments_get_payment_methods`
Get available payment providers for the user's country. Use this if you want to show the user which provider will handle the payment before calling create_checkout.

**Parameters**
| Field | Type | Required | Notes |
|---|---|---|---|
| courseid | int | yes | |
| country | string | no | Auto-detected if omitted |

**Response**
```json
[
  {
    "name": "kashier",
    "display_name": "Kashier",
    "priority": 1
  }
]
```

---

### 4. `local_payments_create_checkout`
Create a payment session. Call when the user taps "Buy".

**Parameters**
| Field | Type | Required | Notes |
|---|---|---|---|
| courseid | int | yes | |
| country | string | no | Auto-detected if omitted |
| lang | string | no | `en` or `ar` — controls Kashier checkout language. Default: `en` |

**Response**
```json
{
  "order_id": "PAY-2026-00012345",
  "checkout_url": "https://checkout.kashier.io/...",
  "expires_at": 1751305200,
  "provider": "kashier",
  "transaction_id": 12
}
```

| Field | Description |
|---|---|
| `checkout_url` | Open this in a WebView |
| `order_id` | **Save this** — needed for `verify_payment` |
| `expires_at` | Session expires after 30 min. After this, call create_checkout again |
| `transaction_id` | Internal ID for invoice lookup |

**WebView integration**
```dart
// Flutter example
NavigationDelegate(
  onNavigationRequest: (request) {
    if (request.url.contains('/local/payments/callback.php')) {
      final uri = Uri.parse(request.url);
      final status = uri.queryParameters['paymentStatus']; // 'SUCCESS' or 'FAILED'
      final orderId = uri.queryParameters['order_id'];
      // Close WebView, then call verify_payment
      return NavigationDecision.prevent;
    }
    return NavigationDecision.navigate;
  },
)
```

---

### 5. `local_payments_verify_payment`
Verify payment status after the WebView closes. The server-to-server webhook will have already enrolled the user before this call in most cases.

**Parameters**
| Field | Type | Required |
|---|---|---|
| order_id | string | yes |

**Response**
```json
{
  "success": true,
  "enrolled": true,
  "status": "completed",
  "courseid": 59
}
```

| `success` | `enrolled` | What to do |
|---|---|---|
| `true` | `true` | Navigate to course content |
| `true` | `false` | Enrolled but unusual — call `get_course_access` to confirm |
| `false` | `false` | Payment failed — show error + retry button |

---

### 6. `local_payments_get_payment_history`
All transactions for the logged-in user.

**Parameters:** none beyond the standard wstoken/wsfunction.

**Response**
```json
[
  {
    "order_id": "PAY-2026-00012345",
    "courseid": 59,
    "course_name": "test 26",
    "amount": 200.00,
    "currency": "EGP",
    "status": "completed",
    "provider": "kashier",
    "timecreated": 1751218800
  }
]
```

---

### 7. `local_payments_get_purchased_courses`
All courses the user has successfully purchased. Use for a "My Purchases" screen.

**Parameters:** none beyond the standard wstoken/wsfunction.

**Response**
```json
[
  {
    "courseid": 59,
    "course_name": "test 26",
    "order_id": "PAY-2026-00012345",
    "amount": 200.00,
    "currency": "EGP",
    "timecreated": 1751218800
  }
]
```

---

### 8. `local_payments_get_invoice`
Invoice details for a specific transaction.

**Parameters**
| Field | Type | Required | Notes |
|---|---|---|---|
| transaction_id | int | yes | From `create_checkout` response or payment history |

**Response**
```json
{
  "invoice_number": "INV-2026-000012",
  "order_id": "PAY-2026-00012345",
  "course_name": "test 26",
  "amount": 200.00,
  "currency": "EGP",
  "issued_at": 1751218800
}
```

---

## Error Handling

Moodle returns errors in this shape:
```json
{
  "exception": "moodle_exception",
  "errorcode": "nopricefound",
  "message": "No pricing rule found for this course in your region."
}
```

| `errorcode` | Meaning | What to show |
|---|---|---|
| `invalidtoken` | Token expired or function not authorized | Re-login |
| `nopricefound` | No pricing set up for user's country | "Not available in your region" |
| `noproviderfound` | No payment provider for country/currency | "Not available in your region" |
| `alreadypurchased` | User already bought this course | Navigate to course |
| `alreadyenrolled` | User is already enrolled | Navigate to course |
| `paymentinitiationfailed` | Kashier API error | "Payment unavailable, try later" |

---

## Multi-Provider Behavior

The server automatically selects the best provider based on the user's country and currency — the app does not need to choose. The selection order:

1. Detect user country (from Moodle profile → IP geolocation → admin default → Egypt fallback)
2. Query enabled providers ordered by priority (lowest number = highest priority)
3. Pick the first provider that supports both the detected country and the course currency

Example with two providers:
- Kashier (priority 1) — supports EG, SA / EGP, SAR
- PayPal (priority 2) — supports all countries / USD

| User country | Course currency | Provider selected |
|---|---|---|
| Egypt | EGP | Kashier |
| Saudi Arabia | SAR | Kashier |
| Egypt | USD | PayPal |
| Any | USD | PayPal |

The `provider` field in the `create_checkout` response tells you which was used.
