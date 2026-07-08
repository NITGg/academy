# B2B Subscriptions — Session Handoff (Follow-up Fixes)

**Audience:** the next AI/session picking up this work.
**Plugin(s):** `local_academy` and `local_payments`, under `src/local/`.
**Moodle:** 3.11 · **Site:** https://academy2026.nitg-eg.com · **Local PHP for `php -l`:** `C:\MAMP\bin\php\php7.2.18\php.exe` (not on PATH).
**Status:** all changes below are in the working tree, `php -l`-clean, **not committed, not deployed.**

This session continued the B2B feature that an earlier session built (that original report was the
previous content of this file). It fixed the reasons B2B "didn't work", moved B2B onto the real
payment gateway, and polished the admin UX. A companion doc with the payment details lives at
`docs/api/b2b-kashier-payment-session.md`.

---

## 0. THE ROOT-CAUSE BUG (most important)

**Symptom:** buying "Business (B2B)" recorded a *normal* subscription — no B2B role, no dashboard link,
and "لديك بالفعل اشتراك نشط" (already-have-subscription) on retry.

**Cause:** the `type` param (`'normal'|'b2b'`) was read with **`PARAM_ALPHA`**, which strips digits, so
`"b2b"` became `"bb"`. Every `$isb2b = ($type === 'b2b')` check was therefore always false.

**Fix:** `PARAM_ALPHANUM` for `type` in both `purchase_subscription` and `create_subscription_checkout`
endpoints in `src/local/academy/api.php`.

⚠️ **Data cleanup:** B2B test purchases made before this fix are `type='normal'` rows in
`mdl_academy_sub_purchases` and will block re-testing (an active normal sub). Cancel them (admin "User
subscriptions" screen, or set `status='cancelled'` in the DB).

---

## 1. B2B now uses the same Kashier flow as normal subscriptions

Previously the B2B button used an assumed-paid shortcut (`purchase_subscription` direct). Now:

- **Front end** (`local/academy/lib.php`): the "Business (B2B)" button calls
  `create_subscription_checkout` with `type=b2b, seats=N` and redirects to Kashier — identical shape to
  the normal `subscribe()`.
- **Checkout** (`local/payments/classes/manager.php` → `create_subscription_checkout`): gained
  `string $type='normal', int $seats=0`. For B2B it validates `b2b_enabled` + the seat option, computes
  the price via `subscription_manager::b2b_price()`, charges that amount, and stores
  `sub_type`+`seats` in the transaction metadata. The "one active subscription" guard is now scoped to
  `type='normal'`, so B2B and normal never block each other.
- **Webhook fulfilment** (both the async webhook path and the sync callback/verify path in the same
  file): forward `$meta->sub_type` + `$meta->seats` into `purchase_subscription(...)`, which creates the
  `type='b2b'` purchase, assigns the `b2b_administrator` role, and grants the buyer course access.
- **API** (`api.php`): `create_subscription_checkout` endpoint reads/forwards `type`/`seats`/`alang`.

`get_my_subscriptions()` now returns `type`; the front page computes the "already subscribed" state
from **normal** subs only, so a B2B purchase no longer marks normal cards as "Subscribed".

---

## 2. Front-page B2B button "owned" state

`local/academy/lib.php`: after a user owns an active B2B sub for a plan, the button flips from
**"Business (B2B)"** to **"Manage business plan"** and links to `b2b_dashboard.php` (new `hp_b2b_manage`
string EN+AR; `B2B_OWNED` map built from the user's active B2B subs; `b2bdashurl` added to page CFG).

---

## 3. B2B Administrator role lifecycle

Moodle roles are **additive** and "Authenticated user" can't be removed, so the role is NOT a
replacement (confirmed decision — replacing would strip other system roles unrecoverably). The gap that
was fixed: the role was **never removed**. New:
`subscription_purchase_manager::unassign_b2b_admin_role_if_unused($userid)` removes the role only when
the user holds no other active B2B sub. Called from `subscription_expiry_task` (on B2B expiry) and
`unsubscribe_user` (admin cancel — which now also expires the parent's approved members + revokes their
access).

---

## 4. Invite landing page: login AND register

`local/academy/b2b_join.php`: a guest opening an invite link no longer auto-redirects to the login
form. It now shows a landing page with **Log in** and **Create new account** buttons (register button
only when `signup_is_enabled()`). Both preserve `$SESSION->wantsurl` so the user returns to the invite
and the membership is created. New strings `b2b_join_guest_intro / _loginbtn / _registerbtn` (EN+AR).

### 4b. Custom signup.php broke the register path (fixed)

`src/login/signup.php` is customized: after `complete_user_login()` it hard-redirected to the home page
(`/?id=14&lang=ar`), **ignoring `wantsurl`** — so a newly-registered invitee never returned to the
invite and no membership was created. Fixed: if `$SESSION->wantsurl` points at
`/local/academy/b2b_join.php`, redirect there instead (scoped narrowly so all other registrations keep
the default landing page). Uses `json_encode()` for the JS redirect target.

---

## 5. Dashboard action UX — styled modals

`local/academy/b2b_dashboard.php`: replaced native `window.confirm()`/`prompt()` with styled modals
matching the front-page dialog design:
- **Reject** → modal with an optional reason textarea (warn/orange).
- **Remove** → confirm modal (danger/red), personalised with the member name.
- **Revoke invite** → confirm modal (previously a silent one-click action).
Dismiss via Cancel / Esc / backdrop. New strings `b2b_confirm_reject_title/body`,
`b2b_confirm_remove_title`, `b2b_confirm_revoke_title/body`, and `b2b_confirm_remove` now takes a
`{name}` placeholder (EN+AR).

---

## 6. View + copy the existing invitation link (schema change)

Previously only the sha256 hash was stored, so an existing active link could never be re-displayed —
only regenerated. Now the raw token is persisted (owner-only) so the active link is shown with a copy
button.

- **Schema:** `version.php` bumped `2026070419 → 2026070420`. New upgrade step adds a nullable `token`
  CHAR(64) column to `academy_b2b_invitations` (mirrored in `db/install.xml`).
- **`b2b_manager.php`:** `generate_invitation()` stores `$rec->token`; `list_invitations()` returns a
  `url` (built from the token) **only** for a still-active link.
- **`b2b_dashboard.php`:** `renderInvites()` shows the link in a styled box with a **copy** button
  (Clipboard API + `execCommand` fallback, "Copied!" feedback) and revoke; the freshly-generated link
  reuses the same box. Links created before this upgrade have no stored token → fall back to the old
  "link active" + revoke display.

---

## 6b. Re-join behaviour for the invitation link

`b2b_manager::join()` used to be fully idempotent — a **removed** user reopening the link just saw
"You were removed from this subscription." Now:
- **approved / pending / rejected** members: no duplicate is created; `join()` returns the current
  status plus `existing => true`, and `b2b_join.php` shows an **"already a member / already pending"**
  message (`b2b_join_already_approved` / `b2b_join_already_pending`, EN+AR; rejected keeps its message).
- **removed / expired** members: the existing membership row is **reset to pending** (a fresh request;
  `(purchaseid,userid)` is unique so it can't be a new row), the admin is notified, and auto-approve
  applies if enabled + a seat is free.

---

## 7. DEPLOY / RUN (required — nothing is live)

```bash
php admin/cli/upgrade.php --non-interactive   # applies savepoint 2026070420 (token column)
php admin/cli/purge_caches.php                # required for new strings, nav link, JS/CSS
```

- Confirm a role with shortname **`b2b_administrator`** exists (Site admin → Users → Permissions →
  Define roles), else the role assignment is silently skipped on purchase.
- For local testing without Kashier (it can't redirect to localhost): hit the existing assumed-paid
  `purchase_subscription` endpoint directly (POST `function, token, subscriptionid, type=b2b, seats`).
  A CFG `assumePaid` dev-toggle was prototyped then reverted this session; it is NOT in the tree.

---

## 8. Files touched this session

- `src/local/academy/api.php` — PARAM_ALPHANUM for `type`; checkout endpoint forwards `type/seats/alang`.
- `src/local/payments/classes/manager.php` — B2B in `create_subscription_checkout`; webhook fulfilment forwards `sub_type/seats`.
- `src/local/academy/lib.php` — B2B → checkout; normal-only active state; "Manage business plan" button.
- `src/local/academy/student.php` — (only touched by the reverted assumePaid work; currently back to original).
- `src/local/academy/classes/subscription_purchase_manager.php` — `type` in `get_my_subscriptions`; role cleanup helper; B2B handling in `unsubscribe_user`.
- `src/local/academy/classes/task/subscription_expiry_task.php` — role cleanup on B2B expiry.
- `src/local/academy/classes/b2b_manager.php` — store raw token; return `url` for active links.
- `src/local/academy/b2b_join.php` — login+register landing.
- `src/login/signup.php` — honour B2B `wantsurl` after registration.
- `src/local/academy/b2b_dashboard.php` — styled confirm/reject modals; view+copy invite link.
- `src/local/academy/version.php`, `db/upgrade.php`, `db/install.xml` — `token` column (v2026070420).
- `src/local/academy/lang/en|ar/local_academy.php` — all new strings in BOTH languages.

## 9. Conventions (unchanged)

- API: `api.php?function=…&token=…&alang=en|ar`, JSON `{status,data|error}`; admin funcs gated in
  `$capmap`, B2B self-service gated by ownership in `b2b_manager`.
- Add every new string to **both** `lang/en` and `lang/ar`. Localise pages via
  `local_academy_string_map()` + `window.ACADEMY_STR`.
- Related assistant memory: `b2b-subscriptions`, `subscriptions-feature`, `localization-en-ar`,
  `frontpage-guest-visibility`.
