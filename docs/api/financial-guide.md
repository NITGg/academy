# Financial — API Guide (Phase 3)

Revenue distribution, reversal, teacher wallet, and withdrawals.

Stories: **US-FN-1-4** (distribute on complete) · **US-FN-1-5** (admin reversal of a consumed Flex) ·
**US-FN-2-1** (teacher withdrawal request) · **US-FN-2-2** (admin process withdrawal).
Wallet model: `docs/specs/financial/00-wallet-model.md`.

All calls go through `…/local/academy/api.php?function=NAME&token=TOKEN`. Response is
`{ "status":"success", "data":… }` or `{ "status":"fail", "error":"msg" }`. State-changing calls are
**POST**; reads are GET. Admin functions require the **`local/academy:manageplatform`** capability
(site managers/admin); teacher functions act on the token's user.

---

## Money model

```
flex_value      = purchase.price_paid / purchase.flex_count
teacher_amount  = round(flex_value * teacher_percent / 100, 2)
platform_amount = flex_value - teacher_amount      # the two always sum to flex_value
```
`teacher_percent` / `platform_percent` come from lesson settings (US-AD-2-1) and must total 100.

**Teacher wallet**

| field | meaning |
|-------|---------|
| `total_earned` | Σ active earnings (teacher share) |
| `pending_withdrawals` | Σ withdrawals in `pending` + `approved` |
| `total_withdrawn` | Σ withdrawals in `paid` |
| `available_balance` | `total_earned − pending_withdrawals − total_withdrawn` |

A withdrawal **reserves** its amount as soon as it's requested (it counts against the balance while
`pending`/`approved`). **Reject** releases it back; **paid** keeps it spent. A **reversed** earning
drops out of `total_earned` (so it reduces the available balance — even retroactively).

**Earning lifecycle:** `active` → `reversed` (admin only). **Withdrawal lifecycle:**
`pending` → `approved` → `paid`, or `pending`/`approved` → `rejected`.

---

## When earnings are created

There is **no "distribute" endpoint** — distribution happens automatically inside
`complete_lesson` (US-LS-3-2). Completing a lesson consumes the reserved Flex **and** writes one
`academy_earnings` row (idempotent per lesson). So to produce an earning: confirm a lesson → complete it.

---

## Endpoints

### Teacher

#### `get_teacher_wallet` (GET)
The caller's wallet summary + `earnings[]` + `withdrawals[]`. Powers the teacher wallet UI.

#### `request_withdrawal` (POST) — US-FN-2-1
Params: `amount` (> 0, ≤ available balance), `method?` (`bank`|`wallet`|`cash`, default `bank`),
`account?` (payout details). Creates a `pending` request.

#### `get_my_withdrawals` (GET)
The caller's own withdrawals (most recent first).

### Admin (`manageplatform`)

#### `reverse_flex` (POST) — US-FN-1-5
Params: `lessonid`, `reason` (required). Returns one **consumed** Flex to the student (consumed →
remaining) and flips the lesson's active earning to `reversed`. A lesson can be reversed only once;
fails with `err_earningnotfound` if it was never distributed and `err_alreadyreversed` if already done.

#### `list_withdrawals` (GET) — US-FN-2-2
Params: `status?` (`pending`|`approved`|`paid`|`rejected`). All requests with teacher name/email.

#### `process_withdrawal` (POST) — US-FN-2-2
Params: `withdrawalid`, `action` = `approve` | `reject` | `pay`, plus `reason?` (required for reject),
`reference?` (for pay).
- `approve`: `pending` → `approved`
- `reject`: `pending`/`approved` → `rejected` (amount returns to balance)
- `pay`: `approved` → `paid` (records reference)

#### `get_platform_wallet` (GET)
Overview: `current_money` (payments − paid-out), `undistributed_money` (value of unconsumed Flex),
`teachers_money` (earned − paid), `platform_earnings`, `total_payments`, `total_paid_out`.

---

## Quick cURL walkthrough

```bash
BASE=http://localhost:8081/local/academy/api.php
TEACHER=<teacher-token>; ADMIN=<admin-token>

# (precondition) a lesson was confirmed then completed → an earning exists for the teacher
curl -s "$BASE?function=get_teacher_wallet&token=$TEACHER"

# teacher requests a withdrawal
curl -s -X POST "$BASE" --data-urlencode function=request_withdrawal --data-urlencode token=$TEACHER \
  --data-urlencode amount=0.40 --data-urlencode method=bank --data-urlencode account="IBAN123"

# admin reviews, approves, then pays
curl -s "$BASE?function=list_withdrawals&token=$ADMIN&status=pending"
curl -s -X POST "$BASE" --data-urlencode function=process_withdrawal --data-urlencode token=$ADMIN \
  --data-urlencode withdrawalid=<id> --data-urlencode action=approve
curl -s -X POST "$BASE" --data-urlencode function=process_withdrawal --data-urlencode token=$ADMIN \
  --data-urlencode withdrawalid=<id> --data-urlencode action=pay --data-urlencode reference=PAY-001

# admin reverses a completed lesson's Flex
curl -s -X POST "$BASE" --data-urlencode function=reverse_flex --data-urlencode token=$ADMIN \
  --data-urlencode lessonid=<id> --data-urlencode reason="Dispute upheld"
```

## UI

- **Teacher** — `wallet.php` ("My earnings"): balance cards, *Withdraw earnings*, withdrawals + earnings
  tables. Reach via Preferences → User account → **My earnings** (teachers only).
- **Admin** — `manage_withdrawals.php` ("Teacher withdrawals"): platform wallet cards, process
  (approve/reject/pay) each request, and a *Reverse a completed lesson's Flex* form. Reach via
  Site administration → Plugins → Local plugins → **Teacher withdrawals**.

Postman: `Academy_Financial.postman_collection.json` (this folder).
