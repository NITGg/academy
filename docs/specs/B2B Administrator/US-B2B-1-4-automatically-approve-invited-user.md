# US-B2B-1-4: Automatically Approve an Invited User

[← spec index](../README.md) · Area: B2B Administrator · **Status:** Spec

As a platform administrator, I want to configure whether invited users are automatically approved, so that the platform can support automatic or manual membership approval.

## Flow
1. 🔧 Open B2B subscription settings
2. 🔧 Configure "Automatically approve invited users" → Save
3. ⚙️ Apply the setting to new membership requests

### When automatic approval is enabled
1. 👤 Register or log in through the invitation link
2. ⚙️ Check the available B2B capacity
3. ⚙️ Approve the membership
4. ⚙️ Allocate one user seat
5. ⚙️ Give the user access to the subscription's eligible courses

### When automatic approval is disabled
1. 👤 Register or log in through the invitation link
2. ⚙️ Create a Pending Approval membership (no seat allocated, no access)
3. ⚙️ Notify the B2B administrator

## Notes
- Automatic approval must still respect the purchased capacity; if no seat is available, the membership
  stays pending.
- The setting affects new membership requests only.
- Changing the setting does not automatically change existing pending or approved memberships unless
  explicitly requested.

## Related
Setting defined in [US-AD-2-1](../admin/US-AD-2-1-update-lesson-settings.md) (Tab 3,
`b2b_auto_approve_invited_users`).
