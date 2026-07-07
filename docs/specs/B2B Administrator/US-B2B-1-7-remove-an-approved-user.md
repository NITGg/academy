# US-B2B-1-7: Remove an Approved User

[← spec index](../README.md) · Area: B2B Administrator · **Status:** Spec

As a B2B administrator, I want to remove an approved user, so that the user no longer has access through my B2B subscription.

## Flow
1. 🏢 Open the approved members list
2. 🏢 Select a user → Select "Remove" → Confirm
3. ⚙️ Change the membership status to **Removed**
4. ⚙️ Revoke the user's B2B subscription access
5. ⚙️ Check the seat-return setting
6. ⚙️ If enabled, return the user's seat; if disabled, keep the seat counted as consumed
7. ⚙️ Update the used and available capacity
8. ⚙️ Notify the user

## Seat-return setting (`b2b_return_seat_after_user_removal`)
- **Enabled** — the removed user no longer consumes a seat; available capacity +1; another user can be
  approved in that seat.
- **Disabled** — the removed user loses access but the consumed seat remains counted; available capacity
  does not increase; the seat stays consumed until the B2B subscription expires.

## Notes
- The removed user is treated as unsubscribed only from this B2B subscription; access is revoked
  immediately.
- Removal must not cancel a separate Normal subscription the user owns, nor access from another valid B2B
  subscription, nor delete the account. The user's main role does not change.
- Status changes Approved → Removed; a Removed membership no longer grants access.
- Membership and removal history are preserved; the system records who removed the user and when.
- Removing the same user more than once must not revoke access repeatedly or change the seat count
  multiple times.

## Related
Setting defined in [US-AD-2-1](../admin/US-AD-2-1-update-lesson-settings.md) (Tab 3). Capacity math:
[US-B2B-1-8](US-B2B-1-8-view-b2b-subscription-capacity.md).
