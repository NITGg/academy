# Assignment activity — in-app submission & feedback (student mobile app)

**Goal:** reproduce, in the mobile app, the student assignment screen that the web UI shows at
`/courses/[id]/activity/[cmid]` for an `assign` activity: read the brief, submit **online text
and/or files without leaving the app**, then see the submission status, grade, and the teacher's
feedback comment.

The web reference implementation:
- Server actions: `src/features/activity/actions.ts` — `getAssignmentData`, `submitAssignment`
- File upload helper: `src/lib/moodle-server.ts` — `uploadFilesToDraftArea`
- UI: `src/features/activity/components/AssignmentViewer.tsx`

---

## The key fact: this feature uses CORE Moodle web services, not `local_academy`

Unlike quizzes/lessons/certificates (which go through `local/academy/api.php`), assignments use the
**standard Moodle REST endpoint** and the built-in `mod_assign_*` functions. **No backend change is
needed** — every function below ships with Moodle core.

```
POST https://academy2026.nitg-eg.com/webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken=<STUDENT_TOKEN>
moodlewsrestformat=json
wsfunction=<function name>
<function params…>
```

The one exception is file upload, which uses Moodle's dedicated upload endpoint
(`/webservice/upload.php`), documented in step 3.

Five calls cover the whole screen:

| # | function | when | purpose |
|---|----------|------|---------|
| 1 | `mod_assign_get_assignments` | on open | brief, due date, max grade, which submission types are allowed, **the real instance id** |
| 2 | `mod_assign_get_submission_status` | on open | submission status, grade, teacher feedback, previously submitted content |
| 3 | `/webservice/upload.php` | on submit (if files) | push files to the user's draft area → returns a draft `itemid` |
| 4 | `mod_assign_save_submission` | on submit | save online text and/or the uploaded files |
| 5 | `mod_assign_submit_for_grading` | on submit (draft-based only) | finalize: move `draft` → `submitted` |

---

## Gotchas (read before you build — each of these cost a bug)

1. **You do NOT get the assignment instance id from the course-contents call.** `getalltopics.php`
   (and core `core_course_get_contents`) return the **cmid**, not the `assign` instance id. Every
   `mod_assign_*` function wants the **instance id** (`assignid`/`assignmentid`). Resolve it by calling
   `mod_assign_get_assignments` for the course and matching on `cmid` → use that row's `id`.
2. **`gradingstatus` lives on `lastattempt`, not on `lastattempt.submission`.** Reading it from the
   submission object gives you `undefined` forever ("not graded" even after grading).
3. **The teacher's feedback comment is in `feedback.plugins`** (the plugin whose `type == "comments"`,
   field `editorfields[0].text`) — it is NOT a top-level field.
4. **Online-text `itemid` can be `0`** for plain text with no embedded images. You do not need a draft
   area just to submit text.
5. **Only call `submit_for_grading` when the assignment uses drafts** (`submissiondrafts == 1`). When
   drafts are off, `save_submission` already sets status to `submitted`; calling `submit_for_grading`
   then errors.
6. **`plugindata` params are nested** — send them with literal bracket keys (see step 4).

---

## Step 1 — Load the assignment (metadata + config)

`mod_assign_get_assignments` returns every assignment in the course(s) you ask for. Match on `cmid`.

**Params**

| param | value |
|---|---|
| `courseids[0]` | the course id |
| `includenotenrolledcourses` | `1` (safe default) |

**Response (trimmed to what the screen needs)**

```jsonc
{
  "courses": [
    {
      "id": 62,
      "assignments": [
        {
          "id": 34,                 // ← the INSTANCE id — use this for all later calls
          "cmid": 2056,             // ← match on this
          "name": "assignment1",
          "intro": "<p>…brief HTML…</p>",
          "duedate": 1817971200,    // unix seconds, 0 = no due date
          "grade": 100,             // max grade (negative = scale, treat >0 as points)
          "submissiondrafts": 0,    // 1 = student must click "submit for grading"
          "requiresubmissionstatement": 0, // 1 = must accept the honesty statement
          "configs": [
            { "plugin": "onlinetext", "subtype": "assignsubmission", "name": "enabled", "value": "1" },
            { "plugin": "file",       "subtype": "assignsubmission", "name": "enabled", "value": "1" },
            { "plugin": "file",       "subtype": "assignsubmission", "name": "maxfilesubmissions", "value": "1" },
            { "plugin": "file",       "subtype": "assignsubmission", "name": "maxsubmissionsizebytes", "value": "5242880" },
            { "plugin": "file",       "subtype": "assignsubmission", "name": "filetypeslist", "value": ".pdf,.docx" }
          ]
        }
      ]
    }
  ]
}
```

**Read the submission config out of `configs`** (subtype `assignsubmission`):

| what | plugin / name | note |
|---|---|---|
| online text allowed | `onlinetext` / `enabled` == `"1"` | show the text box |
| files allowed | `file` / `enabled` == `"1"` | show the file picker |
| max files | `file` / `maxfilesubmissions` | default 1 |
| max bytes per file | `file` / `maxsubmissionsizebytes` | 0 = site default |
| accepted extensions | `file` / `filetypeslist` | `""` = any; comma-separated e.g. `.pdf,.docx` |

---

## Step 2 — Load the student's submission status, grade & feedback

`mod_assign_get_submission_status` — call with the **instance id** from step 1.

**Params**

| param | value |
|---|---|
| `assignid` | instance id (e.g. `34`) |

**Response (trimmed)**

```jsonc
{
  "lastattempt": {
    "submission": {
      "id": 91,
      "status": "submitted",        // new | draft | submitted | reopened
      "plugins": [
        {
          "type": "onlinetext",
          "editorfields": [ { "text": "<p>my answer…</p>" } ]   // prefill the editor
        },
        {
          "type": "file",
          "fileareas": [ { "files": [ { "filename": "cv.pdf" } ] } ] // show as already-submitted
        }
      ]
    },
    "gradingstatus": "graded",       // ← ON lastattempt, NOT on submission. graded | notgraded | … 
    "locked": false,
    "canedit": true,                 // may the student (re)submit right now?
    "cansubmit": true
  },
  "feedback": {
    "gradefordisplay": "97.00 / 100.00", // HTML-ish; render as-is
    "gradeddate": 1817000000,            // unix seconds; >0 ⇒ treat as graded
    "plugins": [
      {
        "type": "comments",              // ← teacher's feedback comment
        "editorfields": [ { "text": "<p>رائع جدا</p>" } ]
      }
    ]
  }
}
```

**Derive the UI state:**

- **Submission badge** ← `lastattempt.submission.status` (`new`→"not submitted", `draft`, `submitted`).
- **Grading badge** ← `lastattempt.gradingstatus`. **Fallback:** if it's missing/`notgraded` but
  `feedback.gradeddate > 0`, treat as **graded** (covers marking-workflow states like `released`).
- **Grade** ← `feedback.gradefordisplay` (already formatted, e.g. `"97.00 / 100.00"`).
- **Teacher feedback** ← `feedback.plugins[type=="comments"].editorfields[0].text` (HTML).
- **Prefill editor** ← `submission.plugins[type=="onlinetext"].editorfields[0].text` (strip tags for a
  plain text box; it round-trips fine as HTML on save).
- **Already-submitted files** ← `submission.plugins[type=="file"].fileareas[].files[].filename`
  (show names; the file URLs are token-bearing — proxy/stream them, don't expose the raw URL).
- **Editable?** show the submit form only when `canedit == true && locked == false`.

---

## Step 3 — Upload files to the draft area (only if the student attached files)

Files can't be embedded in the REST params — upload them first to the user's private **draft file
area**, which returns an `itemid` you then hand to `save_submission`.

```
POST https://academy2026.nitg-eg.com/webservice/upload.php
Content-Type: multipart/form-data

token=<STUDENT_TOKEN>
filearea=draft
itemid=0            // 0 on the FIRST file → server generates a draft itemid
file_1=<binary>
```

**Response** (array, one entry per uploaded file):

```jsonc
[ { "filearea": "draft", "itemid": 774451, "filename": "cv.pdf", "filepath": "/" } ]
```

**To put several files in the SAME draft area:** upload the first with `itemid=0`, read the returned
`itemid`, then upload the rest passing that same `itemid`. All files end up under one draft `itemid`,
which is what `save_submission` expects. (Web helper: `uploadFilesToDraftArea` in `moodle-server.ts`.)

On failure the endpoint returns an object with `error`/`errorcode` instead of an array — check for that.

---

## Step 4 — Save the submission

`mod_assign_save_submission` — writes online text and/or the uploaded files onto the (draft)
submission. `plugindata` is a **nested** structure; send it with literal bracket keys.

**Params**

| param | value | when |
|---|---|---|
| `assignmentid` | instance id | always |
| `plugindata[onlinetext_editor][text]` | the HTML/text | if online text |
| `plugindata[onlinetext_editor][format]` | `1` (HTML) | if online text |
| `plugindata[onlinetext_editor][itemid]` | `0` | if online text (0 = no embedded-image draft) |
| `plugindata[files_filemanager]` | draft `itemid` from step 3 | if files |

Form-encoded, that's literally:

```
assignmentid=34
plugindata[onlinetext_editor][text]=<p>my answer</p>
plugindata[onlinetext_editor][format]=1
plugindata[onlinetext_editor][itemid]=0
plugindata[files_filemanager]=774451
```

**Response:** an array of warnings. **Empty array = success.** A non-empty array means a submission
plugin rejected the data — surface `warnings[0].message`.

---

## Step 5 — Submit for grading (draft-based assignments only)

If `submissiondrafts == 1` (from step 1), the submission is still a **draft** after step 4 — the
student must finalize it:

`mod_assign_submit_for_grading`

| param | value |
|---|---|
| `assignmentid` | instance id |
| `acceptsubmissionstatement` | `1` if `requiresubmissionstatement`, else `0` |

If `requiresubmissionstatement == 1`, you **must** show the honesty statement and pass `1` only after
the student checks it — otherwise the call is rejected.

When `submissiondrafts == 0`, **skip this call** — `save_submission` already set the status to
`submitted`, and submitting-for-grading here throws.

After a successful submit, **re-run steps 1–2** (or just step 2) to refresh the badges/grade.

---

## Full submit flow (pseudocode)

```txt
onOpen:
  A = mod_assign_get_assignments(courseids[0]=courseId)
  assign = A.courses[*].assignments.find(cmid == thisCmid)
  instanceId = assign.id
  S = mod_assign_get_submission_status(assignid=instanceId)
  render brief/config from `assign`, status/grade/feedback from `S`

onSubmit(text, files, acceptedStatement):
  if !text && files.empty: error "add text or a file"
  if assign.requiresubmissionstatement && !acceptedStatement: error "accept the statement"

  params = { assignmentid: instanceId }
  if text:
    params["plugindata[onlinetext_editor][text]"]   = text
    params["plugindata[onlinetext_editor][format]"] = 1
    params["plugindata[onlinetext_editor][itemid]"] = 0
  if files:
    draftId = uploadToDraft(files)           // step 3
    params["plugindata[files_filemanager]"] = draftId

  warnings = mod_assign_save_submission(params)
  if warnings not empty: error warnings[0].message

  if assign.submissiondrafts == 1:
    mod_assign_submit_for_grading(assignmentid=instanceId,
                                  acceptsubmissionstatement = acceptedStatement ? 1 : 0)

  reload status (step 2)
```

---

## Client-side validation (mirror the server, fail fast)

1. Nothing to submit (no text AND no files) → block.
2. `requiresubmissionstatement` on but statement not accepted → block.
3. More files than `maxfilesubmissions` → block / trim.
4. A file bigger than `maxsubmissionsizebytes` (when > 0) → block.
5. File extension not in `filetypeslist` (when non-empty) → block.
6. Past `duedate` — Moodle may still accept as **late**; show a "past due" warning but let the student
   try (the server decides). Don't hard-block on the client.

The server is the final authority and re-checks all of this.

---

## Quick cURL walkthrough

```bash
BASE="https://academy2026.nitg-eg.com/webservice/rest/server.php"
UP="https://academy2026.nitg-eg.com/webservice/upload.php"
TOKEN=<student-token>
COURSE=62
CMID=2056

# 1) list assignments → find the instance id by cmid
curl -s "$BASE" --data-urlencode wstoken=$TOKEN --data-urlencode moodlewsrestformat=json \
  --data-urlencode wsfunction=mod_assign_get_assignments \
  --data-urlencode "courseids[0]=$COURSE" --data-urlencode includenotenrolledcourses=1 \
  | jq '.courses[].assignments[] | select(.cmid=='"$CMID"') | {id, submissiondrafts, requiresubmissionstatement, configs}'
ASSIGN=34   # ← from above

# 2) submission status / grade / feedback
curl -s "$BASE" --data-urlencode wstoken=$TOKEN --data-urlencode moodlewsrestformat=json \
  --data-urlencode wsfunction=mod_assign_get_submission_status --data-urlencode assignid=$ASSIGN \
  | jq '{status:.lastattempt.submission.status, grading:.lastattempt.gradingstatus,
         grade:.feedback.gradefordisplay,
         feedback:(.feedback.plugins[]?|select(.type=="comments").editorfields[0].text)}'

# 3) upload a file → get a draft itemid
ITEMID=$(curl -s "$UP" -F token=$TOKEN -F filearea=draft -F itemid=0 -F file_1=@cv.pdf | jq '.[0].itemid')

# 4) save the submission (online text + the uploaded file)
curl -s "$BASE" --data-urlencode wstoken=$TOKEN --data-urlencode moodlewsrestformat=json \
  --data-urlencode wsfunction=mod_assign_save_submission --data-urlencode assignmentid=$ASSIGN \
  --data-urlencode "plugindata[onlinetext_editor][text]=<p>My answer</p>" \
  --data-urlencode "plugindata[onlinetext_editor][format]=1" \
  --data-urlencode "plugindata[onlinetext_editor][itemid]=0" \
  --data-urlencode "plugindata[files_filemanager]=$ITEMID"
# → []  (empty array = success)

# 5) ONLY if submissiondrafts == 1
curl -s "$BASE" --data-urlencode wstoken=$TOKEN --data-urlencode moodlewsrestformat=json \
  --data-urlencode wsfunction=mod_assign_submit_for_grading --data-urlencode assignmentid=$ASSIGN \
  --data-urlencode acceptsubmissionstatement=0
```

---

## Notes for parity with the web app

- The web screen has **no "open in Moodle" escape hatch** — submission is fully in-app. Match that.
- Token-bearing file URLs (submitted files, feedback files, images inside `intro`) must be
  proxied/streamed, never handed to the client raw — same rule as the rest of the app.
- All required WS functions are enabled on the `moodle_mobile_app` service by default; if a call
  returns `accessexception`/`invalidtoken`, the token or the service config is the problem, not the
  request shape.
