# US-LS-4-1: Cancel a Lesson as a Student

[← spec index](../README.md) · Area: Lessons · **Status:** Spec

As a student, I want to cancel a confirmed lesson, so that the teacher knows I cannot attend.

## Flow
1. 🎓 Open a confirmed lesson → tap "Cancel Lesson"
2. ⚙️ Check the cancellation deadline → 🎓 confirm
3. ⚙️ Status → `Cancelled` → apply cancellation policy → notify teacher

## Notes
- Early cancellation can return the reserved Flex; **late cancellation consumes the Flex**.
- The cancellation deadline is configured by the admin (see [US-AD-2-1](../admin/US-AD-2-1-update-lesson-settings.md)).
