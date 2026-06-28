# US-PK-1-2: Purchase a Package

[← spec index](../README.md) · Area: Student (packages) · **Status:** Built · API: [student-packages](../../api/student-packages-mobile-guide.md)

As a student, I want to purchase a lesson package, so that I can request lessons from teachers.

## Flow
1. 🎓 Open packages → select Flex10/Flex20/Flex30 → tap "Buy Package"
2. ⚙️ Show payment screen → 🎓 enter payment info → confirm
3. ⚙️ Process payment → activate package → add Flex balance → calculate expiration date
4. ⚙️ Check the user does not already have an active package
5. 🎓 Receive purchase confirmation

## Notes
- Package activates only after successful payment. Expiration based on package rules.
- Each Flex = one lesson. A user cannot hold more than one active package.

## Related
Flex/payment mechanics in [US-FN-1-1](../financial/US-FN-1-1-purchase-a-flex-package.md). Package status model in [overview](../00-overview.md).
