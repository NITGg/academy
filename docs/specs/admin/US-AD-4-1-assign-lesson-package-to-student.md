# US-AD-4-1: Assign a Lesson Package to a Student

[← spec index](../README.md) · Area: Admin · **Status:** Spec

As an admin, I want to assign a package to a specific student, so that the student receives Flexes after paying outside the platform.

## Flow
1. 🔧 Open student profile → select "Assign Package"
2. 🔧 Select an active package → add external payment details → confirm
3. ⚙️ Activate package → add Flexes to balance → record admin assignment

## Payment details
Amount paid · payment method · payment reference · payment date · optional note.

## Notes
- Payment happens outside the platform; no online gateway.
- Student must not already have an active package. Only active packages can be assigned.
- Package uses its current Flex count and expiration period. System records the assigning admin.
