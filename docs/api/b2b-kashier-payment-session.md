# B2B Subscriptions — Kashier Payment Integration (Session Summary)

**Plugin:** `local_academy` (+ `local_payments`) at `src/local/`
**Site:** https://academy2026.nitg-eg.com (Moodle 3.11)
**Status at end of session:** implemented in code, all changed PHP files pass `php -l`; **not committed, not deployed**.

---

## 0. ROOT CAUSE (the real bug behind "it subscribed as normal")

The `type` parameter on both subscription endpoints was read with **`PARAM_ALPHA`**, which allows
letters only. The value **`"b2b"` contains a digit**, so `PARAM_ALPHA` silently sanitised it to
**`"bb"`**. Downstream, `$isb2b = ("bb" === "b2b")` was always **false**, so:

- Every "Business (B2B)" purchase was processed as a **normal** subscription (no B2B role assigned,
  no `type='b2b'` purchase row, no seats recorded).
- The B2B button therefore never showed an owned state, and clicking it again threw
  **"لديك بالفعل اشتراك نشط"** (`err_alreadyhassubscription`) via the normal-path guard.
- The B2B **dashboard nav link never appeared**, because it keys off an active `type='b2b'` purchase.

**Fix:** use **`PARAM_ALPHANUM`** for `type` in `api.php` (both `purchase_subscription` and
`create_subscription_checkout`), so `"b2b"` survives intact. This bug pre-existed this session (it was
in the original `purchase_subscription` endpoint), so any earlier "B2B" test purchases are actually
**normal** rows in `academy_sub_purchases` — clean those up (see §9) so they don't block re-testing.

---

## 1. Why this session happened

A previous session shipped the B2B (seat-based, multi-user) subscription feature, but the
**"Business (B2B)" purchase button used an "assumed-paid" shortcut** — it called
`purchase_subscription` directly and never went through the Kashier payment gateway. This produced
three visible problems on the front page:

1. **B2B did not open the Kashier payment page** the way a normal subscription does — it just created
   the purchase instantly.
2. After a B2B purchase, the **normal subscription cards wrongly showed "Subscribed"** (disabled),
   because the active-subscription state counted B2B purchases too.
3. The **"Business (B2B)" button stayed clickable forever** (no "already owned" state), so it looked
   like the purchase had not registered / had been recorded as a normal subscription.

Additionally, `create_subscription_checkout` blocked on **any** active subscription, so once a user
owned a B2B subscription they got *"you already have a subscription"* when trying to buy a normal one.

**Decision (confirmed with the user):** make B2B use the **exact same Kashier flow as a normal
subscription**.

---

## 2. The target flow (after this change)

```
Front page  "Business (B2B)" button
   → confirmB2b() modal (pick seat capacity, see base/discount/final)
   → api.php  create_subscription_checkout  (type=b2b, seats=N)
   → local_payments\manager::create_subscription_checkout
        · validates the plan is b2b_enabled + the seat option exists
        · computes B2B price via subscription_manager::b2b_price()
        · stores { item_type:subscription, sub_type:b2b, seats:N } in transaction metadata
        · charges the B2B price
   → redirect to Kashier payment page
   → user pays
   → webhook (local/payments/webhook.php) / callback verify
        → purchase_subscription(userid, subid, method, order_id, 'b2b', seats)
             · creates academy_sub_purchases row (type=b2b, seats, base_price, discount_percent)
             · assigns the b2b_administrator role (system context)
             · grants the buyer their own course access (no seat consumed)
   → reload front page → button now shows "Manage business plan" → b2b_dashboard.php
```

This is identical in shape to the normal subscription flow; the only B2B-specific parts are the price
computation and the `sub_type`/`seats` carried in the transaction metadata.

---

## 3. Files changed

### `src/local/payments/classes/manager.php`
- **`create_subscription_checkout()`** — added `string $type='normal', int $seats=0` params.
  - When `type=b2b`: require `b2b_enabled`, look up the matching `academy_sub_seat_options` row, and
    compute the charge with `\local_academy\subscription_manager::b2b_price()` (so the amount charged
    equals what the webhook later records as `price_paid`).
  - The **"one active subscription" guard is now scoped to `type=normal`** — a normal purchase is
    blocked only by an active *normal* subscription; B2B purchases are never blocked and never block a
    normal purchase.
  - Transaction `amount`/`original_amount`, the `payment_request` amount, and the metadata all use the
    computed `$amount`; metadata now includes `sub_type` and `seats`.
- **Both webhook fulfilment handlers** (the async webhook path and the sync callback/verify path) now
  forward `$meta->sub_type` and `$meta->seats` into `purchase_subscription(...)`.

### `src/local/academy/api.php`
- **`create_subscription_checkout`** endpoint now reads `type`, `seats`, and `alang`, and passes them
  through: `create_subscription_checkout($subid, $userid, null, $lang, $subtype, $seats)`.

### `src/local/academy/classes/subscription_purchase_manager.php`
- **`get_my_subscriptions()`** now returns `type` for each purchase (so the front end can distinguish
  normal vs B2B). Uses `$r->type ?? 'normal'` for safety before the DB upgrade.

### `src/local/academy/lib.php` (front-page subscriptions section)
- **`subscribeB2b()`** now calls `create_subscription_checkout` (type=b2b, seats) and
  **redirects to `checkout_url`** — mirroring the normal `subscribe()`. (Was: `purchase_subscription`
  + reload.)
- **Active-plan state fix:** `activeSub` is now computed from **normal** subscriptions only, so a B2B
  purchase no longer marks the normal cards as "Subscribed".
- **B2B button "owned" state:** a new `B2B_OWNED` map (subscriptionid → true, built from the user's
  active B2B subscriptions). When the user already owns an active B2B sub for a plan, the button reads
  **"Manage business plan"** and links to `b2b_dashboard.php` instead of re-purchasing.
- Added `b2bdashurl` to the front-page CFG and `b2b_manage` to the string map.

### `src/local/academy/lang/en/local_academy.php` and `.../ar/local_academy.php`
- Added `hp_b2b_manage` in **both** languages ("Manage business plan" / "إدارة اشتراك الأعمال").

### Role lifecycle (B2B Administrator)
Moodle roles are **additive**, and the implicit "Authenticated user" role can never be removed — so a
buyer always shows at least "Authenticated user" + "B2B Administrator". The role is **not** a
replacement (see the trade-off discussion in the session); the gap that was fixed is that the role was
previously **never removed**.
- **`subscription_purchase_manager.php`** — new `unassign_b2b_admin_role_if_unused($userid)`: removes
  the `b2b_administrator` system role, but only if the user holds no other active (non-expired) B2B
  subscription. (Purchase still assigns the role additively via `assign_b2b_admin_role()`.)
- **`task/subscription_expiry_task.php`** — when a B2B parent expires, calls
  `unassign_b2b_admin_role_if_unused()` after expiring its members.
- **`subscription_purchase_manager::unsubscribe_user()`** — admin cancel of a B2B parent now also
  expires its approved members, revokes their access, and runs the same role cleanup.

---

## 4. How to verify the purchase type (DB)

```sql
SELECT id, userid, type, seats, base_price, discount_percent, price_paid, status
FROM mdl_academy_sub_purchases
ORDER BY id DESC LIMIT 5;
```
A B2B purchase has `type = 'b2b'` and a non-zero `seats`.

---

## 5. Where the B2B dashboard lives (UI)

`src/local/academy/b2b_dashboard.php` — the **B2B Administrator dashboard** (capacity stats,
invite-link generate/revoke, member approve/reject/remove).

Two ways in for an owner:
- **Top-right avatar menu** — the link is injected by `local_academy_extend_navigation_user_settings()`
  into the `useraccount` container (only for users with an active `type=b2b` purchase).
- **Front-page card** — after purchase, the "Business (B2B)" button becomes **"Manage business plan"**
  and links straight to the dashboard.

The nav link only appears after **caches are purged** (it is guarded by try/catch and silently hidden
if the DB upgrade hasn't run or the cache is stale).

---

## 6. Deployment steps (required — nothing is live yet)

1. Deploy the working-tree changes to the server.
2. Run the plugin DB upgrade if not already done (adds `type`/`seats`/etc. and the B2B tables):
   ```bash
   php admin/cli/upgrade.php --non-interactive
   ```
3. **Purge caches** (needed for the nav link + new strings):
   ```bash
   php admin/cli/purge_caches.php
   ```
4. Confirm a role with shortname **`b2b_administrator`** exists
   (*Site administration → Users → Permissions → Define roles*), or the role assignment is skipped
   silently on purchase.

---

## 7. End-to-end test checklist

1. Front page → **Business (B2B)** → pick capacity → **Proceed** → you land on the **Kashier payment
   page** (not an instant purchase).
2. Pay successfully → returning to the front page, the plan's `academy_sub_purchases` row has
   `type=b2b` + the chosen `seats`; the `b2b_administrator` role is assigned; you are enrolled.
3. The B2B button now reads **"Manage business plan"** and opens `b2b_dashboard.php`.
4. The **normal** subscription cards are **not** marked "Subscribed" just because you bought B2B.
5. Owning a B2B sub does **not** block buying a normal subscription, and vice-versa.
6. Check both **EN** and **AR** (`?alang=ar`) — the new "Manage business plan" label is localized.

---

## 9. Data cleanup after the PARAM_ALPHA bug

Because of the root-cause bug (§0), earlier "B2B" test purchases were written as **normal**
subscriptions (`type='normal'`, `seats=0`) and left the buyer with an active normal subscription that
blocks re-testing. Identify and remove them before re-testing:

```sql
-- Inspect recent purchases (look for ones that should have been B2B but show type='normal')
SELECT id, userid, subscriptionid, type, seats, price_paid, status, timecreated
FROM mdl_academy_sub_purchases
ORDER BY id DESC LIMIT 20;
```

For each bogus row, either cancel it via the admin "User subscriptions" screen, or in the DB set
`status='cancelled'` (and remove the matching `mdl_academy_sub_payments` row if you want a clean
ledger). No `b2b_administrator` role was assigned for these, so there is no role to unwind.

---

## 8. Notes / not done

- Changes are in the working tree only — **not committed, not pushed**.
- Live testing was not performed (no running Moodle/DB in this session); verification was static
  (`php -l` on all changed files).
- The direct `purchase_subscription` API path still exists — it is now the webhook's fulfilment
  function and the admin-assigned path, no longer the front-page buy path.
- Related memory: `b2b-subscriptions`, `subscriptions-feature`, `localization-en-ar`,
  `frontpage-guest-visibility`.
