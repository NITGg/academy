# US-AD-2-1: Update Settings

[← spec index](../README.md) · Area: Admin · **Status:** In progress · API: [platform-apis](../../api/platform-apis-postman-guide.md)

As an admin, I want to update lesson deadlines and financial settings, so that the system applies the correct lesson and revenue rules.

## Flow
1. 🔧 Open lesson settings
2. ⚙️ Display the settings as **tabs**
3. 🔧 Select the required settings tab
4. 🔧 Update the settings → 🔧 Save
5. ⚙️ Validate and apply the new values

## Tab 1 — Lesson Deadlines
Minimum lesson booking time · student cancellation deadline · lesson time-update deadline · lesson
start allowed time · lesson completion allowed time · absence reporting time (each in minutes or hours).

## Tab 2 — Financial Settings
Teacher earning percentage · platform earning percentage.

## Tab 3 — B2B Subscription Settings
- **Automatically approve invited users** (`b2b_auto_approve_invited_users`) — Enabled / Disabled.
  When **enabled**: an invited user is auto-approved if a seat is available, counts under capacity, and
  gets access. When **disabled**: the user stays *pending*, the B2B administrator must approve, the user
  is not counted and has no access until approved.
- **Return seat when user is removed** (`b2b_return_seat_after_user_removal`) — Enabled / Disabled.
  When **enabled**: a removed user's seat becomes available again (available count +1). When
  **disabled**: the removed user loses access but the seat stays counted as consumed.

## Notes
- Deadline values must be ≥ 0. Teacher % + platform % must total **100%**.
- New values apply to future lesson actions and revenue transactions; existing lesson times and
  completed transactions are unchanged.
- B2B settings apply to future approval/removal actions. Automatic approval cannot exceed the purchased
  capacity. Changing a B2B setting does not retroactively change existing pending, approved, or already
  removed users.

## Related
B2B approval behaviour: [US-B2B-1-4](../B2B%20Administrator/US-B2B-1-4-automatically-approve-invited-user.md).
Seat return on removal: [US-B2B-1-7](../B2B%20Administrator/US-B2B-1-7-remove-an-approved-user.md).
