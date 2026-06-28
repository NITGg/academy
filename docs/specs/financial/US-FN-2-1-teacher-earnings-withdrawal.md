# US-FN-2-1: Teacher Earnings Withdrawal

[← spec index](../README.md) · Area: Financial · **Status:** Spec

> ⚠️ Duplicate ID — `US-FN-2-1` is also used by [Export Teacher Reports](../teacher/US-FN-2-1-export-teacher-reports.md). Resolve numbering later.

As a teacher, I want to request a withdrawal from my available earnings, so that I can receive the money I earned.

## Flow
1. Teacher → open earnings → "Withdraw Earnings" → enter amount → select method → enter account details → confirm
2. ⚙️ Validate available balance → reserve the requested amount → create a pending request → notify admin

## Results
- Admin approves & pays → `Paid`
- Admin rejects → reserved amount returns to available balance

## Notes
- Amount must be > 0 and ≤ available earnings. Reserved earnings cannot be in another request.
- Reversed or pending earnings cannot be withdrawn. Teacher can track status.
- Statuses: Pending, Approved, Rejected, Paid.
- Admin side: [US-FN-2-2](US-FN-2-2-admin-process-withdrawal.md).
