# Academy Platform — Feature Specs (User Stories)

Single source of truth for the **Flex tutoring platform** requirements. These describe a
forward-looking system (student↔teacher lesson marketplace). They are **not** documentation of
the current Moodle 3.11 install — none of these tables/features exist in the live DB yet.

> How to add or update a story: see the `academy-specs` skill (`.claude/skills/academy-specs/`).
> Paste an updated story to Claude and it routes it to the right file and refreshes the table below.

## Layout

**One file per user story**, grouped in area subfolders. Filenames are `US-<ID>-<slug>.md`.

```
docs/specs/
  README.md            ← this index
  00-overview.md       ← roles, package catalog, Flex concept, status models, glossary
  admin/               US-AD-*
  teacher/             US-TR-* + teacher financial (US-FN-1-3, US-FN-2-1)
  student/             US-ST-*, US-PK-*
  lessons/             US-LS-*
  financial/           00-wallet-model.md + US-FN-*
```

## ID convention

`US-<AREA>-<GROUP>-<SEQ>` — e.g. `US-AD-1-2` = Admin, group 1 (packages), story 2.

| AREA | Meaning | Folder |
|------|---------|--------|
| AD | Admin | `admin/` |
| TR | Teacher | `teacher/` |
| ST | Student | `student/` |
| PK | Packages (student-facing) | `student/` |
| LS | Lessons | `lessons/` |
| FN | Financial | `financial/` (or `teacher/` for teacher-facing financial) |

### ID collisions — resolved
The two teacher-facing financial stories were renamed so every ID is now unique:
- *View Teacher Earnings and Withdrawals*: `US-FN-1-3` → **`US-TR-1-3`** (teacher/). `US-FN-1-3` = *Return a Reserved Flex* (financial/).
- *Export Teacher Reports*: `US-FN-2-1` → **`US-TR-2-1`** (teacher/). `US-FN-2-1` = *Teacher Earnings Withdrawal* (financial/).

## Status legend
`Spec` = written, not built · `In progress` = being implemented · `Built` = implemented & verified.

## Story index

### Admin — `admin/`
| ID | Title | Status |
|----|-------|--------|
| US-AD-1-1 | [Create a Lesson Package](admin/US-AD-1-1-create-lesson-package.md) | Built |
| US-AD-1-2 | [Update a Lesson Package](admin/US-AD-1-2-update-lesson-package.md) | Built |
| US-AD-1-3 | [Deactivate a Lesson Package](admin/US-AD-1-3-deactivate-lesson-package.md) | Built |
| US-AD-1-4 | [Delete an Unused Lesson Package](admin/US-AD-1-4-delete-unused-lesson-package.md) | Built |
| US-AD-2-1 | [Update Lesson Settings](admin/US-AD-2-1-update-lesson-settings.md) | Built |
| US-AD-3-1 | [View Lessons and Attendance Reports](admin/US-AD-3-1-view-lessons-and-attendance-reports.md) | Spec |
| US-AD-3-2 | [View Platform Earnings](admin/US-AD-3-2-view-platform-earnings.md) | Spec |
| US-AD-3-3 | [View Package and Flex Reports](admin/US-AD-3-3-view-package-and-flex-reports.md) | Spec |
| US-AD-3-4 | [View Student Flex Balance and History](admin/US-AD-3-4-view-student-flex-balance-and-history.md) | Spec |
| US-AD-4-1 | [Assign a Lesson Package to a Student](admin/US-AD-4-1-assign-lesson-package-to-student.md) | Spec |

### Teacher — `teacher/`
| ID | Title | Status |
|----|-------|--------|
| US-TR-1-1 | [Update Teacher Profile](teacher/US-TR-1-1-update-teacher-profile.md) | Built |
| US-TR-1-2 | [View Related Lessons](teacher/US-TR-1-2-view-related-lessons.md) | Spec |
| US-TR-1-3 | [View Teacher Earnings and Withdrawals](teacher/US-TR-1-3-view-teacher-earnings-and-withdrawals.md) | Spec |
| US-TR-2-1 | [Export Teacher Reports](teacher/US-TR-2-1-export-teacher-reports.md) | Spec |

### Student — `student/`
| ID | Title | Status |
|----|-------|--------|
| US-ST-1-1 | [Student Registration](student/US-ST-1-1-student-registration.md) | Spec |
| US-ST-2-1 | [Browse Teachers](student/US-ST-2-1-browse-teachers.md) | Built |
| US-ST-2-2 | [View Related Lessons](student/US-ST-2-2-view-related-lessons.md) | Spec |
| US-PK-1-1 | [View Available Packages](student/US-PK-1-1-view-available-packages.md) | Built |
| US-PK-1-2 | [Purchase a Package](student/US-PK-1-2-purchase-a-package.md) | Built |
| US-PK-2-1 | [View My Packages and Payment History](student/US-PK-2-1-view-my-packages-and-payment-history.md) | Built |

### Lessons — `lessons/`
| ID | Title | Status |
|----|-------|--------|
| US-LS-1-1 | [Student Requests a Lesson](lessons/US-LS-1-1-student-requests-a-lesson.md) | Spec |
| US-LS-2-1 | [Teacher Accept/Reject/Suggest](lessons/US-LS-2-1-teacher-accept-reject-suggest.md) | Spec |
| US-LS-2-2 | [Student Accept/Reject/Suggest](lessons/US-LS-2-2-student-accept-reject-suggest.md) | Spec |
| US-LS-2-3 | [Teacher Accept/Reject (after response)](lessons/US-LS-2-3-teacher-accept-reject.md) | Spec |
| US-LS-3-1 | [Start a Lesson](lessons/US-LS-3-1-start-a-lesson.md) | Spec |
| US-LS-3-2 | [Complete a Lesson](lessons/US-LS-3-2-complete-a-lesson.md) | Spec |
| US-LS-3-3 | [Report Student Absence](lessons/US-LS-3-3-report-student-absence.md) | Spec |
| US-LS-3-4 | [Report Teacher Absence](lessons/US-LS-3-4-report-teacher-absence.md) | Spec |
| US-LS-4-1 | [Cancel a Lesson as a Student](lessons/US-LS-4-1-cancel-lesson-as-student.md) | Spec |
| US-LS-4-2 | [Cancel a Lesson as a Teacher](lessons/US-LS-4-2-cancel-lesson-as-teacher.md) | Spec |
| US-LS-5-1 | [Update Lesson Time](lessons/US-LS-5-1-update-lesson-time.md) | Spec |
| US-LS-5-2 | [Respond to Update Request](lessons/US-LS-5-2-respond-to-update-request.md) | Spec |

### Financial — `financial/`
| ID | Title | Status |
|----|-------|--------|
| — | [Wallet model (reference)](financial/00-wallet-model.md) | — |
| US-FN-1-1 | [Purchase a Flex Package](financial/US-FN-1-1-purchase-a-flex-package.md) | Built |
| US-FN-1-2 | [Reserve a Flex for a Lesson](financial/US-FN-1-2-reserve-a-flex-for-a-lesson.md) | Spec |
| US-FN-1-3 | [Return a Reserved Flex](financial/US-FN-1-3-return-a-reserved-flex.md) | Spec |
| US-FN-1-4 | [Distribute Lesson Revenue](financial/US-FN-1-4-distribute-lesson-revenue.md) | Spec |
| US-FN-1-5 | [Return a Flex After Revenue Distribution](financial/US-FN-1-5-return-a-flex-after-revenue-distribution.md) | Spec |
| US-FN-2-1 | [Teacher Earnings Withdrawal](financial/US-FN-2-1-teacher-earnings-withdrawal.md) | Spec |
| US-FN-2-2 | [Admin Process Withdrawal](financial/US-FN-2-2-admin-process-withdrawal.md) | Spec |
