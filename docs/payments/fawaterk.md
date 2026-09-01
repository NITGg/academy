# Fawaterk payment provider

Fawaterk (Fawaterak) is a payment gateway subplugin of `local_payments`, living at
`public/local/payments/provider/fawaterk/`. It sits behind the same
`provider_interface` as Kashier, so nothing above the gateway layer knows which
one is charging the card.

## Which API, which credential

Fawaterk has two generations of API and they take **different credentials**, so
the credential and the version go together. `Authentication method` picks both:

| Mode | API | Credential | Notes |
|---|---|---|---|
| **OAuth 2.0** (default) | `/api/v3/*` | Client ID + secret → `/oauth/token` | Current API. Per-request webhook URL, refunds, `intent_key`. |
| HASH API key | `/api/v2/*` | HASH API key as the bearer | Fallback. No refund API; webhook URL must be set in the dashboard. |

Both credentials are on the dashboard's **Integrations** page. The **HASH API
key is required either way** — every webhook is signed with it, not with an
access token, so without it no payment can ever be confirmed.

Verified against a live account (Aug 2026): the OAuth grant, both
`createTransaction` shapes, `getTransactionData`, and the v2 equivalents. Note
that a v3 OAuth token is *rejected* by `/api/v2/*` and vice versa — the two
generations do not share credentials, which is the single most confusing thing
about this integration.

Two consequences worth knowing:

- **OAuth clients can only be created on the live dashboard.** There is no
  staging equivalent, so OAuth and sandbox mode cannot be combined.
- **Sandbox and live are separate accounts with separate credentials.**
  Anything copied from `app.fawaterk.com` is a live credential and is rejected
  when *Sandbox mode* is on, with `Invalid Token or inactive vendor`.

## Payment flows

| `payment_method_id` | Result |
|---------------------|--------|
| a method id | charges that method directly (server-to-server) |
| `0` (default) | auto-selects a method — see [web checkout](#11-which-method-the-web-checkout-uses) |
| `-1` | Fawaterk's hosted page, where it asks for the method itself |

---

## 1. Configuration

**Site admin → Plugins → Local plugins → Payments → Provider settings → Fawaterk**

Fawaterk issues **two separate credential sets**, both on the dashboard's
**Integrations** page. Only one of them works for payments:

| Dashboard section | What it gives you | Does it authenticate the payment API? |
|---|---|---|
| *Iframe/Webhook integrations settings* | HASH API key, providerKey | **Yes** — use this. Also the secret webhook `hashKey`s are signed with. |
| *machine-to-machine credentials* | Client ID + secret, token URL `/oauth/token` | **No** (as of Aug 2026) |

The OAuth path looks like the obvious choice — it's the newer, "recommended"
integration and the dialog is right there. It isn't the one to use. Tested
against the live account: `/oauth/token` mints a token correctly, but
`/api/v2/getPaymentmethods` with that token answers HTTP 400
`{"token":["Invalid Token or inactive vendor."]}`, while the same call with the
HASH API key returns HTTP 200. Those client credentials belong to Fawaterk's
newer *Integrations Transactions* API, not to the v2 payment endpoints.

The OAuth grant is implemented and selectable (`Authentication method` →
OAuth), so if Fawaterk extends it to payments it's a one-setting switch. Until
then, leave it on **HASH API key**.

Two further consequences worth knowing:

- **OAuth clients can only be created on the live dashboard.** There is no
  staging equivalent, so OAuth and sandbox mode can't be combined at all.
- **Sandbox and live are separate accounts with separate credentials.**
  `app.fawaterk.com` is the live dashboard; anything copied from it while
  *Sandbox mode* is on is rejected with the same
  `Invalid Token or inactive vendor`.

| Setting | Notes |
|---------|-------|
| Sandbox mode | On → `https://staging.fawaterk.com`, off → `https://app.fawaterk.com` |
| Authentication method | **OAuth 2.0 (v3)** by default; HASH API key (v2) is the fallback |
| OAuth client ID / secret | From *Integrations → machine-to-machine credentials*. The secret is shown once. |
| OAuth token URL | Leave empty — defaults to `/oauth/token` on the current mode's host |
| HASH API key | From *Iframe/Webhook integrations settings*. Signs every webhook; also the v2 bearer. |
| providerKey | Only needed for Fawaterk's JS iframe, which this plugin doesn't use |
| Live / Sandbox API base URL | Overridable in case Fawaterk moves hosts |
| Charge a method directly | On by default — server-to-server. Off = always use the hosted page. |
| Payment method priority | Comma-separated method ids, best first. Default `2,4,3` (card, Meeza, Fawry). |
| Reference code validity | Days an order stays open when the buyer gets an offline code. Default 3. |
| Fallback phone / address | Sent when the buyer's Moodle profile has neither (both are mandatory to Fawaterk) |
| Email / SMS the invoice | Lets Fawaterk notify the buyer directly |

Check the whole setup in one command:

```bash
docker compose exec moodle php public/local/payments/cli/fawaterk_diagnose.php
```

It proves the credentials, prints the methods the account has enabled, and says
which one the web checkout will charge.

> **The `public/` prefix matters.** The container's working directory is the
> repo root, not the Moodle code root, so CLI scripts are under `public/`. Core
> scripts like `admin/cli/upgrade.php` resolve without it (there is an `admin/`
> at the repo root too), which makes the difference easy to miss.

Two more options, both reading through Moodle's DB layer so they work without a
`mysql` client — there isn't one in the moodle container:

```bash
docker compose exec moodle php public/local/payments/cli/fawaterk_diagnose.php --logs
```

The exact response body of every failed API call, byte for byte — including a
non-JSON one, which is what an HTML error page or a proxy timeout looks like and
which decodes to nothing at all.

To capture successful calls too — the request Moodle sent alongside the response
it got — turn on *Log every API call* in the provider settings. The
`Authorization` header is never recorded; the request body is, and it carries the
buyer's name, email, phone and address, so turn it back off when you are done.

```bash
docker compose exec moodle php public/local/payments/cli/fawaterk_diagnose.php --webhooks
```

What has arrived and whether each signature verified. This separates "Fawaterk
never called us" from "it called and we rejected it" — worth checking after the
first real payment, since the webhook is what does the enrolling.

```bash
docker compose exec moodle php public/local/payments/cli/fawaterk_diagnose.php --transaction=PAY-2026-12345678
```

Asks Fawaterk what happened to one payment: its status, whether it is paid, and
the **attempt history** — which is where a declined card says why. Takes a Moodle
order id or a Fawaterk intent key; with no argument it uses the most recent
transaction. Use this when a payment says "failed" and you need the reason.

> A decline reason only reaches Moodle if the **Failed webhook** is configured in
> the Fawaterk dashboard. Without it the order is marked failed with nothing
> attached, and `--transaction` is the only way to see what the gateway said.

To syntax-check the plugin after a deploy (silence means clean):

```bash
docker compose exec moodle sh -c 'find . -name "*.php" -path "*local/payments*" -exec php -l {} \;' | grep -v 'No syntax errors'
```

Then **Manage providers** → enable *Fawaterk* (and set its priority above Kashier
if it should be the default pick).

### 1.1 Which method the web checkout uses

The buyer chooses, whenever the gateway reports more than one method. There is
no setting for this and deliberately so: the mobile web service hands the app
every method unconditionally, so anything that could switch the web off would
make one gateway behave two ways depending on which screen the student bought
from.

The choice appears in one of two places, depending only on how they got there:

- **In the checkout modal** on the course page — a strip of method cards between
  the total and the coupon box. This is the usual one, and it is the same
  position and shape the app uses, so the two flows read as one product.
- **On its own screen** for anything that links straight to `checkout.php`
  without the modal (a course card, a saved link). Same choice, one step later.

Either way it is skipped when the gateway reports fewer than two methods: one
method is not a choice, and asking about it is a click that teaches nothing.
Whichever the buyer picks is checked against the live list before it is charged;
a stale id falls back to auto-selection rather than a gateway error.

When there was nothing to choose, the gateway picks:

- **one method enabled** → that one.
- **two or more** → the first one named in *Payment method priority* that the
  account actually has enabled. Anything the list doesn't mention is used only if
  nothing in the list matches.
- **the account lists none** → the first id in *Payment method priority*
  anyway. An empty list does not mean the account can't take payments:
  `getPaymentmethods` reports what's configured for the hosted iframe, and an
  account with nothing activated returns `[]` while `invoiceInitPay` charges
  card (id 2) perfectly well. Trusting the enumeration over the configured
  preference would silently push every checkout onto the hosted page.
- **auto-selection off, or no priority configured** → the Fawaterk hosted page.

The account's method list is cached for an hour, so purge caches after enabling a
new method in the Fawaterk dashboard (or run the diagnose script with
`--purge-cache`).

If the chosen method redirects (card), the buyer goes straight to it. If it
returns a code (Fawry, Meeza), they land on a page showing the code — see
[offline codes](#4-offline-codes-fawry-meeza).

### 1.2 Webhook

In the Fawaterk dashboard set the webhook URL to:

```
https://<your-site>/local/payments/webhook_json.php
```

The `_json` suffix is required — it is how Fawaterk decides to POST a JSON body
rather than form fields. That is also why Fawaterk gets its own endpoint file
instead of the shared `webhook.php?provider=…`.

On **v3** the paid/pending webhook URL is sent with every `createTransaction`, so
that one works without any dashboard setup. The **failed**, **cancellation** and
**refund** webhooks are still dashboard-only — set all of them to the same URL;
the handler tells the shapes apart by their fields.

The webhook is what actually enrols the buyer. Everything the app does after
starting a payment is polling for the result of this call.

---

## 2. Mobile API

Three calls, all on the standard Moodle web-service endpoint
(`/webservice/rest/server.php`) with `moodlewsrestformat=json` and the user's token.

### 2.1 List payment methods

```
wsfunction = local_payments_get_provider_payment_methods
```

| Param | Type | Notes |
|-------|------|-------|
| `courseid` | int | The course being bought. `0` for a subscription. |
| `country` | string | ISO-2 from the app (optional — falls back to the profile country) |
| `currency` | string | Only used when `courseid=0` |
| `alang` | string | `en` / `ar` |

```jsonc
{
  "provider": "fawaterk",
  "supports_payment_methods": true,
  "methods": [
    { "id": 2, "name_en": "Visa-Mastercard", "name_ar": "فيزا -ماستر كارد",
      "logo": "https://…/mastercard-visa.png", "redirect": true },
    { "id": 3, "name_en": "Fawry", "name_ar": "فوري",
      "logo": "https://…/fawry.png", "redirect": false },
    { "id": 4, "name_en": "Meeza", "name_ar": "ميزا",
      "logo": "https://…/MeezaDigitalSmall.png", "redirect": false }
  ]
}
```

> **`supports_payment_methods: false`** means the gateway that would handle this
> purchase (e.g. Kashier) shows its own picker. Skip the picker screen entirely,
> call `create_checkout` with no `payment_method_id`, and open `checkout_url`.
>
> The list comes from the Fawaterk account, so it changes when methods are
> enabled or disabled there. Fetch it at checkout time; don't hard-code ids.

### 2.2 Start the payment

```
wsfunction = local_payments_create_checkout                       // a course
wsfunction = local_nit_subscriptions_create_subscription_checkout // a plan
```

Both take the same new parameter:

| Param | Type | Notes |
|-------|------|-------|
| `payment_method_id` | int | `id` from the list above. `0` = let the server pick, `-1` = hosted page. |

…alongside what they already took (`courseid` / `subscriptionid`, `country`,
`alang`, `coupon_code`, and for subscriptions `type`, `seats`, `return_url`).

Both now return a `payment_data` object on top of their existing fields:

```jsonc
{
  "order_id": "PAY-2026-04471382",
  "checkout_url": "https://staging.fawaterk.com/link/I0PAH",
  "expires_at": 1756550400,
  "provider": "fawaterk",
  "transaction_id": 4471,
  "amount": 350.0,
  "original_amount": 500.0,
  "currency": "EGP",
  "payment_data": {
    "type": "redirect",
    "redirect_url": "https://staging.fawaterk.com/link/I0PAH",
    "reference": "",
    "reference_expires_at": "",
    "method_name": "Visa-Mastercard"
  }
}
```

`payment_data.type` is the only thing the app needs to branch on:

| `type` | What it means | What the app does |
|--------|---------------|-------------------|
| `redirect` | Card / 3-D Secure, or a hosted page | Open `redirect_url` in a web view. Watch for a return to `/local/payments/callback.php`, then go to step 2.3. |
| `reference` | Fawry / Meeza / wallet code | Show `reference` (and `reference_expires_at` if set) with copy + share. **Do not** open a web view. The buyer pays at an outlet or in their wallet app, possibly hours later. |
| `none` | No extra step | Go straight to step 2.3. |

Example of a `reference` response (Fawry, `payment_method_id: 3`):

```jsonc
{
  "order_id": "PAY-2026-04471382",
  "checkout_url": "",
  "provider": "fawaterk",
  "transaction_id": 4472,
  "amount": 350.0,
  "currency": "EGP",
  "payment_data": {
    "type": "reference",
    "redirect_url": "",
    "reference": "981335305",
    "reference_expires_at": "2026-09-02 15:53:41",
    "method_name": "Fawry"
  }
}
```

Note `checkout_url` is **empty** here. An app that only reads `checkout_url` will
show a blank web view — always branch on `payment_data.type` first.

### 2.3 Confirm the payment

```
wsfunction = local_payments_verify_payment    // params: order_id
```

Fawaterk's webhook is the authoritative path — it is what completes the
transaction and enrols the student. `verify_payment` reads that result and, if
the webhook hasn't landed yet, falls back to asking Fawaterk directly.

- **`redirect` payments:** poll every ~3 s for up to ~2 min after the web view
  returns.
- **`reference` payments:** don't block the UI. The order stays `PENDING` until
  the buyer pays the code — which may be the next day. Re-check on app
  foreground, or show it under *Payment history*.

---

## 3. Full mobile sequence

```
get_provider_payment_methods(courseid)
        │
        ├── supports_payment_methods = false ──► create_checkout(courseid)
        │                                        └─► open checkout_url
        │
        └── show picker ──► create_checkout(courseid, payment_method_id: <id>)
                                    │
                    ┌───────────────┼───────────────┐
              type=redirect    type=reference     type=none
                    │                │                │
            open redirect_url   show the code         │
                    │                │                │
                    └──────► verify_payment(order_id) ◄┘
                                     │
                              status = completed → enrolled
```

---

## 4. Offline codes (Fawry, Meeza)

Some methods don't take the money — they hand the buyer a code to pay at an
outlet or in a wallet app, often the next day. That changes three things:

**There is nothing to redirect to.** `checkout_url` is empty and
`payment_data.type` is `reference`. On the web the buyer gets a dedicated screen
(`templates/payment_reference.mustache`) showing the code, the amount, the
deadline and a copy button. Returning to `callback.php` for an order that still
hasn't been paid re-shows that screen instead of a failure page.

**The order has to stay open long enough.** A checkout normally expires after 30
minutes (`local_payments | payment_ttl`); a Fawry code would outlive it, and the
confirmation would then land on a dead transaction. So for reference payments the
expiry is taken from the code's own deadline, falling back to Fawaterk's
*Reference code validity* setting (3 days by default).

**A late confirmation still fulfils.** `expired → completed` is an allowed status
transition: if a payment is confirmed after we gave up waiting, the buyer is
still enrolled. Reaching `completed` always requires a signature- and
amount-verified webhook, so this doesn't weaken anything — it just refuses to
strand someone who really paid.

---

## 5. Behaviour worth knowing

**Reusing a pending checkout.** A second `create_checkout` for the same course
returns the existing pending session only if the price *and* the payment method
match. Switching from Fawry to card retires the old session and opens a new one,
so the buyer never gets a code for a charge they've changed their mind about.

**Amounts are re-read, not trusted.** Fawaterk's `paid` webhook carries no
amount, and the amount check is what gates enrolment — so on a valid paid webhook
the gateway calls `getInvoiceData` and uses the API's figures. A webhook whose
signature is fine but whose invoice isn't actually paid is rejected.

**Refunds.** Supported on **v3** (`POST /api/v3/refund/create`, `refund_type: 3`
= integration transaction). On v2 there is no refund endpoint, so
`supports_refund()` reports `false` in that mode and refunds must be raised in
the dashboard. The v3 refund webhook *is* signed, so an approved refund is
matched back to its order by Fawaterk's numeric transaction id — which is
recorded when the payment completes — and applied automatically.

**Currency — multi-currency works, but the reporting is always EGP.** The buyer is
charged in whichever currency the order uses: a 4.50 USD order shows as
`Pay - USD 4.50` and is authorised in USD.

Fawaterk then reports it back converted into EGP, because EGP is their settlement
currency. The spec says so outright:

```jsonc
"total":    "Transaction total converted to EGP."
"currency": "Returned currency is EGP after conversion."
```

So `getTransactionData` on a USD order returns `total: 240.435, currency: EGP`.
That is the same payment expressed in their books, not a different charge — and
comparing it to the order will always disagree for any non-EGP currency.

The gateway therefore declares `reports_normalised_amounts()`, and the amount
check skips the comparison when the reported currency differs from the order.
That is not a hole: the amount is fixed when the transaction is created — the
buyer can only pay what was put on it — and the gateway has already confirmed
*that* transaction as paid, over an authenticated call. The amount is guaranteed
by creation rather than by reporting. When the currencies **do** match, the
figures are compared as normal.

The EGP figure is recorded in the transaction metadata as `settled`, so
reconciling against a Fawaterk statement has something to work from.

---

## 6. Troubleshooting

Every API call is logged to `local_payments_logs` with the full response body:

```sql
SELECT timecreated, level, message, context
  FROM mdl_local_payments_logs
 WHERE provider_id = (SELECT id FROM mdl_local_payments_providers WHERE name = 'fawaterk')
 ORDER BY id DESC LIMIT 20;
```

Webhook bodies (including ones that failed signature checks) are in
`mdl_local_payments_webhooks`.

| Symptom | Usual cause |
|---------|-------------|
| `HTTP 400` on checkout | A rejected field. The message now includes Fawaterk's own validation text — read it. Most often: a currency the account doesn't support, a phone that isn't `01XXXXXXXXX`, or a missing address. Phone and address already fall back to the configured placeholders. |
| `{"token":["Invalid Token or inactive vendor."]}` | Fawaterk rejecting the credentials — note v2 answers **400**, not 401. Either the credential doesn't match the API generation (a v3 OAuth token sent to `/api/v2/*`, or the HASH key sent to `/api/v3/*`), or it's from the wrong environment: `app.fawaterk.com` is the *live* dashboard and its keys fail while sandbox mode is on. `fawaterk_diagnose.php` tells you which. |
| `{"status":"error","message":"Unable to resolve vendor from OAuth client"}` (401) | The OAuth client is valid but isn't linked to a vendor account. Ask Fawaterk to attach it. |
| `{"content-type":["The content-type field is required."]}` | The v2 API demands a `Content-Type` header even on GET. Handled since Aug 2026; if you see it, the deploy is behind. |
| `{"cartTotal":["Amount must be bigger than 5 EGP"]}` (HTTP 422) | Fawaterk enforces a 5 EGP floor per invoice. Any course or plan priced below that cannot be sold through it. |
| The payment page says **"Setup in Progress — we are completing the final configuration for this invoice's payment methods"** | Nothing to fix on this side. The Fawaterk account has no payment methods activated, which is also why `getTrPaymentmethods` returns `[]`. Ask Fawaterk to enable the methods (card, Fawry, Meeza…) for the vendor account. Until they do, every transaction is created successfully but cannot be paid. |
| The due date on the payment page is days away | Fawaterk's default is 2 days; *Payment link validity* now sets it explicitly. The Moodle order may expire sooner — that is fine, a payment confirmed afterwards still fulfils. |
| `Sorry, service is currently unavailable, please try again later` on the card form | Fawaterk's card processor refusing the attempt before it becomes an attempt — `--transaction` shows an empty history, so nothing reached their transaction record. Check, in order: (1) is the order in a currency the account settles in? A converted amount like `240.435` has three decimals and is not a valid EGP amount; (2) is card actually activated on that Fawaterk account — the same thing that makes `getTrPaymentmethods` return `[]`; (3) ask Fawaterk, quoting the `transaction_id` from `--transaction`. |
| Order marked failed with `Amount mismatch: expected 4.5 USD; gateway reported 240.435 EGP` | The API reported the base-currency figure and the webhook did not carry a matching one. Check `--transaction`; if the paid webhook is not arriving at all, that is the real problem — see the webhook rows above. |
| Everything worked, then stopped | The OAuth client was revoked, or its secret rotated. Tokens are cached until they expire, so a revocation can surface minutes later. `fawaterk_diagnose.php --purge-cache` forces a fresh handshake. |
| Payment succeeds but no enrolment | The webhook isn't arriving. Check the dashboard URL ends in `webhook_json.php`, and look for `signature_valid = 0` rows — that means the vendor key in Moodle differs from the one signing the webhook. |
| `no redirect URL or reference` | The account doesn't have that `payment_method_id` enabled. Re-fetch the method list. |
