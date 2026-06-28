# US-LS-2-1: Teacher Accept / Reject / Suggest

[← spec index](../README.md) · Area: Lessons · **Status:** Spec

As a teacher, I want to accept, reject, or suggest another lesson time, so that I can respond to the request.

## Flow
1. 👨‍🏫 Open a pending request → review student, subject, date, time, note
2. 👨‍🏫 Choose: Accept / Reject / Suggest another time
3. ⚙️ Update status → notify student

## Results
- Accept → `Confirmed`, **1 Flex reserved**
- Reject → `Rejected by Teacher`
- Suggest another time → `Waiting for Student`

## Notes
- Teacher can add a rejection reason. Suggested times must be available.
- Lesson is added to both schedules after confirmation.
