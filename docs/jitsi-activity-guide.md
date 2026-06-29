# Jitsi Live Session Activity — Developer Guide

How to create a Jitsi activity in a Moodle course, what restriction options are available, and how external services interact with it.

---

## 1. Where the files live

```
src/mod/jitsi/
├── mod_form.php          # Activity creation/edit form (fields saved to DB)
├── view.php              # Main page — access control, Jitsi embed, recording UI
├── lib.php               # Moodle hooks (add/update/delete instance, cm_info_dynamic)
├── auto_record.php       # Called by teacher browser → starts Jibri recording via REST
├── auto_record_stop.php  # Called by teacher browser → stops Jibri recording via REST
├── ajax.php              # AJAX endpoint: end_session, end_room, get_session_recordings,
│                         #               check_recording_status
├── db/
│   ├── access.php        # Capabilities: mod/jitsi:view, :addinstance, :moderate
│   └── services.php      # External web service definitions
└── classes/external/
    └── get_session_info.php
```

---

## 2. Creating a Jitsi activity

### In the Moodle UI
1. Edit mode → **Add an activity or resource** → choose **Jitsi**.
2. Fill the form defined in `mod_form.php`:

| Field | Type | Description |
|---|---|---|
| `name` | text (required, max 255) | Session title shown in course and browser tab |
| `intro` | editor | Optional description shown above the video frame |
| `roompassword` | password | If set, all participants are auto-submitted this password; students never see a prompt |
| `lobby_enabled` | checkbox | If checked, a waiting room is enabled; teacher must approve each joiner |

3. Standard Moodle availability conditions (date range, group, grade) work normally — students are blocked if not yet available; teachers always see the room.

### Programmatically (web service)
Use `core_course_edit_module` or the standard Moodle REST API with `wsfunction=core_course_add_module`. The custom fields map to the `jitsi` DB table columns: `roompassword`, `lobby_enabled`.

---

## 3. Access restriction layers (in order)

### Layer 1 — Moodle availability conditions (`$cm->available`)
Set in the standard **Restrict access** section of the form (dates, group membership, grade conditions). If `$cm->available` is `false` for a student, `view.php` shows an "ended/unavailable" message and the recording list instead of the live room.

```
src/mod/jitsi/view.php  line 39
if (!$is_teacher && !$cm->available) { ... show message + recordings ... exit; }
```

### Layer 2 — Session whitelist (`academy_session_students`)
When the activity is linked to an `academy_live_sessions` row (via `jitsiid` FK), only students listed in `academy_session_students` can enter. Everyone else sees "not allowed".

```
src/mod/jitsi/view.php  lines 93–104
$allowed = $DB->record_exists('academy_session_students', ['sessionid'=>$session->id,'userid'=>$USER->id]);
```

### Layer 3 — Time window
For linked sessions, students may join only between `start_time − 30 min` and `start_time + duration`. Outside that window they see "too early" or "session ended".

```
src/mod/jitsi/view.php  lines 106–131
$open_from  = $session->start_time - 1800;
$open_until = $session->start_time + ($session->duration * 60);
```

### Layer 4 — Session status
If `academy_live_sessions.status = 'ended'`, the room is closed for everyone (including teachers). Refreshing does not allow re-entry.

### Layer 5 — Standalone ended flag
If no linked session exists, the teacher can end the room via the Jitsi "End meeting for all" button. This sets a Moodle config flag `mod_jitsi_ended_{cmid}`. Subsequent loads show the ended screen.

### Layer 6 — Dynamic visibility (`jitsi_cm_info_dynamic`)
Students not on the session whitelist do not see the activity in the course page at all (the link is hidden). Implemented as a Moodle callback in `lib.php`.

```
src/mod/jitsi/lib.php  lines 60–89
function jitsi_cm_info_dynamic(cm_info $cm) { ... $cm->set_user_visible(false); ... }
```

---

## 4. Security options saved on the activity

Both options are stored in the `jitsi` DB table and read in `view.php`.

| Option | DB column | Effect |
|---|---|---|
| Room password | `roompassword` | Jitsi sets a room password; Moodle auto-submits it on `passwordRequired` (students never see a prompt) |
| Lobby / waiting room | `lobby_enabled` | Teacher runs `api.executeCommand('toggleLobby', true)` on join; participants wait until the teacher approves them |

---

## 5. Roles and capabilities

| Capability | Who | What it unlocks |
|---|---|---|
| `mod/jitsi:view` | all roles incl. guest | Can open the activity page |
| `mod/jitsi:addinstance` | editingteacher, manager | Can create a new Jitsi activity in a course |
| `mod/jitsi:moderate` | editingteacher, manager | Full teacher toolbar, session control, recording buttons, stop/end meeting |

`has_capability('mod/jitsi:moderate', $context)` is the `$is_teacher` check used throughout `view.php`.

---

## 6. Toolbar buttons per role

Teachers and students get different Jitsi toolbar arrays (defined in `view.php`).

**Teacher toolbar** (full control):
```
microphone, camera, desktop, chat, invite,
raisehand, participants-pane, mute-everyone,
whiteboard, etherpad, select-background, noisesuppression,
tileview, filmstrip, videoquality, stats,
security, closedcaptions, shortcuts, fullscreen, hangup
```
> `recording` and `livestreaming` are intentionally **excluded** — they trigger Jitsi's built-in stop-recording dialog which sends an IQ to Jicofo and can destroy the conference. Recording is managed exclusively via Jibri REST API.

**Student toolbar** (personal controls only):
```
microphone, camera, desktop, chat, raisehand, whiteboard,
select-background, noisesuppression, tileview, videoquality,
fullscreen, hangup
```

---

## 7. Auto-recording (Jibri)

Recording is triggered automatically when the teacher joins, not by any toolbar button.

### Flow
```
Teacher opens view.php
  → videoConferenceJoined fires
    → browser POSTs to /mod/jitsi/auto_record.php
      → PHP calls Jibri REST  POST /jibri/api/v1.0/startService
        → Jibri joins the room as a hidden recorder participant
```

### Stop recording
```
Teacher clicks "Stop Recording" button  (or leaves / ends meeting)
  → browser POSTs to /mod/jitsi/auto_record_stop.php
    → PHP calls Jibri REST  POST /jibri/api/v1.0/stopService
```

### Relevant config keys (stored via `local_academysessions` plugin settings)
| Key | Default | Purpose |
|---|---|---|
| `jibri_api_url` | `http://academy_jibri:2223` | Jibri REST API base URL (internal Docker network) |
| `jibri_jitsi_url` | `http://meet.jitsi` | Base URL Jibri uses to open the Jitsi room |
| `jibri_recorder_password` | (hash) | XMPP password for `recorder@hidden.meet.jitsi` |
| `jitsi_host` | `localhost:8443` | Jitsi domain used for the External API JS and JWT `aud` |

### Jibri API endpoints used
```
POST /jibri/api/v1.0/startService   body: JSON with sessionId, callParams, callLoginParams
POST /jibri/api/v1.0/stopService    body: {}
```

---

## 8. Room name generation

Each activity gets a unique, stable room name derived from its IDs:

```php
// src/mod/jitsi/auto_record.php  line 22  (same formula in view.php)
$jitsi_room = 'academy_jitsi_' . $cm->id . '_' . substr(md5($jitsi->id . $cm->id), 0, 8);
```

This means the room name is always the same for a given activity and never clashes across activities.

---

## 9. JWT authentication

Every participant (including the Jibri recorder) gets a signed JWT. The JWT determines moderator status inside the Jitsi room.

```php
// Moderator JWT (teacher)
\local_academysessions\jitsi_jwt::generate($jitsi_room, $displayName, $email, true);

// Participant JWT (student)
\local_academysessions\jitsi_jwt::generate($jitsi_room, $displayName, $email, false);

// Recorder JWT (Jibri — non-moderator so it doesn't affect room ownership)
\local_academysessions\jitsi_jwt::generate($jitsi_room, 'Recorder', 'recorder@meet.jitsi', false);
```

JWT generation class: `src/local/academysessions/classes/jitsi_jwt.php`

---

## 10. Whiteboard tab

Each activity has a shared Excalidraw whiteboard, accessible via the **Whiteboard** tab next to the video tab.

Room name: `academy_wb_jitsi_{cmid}`  
URL: `{excalidraw_app}/#room={encoded_room_name}`

Config key: `local_academysessions → excalidraw_app` (default `http://localhost:9091`).  
Port 9090 is the Socket.IO relay only; the drawable UI is always on 9091.

---

## 11. Recordings

Recordings are stored in the `academy_session_recordings` table.

| Column | Purpose |
|---|---|
| `sessionid` | FK to `academy_live_sessions` (null for standalone activities) |
| `cmid` | FK to `course_modules` — always set |
| `status` | `pending`, `uploading`, `processing`, `ready` |
| `bunny_video_id` | Bunny Stream video ID once uploaded |
| `bunny_video_url` | Signed embed URL (refreshed automatically when expired) |
| `expires_at` | Unix timestamp when the signed URL expires |
| `title` | MinIO object key path (`recordings/academy-{cmid}-{ts}.mp4`) |

**When are recordings shown to students?**  
Only after `start_time + duration` has passed (the session window is closed). Teachers see them immediately.

**Polling**: while `status != 'ready'`, the page renders a spinner and JS polls `ajax.php?function=check_recording_status&rec_id={id}` every 5 seconds until the embed URL is available.

---

## 12. Linking an activity to a scheduled session

To restrict a Jitsi activity to a specific set of students and a time window, create a row in `academy_live_sessions` with `jitsiid = {jitsi instance id}` and populate `academy_session_students` with the allowed user IDs.

```sql
-- Link a session to the activity
INSERT INTO mdl_academy_live_sessions (jitsiid, status, start_time, duration, ...)
VALUES ({jitsi.id}, 'scheduled', {unix_timestamp}, {minutes}, ...);

-- Allow a student
INSERT INTO mdl_academy_session_students (sessionid, userid)
VALUES ({session.id}, {user.id});
```

If no `academy_live_sessions` row exists for the activity, it behaves as a **standalone open room** — any enrolled user who has `mod/jitsi:view` can join at any time (subject to Moodle availability conditions).

---

## 13. Quick reference — key DB tables

| Table | Purpose |
|---|---|
| `mdl_jitsi` | One row per activity instance (name, roompassword, lobby_enabled, …) |
| `mdl_academy_live_sessions` | Scheduled sessions linked to a Jitsi activity |
| `mdl_academy_session_students` | Per-session student whitelist |
| `mdl_academy_session_attendance` | Attendance log (first join timestamp per student) |
| `mdl_academy_session_recordings` | Recording metadata (status, Bunny IDs, signed URLs) |
