# US-AD-5-2: Update a Subscription Plan

[← spec index](../README.md) · Area: Admin · **Status:** In progress · API: [admin-subscriptions](../../api/admin-subscriptions.md) (`update_subscription`)

As an admin, I want to update a subscription plan, so that I can change its information and B2B settings.

## Flow
1. 🔧 Open subscription management
2. 🔧 Select a subscription
3. 🔧 Update its name, normal price, duration, or description
4. 🔧 Enable or disable B2B purchase
5. 🔧 If B2B is enabled, manage the seat options (add, update, or remove)
6. 🔧 Update the separate discount percentage for each seat option
7. ⚙️ Recalculate the B2B price for each updated seat option
8. 🔧 Save the changes
9. ⚙️ Update the subscription and apply changes to future purchases

## Notes
- Changes apply only to **future** purchases.
- Existing **Normal** subscriptions keep their original price and expiration date.
- Existing **B2B** subscriptions keep their original purchase price, number of seats, discount
  percentage, activation date, and expiration date.
- Updating the normal price changes future Normal and B2B prices.
- Updating a seat option or its discount affects only future B2B purchases.
- Removing a seat option prevents new buyers from selecting it; existing B2B subscriptions that used a
  removed seat option remain valid.
- Disabling B2B purchase prevents future B2B purchases but does not affect active B2B subscriptions.
