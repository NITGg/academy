# US-B2B-1-5: Approve a User Membership

[← spec index](../README.md) · Area: B2B Administrator · **Status:** Spec

As a B2B administrator, I want to approve a pending user, so that the user receives subscription access through my B2B subscription.

## Flow
1. 🏢 Open the B2B subscription members list
2. 🏢 Open a pending membership request → Select "Approve"
3. ⚙️ Check the parent B2B subscription is active and not expired
4. ⚙️ Check that an available user seat exists
5. ⚙️ Change the membership status to **Approved** and allocate one seat
6. ⚙️ Create subscription access linked to the parent B2B subscription and membership
7. ⚙️ Set the user's access expiration to the parent B2B subscription expiration date
8. ⚙️ Give the user access to the subscription's eligible courses
9. ⚙️ Notify the user

## Notes
- The approved user gets the same course-access benefits as a Normal-subscription buyer, but creates no
  payment or individual purchase record.
- The access source must be recorded as **B2B**, linked to the B2B administrator's subscription, and
  cannot continue beyond the parent expiration date.
- Only approved users consume capacity; pending and rejected users do not. Approval is blocked when
  capacity is full.
- Approval stores who approved and when. Approving the same membership more than once must not consume
  multiple seats or create duplicate access.
- Removing the user later must revoke or deactivate the related B2B access.

## Related
Reject: [US-B2B-1-6](US-B2B-1-6-reject-a-user-membership.md). Remove:
[US-B2B-1-7](US-B2B-1-7-remove-an-approved-user.md).
