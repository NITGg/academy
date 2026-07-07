# US-B2B-1-1: Purchase a B2B Subscription

[← spec index](../README.md) · Area: B2B Administrator · **Status:** Spec

As a user, I want to purchase a B2B subscription with a selected user capacity, so that I can manage subscription access for a group of users.

## Flow
1. 👤 Open an active subscription plan
2. 👤 Select the B2B subscription type
3. ⚙️ Display the available users-capacity options
4. 👤 Select a capacity (e.g. 10, 20, or 50 users)
5. ⚙️ Display the base price, discount, and final B2B price
6. 👤 Continue to payment → complete the payment
7. ⚙️ Confirm the payment
8. ⚙️ Create the B2B subscription and store the selected capacity and purchase price
9. ⚙️ Change the user's role to **B2B Administrator**
10. ⚙️ Calculate the expiration date
11. 🏢 Receive a purchase confirmation

## Notes
- The role changes only after successful payment. Failed or cancelled payments must not change the role.
- The purchase record must store the original base price, capacity, discount %, and final price.
- The subscription expires according to its configured duration.
- The B2B administrator cannot approve more users than the purchased capacity.
- The subscription **type** cannot be changed after the plan is created; the admin can only update the
  configuration of the existing type. For Normal: base price, duration, name, description. For B2B: also
  the available capacities and discount ratios. Changes apply only to future purchases.

## Related
Plan + seat options: [US-AD-5-1](../admin/US-AD-5-1-create-subscription-plan.md). Generate invites:
[US-B2B-1-2](US-B2B-1-2-generate-a-b2b-invitation-link.md). Capacity view:
[US-B2B-1-8](US-B2B-1-8-view-b2b-subscription-capacity.md).
