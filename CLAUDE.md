# Working in this repository

This repo is a full **Moodle 5.2** codebase with our academy customizations layered on top.

## Golden rule: never modify Moodle core

All customization MUST live in our own plugins and theme. **Do not edit Moodle
core files.** Core = everything that ships with Moodle, e.g. `public/lib/`,
`public/course/`, `public/user/`, `public/admin/`, `public/enrol/`,
`public/question/`, `public/grade/`, `public/message/`, the core activity
modules under `public/mod/`, and the stock themes `public/theme/boost` and
`public/theme/classic`.

Why: an untouched core means we can pull Moodle security/point releases with a
plain upgrade and never hit a merge conflict or lose a fix. Every core edit is a
liability we have to re-apply and re-test on every Moodle update.

## Where our code goes

| Need | Put it in |
|------|-----------|
| Business logic, APIs, payments, subscriptions, custom web-service functions | a plugin under `public/local/*` (e.g. `local_payments`, `local_academy`) |
| A front-page / dashboard content block | `public/blocks/nit_*` |
| Any visual / layout / template change | the custom theme `public/theme/nit` |
| Overriding a core template (e.g. a Boost mustache) | **copy it into `theme/nit/templates/...`** — the theme override wins; never edit the original under `theme/boost` |
| Adding a menu link, capability, or setting | plugin `db/*.php` (`access.php`, `settings.php`, `upgrade.php`) or an admin setting — not a core edit |

If something seems to require a core change, stop and look for the extension
point first: a **hook** (`db/hooks.php`), a **callback**, a **theme override**,
or a plugin **external function**. Moodle almost always has one.

## Known exception to clean up

`public/theme/boost/templates/core/login_panel.mustache` was edited directly
(login page left panel). This is the one core modification in the repo and
should be migrated to `theme/nit/templates/core/login_panel.mustache` so
`theme/boost` returns to stock.

## Check on every Moodle upgrade

Two places deliberately shadow code we do not own. Neither edits it, so neither
breaks an upgrade — but neither inherits an upstream change either. Re-read the
original against ours whenever Moodle or `mod_customcert` moves:

- **`public/local/academy/classes/login_manager.php`** mirrors `login/token.php`
  step for step (it exists so the app can be told an account is *blocked* rather
  than just "invalid login" — see AC-4.3.4). If core adds a guard to `token.php`,
  add it here. Diff them:
  `git diff --no-index public/login/token.php public/local/academy/classes/login_manager.php`
  is noise, so just read both — `token.php` is ~110 lines.
- **`public/mod/customcert/element/nitstudentname/`** is a `customcertelement`
  subplugin (additive; it modifies no upstream file). It prints the name captured
  at issue time instead of the live one, because `mod_customcert`'s element
  registry hard-codes the bundled types and cannot be overridden in place. If
  upstream ever makes `studentname` snapshot-aware, drop ours and convert the
  rows back.

## Two plugins of ours that live outside `local/`

Moodle decides where a subplugin lives, so two of ours sit under core directories.
Both are **additive** — they add a directory, they modify nothing upstream — so
neither is a core edit, and both are excluded from the check below:

- **`public/user/profile/field/phone/`** (`profilefield_phone`) — the phone profile
  field. It also owns the site's ONE country ladder,
  `dialcodes::country_for_ip()`: Moodle's configured GeoIP source first, then a
  free no-key online lookup. Both the sign-up country check and `local_payments`
  pricing go through it. Do not write a second IP→country lookup anywhere; a site
  whose `$CFG->geoip2file` points at a missing file works entirely on that second
  rung, and a caller that stops at the first one silently prices every guest on
  the default row.
- **`public/mod/customcert/element/nitstudentname/`** — see the upgrade note above.

## How to verify no core was touched

List everything changed on top of the Moodle base import, excluding our plugins
and theme — the result should be empty (aside from config/CI/docs):

```bash
# 09316d082 is the "first commit" = the stock Moodle import.
for h in $(git log --no-merges --pretty="%h" | grep -v 09316d082); do
  git show "$h" --pretty=format: --diff-filter=M --name-only
done | sort -u \
  | grep -vE '^public/(local|theme/nit|blocks/nit_section)/' \
  | grep -vE '^public/user/profile/field/phone/' \
  | grep -vE '^public/mod/customcert/element/nitstudentname/' \
  | grep -vE '\.upgradenotes/|\.github/|^\.git|docs/|README|SECURITY|robots|config\.php'
```

## Deploying to the server

- Plugin/theme code-only change (no new DB fields, no version bump):
  `git pull && docker compose exec moodle php admin/cli/purge_caches.php`
- New DB schema, capability, or a bumped `version.php`:
  `git pull && docker compose exec moodle php admin/cli/upgrade.php --non-interactive && … purge_caches.php`

## Do NOT commit these (environment-specific)

`000-default.conf`, `docker-compose.yml`, `Dockerfile`, `moodle_backup.sql`,
`moodledata.zip`, `php-moodle.ini` — they are git-ignored; keep them that way.
Note `public/config.php` is environment-specific too and ideally should not be
tracked.

## Handy tools

- `public/local/payments/cli/ws_diagnose.php --token=… [--fix] [--function=NAME]`
  — diagnose/repair web-service `accessexception` for a token.
- `public/local/msgrules/cli/sync.php [--status] [--rebuild] [--user=ID] [--check=FROM,TO]`
  — apply or inspect the messaging rules. `--check` asks **core** whether one user may
  message another and separately reports whether our rules are the reason, which is the
  quickest way to tell a wrong rule from a rule that has not been rebuilt yet.
- `public/local/nit_category/cli/catscope_diagnose.php --category=ID | --all [--user=ID]`
  — what a category landing page will advertise (plans, coupons, offers) and **why** each
  item was kept or dropped: the plan's assignment or the courses it was derived from, and
  the coupon/offer scope rows. The fastest way to tell "the admin scoped it elsewhere"
  from "the rule is wrong".
- `/local/payments/country_diagnose.php[?courseid=ID]` (a **page**, not a CLI — a CLI has no
  request, so it cannot see the proxy headers that are usually the fault) — why a buyer was
  quoted *that* price. Prints, in order: whether an IP lookup can run at all, what address
  the server actually sees (`getremoteaddr()` next to the raw `REMOTE_ADDR` /
  `X-Forwarded-For` and `$CFG->getremoteaddrconf`), what country that resolves to, and which
  of the course's price rows would therefore win. Also takes `?ip=` to look up any address.
  Reach for it whenever "the Egypt price is being ignored": four different causes produce
  that one symptom and this separates them. Linked from every course's pricing page.
