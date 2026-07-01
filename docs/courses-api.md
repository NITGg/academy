# Academy — Courses API Reference

Base URL: `https://your-domain.com`  
Endpoint: `POST /webservice/rest/server.php`  
Content-Type: `application/x-www-form-urlencoded`

Every request requires:
```
wstoken              = <user token>
moodlewsrestformat   = json
wsfunction           = <function name>
```

---

## Auth & Site

### Get Token
```
POST /login/token.php
```
| Field | Value |
|---|---|
| username | user's username |
| password | user's password |
| service | `moodle_mobile_app` |

```json
{ "token": "abc123..." }
```

---

### Get Site Info
`wsfunction = core_webservice_get_site_info`

No extra parameters needed.

```json
{
  "sitename": "Academy of Excellence",
  "siteurl": "https://your-domain.com",
  "userid": 5,
  "username": "student1",
  "fullname": "Ahmed Ibrahim",
  "userpictureurl": "https://...",
  "lang": "ar",
  "release": "3.11.8+"
}
```
> Call this immediately after login to get `userid` — required for several other calls.

---

## Course Discovery

### Search / Browse All Courses
`wsfunction = core_course_search_courses`

| Field | Type | Required | Notes |
|---|---|---|---|
| criterianame | string | yes | `search` \| `categoryid` \| `levelid` \| `modulelist` |
| criteriavalue | string | yes | Search term, or empty string `""` to return all |
| page | int | no | 0-based page number. Default: `0` |
| perpage | int | no | Results per page. Default: `20` |

```json
{
  "total": 42,
  "courses": [
    {
      "id": 59,
      "fullname": "test 26",
      "shortname": "26 course",
      "summary": "Course description here",
      "summaryformat": 1,
      "categoryid": 3,
      "categoryname": "Miscellaneous",
      "visible": 1,
      "courseimage": "https://your-domain.com/...",
      "enrolledusercount": 2,
      "lang": ""
    }
  ]
}
```

---

### Browse by Category
`wsfunction = core_course_search_courses`

| Field | Value |
|---|---|
| criterianame | `categoryid` |
| criteriavalue | `3` (category ID) |

---

### Get Courses by IDs
`wsfunction = core_course_get_courses`

| Field | Type | Notes |
|---|---|---|
| options[ids][0] | int | First course ID |
| options[ids][1] | int | Second course ID (repeat as needed) |

Leave `options` empty to get **all courses** (admin only).

```json
[
  {
    "id": 59,
    "shortname": "26 course",
    "fullname": "test 26",
    "summary": "...",
    "categoryid": 3,
    "startdate": 1609459200,
    "enddate": 0,
    "visible": 1,
    "format": "topics",
    "showgrades": true,
    "lang": "",
    "courseimage": "https://..."
  }
]
```

---

### Get Courses by Field
`wsfunction = core_course_get_courses_by_field`

| Field | Type | Notes |
|---|---|---|
| field | string | `id` \| `ids` \| `shortname` \| `idnumber` \| `category` |
| value | string | The value to match |

Examples:
```
field = category   value = 3      → all courses in category 3
field = id         value = 59     → single course
field = ids        value = 59,60  → multiple courses
```

---

### Get All Categories
`wsfunction = core_course_get_categories`

No extra parameters needed for top-level. To get children:

| Field | Type | Notes |
|---|---|---|
| criteria[0][key] | string | `parent` |
| criteria[0][value] | int | Parent category ID (0 = top level) |
| addsubcategories | int | `1` to include nested categories |

```json
[
  {
    "id": 1,
    "name": "Miscellaneous",
    "parent": 0,
    "coursecount": 5,
    "depth": 1,
    "path": "/1"
  }
]
```

---

## Enrolled Courses

### Get My Enrolled Courses
`wsfunction = core_enrol_get_users_courses`

| Field | Type | Required |
|---|---|---|
| userid | int | yes — use `userid` from site info |

```json
[
  {
    "id": 59,
    "shortname": "26 course",
    "fullname": "test 26",
    "enrolledusercount": 2,
    "visible": 1,
    "summary": "",
    "summaryformat": 1,
    "format": "topics",
    "showgrades": true,
    "lang": "",
    "courseimage": "https://..."
  }
]
```

---

### Get Enrolled Users in a Course
`wsfunction = core_enrol_get_enrolled_users`

| Field | Type | Required |
|---|---|---|
| courseid | int | yes |

```json
[
  {
    "id": 5,
    "username": "student1",
    "fullname": "Ahmed Ibrahim",
    "email": "ahmed@example.com",
    "roles": [{ "roleid": 5, "name": "Student" }],
    "enrolledcourses": [{ "id": 59 }]
  }
]
```

---

## Course Content

### Get Course Sections & Activities
`wsfunction = core_course_get_contents`

| Field | Type | Required | Notes |
|---|---|---|---|
| courseid | int | yes | |
| options[0][name] | string | no | `excludemodules` — set value to `1` to skip activities |
| options[0][name] | string | no | `excludecontents` — set value to `1` to skip file content |

```json
[
  {
    "id": 12,
    "name": "Section 1 — Introduction",
    "visible": 1,
    "summary": "",
    "summaryformat": 1,
    "section": 1,
    "modules": [
      {
        "id": 34,
        "name": "Welcome Video",
        "modname": "url",
        "modicon": "https://...",
        "visible": 1,
        "url": "https://your-domain.com/mod/url/view.php?id=34",
        "completiondata": {
          "state": 0,
          "timecompleted": 0
        }
      },
      {
        "id": 35,
        "name": "Quiz 1",
        "modname": "quiz",
        "modicon": "https://...",
        "visible": 1,
        "url": "https://your-domain.com/mod/quiz/view.php?id=35"
      }
    ]
  }
]
```

---

### Get a Single Module
`wsfunction = core_course_get_module`

| Field | Type | Required |
|---|---|---|
| id | int | yes — module (cm) ID |

```json
{
  "id": 34,
  "course": 59,
  "name": "Welcome Video",
  "modname": "url",
  "visible": 1,
  "url": "https://your-domain.com/mod/url/view.php?id=34"
}
```

---

## Completion

### Get Course Completion Status
`wsfunction = core_completion_get_course_completion_status`

| Field | Type | Required |
|---|---|---|
| courseid | int | yes |
| userid | int | yes |

```json
{
  "completionstatus": {
    "completed": false,
    "aggregation": 1,
    "completions": [
      {
        "type": 4,
        "title": "Manual self completion",
        "status": "No",
        "complete": false,
        "timecompleted": 0
      }
    ]
  }
}
```

---

### Get Activity Completion Status
`wsfunction = core_completion_get_activities_completion_status`

| Field | Type | Required |
|---|---|---|
| courseid | int | yes |
| userid | int | yes |

```json
{
  "statuses": [
    {
      "cmid": 34,
      "modname": "url",
      "instance": 10,
      "state": 1,
      "timecompleted": 1751218800,
      "overrideby": null,
      "valueused": true,
      "hascompletion": true,
      "isautomatic": false,
      "istrackeduser": true,
      "uservisible": true,
      "details": []
    }
  ]
}
```

---

### Mark Activity Complete (Manual)
`wsfunction = core_completion_update_activity_completion_status_manually`

| Field | Type | Required | Notes |
|---|---|---|---|
| cmid | int | yes | Course module ID |
| completed | int | yes | `1` = complete, `0` = incomplete |

```json
{ "status": true }
```

---

## Grades

### Get Grade Items for a Course
`wsfunction = gradereport_user_get_grade_items`

| Field | Type | Required |
|---|---|---|
| courseid | int | yes |
| userid | int | yes |

```json
{
  "usergrades": [
    {
      "courseid": 59,
      "userid": 5,
      "userfullname": "Ahmed Ibrahim",
      "gradeitems": [
        {
          "id": 12,
          "itemname": "Quiz 1",
          "itemtype": "mod",
          "itemmodule": "quiz",
          "graderaw": 85.0,
          "gradeformatted": "85.00",
          "grademax": 100.0,
          "grademin": 0.0,
          "percentageformatted": "85 %"
        }
      ]
    }
  ]
}
```

---

## User Profile

### Get User by Field
`wsfunction = core_user_get_users_by_field`

| Field | Type | Required | Notes |
|---|---|---|---|
| field | string | yes | `id` \| `username` \| `email` \| `idnumber` |
| values[0] | string | yes | The value to look up |

```json
[
  {
    "id": 5,
    "username": "student1",
    "firstname": "Ahmed",
    "lastname": "Ibrahim",
    "fullname": "Ahmed Ibrahim",
    "email": "ahmed@example.com",
    "country": "EG",
    "lang": "ar",
    "profileimageurl": "https://...",
    "profileimageurlsmall": "https://..."
  }
]
```

---

## Recommended Screen → API Mapping

| Screen | APIs to call |
|---|---|
| Splash / Login | `core_webservice_get_site_info` (save userid) |
| Home / Discover | `core_course_search_courses` + `core_course_get_categories` |
| Category page | `core_course_search_courses` (criterianame=categoryid) |
| My Courses | `core_enrol_get_users_courses` |
| Course Detail | `core_course_get_courses` + `local_payments_get_course_access` + `local_payments_get_course_price` |
| Course Content | `core_course_get_contents` + `core_completion_get_activities_completion_status` |
| Grades | `gradereport_user_get_grade_items` |
| Profile | `core_user_get_users_by_field` |
| My Purchases | `local_payments_get_purchased_courses` |
| Payment History | `local_payments_get_payment_history` |
| Buy Course | `local_payments_create_checkout` → WebView → `local_payments_verify_payment` |
