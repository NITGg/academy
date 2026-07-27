# Certificate Eligibility Service (plugin-agnostic)

Academy2026 decides **who is eligible** for a certificate using configurable business rules, while
the certificate plugin (e.g. Custom Certificate) stays responsible for rendering, PDF, verification
and download. This layer **only answers "is the student eligible?"** — it does not depend on, modify,
or reference any certificate plugin.

## Scope & boundaries (read first)

`academy_certificates` is a **temporary eligibility wrapper, not a certificate system**. It exists
only to hold the Academy-specific eligibility configuration until a real certificate provider is
available. It must **never** duplicate certificate functionality — no templates, PDF generation,
verification, downloads, or certificate history.

- **Now (no provider):** the wrapper stores the ruleset; `name`/`type` are just admin-facing labels
  so several eligibility configs in one course can be told apart.
- **Later (`mod_customcert` installed):** set the wrapper's `externalref` to the Custom Certificate
  activity's `cmid`. That activity becomes the **single source of truth** for the certificate itself;
  the wrapper keeps governing *only* eligibility (ideally via an availability condition — see below).

Do not grow this table/class into a parallel certificate implementation.

- **Where it lives:** `local_academy` (namespace `local_academy\cert`).
- **Domain model:** eligibility rules belong to a **certificate**, not a course. A course may have
  many certificates (Certificate of Completion, of Attendance, of Excellence, …), each with its own
  ruleset.
- **Storage:** one row per certificate in `{academy_certificates}`. The row `id` is the abstract
  **`certificate_id`** the engine evaluates; `externalref` maps it to the real certificate activity
  later (e.g. a `mod_customcert` cmid) with **no redesign**. `courseid` is only the context the rules
  evaluate against.
- **Admin UI:** Site administration → Plugins → Local plugins → **Certificate eligibility**
  (`/local/academy/certificate_eligibility.php`).

## Concepts

A **certificate** carries its self-contained ruleset (root operator + a flat list of rules):

```json
{
  "operator": "and",            // "and" = all rules pass, "or" = any rule passes
  "rules": [
    { "type": "course_progress", "config": { "threshold": 90 } },
    { "type": "attendance",      "config": { "threshold": 70 } },
    { "type": "quiz_passed",     "config": { "quizid": 42 } }
  ]
}
```

- A certificate with **no rules**, or a **disabled** one, means **no one is eligible** (we never
  award on a misconfiguration).
- Unknown or errored rules **fail safe** (treated as not passed) but are still reported.

### Built-in rule types

| type | config | passes when |
|------|--------|-------------|
| `course_progress`  | `threshold` (0–100) | course completion progress % ≥ threshold (core `\core_completion\progress`) |
| `attendance`       | `threshold` (0–100) | attended ÷ expected completed live sessions ≥ threshold (`local_academysessions` tables) |
| `quiz_passed`      | `quizid` (quiz instance id) | gradebook finalgrade ≥ the quiz's `gradepass` (pass grade must be set) |
| `assign_completed` | `cmid` (course module id) | activity completion state ≠ incomplete |
| `course_completed` | — | `\completion_info::is_course_complete()` |

## Adding a new rule type (no engine change)

The evaluation engine never changes. To add a rule:

1. Add a class under `local/academy/classes/cert/rule/` implementing
   `local_academy\cert\rule_interface` (`get_type`, `get_label`, `get_config_schema`, `describe`,
   `evaluate`, `measure`).
2. Register it in `local_academy\cert\rule_registry::builtin()` (one line).

Other plugins/tests can register rules at runtime without editing any file:
`\local_academy\cert\rule_registry::register('my_type', my_rule::class);`

## PHP service API

```php
use local_academy\cert\eligibility_manager;

// Evaluate a specific certificate:
eligibility_manager::is_eligible($userid, $certificateid): bool;
eligibility_manager::get_report($userid, $certificateid): array;   // overall + per-rule breakdown

// All certificates in a course (a course can have several):
eligibility_manager::get_course_certificates($courseid): array;                 // raw rows
eligibility_manager::get_course_certificate_reports($userid, $courseid): array; // per-cert reports

// Admin CRUD:
eligibility_manager::save_certificate($data, $userid): int;   // create/update (id in $data = update)
eligibility_manager::delete_certificate($certificateid): void;

// Ad-hoc (tests / a future availability adapter carrying inline JSON):
eligibility_manager::evaluate_adhoc($userid, $courseid, $rulesetArray): array;
```

`get_report()` returns:

```php
[
  'eligible'      => bool,
  'operator'      => 'and'|'or',
  'results'       => [ [
      'type'        => 'course_progress',
      'passed'      => true,
      'actual'      => 92.5,
      'required'    => 90,
      'unit'        => '%',
      'label'       => 'Course progress ≥ threshold %',        // rule TYPE — admin wording
      'description' => 'Complete at least 90% of "HTML & CSS"', // the requirement — show this
  ], … ],
  'certificateid' => int,
  'courseid'      => int,
  'name'          => string,
  'type'          => string,   // completion|attendance|excellence|custom (label only)
  'externalref'   => int,      // 0 = not yet mapped to a real activity
  'enabled'       => bool,
]
```

**`label` vs `description`.** `label` names the rule *type* and is what the admin picker shows; it
contains placeholders like "≥ threshold %" and means nothing to a student. `description` is the same
rule with the admin's configuration and scope filled in — the actual instruction ("Complete at least
90% of the program's courses"). **Student-facing UI must show `description`**, falling back to
`label` only when it is `''` (a rule that could not name its target, e.g. a deleted quiz).

## HTTP API (`/local/academy/api.php?function=…&token=…`)

- **`check_certificate_eligibility`** (student; admins may pass `userid`)
  Params: `certificateid`, optional `userid`. Returns the `get_report()` payload.
- **`list_certificate_eligibility`** (student; admins may pass `userid`)
  Params: `courseid`, optional `userid`. Returns `{ courseid, certificates:[ report, … ] }`.
- **`get_certificates`** (cap `local/academy:manageplatform`)
  Params: `courseid`. Returns the course's certificates + `catalogue` (rule types & config fields) +
  `activities` (course quizzes/assignments) to build the UI.
- **`save_certificate`** (POST; cap `manageplatform`)
  Params: `id` (0 = create), `courseid`, `name`, `type`, `externalref`, `operator` (`and`/`or`),
  `enabled` (0/1), `rules` (JSON list of `{type, config}`).
- **`delete_certificate`** (POST; cap `manageplatform`) — Params: `id`.

## Future integration (designed for, not built)

When a certificate plugin (e.g. `mod_customcert`) is installed, set each certificate's `externalref`
to the real activity, then add an `availability/condition/*` plugin whose `is_available()` calls
`eligibility_manager::is_eligible($userid, $certificateid)`. That gates the real certificate activity
via Moodle's native "Restrict access" with **zero changes** to the certificate plugin — the same
ruleset JSON simply moves inline into the activity's availability config.

## Verifying

PHPUnit (inside the app container):

```bash
php admin/tool/phpunit/cli/init.php          # once, to init the test DB
vendor/bin/phpunit local/academy/tests/cert_eligibility_test.php
```

Covers: AND/OR combining, empty/unknown-rule fail-safe, certificate CRUD + disabled handling,
**multiple certificates per course** evaluated independently, extensibility via a runtime-registered
rule, and the `course_progress` / `attendance` rules against real generated data.
