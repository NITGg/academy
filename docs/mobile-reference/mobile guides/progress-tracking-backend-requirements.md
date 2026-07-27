# Course & Activity Progress — What the App Needs From the Backend

_Last updated: 2026-07-18_

The app already has the full UI for progress — a per-course bar on the **My Courses**
cards and a per-course summary header inside **Course Details**. They are currently
showing **0% / "0 of 0"** (or hiding entirely) not because of an app bug, but because
the Moodle backend is not yet returning completion data. This document lists exactly
what each screen consumes and what has to be configured/returned on the server for the
numbers to become real.

> TL;DR — Progress in Moodle is derived entirely from **Completion Tracking**. If
> completion is not enabled at the site, enabled per course, and configured per
> activity, every progress endpoint returns `0` or `null`. Nothing on the app side can
> synthesize progress; the data must come from Moodle.

---

## 1. Where progress is shown in the app

| Screen | Widget | Endpoint it depends on | What it reads |
|--------|--------|------------------------|----------------|
| **My Courses** (course cards) | `_CourseProgressBar` in [`courses_list_item.dart`](../lib/features/home/presentation/widgets/courses_list_item.dart) | `core_enrol_get_users_courses` | `progress` (0–100, or `null`) and `completed` (bool) per course |
| **Course Details** (header above activities) | `_CourseProgressHeader` in [`details_screen.dart`](../lib/features/home/presentation/pages/course_details/details_screen.dart) | `core_completion_get_activities_completion_status` | per-activity `state` + `tracking`, aggregated to "N of M activities completed" |
| **Course Details** (per-activity check icon) | activity `ListTile` trailing icon | same as above | each module's `state` (0/1/2/3) |

### 1a. My Courses card — `core_enrol_get_users_courses`
File: [`couses_remote_data_source.dart`](../lib/features/home/data/datasources/couses_remote_data_source.dart) → `getEnrolledCourses`

The card reads `course['progress']`:
- `progress == null` → **the bar is hidden** (course looks like it has no tracking).
- `progress == 0` (a real number) → bar shows **0% Complete**.
- `completed == true` or `progress >= 100` → shows the "completed" state.

### 1b. Course Details header — `core_completion_get_activities_completion_status`
File: [`couses_remote_data_source.dart`](../lib/features/home/data/datasources/couses_remote_data_source.dart) → `getActivitiesCompletion`

The header/icon logic:
- Only modules with `tracking != 0` are counted (`tracking == 0` = "not tracked" → skipped).
- If **no** module is tracked → `trackedTotal == 0` → **the whole progress header is hidden.**
- Completion `state` values the app already understands:
  - `0` = incomplete
  - `1` = complete
  - `2` = complete / pass
  - `3` = complete / fail
- "Completed" count = number of activities in state `1` or `2`.

So "showing all 0" means the endpoint is returning either an empty `statuses` array, or
every status with `tracking == 0` / `state == 0`.

---

## 2. Why it is 0 right now — the Moodle completion chain

For any of the above to be non-zero, **all** of these must be true on the server. This is
the same chain the official Moodle app checks (`CoreCourseCompletion.isCompletionEnabledInCourse`
gates on `enablecompletion`, and the enrolled-courses call returns `progress` only when
tracking is on):

1. **Site level** — Admin → Advanced features → **Enable completion tracking**
   (`enablecompletion = 1`). If this is off, `progress` is `null` for every course and the
   completion-status endpoint returns nothing useful.

2. **Course level** — each course's settings → Completion tracking → **Enable completion
   tracking = Yes**. Courses without this return `progress = null`.

3. **Activity level** — each activity (quiz, resource, assignment, …) must have a
   **Completion condition** configured, e.g.:
   - "Students can manually mark as complete", **or**
   - "Show as complete when conditions are met" (viewed / grade ≥ X / attempt submitted …).

   Activities with completion set to "None/Do not indicate" return `tracking == 0` and are
   invisible to the progress count. **This is the most common reason the details header is
   empty even when the course has completion enabled** — the course flag is on but no
   individual activity has a condition.

4. **Student activity** — the numbers only move once the enrolled student actually meets a
   condition (submits the quiz, views the resource, etc.). A freshly enrolled student on a
   fully-configured course legitimately shows 0%.

---

## 3. Checklist for the backend / Moodle admin

To make progress real, please confirm on the server (ideally on one pilot course first):

- [ ] `enablecompletion` is **on** site-wide (Admin → Advanced features).
- [ ] The course has **"Enable completion tracking = Yes"** in course settings.
- [ ] **Each** activity in the course has a completion **condition** set (not "None").
- [ ] Confirm the **web service user's token** (the student token the app logs in with)
      has the `report/completion:view` capability / can call
      `core_completion_get_activities_completion_status` for their own `userid` — otherwise
      the app silently falls back to an empty map.
- [ ] Both functions are **exposed in the external service** the mobile token uses:
  - `core_enrol_get_users_courses` (already used — just needs `progress`/`completed` populated)
  - `core_completion_get_activities_completion_status`
  - _(optional, see §4)_ `core_completion_get_course_completion_status`

### How to verify quickly (raw calls)

Course-card progress (per-course %):
```
GET /webservice/rest/server.php
  ?wsfunction=core_enrol_get_users_courses
  &wstoken=<STUDENT_TOKEN>
  &moodlewsrestformat=json
  &userid=<USER_ID>
```
Expected on a tracked course: each course object contains `"progress": <0-100>` and
`"completed": true|false`. If `progress` is `null` → completion not enabled for that course.

Course-details activity completion:
```
GET /webservice/rest/server.php
  ?wsfunction=core_completion_get_activities_completion_status
  &wstoken=<STUDENT_TOKEN>
  &moodlewsrestformat=json
  &courseid=<COURSE_ID>
  &userid=<USER_ID>
```
Expected: a `statuses` array where tracked activities have `"tracking": 1|2` and a real
`"state"`. If every entry has `"tracking": 0` → no activity-level conditions are configured.

---

## 4. Optional: overall course-completion criteria

`core_completion_get_activities_completion_status` gives **activity-by-activity** progress,
which is what both current widgets use. If you later want a single authoritative
"course completed" flag driven by the course's *completion criteria* (e.g. "complete when
all activities done" or "complete on a passing final grade"), that comes from a different
endpoint:

```
core_completion_get_course_completion_status(courseid, userid)
  → { completionstatus: { completed: bool, aggregation, completions[] } }
```

Not required for the current UI, but worth enabling if the course uses custom completion
criteria rather than a simple count of activities.

---

## 5. Summary — nothing is blocked on the app

- The app **already renders** progress the moment the endpoints return non-zero data; no
  app changes are required to "turn it on."
- The bars/headers are **intentionally hidden** when data is absent, so enabling completion
  on the backend will make them appear automatically.
- **Action item is entirely server-side:** enable completion tracking (site → course →
  activity) and confirm the student token can read the two completion endpoints above.
