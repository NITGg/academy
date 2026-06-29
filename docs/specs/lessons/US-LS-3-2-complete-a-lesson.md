# US-LS-3-2: Complete a Lesson

[← spec index](../README.md) · Area: Lessons · **Status:** In progress

As a teacher, I want to complete an in-progress lesson, so that the lesson, Flex usage, and earnings are recorded.

## Flow
1. 👨‍🏫 Conduct the lesson in the meeting room
2. 👨‍🏫 Tap "Complete Lesson"
3. ⚙️ Check the lesson has run for the minimum time before allowing completion
4. ⚙️ End the meeting room
5. ⚙️ Status → `Completed` → record the completion time
6. ⚙️ **Permanently consume the reserved Flex**
7. ⚙️ Distribute the Flex value between the teacher and platform
8. ⚙️ Add the lesson to lesson history → notify the student

## Notes
- Only `In Progress` lessons can be completed.
- The lesson cannot be completed until it has run for the configured minimum time after starting (admin-configured "minimum minutes after start before completing" — see [US-AD-2-1](../admin/US-AD-2-1-update-lesson-settings.md)).
- Only the assigned teacher can complete the lesson.
- The teacher can add optional lesson notes.
- The lesson cannot be completed more than once.
- Completing the lesson closes the meeting room.
- Teacher and platform earnings use the percentages active when the lesson is completed — see [US-FN-1-4](../financial/US-FN-1-4-distribute-lesson-revenue.md).
