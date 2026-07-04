# Student Subscriptions API — Mobile Developer Guide

Endpoints a student uses to browse subscription plans, buy one, and see their subscriptions +
payment history. Implements [US-SB-1-1](../specs/student/US-SB-1-1-view-available-subscriptions.md),
[US-SB-1-2](../specs/student/US-SB-1-2-purchase-a-subscription.md),
[US-SB-2-1](../specs/student/US-SB-2-1-view-my-subscriptions-and-payment-history.md).

> A **subscription** buys time-boxed access to a set of Moodle **courses** (unlike a Flex package,
> which buys lesson credits). On a successful purchase the student is **enrolled** into the plan's
> courses until the subscription expires.

> 📮 **Use Postman?** Import [`Academy_Subscriptions.postman_collection.json`](Academy_Subscriptions.postman_collection.json)
> (Postman → Import → File). Run **"0. Login (get token)"** once (auto-saves `{{token}}`), then use the
> *Student — Subscriptions* folder. Details in [§9 Postman](#9-postman-quick-start).

> 💳 **Payment gateway is skipped for now.** `purchase_subscription` assumes the student already paid.
> It activates the subscription immediately and records a successful payment. When a real gateway is
> added, the app will call it first, then call this endpoint on success.

---

## 1. Base URL & endpoint

One endpoint, action chosen by the `function` param:

```
{BASE_URL}/local/academy/api.php
```
- Local dev: `http://localhost:8081` (Android emulator: `http://10.0.2.2:8081`; iOS sim: `http://localhost:8081`)
- Staging/prod: your domain, e.g. `https://academy2026.nitg-eg.com`

## 2. Authentication

Get a token once (per logged-in student) and send it as `token` on every call:
```
POST {BASE_URL}/login/token.php
Content-Type: application/x-www-form-urlencoded

username=STUDENT&password=PASSWORD&service=moodle_mobile_app
→ { "token": "abc123...", "privatetoken": null }
```
These endpoints work for **any logged-in user** — each call acts on the **token's own user**, so a
student only ever sees/affects their own subscriptions and payments.

## 3. Request / response

- **Read calls use `GET`** (`get_available_subscriptions`, `get_my_subscriptions`,
  `get_subscription_payment_history`).
- **`purchase_subscription` requires `POST`** (it changes state + records a payment). A GET returns
  `{"status":"fail","error":"This action requires POST"}`. Send params as a form body.
- Always send `function` + `token`.
- Valid token → `{ "status": "success", "data": ... }` or `{ "status": "fail", "error": "..." }`.
- ⚠️ Invalid/expired token → an **HTML** page, not JSON. Treat any non-JSON body as "session expired → re-login".

---

## 4. Endpoints

| # | Action | `function` | Method | Params (besides `function`, `token`) |
|---|--------|-----------|--------|--------------------------------------|
| 1 | Browse available subscriptions | `get_available_subscriptions` | GET | — |
| 2 | Buy a subscription | `purchase_subscription` | **POST** | `subscriptionid` (required), `method` (opt, default `online`), `reference` (opt) |
| 3 | My subscriptions | `get_my_subscriptions` | GET | — |
| 4 | My payment history | `get_subscription_payment_history` | GET | — |

---

### 4.1 `get_available_subscriptions` — US-SB-1-1
Active plans the student can buy, each with the courses it unlocks.
```
GET /local/academy/api.php?function=get_available_subscriptions&token=TOKEN
```
```json
{ "status": "success", "data": [
  { "id": 1, "name": "365-day", "description": "Full-year access",
    "price": "365.00", "duration_days": 365, "status": "active",
    "courses": [ { "id": 12, "fullname": "English" }, { "id": 13, "fullname": "Arabic" } ] }
] }
```
Show: `name`, `price`, `duration_days`, `description`, and the `courses` list (US-SB-1-1 display).

### 4.2 `purchase_subscription` — US-SB-1-2 (payment assumed paid) — **POST only**
```
POST /local/academy/api.php
Content-Type: application/x-www-form-urlencoded

function=purchase_subscription&token=TOKEN&subscriptionid=1&method=online&reference=DEMO123
```
```json
{ "status": "success", "data": {
  "purchaseid": 4, "paymentid": 4, "transaction_no": "SUB741D120F8B4CB0",
  "status": "active", "timeactivated": 1790400000, "expires_at": 1821936000,
  "courses": [ { "id": 12, "fullname": "English" } ] } }
```
- On success the student is **enrolled** into `courses` until `expires_at`.
- `expires_at` = unix seconds = activation + `duration_days`.
- The full price is recorded as a successful payment (platform revenue).
- **Rule:** a student may hold only **one active subscription**. Buying while one is active returns
  `fail` → `"You already have an active subscription"`.
- Failures: `"This subscription is not available for purchase"` (inactive),
  `"Subscription not found"` (bad id).

### 4.3 `get_my_subscriptions` — US-SB-2-1 (subscriptions)
```
GET /local/academy/api.php?function=get_my_subscriptions&token=TOKEN
```
```json
{ "status": "success", "data": [
  { "id": 4, "subscriptionid": 1, "name": "365-day", "price_paid": "365.00",
    "status": "active", "timeactivated": 1790400000, "expires_at": 1821936000,
    "remaining_days": 365, "duration_days": 365,
    "courses": [ { "id": 12, "fullname": "English" } ] }
] }
```
- The active subscription is returned **first** (a student has at most one active at a time).
- `status` is computed live: `active` | `expired` (and `cancelled` / `payment_failed` if set).
- `remaining_days` is whole days until expiry (0 when not active).

### 4.4 `get_subscription_payment_history` — US-SB-2-1 (payments)
```
GET /local/academy/api.php?function=get_subscription_payment_history&token=TOKEN
```
```json
{ "status": "success", "data": [
  { "id": 4, "subscriptionid": 1, "name": "365-day", "amount": "365.00", "method": "online",
    "reference": "DEMO123", "transaction_no": "SUB741D120F8B4CB0", "status": "success",
    "timecreated": 1790400000 } ]
}
```
Payment records persist even after the subscription expires.

---

## 5. Typical app flow

```
get_available_subscriptions  → show plan list (name, price, days, courses)
        │ user taps "Buy"
        ▼
(real gateway later — skipped now)
        │
        ▼
purchase_subscription(subscriptionid)  → success → student now enrolled in the plan's courses
        │
        ▼
get_my_subscriptions              → "My Subscriptions" screen (status, expiry, days left)
get_subscription_payment_history  → "Payment History" screen
```

## 6. Errors to handle
| `error` | Meaning | Suggested UI |
|---------|---------|--------------|
| `Authentication required` / `Invalid token` | missing/bad token | go to login |
| (HTML instead of JSON) | expired/invalid token | go to login |
| `You already have an active subscription` | one-active-subscription rule | disable Buy / show current subscription |
| `This subscription is not available for purchase` | plan inactive | refresh list |
| `Subscription not found` | bad `subscriptionid` | refresh list |
| `This action requires POST` | purchase sent as GET | use POST |

## 7. Notes / field reference
- Money fields (`price`, `price_paid`, `amount`) are **decimal strings** (e.g. `"365.00"`), currency EGP.
- Time fields are **unix seconds**. `duration_days` is whole days.
- Access is real Moodle enrolment; the courses also appear in the student's normal course list.
- A daily task expires overdue subscriptions and removes course access automatically.
- A student may hold only **one active subscription** at a time (buy again only after it expires).

## 8. Quick test (curl)
```bash
B=http://localhost:8081; T=YOUR_TOKEN
curl "$B/local/academy/api.php?function=get_available_subscriptions&token=$T"
curl -X POST "$B/local/academy/api.php" -d "function=purchase_subscription&token=$T&subscriptionid=1"
curl "$B/local/academy/api.php?function=get_my_subscriptions&token=$T"
curl "$B/local/academy/api.php?function=get_subscription_payment_history&token=$T"
```

## 9. Postman quick start
1. Postman → **Import** → **File** → `docs/api/Academy_Subscriptions.postman_collection.json` → **Import**.
2. Run **"0. Login (get token)"** once — it logs in and **auto-saves** `{{token}}`.
3. Open **Student — Subscriptions** and **Send** any request.

Collection variables (collection → *Variables* tab):
- `base_url` — default `http://localhost:8081` (on a phone use your machine's LAN IP, not `localhost`).
- `token` — auto-filled by the Login request.
- `subscriptionid` — id used by Purchase; set it from *Get Available Subscriptions*.
- `courseid` — used by the admin course-access requests.

**Purchase Subscription** is a **POST** with a urlencoded body. A bad/expired token returns HTML —
re-run **0. Login**.
