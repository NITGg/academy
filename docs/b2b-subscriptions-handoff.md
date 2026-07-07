# B2B Subscriptions — Session Handoff Report

**Purpose:** hand this work off to another account/session so it can be finished and deployed.
**Plugin:** `local_academy` at `src/local/academy/` (Moodle 3.11, site: https://academy2026.nitg-eg.com).
**Status at end of session:** feature fully implemented in code; **DB upgrade must be run on the
server** (see §6). All 19 changed PHP files pass `php -l`; `install.xml` validates.

---

## 1. Objective

Add a **B2B (seat-based, multi-user) subscription** layer on top of the existing normal
subscriptions feature, plus:
- Admin can create/update subscriptions with **B2B seat options** (e.g. 10/20/50 seats), each with
  its own discount %, and a system-computed B2B price.
- The single lesson-settings page becomes a **3-tab "Admin Settings"** page (Lesson Deadlines /
  Financial / B2B Subscription Settings).
- A buyer of a B2B subscription becomes a **B2B Administrator**, can generate invite links, and
  approve/reject/remove invited users against a purchased seat capacity.
- Admin gets a **User Activity report**.

Specs (source of truth): `docs/specs/admin/US-AD-5-1`, `US-AD-5-2`, `US-AD-2-1`, and
`docs/specs/B2B Administrator/US-B2B-1-1 … 1-9`. Index: `docs/specs/README.md`.

## 2. Key decisions (confirmed with the product owner)

1. **Role:** use the **existing** Moodle role with shortname `b2b_administrator` (created via the
   Moodle role UI — *not* defined in plugin code). On a successful B2B purchase it is assigned at
   **system context** via `role_assign()`, looked up by shortname.
2. **Buyer access:** the B2B Administrator **also gets course access for themselves** via a separate
   `grant_course_access()` call, and **does not consume a seat** (capacity is for invited users).
3. **Seat accounting:** each membership stores its own `consumes_seat` flag so historical capacity is
   never recomputed from the current global seat-return setting.
4. **Specs folder:** literally `docs/specs/B2B Administrator/` (with a space).
5. **B2B purchase payment:** the front-page "Business (B2B)" button calls `purchase_subscription`
   directly (assumed-paid — same no-gateway convention the subscription code already uses). Routing
   B2B through the Kashier/`local_payments` gateway is **not done** (see §7).

## 3. Database changes

- **Version:** `version.php` bumped `2026070418 → 2026070419`.
- **Upgrade block:** `db/upgrade.php`, savepoint `2026070419`. Mirrored in `db/install.xml`.
- Alter `academy_subscriptions`: add `b2b_enabled` INT(1) default 0.
- Alter `academy_sub_purchases`: add `type` (normal|b2b), `seats`, `base_price`, `discount_percent`.
- New `academy_sub_seat_options` (subscriptionid, seats, discount_percent) — unique (subscriptionid, seats).
- New `academy_b2b_invitations` (purchaseid, b2b_admin_id, `token_hash` sha256, status
  active|expired|disabled|revoked, expires_at) — unique token_hash.
- New `academy_b2b_memberships` (purchaseid, subscriptionid, b2b_admin_id, userid, invitationid,
  status pending|approved|rejected|removed|expired, `consumes_seat`, reject_reason, approved_by/at,
  removed_by/at) — unique (purchaseid, userid).

## 4. Files changed / added (all under `src/local/academy/`)

**Backend**
- `db/upgrade.php`, `db/install.xml`, `version.php` — schema + version.
- `classes/settings_manager.php` — 2 new keys `b2b_auto_approve_invited_users`,
  `b2b_return_seat_after_user_removal`.
- `classes/subscription_manager.php` — `b2b_enabled` + `seat_options` in create/update, new
  `get_seat_options()`, `save_seat_options()`, `b2b_price()`; seat options included in `get_subscriptions()`.
- `classes/subscription_purchase_manager.php` — `purchase_subscription($userid,$subid,$method,$ref,$type,$seats)`;
  B2B branch computes price, sets role via new `assign_b2b_admin_role()`, grants buyer access; the
  "one active normal subscription" rule now scoped to `type=normal`; `format_plan()` exposes b2b fields.
- `classes/b2b_manager.php` — **NEW**: invitations (generate/list/revoke/validate), `join()`,
  `approve_membership()`, `reject_membership()`, `remove_member()`, `capacity_stats()`,
  `list_members()`, `get_my_b2b_subscriptions()`. Ownership enforced via `require_owned_purchase()`.
- `classes/notification_manager.php` — B2B methods (`b2b_purchase_confirmed`, `b2b_membership_pending`,
  `b2b_membership_approved`, `b2b_membership_rejected`, `b2b_member_removed`) + shared `b2b_send()`.
- `db/messages.php` — new provider `b2bnotification`.
- `classes/task/subscription_expiry_task.php` — expiring a B2B parent also expires its approved
  memberships and revokes their course access.
- `classes/report_manager.php` — `user_activity_report($userid,$filters)` (US-B2B-1-9).
- `api.php` — settings pass-through of B2B keys; `seat_options`/`b2b_enabled` on create/update
  (helper `academy_decode_seat_options()`); `purchase_subscription` accepts `type`/`seats`; new B2B
  endpoints: `get_my_b2b_subscriptions`, `get_b2b_dashboard`, `b2b_generate_invite`,
  `b2b_revoke_invite`, `b2b_approve_member`, `b2b_reject_member`, `b2b_remove_member`, `b2b_join`;
  `report_user_activity` (admin-gated).

**Frontend**
- `manage_settings.php` — rewritten as a **3-tab** page (reuses the `manage_reports.php` tab pattern).
- `manage_subscriptions.php` — "B2B purchase available" checkbox + dynamic seat-option editor with
  live price; B2B badge in the plans table.
- `lib.php` — front-page card "Business (B2B)" purchase modal (choose capacity → base/discount/final →
  buy); B2B dashboard nav link for owners; **both site-wide hooks now wrapped in try/catch** (see §6).
- `b2b_dashboard.php` — **NEW**: capacity stats, member tabs (pending/approved/rejected/removed) with
  approve/reject/remove, generate/revoke invite link.
- `b2b_join.php` — **NEW**: invitation landing page (validate → login/register → create membership).
- `manage_reports.php` — new "User Activity" tab.

**i18n**
- `lang/en/local_academy.php` and `lang/ar/local_academy.php` — all new keys added in both languages
  (`set_*`, `sub_*`, `hp_b2b_*`, `b2b_*`, `notif_b2b_*`, `rp_ua_*`, `err_*`, messageprovider).

## 5. How it works (quick reference)

- **B2B price:** `original = base_price × seats; discount = original × pct/100; final = original − discount`
  (`subscription_manager::b2b_price`).
- **Consumed seats:** `count(memberships where consumes_seat=1)`; **available = purchased − consumed**.
  Approve → `consumes_seat=1`; remove with seat-return **on** → 0, **off** → 1 (recorded per record).
- **Invites:** raw token in the link, only the **sha256 hash** is stored; the raw link is shown once
  (on generation). `b2b_join.php` sets `$SESSION->wantsurl` then sends guests to login.
- **Auto-approval:** `join()` auto-approves when `b2b_auto_approve_invited_users=1` and a seat is free;
  otherwise pending + notify the admin.

## 6. ⚠️ Current blocking issue — RUN THE DB UPGRADE

Symptom seen this session after deploy: **"Error reading from database"** on the front page.
Cause: new code references new columns/tables, but the **plugin DB upgrade had not run on the server**.

Mitigation already applied: `local_academy_before_footer()` and the nav hook in `lib.php` are now
wrapped in `try/catch`, so a pending upgrade no longer takes the whole site down.

**Required action on the server (Moodle web root):**
```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```
Or as an admin in the browser: **Site administration → Notifications**.

Prerequisite check: confirm a role with shortname **`b2b_administrator`** exists
(*Site administration → Users → Permissions → Define roles*). Without it, the purchase still succeeds
but no role is assigned (assignment is skipped silently).

## 7. Not done / next steps

1. **Deploy + run the upgrade** on the server (§6), then run the end-to-end verification below.
2. **Gateway payment for B2B** (optional): B2B buy currently uses the assumed-paid path. To charge via
   the gateway, extend `local_payments\manager::create_subscription_checkout()` to carry `type`+`seats`
   and call the B2B branch of `purchase_subscription` on webhook success.
3. **Live testing:** none of this was tested on a running Moodle (no local DB/server access this
   session). Verification was static only: `php -l` on all changed files + `install.xml` XML validation.
4. Consider making `subscription_manager::get_subscriptions()` degrade gracefully if the seat-options
   table is missing (extra safety beyond the hook guards).
5. Nothing was committed or pushed — the working tree holds all changes.

## 8. End-to-end verification checklist

1. Run the upgrade; confirm the 3 new tables + new columns exist.
2. `manage_settings.php`: switch all 3 tabs, save, reload → values persist; teacher%+platform% must total 100.
3. `manage_subscriptions.php`: create a B2B plan with seat options 10/20/50 @ 10/20/30%, base 100 →
   verify computed finals (10 seats = 900).
4. Front page → "Business (B2B)" → pick capacity → buy → confirm `b2b_administrator` role assigned,
   buyer enrolled, `academy_sub_purchases` row has type=b2b/seats/base_price/discount_percent/price_paid.
5. `b2b_dashboard.php`: generate link → open `b2b_join.php?t=…` as another user → pending (or
   auto-approved per setting) → approve → user enrolled, consumes_seat=1, capacity correct; reopen link
   → no duplicate membership.
6. Remove with seat-return **on** (available +1) then **off** (seat stays) → matches the mixed-policy
   example (10 cap, 5 approved + 1 removed-kept = 6 consumed, 4 available).
7. Backdate `expires_at`, run `subscription_expiry_task` → parent + memberships expire, access revoked.
8. `manage_reports.php` → "User Activity" tab → shows roles, subs, B2B memberships, actions; date filter works.
9. Check EN and AR (`?alang=ar`) on every new/edited page.

## 9. Conventions to keep

- API dispatch: `api.php?function=…&token=…&alang=en|ar`, JSON `{status,data|error}`; admin functions
  gated in `$capmap`, B2B self-service functions gated by ownership inside `b2b_manager`.
- Pages: vanilla HTML + `api.php` + WS token; localize via `local_academy_string_map()` +
  `window.ACADEMY_STR`; multilang plan name/description via `{mlang}`.
- Add every new string to **both** `lang/en` and `lang/ar`.
- The plugin lives at `src/local/academy/` (note the `src/` prefix).
- Related memory: `subscriptions-feature`, `b2b-subscriptions`, `localization-en-ar`,
  `frontpage-guest-visibility` (in the assistant memory index).
