# Overview

## Application Overview

The platform connects **students** with **teachers**. Three roles: **Student**, **Teacher**, **Admin**.

- Teachers register and wait for lesson requests from students.
- Students purchase lesson packages and spend their available lessons (Flexes) to request sessions.

## User Roles Overview

**Student** can: create an account · browse teachers · view teacher profiles · purchase a package ·
send a lesson request · pick a preferred date/time · negotiate another time · view previous lessons ·
cancel per the cancellation rules.

**Teacher** can: create an account · add personal info · add subjects/specializations · receive lesson
requests · accept / reject / suggest another time · view previous lessons.

**Admin** can: approve/reject teacher accounts · create & update packages · set prices · set expiration
periods · view payments & reports · suspend/activate accounts.

## Lesson Packages Overview

### Shared rules

- **One Flex = one lesson.** One lesson = **1 hour (+10 min break)**.
- The student must have an **active package** to request a lesson.
- A student can request a lesson from any available teacher.
- A student cannot use more lessons than their remaining Flex balance.
- Each package has an expiration period in **days** (`0` = unlimited).
- Unused Flexes expire when the package expiration date is reached.
- Admin defines package Flex count, price, and expiration period.

### Catalog

| Package | Flexes | Price   |
| ------- | ------ | ------- |
| Flex10  | 10     | 1000 EG |
| Flex20  | 20     | 1900 EG |
| Flex30  | 30     | 2700 EG |

## Glossary

- **Flex** — one prepaid lesson credit. Value = package price ÷ flex count.
- **Reserved Flex** — held when a lesson is confirmed; not yet earnings.
- **Consumed Flex** — permanently spent when a lesson is completed; triggers revenue split.
- **Returned Flex** — given back (teacher cancel/absence, early student cancel, admin reversal).
- **Active package** — a purchased/assigned package with remaining, non-expired Flexes. A student may
  hold only **one** active package at a time.

## Lesson status model

`Pending` → (`Waiting for Student` ⇄ `Waiting for Teacher`) → `Confirmed` → `In Progress` →
`Completed` | `Student Absent` | `Teacher Absent` | `Cancelled` / `Cancelled by Teacher` |
`Rejected by Teacher`.

## Package status model

`Pending Payment` → (`Payment Failed` | `Cancelled`) | `Active` → (`Fully Used` | `Expired`).
