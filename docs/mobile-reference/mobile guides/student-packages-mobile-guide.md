# Student Packages API — Mobile Developer Guide

Endpoints a student uses to browse packages, buy one, and see their packages + payment history.
Implements [US-PK-1-1](../specs/student/US-PK-1-1-view-available-packages.md),
[US-PK-1-2](../specs/student/US-PK-1-2-purchase-a-package.md),
[US-PK-2-1](../specs/student/US-PK-2-1-view-my-packages-and-payment-history.md).

> 📮 **Use Postman?** Import the ready collection
> [`Academy_Packages.postman_collection.json`](Academy_Packages.postman_collection.json)
> (Postman → Import → File). Run **"0. Login (get token)"** once (it auto-saves `{{token}}`), then use the
> *Student — Packages* folder. Details in [§9 Postman](#9-postman-quick-start).

> 💳 **Payment gateway is skipped for now.** `purchase_package` assumes the student already paid
> (i.e. "went to the gateway and paid"). It activates the package immediately and records a successful
> payment. When a real gateway is added, the app will call it first, then call this endpoint on success.

---

## 1. Base URL & endpoint

One endpoint, action chosen by the `function` param:

```
{BASE_URL}/local/academy/api.php
```

- Local dev: `http://localhost:8081`  (emulator: use `http://10.0.2.2:8081` on Android, `http://localhost:8081` on iOS sim)
- Staging/prod: your domain, e.g. `https://academy2026.nitg-eg.com`

## 2. Authentication

Get a token once (per logged-in student) and send it as `token` on every call:

```
POST {BASE_URL}/login/token.php
Content-Type: application/x-www-form-urlencoded

username=STUDENT&password=PASSWORD&service=moodle_mobile_app
→ { "token": "abc123...", "privatetoken": null }
```

These endpoints work for **any logged-in user** (no admin rights needed). Each call acts on the
**token's own user** — a student only ever sees/affects their own packages and payments.

## 3. Request / response

- **Read calls use `GET`** (`get_available_packages`, `get_my_packages`, `get_payment_history`).
- **`purchase_package` requires `POST`** (it changes state + records a payment). A GET returns
  `{"status":"fail","error":"This action requires POST"}`. Send params as a form body.
- Always send `function` + `token`.
- Valid token → JSON: `{ "status": "success", "data": ... }` or `{ "status": "fail", "error": "..." }`.
- ⚠️ Invalid/expired token → an **HTML** page, not JSON (platform-wide behaviour). Treat any
  non-JSON / non-200 body as "session expired → re-login".

---

## 4. Endpoints

| # | Action | `function` | Method | Params (besides `function`, `token`) |
|---|--------|-----------|--------|--------------------------------------|
| 1 | Browse available packages | `get_available_packages` | GET | — |
| 2 | Buy a package | `purchase_package` | **POST** | `packageid` (required), `method` (opt, default `online`), `reference` (opt) |
| 3 | My packages | `get_my_packages` | GET | — |
| 4 | My payment history | `get_payment_history` | GET | — |

---

### 4.1 `get_available_packages` — US-PK-1-1
Active packages the student can buy.

```
GET /local/academy/api.php?function=get_available_packages&token=TOKEN
```
```json
{ "status": "success", "data": [
  { "id": "6", "name": "Flex20", "description": "...", "flex_count": "20",
    "price": "1900.00", "expiration_days": "0", "status": "active" }
] }
```
Show: `name`, `flex_count`, `price`, `expiration_days` (0 = never expires), `description`.

### 4.2 `purchase_package` — US-PK-1-2  (payment assumed paid) — **POST only**
```
POST /local/academy/api.php
Content-Type: application/x-www-form-urlencoded

function=purchase_package&token=TOKEN&packageid=6&method=online&reference=DEMO123
```
```json
{ "status": "success", "data": {
  "purchaseid": 3, "paymentid": 1, "transaction_no": "TXN741D120F8B4CB0",
  "flex_balance": 20, "expires_at": 1790413057, "status": "active" } }
```
- `flex_balance` = flexes credited. `expires_at` = unix seconds (0 = never).
- **Rule:** a student may hold only **one active package**. Buying while one is active returns
  `fail` → `"You already have an active package"`.
- Other failures: `"This package is not available for purchase"` (inactive), `"Package not found"`.

### 4.3 `get_my_packages` — US-PK-2-1 (packages)
```
GET /local/academy/api.php?function=get_my_packages&token=TOKEN
```
```json
{ "status": "success", "data": [
  { "id": 3, "packageid": 6, "name": "Flex20",
    "total_flex": 20, "remaining_flex": 20, "used_flex": 0, "price_paid": "1900.00",
    "status": "active", "timeactivated": 1782637057, "expires_at": 1790413057, "expiration_days": 90 }
] }
```
- Active package is returned **first**.
- `status` is computed live: `active` | `fully_used` (remaining = 0) | `expired` | `cancelled`.

### 4.4 `get_payment_history` — US-PK-2-1 (payments)
```
GET /local/academy/api.php?function=get_payment_history&token=TOKEN
```
```json
{ "status": "success", "data": [
  { "id": 1, "packageid": 6, "name": "Flex20", "amount": "1900.00", "method": "online",
    "reference": "DEMO123", "transaction_no": "TXN741D120F8B4CB0", "status": "success",
    "timecreated": 1782637057 } ]
}
```
Payment records persist even after the package expires or is fully used.

---

## 5. Typical app flow

```
get_available_packages  → show package list
        │ user taps "Buy"
        ▼
(real gateway later — skipped now)
        │
        ▼
purchase_package(packageid)  → success → show confirmation (transaction_no, flex_balance)
        │ fail "already have active package" → tell the user
        ▼
get_my_packages        → "My Packages" screen (balance, expiry, status)
get_payment_history    → "Payment History" screen
```

## 6. Errors to handle

| `error` | Meaning | Suggested UI |
|---------|---------|--------------|
| `Authentication required` / `Invalid token` | missing/bad token | go to login |
| (HTML instead of JSON) | expired/invalid token | go to login |
| `You already have an active package` | one-active-package rule | disable Buy / show current package |
| `This package is not available for purchase` | package inactive | refresh list |
| `Package not found` | bad `packageid` | refresh list |

## 7. Notes / field reference
- Money fields (`price`, `price_paid`, `amount`) are **decimal strings** (e.g. `"1900.00"`), currency EGP.
- Time fields are **unix seconds**.
- `expiration_days` / `expires_at`: `0` means never expires.
- Each Flex = one lesson (lesson booking will use the balance — separate stories, not built yet).

## 8. Quick test (curl)
```bash
B=http://localhost:8081; T=YOUR_TOKEN
curl "$B/local/academy/api.php?function=get_available_packages&token=$T"
curl -X POST "$B/local/academy/api.php" -d "function=purchase_package&token=$T&packageid=6"
curl "$B/local/academy/api.php?function=get_my_packages&token=$T"
curl "$B/local/academy/api.php?function=get_payment_history&token=$T"
```

## 9. Postman quick start

Don't build requests by hand — import the ready collection.

1. Postman → **Import** → **File** → choose `docs/api/Academy_Packages.postman_collection.json` → **Import**.
2. Run **"0. Login (get token)"** once — it logs in and **auto-saves** the token into `{{token}}`.
3. Open the **Student — Packages** folder and **Send** any request.

Collection variables (collection → *Variables* tab):
- `base_url` — default `http://localhost:8081` (change for staging/prod; on a phone use your machine's LAN IP, not `localhost`).
- `token` — auto-filled by the Login request.
- `packageid` — id used by Purchase; set it to a real id from *Get Available Packages*.

**Purchase Package** is already set up as a **POST** with a urlencoded body. A bad/expired token returns
HTML instead of JSON — just re-run **0. Login**.
