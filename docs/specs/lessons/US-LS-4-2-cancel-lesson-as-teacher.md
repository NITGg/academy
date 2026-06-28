# US-LS-4-2: Cancel a Lesson as a Teacher

[← spec index](../README.md) · Area: Lessons · **Status:** Spec

As a teacher, I want to cancel a confirmed lesson, so that the student can use the Flex for another lesson.

## Flow
1. 👨‍🏫 Open a confirmed lesson → tap "Cancel Lesson" → enter cancellation reason → confirm
2. ⚙️ Status → `Cancelled by Teacher` → **return the reserved Flex** → release the lesson time → notify student

## Notes
- A teacher cancellation should not consume the student's Flex.
- Repeated cancellations may be reviewed by the admin.
