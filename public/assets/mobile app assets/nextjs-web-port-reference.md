# Web Port Reference — Moodle Backend Integration

_Compiled 2026-07-25 from the current `excellence_academy` (Academy) Flutter codebase (not from the old, stale API-endpoints Word doc in the repo root, which only covers 2 of 13 features and references an old domain)._

This is the reference doc for the team building the Next.js web client against the same backend. It lists every endpoint, model, and integration point the Flutter app uses, plus porting notes and an agent checklist.

> **Read §5 (Auth) before writing any API code.** The mobile app embeds a Moodle **admin token** client-side. That is not safe to repeat in a browser — see the callout there.

---

## 1. Backend shape

Single hardcoded tenant, no site picker: `https://academy2026.nitg-eg.com`.

Three kinds of backend calls:

1. **Standard Moodle REST** — `POST/GET /webservice/rest/server.php?wsfunction=<fn>&moodlewsrestformat=json&wstoken=<token>` — core Moodle functions (`core_*`, `mod_*`, `enrol_*`).
2. **Custom `local_academy` plugin dispatcher** — `POST/GET /local/academy/api.php?function=<fn>&token=<token>&alang=<ar|en>` — everything academy-specific (quiz, lessons, teachers, coupons, packages, subscriptions, programs, discount preview). Response envelope: `{"status":"success"|"fail","data":...}` or `{"status":"fail","error":...}`.
3. **A handful of other bespoke PHP endpoints** — `/login/token.php`, `/webservice/upload.php`, `/local/googleauth/google_login.php`, `/local/multitopics/getalltopics.php`, `/local/payments/callback.php`.
4. **Dynamic settings / remote config** — a mechanism the mobile app uses to fetch feature flags, tokens, and payment/display config at boot. **Not ready on the backend for the web app yet** — see the note in §5, don't build against the mobile endpoint.

Content-Type is `application/x-www-form-urlencoded` for most Moodle POSTs; the `local_academy` dispatcher and Google login use JSON/query params — see per-call notes below.

---

## 2. Auth & session mechanics

**Login**: `POST /login/token.php` (form: `service=moodle_mobile_app&username&password`) → `{token}`. Standard Moodle mobile token exchange — no OAuth wrapper of its own.

**After login**, two calls populate the session:
- `core_webservice_get_site_info` → `userid`, `username`, `firstname`, `lastname`, `userpictureurl`.
- `core_user_get_users_by_field` (`field=id`) → `phone1` + custom fields (`year`, `ParentPhone`), which site-info doesn't return.

**Registration**: `core_user_create_users` (admin token) creates the account, then immediately runs the login flow above for the new user.

**Social login**:
- Google: native ID token → `POST /local/googleauth/google_login.php` (JSON `{idtoken}`) → find-or-create + `wstoken`.
- Apple: mobile has a native SSO integration too, but it goes through an old/legacy backend path that predates this project — don't port that endpoint as-is. If web needs Apple sign-in, get a fresh endpoint from the backend team rather than reusing the mobile one.

**Token model — two tokens, both currently shipped to the client:**

| Token | Source | Used for |
|---|---|---|
| `wstoken` (per-user) | Returned from `/login/token.php`, persisted after login | User-scoped reads/writes: their courses, messages, calendar, payments, lessons |
| `free_activity_token` | Fetched from the app's dynamic-settings/remote-config mechanism (see §5 note — not yet available for web) | A shared/anonymous "free content" token, not user-specific |
| `admin_token` | Also fetched from dynamic settings, with a hardcoded fallback in the mobile app today | **Privileged ops**: user CRUD, manual enrolment, full course catalog, teacher directory, delete account, password reset, profile image, discount previews when no user token |

> ⚠️ **Security note for the web port.** The Flutter app fetches and holds an admin-scoped Moodle token in the client process — acceptable-ish in a compiled mobile binary behind app-store review, **not acceptable in a browser**, where anyone can read it from Network tab / JS memory. For Next.js: put every admin-token call behind a server-side API route or Server Action; never send `admin_token` (or any response payload that contains it) to the browser. The per-user `wstoken` should also live server-side in an httpOnly session cookie, not `localStorage`, with the Next.js server proxying Moodle calls.

**No token refresh** — Moodle tokens from `service=moodle_mobile_app` are long-lived; there's no refresh-token interceptor to replicate.

**"Child mode"** is a pure client-side theme toggle, not a backend role — no server call changes because of it. Skip it or treat as a CSS theme switch if the web port wants it.

**Password check**: re-runs `/login/token.php` with the candidate password to verify it (no verify-only endpoint exists). **Delete account**: `core_user_delete_users` (admin token).

---

## 3. Moodle `wsfunction` calls by feature

### Auth
| Function | Params | Description |
|---|---|---|
| `local_profilefields_get_profile_fields` | — | Custom-field definitions (populates registration "year" dropdown) |
| `core_webservice_get_site_info` | `wstoken` | Post-login user/site payload |
| `core_user_get_users_by_field` | `field=id`, `values[0]` | Extended profile: phone1, year, ParentPhone |
| `core_user_create_users` | `users[0][...]`, `customfields[]` | Register (admin token) |
| `core_user_update_users` | `users[0][id, ...]` | Edit profile / reset password / attach uploaded photo (admin token; 3 different call sites, same function) |
| `core_user_delete_users` | `userids[0]` | Delete account (admin token) |
| `/webservice/upload.php` (not wsfunction) | multipart `file`, `itemid=0`, `filearea=draft` | Upload profile photo to draft area, returns itemid |

### Home / Courses
| Function | Params | Description |
|---|---|---|
| `core_enrol_get_users_courses` | `userid` | "My Courses" list, incl. `progress`/`completed` |
| `enrol_manual_enrol_users` | `enrolments[0][userid,roleid=5,courseid]` | Enrol as student (admin token) |
| `core_course_get_courses_by_field` | — | Full course catalog (admin token) |
| `local_payments_get_courses_with_pricing` | `field/value` (e.g. `field=ids`), `country` | Bulk country-resolved pricing for a course set |
| `core_course_search_courses` | `criterianame=search`, `criteriavalue`, `page`, `perpage` | Course search |
| `core_course_get_categories` | `criteria[0][key]=parent`, `value=0`, `addsubcategories=1` | Category tree |
| `core_completion_get_activities_completion_status` | `courseid`, `userid` | Per-activity completion (`state`, `tracking`) |
| `core_completion_update_activity_completion_status_manually` | `cmid`, `completed=1|0` | Manual-tracking toggle |
| `mod_resource_view_resource`, `mod_url_view_url`, `mod_page_view_page`, `mod_folder_view_folder`, `mod_book_view_book`, `mod_lesson_view_lesson`, `mod_scorm_view_scorm` | id param varies by type | Fires "viewed" event so Moodle's on-view completion triggers (needed because the app renders content itself instead of Moodle's module page) |
| `core_course_get_contents` | `courseid`, `options[0][name]=cmid` | Resolve a module's `instance` id from `cmid` (helper for the above) |
| `/local/multitopics/getalltopics.php` (not wsfunction) | `courseid`, `wstoken`, `lang` | Full course content tree — see §6 |

### Quiz
Only one core function; everything else is on `local_academy` (§4):
| Function | Params | Description |
|---|---|---|
| `mod_quiz_view_quiz` | — | Fires quiz "view" completion event (best-effort) |

### Calendar
| Function | Params | Description |
|---|---|---|
| `core_calendar_get_calendar_monthly_view` | `year`, `month`, `courseid` (0 = all) | Month grid of events |

### Messages
| Function | Params | Description |
|---|---|---|
| `core_message_get_conversations` | `userid`, `type`, `limitfrom`, `limitnum` | Conversation list |
| `core_message_get_conversation_messages` | `currentuserid`, `convid`, `newest=1` | Thread messages + members |
| `core_message_send_messages_to_conversation` | `conversationid`, `messages[0][text,textformat=2]` | Reply in existing conversation |
| `core_message_send_instant_messages` | `messages[0][touserid,text,textformat=2]` | Start/continue a 1:1 chat by user id |
| `core_message_mark_all_conversation_messages_as_read` | `userid`, `conversationid` | Clear unread badge |
| `core_message_get_unread_conversation_counts` | — | Sum `types[1]+types[2]` for badge |

### Notifications
| Function | Params | Description |
|---|---|---|
| `core_message_get_messages` | `useridto`, `useridfrom=0`, `type=notifications`, `read=0/1` | Called twice (read + unread) and merged — `message_popup_get_popup_notifications` throws on this server |
| `message_popup_get_unread_popup_notification_count` | `useridto` | Badge count |
| `core_message_mark_all_notifications_as_read` | `useridto`, `useridfrom=0` | Mark all read |
| `core_message_mark_notification_read` | `notificationid` | Mark one read |

### Payments / Invoices (`local_payments_*`, on standard `server.php`)
| Function | Params | Description |
|---|---|---|
| `local_payments_get_course_access` | `courseid` | `{isEnrolled, isPurchased, hasPendingPayment, paymentStatus, orderId}` |
| `local_payments_get_course_price` | `courseid`, `country` | Single-course pricing |
| `local_payments_create_checkout` | `courseid`, `lang`, `country`, `coupon_code` | Starts Kashier hosted checkout → `{orderId, checkoutUrl, expiresAt, provider, transactionId}` |
| `local_payments_get_payment_history` | `page`, `perpage` | Past transactions |
| `local_payments_get_invoice` | `transaction_id` | Full invoice detail |
| `local_payments_verify_payment` | `order_id` | Confirms charge after checkout redirect |

### Teachers
| Function | Params | Description |
|---|---|---|
| `core_enrol_get_enrolled_users` | `courseid` | All enrolled users; client filters to teacher roles (admin token) |

---

## 4. `/local/academy/api.php` dispatcher calls

Same request shape everywhere: `token`, `function`, often `alang` (`ar`/`en`) for translated text. Response: `{status, data}` or `{status:"fail", error}`.

**Quiz**
| function | Method | Description |
|---|---|---|
| `get_quiz` | GET `cmid` | Full quiz incl. `"correct"` flags — fetched with **admin token** deliberately, for answer-review UI |
| `start_quiz_attempt` | POST `quizid` | → `{attemptid}` |
| `submit_quiz_attempt` | POST `attemptid`, `answers=<json>` | `[{questionid,answer}]` |
| `get_quiz_attempt` | GET `attemptid` | Finished attempt for review |
| `get_my_quiz_attempts` | GET `quizid` | All of the user's attempts |

**Teachers**
| function | Description |
|---|---|
| `get_all_teachers` | `page/perpage(≤200)/search/categoryid/year/approved/available` — full directory w/ subjects, hours, busy times, rating (admin token) |

**Lessons** (booking/credit system)
| function | Method | Description |
|---|---|---|
| `get_my_lessons` | GET `role=student`, `status` | Student's bookings |
| `get_lesson` | GET `lessonid` | Detail incl. Jitsi session if live |
| `get_flex_history` | GET | Lesson-credit ("Flex") ledger |
| `get_lesson_settings` | GET | `minBookingMinutes`, `cancelDeadlineMinutes`, `updateDeadlineMinutes`, `startAllowedMinutes`, `absenceReportMinutes` |
| `request_lesson` | POST `teacherid`, `subject`, `requested_time`, `note` | Book a request |
| `student_respond_lesson` | POST `lessonid`, `action=accept|reject|suggest`, `suggested_time`, `reject_reason` | Respond to teacher counter-offer |
| `cancel_lesson_student` | POST `lessonid`, `reason` | Cancel confirmed lesson |
| `cancel_lesson_request` | POST `lessonid`, `reason` | Withdraw pending request |
| `report_teacher_absent` | POST `lessonid` | No-show past grace period → Flex returned |
| `request_time_update` | POST `lessonid`, `proposed_time` | Propose new time |
| `respond_time_update` | POST `lessonid`, `action=accept|reject` | |

**Coupons**
| function | Description |
|---|---|
| `get_available_coupons` | Platform-wide active coupons (`alang`) |

**Packages** (Flex credit bundles)
| function | Method | Description |
|---|---|---|
| `get_available_packages` | GET | Catalog |
| `create_package_checkout` | POST `packageid`, `coupon_code` | → `CheckoutSession` (Kashier) |
| `preview_discount` | GET `item_type=package`, `item_id`, `coupon_code` | Shared discount engine (see below) |
| `get_my_packages` | GET | User's packages + remaining Flex |
| `get_payment_history` | GET | Package payment history |
| `purchase_package` | POST (legacy) | Superseded by Kashier checkout — likely skip in the port |

**Subscriptions**
| function | Method | Description |
|---|---|---|
| `get_available_subscriptions` | GET | Plan catalog incl. included courses |
| `preview_discount` | GET `item_type=subscription` | |
| `create_subscription_checkout` | POST `subscriptionid`, `coupon_code` | → `CheckoutSession` |
| `get_my_subscriptions` | GET | User's plans |
| `get_subscription_payment_history` | GET | |
| `purchase_subscription` | POST (legacy) | Skip — superseded |

**Programs**
| function | Method | Description |
|---|---|---|
| `get_catalogue_programs` | GET | Browsable catalog |
| `get_my_programs` | GET | Owned programs |
| `get_program_details` | GET `programid` | Full detail; distinguishes not-available vs server error |
| `join_program` | POST `programid` | Self-enrol into a free program (idempotent) |
| `open_certificate` | POST `certificateid` | Mints a ~2min auto-login URL to the `mod_customcert` page |
| `preview_discount` | GET `item_type=program` | |
| `list_program_certificate_eligibility` | GET `programid` | Certificates + eligibility rules |
| `create_program_checkout` | POST `programid`, `coupon_code` | → `CheckoutSession` |

> `preview_discount` is **one shared endpoint** reused for courses/packages/subscriptions/programs (`item_type` switches the target) — build one `DiscountPreview` client function, not four.

---

## 5. Other custom endpoints

| Path | Method | Purpose |
|---|---|---|
| `/login/token.php` | POST form | Login (see §2) |
| `/webservice/upload.php` | POST multipart | Profile photo upload |
| `/local/googleauth/google_login.php` | POST JSON | Google SSO |
| `/local/multitopics/getalltopics.php` | GET | Full course content tree: sections → activities, incl. `other_fields` (Zoom banner data) and `lang` param for server-rendered strings that `{mlang}` can't express (e.g. `availabilityinfo`) |
| `/local/payments/callback.php` | browser redirect | Kashier checkout return URL; app reads `?paymentStatus=SUCCESS\|FAILED` from the URL, then calls `local_payments_verify_payment` |
| `https://ipapi.co/country_code/`, `https://api.country.is/` | GET | Third-party IP geolocation, feeds `country` into pricing calls, with server fallback if both fail |

> **Note — dynamic settings / remote config, not ready for web yet.** The mobile app boots by fetching a single remote-config blob (feature flags, the `wstoken`/`admin_token` pair, payment-provider config, watermark settings, app-version/update info, WhatsApp contact, social-login toggles, a request-timeout value, and a promo video URL) so those can change server-side without an app release. **The backend doesn't have a web-facing equivalent of this yet — it's planned for later.** Don't build against the mobile app's current settings endpoint; treat this as a placeholder integration point. When the backend team ships it, the same pattern applies as everywhere else here: fetch it server-side only, and strip any token/secret fields before forwarding a "public config" subset to the browser.

**Confirmed absent** (don't build for): push notifications (no FCM/OneSignal — "notifications" are polled from `core_message_get_messages`), deep links (no `app_links`/`uni_links`, `url_launcher`-outbound only), server-side IAP receipt validation (mobile-only concern, not applicable to web anyway). **Also out of scope for this port**: the mobile app's direct Paymob integration and its legacy Apple-SSO endpoint — both are old code tied to a previous backend generation; don't carry them forward.

---

## 6. Data models (by feature)

No codegen (no freezed/json_serializable) — hand-written `fromJson`. Field names below are the app's internal camelCase after mapping; raw Moodle JSON is mostly snake_case.

- **Auth**: `ProfileField(id, shortname, name, datatype, required, options[])`
- **Courses**: `Course(id, fullname, shortname, summary, categoryId, visible, imageUrl, country, currency, price, originalPrice, salePrice, discountPercentage, isSaleActive, saleEndsAt, isFree, isPurchased, isEnrolled, offerName)`; `CourseCategory(id, name, parent, courseCount, sortOrder)`; `CourseResponse/ParentSection/TopicSection/Activity` (content tree from `getalltopics.php`); `ActivityCompletion(state, tracking)`; `JitsiSession(serverUrl, room, jwt, subject, isTeacher, available, availableInfo, whiteboardUrl, featureFlags, recordings[])`; `JitsiRecording(id, title, status, playbackUrl, thumbnailUrl, embedUrl, timecreated)`
- **Calendar**: `CalendarEvent(id, name, timeStart, formattedTime, moduleName, eventType, url, courseName, courseId)`; `CalendarDay`, `CalendarMonth`
- **Coupons**: `CouponTarget(itemType, itemId, label)`; `AvailableCoupon(code, status, discountType, discountValue, maxDiscount, usageType, usageLimit, usageCount, startDate, endDate, appliesTo[], isRedeemable*)` (*computed)
- **Lessons**: `Lesson(id, subject, status, myRole, requestedTime, confirmedTime, suggestedTime, actualStart, actualEnd, note, teacherId/studentId/Name/Photo, serverActions, proposals[], cancelReason, rejectReason, canJoin, joinUrl, cmid, sessionId, jitsiSession)`; `LessonProposal`, `FlexTx(id, type, amount, balance, lessonId, note, timeCreated)`, `LessonSettings`
- **Messages**: `Conversation(id, type, name, imageUrl, unreadCount, members[], messages[])`; `ConversationMember(id, fullName, profileImageUrl, isOnline, isBlocked)`; `ChatMessage(id, userIdFrom, text, timeCreated)`; `SentInstantMessage`
- **Notifications**: `AppNotification(id, subject, text, fullmessage, fullmessagehtml, smallmessage, isRead, contextUrl, timeCreated, timeRead, userFromFullName, component, eventType)`
- **Packages**: `AvailablePackage(id, name, description, flexCount, price, expirationDays, status, offer)`; `MyPackage(id, packageId, name, totalFlex, remainingFlex, usedFlex, pricePaid, status, timeActivated, expiresAt, expirationDays)`; `PaymentRecord`, `PurchaseResult`
- **Payments** (shared across course/package/subscription/program checkout): `CourseAccess`, `CoursePrice`, `CheckoutSession(orderId, checkoutUrl, expiresAt, provider, transactionId)`, `PaymentVerification`, `PaymentHistoryItem`, `InvoiceDetails`
- **Programs**: `CatalogueProgram(id, name, description, free, price, currency, offer, owned, joinable)`; `MyProgram`, `ProgramAllocation`, `ProgramContentNode` (recursive tree), `ProgramCertificate` + `ProgramCertificateRule`, `ProgramDetails`
- **Quiz**: `Quiz(quizId, cmid, courseId, name, intro, timeLimit, attemptsAllowed, questions[])`; `QuizQuestion(slot, questionId, type, text, images, defaultMark, supported, single, options[])`; `QuizOption(id, text, images, correct)`; `QuizAttempt`, `QuestionResult`, `QuizSubmissionResult`, `QuizAttemptSummary`, `QuizAttemptReview`, `QuizAnswer`
- **Subscriptions**: `AvailableSubscription(id, name, description, price, durationDays, status, courses[], offer)`; `MySubscription`, `SubscriptionPaymentRecord`, `SubscriptionPurchaseResult`
- **Teachers**: `Teacher(userId, fullName, email, headline, bio, experience, photoUrl, rating, approved, available, subjects[], hours[], busyTimes[][])`; `TeacherSubject`, `TeacherHour`, `TeachersPage`
- **Shared pricing**: `Offer(name, discountType, discountValue, discount, original, finalPrice, label)`; `DiscountPreview(original, offerDiscount, offerName, couponDiscount, couponCode, discount, finalPrice, couponError)` — the shape every `preview_discount` call returns, regardless of `item_type`

---

## 7. Other integration points

- **Payments**: Kashier is the live gateway (hosted checkout opened in a webview / iframe on web, success detected via redirect to `/local/payments/callback.php?paymentStatus=...`, confirmed via `local_payments_verify_payment`). That's the only payment integration in scope for this port.
- **Jitsi**: `serverUrl/room/jwt` come embedded in course/lesson JSON (not a separate call) — for web, use Jitsi's web SDK/iframe API with the same JWT.
- **Zoom**: not an SDK integration at all — just a banner reading `online_url` etc. out of the course's `other_fields`, opened as an external link. Trivial to port as a plain `<a>`.
- **PDF invoices**: generated client-side from `InvoiceDetails` (fonts: Amiri/Cairo for Arabic) — the backend never returns a PDF. Use a web PDF lib (e.g. `pdf-lib`/`@react-pdf/renderer`) with the same font choice for Arabic.
- **Localization**: two independent layers —
  1. UI strings: standard i18n (ARB → `AppLocalizations`); web equivalent is any i18n library (e.g. `next-intl`) with the same `ar.json`/`en.json` split.
  2. Content strings inside Moodle data use `{mlang xx}...{mlang}` markup, parsed client-side (`mlang.dart`). Port this parser as-is — it's a small regex-based extractor, not a Moodle feature you can rely on the server to resolve except where a `lang`/`alang` param is explicitly supported (`getalltopics.php`, `local_academy`).
  3. RTL is driven by the **selected app language**, not just browser locale — force `dir="rtl"` on Arabic selection app-wide, same as the Flutter `Directionality` override.

---

## 8. Porting recommendations for Next.js

1. **Backend-for-frontend layer is mandatory, not optional.** Every call that currently uses `admin_token` (user CRUD, catalog fetch, teacher directory, manual enrol, discount preview fallback, quiz `get_quiz` with correct-answer flags) must go through a Next.js API route / Server Action that holds the admin token server-side. Never inline it into a client bundle or `NEXT_PUBLIC_*` env var.
2. **Session**: server-side httpOnly cookie holding the user's `wstoken` (set at login via a Route Handler), Next.js server proxies subsequent Moodle calls using that cookie — mirrors the mobile app's behavior without exposing tokens to `document.cookie`/JS.
3. **One shared discount-preview client function** (`item_type` param) rather than 4 separate ones — matches how the backend actually models it.
4. **Kashier checkout** works as a redirect/iframe on web same as WebView on mobile; reuse the same `local_payments_create_checkout` → redirect → `local_payments_verify_payment` sequence.
5. **Dynamic settings / remote config is a placeholder for now.** The backend doesn't expose a web-facing version yet (planned for later — see §5 note). Build the web app's config loading against a stub/local `.env` in the meantime, but design the loader so swapping in the real endpoint later is a one-line change: fetch once server-side at boot (or per-request, cached), and only forward a non-secret subset (feature flags, watermark, video URL, WhatsApp contact) to the client — never `admin_token`/payment secrets.
6. **Mirror the "view" tracking calls** (`mod_*_view_*`, `mod_quiz_view_quiz`) wherever the web app renders course/quiz content itself instead of loading Moodle's own page — otherwise completion tracking silently breaks exactly like it does server-side when unconfigured (see [progress-tracking-backend-requirements.md](progress-tracking-backend-requirements.md)).
7. **Skip**: in-app-purchase flow (mobile-only), the legacy `purchase_package`/`purchase_subscription` functions, and the mobile app's Paymob integration and legacy Apple-SSO endpoint — all four are old code from a previous backend generation, not part of this port.
8. **Flag to the backend team before building on top of them**: (a) timeline for the web-facing dynamic-settings/remote-config endpoint (§5); (b) confirm whether a scoped, non-admin service token can be issued for catalog/discount-preview reads instead of reusing the full admin token, since that removes the biggest security wrinkle for the web port; (c) a fresh Apple-SSO endpoint if/when web needs Apple sign-in.

---

## 9. Agent checklist — Next.js web port

Use this instead of the Flutter `AGENT_CHECKLIST.md` (that one is Dart/Flutter-specific — BLoC, widgets, `dart format`, etc. don't apply here). Check off what's applicable before calling any endpoint-integration task done.

**Architecture**
- [ ] Feature-first folder structure mirroring the Flutter app's feature list (auth, courses, quiz, calendar, coupons, messages, notifications, payments, packages, subscriptions, programs, teachers, lessons) so the two codebases stay easy to cross-reference.
- [ ] Clear separation: server-only API/data layer (Route Handlers or Server Actions) vs. client UI components.
- [ ] Centralized API client (single fetch wrapper) instead of scattering `fetch()` calls per component.

**Security (the biggest departure from the mobile app)**
- [ ] `admin_token` and any other secret from the dynamic-settings/remote-config mechanism (once it exists for web — see §5) never reaches a client bundle, browser cookie, or `NEXT_PUBLIC_*` env var.
- [ ] User `wstoken` stored in an httpOnly, secure cookie — not `localStorage`/`sessionStorage`.
- [ ] Every admin-scoped Moodle call is proxied through a server route that attaches the token server-side.
- [ ] Kashier callback verification (`local_payments_verify_payment`) happens server-side, not trusted from client-supplied query params alone.

**Data layer**
- [ ] One typed model per Moodle response shape (per §6), matching field names so this doc stays the cross-reference.
- [ ] Shared `DiscountPreview`/`preview_discount` client used for all four `item_type`s, not duplicated.
- [ ] `{mlang}` parser ported and used wherever course/content JSON is rendered.
- [ ] Completion "view" pings (`mod_*_view_*`) fired wherever the web app renders a module inline.

**Auth**
- [ ] Login → token → site-info → extended-profile sequence matches §2 exactly (site-info alone is missing phone/custom fields).
- [ ] Social login (Google at minimum) wired to the same `local_googleauth` endpoint.
- [ ] No token-refresh logic invented — Moodle tokens here don't expire/rotate in the current backend.

**i18n / RTL**
- [ ] `ar`/`en` UI strings + `dir="rtl"` toggle driven by the selected language, not just browser `Accept-Language`.
- [ ] `lang`/`alang` params passed on every call that supports server-side language selection (`getalltopics.php`, all `local_academy` calls).

**Payments**
- [ ] Kashier hosted-checkout → redirect → verify flow implemented for course/package/subscription/program purchases. This is the only payment provider in scope — no Paymob.
- [ ] Invoice PDF generated client- or server-side from `InvoiceDetails` (no PDF comes from the backend).

**Error handling**
- [ ] Every Moodle call handles the `{status:"fail", error}` / Moodle `exception` envelope, not just the happy path.
- [ ] `local_academy` and standard `server.php` errors are normalized to one app-level error type.

**Gaps to resolve with the backend team before shipping**
- [ ] Confirm whether a non-admin scoped token can replace `admin_token` for read-heavy calls (catalog, discount preview, teacher directory).
- [ ] Get a timeline / spec for the web-facing dynamic-settings endpoint (§5) — the app currently has to stub this.
- [ ] Get a fresh Apple-SSO endpoint from the backend team if/when Apple sign-in is needed on web — don't reuse the mobile app's legacy one.

**Docs**
- [ ] Update this file when a new endpoint/model is added so it doesn't go stale the way the old endpoints doc did.
