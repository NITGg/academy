# Programs — Mobile Guide (البرامج / برامجي / تفاصيل البرنامج)

How to build the three program screens the web site already has:

| Web | Arabic label | Mobile screen |
|-----|--------------|---------------|
| Front-page "Programs" section | **البرامج** | Programs catalogue (list) |
| Front-page "My programs" section | **برامجي** | My programs (list) |
| `/enrol/programs/catalogue/program.php?id=3` | — | **Program details** (shopper view) |
| `/enrol/programs/my/program.php?id=3` | — | **Program details** (owner view) |

> **The two detail pages are one mobile screen.** The web splits them because Moodle redirects
> between two URLs depending on whether you own the program. The API does not: `get_program_details`
> returns both shapes and tells you which to render via the `owned` flag. Build **one** screen.

A "program" (`enrol_programs`) is a curriculum — an ordered tree of sets and courses that a student
works through. It is **not** a Flex package and **not** a subscription; those are separate products
with their own guides. A program can be free or paid; paid programs are bought with the same Kashier
checkout as everything else.

---

## 1. Conventions

Same dispatcher, auth, and envelope as the rest of the Academy API — see
[`api/README.md`](api/README.md) for the base URL and how to get a token.

```
GET|POST  {WWWROOT}/local/academy/api.php?function=NAME&token=TOKEN
```

```jsonc
{ "status": "success", "data": … }        // ok
{ "status": "fail",    "error": "msg" }   // error — always show `error` to the user
```

Reads are GET. Add `&alang=ar` or `&alang=en` to get program names and system messages in that
language (**`alang`**, not `lang` — `lang` would clobber the user's session language).

All four endpoints below need only a **valid token** — no special capability.

---

## 2. Screen 1 — البرامج (catalogue list)

```
GET  api.php?function=get_catalogue_programs&token=TOKEN
```

Returns every program this user is allowed to see, ordered by name — identical to the front-page
section. Visibility is the plugin's own rule: the program is not archived **and** (it is public, **or**
the user is already allocated, **or** the user is in one of its cohorts).

```jsonc
{
  "status": "success",
  "data": [
    {
      "id": 3,
      "name": "Full Stack Diploma",
      "description": "Nine months, twelve courses…",   // plain text, ≤180 chars — for the card
      "free": 0,                                       // 1 = free program, 0 = paid
      "price": 4500.0,                                 // 0.0 when free
      "currency": "EGP",
      "offer": {                                       // null when there is no active offer
        "label": "-25%",
        "original": 4500.0,
        "final": 3375.0
      },
      "owned": 1,                                      // user already has it
      "joinable": 0                                    // free program the user can self-enrol into
    }
  ]
}
```

### Card state machine

Read `owned` → `free` → `offer` in that order. This is exactly what the web cards do:

| Condition | Badge | Price | Button | Tap action |
|-----------|-------|-------|--------|-----------|
| `owned == 1` | "Enrolled" | — | **Open** | Details screen |
| `free == 1` and `joinable == 1` | "Free" | Free | **Join** | Details screen |
| `free == 1` and `joinable == 0` | "Free" | Free | **View** | Details screen |
| `free == 0`, `offer != null` | "Paid" + `offer.label` | `offer.original` struck through, then `offer.final` | **Buy** | Checkout (§6) |
| `free == 0`, no offer | "Paid" | `price` + `currency` | **Buy** | Checkout (§6) |

**The whole card must be tappable, not just the button.** Tapping anywhere on the card opens the
details screen so the user can decide before buying; only the Buy button goes straight to checkout.

> `joinable == 0` on a free program means the site admin has not opened self-enrolment — there is
> currently **no way in**. Show the program, but label the button "View", not "Join". Do not promise
> a signup that will fail.

---

## 3. Screen 2 — برامجي (my programs)

```
GET  api.php?function=get_my_programs&token=TOKEN
```

The programs this user is allocated to, newest allocation first. Archived allocations and archived
programs are excluded, so an empty array means "hide the section entirely" — that is what the web
does.

```jsonc
{
  "status": "success",
  "data": [
    {
      "id": 3,
      "name": "Full Stack Diploma",
      "description": "Nine months, twelve courses…",
      "timeallocated": 1751000000,   // when they got it
      "timestart":     1751000000,   // program start
      "timedue":       1759000000,   // deadline, 0 = not set
      "timeend":       1767000000,   // hard end, 0 = not set
      "timecompleted": 0,            // 0 = not finished
      "completed": 0
    }
  ]
}
```

All times are **Unix seconds, or `0` meaning "not set"** — render `0` as "Not set", never as
1 Jan 1970. Show Started / Due, and then Completed if `completed == 1`, otherwise Ends.

Tapping a card opens the details screen.

---

## 4. Screen 3 — Program details

```
GET  api.php?function=get_program_details&programid=3&token=TOKEN
```

One call for both audiences. `owned` decides what you render.

```jsonc
{
  "status": "success",
  "data": {
    "id": 3,
    "name": "Full Stack Diploma",
    "description": "Nine months, twelve courses…",        // plain text, for a collapsed header
    "description_html": "<p>Nine months…</p>",            // full description — render as HTML
    "image": "https://…/pluginfile.php/…/banner.png",     // "" when the program has none
    "free": 0,
    "price": 4500.0,
    "currency": "EGP",
    "offer": { "label": "-25%", "original": 4500.0, "final": 3375.0 },
    "owned": 1,
    "joinable": 0,

    "allocation": {              // null unless owned == 1
      "timeallocated": 1751000000,
      "timestart":     1751000000,
      "timedue":       1759000000,
      "timeend":       0,
      "timecompleted": 0,
      "completed": 0
    },

    "content": [                 // the curriculum, nested
      {
        "itemid": 22,
        "type": "set",           // "set" | "course"
        "name": "Stage 1 — Foundations",
        "courseid": 0,           // 0 for a set
        "sequencetype": "All in order",   // human-readable completion rule; "" for a course
        "timecompleted": 0,
        "completed": 0,
        "children": [
          {
            "itemid": 23,
            "type": "course",
            "name": "HTML & CSS",
            "courseid": 41,      // open the course with this id
            "sequencetype": "",
            "timecompleted": 1752000000,
            "completed": 1,
            "children": []
          }
        ]
      }
    ]
  }
}
```

### Rendering

**Always:** image (if any), name, `description_html`, and the `content` tree.

`content` is a recursive tree — render it as an expandable outline, indenting one level per depth.
A **set** is a grouping with a completion rule (show `sequencetype` as a subtitle: *"All in order"*,
*"At least 2 of 5"*). A **course** is a leaf.

**When `owned == 0`** (shopper) — this is the "decide whether to buy" view:

- Price block: `offer.original` struck through + `offer.final` when `offer != null`, else `price`.
- Primary button: **Buy** → checkout (§6). For a free program: **Join** → `join_program` (§5) when
  `joinable == 1`, otherwise no button at all (label it **View**, never a Join that will fail).
- The `content` tree is the selling point — show it in full, but **do not link the courses**. The
  user cannot open them yet. `timecompleted` / `completed` are always `0` here; hide those columns.

**When `owned == 1`** (owner) — the progress view:

- No price, no Buy button.
- Allocation panel from `allocation`: Status (Completed / In progress), Allocated, Start, Due, End,
  Completion date. Again, `0` renders as "Not set".
- The `content` tree becomes the progress list: show each item's completion date when
  `completed == 1`, and make every `type == "course"` row **tap-to-open** using `courseid`.

### Errors

`{"status":"fail","error":"Program not found"}` covers all three of: no such program, archived, and
*not visible to you*. This is deliberate — it does not leak the existence of a program the user is
not allowed to see. Show a generic "This program is not available" and pop back to the list.

> **Non-JSON responses are a *different* failure — do not treat them as "not available."** Every
> endpoint in this guide is contracted to return the `{status, …}` JSON envelope. If a response does
> **not** parse as JSON (an HTTP 5xx, or a Moodle HTML error page instead of a body), that is a
> **server error, not a program-state error**. Distinguish the two: on a real `{"status":"fail"}`
> show "This program is not available" and go back; on a parse failure / non-2xx show a transient
> **"Something went wrong, try again"** with a retry, and log the raw body so it reaches the backend.
> Collapsing both into the same "غير متاح" message is what hid the `file_rewrite_pluginfile_urls`
> fatal on program 1 for a full debugging cycle — the screen said "unavailable" when the server was
> actually 500-ing. This rule applies to **all** endpoints below, not just this one.

---

## 5. Joining a free program (the Join button)

When a program is **free** and **joinable** (`free == 1 && joinable == 1`), the details-screen primary
button is **Join**, and this is the call behind it — the free counterpart of §6's checkout. There is
**no payment, no WebView, no `local_payments_verify_payment` step**: one POST allocates the user
straight away.

```
POST api.php?function=join_program&token=TOKEN
     programid=1
```

```jsonc
{
  "status": "success",
  "data": {
    "programid":     1,
    "allocationid":  57,
    "timeallocated": 1751000000,
    "owned":         1            // always 1 on success — the user now owns the program
  }
}
```

On success the user **owns** the program. Do exactly what a completed purchase does: re-fetch
`get_program_details` (or `get_my_programs`) and switch the screen to the **owner** view — no restart,
no second confirmation.

**Idempotent.** If the user is already allocated (double-tap, a stale card, an admin added them), the
call still returns `success` with their existing `allocationid` — treat it as a normal join, not an
error.

### Errors worth handling by name

| `error` | Meaning | Do |
|---------|---------|----|
| `Program not found` | gone, archived, or not visible | Back to list, refresh |
| `This program is paid — use checkout to buy it` | `join_program` on a priced program | Bug in the app — use the Buy path (§6), not Join |
| `This program is not open for self-enrolment` | `joinable` was `0`, or the admin closed self-signup meanwhile | Re-fetch details; show the program as **View**-only, hide the Join button |

`This program is not open for self-enrolment` is the same state the catalogue reports as
`joinable == 0`. If you only ever show the **Join** button when `joinable == 1`, a user should hit it
only when self-signup was closed between loading the card and tapping Join — so on this error just
re-fetch and the button will correctly become **View**.

> **Why there is a separate call at all.** A free program is *not* auto-joined just because it is free
> — a student only becomes a member when they deliberately enrol, exactly like the web's **Enrol**
> button. `join_program` is that deliberate step; without it a "free" program can be browsed but never
> entered, which is the bug this fixes.

---

## 6. Buying a paid program

Identical to the package / subscription flow — see
[`packages-subscriptions-kashier-mobile-guide.md`](packages-subscriptions-kashier-mobile-guide.md)
for the full Kashier walkthrough, WebView handling, and edge cases. Only the create call differs:

```
POST api.php?function=create_program_checkout&token=TOKEN
     programid=3
     coupon_code=SUMMER25     (optional)
     alang=ar                 (optional — language of the Kashier payment page)
```

```jsonc
{
  "status": "success",
  "data": {
    "order_id": "ORD-…",
    "checkout_url": "https://checkout.kashier.io/?…",
    "expires_at": 1751003600,
    "provider": "kashier",
    "transaction_id": 8842
  }
}
```

Then, exactly as for packages:

```
1. POST create_program_checkout        → checkout_url
2. Open checkout_url in a WebView      → user pays
   (a server-to-server webhook allocates the program — this is the source of truth)
3. WebView is redirected to /local/payments/callback.php?order_id=…&paymentStatus=SUCCESS|FAILED
   → read paymentStatus, close the WebView
4. POST /webservice/rest/server.php  wsfunction=local_payments_verify_payment  (real Moodle WS)
   → { success, status }
5. success → refresh with get_my_programs / get_program_details
```

**Optional — show the discount before paying:**

```
GET api.php?function=preview_discount&item_type=program&item_id=3&coupon_code=SUMMER25&token=TOKEN
```

Same endpoint and response shape as packages and subscriptions; see
[`api/coupons-offers-mobile-guide.md`](api/coupons-offers-mobile-guide.md).

### Errors worth handling by name

| `error` | Meaning | Do |
|---------|---------|----|
| `Program not found` | gone, archived, or not visible | Back to list, refresh |
| `This program has no price set` | `create_program_checkout` on a free program | Bug — use the Join path |
| `You already have access to this program` | duplicate purchase | Refresh; switch to the owner view |
| `This program is archived` | retired mid-flow | Back to list, refresh |

After **any** `fail` from checkout, re-fetch `get_program_details` before retrying — the state that
caused it (someone else allocated the user, price changed, program archived) is now in the response.

---

## 7. Certificates on the details screen (optional)

If the program has certificates configured, the owner view can show which ones the student qualifies
for — the web does this on `my/program.php`.

```
GET api.php?function=list_program_certificate_eligibility&programid=3&token=TOKEN
```

See [`api/certificate-eligibility.md`](api/certificate-eligibility.md) for the response shape. An
empty `certificates` array means the program has none — render nothing.

**Call it in both views, not just for owners.** A certificate is part of what the program is selling,
so a shopper needs to see it and its requirements *before* deciding to buy — that is exactly when the
information is useful. The endpoint does not require ownership. The web does the same on both program
pages.

Each certificate carries `eligible`, `operator` (`and` = must meet every requirement, `or` = any one
of them), a `results` list — one entry per requirement — and **`openable`** (see below). For
each requirement entry render:

- **`description`** → what the student must do, e.g. *"Complete at least 90% of the program's
  courses"*. Fall back to `label` only when `description` is `''`. **Do not show `label` by
  default** — it is the rule *type* ("Program progress ≥ threshold %"), admin wording that leaves
  the student none the wiser. Shown in **both** views; it is the whole point of the card.
- `passed` → a ✓ / ✗ marker. **Owner view only.**
- `actual` / `required` / `unit` → progress, e.g. `72 / 90 %`, `2 / 3`. **Owner view only**, and only
  when `unit` is non-empty or `required > 1`; a plain yes/no requirement has no useful number.

In the **shopper view** drop the marks and the numbers entirely and render the requirements as a
neutral bulleted checklist: the student has not started, so every line would read ✗ `0 / 90 %` —
accurate, but it looks like rejection rather than an invitation. Show the certificate name with an
"Included" badge and a line like *"Join this program to start working towards this certificate."*
Never link to the certificate itself for a non-owner.

### Opening the certificate — `openable` + `open_certificate`

A certificate lives on a Moodle **web** page (`/mod/customcert/view.php`) that needs a logged-in
browser session — your API **token alone cannot authenticate it**, so opening that URL directly in a
WebView just lands on the Moodle login page. The backend solves this for you with a two-step flow, so
the app needs **no token handling and no auto-login code of its own**:

**Step 1 — the list tells you which certificates can be opened.** Each certificate report carries
`openable`:

```jsonc
{
  "certificateid": 12,
  "name": "Full Stack Diploma — Certificate of Completion",
  "eligible": true,
  "operator": "and",
  "externalref": 2042,   // the linked customcert cmid (info only — you don't need it)
  "openable": true,      // ← show an "Open certificate" button only when this is true
  "results": [ … ]
}
```

`openable` is `true` only when the student is **eligible** *and* the certificate is linked to a real
activity. When it is `false`, show no open button — just the requirements. (On the same call the
server has already enrolled an eligible student into the certificate's host course, so step 2 will
resolve instead of hitting an access-denied page.)

**Step 2 — mint a self-authenticating link at the moment of the tap.** When the user taps **"Open
certificate" / "عرض الشهادة"**, call:

```
POST api.php?function=open_certificate&token=TOKEN
     certificateid=12
```

```jsonc
{
  "status": "success",
  "data": {
    "url": "https://…/local/academy/autologin.php?key=ab12…&cmid=2042"
  }
}
```

Open `data.url` in a **plain WebView** — nothing else. That URL logs the user in with a **single-use,
~2-minute, IP-locked key** and redirects straight to the certificate page, where mod_customcert's own
download / verify controls take over. Do **not** cache it, prepend a token, or try to render the
certificate yourself.

- **Mint it on the tap, not ahead of time.** The link is single-use and expires in ~2 minutes, so
  request it when the user actually taps Open — never store it from an earlier screen.
- **Errors** (`status: "fail"`): `You have not met the requirements for this certificate yet.`
  (`eligible` flipped to false since the list was loaded — re-fetch the list) or `This certificate is
  not available to open yet.` (no linked activity — `openable` should have been false; hide the
  button).

> **Why a second call instead of a URL in the list?** The link must be fresh (single-use, short-lived)
> to be safe, and a URL baked into the list would already be stale or spent by the time the user taps.
> Minting it on demand keeps every open working on the first try.

- **`externalref`** is the raw customcert `cmid`. You never need it — `open_certificate` handles the
  mapping. `externalref == 0` means no activity is linked yet, so `openable` is always `false` there.

---

## 8. Build order

1. **البرامج** — `get_catalogue_programs`, cards per the §2 state machine, whole card tappable.
2. **Details screen** — `get_program_details`, both branches of `owned`.
3. **برامجي** — `get_my_programs`, reusing the same card widget with dates instead of price.
4. **Join** — `join_program` for free programs (one POST, no payment), then switch to the owner view.
5. **Buy** — `create_program_checkout` + the shared Kashier WebView you already have.
6. Certificates panel, if the site uses them — show **Open certificate** when `openable`, and fetch
   the link from `open_certificate` on tap.

Steps 1–4 are read-only-plus-join and need no payment plumbing, so they can ship before Buy.

---

## Endpoint summary

| Function | Method | Params | Purpose |
|----------|--------|--------|---------|
| `get_catalogue_programs` | GET | — | البرامج list |
| `get_my_programs` | GET | — | برامجي list |
| `get_program_details` | GET | `programid` | Details screen (both views) |
| `join_program` | POST | `programid` | Self-enrol into a **free** program (the Join button) |
| `create_program_checkout` | POST | `programid`, `coupon_code?`, `alang?` | Start a purchase |
| `preview_discount` | GET | `item_type=program`, `item_id`, `coupon_code?` | Price preview |
| `list_program_certificate_eligibility` | GET | `programid` | Certificates panel (each cert has `openable`) |
| `open_certificate` | POST | `certificateid` | Fresh single-use auto-login URL that opens the certificate |
