# Theme, Styles & Kids Mode — Guide for the Web (Next.js) Port

_Written for the Next.js dev, from the current Flutter app's actual theme code (`lib/core/theme.dart`, `lib/core/constants/*`, `lib/core/theme/*`, `lib/core/widgets/wave_app_bar.dart`, `lib/core/widgets/bubble_nav_bar.dart`, `lib/core/assets/playful_*.dart`). File paths throughout point at the source of truth in the Flutter repo — when in doubt, that file wins over this doc._

---

## 1. The mental model

There are **4 theme options** the user can pick in Settings → Appearance, but really **3 visual systems**:

1. **Light** — the default "calm, professional educational" look.
2. **Dark** — same structure as Light, different color values.
3. **System** — just follows the OS, resolves to Light or Dark. Not a 3rd visual system.
4. **Child Mode ("Kids theme")** — a distinct, colorful, curvy visual system. Always light-brightness. Not a dark-mode variant, not a recolor — it swaps palette, font family, corner radii, the app bar shape, the bottom nav shape, and unlocks Lottie animations + sound effects that don't exist in Light/Dark at all.

**Key architectural idea to carry over to Next.js**: Light and Dark are *one* theme builder function fed a `brightness` flag; Child Mode is the *same* builder fed an extra `playful: true` flag that swaps a handful of tokens. It is not a fork of the theme — it's parametrized. Structure your Next.js theme the same way (one token-resolution function/hook with a `variant` input), not three copy-pasted CSS files.

---

## 2. Design tokens (colors, spacing, radii, shadows)

Source: `lib/core/constants/app_colors.dart`, `lib/core/constants/app_dimens.dart`.

### 2.1 Colors — Light / Dark

| Token | Light | Dark |
|---|---|---|
| Primary | `#2563EB` (blue-600) | `#60A5FA` (blue-400) |
| Primary light tint | `#DBEAFE` (blue-100) | — |
| Secondary | `#10B981` (emerald-500) | same |
| Accent | `#F59E0B` (amber-500) | same |
| Background (page) | `#F8FAFC` (slate-50) | `#0F172A` (slate-900) |
| Surface (cards) | `#FFFFFF` | `#1E293B` (slate-800) |
| Surface variant / subtle fill | `#F1F5F9` (slate-100) | `#334155` (slate-700) |
| Text primary | `#0F172A` (slate-900) | `#F1F5F9` (slate-100) |
| Text secondary | `#64748B` (slate-500) | `#94A3B8` (slate-400) |
| Border | `#E2E8F0` (slate-200) | `#334155` (slate-700) |
| Error | `#EF4444` (red-500) | same |
| Success | `#10B981` (emerald-500) | same |
| Warning | `#F59E0B` (amber-500) | same |

Semantic pattern: page background sits one step darker/lighter than card surfaces so cards visually "lift" off the page — background ≠ surface, always. Don't collapse them into one gray in the web port.

### 2.2 Colors — Child Mode (Kids theme)

Distinct palette, always light-brightness:

| Token | Value |
|---|---|
| Primary (vivid violet) | `#6C5CE7` |
| Background (warm off-white) | `#FFF7F2` |
| Surface | `#FFFFFF` |
| Border (soft violet tint) | `#F0E6FF` |
| Accent — teal | `#14B8A6` |
| Accent — orange | `#FB923C` |
| Accent — pink | `#EC4899` |
| Accent — yellow | `#FACC15` |

The 5 accents (`[teal, orange, pink, yellow, primary]`) form a **rotating palette** — used to color a *sequence* of things (bottom-nav bubbles, map nodes, badges) by index so neighbors are never the same color: `accents[index % accents.length]`. Implement this as a small helper/hook in the web port (`getAccent(index)`), not a fixed per-item color.

Text/error/success/warning colors are shared with Light (Child Mode doesn't redefine them).

### 2.3 Spacing scale (8pt grid)

| Token | px |
|---|---|
| `xxs` | 4 |
| `xs` | 8 |
| `sm` | 12 |
| `md` | 16 (default gutter/padding) |
| `lg` | 24 (card insets, section padding) |
| `xl` | 32 |
| `xxl` | 40 |
| `xxxl` | 48 |
| `huge` | 64 |

Map straight to Tailwind spacing scale or CSS custom properties (`--space-md: 16px`, etc.) — the numbers already land on common steps.

### 2.4 Corner radii

| Component | Light/Dark | Child Mode |
|---|---|---|
| Card | 20px | **28px** |
| Button | 16px | **22px** |
| Input | 14px | **18px** |
| Dialog | 24px | **28px** |
| Bottom sheet (top corners) | 24px | **28px** |
| Chip | 12px | **18px** |
| Pill (fully rounded) | 999px | 999px |

Child Mode doesn't get new radius *tokens* — it's the same component families, just rounder. In the web port, express this as one CSS variable per component family (`--radius-card`) that gets reassigned under a `[data-theme="kids"]` selector, exactly like color tokens.

### 2.5 Shadows

Brightness-aware, deliberately subtle (no heavy Material drop-shadows):

- **Card (resting)**: Light → `0 4px 12px rgba(15,23,42,0.06)`. Dark → none (cards separate via border, not shadow).
- **Raised (sheets/popovers)**: Light → `0 8px 24px rgba(15,23,42,0.10)`. Dark → `0 6px 16px rgba(0,0,0,0.25)`.

Child Mode doesn't override shadows — it reuses Light's.

### 2.6 Minimum touch target

`48px` minimum height on every interactive control (buttons, nav items). Keep this in the web port too — it's an accessibility floor, not a mobile-only concern.

---

## 3. Typography

Source: `lib/core/theme/app_typography.dart`, `lib/core/constants/text_styles.dart`.

### 3.1 Font families

| Theme | Family | Covers |
|---|---|---|
| Light / Dark | **Cairo** | Latin + Arabic |
| Child Mode | **Baloo Bhaijaan 2** | Latin + Arabic (rounded, bubbly display face) |

Both are Google Fonts — loaded at runtime via the `google_fonts` package on mobile, so **no font files need to be sent as assets**. In Next.js, use `next/font/google` for both (`Cairo`, `Baloo_Bhaijaan_2`) — same free source, no licensing step needed.

The whole point of picking Baloo Bhaijaan 2 for Child Mode: it needs to *read* as playful at a glance, in both English and Arabic, without picking two unrelated fonts per script.

### 3.2 Type scale

Base sizes (px), Light/Dark and Child Mode share the same scale — only the family swaps:

| Style | Size | Weight | Line height | Use |
|---|---|---|---|---|
| `display` | 40 | 700 | 1.15 | Hero/marketing numerals, sparingly |
| `h1` | 32 | 700 | 1.2 | Page titles |
| `h2` | 24 | 700 | 1.25 | Section headers |
| `h3` | 20 | 600 | 1.3 | Card/group titles |
| `body` | 16 | 400 | 1.5 | Default body copy |
| `bodyStrong` | 16 | 600 | 1.5 | Emphasized body/inline labels |
| `caption` | 14 | 400 | 1.4 | Secondary text, metadata |
| `small` | 12 | 400 | 1.35 | Fine print, timestamps |

Plus a couple of raw legacy sizes still in use (`bold14`, `regular14`, `medium14`, `bold16`, `medium10`) — treat the semantic scale above as canonical for new work.

### 3.3 User-adjustable font weight & size (accessibility)

Two independent user preferences layered on top of the base scale — worth replicating on web for parity:

- **Font size** — 4 steps, applied as a **global multiplier**, not per-style overrides: `small=0.85×`, `normal=1×`, `large=1.15×`, `extraLarge=1.30×`. On mobile this rides `MediaQuery.textScaler` so literally every `Text` scales without per-component work. On web, the equivalent is a single `font-size` multiplier on `:root` (e.g. a CSS variable multiplying `rem` base, or a `data-font-scale` attribute driving `html { font-size: X% }`) — don't hardcode scale logic into individual components.
- **Font weight** — 4 steps (`light -2`, `regular 0`, `medium +2`, `bold +3`), applied as a **relative shift** across the whole scale (every style's intrinsic weight moves by the same delta), not an absolute weight. This preserves the hierarchy (h1 stays heavier than body) while letting the user go lighter/heavier overall. Steps are spaced 200 CSS-weight-units apart specifically because a single 100-unit shift is imperceptible, especially on already-bold headings.

Both preferences apply identically in Child Mode — only the font *family* differs there, not the sizing/weight logic.

---

## 4. Theme system architecture (how to structure this in Next.js)

Source: `lib/core/theme.dart`.

The Flutter theme is built by **one function** with two knobs — `brightness` (light/dark) and `playful` (bool, Child Mode) — that resolves every token (color, radius, font family) via ternaries, then feeds them into one shared `ThemeData` structure (buttons, inputs, cards, dialogs, sheets, chips, nav bar, app bar all themed centrally, once).

**Recommended Next.js equivalent**: don't build 3 separate CSS/Tailwind theme files. Build one **token resolver** (a hook, a `getThemeTokens(variant)` function, or CSS custom properties keyed by a root attribute) with the same two axes:

```ts
type ThemeVariant = "light" | "dark" | "kids";
// "kids" is always light-brightness — same idea as ThemeMode.light + playful:true on mobile
```

Concretely, the cleanest web mapping is **CSS custom properties switched by a `data-theme` attribute on `<html>`**, exactly mirroring how the Flutter `ChildModeTheme` extension is "present or absent" on the resolved theme:

```css
:root, [data-theme="light"] {
  --color-primary: #2563EB;
  --color-bg: #F8FAFC;
  --color-surface: #FFFFFF;
  --radius-card: 20px;
  --font-family: "Cairo", sans-serif;
  /* ...etc, full token list from §2 */
}

[data-theme="dark"] {
  --color-primary: #60A5FA;
  --color-bg: #0F172A;
  --color-surface: #1E293B;
  /* radii unchanged from light */
}

[data-theme="kids"] {
  --color-primary: #6C5CE7;
  --color-bg: #FFF7F2;
  --color-surface: #FFFFFF;
  --radius-card: 28px;
  --radius-button: 22px;
  --font-family: "Baloo Bhaijaan 2", sans-serif;
  /* + the 5 rotating accents as --color-accent-1..5 */
}
```

`system` isn't a 4th variant — it's "no explicit choice, resolve `light`/`dark` from `prefers-color-scheme` at runtime," same as mobile's `ThemeMode.system`.

Components should read tokens (`var(--radius-card)`, `var(--color-primary)`), never hardcode a value — that's what makes Child Mode "just work" everywhere once the token layer is right, instead of needing an `if (kidsMode)` branch in every component (mobile explicitly avoids that pattern; don't reintroduce it on web).

### 4.1 Detecting Kids mode inside a component

Mobile's pattern: a theme *extension* that's only attached to the Child theme, so any widget can ask "is Kids mode active?" cheaply via `Theme.of(context).extension<ChildModeTheme>()` — no prop drilling, no separate global store to keep in sync.

Web equivalent: a small context/hook, e.g. `useTheme()` returning `{ variant, isKids, accents }`, backed by the same `data-theme` attribute (read once via `useSyncExternalStore` or a simple React context provider at the root) — not a prop threaded through every component tree.

---

## 5. Kids theme — the pieces beyond colors/radii

This is the part that makes Kids mode feel like a different app, not just a recolor. Four pieces, in order of effort:

### 5.1 The wavy app bar

Source: `lib/core/widgets/wave_app_bar.dart`.

In every other theme, the app bar is a flat, standard header. In Kids mode **only**, it grows a colored wavy band below the normal bar content (title/icons stay in the top, untouched, flat band; the wave is a second band ~30px tall directly beneath it, filled with the Kids primary color, clipped into a gentle two-hump scallop shape).

Important detail: the wave is **additive height**, not a reshaping of the existing bar — the real header content never sits inside the curvy part, so nothing overlaps the scallops. Implement on web as: normal header (flex row, fixed height) + a `<svg>` or `clip-path`-cut `<div>` immediately below it, height ≈30px, only rendered when `variant === "kids"`.

Approximate wave shape (two asymmetric humps, safe to freehand-match by eye rather than pixel-match the Flutter Bezier curve):
- Starts flat at the left edge
- Dips down toward a low point around 25% width
- Rises to a mid-height plateau around 50%
- Rises again to its highest point around 75%
- Settles back down toward the right edge

An SVG `<path>` with two `Q` (quadratic bezier) curves reproduces this fine; exact control points aren't load-bearing, the *silhouette* is.

### 5.2 The bubble bottom navigation

Source: `lib/core/widgets/bubble_nav_bar.dart`.

Replaces the standard flat bottom nav bar **only in Kids mode**. Each tab is a circular "bubble" icon. The *selected* tab is the only one that expands into a filled pill (background = that tab's rotating accent color) revealing its label; unselected tabs stay icon-only circles in a muted color. Selecting a tab animates the pill open with a slight overshoot (`easeOutBack`-style spring), ~250ms.

Web notes:
- Whole bar sits in a floating rounded card (28px radius) with a soft violet-tinted shadow, not a flush-to-edge bar.
- Each tab's accent color comes from the rotating accent list by index (§2.2) — tab 0 = teal, tab 1 = orange, etc., wrapping if there are more tabs than accents.
- Width transition on the label (`AnimatedSize` on mobile) — a CSS `width`/`max-width` transition with `overflow: hidden` on the label span reproduces this.
- A light haptic/selection sound fires on tab change on mobile (`selectionHaptic()`) — optional on web, skip or use a subtle click sound if you want full parity.

### 5.3 Lottie animations (playful flourishes)

Source: `lib/core/assets/playful_assets.dart`, `lib/core/assets/playful_lottie_scene.dart`.

**This is the part where assets matter — see §6.**

Design pattern (important — copy this, not just the assets): a **named-slot registry**, `Map<slot, filePath?>`, where every slot defaults to `null` (no animation, nothing bundled). A slot with no asset renders nothing (or a plain fallback like a spinner) instead of erroring — the app is fully functional with zero Lottie files present. Adding an animation later is a one-line change to the registry, not a component rewrite.

Slots currently defined (mobile), for you to mirror 1:1 as a `const KIDS_LOTTIE_SLOTS` map in the web port:

| Slot | Purpose | Asset (to be sent) |
|---|---|---|
| `quizSuccess` | Celebration on finishing a quiz | `bird-confity.json` |
| `celebration` | Generic success (certificate, payment, subscription activated…) | `Celebration.json` |
| `childModeOn` | Full-screen flourish the moment the user switches into Kids mode | `child_mode_on.json` |
| `loading` | Playful loading indicator, replaces a plain spinner | `loading.json` |
| `courseDetails` | "Reading" mascot on the course-details screen | `Reading.json` |
| `quizScreen` | "Solving" mascot on the quiz screen | `solving.json` |
| `emptyState` | Empty-list illustration | _not yet designed — slot exists, asset pending_ |
| `homeGreeting` | Mascot near the home-tab greeting | _not yet designed — slot exists, asset pending_ |

Behavioral rules to preserve on web:
- Every one of these is **Kids-mode-only** — in Light/Dark, the component renders nothing/its non-playful equivalent (skeleton, `<Spinner />`), full stop.
- A broken/missing file must **never crash the screen** — wrap the lottie-player in a try/fallback (e.g. `lottie-react`'s `onError` → swap to fallback content).
- `loading` specifically has a short grace period (~450ms) before it appears, so a fast load never flashes it — worth keeping, it avoids a jarring flicker.
- There's a fancier "hero" variant on mobile (mascot plays big/full-screen once per screen visit, then flies down and shrinks into its resting spot in the layout) used for `courseDetails`/`quizScreen`. This is a nice-to-have, not required for parity — a straightforward inline looping Lottie in its resting spot is a perfectly reasonable v1 on web. Only chase the fly-in effect if there's time.

Recommended web library: **`lottie-react`** (thin wrapper over `lottie-web`) or `@lottiefiles/dotlottie-react` if the assets get converted to `.lottie` — either works with the plain-JSON files the mobile team will send.

### 5.4 Sound effects (quiz only, Kids mode only)

Source: `lib/features/quiz/presentation/services/quiz_sound_service.dart`.

Outside Kids mode, the quiz is silent (only haptics, which don't have a meaningful web equivalent — skip). In Kids mode, three short clips play:

| Cue | File (to be sent) | When |
|---|---|---|
| Correct answer | `correct.mp3` | Answer submitted, marked correct |
| Wrong answer | `wrong.mp3` | Answer submitted, marked wrong / an error occurs |
| Quiz complete | `complete.mp3` | Whole quiz finished |

"Option selected" (just picking an option, before submitting) uses a generic system click sound on mobile, not a bundled clip — on web, either skip it or use a tiny synthesized/system-style click, not worth a dedicated asset.

Implementation note: mobile gives each clip its own low-latency player so rapid-fire answers don't stall waiting on the previous clip. On web, `new Audio(url)` per play call (or a small pool) avoids the same stutter — don't share one `<audio>` element across all three cues.

---

## 6. Assets — what's coming separately, and where each one goes

The mobile/design team will send these as raw files; this doc just maps each one to its slot so nothing gets misplaced. Nothing above requires guessing filenames — table already has them.

**Lottie JSON** (6 files, `assets/lottie/` on mobile):
`bird-confity.json`, `Celebration.json`, `child_mode_on.json`, `loading.json`, `Reading.json`, `solving.json` — mapped in §5.3.

**Sound MP3** (3 files, `assets/sounds/` on mobile):
`correct.mp3`, `wrong.mp3`, `complete.mp3` — mapped in §5.4.

**Fonts**: none to send — Cairo and Baloo Bhaijaan 2 are both pulled from Google Fonts at build/runtime on both platforms.

**Two Lottie slots have no asset yet** (`emptyState`, `homeGreeting`) — build the slot/registry entry now so wiring one in later is a one-line change, same as mobile.

---

## 7. Motion vocabulary

Source: `lib/core/animations/motion.dart`.

One shared timing system, reused everywhere instead of every component picking its own duration:

| Token | Duration | Use |
|---|---|---|
| `fast` | 150ms | Quick state flips — chip select, icon swap, press feedback |
| `medium` | 250ms | Default for most UI — cross-fades, entrances, expand/collapse |
| `slow` | 350ms | Page transitions, hero flights |

| Curve | CSS equivalent | Use |
|---|---|---|
| `emphasized` | `cubic-bezier(0.215, 0.61, 0.355, 1)` (≈ ease-out-cubic) | Entrances, emphasis |
| `standard` | `cubic-bezier(0.645, 0.045, 0.355, 1)` (≈ ease-in-out-cubic) | Cross-fades, reversible transitions |

Page transitions app-wide: a calm fade-through — incoming page fades in while easing up a few px (`translateY(2%) → translateY(0)` + `opacity 0→1`), outgoing page fades out underneath. In Next.js this maps cleanly to a `framer-motion` `AnimatePresence` wrapper on route changes, same curve/duration.

This vocabulary is **shared** by Light/Dark/Kids — Kids mode doesn't get its own timing system, just extra motion moments layered on top (the bubble-nav spring, the hero fly-ins) using these same base tokens.

---

## 8. Settings UI & switching behavior

Source: `lib/features/settings/presentation/pages/settings_screen.dart`, `lib/core/settings/app_settings_enums.dart`.

- Theme picker is a single 4-way choice: System / Light / Dark / Kids — not a separate "enable kids mode" toggle plus a separate light/dark picker. Mirror that as one control on web, not two.
- Persisted client-side (secure storage on mobile) as a plain string key (`"system" | "light" | "dark" | "child"`) inside a JSON settings blob alongside language/font-size/font-weight. On web, `localStorage`/cookie + your existing settings persistence is the equivalent — no backend involvement for this preference.
- **Switching into Kids mode specifically** plays the `childModeOn` full-screen Lottie celebration, then routes the user to the Home tab once the animation completes. Worth keeping as a delight moment — it's the one time an animation plays *outside* its normal contextual slot, deliberately, as an onboarding beat into the new mode.

---

## 9. Quick-reference checklist for the web implementation

- [ ] One token-resolution layer (CSS custom properties keyed by `data-theme`, or a themed hook) — not 3 parallel component trees.
- [ ] `system` resolves via `prefers-color-scheme`, isn't a stored 4th variant.
- [ ] Kids mode = light-brightness + swapped palette + swapped font + bigger radii + wave app bar + bubble nav + Lottie/sound unlocked — never a dark-mode variant.
- [ ] Rotating 5-color accent list for anything rendered as a sequence in Kids mode (nav tabs, map nodes, badges) — index into it, don't hardcode per-item colors.
- [ ] Cairo (default) / Baloo Bhaijaan 2 (Kids) via `next/font/google` — no font files needed from the mobile team.
- [ ] Lottie registry: named slots, every slot nullable/optional, missing-asset-safe, Kids-mode-gated. Table in §5.3 is the exact slot list to mirror.
- [ ] Sound: 3 clips, quiz-only, Kids-mode-only, low-latency/pooled playback. Table in §5.4.
- [ ] Shared motion tokens (150/250/350ms, two named curves) used everywhere, including the global page-transition fade-through.
- [ ] User font-size (4 steps, global multiplier) and font-weight (4 steps, relative shift) preferences apply identically across all three variants.
- [ ] One theme picker control (System/Light/Dark/Kids), not a separate kids toggle.
