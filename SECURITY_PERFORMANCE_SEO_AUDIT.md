# Security, Performance & SEO Audit

**Project:** NIT Academy — Moodle 5.x (LMS) with custom NIT plugin suite
**Repository root:** `D:\My work\NIT\Projects\Academy\moodle-latest-502\moodle`
**Branch audited:** `moodle-new-version` (base branch: `main` = initial vanilla-Moodle + first-import commit)
**Audit type:** Read-only inspection + safe static analysis. **No source files were modified.**
**Date:** 2026-08-08

---

## 0. Scope, Method & Important Caveats

### What was audited
This is a **Moodle PHP application** (server-rendered Mustache templates), *not* a Next.js/React SPA. The supplied audit template assumes a modern JS backend/frontend split; each section below is mapped onto the actual stack, and JS-SPA-only checks are marked **N/A (stack mismatch)** with a reason.

Moodle **core** (tens of thousands of files) is an established upstream product and was **not** re-audited. The audit focuses on the **custom NIT code** — the only code this team owns and can be held responsible for:

| Plugin | Type | Maturity | Release | Purpose |
| ------ | ---- | -------- | ------- | ------- |
| `theme_nit` | Theme (Boost child) | `MATURITY_ALPHA` | 0.3.0 | Brand theme, design system, front page, colour/font editor |
| `block_nit_section` | Block | `MATURITY_ALPHA` | 0.1.0 | Rich-HTML front-page section block |
| `local_nit_core` | Local (library/SDK) | `MATURITY_ALPHA` | 0.1.0 | "Core SDK" — service locator, config/cache/flags/branding facades |
| `local_nit_finance` | Local | `MATURITY_ALPHA` | 0.1.0 | Wallet, earnings, teacher withdrawals |
| `local_googleauth` | Local | `MATURITY_STABLE` | 1.0.0 | Google ID-token → Moodle web-service token exchange (mobile) |

**Custom code size:** ~120 files / ~8,200 PHP LOC across the five plugins.

### Method
- Git diff of `main...HEAD` (56 files) to find recent changes, then a full enumeration of the five custom plugins (much custom code predates the branch — it was committed in the initial import).
- Manual read of every security-sensitive file (all HTTP entry points, auth, money, file upload, output paths).
- A parallel deep-analysis pass over the 55-file `local_nit_core` SDK.
- `npm audit --omit=dev` for production JS dependencies.
- Static SEO/performance review of templates and query patterns.

### Caveats — what could NOT be verified
- **PHP and Composer are not installed on this machine.** The application cannot be booted here, so **no runtime testing was possible**: no live response-time / p95 / RPS benchmarks, no Lighthouse/PageSpeed, no `composer audit`. All performance and CWV findings are **code-based estimates**, explicitly labelled as such.
- Runtime configuration (`config.php` at repo root, admin settings, server headers, TLS, WAF, reverse-proxy caching) lives outside the repository → marked **Not Verifiable From Repository** where relevant.
- Third-party PHP library CVEs (vendored `lib/google`, `lib/htmlpurifier`, etc.) could not be checked without `composer audit`.

---

# 1. Core Files Change Report

### 1.1 How the change set was derived
`git diff main...HEAD` reports **56 changed files**. A large fraction are **not real customizations** — they are vanilla Moodle files that reappeared because of a single `.gitignore` fix:

```diff
# .gitignore
-config.php        # matched config.php at ANY depth (wrongly ignored theme/boost/config.php,
+/config.php       # mod/quiz/.../config.php, cache/classes/config.php, lib/.../Config.php, ...)
```

The old un-anchored `config.php` pattern was hiding ~20 legitimate core files; anchoring it to `/config.php` correctly un-ignores them. **Confirmed:** the real secrets file (repo-root `/config.php`) remains **untracked** (present on disk, ignored) — no credential leak in git.

### 1.2 Genuinely customized / added files (by area)

| File | Area | Change Summary | Risk | Notes |
| ---- | ---- | -------------- | ---- | ----- |
| `public/local/googleauth/token.php` | Authentication / API | **New** token-exchange endpoint (Google ID token → MoodleWS token) | **Medium** | Well-built; see §2. Wildcard CORS, no rate limit |
| `public/local/googleauth/settings.php` | Auth / Config | Admin settings (client IDs, allow-create, domain restrict) | Low | `PARAM_RAW` on textarea, admin-only |
| `public/local/googleauth/classes/privacy/provider.php` | Auth / Privacy | GDPR null-provider | Low | Correct (no personal data stored) |
| `public/local/nit_finance/manage_withdrawals.php` | Business logic / Admin | **New** withdrawal-queue admin page (money) | **Medium** | Capability + sesskey OK; output escaped |
| `public/local/nit_finance/classes/service/withdrawal_service.php` | Services / Money | Withdrawal state machine | **High** | TOCTOU race on balance check (see §2) |
| `public/local/nit_finance/classes/service/wallet_service.php` | Services / Money | Balance aggregation (event-sourced) | Low | Good design; parameterized SQL |
| `public/local/nit_finance/classes/api/wallet.php` | API facade | Public money API | Medium | Delegates to services; no auth of its own (by design — callers gate) |
| `public/local/nit_finance/db/install.xml` | Database | `nit_earning`, `nit_withdrawal` tables | Low | Integer minor-units, composite indexes ✅ |
| `public/local/nit_finance/db/access.php` | Authorization | `local/nit_finance:manage` (manager) | Low | Correct |
| `public/local/nit_core/**` (55 files) | Services / SDK | Service locator, config/cache/flags/branding | Low | Clean; no endpoints (see §2.7) |
| `public/theme/nit/lib.php` | Frontend Core / Services | SCSS callbacks, front-page data helpers, font serving | **Medium** | N+1 front-page queries (see §4) |
| `public/theme/nit/gallery.php` | Config / Admin | Colour + font editor (POST handler, file upload) | Low | `admin_externalpage_setup` + sesskey + validation ✅ |
| `public/theme/nit/colours.php` | API | **New** public read-only palette JSON | Low | No secrets; wildcard CORS acceptable |
| `public/theme/nit/classes/output/core_renderer.php` | Frontend Core | Thin renderer override | Low | No business logic |
| `public/theme/nit/classes/output/gallery.php` | Frontend | Gallery view-model | Info | Hardcoded demo stats (§1.4) |
| `public/theme/nit/layout/frontpage.php` | Frontend Core | Front-page layout, exposes `NIT_STATS`/`NIT_COURSES` | Medium | JSON encoded safely; drives client render |
| `public/theme/nit/templates/frontpage.mustache` | Frontend | Client-side course/stat rendering | Medium | SEO/CWV impact (§5, §6) |
| `public/theme/boost/templates/core/login_panel.mustache` | Frontend (core override) | Removed left marketing panel | Low | Direct core-theme template edit (§1.3) |
| `public/theme/nit/templates/core/signup_form_layout.mustache` | Frontend | Signup override + "already have account" link | Low | `{{{formhtml}}}` is core-trusted |
| `public/theme/nit/templates/theme_boost/navbar.mustache` | Frontend | Custom navbar | Low | Triple-stache on core nav nodes (matches core) |
| `public/blocks/nit_section/block_nit_section.php` | Business logic | Rich-HTML block w/ layout controls | Low/Info | Trusted-content model mirrors core HTML block |
| `public/blocks/nit_section/edit_form.php` | Frontend | Block config form | Low | Raw-HTML field `PARAM_RAW` (by design) |
| `.github/workflows/composed/config.php` | Build / CI | Moodle CI template (test creds) | Low | Standard Moodle CI, `GITHUB_WORKFLOW` gated |
| `docs/apis/colour-palette-api.md` | Docs | Palette API doc | Info | — |

### 1.3 Files that should normally NOT be modified directly
- **`public/theme/boost/config.php`, `public/theme/classic/config.php`, `public/theme/boost/templates/core/login_panel.mustache`** — editing the **Boost core theme** in place (rather than overriding from `theme_nit`) means every Moodle upgrade will conflict/overwrite these. The `login_panel.mustache` change lives in `theme/boost`, not `theme/nit`. **Recommendation:** move overrides into `theme_nit/templates/`.
- `public/theme/nit/layout/frontpage.php` is a **fork of** `theme/boost/layout/drawers.php`. It is well-commented (`NIT:` fences + "re-diff on upgrade" note), but forked layouts are an upgrade-maintenance liability. **Risk: Medium (maintainability).**

### 1.4 Other observations
- **Hardcoded demo data:** `theme/nit/classes/output/gallery.php:85-89` returns hardcoded stat cards (`'1,284'`, `'842'`, `'37'`). This is the **admin design-system gallery** page only (not public), so it is cosmetic, but it is dead/placeholder data. **Info.**
- **Hardcoded currency:** `'EGP'` is hardcoded in `theme_nit_course_price()` output and `manage_withdrawals.php`. Acceptable for a single-market product; note for i18n. **Info.**
- **Duplicated logic:** none material found. The finance facades (`api\wallet`, `api\config`) are thin delegators by intent (Facade pattern), not duplication.
- **Secrets/keys/tokens hardcoded:** **none found.** Grep for `api_key`, `secret=`, `sk_live`, `AKIA`, private keys, and password literals across all custom code returned nothing. All `die()` occurrences are the standard `defined('MOODLE_INTERNAL') || die();` guard.
- **Debug/temporary code:** none. No `var_dump`/`print_r`/`console.log`/`debugger` leftovers in production paths.
- **Commented-out production code:** none material.

### Core Changes Summary
- **Total important changed/custom files:** ~120 files across 5 custom plugins (56 in the branch diff, ~20 of which are un-ignored vanilla files).
- **High-risk changes:** 1 (withdrawal race condition).
- **Medium-risk changes:** ~6 (googleauth endpoint hardening, front-page N+1, client-rendered SEO content, forked Boost layout/templates, flag-discovery scan).
- **Low-risk changes:** the remainder.

---

# 2. Full Security Audit

## 2.1 Backend Security

Moodle provides the security substrate (parameterized `$DB`, `format_string`/`format_text` output escaping, `required_param` typed input, `sesskey` CSRF tokens, capability system, `\core\session`). The custom code **uses these correctly** in almost all places. Findings below are on the custom code only.

| Severity | Issue | File / Location | Attack Scenario | Impact | Recommendation |
| -------- | ----- | --------------- | --------------- | ------ | -------------- |
| **High** | **TOCTOU race on withdrawal balance check** — `available_balance()` (SUM) is read, then a withdrawal row is created, with **no DB transaction, no row lock, no application lock** | `local/nit_finance/classes/service/withdrawal_service.php:44-61` (+ `wallet_service.php:58-66`) | A teacher fires two concurrent `request_withdrawal` calls; both read the same balance, both pass the check, both create holds that together exceed the balance | Teacher withdraws more than earned → direct financial loss | Wrap check+insert in `$DB->start_delegated_transaction()` and take a lock (`\core\lock`) keyed on `teacherid`, or re-verify `available_balance >= 0` inside the transaction after insert and roll back if negative. **Note:** `request()` is not yet wired to an HTTP endpoint in this repo, so it is not *currently* remotely reachable — but the public API `wallet::request_withdrawal()` is intended to be called from a teacher-facing page; fix before that lands. |
| **Medium** | **No rate limiting / abuse control** on the token endpoint; each call makes an outbound request to Google `tokeninfo` | `local/googleauth/token.php:81-84` | Attacker scripts thousands of POSTs → outbound-request amplification / resource exhaustion; no Moodle login-lockout applies | DoS / cost amplification; slow-drip credential probing | Add a per-IP throttle (e.g. `\core\ratelimiter` or a short MUC counter), and consider verifying the ID token **locally** against Google's cached JWKS instead of the network `tokeninfo` call (also faster — see §4) |
| **Medium** | **Email-based account linking with no auth-method allowlist** — a valid Google sign-in mints a WS token for **any** matching, verified-email Moodle account, including `auth=manual` password accounts | `local/googleauth/token.php:118-164` | If a user registered locally with `victim@gmail.com`, anyone controlling that Google identity gets a live token for the Moodle account | Account takeover where email ownership diverges from Moodle password ownership | Restrict linking to accounts whose `auth` is in an allowlist (e.g. `oauth2`), or require an explicit link/confirm step for pre-existing non-OAuth accounts |
| **Low** | **Wildcard CORS** `Access-Control-Allow-Origin: *` on a token-returning endpoint | `local/googleauth/token.php:54` | Any web origin can invoke it; mitigated because it needs a valid Google-signed `idtoken` in the POST body (not a cookie) and sets no `Allow-Credentials` | Low — no ambient-credential CSRF; token still requires a valid idtoken | Echo a configured allowlist origin instead of `*`; add `Vary: Origin` |
| **Low** | **Font upload validated by extension only** (not file content / magic bytes) | `theme/nit/gallery.php:116-120` | An admin uploads a non-font renamed `.ttf` | Very low — endpoint is gated by `moodle/site:config` (full admin) and stored as a static font asset served with `Content-Type` by Moodle file API | Optional: sniff magic bytes; acceptable given admin-only gate |
| **Low/Info** | **Raw-HTML block renders unsanitized HTML (incl. `<script>`) in trusted contexts** | `blocks/nit_section/block_nit_section.php:82-119, 226-231` | A user with `block/nit_section:addinstance` (editingteacher/manager) authors `<script>` in a non-user context | Stored XSS by privileged authors | **By design** — mirrors Moodle's core HTML block exactly: `content_is_trusted()` disables cleaning only outside `CONTEXT_USER`, and the capability carries `RISK_XSS | RISK_SPAM` (`db/access.php`). Acceptable if the addinstance capability stays restricted to trusted roles. Document it. |
| **Medium** | **Install-wide class scan not cached across requests** (see §4 — perf, but abuse-relevant on hot paths) | `local/nit_core/classes/flag/registry.php:84-102` | — | Perf/DoS amplification if flags are checked on unauthenticated hot paths | Wrap discovery result in a MUC cache |
| Info | `local_nit_core` has **no `db/access.php` and no endpoints** | plugin-wide | — | None — it is a pure library; nothing to gate | Add capabilities when concrete endpoints are introduced |

### Positive findings (backend) — verified correct
- **Authentication/authorization enforced server-side** on every custom entry point:
  - `theme/nit/gallery.php:35` → `admin_externalpage_setup('theme_nit_gallery')` (login + `moodle/site:config`).
  - `local/nit_finance/manage_withdrawals.php:29-31` → `require_login()` + `require_capability('local/nit_finance:manage')`.
  - `local/googleauth/token.php` → verifies the Google token **server-side** (issuer, audience against configured client IDs, expiry, `email_verified`), then applies core account gates (guest/suspended/unconfirmed/maintenance) mirroring `/login/token.php`.
- **CSRF:** all state-changing POST handlers call `confirm_sesskey()` (`gallery.php:46`, `manage_withdrawals.php:42`). GET token endpoint is POST-only by design.
- **Input validation:** typed `required_param`/`optional_param` throughout (`PARAM_INT`, `PARAM_ALPHA`, `PARAM_ALPHANUMEXT`, `PARAM_TEXT`); colour values regex-validated (`/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/`) before storage.
- **SQL injection:** no raw string-built SQL anywhere. Custom SQL (`withdrawal_service::list_all`, `wallet_service::sum`, `theme_nit_course_teacher`) uses **bound named parameters** and `get_in_or_equal`. `local_nit_core` uses `\core\persistent` exclusively.
- **Money handling:** stored as **integer minor units** (no floats), balances **event-sourced by aggregation** over immutable `nit_earning`/`nit_withdrawal` rows (`wallet_service.php:23-35`) — a robust design that avoids mutable-balance corruption.
- **Password hashing / reset / OTP:** delegated to Moodle core (unchanged). No custom crypto.
- **Secrets management:** no secrets in repo; client IDs stored as admin config.
- **File upload:** uses `is_uploaded_file()`, `UPLOAD_ERR_OK` check, extension allowlist, `clean_param(..., PARAM_FILE)`, stored via Moodle File API in system context.
- **Mass assignment:** finance entities use `\core\persistent` with defined schemas; googleauth explicitly whitelists user fields on create.
- **Command injection / eval / unserialize / SSRF (arbitrary):** none. The only outbound HTTP is a fixed Google URL. No `eval`/`system`/`shell_exec`/`unserialize` in custom code.

### Not verifiable from repository
Security headers (CSP, HSTS, `X-Frame-Options`), TLS, `$CFG` production flags (`debug`, `passwordsaltmain`, `cookiesecure`, `cookiehttponly`), and helmet-equivalent config live in the untracked root `config.php` / web-server config. **Marked Not Verifiable.**

### Backend Security Score: **82/100 (82%)**
**Why:** Core Moodle security primitives are used correctly and consistently — parameterized SQL, sesskey CSRF, typed input, server-side capability checks, safe output escaping, integer money, event-sourced balances, a well-hardened token endpoint. Points deducted for: one **High** design-level financial race condition (−9), **Medium** absence of rate-limiting and an over-permissive email-linking policy on the auth endpoint (−6), and **Low** items (wildcard CORS, extension-only upload validation) (−3). Not higher because production hardening (headers, `$CFG` flags) is **unverifiable** here and the withdrawal race is a genuine money-loss vector once wired. Not lower because there are **no confirmed injection, IDOR, or broken-access-control vulnerabilities** in the custom code.

---

## 2.2 Frontend Security

The "frontend" is server-rendered Mustache + small inline vanilla-JS enhancers. There is **no SPA, no client-side router, no JWT-in-browser, no `localStorage` token storage** in the custom code.

| Severity | Issue | File / Location | Exploitation Scenario | Impact | Recommendation |
| -------- | ----- | ------------- | --------------------- | ------ | -------------- |
| Low | **Unescaped triple-stache on nav nodes** `{{{url}}}` / `{{{text}}}` | `theme/nit/templates/theme_boost/navbar.mustache:52,90-96` | Nav text/URLs come from core `language_menu`/navigation exports (already escaped) and admin-configured custom menu | Low — matches core Boost convention; not user-controlled | Leave as-is (parity with core) or switch menu text to `{{text}}` |
| Low | **`Access-Control-Allow-Origin: *`** on public palette JSON | `theme/nit/colours.php:61` | Any origin reads the palette | None — data is public branding, read-only, no secrets | Acceptable; optionally scope origins |
| Info | **JS-driven counters/course grid start empty then populate** | `theme/nit/templates/frontpage.mustache:68-138` | — | Layout shift (CLS) — see §5 | Reserve space; not a security issue |

### Positive findings (frontend) — verified correct
- **Client-side course rendering is XSS-safe.** The inline renderer (`frontpage.mustache:96-133`) escapes every dynamic value: text via an `esc()` helper (`textContent`→`innerHTML`), URLs via `encodeURI`, and background-image URLs strip `"` and `\`. Course `fullname` is already `format_string`-escaped server-side (`lib.php:378`) and `summary` is reduced to plain text (`html_to_text` + `shorten_text`).
- **JSON injection into `<script>` is safe.** `nitstatsjson`/`nitcoursesjson` use `json_encode` **without** `JSON_UNESCAPED_SLASHES`, so `</script>` becomes `<\/script>` and cannot break out of the script element (`frontpage.php:146-148`, `frontpage.mustache:69-70`).
- **No `dangerouslySetInnerHTML`, no `document.write`, no `eval`, no `innerHTML` of untrusted data.** (The one `wrap.innerHTML = html` uses an author-controlled block template with already-escaped substitutions.)
- **No secrets, API keys, or tokens embedded in frontend code or templates.** No source maps or dev endpoints shipped by custom code.
- **Authorization is never performed only on the frontend** (see §3).

### Frontend Security Score: **86/100 (86%)**
**Why:** Output escaping is disciplined and correct across server templates and the client-side renderer; JSON-in-script is breakout-safe; no token-in-browser, no unsafe sinks, no leaked secrets. Deductions are minor: parity-with-core triple-stache on nav (−2), wildcard CORS on the public endpoint (−2), and CLS-inducing empty-then-fill rendering (−2, cosmetic). The remaining gap reflects the **privileged raw-HTML block** surface (shared with §2.1) and reliance on core CSP config that is unverifiable here.

---

# 3. HTML / Browser-to-Server Security Audit

**Question: can an attacker bypass the UI (curl/Postman/DevTools/modified JS) and hit the backend directly?**

Every custom server entry point was traced. There are **no custom REST/`external_function` web services** in the NIT plugins — the surface is three PHP endpoints plus core Moodle. Server-side enforcement is independent of the UI in all cases.

| Frontend Action | Backend Endpoint | Server Validation | Status | Issue |
| --------------- | ---------------- | ----------------- | ------ | ----- |
| Mobile Google sign-in | `local/googleauth/token.php` | AuthN: Google token verified server-side (iss/aud/exp/email_verified) + account gates. AuthZ: guest/suspended/confirmed checks. Input: `PARAM_RAW` idtoken, `PARAM_ALPHANUMEXT` service. Business rules: service must be enabled | ✅ Secure | No rate limit (⚠️ Medium, §2); email-linking policy (⚠️ Medium) |
| Save/reset colours | `theme/nit/gallery.php` (POST) | AuthN+AuthZ: `admin_externalpage_setup` (`site:config`). CSRF: `confirm_sesskey()`. Input: hex regex; invalid → default | ✅ Secure | — |
| Upload/reset fonts | `theme/nit/gallery.php` (multipart) | Same admin gate + sesskey; `is_uploaded_file`, extension allowlist, `PARAM_FILE` | ✅ Secure | Extension-only validation (Low) |
| Approve/pay/reject withdrawal | `local/nit_finance/manage_withdrawals.php` (GET+POST) | AuthN: `require_login`. AuthZ: `require_capability('local/nit_finance:manage')`. CSRF: `confirm_sesskey()`. Ownership: N/A (admin queue). Business rules: state-machine transitions enforced | ✅ Secure | State check not lock-guarded (Low race, §2) |
| Request withdrawal (API) | `wallet::request_withdrawal()` (no HTTP wire yet) | Amount>0 + balance check | ⚠️ Needs Improvement | **TOCTOU race** (High, §2) — fix before exposing |
| Read colour palette | `theme/nit/colours.php` | Read-only, `NO_MOODLE_COOKIES`, no sensitive data | ✅ Secure | Public by design |
| Add/configure section block | core block config flow → `block_nit_section` | Core `block/nit_section:addinstance` (`RISK_XSS`), sesskey via core mform | ✅ Secure | Trusted-content raw HTML by design (§2) |
| Front-page stats/courses | rendered in `frontpage.php` (no user input) | Read-only aggregation; no params accepted | ✅ Secure | Perf (§4), SEO (§6) |

**Parameter/price/role manipulation checks:**
- **Client-side price manipulation:** N/A — course price is computed server-side (`theme_nit_course_price`, `lib.php:272-288`) and only displayed; no client value is trusted for payment. Actual payment goes through core enrol plugins.
- **User-ID manipulation / IDOR:** the finance admin page derives the actor from `$USER->id` server-side (`manage_withdrawals.php:49`), never from a request field. Withdrawal `wid` is `PARAM_INT` and only reachable with the `manage` capability. No per-object ownership bypass found.
- **Hidden/disabled-field bypass:** finance form values (`action`, `reason`, `reference`) are all re-validated and constrained server-side (`PARAM_ALPHA`/`PARAM_TEXT`, state machine).
- **Role manipulation:** roles/capabilities are resolved server-side via Moodle's capability API; no client-supplied role is trusted.

### Conclusion — Is frontend→backend communication safely protected against direct HTML/API manipulation?

**Answer: YES (with two caveats to close before production).**

Every sensitive operation independently enforces authentication, authorization (capabilities), CSRF (sesskey), and typed input **on the server**, so bypassing the UI with curl/Postman/DevTools gains an attacker nothing they aren't already authorized for. The custom code never relies on HTML `disabled`/`hidden` attributes or client-side checks for security. The two caveats are the **withdrawal race condition** (fix with a transaction/lock before the request endpoint is exposed) and **rate-limiting** on the public token endpoint — neither is a UI-trust bypass, but both are direct-call robustness gaps.

### Frontend → Backend Request Security Score: **88/100 (88%)**

---

# 4. Backend Performance Audit

> **All figures below are code-based estimates. No runtime benchmarks were run** (PHP is not installed on this host). No real response-time/p95/RPS numbers are claimed.

| Endpoint / Area | Performance Issue | Severity | Cause | Recommended Optimization |
| --------------- | ----------------- | -------- | ----- | ------------------------ |
| **Site front page** (`theme_nit_get_courses`) | **N+1 queries** — for each of up to 12 courses: `context_course::instance`, `get_area_files` (overview image), `theme_nit_course_price` (1 query), `theme_nit_course_teacher` (2 queries) | **Medium** | Per-course helper calls in a loop, no batching | Batch price/teacher/context lookups; cache the assembled course list in MUC with a short TTL keyed by course revision | 
| `theme/nit/lib.php:326-387` | Runs on **every** front-page hit (incl. guests, the highest-traffic page), **uncached** | **Medium** | No MUC/`get_config` memoization | Wrap `theme_nit_get_courses()` + `theme_nit_get_site_stats()` output in an application cache; invalidate on course CRUD |
| **Site front page** (`theme_nit_get_site_stats`) | 5 `COUNT` queries incl. `COUNT(DISTINCT userid) FROM {user_enrolments}` on every load | **Medium** | Full-table aggregate, uncached | Cache result (e.g. 5–15 min TTL); the DISTINCT-count scales with enrolments |
| `local/googleauth/token.php:81-84` | **Blocking outbound HTTP** to Google `tokeninfo` on every sign-in (10s timeout) | Low | Network round-trip inline in request | Verify the ID-token JWT **locally** against Google's cached JWKS (also removes the DoS-amplification vector, §2) |
| `local/nit_finance/withdrawal_service::list_all` | **No pagination** — returns all withdrawals with a `{user}` JOIN | Low | Unbounded `get_records_sql` | Add `LIMIT`/paging for the admin queue as volume grows |
| `local/nit_core/flag/registry::discover` | **Install-wide class scan** (`get_component_classes_in_namespace(null,'flag')`) instantiating every provider | **Medium** | Scans all components; memoized per-request only | MUC-cache the discovery result across requests |
| `local/nit_core/cache_manager::set` | 2–3 cache round-trips per write (index `track()`) | Low | Area-index maintenance on every `set` | Fine at low volume; avoid in tight loops |
| `local/nit_core/base/repository::find_all` | `$limit = 0` default = unbounded | Low | Permissive default | Require an explicit limit in consumers |

### Positive findings (backend performance)
- **Finance schema is well-indexed:** `nit_earning` has `(lessonid,status)` and `(teacherid,status)`; `nit_withdrawal` has `(teacherid,status)` and `(status)` — exactly matching the aggregation `WHERE` clauses. Money columns are integers.
- **No queries inside loops** in `local_nit_core` production code; the per-page welcome-panel hook short-circuits cheaply (`config::get_bool` + pagelayout check) before doing anything.
- **Font/asset serving** uses the Moodle File API with `cacheability => public` (`lib.php:530-543`), so fonts are browser/proxy-cacheable and CDN-friendly.

### Backend Performance Score: **75/100** (code-based estimate)
**Why:** Data model and indexing are solid, and most code paths are lean. The dominant risk is the **uncached N+1 pattern on the front page** — the single most-requested URL — plus the uncached whole-table stat counts and the install-wide flag scan. None are algorithmically catastrophic, but together they will inflate DB load under traffic. With MUC caching on the front-page helpers and local JWKS verification, this comfortably reaches the mid-80s.

---

# 5. Frontend Performance Audit

Next.js-specific checks (`next/image`, `next/font`, RSC/`"use client"`, ISR, hydration) are **N/A — this is a Moodle Mustache/vanilla-JS frontend, not Next.js.** Assessed against the actual stack:

| File / Page | Performance Issue | Severity | Impact | Recommendation |
| --------- | ----------------- | -------- | ------ | -------------- |
| `theme/nit/templates/frontpage.mustache:96-133` | **Course grid built client-side** from `window.NIT_COURSES` after `DOMContentLoaded` | **Medium** | Empty container → cards appended = **CLS**; content not in initial paint | Reserve min-height on the grid/cards; or render cards server-side (also fixes SEO, §6) |
| `frontpage.mustache:118-122` | **Course images as CSS `background-image`**, no dimensions, no `loading="lazy"`, no `srcset`/modern format | **Medium** | **LCP** risk (unoptimized original-size images), no lazy-loading, no responsive sizing | Serve responsive/WebP variants; add explicit sizing; lazy-load below-the-fold |
| `frontpage.mustache:78-92` | Stat counters animate 0→value over 900ms via rAF | Low | Cosmetic; contributes to CLS if unsized | Set fixed width on counter elements |
| Inline `<script>` in `frontpage.mustache` | Front-page JS is inline (not a cached AMD module) | Low | Re-downloaded with each HTML response; not separately cacheable | Acceptable for a small script; move to an AMD module if it grows |
| Theme SCSS (`lib.php:395-510`) | Full Bootstrap + Boost preset + NIT layers compiled | Info | Standard Moodle cost; compiled CSS is cached by theme revision | No action (Moodle caches compiled CSS) |
| Fonts (`lib.php:214-238`) | `@font-face` uses `font-display: swap` ✅, self-hosted | — | Good — avoids invisible-text, no external font origin | Keep |

### Positive findings (frontend performance)
- Self-hosted fonts with `font-display: swap` and `font-weight: 100 900` variable range — good FOIT/FOUT behavior, no third-party font origin.
- No render-blocking third-party scripts introduced by custom code.
- Compiled CSS and served fonts are cacheable (`cacheability: public`).

### Frontend Performance Score: **72/100** (code-based estimate)
**Why:** Font strategy is good and there are no heavy third-party scripts, but the **client-rendered course grid + background-image course images** are real LCP/CLS liabilities on the marketing landing page, and no image optimization (dimensions/lazy/modern formats) is applied to course art. Server-rendering the grid and optimizing images would move this into the mid-80s.

---

# 6. SEO Compliance Audit

**Static Code SEO Analysis** (no Lighthouse/PageSpeed run — no live instance available; **no Lighthouse scores are invented**).

| Area | Finding | Status |
| ---- | ------- | ------ |
| Server-side rendering | Moodle renders full HTML server-side (Mustache) — base pages are crawlable | ✅ Good |
| **Primary front-page content (course catalog)** | **Rendered client-side** from `window.NIT_COURSES` (`frontpage.mustache:96-133`) — course titles, summaries, and **internal links to courses are not in the initial HTML** | ❌ Risk |
| Heading hierarchy | Custom course-detail renderer emits a single `<h1>` (`format_topics_renderer.php:332`); signup emits `<h1>`; front page relies on author blocks | ⚠️ Verify one H1/page |
| Titles / meta description | Set by Moodle core (`$PAGE->set_title/heading`); **no custom `<meta name="description">`** in NIT templates | ⚠️ Core default only |
| Open Graph / Twitter cards | **None** in custom templates | ❌ Missing |
| Canonical URLs | Not emitted by custom templates (core default) | ⚠️ Not Verifiable / core |
| Structured data (JSON-LD / schema.org `Course`) | **None** — a Course catalog is an ideal JSON-LD candidate | ❌ Missing |
| `robots.txt` / `sitemap.xml` | No custom `robots.txt`; Moodle ships **no XML sitemap** by default | ⚠️ Missing sitemap |
| Image alt text | Course images are CSS backgrounds (no `<img>`, so no alt); decorative pattern fallback | ⚠️ Non-text images |
| Internal links / crawlability | Course links exist only inside the client-rendered cards → not followed by non-JS crawlers | ❌ Risk |
| Semantic HTML | Templates use semantic tags; `format_topics_renderer` uses proper headings/sections | ✅ Good |
| Mobile responsiveness | Bootstrap/Boost responsive base + custom responsive SCSS | ✅ Good |
| 404 / redirects / status codes | Handled by Moodle core | ✅ (core) |
| Localization / hreflang | Bilingual EN/AR (font slots, RTL) but **no `hreflang`** tags emitted | ⚠️ Missing hreflang |

**Likely Core Web Vitals risks (code-based):**
- **LCP:** unoptimized course background images on the landing page (§5).
- **CLS:** empty-then-filled course grid and count-up stat elements without reserved space (§5).
- **INP:** low risk — minimal JS, no heavy handlers.

### SEO Technical Compliance Score: **66/100**
**Why:** SSR base and semantic markup are good, but the site's **primary marketing content (the course catalog and its internal links) is invisible to non-JS crawlers**, and there is no Open Graph, no JSON-LD `Course` schema, no XML sitemap, and no `hreflang` for the bilingual site. These are the highest-leverage SEO gaps.

### SEO Performance Readiness Score: **70/100**
**Why:** Good font strategy and lean JS, but LCP (unoptimized landing images) and CLS (client-rendered/animated content) risks on the most SEO-important page hold it back. Server-rendering the grid + image optimization would materially improve both scores.

---

# 7. Dependency Security

| Scope | Result |
| ----- | ------ |
| **npm production dependencies** (`npm audit --omit=dev`) | **0 vulnerabilities** (critical 0 / high 0 / moderate 0 / low 0) across 1,195 resolved deps. Production deps are `@moodlehq/design-system`, `react`, `react-dom` (Moodle core build/runtime, not custom) |
| npm dev dependencies | Not separately reported; dev-only build tooling (grunt/babel/eslint), not shipped to users |
| **Composer / PHP dependencies** | **Not Verifiable From Repository** — Composer is not installed on this host, so `composer audit` could not run. `composer.lock` is present |
| Custom NIT plugins | **Zero third-party runtime dependencies of their own** — pure Moodle API consumers. Inter-plugin deps only (`theme_nit`/`nit_finance` → `local_nit_core`) |

| Package | Version | Severity | Vulnerability | Fix Available |
| ------- | ------- | -------- | ------------- | ------------- |
| (production npm) | — | None found | — | — |
| (PHP/Composer) | — | Not Verifiable | Run `composer audit` in CI | — |

**Recommendation:** add `composer audit` (and `npm audit` for dev) to the CI pipeline; the custom code itself introduces no new dependency risk.

---

# 8. Final Security & Performance Dashboard

| Area | Score | Status |
| ---- | ----: | ------ |
| Backend Security | 82% | ⚠️ |
| Frontend Security | 86% | ⚠️ |
| Frontend → Backend Request Security | 88% | ✅ |
| Backend Performance | 75% | ⚠️ |
| Frontend Performance | 72% | ⚠️ |
| SEO Technical Compliance | 66% | ⚠️ |
| **Overall Production Readiness** | **78%** | ⚠️ |

*Scale: 90–100 Excellent / 80–89 Good / 70–79 Acceptable (needs improvement) / 50–69 Significant issues / <50 High risk.*
*Overall is a weighted judgement (security-weighted), not a raw average, and reflects that the plugins are self-declared `MATURITY_ALPHA` (except `local_googleauth`, `STABLE`).*

---

# 9. Priority Fix List

## P0 — Critical (fix before deployment)
*(None strictly Critical; the following is High and money-related — treat as a release blocker for the finance feature.)*

- **Withdrawal balance race condition (TOCTOU)**
  - **File(s):** `public/local/nit_finance/classes/service/withdrawal_service.php:44-61`, `classes/service/wallet_service.php:58-66`
  - **Why it matters:** concurrent withdrawal requests can each pass the balance check and create holds that exceed the available balance → a teacher can withdraw more money than earned.
  - **Fix:** wrap the balance check + insert in a delegated DB transaction and take a `\core\lock` keyed on `teacherid` (or re-assert `available_balance() >= 0` inside the transaction and roll back). Do this **before** exposing `wallet::request_withdrawal()` via any HTTP endpoint.

## P1 — High (fix before production)
- **Rate-limit / harden the Google token endpoint**
  - **File:** `public/local/googleauth/token.php:54,81-84`
  - **Why:** no throttle + a blocking outbound call per request enables DoS/amplification; wildcard CORS is broader than needed.
  - **Fix:** add per-IP throttling; verify the JWT locally against cached Google JWKS instead of the network `tokeninfo` call; replace `Access-Control-Allow-Origin: *` with a configured allowlist.
- **Constrain email-based account linking**
  - **File:** `public/local/googleauth/token.php:118-164`
  - **Why:** a Google sign-in currently mints a token for any matching verified-email account, including local-password accounts → takeover risk where email ≠ Moodle password ownership.
  - **Fix:** allowlist linkable `auth` methods, or require an explicit link/confirm for pre-existing non-OAuth accounts.
- **Cache the front-page data helpers**
  - **File:** `public/theme/nit/lib.php:249-264, 326-387`
  - **Why:** uncached N+1 course queries + whole-table stat counts on the highest-traffic page.
  - **Fix:** MUC-cache `theme_nit_get_courses()` and `theme_nit_get_site_stats()`; invalidate on course changes.

## P2 — Medium (important, not blockers)
- **Server-render the front-page course grid** (`frontpage.mustache:96-133`) — fixes both SEO invisibility (§6) and CLS/LCP (§5).
- **Add SEO essentials** — JSON-LD `Course` schema, Open Graph/Twitter meta, an XML sitemap, and `hreflang` for EN/AR.
- **Cache `local_nit_core` flag discovery** (`classes/flag/registry.php:84-102`) in MUC.
- **Optimize course images** — dimensions, `loading="lazy"`, responsive/WebP variants.
- **Move Boost core-theme overrides into `theme_nit`** (`theme/boost/templates/core/login_panel.mustache`) to survive upgrades.

## P3 — Low (cleanup / maintainability)
- Font upload: add magic-byte sniffing (currently extension-only) — `gallery.php:116-120`.
- Add pagination to the withdrawal admin queue — `withdrawal_service::list_all`.
- Require an explicit limit on `repository::find_all` — `base/repository.php:58`.
- Replace hardcoded demo stats in the gallery view-model with a note that they are placeholders — `output/gallery.php:85-89`.
- Document the `block_nit_section` trusted-HTML behavior and keep `addinstance` restricted to trusted roles.
- Track the `local_nit_core` cache subsystem (defined but only test-exercised) so it doesn't rot.
- Add `composer audit` + `npm audit` to CI.

---

# 10. Final Verdict

1. **Is the backend secure enough for production?** **Almost.** Fundamentals are strong (parameterized SQL, sesskey CSRF, server-side capability checks, safe output, integer/event-sourced money). Close the withdrawal race (P0) and add token-endpoint rate-limiting + linking policy (P1) first.
2. **Is the frontend secure enough for production?** **Yes.** Output escaping is disciplined, client-side rendering is XSS-safe, no token-in-browser, no unsafe sinks, no leaked secrets.
3. **Can frontend/HTML manipulation bypass backend security?** **No.** Every sensitive operation re-validates auth, capability, CSRF, and input server-side; nothing trusts hidden/disabled fields or client checks.
4. **Are authentication and authorization enforced server-side?** **Yes**, on every custom entry point (`admin_externalpage_setup`, `require_login`+`require_capability`, server-side Google-token verification).
5. **Are there any Critical or High security vulnerabilities?** **One High:** the withdrawal TOCTOU race (financial). No confirmed Critical. Two Medium auth-endpoint items.
6. **Is backend performance production-ready?** **Acceptable with fixes.** Schema/indexing are good; add caching for the front-page helpers and flag discovery (N+1 + whole-table counts on the busiest page).
7. **Is frontend performance production-ready?** **Acceptable with fixes.** Good font strategy; address client-rendered grid CLS and unoptimized course images (LCP).
8. **Is the frontend technically ready for good SEO?** **Partially.** SSR base is crawlable, but the primary course content is client-rendered and OG/JSON-LD/sitemap/hreflang are missing — the biggest SEO wins are here.
9. **Top 5 things to fix before production:**
   1. Withdrawal balance race — add transaction + lock (P0).
   2. Rate-limit + local JWKS verification + tighter CORS on `token.php` (P1).
   3. Constrain Google email→account linking (P1).
   4. Cache front-page course/stat helpers to kill the N+1 (P1).
   5. Server-render the course grid + add JSON-LD/OG/sitemap (P2 — SEO + CWV).
10. **Overall: is this project ready for production deployment?** **Not yet — but close.** Score **78% (Acceptable, needs improvement).** The custom code is well-architected and largely secure, with no injection/IDOR/broken-access-control defects. The blockers are a small, well-defined set: one financial race condition, two auth-endpoint hardening items, front-page caching, and SEO/image optimization. Clear P0/P1 and this is comfortably production-ready. The plugins are also self-declared **ALPHA** (except `local_googleauth`), which matches the maturity observed.

---

### Appendix — Verification notes
- **Environment limits:** PHP/Composer not installed → **no runtime benchmarks, no Lighthouse, no `composer audit`.** All performance/CWV/SEO-performance figures are **code-based estimates**, and any figure that would require a live instance is labelled *Not Verifiable From Repository*.
- **npm production audit** was executed and returned **0 vulnerabilities**.
- **Secrets:** repo-root `/config.php` (Moodle secrets) is **untracked** — verified. No secrets found in any custom file.
- Line numbers refer to the state of the `moodle-new-version` branch at audit time.
