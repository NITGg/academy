# US-B2B-1-6: Reject a User Membership

[← spec index](../README.md) · Area: B2B Administrator · **Status:** Spec

As a B2B administrator, I want to reject a pending user request, so that unauthorized users cannot access my B2B subscription.

## Flow
1. 🏢 Open the pending members list
2. 🏢 Select a user → Select "Reject"
3. 🏢 Optionally enter a reason
4. ⚙️ Change the membership status to **Rejected** (no seat allocated)
5. ⚙️ Notify the user

## Notes
- Rejecting a pending membership does not affect available capacity.
- A rejected user does not receive subscription access.
- The rejection history is preserved.
