# Subscriptions API — Admin/Frontend Developer Guide

Admin-only endpoints to manage course-access **subscription plans** and per-course subscription
availability.
Implements [US-AD-5-1](../specs/admin/US-AD-5-1-create-subscription-plan.md) …
[US-AD-5-4](../specs/admin/US-AD-5-4-delete-unused-subscription-plan.md) and
[US-AD-6-1](../specs/admin/US-AD-6-1-set-course-subscription-availability.md).

- **Plugin:** `local_academy` · tables `mdl_academy_subscriptions`, `mdl_academy_course_access`,
  `mdl_academy_sub_purchases`, `mdl_academy_sub_payments`
- **Audience:** the admin web dashboard.
- **In-site UI:** Site administration → Plugins → Local plugins → **Manage subscriptions**
  (`/local/academy/manage_subscriptions.php`).

> A subscription is a **separate** access mechanism from Flex packages: it grants time-boxed access to
> a set of Moodle **courses** (real enrolment). Its full price is platform revenue — it is **not**
> split with teachers.

---

## 1. Endpoint & auth

```
{BASE_URL}/local/academy/api.php        (function chosen by the `function` param)
```
Get a token by exchanging admin credentials, then send it as `token` on every call:
```
POST {BASE_URL}/login/token.php    body: username=admin&password=PASSWORD&service=moodle_mobile_app
→ { "token": "abc123..." }
```
⚠️ **Admin-only.** The token's user needs the `local/academy:managesubscriptions` capability
(site admin / manager). Otherwise → `{"status":"fail","error":"Permission denied"}`.

Reads are `GET`; **state-changing actions require `POST`** (a GET returns
`{"status":"fail","error":"This action requires POST"}`). Response is always JSON:
`{ "status":"success", "data":… }` or `{ "status":"fail", "error":"…" }`. An invalid/expired token
returns an **HTML** page — treat any non-JSON body as "re-login".

---

## 2. Plan functions (US-AD-5-*)

| Action | `function` | Method | Required | Optional | Story |
|--------|-----------|--------|----------|----------|-------|
| Create | `create_subscription` | POST | `name`, `price`, `duration_days` | `description`, `active` (default `1`) | US-AD-5-1 |
| Update | `update_subscription` | POST | `id` | any of `name`, `description`, `price`, `duration_days`, `status` | US-AD-5-2 |
| Deactivate | `deactivate_subscription` | POST | `id` | — | US-AD-5-3 |
| Reactivate | `activate_subscription` | POST | `id` | — | US-AD-5-3 |
| Delete | `delete_subscription` | POST | `id` | — | US-AD-5-4 |
| List | `get_subscriptions` | GET | — | `status` = `active` \| `inactive` (omit = all) | helper |
| Get one | `get_subscription` | GET | `id` | — | helper |

### Subscription object
| Field | Type | Notes |
|-------|------|-------|
| `id` | int | plan id |
| `name` | string | |
| `description` | string | |
| `price` | decimal string | e.g. `"365.00"` (EGP) — string in JSON. Must be ≥ 0 |
| `duration_days` | int | access length in days. Must be > 0 |
| `status` | string | `active` or `inactive` |
| `courses` | array | `[{ "id", "fullname" }]` — courses this plan unlocks (from the access map) |
| `timecreated` / `timemodified` | int | unix seconds |

```bash
B=http://localhost:8081; T=YOUR_TOKEN
# Create
curl -X POST "$B/local/academy/api.php" \
  -d "function=create_subscription&token=$T&name=365-day&price=365&duration_days=365&active=1"
# List active
curl "$B/local/academy/api.php?function=get_subscriptions&token=$T&status=active"
# Update (send only what changed)
curl -X POST "$B/local/academy/api.php" -d "function=update_subscription&token=$T&id=1&price=400"
# Deactivate / delete
curl -X POST "$B/local/academy/api.php" -d "function=deactivate_subscription&token=$T&id=1"
curl -X POST "$B/local/academy/api.php" -d "function=delete_subscription&token=$T&id=1"
```

---

## 3. Course access functions (US-AD-6-1)

Choose which subscriptions can access a course. `subscriptionids` is a **JSON array** (only used when
`mode=specific`).

| Action | `function` | Method | Required | Notes |
|--------|-----------|--------|----------|-------|
| Set course access | `set_course_subscriptions` | POST | `courseid`, `mode` (`all` \| `specific`) | `subscriptionids` JSON array when `mode=specific` |
| Read course access | `get_course_access` | GET | `courseid` | — |

- `mode=all` → **every** subscription unlocks the course.
- `mode=specific` → only the listed subscriptions unlock it.
- Saving **replaces** the course's previous rule; changes apply immediately.

`get_course_access` returns:
```json
{ "status": "success", "data": {
  "courseid": 12, "mode": "specific", "subscriptionids": [1, 3] } }
```
`mode` is `all`, `specific`, or `none` (no rule set → the course is not unlocked by any subscription).

```bash
# English course → only the 365-day plan (id 1)
curl -X POST "$B/local/academy/api.php" \
  -d "function=set_course_subscriptions&token=$T&courseid=12&mode=specific&subscriptionids=[1]"
# Arabic course → all subscriptions
curl -X POST "$B/local/academy/api.php" \
  -d "function=set_course_subscriptions&token=$T&courseid=13&mode=all"
# Read it back
curl "$B/local/academy/api.php?function=get_course_access&token=$T&courseid=12"
```

---

## 4. Behaviour the UI must reflect

- **Edits apply to future purchases only.** `update_subscription` changes only the fields you send;
  students who already bought keep their original **price** and **expiration date** (a snapshot is
  stored at purchase time).
- **Purchased plans can't be deleted — only deactivated.** `delete_subscription` succeeds only when the
  plan was never purchased (no purchase/payment rows). Otherwise it returns
  *"This subscription has purchase records and cannot be deleted. Deactivate it instead."* → offer
  Deactivate.
- **Deactivating keeps existing student subscriptions active** until they expire; it only removes the
  plan from the student browse list. Admin can reactivate later.
- **Access is real enrolment.** On purchase the student is enrolled (manual enrolment, student role)
  into the plan's courses, with the enrolment ending at the subscription's expiry. A daily task expires
  overdue subscriptions and unenrols from any course no other active subscription of theirs still
  grants. Changing a course's access map affects **future** purchases (existing enrolments are not
  retroactively added/removed).

---

## 5. Errors to handle
| `error` | Cause | UI hint |
|---------|-------|---------|
| `Permission denied` | token user lacks `managesubscriptions` | show "no access" |
| `Subscription name is required` | empty `name` | inline validation |
| `Number of days must be greater than zero` | `duration_days` ≤ 0 | inline validation |
| `Price cannot be negative` | `price` < 0 | inline validation |
| `Subscription not found` | bad `id` | refresh list |
| `This subscription has purchase records and cannot be deleted. Deactivate it instead.` | delete blocked | offer Deactivate |
| `Course not found` | bad `courseid` | — |
| `This action requires POST` | mutation sent as GET | use POST |

---

## 6. Rules enforced server-side
- `price` ≥ 0; `duration_days` > 0; `status` ∈ {`active`,`inactive`}.
- `delete_subscription` only when no purchase/payment references the plan.
- `subscriptionid = 0` in `academy_course_access` is the sentinel for "all subscriptions".

See the **student** side (browse / buy / my subscriptions) in
[`subscriptions-mobile-guide.md`](subscriptions-mobile-guide.md). Postman:
[`Academy_Subscriptions.postman_collection.json`](Academy_Subscriptions.postman_collection.json).
