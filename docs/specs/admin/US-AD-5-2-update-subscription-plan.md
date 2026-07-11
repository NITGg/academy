# US-AD-5-2: Update a Subscription Plan

[← spec index](../README.md) · Area: Admin · **Status:** In progress · API: [admin-subscriptions](../../api/admin-subscriptions.md) (`update_subscription`)

As an admin, I want to update a subscription plan and its offer and B2B settings, so that I can control pricing, promotions, and B2B behavior.

## Flow
1. 🔧 Open subscription management
2. 🔧 Select a subscription

### Basic info update
3. 🔧 Update the name, price, duration, or description

### Offer update (optional)
4. 🔧 Enable / disable the offer
5. 🔧 Update the discount type, value, or dates

### B2B update
6. 🔧 Enable or disable B2B purchase
7. 🔧 If B2B is enabled: add new seat options, update existing seat options, or remove seat options
8. 🔧 Update the discount percentage for each seat option
9. ⚙️ Recalculate the B2B prices

### Save changes
10. 🔧 Save the changes
11. ⚙️ Update the subscription
12. ⚙️ Apply changes to future purchases

## Notes

### General rules
- Changes apply only to future purchases.
- Existing subscriptions are not affected.

### Offer rules
- The offer is optional.
- Only one offer per subscription.
- The offer applies only to normal purchase.
- The offer does **not** apply to B2B pricing.
- The offer is active only between its start and end date.
- The final price cannot be less than zero.

### B2B rules
- Updating the price affects future B2B purchases.
- Updating seat options affects only new purchases.
- Removing a seat option does not affect existing subscriptions.
- Disabling B2B prevents future B2B purchases only.
