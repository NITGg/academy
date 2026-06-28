# US-FN-1-3: View Teacher Earnings and Withdrawals

[← spec index](../README.md) · Area: Teacher (financial) · **Status:** Spec

> ⚠️ Duplicate ID — `US-FN-1-3` is also used by [Return a Reserved Flex](../financial/US-FN-1-3-return-a-reserved-flex.md). Resolve numbering later.

As a teacher, I want to view my earnings and withdrawal history, so that I can track my available balance and withdrawn money.

## Earnings summary
Total earnings · available balance · reserved withdrawal amount · total withdrawn amount.

## Lesson earnings (per row)
Completed lesson · student name · lesson date · Flex value · teacher % · earning amount · earning status.

## Withdrawal history (per row)
Withdrawal amount · request date · withdrawal method · payment reference · status · rejection reason.

## Actions
Request withdrawal · view withdrawal details.

## Notes
- Only consumed Flexes generate earnings; returned Flexes reverse the related earnings.
- Pending withdrawal amounts are reserved and cannot be withdrawn again.
- Withdrawal statuses: Pending, Approved, Rejected, Paid.
