---
name: apply-brand-colors
description: Rewire a specific Moodle page/route to fully use the theme_nit Brand Colors variables (--nit-brand-*), so every colour resolves from the palette and the page matches the site brand. Covers all situations — Arabic (RTL) and English (LTR), light and dark, and every screen size (mobile → desktop). Use when the user gives a page URL/route and asks to "apply brand colors", "brand this page", "make it use the colour variables", or fix off-brand / hard-coded colours on a page.
---

# Apply Brand Colors to a page

Take one page (a URL or route on the Moodle site) and make **every colour on it**
resolve from the theme_nit Brand Colors palette (`--nit-brand-*`), so it matches
the brand and re-skins automatically. Handle all states: **RTL (ar) + LTR (en)**,
**dark (the brand default) + light**, and **every breakpoint**.

## Golden rule — never touch core

All overrides live in **`theme_nit`** — `public/theme/nit/scss/components/*.scss`
(or a small page-specific partial you add there). **Never** edit Moodle core or
`theme/boost`. Our SCSS compiles *after* Boost in one stream, so a rule with the
**same selector/specificity wins by cascade order**, and a more specific selector
always wins. When core forces a colour, match its selector in theme_nit and point
it at a brand token. (See existing examples: the login form, the `.moremenu`
admin nav, and `#page.drawers .main-inner` in `_login.scss` / `_tabs.scss` /
`post.scss`.)

## The palette — role → variable → what uses it

The source of truth is `theme_nit_brand_roles()` in `public/theme/nit/lib.php`.
Pick the role by its **job**, never by its hue. 16 roles, each a live custom
property that defaults to Group 1 (a component in `.nit-brand-2` / `.nit-brand-3`
re-reads the same names from that group):

| Role | Variable | Use it for |
|------|----------|------------|
| Primary | `--nit-brand-primary` | background of the main button, checked toggles, progress fill, notification dots, navbar background |
| Secondary | `--nit-brand-secondary` | background of secondary / dual buttons |
| Accent | `--nit-brand-accent` | non-text accent only (reserved) — **not** for text; use Accent Text for links/words/underlines |
| Accent Text | `--nit-brand-accenttext` | text of links, important words, underlines / active indicators (drives Bootstrap `$link-color`) |
| Background | `--nit-brand-background` | page background |
| Surface | `--nit-brand-surface` | background of cards, dropdowns, side menu, inputs, tooltips, tables, page sections |
| Text primary | `--nit-brand-textprimary` | main text, text in buttons, text in inputs, navbar text, navbar underline |
| Text secondary | `--nit-brand-textsecondary` | secondary text, placeholders |
| Border primary | `--nit-brand-borderprimary` | main border colour (cards, dividers, inputs) |
| Border secondary | `--nit-brand-bordersecondary` | secondary / ghost button borders |
| Hover Background | `--nit-brand-hoverbackground` | background of anything on hover |
| Hover Text | `--nit-brand-hovertext` | text / links on hover |
| Error | `--nit-brand-error` | errors, danger / destructive actions, invalid fields |
| Success | `--nit-brand-success` | success, enrolled / active / paid, positive states |
| Warning | `--nit-brand-warning` | warnings, caution, pending / expiring |
| Info | `--nit-brand-info` | neutral notices, tips, hints |

### Derived shades (when a base role isn't enough)
Never hard-code a hex. Derive tints, gradients and opacity from a role with
`color-mix` so it still tracks the brand:
- Tint fill (badge / alert bg): `color-mix(in srgb, var(--nit-brand-success) 15%, transparent)`
- Faint divider over a surface: `color-mix(in srgb, var(--nit-brand-textprimary) 8%, transparent)`
- Two-stop gradient: `linear-gradient(135deg, color-mix(in srgb, var(--nit-brand-accent) 72%, #000), var(--nit-brand-accent))`
- Modal scrim: `color-mix(in srgb, var(--nit-brand-background) 72%, transparent)`
- Drop shadows stay neutral (`rgba(0,0,0,.35)`) — a shadow is not a brand colour.

### Buttons — text must stay readable on a fill
Bootstrap auto-contrast can flip a button label to black on a mid-tone fill. Force
the brand foreground on solid brand buttons: `--bs-btn-color: var(--nit-brand-textprimary)`.

## Procedure

Work on the local site (`http://localhost:8080`, bind-mounted to this repo,
container `moodle_app`). Some pages need an admin session — inspect what you can
reach; drive the browser with the MCP browser tools.

1. **Open + inspect.** Load the page. For every visible element read the computed
   `background-color`, `color`, and `border-color`, and note which are off-brand
   (a raw hex / grey from core, or white on the dark brand). Read the actual CSS
   rule that sets each off-brand colour (walk `document.styleSheets`) so you know
   the exact selector to override.
2. **Map each colour to a role** using the table above — by job, not hue. Backgrounds
   → Background / Surface; text → Text primary / secondary; borders → Border
   primary / secondary; hovers → Hover Background / Hover Text; status → Error /
   Success / Warning / Info; the one call-to-action → Primary; alternates → Secondary.
3. **Write the override in theme_nit**, matching the core selector (same or higher
   specificity) so it wins by cascade order. Put it in the most fitting
   `scss/components/*.scss` (e.g. `_forms.scss`, `_tabs.scss`, `_login.scss`,
   `_cards.scss`) or add a new partial. Use `var(--nit-brand-*)`, never a hex.
4. **Cover every situation:**
   - **RTL (ar) + LTR (en):** use logical properties (`inset-inline-*`,
     `margin-inline-*`, `padding-inline-*`, `border-inline-*`) — never left/right.
     Verify by switching the site language to Arabic and back.
   - **Dark + light:** the brand is dark; make sure text keeps ≥ 4.5 contrast on
     its background in both. Don't leave `.bg-white` / `.bg-light` islands with
     white text (scope a fix to those form controls if needed).
   - **Small + large screens:** resize the viewport (mobile 375, tablet 768,
     desktop 1280). No horizontal overflow; wide content scrolls in its own box.
5. **Rebuild + purge** (see below).
6. **Verify** every change in the browser (see checklist). Never call it done
   without measured proof.

## Tooling

Rebuild the theme CSS after any SCSS change (path conversion off for container paths):
```bash
export MSYS_NO_PATHCONV=1
docker exec moodle_app php /var/www/html/admin/cli/purge_caches.php
docker exec moodle_app php /var/www/html/admin/cli/build_theme_css.php --themes=nit
```

Lint PHP if you touched it:
```bash
docker exec moodle_app php -l /var/www/html/public/theme/nit/<file>.php
```

Verify a colour in the browser (computed value + WCAG contrast) — inject or read
the real element and compute the ratio; a text/background pair must be ≥ 4.5:
```js
const lum=(rgb)=>{const m=rgb.match(/[\d.]+/g);let[r,g,b]=m.slice(0,3).map(Number);
  [r,g,b]=[r,g,b].map(v=>{v/=255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4)});
  return 0.2126*r+0.7152*g+0.0722*b};
const ratio=(a,b)=>{const l1=lum(a),l2=lum(b),hi=Math.max(l1,l2),lo=Math.min(l1,l2);
  return ((hi+0.05)/(lo+0.05)).toFixed(2)};
```
The full theme CSS is loaded on every page, so you can read any selector's rule
or inject sample markup on a page you can reach and measure it faithfully.

After a rebuild the page must be **reloaded** to pull the new CSS (the loaded
stylesheet is otherwise stale).

## Verification checklist (all must pass)

- [ ] No raw hex / core grey remains on the page — every colour is a `var(--nit-brand-*)` (or a `color-mix` of one).
- [ ] Every text/background pair measures **≥ 4.5** contrast (large/bold text ≥ 3).
- [ ] Buttons: solid brand buttons have brand-white labels (no black auto-contrast).
- [ ] Hover states use `--nit-brand-hoverbackground` / `--nit-brand-hovertext`.
- [ ] **Arabic (RTL)** and **English (LTR)** both render correctly — no flipped spacing, no clipped text.
- [ ] **Mobile, tablet, desktop** all render with no horizontal overflow.
- [ ] Only `theme_nit` files changed (`git status` shows nothing under `public/lib`, `public/theme/boost`, or other core paths).

## Deploy

Changes are code-only (no DB). On the server:
```bash
git pull && docker compose exec moodle php admin/cli/purge_caches.php
```
