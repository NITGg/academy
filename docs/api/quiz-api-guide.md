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
      "defaultmark": 1.0,
      "supported": true,
      "single": true,
      "options": [
        { "id": 1, "text": "Cairo" },
        { "id": 2, "text": "Alexandria" },
        { "id": 3, "text": "Luxor" }
      ]
    },
    {
      "slot": 2,
      "questionid": 102,
      "type": "truefalse",
      "text": "The Earth orbits the Sun.",
      "defaultmark": 1.0,
      "supported": true,
      "options": [
        { "id": 10, "text": "True" },
        { "id": 11, "text": "False" }
      ]
    },
    {
      "slot": 3,
      "questionid": 103,
      "type": "multichoice",
      "text": "Which are primary colours? (select all)",
      "defaultmark": 1.0,
      "supported": true,
      "single": false,
      "options": [
        { "id": 20, "text": "Red" },
        { "id": 21, "text": "Green" },
        { "id": 22, "text": "Blue" },
        { "id": 23, "text": "Yellow" }
      ]
    }
  ]
}}
```

**Response (admin token)** — same but each option includes `"correct"`:
```json
{ "id": 1, "text": "Cairo",      "correct": true  },
{ "id": 2, "text": "Alexandria", "correct": false }
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
    { "slot": 1, "questionid": 101, "type": "multichoice", "text": "...", "max_mark": 1.0 }
  ]
}}
```

**Admin token** adds `correct_answers[]` per question:
```json
"correct_answers": [
  { "id": 1, "text": "Cairo",      "correct": true  },
  { "id": 2, "text": "Alexandria", "correct": false }
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

```
get_quizzes          →  show quiz list
  ↓ user taps a quiz
get_quiz(cmid)       →  render questions natively (no web view)
  ↓ user answers all questions
start_quiz_attempt   →  get attemptid
submit_quiz_attempt  →  send answers, get score
  ↓ show results screen
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
