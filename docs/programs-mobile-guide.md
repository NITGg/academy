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
| `free == 0`, `offer != null` | "Paid" + `offer.label` | `offer.original` struck through, then `offer.final` | **Buy** | Checkout (§5) |
| `free == 0`, no offer | "Paid" | `price` + `currency` | **Buy** | Checkout (§5) |

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
- Primary button: **Buy** → checkout (§5). For a free program: **Join** when `joinable == 1`,
  otherwise no button at all.
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

---

## 5. Buying a paid program

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

## 6. Certificates on the details screen (optional)

If the program has certificates configured, the owner view can show which ones the student qualifies
for — the web does this on `my/program.php`.

```
GET api.php?function=list_program_certificate_eligibility&programid=3&token=TOKEN
```

See [`api/certificate-eligibility.md`](api/certificate-eligibility.md) for the response shape. Only
call it when `owned == 1`; an empty `certificates` array means the program has none — render nothing.

Each certificate carries `eligible`, `operator` (`and` = must meet every requirement, `or` = any one
of them), and a `results` list — one entry per requirement. For each entry render:

- `passed` → a ✓ / ✗ marker
- **`description`** → what the student must do, e.g. *"Complete at least 90% of the program's
  courses"*. Fall back to `label` only when `description` is `''`. **Do not show `label` by
  default** — it is the rule *type* ("Program progress ≥ threshold %"), admin wording that leaves
  the student none the wiser.
- `actual` / `required` / `unit` → progress, shown when `unit` is non-empty or `required > 1`
  (e.g. `72 / 90 %`, `2 / 3`). A plain yes/no requirement has no useful number; the marker says it.

---

## 7. Build order

1. **البرامج** — `get_catalogue_programs`, cards per the §2 state machine, whole card tappable.
2. **Details screen** — `get_program_details`, both branches of `owned`.
3. **برامجي** — `get_my_programs`, reusing the same card widget with dates instead of price.
4. **Buy** — `create_program_checkout` + the shared Kashier WebView you already have.
5. Certificates panel, if the site uses them.

Steps 1–3 are read-only and need no payment plumbing, so they can ship on their own.

---

## Endpoint summary

| Function | Method | Params | Purpose |
|----------|--------|--------|---------|
| `get_catalogue_programs` | GET | — | البرامج list |
| `get_my_programs` | GET | — | برامجي list |
| `get_program_details` | GET | `programid` | Details screen (both views) |
| `create_program_checkout` | POST | `programid`, `coupon_code?`, `alang?` | Start a purchase |
| `preview_discount` | GET | `item_type=program`, `item_id`, `coupon_code?` | Price preview |
| `list_program_certificate_eligibility` | GET | `programid` | Certificates panel |
