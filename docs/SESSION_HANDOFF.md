# Academy Frontend — AI Session Handoff

> Last updated: 2026-07-26  
> Branch: `frontend-student`  
> Working directory: `D:\My work\NIT\Projects\Academy\academy-frontend`

---

## Project Overview

**Excellence Academy (EA)** is an Arabic-first RTL educational platform for Egyptian students, backed by a **Moodle CMS** at `https://academy2026.nitg-eg.com`.

This repo (`academy-frontend`) is the **Next.js web port** of an existing Flutter mobile app. The UI adapts to web/desktop — it does NOT mirror mobile screens.

---

## Tech Stack

| Concern | Choice |
|---|---|
| Framework | Next.js 16.2.11 (App Router, React 19, TypeScript) |
| Styling | Tailwind CSS v4, `oklch` colors |
| UI components | shadcn/ui via `@base-ui/react` (base-nova style, RTL enabled) |
| i18n | next-intl v4.13.4 — locale from cookie (no URL routing), default `'ar'` |
| State | Zustand v5 (auth, locale, theme stores) |
| Data fetching | TanStack React Query v5 |
| Forms | react-hook-form + zod |
| Fonts | Cairo (default), Baloo Bhaijaan 2 (Kids mode) |
| HTTP client | Axios (`src/lib/axios.ts`) — internal `/api` calls only |
| Backend | Moodle REST + `/local/academy/api.php` (BFF pattern) |

---

## Architecture: Backend-for-Frontend (BFF)

**CRITICAL SECURITY RULE**: The Moodle `wstoken` is stored ONLY in an httpOnly cookie. It NEVER reaches the browser JS. All Moodle calls are proxied through Next.js Route Handlers.

```
Browser → /api/auth/login (Route Handler) → Moodle /login/token.php
Browser → /api/courses (Route Handler) → Moodle REST (wstoken from httpOnly cookie)
```

The `admin_token` (from `MOODLE_ADMIN_TOKEN` env var) is server-only and never sent to the client.

---

## Three Visual Systems (Themes)

Controlled via `data-theme` attribute on `<html>`:

| Theme | Key colors | Font | Notes |
|---|---|---|---|
| Light | `--primary: oklch(0.546 0.245 262.881)` (blue-600) | Cairo | Default |
| Dark | Inverted, darker backgrounds | Cairo | via `.dark` class |
| Kids | Violet primary `oklch(0.530 0.230 295.000)`, warm off-white | Baloo Bhaijaan 2 | Lottie animations enabled |

Theme store: `src/store/useThemeStore.ts` — 4 variants: `system | light | dark | kids`  
FOUC prevention: inline `<script>` in `<head>` reads `ea-theme` from localStorage before React hydrates.

---

## Key Environment Variables

```env
MOODLE_BASE_URL=https://academy2026.nitg-eg.com
MOODLE_ADMIN_TOKEN=<server-only, never NEXT_PUBLIC_>
SESSION_SECRET=<random 32-char string>
NEXT_PUBLIC_GOOGLE_CLIENT_ID=<optional>
```

Session cookie name: `ea-session` (httpOnly, base64-encoded JSON of `AuthSession`).

---

## Completed Work (Phases 0 + 1)

### Phase 0 — Foundations
- [x] `next.config.ts` — next-intl plugin, remote images for Moodle hostname
- [x] `src/i18n/request.ts` — locale from cookie, fallback to `'ar'`
- [x] `src/app/globals.css` — full brand design token system (light/dark/kids, motion tokens, typography scale)
- [x] `src/app/layout.tsx` — Cairo + Baloo Bhaijaan 2 fonts, FOUC prevention script
- [x] `src/app/providers.tsx` — theme init + session check on mount
- [x] `src/store/useThemeStore.ts` — 4-variant theme system
- [x] `src/store/useLocaleStore.ts` — locale toggle (sets cookie + reload)
- [x] `src/store/useAuthStore.ts` — `user`, `isAuthenticated`, `isLoading`, `checkSession()`, `logout()`
- [x] `src/config/constants.ts` — MOODLE_BASE_URL, SESSION_COOKIE, etc.
- [x] `src/config/site.ts` — siteConfig (name, url, whatsappNumber)
- [x] `src/config/lottie.ts` — Kids Lottie slot registry (null-safe)
- [x] `src/lib/theme.ts` — KIDS_ACCENTS, getKidsAccent, MOTION constants
- [x] `src/lib/mlang.ts` — Moodle `{mlang xx}content{mlang}` parser
- [x] `src/lib/react-query.ts` — QueryClient singleton
- [x] `src/lib/axios.ts` — internal apiClient pointing to `/api`
- [x] `messages/ar.json` + `messages/en.json` — full i18n strings (nav, auth, common, etc.)
- [x] `public/assets/logo.svg` + `public/assets/logoW.svg` — brand logos
- [x] `public/assets/lottie/` — 6 Kids Lottie JSON files
- [x] `pnpm-workspace.yaml` — fixed build script permissions for `@parcel/watcher` + `@swc/core`

### Phase 1 — App Shell + Auth
- [x] `src/proxy.ts` — route protection (renamed from deprecated `middleware.ts`)
- [x] `src/app/(auth)/layout.tsx` — auth route group layout
- [x] `src/app/(auth)/login/page.tsx` — login form (react-hook-form + zod)
- [x] `src/app/(auth)/register/page.tsx` — register form
- [x] `src/app/(dashboard)/layout.tsx` — wraps all dashboard routes in `<AppShell>`
- [x] `src/app/(dashboard)/[11 placeholder pages]` — courses, teachers, calendar, lessons, messages, notifications, packages, subscriptions, programs, payments, profile
- [x] `src/app/page.tsx` — home page (wraps AppShell directly, server component)
- [x] `src/app/api/auth/login/route.ts` — BFF: fetchMoodleToken → get user info → set httpOnly cookie
- [x] `src/app/api/auth/logout/route.ts` — deletes session cookie
- [x] `src/app/api/auth/session/route.ts` — returns `{ authenticated, user }`
- [x] `src/app/api/courses/route.ts` — Moodle course search + categories
- [x] `src/app/api/courses/[id]/route.ts` — course detail + contents
- [x] `src/lib/moodle-server.ts` — BFF Moodle client (callMoodleRest, callAcademyApi, fetchMoodleToken)
- [x] `src/lib/session.ts` — httpOnly cookie management
- [x] `src/components/layout/app-shell.tsx` — desktop sidebar + mobile drawer + header + WhatsApp FAB
- [x] `src/components/layout/app-sidebar.tsx` — nav links, language toggle, profile, logout
- [x] `src/components/layout/app-header.tsx` — search bar, notifications, messages, user avatar
- [x] `src/services/auth.service.ts` — calls `/api/auth/*`
- [x] `src/services/course.service.ts` — calls `/api/courses`
- [x] `src/validations/auth.schema.ts` — loginSchema + registerSchema (zod)
- [x] `src/types/index.d.ts` — User, AuthSession interfaces
- [x] `src/types/api.d.ts` — MoodleRestResponse, AcademyApiResponse, etc.

### Bug fixes applied (from other AI's code)
- `callAcademyApi` was using wrong `?action=` param and `Authorization: Bearer` header → fixed to `?function=&token=&alang=` with JSON POST body
- Login route was calling non-existent `get_extended_profile` → fixed to `core_user_get_users_by_field`
- Course search had wrong param names (`cagename`/`value`) → fixed to `criterianame`/`criteriavalue`
- `callMoodleRest` was using GET → fixed to POST with `application/x-www-form-urlencoded`
- `useTranslations` hook incorrectly used in Server Component → removed
- `Button asChild` prop (Radix pattern) was passed to `@base-ui/react` Button → replaced with `Link + buttonVariants`

---

## Pending Tasks (Next Phases)

### Phase 2 — Core Learner Loop (DO NEXT)
- [ ] Real courses listing page with data from Moodle (`/api/courses`)
- [ ] Course detail page (`/api/courses/[id]`) with content tree
- [ ] Completion tracking (view pings to Moodle)
- [ ] Loading states / error states / empty states

### Phase 3 — Monetization
- [ ] Shared `preview_discount` client
- [ ] Kashier checkout flow
- [ ] Packages/subscriptions/programs pages with real data
- [ ] Invoice PDF generation

### Phase 4 — Engagement
- [ ] Quiz component
- [ ] Calendar with real Moodle data
- [ ] Messages (real data)
- [ ] Notifications (polling)
- [ ] Teacher directory + lesson booking
- [ ] Jitsi web SDK integration

### Nice-to-have / pending assets
- [ ] `emptyState` and `homeGreeting` Lottie slots (assets not yet designed)
- [ ] Sound effects: `correct.mp3`, `wrong.mp3`, `complete.mp3` (directory created at `public/assets/sounds/`)
- [ ] Mobile bottom navigation bar for `<lg` viewports
- [ ] Google Sign-In wiring (`/local/googleauth/google_login.php`)
- [ ] Apple SSO (if needed)

---

## Important File Paths

```
src/
├── app/
│   ├── layout.tsx           # Root layout (fonts, i18n provider, FOUC script)
│   ├── page.tsx             # Home page (AppShell + placeholder content)
│   ├── globals.css          # ALL design tokens (light/dark/kids, typography, motion)
│   ├── providers.tsx        # Client: theme init + session check
│   ├── (auth)/
│   │   ├── layout.tsx       # Auth route group layout
│   │   ├── login/page.tsx
│   │   └── register/page.tsx
│   ├── (dashboard)/
│   │   ├── layout.tsx       # Wraps children in <AppShell>
│   │   └── [courses|teachers|...]/page.tsx   # 11 placeholder pages
│   └── api/
│       ├── auth/login/route.ts
│       ├── auth/logout/route.ts
│       ├── auth/session/route.ts
│       └── courses/route.ts + [id]/route.ts
├── components/layout/
│   ├── app-shell.tsx        # Main layout container
│   ├── app-sidebar.tsx      # Desktop/mobile sidebar
│   └── app-header.tsx       # Sticky header
├── lib/
│   ├── moodle-server.ts     # BFF Moodle client (SERVER ONLY - uses 'server-only')
│   ├── session.ts           # httpOnly cookie helpers
│   ├── axios.ts             # Internal apiClient
│   ├── mlang.ts             # Moodle multilang parser
│   ├── theme.ts             # KIDS_ACCENTS, MOTION constants
│   └── react-query.ts       # QueryClient factory
├── store/
│   ├── useAuthStore.ts
│   ├── useThemeStore.ts
│   └── useLocaleStore.ts
├── config/
│   ├── constants.ts
│   ├── site.ts
│   └── lottie.ts            # Kids Lottie slot registry
├── types/
│   ├── index.d.ts           # User, AuthSession
│   └── api.d.ts             # Moodle API response types
├── validations/
│   └── auth.schema.ts
├── services/
│   ├── auth.service.ts
│   └── course.service.ts
└── proxy.ts                 # Route protection (replaces deprecated middleware.ts)
docs/
└── mobile-reference/        # Mobile app assets + theme guide (NOT in public/)
public/
├── assets/
│   ├── logo.svg
│   ├── logoW.svg            # White logo (used on primary bg)
│   ├── lottie/              # 6 Kids Lottie JSON files
│   └── sounds/              # Empty — awaiting correct.mp3, wrong.mp3, complete.mp3
messages/
├── ar.json
└── en.json
```

---

## Moodle API Notes

### REST endpoint
`POST https://academy2026.nitg-eg.com/webservice/rest/server.php`  
Body: `application/x-www-form-urlencoded`  
Params: `wstoken`, `wsfunction`, `moodlewsrestformat=json`, + function-specific params

### Academy API endpoint  
`POST https://academy2026.nitg-eg.com/local/academy/api.php?function=<fn>&token=<token>&alang=<lang>`  
Body: JSON

### Auth flow
1. `POST /login/token.php` → `{ token: wstoken }`
2. `core_webservice_get_site_info` → `{ userid, fullname, userpictureurl }`
3. `core_user_get_users_by_field { field: "id", "values[0]": userid }` → `{ phone1, customfields: [year, ParentPhone] }`
4. Store `{ user, wstoken }` in httpOnly cookie `ea-session`

---

## Dev Setup

```bash
# Install
pnpm install

# Dev server (port 3000)
pnpm dev

# Type check
pnpm exec tsc --noEmit

# Build
pnpm build
```

Build scripts for `@parcel/watcher` and `@swc/core` are enabled in `pnpm-workspace.yaml`.

---

## RTL / i18n Notes

- Locale stored in `locale` cookie (not URL), defaults to `'ar'`
- Use logical CSS properties: `ms-` / `me-` / `ps-` / `pe-` / `start-` / `end-` (NOT `ml-`/`mr-`)
- Sidebar is on the **right** in RTL (linguistically correct for Arabic)
- Mobile drawer slides from `end` side in RTL

---

## Current Build Status

- TypeScript: ✅ no errors
- Dev server: ✅ starts cleanly on port 3000
- Auth proxy (`src/proxy.ts`): ✅ route protection working
- Login page: ✅ renders in Arabic RTL
- Home page: ✅ renders with AppShell (sidebar + header)
- Console errors: ✅ none (fixed `asChild` prop leak from `@base-ui/react`)
