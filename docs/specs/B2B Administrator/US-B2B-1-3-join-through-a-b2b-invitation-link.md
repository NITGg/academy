# US-B2B-1-3: Join Through a B2B Invitation Link

[← spec index](../README.md) · Area: B2B Administrator · **Status:** Spec

As an invited user, I want to register or log in through a B2B invitation link, so that my account can be linked to the related B2B subscription.

## Flow
1. 👤 Open the invitation link
2. ⚙️ Validate the invitation link
3. ⚙️ Display registration or login
4. 👤 Register a new account or log in
5. ⚙️ Create the user (if registering)
6. ⚙️ Link the account to the B2B administrator and subscription
7. ⚙️ Create a B2B membership request
8. ⚙️ Determine the initial membership status based on platform settings

## Membership statuses
- **Pending Approval** — joined via the link but cannot access the subscription yet.
- **Approved** — accepted, consumes a seat, can access the subscription.
- **Rejected** — the B2B administrator refused the join request.
- **Removed** — was approved, then removed by the B2B administrator.
- **Expired** — no longer usable because the related B2B subscription expired.

## Notes
- Registering through the link does not change the user's main role.
- The relationship is represented by a membership record.
- No subscription access before approval unless automatic approval is enabled.
- Reopening the same link must not create duplicate memberships; detect an account already linked to the
  same subscription.
- An invalid, expired, disabled, or revoked link must not create a membership.

## Related
Auto-approval: [US-B2B-1-4](US-B2B-1-4-automatically-approve-invited-user.md). Manual approval:
[US-B2B-1-5](US-B2B-1-5-approve-a-user-membership.md).
