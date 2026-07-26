# Web Port Reference — Moodle Backend Integration

_Compiled 2026-07-25 from the current `excellence_academy` (Academy) Flutter codebase (not from the old, stale API-endpoints Word doc in the repo root, which only covers 2 of 13 features and references an old domain)._

This is the reference doc for the team building the Next.js web client against the same backend. It lists every endpoint, model, and integration point the Flutter app uses, real response-body JSON for every endpoint (§10), plus porting notes and an agent checklist.

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

> Response body examples (raw JSON, real key names) for every endpoint in this and the next section are in **§10**.

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

Same request shape everywhere: `token`, `function`, often `alang` (`ar`/`en`) for translated text. Response: `{status, data}` or `{status:"fail", error}`. See **§10** for real response body examples.

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

---

## 10. Response shapes (by endpoint)

Every key below is the **raw** key read by the app's `fromJson` parsing code (mostly `lib/features/*/data/models/*.dart`) — not the app's internal camelCase Dart field names from §6. Helpers like `_asInt`/`_asString`/`_asTime` only coerce types, they never rename keys, so what's shown here is what the backend actually sends. Endpoints marked **no model** are consumed as a raw `Map`/`List` at the call site rather than through a dedicated model — build a loose type for those rather than a strict one, since the app itself doesn't rely on their full shape either.

Three response envelopes are in play across the app — know which one you're dealing with before writing a parser:

1. **Standard Moodle REST** (`/webservice/rest/server.php`) — payload returned bare; failure is a top-level `{"exception": "...", "errorcode": "...", "message": "..."}`. This also covers `local_payments_*` despite the plugin-style name.
2. **`local_academy` dispatcher** (`/local/academy/api.php`) — always `{"status": "success", "data": ...}` or `{"status": "fail", "error": "..."}`. Covers quiz, teachers (`get_all_teachers`), lessons, coupons, packages, subscriptions, programs, and `preview_discount`.
3. **Bespoke PHP** (`/login/token.php`, `/webservice/upload.php`, `/local/multitopics/getalltopics.php`) — each has its own ad-hoc shape, documented per-endpoint below.

### Auth

#### `local_profilefields_get_profile_fields`
Bare array.
```json
[
  { "id": 3, "shortname": "year", "name": "Academic year", "datatype": "menu",
    "required": 1, "options": ["Secondary 1", "Secondary 2", "Secondary 3"] }
]
```

#### `core_webservice_get_site_info`
No model — raw `Map`. App reads `token` (merged in from login), `userid`, `username`, `firstname`, `lastname`, `userpictureurl`.
```json
{
  "sitename": "Excellence Academy",
  "username": "ahmed.hassan",
  "firstname": "Ahmed",
  "lastname": "Hassan",
  "fullname": "Ahmed Hassan",
  "lang": "ar",
  "userid": 1842,
  "siteurl": "https://lms.excellence-academy.com",
  "userpictureurl": "https://lms.excellence-academy.com/webservice/pluginfile.php/62/user/icon/boost/f1?rev=4471",
  "release": "4.3.5 (Build: 20240712)",
  "version": "2023100905.02",
  "userissiteadmin": false
}
```
Error (checked via `exception != null`):
```json
{ "exception": "moodle_exception", "errorcode": "invalidtoken", "message": "Invalid token - token not found" }
```

#### `core_user_get_users_by_field`
No model — raw `List`. App reads only `phone1` and `customfields[]` where `shortname` is `year` or `ParentPhone`.
```json
[
  {
    "id": 1842, "username": "ahmed.hassan", "firstname": "Ahmed", "lastname": "Hassan",
    "email": "ahmed.hassan@example.com", "phone1": "01001234567",
    "customfields": [
      { "type": "menu", "value": "Secondary 2", "shortname": "year" },
      { "type": "text", "value": "01119876543", "shortname": "ParentPhone" }
    ]
  }
]
```

#### `core_user_create_users`
No model — raw `List`; app reads only `[0].id`.
```json
[ { "id": 1842, "username": "ahmed.hassan@example.com" } ]
```
Failure:
```json
{ "exception": "invalid_parameter_exception", "errorcode": "invalidparameter", "debuginfo": "Username already exists: ahmed.hassan@example.com" }
```

#### `core_user_update_users`
No model — raw `Map`. Moodle returns `null` on success (normalized to `{}` by the app). Reused for profile edit, password reset, and attaching an uploaded profile photo.
```json
{ "warnings": [] }
```

#### `core_user_delete_users`
No model — raw `Map`. Returns `null` on success.
```json
{ "warnings": [] }
```

#### `/webservice/upload.php`
No model — raw `List` (may arrive as a JSON *string*, which the app `jsonDecode`s). App reads only `[0].itemid`.
```json
[
  { "component": "user", "contextid": 5, "filearea": "draft",
    "filename": "profile.jpg", "itemid": 528931744 }
]
```

#### `/login/token.php`
No model — raw `Map`. App reads `token`; on failure, `message`/`error`.
```json
{ "token": "8f3a1c9e2b7d4056a1e9c4f7b2d80a63", "privatetoken": "kQ2vL8xNpR3tYw7ZaB1cD5eF9gH0jK4m" }
```
Failure:
```json
{ "error": "Invalid login, please try again", "errorcode": "invalidlogin", "message": "Invalid login, please try again" }
```

> Not in the endpoint tables but in the same datasource: `/local/googleauth/google_login.php` returns `{token, userid, username, firstname, lastname, error}`.

---

### Home / Courses

#### `core_enrol_get_users_courses`
No model — raw `List`. App reads `id`, `fullname`, `visible`, `startdate`, `enddate`, `progress`, `completed`, `overviewfiles[0].fileurl`.
```json
[
  {
    "id": 54, "fullname": "Physics — Secondary 2", "visible": 1,
    "progress": 42.5, "completed": false,
    "startdate": 1756684800, "enddate": 1783036800,
    "category": 12, "lastaccess": 1785312400,
    "overviewfiles": [
      { "filename": "physics-cover.jpg", "fileurl": "https://lms.excellence-academy.com/webservice/pluginfile.php/331/course/overviewfiles/physics-cover.jpg", "mimetype": "image/jpeg" }
    ]
  }
]
```

#### `enrol_manual_enrol_users`
No model — raw `Map?`. Moodle returns `null` on success.
```json
null
```
Failure:
```json
{ "exception": "required_capability_exception", "errorcode": "nopermissions", "message": "Sorry, but you do not currently have permissions to do that (Enrol users)" }
```

#### `core_course_get_courses_by_field`
No model — raw `Map`. App reads `courses[]`, and per course `id`, `categoryid`, `visible`, `startdate`, `enddate`, `overviewfiles`, `fullname`, `customfields[]` (`shortname == "year"` → `value`).
```json
{
  "courses": [
    {
      "id": 54, "fullname": "Physics — Secondary 2", "shortname": "PHY-S2",
      "categoryid": 12, "visible": 1, "format": "multitopics",
      "startdate": 1756684800, "enddate": 1783036800,
      "overviewfiles": [
        { "fileurl": "https://lms.excellence-academy.com/webservice/pluginfile.php/331/course/overviewfiles/physics-cover.jpg" }
      ],
      "customfields": [
        { "name": "Academic year", "shortname": "year", "value": "Secondary 2" }
      ]
    }
  ],
  "warnings": []
}
```

#### `local_payments_get_courses_with_pricing`
Envelope `{ "courses": [...] }`; each item → `Course.fromJson`. Raw keys: `id`, `fullname`, `shortname`, `summary`, `categoryid`, `visible`, `image_url` or `overviewfiles[0].fileurl`, `pricing_country`, `currency`, `price`, `original_price`, `sale_price`, `discount_percentage`, `is_sale_active`, `sale_ends_at`, `is_free`, `is_purchased`, `is_enrolled`, `offer_name`.
```json
{
  "courses": [
    {
      "id": 54, "fullname": "Physics — Secondary 2", "shortname": "PHY-S2",
      "summary": "<p>Full-year physics course.</p>", "categoryid": 12, "visible": 1,
      "overviewfiles": [
        { "fileurl": "https://lms.excellence-academy.com/webservice/pluginfile.php/331/course/overviewfiles/physics-cover.jpg" }
      ],
      "pricing_country": "EG", "currency": "EGP", "price": 1200,
      "original_price": 1500, "sale_price": 950, "discount_percentage": 37,
      "is_sale_active": true, "sale_ends_at": 1786060800,
      "is_free": false, "is_purchased": false, "is_enrolled": false,
      "offer_name": "Back to School + Summer Sale"
    }
  ]
}
```

#### `core_course_search_courses`
No model — raw `Map`; `courses[]` items are re-priced via the call above (only `id` is used from search results directly).
```json
{
  "total": 3,
  "courses": [
    {
      "id": 54, "fullname": "Physics — Secondary 2", "shortname": "PHY-S2",
      "categoryid": 12, "summary": "<p>Full-year physics course.</p>",
      "overviewfiles": [ { "fileurl": "https://.../physics-cover.jpg", "mimetype": "image/jpeg" } ],
      "startdate": 1756684800, "enddate": 1783036800
    }
  ],
  "warnings": []
}
```

#### `core_course_get_categories`
Bare array → `CourseCategory.fromJson`. Raw keys: `id`, `name`, `parent`, `coursecount`, `sortorder`.
```json
[
  { "id": 12, "name": "Secondary 2", "parent": 0, "sortorder": 30000,
    "coursecount": 9, "visible": 1, "depth": 1, "path": "/12" }
]
```

#### `core_completion_get_activities_completion_status`
Envelope `{ "statuses": [...] }`; only `cmid`, `state`, `tracking` per row are read (rows with `tracking == 0` are skipped).
```json
{
  "statuses": [
    { "cmid": 4471, "modname": "resource", "instance": 812,
      "state": 1, "tracking": 2, "timecompleted": 1785200000 }
  ],
  "warnings": []
}
```

#### `core_completion_update_activity_completion_status_manually`
No model — only `exception`/`status` checked.
```json
{ "status": true, "warnings": [] }
```

#### `mod_*_view_*` (resource / url / page / folder / book / lesson / scorm)
Fire-and-forget view-tracking ping — the body is discarded, only presence of `exception` is checked.
```json
{ "status": true, "warnings": [] }
```

#### `core_course_get_contents`
Used to resolve a module's `instance` id from its `cmid` (reads `modules[].id` / `modules[].instance`), and as a fallback content-tree source. Bare array of sections.
```json
[
  {
    "id": 991, "name": "Unit 1 — Kinematics", "visible": 1, "section": 1,
    "modules": [
      {
        "id": 4471, "instance": 812, "modname": "resource",
        "name": "Lecture 1 — Motion in a straight line", "visible": 1,
        "url": "https://lms.excellence-academy.com/mod/resource/view.php?id=4471",
        "modicon": "https://lms.excellence-academy.com/theme/image.php/boost/core/1784500000/f/pdf",
        "availabilityinfo": "",
        "contents": [
          { "type": "file", "filename": "lecture-01.pdf", "fileurl": "https://lms.excellence-academy.com/webservice/pluginfile.php/8890/mod_resource/content/3/lecture-01.pdf", "mimetype": "application/pdf" }
        ]
      }
    ]
  }
]
```

#### `/local/multitopics/getalltopics.php`
The largest response in the app — model chain `CourseResponse` → `ParentSection` → `TopicSection` → `Activity` → `JitsiSession` → `JitsiRecording` (all in `course_models.dart`).
```json
{
  "courseid": 54,
  "fullname": "Physics — Secondary 2",
  "format": "multitopics",
  "isavailable": true,
  "status": "available",
  "other_fields": {
    "zoom_url": "https://zoom.us/j/98213347711?pwd=Rk5qb1J",
    "zoom_starts_at": 1785398400
  },
  "parents": [
    {
      "id": "991",
      "sectionnum": 1,
      "name": "Unit 1 — Kinematics",
      "parent": true,
      "activities": [
        {
          "id": "4471", "instance": "812", "modname": "resource",
          "name": "Lecture 1 — Motion in a straight line", "sectionnum": "1",
          "visible": true, "uservisible": true,
          "url": "https://lms.excellence-academy.com/mod/resource/view.php?id=4471",
          "tags": ["kinematics", "week1"],
          "modicon": "https://lms.excellence-academy.com/theme/image.php/boost/core/1784500000/f/pdf",
          "resourcetype": "pdf",
          "fileurl": "https://lms.excellence-academy.com/webservice/pluginfile.php/8890/mod_resource/content/3/lecture-01.pdf",
          "locked": false, "availabilityinfo": "", "jitsi_session": null
        }
      ],
      "topics": [
        {
          "id": "992", "sectionnum": 2, "name": "Week 2 — Live sessions",
          "activities": [
            {
              "id": "4488", "instance": "77", "modname": "jitsi",
              "name": "Live revision — Newton's laws", "sectionnum": "2",
              "visible": true, "uservisible": true,
              "url": "https://lms.excellence-academy.com/mod/jitsi/view.php?id=4488",
              "tags": [], "resourcetype": "jitsi", "fileurl": "", "locked": false,
              "availabilityinfo": "Not available unless: You complete Lecture 1",
              "jitsi_session": {
                "server_url": "https://meet.excellence-academy.com",
                "room": "cm-4488-revision",
                "jwt": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
                "subject": "Live revision — Newton's laws",
                "is_teacher": false, "available": true, "available_info": "",
                "whiteboard_url": "https://board.excellence-academy.com/b/cm-4488",
                "feature_flags": { "chat.enabled": true, "screen-sharing.enabled": false, "recording.enabled": true },
                "recordings": [
                  {
                    "id": 3312, "title": "Live revision — Newton's laws (2026-07-12)", "status": "ready",
                    "playback_url": "https://rec.excellence-academy.com/play/3312.mp4",
                    "thumbnail_url": "https://rec.excellence-academy.com/thumb/3312.jpg",
                    "embed_url": "https://rec.excellence-academy.com/embed/3312",
                    "timecreated": 1784563200
                  }
                ]
              }
            }
          ]
        }
      ]
    }
  ]
}
```

---

### Quiz
All calls except `mod_quiz_view_quiz` are `local_academy` (envelope `{status, data}`).

#### `mod_quiz_view_quiz`
Fire-and-forget, response discarded. `{ "status": true, "warnings": [] }`

#### `get_quiz`
```json
{
  "status": "success",
  "data": {
    "quizid": 233, "cmid": 4502, "courseid": 54, "name": "Unit 1 Quiz — Kinematics",
    "intro": "<p>10 questions, 15 minutes.</p>", "timelimit": 900, "attempts_allowed": 3,
    "questions": [
      {
        "slot": 1, "questionid": 8811, "type": "multichoice",
        "text": "Which graph represents constant acceleration?", "images": [],
        "defaultmark": 1.0, "supported": true, "single": true,
        "options": [
          { "id": 30451, "text": "A straight line with positive slope", "images": [], "correct": true },
          { "id": 30452, "text": "A horizontal line at zero", "images": [], "correct": false }
        ]
      }
    ]
  }
}
```
`"correct"` is only present when fetched with the admin token (deliberate, for the answer-review UI).

#### `start_quiz_attempt`
```json
{
  "status": "success",
  "data": { "attemptid": 55219, "quizid": 233, "attempt_number": 2, "timestart": 1785312400, "timelimit": 900, "state": "inprogress" }
}
```

#### `submit_quiz_attempt`
```json
{
  "status": "success",
  "data": {
    "attemptid": 55219, "state": "finished", "score": 8.0, "max_score": 10.0, "percent": 80.0,
    "results": [
      { "questionid": 8811, "type": "multichoice", "mark": 1.0, "max_mark": 1.0, "correct": true },
      { "questionid": 8812, "type": "truefalse", "mark": 0.0, "max_mark": 1.0, "correct": false }
    ]
  }
}
```

#### `get_quiz_attempt`
`questions[]` is kept loosely typed (no dedicated model) and passed through as-is.
```json
{
  "status": "success",
  "data": {
    "attemptid": 55219, "quizid": 233, "quiz_name": "Unit 1 Quiz — Kinematics",
    "attempt_number": 2, "state": "finished",
    "timestart": 1785312400, "timefinish": 1785313180,
    "score": 8.0, "max_score": 10.0, "percent": 80.0,
    "questions": [
      {
        "slot": 1, "questionid": 8811, "type": "multichoice",
        "mark": 1.0, "max_mark": 1.0, "correct": true,
        "answer": 30451, "correct_answer": 30451,
        "options": [ { "id": 30451, "text": "...", "correct": true } ]
      }
    ]
  }
}
```

#### `get_my_quiz_attempts`
`data` is an array.
```json
{
  "status": "success",
  "data": [
    { "attemptid": 55219, "attempt_number": 2, "state": "finished", "score": 8.0, "max_score": 10.0, "percent": 80.0, "timestart": 1785312400, "timefinish": 1785313180 },
    { "attemptid": 54088, "attempt_number": 1, "state": "abandoned", "score": null, "max_score": 10.0, "percent": null, "timestart": 1785100000, "timefinish": 0 }
  ]
}
```

---

### Calendar

#### `core_calendar_get_calendar_monthly_view`
```json
{
  "periodname": "July 2026",
  "previousperiod": { "year": 2026, "mon": 6 },
  "nextperiod": { "year": 2026, "mon": 8 },
  "date": { "year": 2026, "mon": 7 },
  "daynames": [ { "shortname": "Sun" }, { "shortname": "Mon" } ],
  "weeks": [
    {
      "prepadding": [3, 4],
      "postpadding": [],
      "days": [
        {
          "mday": 26, "timestamp": 1785110400, "istoday": true, "isweekend": false,
          "events": [
            {
              "id": 7712, "name": "Unit 1 Quiz — Kinematics is due", "eventtype": "due",
              "timestart": 1785196740, "modulename": "quiz", "courseid": 54,
              "formattedtime": "<a href=\"https://lms.excellence-academy.com/mod/quiz/view.php?id=4502\">11:59 PM</a>",
              "url": "https://lms.excellence-academy.com/mod/quiz/view.php?id=4502",
              "course": { "id": 54, "fullname": "Physics — Secondary 2" }
            }
          ]
        }
      ]
    }
  ]
}
```

---

### Messages

#### `core_message_get_conversations`
```json
{
  "conversations": [
    {
      "id": 4021, "type": 1, "name": "", "imageurl": "https://lms.excellence-academy.com/webservice/pluginfile.php/62/user/icon/boost/f1?rev=5510",
      "unreadcount": 2,
      "members": [
        { "id": 907, "fullname": "Dr. Mona Saleh", "profileimageurl": "https://.../f1?rev=5510", "isonline": true, "isblocked": false }
      ],
      "messages": [
        { "id": 91145, "useridfrom": 907, "text": "<p dir=\"rtl\">تم رفع مراجعة الوحدة الأولى</p>", "timecreated": 1785301122 }
      ]
    }
  ]
}
```

#### `core_message_get_conversation_messages`
```json
{
  "id": 4021,
  "members": [
    { "id": 907, "fullname": "Dr. Mona Saleh", "profileimageurl": "https://.../f1?rev=5510", "isonline": true, "isblocked": false }
  ],
  "messages": [
    { "id": 91145, "useridfrom": 907, "text": "<p dir=\"rtl\">تم رفع مراجعة الوحدة الأولى</p>", "timecreated": 1785301122 },
    { "id": 91146, "useridfrom": 1842, "text": "<p dir=\"rtl\">شكراً يا دكتورة</p>", "timecreated": 1785301480 }
  ]
}
```

#### `core_message_send_messages_to_conversation`
Bare array; app reads `[0]`.
```json
[ { "id": 91147, "useridfrom": 1842, "text": "<p dir=\"rtl\">متى موعد المراجعة القادمة؟</p>", "timecreated": 1785312400 } ]
```

#### `core_message_send_instant_messages`
Bare array; app reads `[0]`. `msgid == -1` or a non-empty `errormessage` means failure.
```json
[
  { "msgid": 91148, "conversationid": 4021, "useridfrom": 1842, "text": "مرحباً دكتورة، لدي سؤال عن الواجب", "timecreated": 1785312460, "errormessage": null }
]
```

#### `core_message_mark_all_conversation_messages_as_read`
No payload consumed — only checked for a top-level `exception`.
```json
null
```

#### `core_message_get_unread_conversation_counts`
No model — app sums `types["1"] + types["2"]`.
```json
{ "types": { "1": 3, "2": 0, "3": 0 }, "favourites": 0 }
```

---

### Notifications

#### `core_message_get_messages`
Envelope `{ "messages": [...] }`, called twice (`read=0`, `read=1`) and merged client-side. The **entire raw object per message** is also retained for diagnostic logging (see the notification-navigation feature).
```json
{
  "messages": [
    {
      "id": 60233, "subject": "Lesson confirmed",
      "text": "<p>Your lesson with Dr. Mona Saleh is confirmed for Sunday 10:00.</p>",
      "fullmessage": "Your lesson with Dr. Mona Saleh is confirmed for Sunday 10:00.",
      "fullmessagehtml": "<p>Your lesson with Dr. Mona Saleh is confirmed for Sunday 10:00.</p>",
      "smallmessage": "Lesson confirmed for Sunday 10:00",
      "contexturl": "https://lms.excellence-academy.com/local/academy/lesson.php?id=3391",
      "contexturlname": "View lesson",
      "timecreated": 1785299000, "timeread": 0,
      "userfromfullname": "Excellence Academy",
      "component": "local_academy", "eventtype": "lessonconfirmed",
      "customdata": "{\"lessonid\":3391,\"teacherid\":907}"
    }
  ],
  "warnings": []
}
```

#### `message_popup_get_unread_popup_notification_count`
Bare integer, not an object.
```json
4
```

#### `core_message_mark_all_notifications_as_read`
Response discarded entirely by the app. `true`

#### `core_message_mark_notification_read`
Response discarded entirely by the app.
```json
{ "notificationid": 60233, "warnings": [] }
```

---

### Payments / Invoices
All `local_payments_*` calls throw a `PaymentException` when a top-level `exception` is present.

#### `local_payments_get_course_access`
```json
{ "courseid": 54, "is_enrolled": false, "is_purchased": false, "has_pending_payment": true, "payment_status": "pending", "order_id": "EA-ORD-20260726-8841" }
```

#### `local_payments_get_course_price`
Note: this uses `country`, not `pricing_country` like the bulk-pricing endpoint above.
```json
{
  "courseid": 54, "country": "EG", "currency": "EGP", "price": 1200,
  "original_price": 1500, "sale_price": 950, "is_sale_active": true,
  "discount_percentage": 37, "sale_ends_at": 1786060800,
  "is_enrolled": false, "is_purchased": false
}
```

#### `local_payments_create_checkout`
Same `CheckoutSession` shape reused by `create_package_checkout` / `create_subscription_checkout` / `create_program_checkout`.
```json
{
  "order_id": "EA-ORD-20260726-8841",
  "checkout_url": "https://checkout.kashier.io/?merchantId=MID-2841&orderId=EA-ORD-20260726-8841&amount=950&currency=EGP&hash=b71f2c",
  "expires_at": 1785316000, "provider": "kashier", "transaction_id": 20117
}
```

#### `local_payments_get_payment_history`
Bare array.
```json
[
  {
    "transaction_id": 20117, "order_id": "EA-ORD-20260726-8841", "courseid": 54,
    "course_name": "Physics — Secondary 2", "amount": 950, "original_amount": 1500,
    "currency": "EGP", "status": "paid", "provider": "kashier", "payment_method": "card",
    "invoice_number": "INV-2026-004417", "timecreated": 1785312500
  }
]
```

#### `local_payments_get_invoice`
```json
{
  "invoice_number": "INV-2026-004417", "amount": 950, "original_amount": 1500,
  "currency": "EGP", "status": "issued", "order_id": "EA-ORD-20260726-8841",
  "course_name": "Physics — Secondary 2", "payment_date": 1785312500, "invoice_date": 1785312540
}
```

#### `local_payments_verify_payment`
```json
{ "success": true, "enrolled": true, "status": "paid", "courseid": 54 }
```

#### `/local/payments/callback.php`
**No JSON response** — a browser redirect only. The app detects the hit by URL substring, then calls `local_payments_verify_payment` for the actual outcome; the provider's own query params (`paymentStatus`, `orderId`, etc.) are not parsed client-side.

---

### Teachers

#### `core_enrol_get_enrolled_users`
Bare array; the app filters `roles[].shortname` to `editingteacher`/`teacher` client-side (no server-side role filter exists), then maps to a `Teacher` using only `id`, `fullname`, `email`, `profileimageurl`.
```json
[
  {
    "id": 907, "firstname": "Mona", "lastname": "Saleh", "fullname": "Dr. Mona Saleh",
    "email": "mona.saleh@excellence-academy.com",
    "profileimageurl": "https://lms.excellence-academy.com/webservice/pluginfile.php/62/user/icon/boost/f1?rev=5510",
    "roles": [ { "roleid": 3, "shortname": "editingteacher" } ]
  }
]
```

#### `get_all_teachers`
```json
{
  "status": "success",
  "data": {
    "total": 47, "page": 0, "perpage": 20,
    "teachers": [
      {
        "userid": 907, "fullname": "Dr. Mona Saleh", "email": "mona.saleh@excellence-academy.com",
        "headline": "Physics teacher, 12 years of experience",
        "bio": "Specialises in mechanics and exam preparation for Secondary 2 and 3.",
        "experience": "12 years",
        "photourl": "https://lms.excellence-academy.com/webservice/pluginfile.php/62/user/icon/boost/f1?rev=5510",
        "rating": 4.8, "approved": 1, "available": 1,
        "subjects": [
          { "subject": "Physics", "specialization": "Mechanics" },
          { "subject": "Mathematics", "specialization": "" }
        ],
        "hours": [
          { "dayofweek": 0, "starttime": "10:00", "endtime": "14:00" },
          { "dayofweek": 3, "starttime": "16:00", "endtime": "20:00" }
        ],
        "busy_times": [ [1785398400, 1785402000], [1785484800, 1785488400] ]
      }
    ]
  }
}
```

---

### Lessons

`Lesson.fromJson` tolerates **both snake_case and camelCase** for nearly every key (it tries several aliases in order, first non-null wins) — pick one canonical shape from your own backend rather than reimplementing the alias fallbacks. Key aliases as tried by the mobile app:

| field | keys tried, in order |
|---|---|
| id | `id`, `lessonid`, `lesson_id` |
| subject | `subject`, `subjectname` |
| my_role | `my_role`, `myRole`, `role` |
| requested_time | `requested_time`, `requestedTime` |
| confirmed_time | `confirmed_time`, `confirmedTime` |
| suggested_time | `suggested_time`, `suggestedTime` |
| actual_start / actual_end | `actual_start`/`actualStart`, `actual_end`/`actualEnd` |
| note | `note`, `reason` |
| teacher_name | `teacher_name`, `teachername`, `teacher_fullname` |
| student_name | `student_name`, `studentname`, `student_fullname` |
| actions | `actions` (array of strings) |
| proposals | `proposals`, `updates`, `time_updates` |
| can_join / join_url | `can_join`/`canJoin`, `join_url`/`joinUrl` |

`LessonProposal`: `proposed_time`\|`proposedTime`\|`time`; `proposed_by`\|`proposedBy`\|`by`\|`role`; `status`.
`LessonJitsiSession`: `server_url`, `room`, `jwt`, `subject`, `is_teacher`, `available`, `available_info`, `feature_flags` — **no `recordings[]`/`whiteboard_url`**, unlike the course-layer `JitsiSession` in §"Home / Courses" above.

#### `get_my_lessons`
```json
{
  "status": "success",
  "data": [
    {
      "id": 3391, "subject": "Physics", "status": "confirmed", "my_role": "student",
      "requested_time": 1785398400, "confirmed_time": 1785402000,
      "teacherid": 907, "studentid": 1842,
      "teacher_name": "Dr. Mona Saleh", "student_name": "Ahmed Hassan",
      "actions": ["cancel", "request_time_update", "report_teacher_absent"],
      "proposals": [], "can_join": false, "cmid": 4610, "jitsi_session": null
    }
  ]
}
```

#### `get_lesson`
Same shape as above, single object, typically with the live-room fields populated.
```json
{
  "status": "success",
  "data": {
    "id": 3391, "subject": "Physics", "status": "in_progress", "my_role": "student",
    "confirmed_time": 1785402000, "actual_start": 1785402060,
    "teacher_name": "Dr. Mona Saleh", "student_name": "Ahmed Hassan",
    "actions": ["report_teacher_absent"],
    "proposals": [ { "proposed_time": 1785405600, "proposed_by": "teacher", "status": "pending" } ],
    "can_join": true, "join_url": "https://meet.excellence-academy.com/lesson-3391", "cmid": 4610,
    "jitsi_session": {
      "server_url": "https://meet.excellence-academy.com", "room": "lesson-3391",
      "jwt": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
      "is_teacher": false, "available": true,
      "feature_flags": { "chat.enabled": true, "raise-hand.enabled": true, "recording.enabled": false }
    }
  }
}
```

#### `get_flex_history`
```json
{
  "status": "success",
  "data": [
    { "id": 1188, "type": "reserve", "amount": -1, "balance": 7, "lessonid": 3391, "note": "Reserved for lesson #3391 (Physics)", "timecreated": 1785300000 },
    { "id": 1189, "type": "return", "amount": 1, "balance": 8, "lessonid": 3388, "note": "Teacher absent — Flex returned", "timecreated": 1785220000 }
  ]
}
```

#### `get_lesson_settings`
```json
{
  "status": "success",
  "data": {
    "min_booking_minutes": 120, "cancel_deadline_minutes": 360,
    "update_deadline_minutes": 180, "start_allowed_minutes": 10, "absence_report_minutes": 15
  }
}
```

#### `request_lesson`
Returns the newly created lesson — same shape as `get_lesson`, typically `status: "pending"`.
```json
{
  "status": "success",
  "data": {
    "id": 3392, "subject": "Mathematics", "status": "pending", "my_role": "student",
    "requested_time": 1785484800, "teacherid": 907, "studentid": 1842,
    "actions": ["withdraw"], "can_join": false, "cmid": 0, "jitsi_session": null
  }
}
```

#### `student_respond_lesson`, `cancel_lesson_student`, `cancel_lesson_request`, `report_teacher_absent`, `request_time_update`, `respond_time_update`
**No model for any of these six** — the app only checks `status == "success"` and never reads `data`'s fields. The backend returns a small ad-hoc status object per action:
```json
// student_respond_lesson
{ "status": "success", "data": { "lessonid": 3391, "status": "confirmed", "confirmed_time": 1785402000, "flex_reserved": 1 } }
// cancel_lesson_student
{ "status": "success", "data": { "lessonid": 3391, "status": "cancelled", "flex_returned": 1, "flex_balance": 8 } }
// report_teacher_absent
{ "status": "success", "data": { "lessonid": 3391, "status": "teacher_absent", "flex_returned": 1, "flex_balance": 9 } }
```
Failure envelope (all lessons calls):
```json
{ "status": "fail", "error": "لا يمكن الإلغاء بعد بدء الدرس" }
```

---

### Coupons

#### `get_available_coupons`
Note: `startdate`/`enddate` have **no underscore** here, unlike almost every other academy-plugin key.
```json
{
  "status": "success",
  "data": [
    {
      "code": "SUMMER26", "status": "active", "discount_type": "percent",
      "discount_value": 20, "max_discount": 300, "usage_type": "once_per_user",
      "usage_limit": 500, "usage_count": 187,
      "startdate": 1783036800, "enddate": 1787097600,
      "applies_to": [ { "item_type": "course", "item_id": 54, "label": "Physics — Secondary 2" } ]
    }
  ]
}
```

---

### Packages

#### `get_available_packages`
```json
{
  "status": "success",
  "data": [
    {
      "id": "7", "name": "10 Flex Package", "description": "عشرة دروس مرنة صالحة لمدة 90 يوماً",
      "flex_count": "10", "price": "1800", "expiration_days": "90", "status": "active",
      "offer": { "name": "Summer Sale", "discount_type": "percent", "discount_value": 15, "discount": 270, "original": 1800, "final": 1530, "label": "خصم 15%" }
    }
  ]
}
```

#### `create_package_checkout`
Same `CheckoutSession` shape as `local_payments_create_checkout`.
```json
{
  "status": "success",
  "data": { "order_id": "EA-PKG-20260726-1174", "checkout_url": "https://checkout.kashier.io/?...", "expires_at": 1785316000, "provider": "kashier", "transaction_id": 20118 }
}
```

#### `preview_discount` (`item_type=package`)
This exact shape is shared by **all four** `preview_discount` calls (course/package/subscription/program) — build one client function, not four. An invalid coupon still returns `status: "success"`; rejection shows up as a non-null `coupon_error`.
```json
{
  "status": "success",
  "data": {
    "original": 1800, "offer_discount": 270, "offer_name": "Summer Sale",
    "coupon_discount": 153, "coupon_code": "SUMMER26", "discount": 423,
    "final": 1377, "coupon_error": null
  }
}
```
Rejected coupon (still `status: "success"`):
```json
{
  "status": "success",
  "data": { "original": 1800, "offer_discount": 270, "offer_name": "Summer Sale", "coupon_discount": 0, "coupon_code": "EXPIRED10", "discount": 270, "final": 1530, "coupon_error": "هذا الكوبون منتهي الصلاحية" }
}
```

#### `get_my_packages`
```json
{
  "status": "success",
  "data": [
    { "id": 442, "packageid": 7, "name": "10 Flex Package", "total_flex": 10, "remaining_flex": 8, "used_flex": 2, "price_paid": "1530", "status": "active", "timeactivated": 1783200000, "expires_at": 1790976000, "expiration_days": 90 }
  ]
}
```

#### `get_payment_history` (Packages)
```json
{
  "status": "success",
  "data": [
    { "id": 3081, "packageid": 7, "name": "10 Flex Package", "amount": "1530", "method": "online", "reference": "EA-PKG-20260726-1174", "transaction_no": "KSH-TX-77412093", "status": "completed", "timecreated": 1783199400 }
  ]
}
```

#### `purchase_package` (legacy — skip)
```json
{
  "status": "success",
  "data": { "purchaseid": 442, "paymentid": 3081, "transaction_no": "KSH-TX-77412093", "flex_balance": 10, "expires_at": 1790976000, "status": "active" }
}
```

---

### Subscriptions

#### `get_available_subscriptions`
```json
{
  "status": "success",
  "data": [
    {
      "id": 4, "name": "Secondary 2 — Full Term", "description": "كل مواد الصف الثاني الثانوي لمدة فصل دراسي",
      "price": "3500", "duration_days": 120, "status": "active",
      "courses": [ { "id": 54, "fullname": "Physics — Secondary 2" }, { "id": 55, "fullname": "Chemistry — Secondary 2" } ],
      "offer": { "name": "Early Bird", "discount_type": "fixed", "discount_value": 500, "discount": 500, "original": 3500, "final": 3000, "label": "خصم 500 ج.م" }
    }
  ]
}
```

#### `preview_discount` (`item_type=subscription`)
Same shared shape as the package one above.
```json
{
  "status": "success",
  "data": { "original": 3500, "offer_discount": 500, "offer_name": "Early Bird", "coupon_discount": 600, "coupon_code": "SUMMER26", "discount": 1100, "final": 2400, "coupon_error": null }
}
```

#### `create_subscription_checkout`
Same `CheckoutSession` shape.
```json
{
  "status": "success",
  "data": { "order_id": "EA-SUB-20260726-0663", "checkout_url": "https://checkout.kashier.io/?...", "expires_at": 1785316000, "provider": "kashier", "transaction_id": 20119 }
}
```

#### `get_my_subscriptions`
```json
{
  "status": "success",
  "data": [
    {
      "id": 511, "subscriptionid": 4, "name": "Secondary 2 — Full Term", "price_paid": "2400",
      "status": "active", "timeactivated": 1783200000, "expires_at": 1793577600,
      "remaining_days": 97, "duration_days": 120,
      "courses": [ { "id": 54, "fullname": "Physics — Secondary 2" }, { "id": 55, "fullname": "Chemistry — Secondary 2" } ]
    }
  ]
}
```

#### `get_subscription_payment_history`
```json
{
  "status": "success",
  "data": [
    { "id": 3082, "subscriptionid": 4, "name": "Secondary 2 — Full Term", "amount": "2400", "method": "online", "reference": "EA-SUB-20260726-0663", "transaction_no": "KSH-TX-77412411", "status": "completed", "timecreated": 1783199800 }
  ]
}
```

#### `purchase_subscription` (legacy — skip)
```json
{
  "status": "success",
  "data": {
    "purchaseid": 511, "paymentid": 3082, "transaction_no": "KSH-TX-77412411", "status": "active",
    "timeactivated": 1783200000, "expires_at": 1793577600,
    "courses": [ { "id": 54, "fullname": "Physics — Secondary 2" }, { "id": 55, "fullname": "Chemistry — Secondary 2" } ]
  }
}
```

---

### Programs
`get_program_details` throws distinctly on two different failures: `status != "success"` → not-available (state error); a non-JSON body (5xx / HTML error page) → server error. Keep that distinction on the web port — the guide's existing `EmptyState` pattern (cloud icon + retry for server errors, "unavailable" wording for state errors) depends on it.

#### `get_catalogue_programs`
```json
{
  "status": "success",
  "data": [
    { "id": 18, "name": "Secondary 2 — Science Track", "description": "برنامج متكامل يشمل الفيزياء والكيمياء والأحياء", "free": 0, "price": 4200, "currency": "EGP", "offer": { "original": 4200, "final": 3570, "label": "خصم 15%" }, "owned": 0, "joinable": 0 },
    { "id": 21, "name": "Study Skills Starter", "description": "برنامج مجاني لمهارات المذاكرة", "free": 1, "price": 0, "currency": "EGP", "offer": null, "owned": 0, "joinable": 1 }
  ]
}
```

#### `get_my_programs`
```json
{
  "status": "success",
  "data": [
    { "id": 18, "name": "Secondary 2 — Science Track", "timeallocated": 1783200000, "timestart": 1783209600, "timedue": 1793577600, "timeend": 0, "timecompleted": 0, "completed": 0 }
  ]
}
```

#### `get_program_details`
```json
{
  "status": "success",
  "data": {
    "id": 18, "name": "Secondary 2 — Science Track",
    "description": "برنامج متكامل يشمل الفيزياء والكيمياء والأحياء",
    "description_html": "<p>برنامج متكامل يشمل <strong>الفيزياء</strong> والكيمياء والأحياء.</p>",
    "image": "https://lms.excellence-academy.com/pluginfile.php/1/enrol_programs/image/18/science-track.jpg",
    "free": 0, "price": 4200, "currency": "EGP",
    "offer": { "original": 4200, "final": 3570, "label": "خصم 15%" },
    "owned": 1, "joinable": 0,
    "allocation": { "timeallocated": 1783200000, "timestart": 1783209600, "timedue": 1793577600, "timeend": 0, "timecompleted": 0, "completed": 0 },
    "content": [
      {
        "itemid": 301, "type": "set", "name": "Core science subjects", "courseid": 0,
        "sequencetype": "All in order", "timecompleted": 0, "completed": 0,
        "children": [
          { "itemid": 302, "type": "course", "name": "Physics — Secondary 2", "courseid": 54, "timecompleted": 1785200000, "completed": 1, "children": [] },
          { "itemid": 303, "type": "course", "name": "Chemistry — Secondary 2", "courseid": 55, "timecompleted": 0, "completed": 0, "children": [] }
        ]
      }
    ]
  }
}
```

#### `join_program`
No model — only `status == "success"` is checked, `data` is ignored.
```json
{ "status": "success", "data": { "programid": 21, "allocated": 1, "timeallocated": 1785312400 } }
```
Failure:
```json
{ "status": "fail", "error": "Self-enrolment is closed for this program" }
```

#### `open_certificate`
Only `data.url` is read — a single-use, ~2-minute auto-login link.
```json
{ "status": "success", "data": { "url": "https://lms.excellence-academy.com/local/academy/autologin.php?key=Z9x3Kq1Ub7Ne&cmid=4733&expires=1785312520" } }
```

#### `preview_discount` (`item_type=program`)
Same shared shape as courses/packages/subscriptions.
```json
{
  "status": "success",
  "data": { "original": 4200, "offer_discount": 630, "offer_name": "Science Track Launch", "coupon_discount": 300, "coupon_code": "SUMMER26", "discount": 930, "final": 3270, "coupon_error": null }
}
```

#### `list_program_certificate_eligibility`
Note: the rules array key is **`results`**, not `rules`. `data` also tolerates a bare array in place of `{"certificates": [...]}`.
```json
{
  "status": "success",
  "data": {
    "certificates": [
      {
        "certificateid": 61, "name": "Science Track — Completion Certificate", "type": "completion",
        "eligible": 1, "enabled": 1, "operator": "and", "open_state": "open", "openable": 1, "externalref": 4733,
        "results": [
          { "passed": 1, "label": "Complete at least {percent}% of the program's courses", "actual": 100, "required": 90, "unit": "%" },
          { "passed": 0, "label": "Attend at least {count} live sessions", "actual": 6, "required": 8, "unit": "" }
        ]
      }
    ]
  }
}
```

#### `create_program_checkout`
Same `CheckoutSession` shape.
```json
{
  "status": "success",
  "data": { "order_id": "EA-PRG-20260726-0219", "checkout_url": "https://checkout.kashier.io/?...", "expires_at": 1785316000, "provider": "kashier", "transaction_id": 20120 }
}
```

---

### Non-JSON integrations

| Call | Shape |
|---|---|
| `https://ipapi.co/country_code/` | Plain text 2-letter code, e.g. `EG` |
| `https://api.country.is/` (fallback) | `{ "ip": "...", "country": "EG" }` |
| Jitsi web SDK/iframe | Not a REST call — inputs come from the `jitsi_session` objects embedded in `getalltopics.php` and `get_lesson` above |
| PDF invoice generation | Not a REST call — rendered client-side from `local_payments_get_invoice`'s response |
| `{mlang}` content parser | Not a REST call — a client-side string transform applied to already-fetched fields |

### Cross-cutting notes

1. **Three response envelopes**, know which one you're implementing before writing a parser — see the intro to this section.
2. **`preview_discount` never fails on a bad coupon.** It returns `status: "success"` with `coupon_discount: 0` and a populated `coupon_error`. One shape, reused by all four `item_type`s.
3. **`CheckoutSession` is one shape across four endpoints** (course/package/subscription/program checkout creation) — `provider` defaults to `"kashier"` when absent.
4. **Coupon date keys have no underscore** (`startdate`/`enddate`) — an exception to the academy plugin's otherwise-consistent snake_case.
5. **Program certificate rules live under `results`**, not `rules`.
6. **Lessons tolerate snake_case *and* camelCase** for nearly every key (see the alias table under Lessons) — the web port should pick one canonical set from the live backend, not reimplement the fallback chain.
7. **Two distinct Jitsi session shapes exist**: the course-layer one (`getalltopics.php`) has `whiteboard_url` + `recordings[]`; the lessons-layer one (`get_lesson`) does not.
