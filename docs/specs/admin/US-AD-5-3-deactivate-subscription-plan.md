# US-AD-5-3: Deactivate a Subscription Plan

[← spec index](../README.md) · Area: Admin · **Status:** In progress · API: [admin-subscriptions](../../api/admin-subscriptions.md) (`deactivate_subscription` / `activate_subscription`)

As an admin, I want to deactivate a subscription plan, so that students cannot purchase it anymore.

## Flow
1. 🔧 Open subscription management
2. 🔧 Select an active subscription
3. 🔧 Select "Deactivate"
4. 🔧 Confirm the action
5. ⚙️ Remove it from the available subscriptions

## Notes
- Existing student subscriptions remain active until expiration.
- The admin can activate the subscription again later.
