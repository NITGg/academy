# US-FN-1-5: Return a Flex After Revenue Distribution

[← spec index](../README.md) · Area: Financial · **Status:** Spec

As an admin, I want to return a consumed Flex to a student, so that I can correct an approved lesson or financial issue.

## Flow
1. 🔧 Open the completed lesson → select "Return Flex" → add a return reason → confirm
2. ⚙️ Return one Flex to the student
3. ⚙️ Reverse the teacher earning → reverse the platform earning → record the reversal transaction

## Example
Flex value 1 EGP → student +1 Flex, teacher −0.40 EGP, platform −0.60 EGP.

## Notes
- Only consumed & distributed Flexes can be returned. Admin must provide a reason. A Flex cannot be returned more than once.
- Original transaction remains in history; a separate reversal transaction is created for auditing.
- If the teacher earning was already withdrawn, the reversed amount is deducted from the teacher's current or future balance.
