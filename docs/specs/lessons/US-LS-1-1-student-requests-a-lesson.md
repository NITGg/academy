# US-LS-1-1: Student Requests a Lesson

[← spec index](../README.md) · Area: Lessons · **Status:** Spec

As a student, I want to request a lesson from a teacher, so that I can study the selected subject.

## Flow
1. 🎓 Open a teacher profile → select available date/time → add required note
2. ⚙️ Check active package, available Flex, and selected time
3. ⚙️ Check the lesson is at least one hour from now
4. 🎓 Confirm → ⚙️ create request with `Pending` status → notify teacher

## Notes
- Student must have an active package and available Flex.
- No Flex is permanently used while the request is pending.
- The subject must be supported by the teacher.
- The note is **required** — the request cannot be submitted without it.
