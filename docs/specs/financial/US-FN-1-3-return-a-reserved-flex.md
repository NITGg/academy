# US-FN-1-3: Return a Reserved Flex

[← spec index](../README.md) · Area: Financial · **Status:** Spec

> ⚠️ Duplicate ID — `US-FN-1-3` is also used by [View Teacher Earnings and Withdrawals](../teacher/US-FN-1-3-view-teacher-earnings-and-withdrawals.md). Resolve numbering later.

As a system, I want to return a reserved Flex when required, so that the student does not lose it incorrectly.

## Flow
1. ⚙️ Receive a valid cancellation or absence result
2. ⚙️ Return the reserved Flex to the student
3. ⚙️ Cancel any related teacher or platform earnings

## Flex return cases
Teacher cancels the lesson · teacher is absent · student cancels before the cancellation deadline.

## Notes
- No teacher earnings added; no platform revenue added.
