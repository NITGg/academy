# US-LS-2-2: Student Accept / Reject / Suggest

[← spec index](../README.md) · Area: Lessons · **Status:** Spec

As a student, I want to respond to the teacher's suggested time, so that we can agree on a suitable time.

## Flow
1. 🎓 Open request with `Waiting for Student` → review suggested time
2. 🎓 Choose: Accept / Reject / Suggest another time
3. ⚙️ Update status → notify teacher

## Results
- Accept → `Confirmed`, **1 Flex reserved**
- Reject → `Suggested Time Rejected by Student` (⚠️ source says "or something else" — confirm exact status)
- Suggest another time → `Waiting for Teacher`

## Notes
- Student must still have an active package and available Flex before confirmation.
