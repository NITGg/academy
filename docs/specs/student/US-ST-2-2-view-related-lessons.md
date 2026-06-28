# US-ST-2-2: View Related Lessons

[← spec index](../README.md) · Area: Student · **Status:** Spec

As a student, I want to view my lesson requests and lessons, so that I can follow status and manage my schedule.

## Flow
1. 🎓 Open "My Lessons" → ⚙️ display lessons related to the student
2. 🎓 Filter by status → select a lesson
3. ⚙️ Display details (teacher, date, reject reason, attendance report, …) and available actions

## Available actions (status-dependent)
Cancel the request · accept a suggested time · suggest another time · cancel a confirmed lesson · report teacher absence · view details.

## Notes
- Student sees only their own lessons; upcoming confirmed appear first.
- Upcoming lessons stay inactive until the teacher starts the lesson.
- Completed/cancelled remain in history.
- System shows whether each Flex is available, reserved, returned, or used.
