# US-AD-2-1: Update Lesson Settings

[← spec index](../README.md) · Area: Admin · **Status:** Built · API: [platform-apis](../../api/platform-apis-postman-guide.md)

As an admin, I want to update lesson deadlines and financial settings, so that the system applies the correct lesson and revenue rules.

## Flow
1. 🔧 Open lesson settings
2. 🔧 Update lesson deadlines → select minutes or hours
3. 🔧 Update teacher earning percentage
4. 🔧 Update platform earning percentage
5. 🔧 Save → ⚙️ validate and apply

## Lesson deadlines
Minimum lesson booking time · student cancellation deadline · lesson time-update deadline · lesson start allowed time · **minimum minutes after start before completing** · absence reporting time.

## Financial settings
Teacher earning % · platform earning %.

## Notes
- Deadlines must be ≥ 0. Teacher % + platform % must total **100%**.
- New values apply to future actions/transactions; existing lesson times and completed transactions unchanged.
