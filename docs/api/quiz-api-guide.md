# Quiz API Guide

Replaces the Moodle quiz web view with REST endpoints that return structured JSON —
so the mobile app can render a fully native quiz UI.

> **Postman:** import `docs/api/Academy_Quiz.postman_collection.json`.
> Run **Login as Admin** or **Login as Student** first, then use any request.

---

## Supported question types

| Moodle qtype | Description | Supported |
|---|---|---|
| `multichoice` (single=1) | Single-answer MCQ | ✅ |
| `multichoice` (single=0) | Multi-answer MCQ | ✅ |
| `truefalse` | True / False | ✅ |
| `shortanswer`, `numerical`, etc. | Other types | `supported: false` — returned with no options |

Unsupported questions appear in the response with `"supported": false` and no `options` array so the app can render a fallback (e.g. a web view for that one question).

---

## Correct answers visibility

| Token | `get_quiz` | `get_quiz_attempt` |
|---|---|---|
| Admin (`manageplatform`) | Each option includes `"correct": true/false` | Each question includes `correct_answers[]` |
| Student / Teacher | `correct` field is absent | `correct_answers` is absent |

---

## Images in questions & answers

Every question and every answer option is returned as two clean fields:

| Field | Type | Meaning |
|---|---|---|
| `text` | string | The human-readable text, **all HTML tags stripped** (may be `""`). |
| `images` | string[] | The URL of every image, in order (may be `[]`). |

No HTML is returned anymore — the wrapping `<p>`, `<br>`, `style`, `dir` markup and
the `<img>` tags are parsed out server-side so the app never has to touch HTML.

```json
{
  "id": 1,
  "text": "",
  "images": ["https://site/local/academy/qfile.php?questionid=717&area=answer&itemid=1&file=diagram.png&token=STUDENT_TOKEN"]
}
```

An option can have both text and images (e.g. `"text": "1+1"`, plus an image), or just
one of them. Render `text` (if non-empty) and then each URL in `images`.

**Image URLs point at `local/academy/qfile.php`** — a dedicated, token-authorised image
server — with the token already included, so the app (or a browser) can load them
directly. No `?token=` handling needed on the client.

> **Why not `webservice/pluginfile.php`?** Question-bank images can't be served that way
> with a plain student token — Moodle's `question_pluginfile` only authorises them inside
> a quiz attempt/preview context, so it fails with
> `{"errorcode":"requireloginerror"}` ("Course or activity not accessible").
> `qfile.php` authorises by **token + course enrolment** instead, so it works even before
> an attempt is started. Access is denied (`403`) if the user isn't enrolled in a course
> whose quiz uses that question (admins with `manageplatform` can load any).

---

## 1. `get_quizzes` — GET

List all quizzes on the platform, optionally filtered by course.

```
GET /local/academy/api.php?function=get_quizzes&token=TOKEN
                          [&courseid=2]
```

| Param | Type | Description |
|---|---|---|
| `courseid` | int | Optional. Filter to quizzes in this course. |

**Response:**
```json
{ "status": "success", "data": [
  {
    "quizid": 5,
    "cmid": 12,
    "courseid": 2,
    "name": "Chapter 1 Test",
    "intro": "Test your understanding of Chapter 1.",
    "timelimit": 1800,
    "attempts_allowed": 3
  }
]}
```

> `timelimit` is in **seconds** (0 = no limit). `attempts_allowed` 0 = unlimited.

---

## 2. `get_quiz` — GET

Get a single quiz with all questions and answer options structured as JSON.

```
GET /local/academy/api.php?function=get_quiz&token=TOKEN&cmid=12
```

| Param | Type | Description |
|---|---|---|
| `cmid` | int | **Required.** Course module id (from `get_quizzes`). |

**Response (student token):**
```json
{ "status": "success", "data": {
  "quizid": 5,
  "cmid": 12,
  "courseid": 2,
  "name": "Chapter 1 Test",
  "intro": "",
  "timelimit": 1800,
  "attempts_allowed": 3,
  "questions": [
    {
      "slot": 1,
      "questionid": 101,
      "type": "multichoice",
      "text": "What is the capital of Egypt?",
      "images": [],
      "defaultmark": 1.0,
      "supported": true,
      "single": true,
      "options": [
        { "id": 1, "text": "Cairo",      "images": [] },
        { "id": 2, "text": "Alexandria", "images": [] },
        { "id": 3, "text": "Luxor",      "images": [] }
      ]
    },
    {
      "slot": 2,
      "questionid": 102,
      "type": "truefalse",
      "text": "The Earth orbits the Sun.",
      "images": [],
      "defaultmark": 1.0,
      "supported": true,
      "options": [
        { "id": 10, "text": "True",  "images": [] },
        { "id": 11, "text": "False", "images": [] }
      ]
    },
    {
      "slot": 3,
      "questionid": 103,
      "type": "multichoice",
      "text": "",
      "images": ["https://site/webservice/pluginfile.php/355/question/questiontext/103/diagram.png"],
      "defaultmark": 1.0,
      "supported": true,
      "single": false,
      "options": [
        { "id": 20, "text": "Red",    "images": [] },
        { "id": 21, "text": "Green",  "images": [] },
        { "id": 22, "text": "Blue",   "images": [] },
        { "id": 23, "text": "", "images": ["https://site/webservice/pluginfile.php/355/question/answer/23/opt.png"] }
      ]
    }
  ]
}}
```

**Response (admin token)** — same but each option includes `"correct"`:
```json
{ "id": 1, "text": "Cairo",      "images": [], "correct": true  },
{ "id": 2, "text": "Alexandria", "images": [], "correct": false }
```

> `single: true` = pick one answer. `single: false` = pick multiple answers.

---

## 3. `start_quiz_attempt` — POST

Start a new attempt. Must be called before submitting answers.

```
POST /local/academy/api.php
Content-Type: application/x-www-form-urlencoded

function=start_quiz_attempt&token=STUDENT_TOKEN&quizid=5
```

| Param | Type | Description |
|---|---|---|
| `quizid` | int | **Required.** |

**Response:**
```json
{ "status": "success", "data": {
  "attemptid": 88,
  "quizid": 5,
  "attempt_number": 1,
  "timestart": 1720000000,
  "timelimit": 1800,
  "state": "inprogress"
}}
```

**Errors:**
- `attemptsexhausted` — student has used all allowed attempts.

---

## 4. `submit_quiz_attempt` — POST

Submit all answers and finish the attempt. Cannot be called twice on the same attempt.

```
POST /local/academy/api.php
Content-Type: application/x-www-form-urlencoded

function=submit_quiz_attempt&token=STUDENT_TOKEN
&attemptid=88
&answers=[{"questionid":101,"answer":1},{"questionid":102,"answer":10},{"questionid":103,"answer":[20,22]}]
```

| Param | Type | Description |
|---|---|---|
| `attemptid` | int | **Required.** From `start_quiz_attempt`. |
| `answers` | JSON array | **Required.** See format below. |

### `answers` format

| Question type | Format | Example |
|---|---|---|
| Single MCQ (`single: true`) | `{ "questionid": 101, "answer": OPTION_ID }` | `{"questionid":101,"answer":1}` |
| True / False | `{ "questionid": 102, "answer": OPTION_ID }` | `{"questionid":102,"answer":10}` |
| Multi MCQ (`single: false`) | `{ "questionid": 103, "answer": [ID, ID] }` | `{"questionid":103,"answer":[20,22]}` |

`OPTION_ID` is the `id` field from the option object in `get_quiz`.

**Response:**
```json
{ "status": "success", "data": {
  "attemptid": 88,
  "state": "finished",
  "score": 2.0,
  "max_score": 3.0,
  "percent": 66.7,
  "results": [
    { "questionid": 101, "type": "multichoice", "mark": 1.0, "max_mark": 1.0, "correct": true  },
    { "questionid": 102, "type": "truefalse",   "mark": 1.0, "max_mark": 1.0, "correct": true  },
    { "questionid": 103, "type": "multichoice", "mark": 0.0, "max_mark": 1.0, "correct": false }
  ]
}}
```

**Errors:**
- `attemptalreadyclosed` — attempt was already submitted.
- `notyourattempt` — attemptid belongs to a different user.

> `submit_quiz_attempt` also grades any answers previously stored with
> `save_quiz_answer` (below); answers passed in this call take priority.

---

## 4.1 `save_quiz_answer` — POST

Save the answer to **one** question **without finishing** the attempt. Use this to
persist answers as the student moves through the quiz question-by-question; the
attempt stays `inprogress`. Calling it again for the same question overwrites the
previous answer.

```
POST /local/academy/api.php
Content-Type: application/x-www-form-urlencoded

function=save_quiz_answer&token=STUDENT_TOKEN
&attemptid=88
&questionid=101
&answer=1
```

| Param | Type | Description |
|---|---|---|
| `attemptid` | int | **Required.** From `start_quiz_attempt`. |
| `questionid` | int | **Required.** The question being answered. |
| `answer` | int or JSON array | **Required.** Option id (single MCQ / true-false) or `[id,id]` (multi MCQ). |

**Response:**
```json
{ "status": "success", "data": {
  "attemptid": 88,
  "questionid": 101,
  "saved": true,
  "state": "inprogress"
}}
```

**Errors:**
- `attemptalreadyclosed` — the attempt has already been finished.
- `notyourattempt` — attemptid belongs to a different user.
- `invalidquestionid` — the question is not part of this attempt's quiz.

---

## 4.2 `finish_quiz_attempt` — POST

Submit **all** questions: grade every answer saved with `save_quiz_answer` and
close the attempt. This is the "submit all" endpoint for the incremental flow — no
`answers` body is needed.

```
POST /local/academy/api.php
Content-Type: application/x-www-form-urlencoded

function=finish_quiz_attempt&token=STUDENT_TOKEN&attemptid=88
```

| Param | Type | Description |
|---|---|---|
| `attemptid` | int | **Required.** |

**Response:** identical to `submit_quiz_attempt` (score, max_score, percent, results[]).

**Errors:**
- `attemptalreadyclosed` — the attempt has already been finished.
- `notyourattempt` — attemptid belongs to a different user.

---

## 5. `get_quiz_attempt` — GET

Review a finished attempt.

```
GET /local/academy/api.php?function=get_quiz_attempt&token=TOKEN&attemptid=88
```

| Param | Type | Description |
|---|---|---|
| `attemptid` | int | **Required.** |

**Response (student):**
```json
{ "status": "success", "data": {
  "attemptid": 88,
  "quizid": 5,
  "quiz_name": "Chapter 1 Test",
  "attempt_number": 1,
  "state": "finished",
  "timestart": 1720000000,
  "timefinish": 1720001200,
  "score": 2.0,
  "max_score": 3.0,
  "percent": 66.7,
  "questions": [
    { "slot": 1, "questionid": 101, "type": "multichoice", "text": "...", "images": [], "max_mark": 1.0 }
  ]
}}
```

**Admin token** adds `correct_answers[]` per question:
```json
"correct_answers": [
  { "id": 1, "text": "Cairo",      "images": [], "correct": true  },
  { "id": 2, "text": "Alexandria", "images": [], "correct": false }
]
```

---

## 6. `get_my_quiz_attempts` — GET

List all attempts the current user has made on a quiz.

```
GET /local/academy/api.php?function=get_my_quiz_attempts&token=STUDENT_TOKEN&quizid=5
```

| Param | Type | Description |
|---|---|---|
| `quizid` | int | **Required.** |

**Response:**
```json
{ "status": "success", "data": [
  {
    "attemptid": 88,
    "attempt_number": 1,
    "state": "finished",
    "score": 2.0,
    "max_score": 3.0,
    "percent": 66.7,
    "timestart": 1720000000,
    "timefinish": 1720001200
  },
  {
    "attemptid": 91,
    "attempt_number": 2,
    "state": "inprogress",
    "score": null,
    "max_score": 3.0,
    "percent": null,
    "timestart": 1720005000,
    "timefinish": 0
  }
]}
```

---

## Typical mobile flow

**A — one-shot submit** (collect all answers, send once):
```
get_quizzes          →  show quiz list
  ↓ user taps a quiz
get_quiz(cmid)       →  render questions natively (no web view)
start_quiz_attempt   →  get attemptid
  ↓ user answers all questions
submit_quiz_attempt  →  send all answers, get score
```

**B — incremental submit** (save each answer, finish at the end):
```
start_quiz_attempt   →  get attemptid
  ↓ for each question the student answers
save_quiz_answer     →  persist that answer, attempt stays open
  ↓ user taps "Submit"
finish_quiz_attempt  →  grade all saved answers, get score
```

Then either flow:
```
get_quiz_attempt     →  detailed review (optional)
get_my_quiz_attempts →  show attempt history
```

---

## Postman quick start

1. Import `docs/api/Academy_Quiz.postman_collection.json`
2. Set `{{base_url}}` and `{{cmid}}` in the collection variables
3. Run **Login as Admin** and **Login as Student** (auto-saves tokens)
4. Run **Get All Quizzes** — copy a `cmid` into `{{cmid}}`
5. Run **Get Quiz (Student)** — saves `{{quizid}}` automatically
6. Run **Start Attempt** — saves `{{attemptid}}` automatically
7. Edit the `answers` in **Submit Answers** with real option ids from step 5
8. Run **Submit Answers** then **Review My Attempt**
