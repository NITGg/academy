# Course Details — Web (Next.js) Implementation Guide

This document explains how **Course Details** works in the Flutter app so a web developer can build an equivalent screen in **Next.js**. It covers:

1. The content endpoint and its exact response shape
2. How each activity type is detected and routed
3. How each content type is opened/viewed **inside the app** (PDF, YouTube, direct video, audio, quizzes, certificates)
4. The **Normal UI** and the **Kids UI** (Child Mode)
5. The **"don't download / don't save"** policy and its one exception (certificates)
6. The **certificate WebView + JS-injection** pattern (hiding the old site's chrome before the file URL is known)
7. Recommended Next.js packages for each job

> **Note on source access:** you won't have the app's source repository. This guide is self-contained — every endpoint, response shape, and behavior you need is described here. The file paths in [§14](#14-source-map-flutter--what-to-read) are the Flutter references those descriptions came from; if anything is unclear or you want to see how a specific piece is implemented, **ask us for that individual file** and we'll share it. Don't assume you can browse the repo.
>
> Backend note: the app talks to a **Moodle** instance through Moodle web services (`/webservice/rest/server.php`) plus two custom plugins:
> - `local/multitopics/getalltopics.php` — the course-content tree (the main endpoint below)
> - `local/academy/api.php` — the native quiz API (`function=...`)
>
> Almost every URL is authenticated by appending `?token=<wstoken>` (a Moodle web-service token). This is how the current app and the old website both work. See [Security & the token model](#12-security--the-token-model) for what this means on the web.

---

## 1. High-level flow

```
Course card ──tap──▶ Course Details screen
                        │
                        ├── GET /local/multitopics/getalltopics.php   (course tree)
                        ├── GET core_completion_get_activities_completion_status  (progress)
                        │
                        ▼
              Render sections → activities
                        │
                 user taps an activity
                        │
        ┌───────────────┼───────────────────────────────┐
        ▼               ▼               ▼                ▼
   modname==quiz   video/youtube    application/pdf   everything else
   → Quiz flow     → player         → in-app PDF      → in-app WebView
                                       viewer            (with JS injection)
                                                          │
                                              certificate download intercepted
                                              → PDF viewer (share ALLOWED)
```

Two decisions drive the routing:

- **`modname`** (Moodle module type: `quiz`, `resource`, `url`, `page`, `forum`, `customcert`, `jitsi`, …)
- **`resourcetype`** (the file MIME type for file resources: `video/mp4`, `audio/mpeg`, `application/pdf`, `image/png`, …) plus the file URL extension.

The Flutter routing logic lives in:
- `lib/features/home/presentation/pages/course_details/details_screen.dart` (`_handleActivityTap`)
- `.../content_handlers.dart` (`ContentHandlers.handleContent`)
- `.../type_checkers.dart` (`ContentTypeChecker`)

---

## 2. The content endpoint

### Request

```
GET {MOODLE_BASE}/local/multitopics/getalltopics.php
      ?courseid={courseId}
      &wstoken={token}
      &moodlewsrestformat=json
      &lang={ar|en}
```

- `lang` matters: the server localizes some strings (notably each activity's `availabilityinfo` restriction message) **server-side** at request time. Pass `ar` or `en` to match the UI language.
- `wstoken` is the current user's Moodle web-service token.

Reference: `CoursesRemoteDataSource.getCourseContentNew` in
`lib/features/home/data/datasources/couses_remote_data_source.dart`.

### Response shape

```jsonc
{
  "courseid": 123,
  "fullname": "Course full name",       // may contain {mlang} markup — see §11
  "shortname": "SHORT",
  "format": "topics",
  "isavailable": true,
  "status": "available",

  // Live-session banners (Zoom / Jitsi / Google Meet) shown ABOVE the list.
  // Loosely typed; keys vary. Treat as optional.
  "other_fields": { /* zoom/jitsi/meet metadata */ },

  // The section tree. Each "parent" is a section header; its "activities"
  // are the tappable rows.
  "parents": [
    {
      "id": "45",
      "sectionnum": 1,
      "name": "Unit 1 — Introduction",   // may contain {mlang}
      "parent": true,
      "topics": [ /* optional nested subsections, same shape */ ],
      "activities": [
        {
          "id": "980",                   // course-module id (cmid) — IMPORTANT
          "instance": 12,                // module instance id (for view tracking)
          "modname": "resource",         // quiz|resource|url|page|forum|customcert|jitsi|...
          "name": "Lecture 1 slides",
          "sectionnum": "1",
          "visible": true,
          "uservisible": true,
          "url": "https://.../mod/resource/view.php?id=980",
          "tags": [],
          "modicon": "https://.../pix/icon.svg",
          "resourcetype": "application/pdf", // MIME type for file resources
          "fileurl": "https://.../webservice/pluginfile.php/.../slides.pdf",
          "locked": false,               // gated by an unmet access restriction
          "availabilityinfo": "",        // HTML reason shown when locked
          "jitsi_session": null          // present only for jitsi activities
        }
      ]
    }
  ]
}
```

### Field reference (the ones that matter)

| Field | Meaning / how it's used |
|---|---|
| `parents[]` | Sections. Render `name` as a header, then its `activities`. |
| `activities[].id` | **cmid** (course-module id). Used as the quiz `cmid`, for completion, and view tracking. Parse to int. |
| `activities[].instance` | Module instance id. Needed to register a "view" (auto-completion) — see §9. `0` when absent. |
| `activities[].modname` | Primary router key. `quiz` → native quiz; else fall through to content handling. |
| `activities[].resourcetype` | MIME type of a file resource (`video/…`, `audio/…`, `application/pdf`, `image/…`). Empty for non-file modules — then fall back to `modname`. |
| `activities[].fileurl` | Direct file URL (PDF/MP4/…). **Empty string is normalized to null** — for non-file modules (customcert, forum) use `url` instead. |
| `activities[].url` | Moodle module page URL. Used when there's no `fileurl`. |
| `activities[].locked` | `true` = restricted; render dimmed, non-tappable, show `availabilityinfo` as subtitle. **Show it, don't hide it** — students should see what's coming and what unlocks it. |
| `activities[].availabilityinfo` | HTML restriction message. Strip tags (`replace(/<[^>]*>/g, '')`) before showing. |
| `activities[].jitsi_session` | Live-session object (server URL, room, JWT, recordings, whiteboard). Only for the live-class integration. |
| `other_fields` | Zoom/Jitsi/Meet banner data shown above the list. Optional. |

The content URL the app actually opens is: **`fileurl ?? url`**.

### Completion / progress (optional but recommended)

```
GET {MOODLE_BASE}/webservice/rest/server.php
      ?wsfunction=core_completion_get_activities_completion_status
      &wstoken={token}
      &moodlewsrestformat=json
      &courseid={courseId}
      &userid={userId}
```

Response → `statuses[]` where each entry has `cmid`, `state`, `tracking`:

- `tracking`: `0` = not tracked (show no indicator), `1` = **manual** (student can toggle), `2` = **automatic** (system-driven).
- `state`: `0` incomplete, `1` complete, `2` complete-pass, `3` complete-fail.

Build a `Map<cmid, {state, tracking}>` and:
- show a check/clock/cross icon per activity,
- compute overall progress `% = done / total` for a header bar,
- for manual activities, allow toggling via `core_completion_update_activity_completion_status_manually` (`cmid`, `completed=1|0`).

Any error here should yield an **empty map**, never break the content list.

---

## 3. Content type detection (the router)

Port of `ContentTypeChecker` (`type_checkers.dart`) + `ContentHandlers.handleContent`:

```ts
// The order matters — check in exactly this sequence.
function routeActivity(activity: Activity) {
  // 0. Quiz is decided by modname, before any URL inspection.
  if (activity.modname === "quiz") return openQuiz(activity);        // §7

  // 1. Live session (Jitsi) — only if you port live classes.
  if (activity.modname === "jitsi" && activity.jitsi_session)
    return openLiveSession(activity);

  const url = activity.fileurl || activity.url;
  const mime = activity.resourcetype;

  // 2. YouTube (hosted video)
  if (isYouTube(url)) return openYouTube(url);                       // §5

  // 3. Direct video file
  if (isVideoFile(url, mime)) return openVideoPlayer(url);           // §5

  // 4. PDF (incl. certificates when reached as a file)
  if (isPdf(url, mime)) return openPdf(url, { allowDownload: false });// §4

  // 5. Moodle quiz web page (legacy) / forum / anything else
  if (url.includes("mod/quiz") || url.includes("quiz"))
    return openWebView(url, "quiz");                                 // §6
  if (url.includes("mod/forum") || url.includes("forum"))
    return openWebView(url, "forum");                               // §6

  // 6. Fallback: generic in-app WebView with UI injection
  return openWebView(url, "content");                               // §6
}

const isYouTube = (u: string) =>
  u.includes("youtube.com") || u.includes("youtu.be");

function isPdf(u: string, mime?: string) {
  if (mime?.includes("application/pdf")) return true;
  const lower = u.toLowerCase();
  if (lower.endsWith(".pdf")) return true;
  if (u.includes("mod_resource") && lower.includes(".pdf")) return true;
  return false;
}

function isVideoFile(u: string, mime?: string) {
  if (mime?.startsWith("video/")) return true;
  const exts = [".mp4", ".avi", ".mov", ".mkv", ".webm", ".ogg", ".3gp"];
  const lower = u.toLowerCase();
  if (exts.some((e) => lower.endsWith(e))) return true;
  // mod_resource files sometimes have no clean extension: if it's a resource
  // and not a PDF, treat as video (matches the app's heuristic).
  if (u.includes("mod_resource")) {
    if (exts.some((e) => lower.includes(e))) return true;
    if (!isPdf(u, mime)) return true;
  }
  return false;
}
```

> **Audio note:** the app does not have a separate audio route — `resourcetype` `audio/*` gets the `audiotrack` icon in the list, but on tap it falls through `isVideoFile`'s `mod_resource` branch into the same media player (which plays audio fine). On the web you can do the same (an `<audio>`/media player), or branch `mime.startsWith("audio/")` to a dedicated audio player. Either is faithful; a dedicated audio card is a nicer web UX.

---

## 4. PDFs — in-app, view-only

**Native behavior** (`pdf_handler.dart`):
1. Ensure the URL carries `?token=`.
2. Download the file to a temp dir (with a progress dialog + cancel), cache by filename.
3. Open a full-screen **in-app PDF viewer** (`flutter_pdfview`) — swipe pages, no browser chrome.
4. **`allowShare` is `false` for course content** → no share/save button. It is `true` **only for certificates** (see §8).

**Web equivalent — do NOT hand the URL to the browser's native PDF viewer** (that exposes the download/print toolbar and the raw file URL). Render it yourself so it stays view-only:

- Use **pdf.js** via **`react-pdf`** (`pdfjs-dist`) to render pages to `<canvas>`.
- Fetch the bytes through **your own Next.js route handler / API proxy** so the `token` never appears in the client URL and the file isn't a direct link (see §12). Render from an in-memory `Blob`/`ArrayBuffer`, never an `<a href>` or `<iframe src>` to the file.
- Do not render a download button for course content.

```tsx
// components/PdfViewer.tsx
import { Document, Page, pdfjs } from "react-pdf";
pdfjs.GlobalWorkerOptions.workerSrc = "/pdf.worker.min.js"; // self-host the worker

export function PdfViewer({ src, allowDownload = false }: { src: Blob | string; allowDownload?: boolean }) {
  const [pages, setPages] = useState(0);
  return (
    <div onContextMenu={(e) => !allowDownload && e.preventDefault()}>
      <Document file={src} onLoadSuccess={({ numPages }) => setPages(numPages)}>
        {Array.from({ length: pages }, (_, i) => (
          <Page key={i} pageNumber={i + 1} renderTextLayer={false} renderAnnotationLayer={false} />
        ))}
      </Document>
    </div>
  );
}
```

- Load the PDF bytes via a proxy: `const blob = await fetch(`/api/file?cmid=${id}`).then(r => r.blob())` — the API route adds the token server-side and streams the file. The browser tab never sees the Moodle URL or token.

Recommended packages: **`react-pdf`** (or `@react-pdf-viewer/core`), **`pdfjs-dist`**.

---

## 5. Video & audio

### YouTube (`youtube_handler.dart`)

Native: extracts the video id, opens a full-screen dialog with `youtube_player_flutter` (autoplay, allows landscape, close button, optional watermark overlay).

Web:
- Use **`react-youtube`** (wraps the YouTube IFrame API) or a plain privacy-friendly embed (`youtube-nocookie.com`).
- You can't fully hide YouTube's own controls, but disable related videos and keep it inside a modal:
  ```tsx
  <YouTube videoId={id} opts={{ playerVars: { autoplay: 1, rel: 0, modestbranding: 1 } }} />
  ```
- Extract the id yourself (mirror `YoutubePlayer.convertUrlToId`): handle `youtu.be/<id>`, `watch?v=<id>`, `/embed/<id>`.

### Direct video files (`platform_player.dart`)

Native: `video_player` + `chewie` (controls, fullscreen, autoplay), portrait-lock lifted while open.

Web:
- Use a token-aware proxy for the source (§12), then a hardened HTML5 player.
- Recommended: **`react-player`** (handles mp4/webm/hls uniformly) or **`hls.js`** if the backend ever serves HLS. Plain `<video>` is fine for mp4/webm.
- Anti-download hardening (deterrents, see §11):
  ```tsx
  <video
    src="/api/file?cmid=980"     // proxied, no token in URL
    controls
    controlsList="nodownload noplaybackrate"
    disablePictureInPicture
    onContextMenu={(e) => e.preventDefault()}
    playsInline
  />
  ```

### Audio

Treat `resourcetype.startsWith("audio/")` as a dedicated case for nicer UX: same proxied `src`, an `<audio controls controlsList="nodownload">` or **`react-h5-audio-player`** for a styled card. (The Flutter app reuses the video player; a dedicated audio card is an acceptable, better-looking web equivalent.)

---

## 6. Generic content & the WebView (the "old site" cases)

Some activities (forums, some quizzes-as-web-pages, pages, misc Moodle modules, certificate pre-download page) have **no clean file** — they're pages on the **old Moodle/website**. The app opens them in an in-app WebView (`flutter_inappwebview`) and **injects JavaScript/CSS to strip the old site's chrome** (headers, footers, breadcrumbs, menus) so only the content shows. See `webview_handler.dart` + `injection.dart`.

Two injections happen:

**A. Token propagation** (so in-page XHR/fetch stay authenticated):
```js
// Wrap XMLHttpRequest.open and window.fetch to append &token=<token>
// to same-origin (moodle host) requests that don't already have it,
// and mirror the token into localStorage/sessionStorage ('wstoken').
```

**B. UI hiding** (run on load-start, load-stop, and after each in-page navigation):
```js
(function () {
  // inject the site's cleanup stylesheet
  var link = document.createElement("link");
  link.rel = "stylesheet";
  link.href = "https://<host>/.../style/style.css";
  document.head.appendChild(link);

  ["inner_page_breadcrumb","ccnHeader1","mobile-menu","footer_one",
   "footer_middle_area","footer_bottom_area","mobileNone","copyright-widget",
   "breadcrumb_widgets","ccnMdlHeading","activity-navigation"]
    .forEach((cls) => {
      for (const el of document.getElementsByClassName(cls)) el.style.display = "none";
    });

  ["ccnSettingsMenuContainer","page-heading-button"]
    .forEach((id) => { const el = document.getElementById(id); if (el) el.style.display = "none"; });
})();
```

It's re-applied on `onLoadStart`, `onLoadStop`, and `onUpdateVisitedHistory` (with a ~300ms delay) because Moodle loads some parts dynamically.

### On the web this is the hardest part — you cannot inject JS into a cross-origin `<iframe>`

The Flutter app can inject into any page because it owns the WebView. A browser **cannot** run scripts inside a cross-origin iframe (same-origin policy). So you have options, best → worst:

1. **Rebuild these views natively (recommended).** Forums, quizzes, and certificates all have proper Moodle/plugin web-service endpoints — consume the JSON and render your own React components. This is what the app already did for quizzes (§7) and PDFs (§4). Prefer this for everything you can.
2. **Server-side proxy + sanitize.** Add a Next.js route handler that fetches the old page **server-side** (with the token), then strip the chrome server-side (e.g. with **`cheerio`**: remove the same class/id list, inject the cleanup stylesheet) and return clean HTML you render into your own layout. Because your server fetched it, you can rewrite/relative-ize asset URLs and re-inject the token on links. This reproduces the app's JS-injection effect without violating cross-origin rules.
3. **Same-origin iframe only.** If (and only if) you serve the proxied+sanitized HTML from **your own origin** (e.g. `/api/embed?...`), you may load it in an iframe and post-process it — but at that point you've already done the sanitizing in (2), so the iframe adds little.

> Do **not** iframe the live old site directly and hope to hide elements with CSS — you can't reach into it, and it exposes the raw site + token. Use the proxy-sanitize approach.

Cheerio sketch for the proxy:
```ts
// app/api/embed/route.ts
import * as cheerio from "cheerio";
export async function GET(req: Request) {
  const target = /* build moodle url + token from session */;
  const html = await fetch(target).then((r) => r.text());
  const $ = cheerio.load(html);
  ["inner_page_breadcrumb","ccnHeader1","footer_one", /* …same list… */]
    .forEach((c) => $(`.${c}`).remove());
  ["ccnSettingsMenuContainer","page-heading-button"].forEach((id) => $(`#${id}`).remove());
  $("head").append(`<link rel="stylesheet" href="https://<host>/.../style/style.css">`);
  return new Response($.html(), { headers: { "content-type": "text/html; charset=utf-8" } });
}
```

---

## 7. Quizzes — native flow (fully rebuilt, not a WebView)

Quizzes are **not** shown as web pages. When `modname === "quiz"`, the app opens a native quiz UI backed by the `local/academy/api.php` plugin. Rebuild this in React.

Reference: `lib/features/quiz/` (`quiz_remote_data_source.dart`, `quiz_models.dart`, `quiz_cubit.dart`, `quiz_screen.dart`).

### Endpoints (`{MOODLE_BASE}/local/academy/api.php`)

All responses are `{ "status": "success" | ..., "data": {...} }`. On non-success, `error`/`errorcode`/`message` carry the reason (e.g. `attemptsexhausted`, `attemptalreadyclosed`).

| Function | Method | Params | Purpose |
|---|---|---|---|
| `get_quiz` | GET | `token=<ADMIN>`, `cmid` | Quiz + all questions/options. **Fetched with the ADMIN token** so each option includes `correct: true/false` (used to reveal right/wrong). On web, call it from a **server route** — keep the `correct` flags, hide the admin token. |
| `start_quiz_attempt` | POST (form) | `token=<student>`, `quizid` | Begins an attempt → `attemptid`. |
| `submit_quiz_attempt` | POST (form) | `token=<student>`, `attemptid`, `answers` (JSON string) | Submits answers, returns graded result. |
| `get_quiz_attempt` | GET | `token=<student>`, `attemptid` | A finished attempt, for review. |
| `get_my_quiz_attempts` | GET | `token=<student>`, `quizid` | Past attempts (for history / attempts-left). |

Plus, to trigger "require view" completion (native flow never loads Moodle's quiz page):
```
POST /webservice/rest/server.php  wsfunction=mod_quiz_view_quiz  wstoken=<student>  quizid=<id>
```

> ⚠️ **Security note (admin token, not the flags):** `get_quiz` must be fetched with the **admin token** because that's what makes the server include each option's `correct: true/false`, and the UI **reveals correctness per question client-side** (a required product behavior — keep it). The thing to protect is the **admin token itself**, not the flags: **never call `get_quiz` with the admin token from the browser**, or the token ships to the client. Put `get_quiz` behind a **Next.js server route / server action** that holds the admin token server-side, calls Moodle, and returns the payload **including `correct`** to the browser. That way the flags reach the client for the reveal, but the admin token never leaves the server.
>
> ```ts
> // app/api/quiz/[cmid]/route.ts — admin token stays server-side; `correct` flags pass through
> export async function GET(_req: Request, { params }: { params: { cmid: string } }) {
>   const url = new URL(`${MOODLE_BASE}/local/academy/api.php`);
>   url.searchParams.set("function", "get_quiz");
>   url.searchParams.set("token", process.env.MOODLE_ADMIN_TOKEN!); // server-only env var
>   url.searchParams.set("cmid", params.cmid);
>   const data = await fetch(url, { cache: "no-store" }).then((r) => r.json());
>   return Response.json(data); // keep options[].correct so the UI can reveal answers
> }
> ```

### `get_quiz` → `data` shape

```jsonc
{
  "quizid": 55, "cmid": 980, "courseid": 123,
  "name": "Unit 1 Quiz",
  "intro": "…",                 // may contain {mlang}
  "timelimit": 600,             // SECONDS, 0 = no limit
  "attempts_allowed": 3,        // 0 = unlimited
  "questions": [
    {
      "slot": 1,
      "questionid": 101,
      "type": "multichoice",    // multichoice | truefalse | (others → unsupported)
      "single": true,           // multichoice only: true=pick one, false=pick many
      "supported": true,        // false → app renders a skippable fallback
      "text": "…",              // may contain {mlang}
      "images": ["https://.../pluginfile.php/..."],  // append token before loading
      "defaultmark": 1.0,
      "options": [
        { "id": 1, "text": "A", "images": [], "correct": true },   // 'correct' admin-only
        { "id": 2, "text": "B", "images": [] }
      ]
    }
  ]
}
```

Question-type mapping (`QuestionType.from`):
- `truefalse` → true/false
- `multichoice` + `single:true` → single choice (radio)
- `multichoice` + `single:false` → multi-select (checkboxes)
- anything else / `supported:false` → **unsupported** (render a skippable placeholder)

### Answer submission shape

`answers` is a JSON string of:
```jsonc
[ { "questionid": 101, "answer": 1 },          // single/true-false: option id (int)
  { "questionid": 102, "answer": [3, 5] } ]    // multi-select: array of option ids
```

### `submit_quiz_attempt` → `data`

```jsonc
{
  "attemptid": 900, "state": "finished",
  "score": 2, "max_score": 3, "percent": 66.7,
  "results": [ { "questionid": 101, "type": "multichoice", "mark": 1, "max_mark": 1, "correct": true } ]
}
```

### Quiz UX to reproduce

- Intro screen: name, intro, time limit, attempts allowed / attempts used (from `get_my_quiz_attempts`), Start button.
- One question at a time, progress bar, optional countdown timer (`timelimit` seconds), Continue/Finish button.
- Leaving mid-attempt prompts a confirm dialog and closes the attempt (native calls `endAttemptOnLeave`). On web, warn via `beforeunload` and/or a modal, and submit-what-you-have.
- Completion screen with score/percent + confetti.
- Accessibility: announce each question and correctness; the app does this via screen-reader announcements — mirror with `aria-live` regions.

Recommended packages: your normal state tool (Zustand/Redux/React Query), **`canvas-confetti`** for the success burst, **`react-use`** or a small hook for the countdown.

---

## 8. Certificates — the one downloadable/savable case

Certificates are Moodle **`customcert`** activities. There's no `fileurl` up front; the PDF is generated behind a **download page**. The native flow:

1. The certificate activity opens in the **in-app WebView** (§6) — a `customcert` page on the old site, chrome hidden by injection.
2. When the user triggers "download" (`customcert`'s `&downloadown=1`), the site returns the PDF as an **attachment**. The WebView can't render an attachment inline, so a **download callback** (`onDownloadStartRequest`) intercepts it.
3. The interceptor detects a PDF (`mime contains pdf`, or url contains `.pdf`/`downloadown`) and re-opens it in the **in-app PDF viewer with `allowShare: true`** — the certificate title becomes "Congratulations", a confetti overlay plays, and a **share/save action** appears in the app bar.
4. Non-PDF downloads are handed to the system browser.

So certificates are the **only** content where `allowShare/allowDownload` is `true`. Everything else stays view-only.

**Web equivalent:**

- Rebuild the certificate step **server-side**: a Next.js route requests the customcert download (`downloadown=1`) with the token, receives the PDF bytes, and either:
  - streams them to your `PdfViewer` as a `Blob` (view), **and**
  - offers an explicit **Download / Save** button (allowed here only), e.g. `Content-Disposition: attachment; filename="certificate.pdf"` from your route, or a client `URL.createObjectURL(blob)` → `<a download>`.
- Give it the celebratory treatment (title, `canvas-confetti`) to match the app.
- Filename: sanitize the title (keep letters incl. Arabic, digits, spaces, dashes, underscores), fall back to `certificate.pdf`. Mirror of the native `_sharePdf` cleanup regex: `title.replace(/\.pdf$/i, "").replace(/[^\w؀-ۿ \-]/g, "").trim()`.

---

## 9. Marking activities viewed (auto-completion)

Because the app streams files directly (bypassing Moodle's module page), it manually registers a "view" so Moodle's *on-view* completion fires. Reproduce if you show completion:

- After opening a file activity that is **auto-tracked and not yet done**, call the module's view web service:

  | modname | wsfunction | id param (uses `instance`) |
  |---|---|---|
  | `resource` | `mod_resource_view_resource` | `resourceid` |
  | `url` | `mod_url_view_url` | `urlid` |
  | `page` | `mod_page_view_page` | `pageid` |
  | `folder` | `mod_folder_view_folder` | `folderid` |
  | `book` | `mod_book_view_book` | `bookid` |
  | `lesson` | `mod_lesson_view_lesson` | `lessonid` |
  | `scorm` | `mod_scorm_view_scorm` | `scormid` |
  | `quiz` | `mod_quiz_view_quiz` | `quizid` |

- Use `activity.instance`; if it's `0`, resolve it from the cmid via `core_course_get_contents` (`options[0][name]=cmid`).
- Best-effort — swallow errors, then re-fetch completion to update the checkmark. (`markActivityViewed` in the data source.)

---

## 10. The two UIs

### 10.1 Normal UI (`details_screen.dart`)

- App bar: course title, a thin **progress bar** under the title (percent + "X of Y completed"), shown only when there are completion-tracked activities.
- Body: a single scrollable list:
  - optional live-session banners (Zoom/Jitsi/Meet) at the top,
  - **section header** (`parents[].name`) then its activity rows,
  - each activity = a bordered `ListTile`: a tinted **type-icon tile** on the left (color per type), title, optional restriction subtitle, and trailing completion/recording/whiteboard icons,
  - a trailing "enrolled teachers" strip.
- Icon per type (`_getActivityIcon`): `video/*`→play, `audio/*`→note, `image/*`→image, `application/pdf`/`pdfannotator`→pdf, `quiz`→quiz, `jitsi`/`googlemeet`→camera, `forum`→forum, `customcert`→certificate, else generic file.
- **Locked activities**: dimmed, lock icon, restriction reason as subtitle, not tappable.
- Pull-to-refresh re-fetches content + completion without flashing a spinner.

### 10.2 Kids UI — "Child Mode" (`course_map_view.dart`)

Child Mode is a separate, colorful theme (a 4th app theme). In Course Details it **replaces the flat list with a game-style "world map"**:

- Each **section** becomes a "Unit" (centered title between two rules).
- Each **activity** becomes a **circular node** on a **winding dashed path**, laid out in a staggered left/right zigzag (`x = width * (i even ? 0.30 : 0.70)`, fixed row height).
- Node = a thick colored **ring** around the activity's type icon; ring color reflects completion (**green** done, **red** failed, **grey** locked, otherwise a rotating accent color).
- Corner **badges** on a node: top-right = recordings, left = whiteboard (for live sessions), each independently tappable.
- **Tapping a node reuses the exact same routing** (`onActivityTap`) as the normal list — so all content handling (PDF/video/quiz/certificate) is identical; only the presentation differs.
- Long-press toggles manual completion (with haptics).
- Progress hint: "hold to mark complete".
- The quiz flow also gets Child-Mode extras (mascot, sounds) but the logic is the same.

**Web approach:** detect the "kids" theme flag and swap the list renderer for a map renderer. Draw the dashed connectors with an **SVG `<path>`** (quadratic béziers between node centers, `stroke-dasharray`), absolutely-position the nodes. Keep the tap handler shared between both renderers so there's one code path for opening content. Framer Motion (**`framer-motion`**) is a good fit for the node press/entrance animations; **`lottie-react`** if you want the mascots.

Reference for the whole theme system: `docs/theme-and-kids-mode-guide.md` and `lib/core/theme/child_mode_theme.dart`.

---

## 11. "Don't download / don't save" policy

**Policy:** all course content is **view-only**. The **only** exception is **certificates** (share/save allowed). In the app this is enforced by:

- Streaming files into in-app viewers instead of exposing file links.
- The `allowShare` flag defaulting to `false` and set `true` only on the certificate path.
- OS-level **screen-capture/recording prevention**: `ScreenSecurity.setSecureMode(...)` (Android `FLAG_SECURE`, iOS equivalent), toggled by the `prevent_screen_recording` server setting. There's also an optional watermark overlay (`watermark` setting) on the video players.

**Honest reality on the web:** a browser **cannot** truly prevent saving. Anything the browser renders can be captured, and there is no web equivalent of `FLAG_SECURE`. Treat the following as **deterrents, not guarantees**, and design so the *convenient* paths are closed:

- **Never expose the raw file URL or token to the page.** Proxy every file through a Next.js route (§12) and render from `Blob`/`ArrayBuffer`. No `<a href=file>`, no direct `<iframe src=moodleUrl>`.
- **PDFs:** render with pdf.js to `<canvas>` (no browser PDF toolbar, no built-in download/print). Don't ship a download button for content.
- **Video/audio:** `controlsList="nodownload noplaybackrate"`, `disablePictureInPicture`, `onContextMenu={preventDefault}`. Consider **HLS with short-lived signed segment URLs** if you need stronger protection; for real DRM you'd need EME/Widevine (heavy — usually overkill here).
- **Images/general:** disable right-click/drag on media containers, add a translucent **watermark** overlay (mirrors the app's `watermark` option) with the user's id/email so leaked captures are traceable.
- **Certificates only:** provide an explicit Download/Save (and a share link if desired).

State this limitation to stakeholders explicitly — the native app can enforce more than the web can.

### `{mlang}` multi-language strings

Many text fields (`name`, `intro`, `fullname`, question text) can contain Moodle `{mlang}` markup for bilingual (Arabic/English) content. The app parses it client-side (`parseMlang`, `lib/core/utils/mlang.dart`) to pick the active language. Port a small `parseMlang(text, lang)` helper: `{mlang ar}…{mlang}{mlang en}…{mlang}` → the block matching the current locale (fall back to `other`/first). Apply it to every server string you display.

---

## 12. Security & the token model

The native app appends `?token=<wstoken>` to nearly every URL, and injects the token into WebView storage. That's fine-ish in a sandboxed app; **on the web it's dangerous** (tokens in URLs land in history, logs, referrers; admin token must never reach the browser). Recommended web architecture:

- **Keep the Moodle token(s) server-side.** Store the student token in an **httpOnly** session cookie (or a server session). Keep the **admin token only on the server** — the browser must never see it.
- **Proxy all Moodle calls through Next.js route handlers / server actions.** The browser calls `/api/course/:id/content`, `/api/file?cmid=...`, `/api/quiz/...`; the server attaches the right token and forwards to Moodle. This also lets you:
  - strip only the truly sensitive fields (live-session `jwt`/`token`, any admin token) before responding — **keep quiz option `correct` flags**, the UI needs them to reveal answers client-side,
  - sanitize old-site HTML (§6),
  - stream files without leaking URLs (§4, §5),
  - keep `token`/`jwt`/`wstoken` out of anything client-visible (the app itself redacts these in logs — do the same).
- Never put personal data or tokens in query strings on the client.

---

## 13. Recommended Next.js package summary

| Job | Package(s) |
|---|---|
| PDF rendering (view-only) | `react-pdf` (+ `pdfjs-dist`), or `@react-pdf-viewer/core` |
| YouTube | `react-youtube` (or nocookie iframe embed) |
| Direct video / HLS | `react-player`, or `hls.js` + `<video>` |
| Audio | `react-h5-audio-player`, or hardened `<audio>` |
| Old-site HTML sanitize (proxy) | `cheerio` (server), `dompurify` (extra client safety) |
| Quiz timer / hooks | `react-use` (or a small custom hook) |
| Confetti (quiz + certificate) | `canvas-confetti` |
| Kids-map animations / mascots | `framer-motion`, `lottie-react` |
| Data fetching / caching | `@tanstack/react-query` (or RSC + server actions) |
| Live class (only if ported) | `@jitsi/react-sdk` |

---

## 14. Source map (Flutter → files you can request)

You don't have the repository. The table maps each concern to the Flutter file it's based on — use it to **request specific files** from us when you want to see an implementation detail, rather than as paths you can open yourself.

| Concern | File |
|---|---|
| Screen, list, progress, routing tap | `lib/features/home/presentation/pages/course_details/details_screen.dart` |
| Content router | `.../course_details/content_handlers.dart` |
| Type detection | `.../course_details/type_checkers.dart` |
| PDF download + viewer + share | `.../course_details/pdf_handler.dart` |
| YouTube dialog | `.../course_details/youtube_handler.dart` |
| Direct video/audio player | `.../course_details/platform_player.dart` |
| WebView + UI/token injection | `.../course_details/webview_handler.dart`, `.../course_details/injection.dart` |
| Kids "world map" | `.../course_details/course_map_view.dart` |
| Content endpoint + completion + view-tracking | `lib/features/home/data/datasources/couses_remote_data_source.dart` |
| Content models | `lib/features/home/data/models/course_models.dart` |
| Quiz API | `lib/features/quiz/data/datasources/quiz_remote_data_source.dart` |
| Quiz models | `lib/features/quiz/data/models/quiz_models.dart` |
| Quiz UI | `lib/features/quiz/presentation/pages/quiz_screen.dart` |
| Route argument bundles | `lib/core/routing/route_args.dart` |
| Screen-capture prevention | `lib/core/screen_security.dart` (+ `main.dart` toggle) |
| `{mlang}` parsing | `lib/core/utils/mlang.dart` |
| Kids theme system | `lib/core/theme/child_mode_theme.dart`, `docs/theme-and-kids-mode-guide.md` |
```
