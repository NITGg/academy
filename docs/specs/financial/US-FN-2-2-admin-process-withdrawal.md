# US-FN-2-2: Admin Process Withdrawal

[← spec index](../README.md) · Area: Financial · **Status:** Spec

As an admin, I want to review and process teacher withdrawal requests, so that teachers receive their available earnings.

## Flow
1. Admin → open withdrawal requests → review teacher & payment details
2. Admin → approve or reject → record payment reference when paid
3. ⚙️ Update status → notify teacher

## Results
- Approve → `Approved`
- Confirm payment → `Paid`
- Reject → `Rejected`, amount returns to teacher balance

## Notes
- Admin must provide a reason when rejecting. Only approved requests can be marked paid.
- A paid withdrawal cannot be processed again. System records the admin who processed it.
- Teacher side: [US-FN-2-1](US-FN-2-1-teacher-earnings-withdrawal.md).
