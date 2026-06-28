# US-FN-1-4: Distribute Lesson Revenue

[← spec index](../README.md) · Area: Financial · **Status:** Spec

As a system, I want to distribute the Flex value after a completed lesson, so that the teacher and platform receive their shares.

## Flow
1. Teacher → completes the lesson
2. ⚙️ Consume the reserved Flex
3. ⚙️ Add **40%** of Flex value to teacher earnings
4. ⚙️ Add **60%** of Flex value to platform revenue
5. ⚙️ Record the financial transaction

## Example
Flex value 1 EGP → teacher 0.40 EGP, platform 0.60 EGP.

## Notes
- Revenue distributed only when the Flex is permanently consumed.
- Percentages are configurable by admin ([US-AD-2-1](../admin/US-AD-2-1-update-lesson-settings.md)) and calculated from the Flex value of the purchased package.
- Triggered by lesson completion: [US-LS-3-2](../lessons/US-LS-3-2-complete-a-lesson.md).
