# US-LS-3-1: Start and Join a Lesson

[← spec index](../README.md) · Area: Lessons · **Status:** In progress

As a teacher, I want to start a confirmed lesson, so that the teacher and student can join the meeting room.

## Flow
1. 👨‍🏫 Open the confirmed lesson
2. ⚙️ Check that the allowed start time has arrived
3. 👨‍🏫 Tap "Start Lesson"
4. ⚙️ Create the meeting room
5. ⚙️ Status → `In Progress` → record the actual start time
6. ⚙️ Display the "Join Lesson" button for the teacher and student → notify the student
7. 👨‍🏫 / 🎓 Tap "Join Lesson"
8. ⚙️ Open the meeting room

## Notes
- Only the assigned teacher can start the lesson.
- Only confirmed lessons can be started.
- The lesson cannot start before the allowed start time.
- Only the assigned teacher and student can join the meeting room.
- The "Join Lesson" button appears after the meeting room is created.
- The same meeting room is used by the teacher and student.
- After starting, the teacher can complete the lesson or report student absence.
