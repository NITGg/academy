# Refunds

Two routes, and which one applies is decided by the policy, not by the buyer:

| Situation | What happens |
|---|---|
| Inside the refund window | The buyer refunds themselves. Money goes back at once, access is removed, no queue. |
| Outside it, or window = 0 | The buyer **asks**. Staff decide from a queue and the buyer is notified either way. |

A window of `0` does **not** mean "no refunds". It means no *automatic* refund —
the buyer can still ask. "You cannot even ask" is not a policy anyone wants to
defend to a student, so it is not one the code can express.

---

## Where the policy lives

Two numbers: **a window in hours** and **a fee**, and the fee is always a
**percentage of the amount paid**.

That one decision is what lets both numbers sit on the course or the plan instead
of on every price row. A percentage carries no currency, so a course sold at 36
EGP and 450 USD needs one number, not two; and it follows what was actually
charged, so a coupon that halves the price halves the fee. A flat `10` would have
meant ten pounds to one buyer and ten dollars to another — a fiftyfold difference
out of a single setting — and would quietly become a third of a discounted sale.

### Courses

*Course → Course pricing* → the **Refund policy for this course** form at the
bottom of the page, under the price rules.

| Field | Meaning |
|---|---|
| **Refund window (hours)** | Hours from payment during which the buyer can refund themselves. Blank = follow the site policy. `0` = they must ask. |
| **Default refund fee (%)** | Percentage of what the buyer paid, kept from the refund. Blank = follow the site policy. `0` = full refund. |

Blank and zero are different, and the distinction matters: blank falls back, zero
is a deliberate choice.

### Subscriptions

*Manage subscriptions → add/edit a plan* — the same two fields, on the plan.
They cover every country price row the plan has, because a percentage is the same
policy in all of them.

### Everything else

*Site admin → Plugins → Local plugins → Payments → **Refunds***

| Setting | Notes |
|---|---|
| Allow refunds | Master switch. Off by default; with it off no refund button appears anywhere. |
| Refunds: courses / subscriptions / everything else | Window + **default refund fee (%)**, used by anything that sets nothing of its own. |

The third block is what makes "any other thing we sell" work without a code
change: an item type with no settings of its own falls through to it.

---

## Resolution order

1. The **course or plan** the payment was for — its own terms, if it sets any.
   Each of the two numbers falls back on its own, so a course can set a window
   and still take the site's fee.
2. The **site policy** for that item type.
3. The **default** block, for anything that is neither a course nor a
   subscription.

The fee is computed against the amount on the transaction, so a sale is always
worth back what the policy said at the time it was *refunded*, on the money that
actually changed hands — discount included.

---

## Who does what

**Buyer** — *Payment history* has a **Refund** column on completed payments. The
button says which route it is: **Refund** inside the window, **Request refund**
outside it. Either way they see paid → fee → what comes back before confirming.

**Staff** — two places:

- *Payments → **All payments*** has a **Refund** action per row, for anyone with
  `local/payments:managerefunds`. The window does not apply to staff, and the
  policy fee is offered as a checkbox rather than assumed — most staff-initiated
  refunds are corrections, and charging a cancellation fee for your own mistake
  is hard to defend.
- *Payments → **Refund requests*** is the queue: approve or decline, with a note
  that reaches the buyer as a notification. Approving refunds **on the terms the
  buyer was quoted when they asked**, not today's settings.

---

## What a refund actually does

One gateway call, one row in `local_payments_refunds`, the transaction marked
`refunded`, enrolment removed, buyer notified. Both routes end in the same place;
only the authorisation differs.

The status is **`refunded`** even when a fee was kept. `partially_refunded` means
part of the purchase is still in force — one seat of three handed back — and a
fee is a deduction, not a portion of the sale left alive.

Refunds need a gateway that supports them. Fawaterk does on **v3/OAuth**; on v2
you get "raise it in the gateway dashboard instead".

---

## Mobile API

Two calls. Both take the standard `wstoken` / `moodlewsrestformat=json`, plus an
optional `alang` (`en` / `ar`).

### Ask what is on offer

```
wsfunction = local_payments_get_refund_options
params     = transaction_id
```

```jsonc
{
  "action": "refund",
  "reason_required": false,
  "message": "",
  "paid": 36.0,
  "fee": 3.6,
  "fee_percent": 10.0,
  "net": 32.4,
  "currency": "EGP",
  "window_hours": 48,
  "deadline": 1756900000,
  "policy": "Refundable within 48 hours of purchase, less a 3.60 EGP fee."
}
```

Switch on `action` — one field rather than three booleans to combine:

| `action` | Show |
|---|---|
| `refund` | A **Refund** button. Reason optional. |
| `request` | A **Request refund** button. Reason **required**. |
| `pending` | "Waiting on a decision" — they have already asked. |
| `none` | Nothing. `message` says why, already translated. |

Show `net` as the headline figure; `paid` and `fee` explain it, and `fee_percent`
is the policy behind the fee if you want to state it as a rule. `policy` is a
ready-made sentence if you would rather not compose one.

### Do it

```
wsfunction = local_payments_submit_refund
params     = transaction_id, reason
```

```jsonc
{ "outcome": "refunded", "message": "Refunded 32.40 EGP…", "amount": 32.4, "currency": "EGP" }
```

`outcome` is `refunded`, `requested`, or `failed`. Show `message` — it is written
for a buyer and already translated.

**One call, not two.** Which route applies depends on a window that may have
closed between drawing the screen and pressing the button, so the server decides
and tells you what it did. Do not pick the endpoint from a cached `action`.

Errors arrive as the usual `{exception, errorcode, message}`; `message` is
displayable as-is.

---

## Testing it

1. *Payments → Refunds* → **Allow refunds** on. Set **Refunds: courses** window
   to `48`, fee `10`%.
2. Buy a course, then open *Payment history* → **Refund**. You should get 90%
   back, be unenrolled, and see the order as `refunded`.
3. Now open that course's *Course pricing* page and set **Refund window `0`** in
   the form at the bottom, then buy again. The button becomes **Request refund**
   and a reason is required.
4. *Payments → Refund requests* → approve it. The buyer gets a notification.
5. Repeat 2 with the course priced in a second currency. The same `10` should
   still hold back a tenth — that is the point of a percentage.

Step 3 proves the course override beats the site setting; step 5 proves the one
number is genuinely currency-free.
