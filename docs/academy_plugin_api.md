# Excellence Academy — Moodle Plugin API Specification

> **Audience:** Back-end developer implementing `/local/academy/api.php`  
> **Base URL:** `https://<moodle-host>/local/academy/api.php`  
> **Auth:** Every call passes `token` (Moodle user web-service token) either as a query param (GET) or POST body field.

---

## 1. Response Envelope

Every endpoint returns JSON with the same outer shape.

**Success**
```json
{ "status": "success", "data": <payload> }
```

**Error**
```json
{ "status": "error", "error": "Human-readable message in Arabic or English" }
```

The mobile app throws the `error` string directly as a user-visible message — keep it short and descriptive.

---

## 2. General Conventions

| Convention | Detail |
|---|---|
| All timestamps | Unix seconds (integer). `0` means "not set". |
| Field names | `snake_case` throughout. |
| Read operations | HTTP GET, params in query string. |
| Write operations | HTTP POST, `application/x-www-form-urlencoded`. |
| Optional params | Omit from the body entirely if not provided — do not send empty strings for optional fields. |
| IDs | Integers; the mobile client may cast to string for display only. |

---

## 3. Packages API

### 3.1 `get_available_packages` — GET

Returns the catalogue of purchasable Flex packages.

**Request params:**
| Param | Type | Required |
|---|---|---|
| `function` | string | `get_available_packages` |
| `token` | string | ✓ |

**Response `data`:** `AvailablePackage[]`

```json
[
  {
    "id": "1",
    "name": "باقة 10 حصص",
    "description": "عشر حصص خصوصية صالحة لمدة 6 أشهر",
    "flex_count": "10",
    "price": "150.00",
    "expiration_days": "180",
    "status": "active"
  }
]
```

| Field | Type | Notes |
|---|---|---|
| `id` | string/int | Package definition ID |
| `name` | string | Display name |
| `description` | string | Marketing copy |
| `flex_count` | string/int | Flex credits included |
| `price` | string/decimal | Display price |
| `expiration_days` | string/int | Days until expiry after activation; `"0"` = never expires |
| `status` | string | `active` / `inactive` — only `active` packages are shown |

---

### 3.2 `purchase_package` — POST

Purchase a package. Creates a purchase record and payment record; activates the package if payment is instant.

**Request params:**
| Param | Type | Required |
|---|---|---|
| `function` | string | `purchase_package` |
| `token` | string | ✓ |
| `packageid` | int | ✓ |
| `method` | string | ✓ — `online` / `bank_transfer` / `cash` |
| `reference` | string | Optional — external transaction reference |

**Response `data`:** `PurchaseResult`

```json
{
  "purchaseid": 42,
  "paymentid": 17,
  "transaction_no": "TXN-2024-00042",
  "flex_balance": 10,
  "expires_at": 1750000000,
  "status": "active"
}
```

| Field | Notes |
|---|---|
| `purchaseid` | ID of the created user-package record |
| `paymentid` | ID of the payment record |
| `transaction_no` | Unique reference shown to user |
| `flex_balance` | User's total remaining Flex after purchase |
| `expires_at` | Unix timestamp when this package expires; `0` = never |
| `status` | `active` / `pending` (if awaiting payment confirmation) |

---

### 3.3 `get_my_packages` — GET

Returns all packages the authenticated user has purchased.

**Request params:** `function`, `token`

**Response `data`:** `MyPackage[]`

```json
[
  {
    "id": 42,
    "packageid": 1,
    "name": "باقة 10 حصص",
    "total_flex": 10,
    "remaining_flex": 7,
    "used_flex": 3,
    "price_paid": "150.00",
    "status": "active",
    "timeactivated": 1720000000,
    "expires_at": 1750000000,
    "expiration_days": 180
  }
]
```

| Field | Notes |
|---|---|
| `id` | User-package record ID |
| `packageid` | Original package definition ID |
| `status` | `active` / `expired` / `fully_used` / `cancelled` |
| `timeactivated` | Unix timestamp of activation |
| `expires_at` | `0` means never expires |

---

### 3.4 `get_payment_history` — GET

Returns the user's full payment ledger.

**Request params:** `function`, `token`

**Response `data`:** `PaymentRecord[]`

```json
[
  {
    "id": 17,
    "packageid": 1,
    "name": "باقة 10 حصص",
    "amount": "150.00",
    "method": "online",
    "reference": "",
    "transaction_no": "TXN-2024-00042",
    "status": "completed",
    "timecreated": 1720000000
  }
]
```

| `status` values | Meaning |
|---|---|
| `completed` | Payment confirmed, Flex credited |
| `pending` | Awaiting confirmation |
| `failed` | Payment failed |
| `refunded` | Refund issued |

---

## 4. Lessons & Flex API

### 4.1 Lesson Object

Every lesson endpoint that returns a lesson (or list of lessons) must include all fields below.

```json
{
  "id": 101,
  "studentid": 55,
  "student_name": "أحمد محمد",
  "student_photo": "https://moodle.example.com/...jpg",
  "teacherid": 12,
  "teacher_name": "محمد علي",
  "teacher_photo": "https://moodle.example.com/...jpg",
  "subject": "رياضيات",
  "status": "confirmed",
  "my_role": "student",
  "requested_time": 1720100000,
  "confirmed_time": 1720200000,
  "suggested_time": null,
  "actual_start": null,
  "actual_end": null,
  "note": "أحتاج مساعدة في التفاضل",
  "reject_reason": "",
  "cancel_reason": "",
  "can_join": false,
  "join_url": "",
  "cmid": 0,
  "sessionid": 0,
  "actions": ["cancel", "request_time_update", "report_teacher_absent"],
  "proposals": [],
  "jitsi_session": null
}
```

#### 4.1.1 Field Reference

| Field | Type | Notes |
|---|---|---|
| `id` | int | Lesson ID |
| `studentid` | int | Moodle user ID of student |
| `student_name` | string | Full name |
| `student_photo` | string | Absolute URL; empty string if none |
| `teacherid` | int | Moodle user ID of teacher |
| `teacher_name` | string | Full name |
| `teacher_photo` | string | Absolute URL; empty string if none |
| `subject` | string | Subject / topic |
| `status` | string | See §4.2 — State Machine |
| `my_role` | string | `"student"` or `"teacher"` — from the requesting user's perspective |
| `requested_time` | int\|null | Original requested time (unix seconds); `0` or `null` if unset |
| `confirmed_time` | int\|null | Time both parties agreed on |
| `suggested_time` | int\|null | Counter-proposal currently in negotiation |
| `actual_start` | int\|null | When teacher/system started the Jitsi room |
| `actual_end` | int\|null | When the room was closed |
| `note` | string | Student's booking note |
| `reject_reason` | string | Filled when teacher or student rejects |
| `cancel_reason` | string | Filled on cancellation |
| `can_join` | bool | `true` when the Jitsi room is live and open for this user |
| `join_url` | string | Web fallback URL (may be empty) |
| `cmid` | int | Moodle course-module ID of the BigBlueButton/Jitsi activity; `0` if none |
| `sessionid` | int | Session record ID; `0` if none |
| `actions` | string[] | Server-authoritative list of allowed actions for the caller (see §4.3) |
| `proposals` | Proposal[] | Pending time-update proposals (see §4.1.2) |
| `jitsi_session` | JitsiSession\|null | Present and non-null only when `can_join` is `true` (see §4.1.3) |

#### 4.1.2 Proposal Object

Returned inside `proposals[]` when a time-change request is outstanding.

```json
{
  "proposed_time": 1720300000,
  "proposed_by": "teacher",
  "status": "pending"
}
```

| Field | Values |
|---|---|
| `proposed_by` | `"student"` or `"teacher"` |
| `status` | `"pending"` / `"accepted"` / `"rejected"` |

#### 4.1.3 JitsiSession Object

**Only include this object when `can_join` is `true`.**  
The mobile app reads `available` to decide whether to let the user enter the room.

```json
{
  "server_url": "https://meet.jitsi.example.com",
  "room": "academy-lesson-101-abc123",
  "jwt": "<signed JWT>",
  "subject": "رياضيات — محمد علي",
  "is_teacher": false,
  "available": false,
  "available_info": "Waiting for the teacher to start the session",
  "host_id": "teacher_moodle_id_12",
  "feature_flags": {
    "recording.enabled": false,
    "screen-sharing.enabled": true,
    "chat.enabled": true,
    "raise-hand.enabled": true,
    "video.enabled": true,
    "audio.enabled": true,
    "livestreaming.enabled": false,
    "tile-view.enabled": true
  }
}
```

| Field | Type | Notes |
|---|---|---|
| `server_url` | string | Base URL of the Jitsi Meet server |
| `room` | string | Unique room name for this lesson |
| `jwt` | string | Signed JWT token for Jitsi auth |
| `subject` | string | Conference title shown in the Jitsi UI |
| `is_teacher` | bool | `true` if the requesting user is the teacher (moderator role in JWT) |
| `available` | bool | **Critical.** `false` = room exists but teacher hasn't opened it yet. The mobile app shows a dimmed "Waiting for teacher" button instead of the join button. |
| `available_info` | string | Human-readable explanation shown when `available` is `false`. May be in Arabic or English. |
| `host_id` | string | Teacher's Moodle user ID as string (used as Jitsi moderator ID) |
| `feature_flags` | object | Jitsi feature flags to pass to the SDK. All values are booleans. |

**`available` behavior:**
- When the teacher has not yet started/opened the Jitsi room: `available: false`, `available_info: "Waiting for the teacher to start the session"` (or Arabic equivalent)
- When the room is live and the teacher is present: `available: true`, `available_info: ""`
- The student's join button is only clickable when both `can_join: true` AND `available: true`

---

### 4.2 Lesson State Machine

```
                    [student requests]
                          │
                       pending
                    ┌──────────────────────────────┐
                    │ teacher accepts              │ teacher rejects
                    ▼                              ▼
              waiting_student                  rejected (terminal)
          ┌──────────────┬──────────┐
          │ student      │ student  │ student
          │ accepts      │ suggests │ rejects
          ▼              ▼          ▼
       confirmed   waiting_teacher  rejected (terminal)
          │              │
          │              │ teacher accepts/suggests → loops back
          │              │ teacher rejects → rejected (terminal)
          │
    [at lesson time]
          │
       in_progress
          │
    [room closed]
          │
       completed (terminal)
          │ (if student did not join)
       student_absent (terminal)
          │ (if teacher never started)
       teacher_absent (terminal)


Also from confirmed/in_progress:
  - Student cancels → cancelled (terminal)  [flex returned if early, consumed if late]
  - Teacher cancels → cancelled_teacher (terminal) [flex always returned]
  - Student reports teacher absent → teacher_absent (terminal) [flex returned]

Also from pending/waiting_teacher:
  - Student withdraws → cancelled (terminal)  [no flex involved]
```

**All status values:**

| Status | Description |
|---|---|
| `pending` | Student requested; awaiting teacher's first response |
| `waiting_student` | Teacher responded (accepted with time, or suggested time); student must reply |
| `waiting_teacher` | Student suggested time; teacher must reply |
| `confirmed` | Both agreed on a time; Flex is reserved |
| `in_progress` | Lesson is currently happening (room is open) |
| `completed` | Lesson finished normally; Flex consumed |
| `student_absent` | Lesson time passed; student never joined; Flex consumed |
| `teacher_absent` | Teacher never started; Flex returned to student |
| `rejected` | Teacher or student rejected the lesson request |
| `cancelled` | Student cancelled (early = Flex returned; late = Flex consumed) |
| `cancelled_teacher` | Teacher cancelled; Flex always returned to student |

---

### 4.3 Allowed Actions

The `actions[]` array in the lesson object tells the client which operations are currently valid. The server must still validate and reject any call that violates timing rules.

| Action string | Endpoint | Available when |
|---|---|---|
| `accept` | `student_respond_lesson` | `waiting_student` |
| `reject` | `student_respond_lesson` | `waiting_student` |
| `suggest` | `student_respond_lesson` | `waiting_student` |
| `withdraw` | `cancel_lesson_request` | `pending` or `waiting_teacher` |
| `cancel` | `cancel_lesson_student` | `confirmed` (within deadline) |
| `report_teacher_absent` | `report_teacher_absent` | `confirmed` or `in_progress` (after `absence_report_minutes`) |
| `request_time_update` | `request_time_update` | `confirmed`, no pending proposal, before `update_deadline_minutes` |
| `respond_time_update` | `respond_time_update` | `confirmed`, incoming proposal from the other party |

---

### 4.4 Flex Credit Rules

| Event | Effect on student's Flex |
|---|---|
| Student accepts (`waiting_student` → `confirmed`) | **Reserve** 1 Flex (deducted from balance, held) |
| Lesson completed normally | **Consume** the reserved 1 Flex (it stays deducted) |
| Student absent | **Consume** the reserved 1 Flex |
| Teacher absent (student reports) | **Return** the reserved 1 Flex |
| Student cancels early (before `cancel_deadline_minutes`) | **Return** the reserved 1 Flex |
| Student cancels late (after `cancel_deadline_minutes`) | **Consume** the reserved 1 Flex |
| Teacher cancels at any time | **Return** the reserved 1 Flex |
| Request withdrawn (before any Flex reservation) | No change |

The `get_flex_history` endpoint exposes the ledger with `type` = `reserve` / `consume` / `return`.

---

### 4.5 Lesson Settings

`get_lesson_settings` returns the admin-configurable timing windows.

| Field | Meaning |
|---|---|
| `min_booking_minutes` | Lesson must be requested at least this many minutes in the future |
| `cancel_deadline_minutes` | Cancellations before this many minutes before lesson time = Flex returned |
| `update_deadline_minutes` | Time-update requests must be submitted this many minutes before lesson time |
| `start_allowed_minutes` | Teacher can open the Jitsi room this many minutes before the confirmed time |
| `absence_report_minutes` | Student can report teacher absent this many minutes after the lesson was supposed to start |

---

## 5. Lesson Endpoints

### 5.1 `get_my_lessons` — GET

**Params:**

| Param | Required | Notes |
|---|---|---|
| `token` | ✓ | |
| `role` | ✓ | Always `"student"` from the mobile app |
| `status` | ✗ | Filter to a single status value; omit for all lessons |

**Response `data`:** `Lesson[]` — ordered newest first.

---

### 5.2 `get_lesson` — GET

**Params:** `token`, `lessonid` (int)

**Response `data`:** single `Lesson` object.

---

### 5.3 `get_flex_history` — GET

**Params:** `token`

**Response `data`:** `FlexTx[]`

```json
[
  {
    "id": 5,
    "type": "reserve",
    "amount": 1,
    "balance": 9,
    "lessonid": 101,
    "note": "حجز حصة #101",
    "timecreated": 1720200000
  }
]
```

| Field | Notes |
|---|---|
| `type` | `reserve` / `consume` / `return` |
| `amount` | Always positive; sign is implied by `type` |
| `balance` | Running remaining balance after this transaction |
| `lessonid` | Associated lesson; `null` for package purchases |

---

### 5.4 `get_lesson_settings` — GET

**Params:** `token`

**Response `data`:** single `LessonSettings` object (see §4.5).

---

### 5.5 `request_lesson` — POST

Student books a new lesson with a teacher.

**Params:**

| Param | Required | Type | Notes |
|---|---|---|---|
| `teacherid` | ✓ | int | Moodle teacher user ID |
| `subject` | ✓ | string | Subject/topic |
| `requested_time` | ✓ | int | Proposed lesson time (unix seconds) |
| `note` | ✗ | string | Student's note to the teacher |

**Response `data`:** newly created `Lesson` object with `status: "pending"`.

**Validation the server must enforce:**
- `requested_time` must be at least `min_booking_minutes` in the future.
- Student must have at least 1 available Flex from an active package (balance check, not deduction — reservation happens on accept).
- Teacher must exist and be available to teach.

---

### 5.6 `student_respond_lesson` — POST

Student responds to a `waiting_student` lesson.

**Params:**

| Param | Required | Notes |
|---|---|---|
| `lessonid` | ✓ | |
| `action` | ✓ | `accept` / `reject` / `suggest` |
| `suggested_time` | If `action=suggest` | Unix seconds |
| `reject_reason` | If `action=reject` | Optional free text |

**Outcomes:**

| Action | New status | Flex |
|---|---|---|
| `accept` | `confirmed` | Reserve 1 Flex |
| `reject` | `rejected` | — |
| `suggest` | `waiting_teacher` | — |

**Response `data`:** updated `Lesson` object.

---

### 5.7 `cancel_lesson_student` — POST

Student cancels a `confirmed` lesson.

**Params:** `lessonid`, `reason` (optional string)

**Outcomes based on timing:**
- Before `cancel_deadline_minutes` before lesson: status → `cancelled`, Flex returned.
- After that deadline: status → `cancelled`, Flex consumed.

**Response `data`:** updated `Lesson` object.

---

### 5.8 `cancel_lesson_request` — POST

Student withdraws a lesson while it is `pending` or `waiting_teacher` (no Flex involved yet).

**Params:** `lessonid`, `reason` (optional string)

**Response `data`:** updated `Lesson` object with `status: "cancelled"`.

---

### 5.9 `report_teacher_absent` — POST

Student reports the teacher didn't show up.

**Params:** `lessonid`

**Validation:** Current time must be at least `absence_report_minutes` past `confirmed_time`.

**Outcome:** status → `teacher_absent`, Flex returned.

**Response `data`:** updated `Lesson` object.

---

### 5.10 `request_time_update` — POST

Student requests to reschedule a `confirmed` lesson.

**Params:** `lessonid`, `proposed_time` (unix seconds)

**Validation:**
- Current time must be before `update_deadline_minutes` before the confirmed lesson time.
- No other pending proposal may exist.

**Outcome:** status stays `confirmed`; a `Proposal` object is added to `proposals[]` with `status: "pending"` and `proposed_by: "student"`.

**Response `data`:** updated `Lesson` object.

---

### 5.11 `respond_time_update` — POST

Accept or reject the other party's time-update proposal.

**Params:** `lessonid`, `action` (`accept` / `reject`)

**Outcomes:**

| Action | Result |
|---|---|
| `accept` | `confirmed_time` updated to `proposed_time`; proposal marked `accepted` |
| `reject` | Proposal marked `rejected`; `confirmed_time` unchanged |

**Response `data`:** updated `Lesson` object.

---

## 6. Additional Endpoints (from home screen)

### 6.1 `browse_teachers` — GET

Returns the list of teachers shown on the home screen.

**Params:** `token`

**Response `data`:** `Teacher[]`

```json
[
  {
    "userid": 12,
    "fullname": "محمد علي",
    "photourl": "https://...",
    "headline": "مدرس رياضيات",
    "subjects": [
      { "subject": "رياضيات" },
      { "subject": "فيزياء" }
    ]
  }
]
```

---

## 7. Error Handling

The mobile app displays the `error` field string directly to the user. Recommended Arabic error messages:

| Scenario | Message |
|---|---|
| Invalid token | `"انتهت صلاحية الجلسة، يرجى تسجيل الدخول مجدداً"` |
| Insufficient Flex | `"رصيدك غير كافٍ لحجز حصة"` |
| Lesson not found | `"الحصة غير موجودة"` |
| Action not allowed in current state | `"لا يمكن تنفيذ هذا الإجراء الآن"` |
| Requested time too soon | `"يجب حجز الحصة قبل موعدها بـ X دقيقة على الأقل"` |
| Missing required param | `"بيانات الطلب غير مكتملة"` |

---

## 8. Jitsi Integration Notes

The server is responsible for:

1. **Creating the Jitsi room** when the lesson enters `in_progress` (or when the teacher explicitly opens it early within `start_allowed_minutes`).
2. **Generating the JWT** for each user with appropriate Jitsi claims:
   - Teacher → moderator role
   - Student → participant role
3. **Setting `can_join: true`** and populating `jitsi_session` on every `get_lesson` / `get_my_lessons` response once the room exists.
4. **Setting `available: true`** only after the teacher has actually joined the room (or triggered room creation). Before that, set `available: false` and fill `available_info` with a human-readable message.
5. **Setting `available: false`** again if the teacher disconnects and the session is recoverable.

The mobile app will:
- Show a **"Join lesson"** button when `can_join && jitsi_session.available`.
- Show a **dimmed "Waiting for teacher" button** when `can_join && !jitsi_session.available` (uses `available_info` as the label).
- Show **nothing** (no join UI) when `can_join` is `false`.

---

## 9. Summary Checklist

### Packages
- [ ] `get_available_packages` — catalogue list
- [ ] `purchase_package` — create purchase + payment records
- [ ] `get_my_packages` — user's purchased packages with Flex balances
- [ ] `get_payment_history` — ledger of all payments

### Lessons
- [ ] `get_my_lessons` — filtered list with `role` + optional `status`
- [ ] `get_lesson` — single lesson by ID
- [ ] `get_lesson_settings` — admin-configurable timing windows
- [ ] `get_flex_history` — Flex ledger with running balance
- [ ] `request_lesson` — student books; validates timing + Flex availability
- [ ] `student_respond_lesson` — accept (reserves Flex) / reject / suggest
- [ ] `cancel_lesson_student` — from `confirmed`; early vs late Flex logic
- [ ] `cancel_lesson_request` — withdraw from `pending`/`waiting_teacher`
- [ ] `report_teacher_absent` — after deadline; returns Flex
- [ ] `request_time_update` — reschedule proposal from `confirmed`
- [ ] `respond_time_update` — accept/reject the other party's proposal

### Jitsi
- [ ] Room created when lesson goes `in_progress` (or early open by teacher)
- [ ] JWT generated per user (moderator for teacher, participant for student)
- [ ] `can_join` + `jitsi_session` populated on every lesson read while room is live
- [ ] `jitsi_session.available` toggled based on whether teacher has joined
- [ ] `available_info` set to a descriptive message when `available: false`

### Misc
- [ ] `browse_teachers` — home screen teacher carousel
- [ ] All responses follow `{status, data}` / `{status, error}` envelope
- [ ] All timestamps are Unix seconds (integers)
