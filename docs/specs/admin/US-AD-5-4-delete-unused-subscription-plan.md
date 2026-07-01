# US-AD-5-4: Delete an Unused Subscription Plan

[← spec index](../README.md) · Area: Admin · **Status:** In progress · API: [admin-subscriptions](../../api/admin-subscriptions.md) (`delete_subscription`)

As an admin, I want to delete an unused subscription plan, so that I can remove unnecessary plans.

## Flow
1. 🔧 Open subscription management
2. 🔧 Select a subscription
3. 🔧 Select "Delete"
4. ⚙️ Check its purchase and payment history
5. 🔧 Confirm the deletion
6. ⚙️ Permanently delete the subscription

## Notes
- A subscription can only be deleted if it has never been purchased.
- A subscription with payment or usage records can only be deactivated.
- Deletion cannot be undone.
