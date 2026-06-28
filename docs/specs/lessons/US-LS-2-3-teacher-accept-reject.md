# US-LS-2-3: Teacher Accept / Reject (after student response)

[← spec index](../README.md) · Area: Lessons · **Status:** Spec

As a teacher, I want to review the student's response and suggested times, so that I can accept a suitable time or reject the request.

## Flow
1. 👨‍🏫 Open request with `Waiting for Teacher` → review response and suggested times
2. 👨‍🏫 Choose: Accept a suggested time / Reject the request
3. ⚙️ Update status → notify student

## Results
- Accept a suggested time → `Confirmed`, **1 Flex reserved**
- Reject → `Rejected by Teacher`

## Notes
- Teacher can accept any available time suggested by the student; can add optional rejection reason.
- Selected time must still be available. A rejected request cannot be confirmed later.
