# Meeting room — "wait for teacher" (student mobile app)

**Goal:** the student must NOT enter the lesson's Jitsi room until the teacher is actually in the call.

Read together with the **Meeting room & joining** section of
[lessons-flex-guide.md](lessons-flex-guide.md).

---

## How it works (no new endpoints)

- The **teacher** joins through the Moodle room page (`join_url` → `view.php`). That page records when
  the teacher enters/leaves the call automatically — the student app does nothing for this.
- The **student mobile app** joins the room natively from the `jitsi_session` payload and simply
  reads whether the teacher is present.

There are **no new APIs** for this feature. The student app only needs to honor one existing field:
`jitsi_session.available`.

---

## What the student app must do

1. **Do not use `is_teacher` to detect presence.** `jitsi_session.is_teacher` means *"is the caller the
   teacher"* — it is always `false` for a student and never changes.
2. Show **"Join Lesson"** whenever the lesson's `can_join` is `true` (lesson is `in_progress`).
3. On tap, look at **`jitsi_session.available`**:
   - `true` → connect with `server_url` + `room` + `jwt`.
   - `false` → show `jitsi_session.available_info` ("Waiting for the teacher…"), keep polling
     `get_lesson` / `get_my_lessons` (~every 5s), and connect as soon as it becomes `true`.

### Fields (inside `jitsi_session`)

| field | meaning | app action |
|-------|---------|------------|
| `available` | `false` until the teacher is in the call; `true` once present | gate the connect on this |
| `available_info` | "Waiting for the teacher…" text while `available` is `false` | show it while waiting |
| `is_teacher` | role flag (is the caller the teacher) | NOT a presence flag — ignore for waiting |
| `server_url`, `room`, `jwt` | native Jitsi SDK credentials | use to connect once `available` |

`can_join` only means a room exists; decide whether to actually connect using `available`.

---

## Quick test (curl)

```bash
BASE="https://academy2026.nitg-eg.com"

# Student BEFORE the teacher joins → available:false
curl "$BASE/local/academy/api.php?function=get_lesson&token=$STUDENT_TOKEN&lessonid=$LID" \
  | jq '.data.jitsi_session | {is_teacher, available, available_info}'

# (teacher opens the room via the teacher page / join_url)

# Student AFTER the teacher joins → available:true
curl "$BASE/local/academy/api.php?function=get_lesson&token=$STUDENT_TOKEN&lessonid=$LID" \
  | jq '.data.jitsi_session | {available, available_info}'
```

> If the first call already returns `available:true` with no teacher present, the backend build is out of
> date — make sure the `local_academy` + `local_academysessions` plugins are deployed and the Moodle
> upgrade has run (adds the `teacher_joined_at` column).
