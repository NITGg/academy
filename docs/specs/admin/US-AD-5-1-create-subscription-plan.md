# US-AD-5-1: Create a Subscription Plan

[← spec index](../README.md) · Area: Admin · **Status:** In progress · API: [admin-subscriptions](../../api/admin-subscriptions.md) (`create_subscription`)

As an admin, I want to create a subscription plan, so that students can purchase access to courses.

## Flow
1. 🔧 Open subscription management
2. 🔧 Select "Create Subscription"
3. 🔧 Enter the name, price, and number of days
4. 🔧 Add an optional description
5. 🔧 Activate the subscription
6. ⚙️ Display it to students

## Notes
- The price must be greater than or equal to zero.
- The number of days must be greater than zero.
- Only active subscriptions are available for purchase.

## Related
Course access is granted per subscription — see [US-AD-6-1](US-AD-6-1-set-course-subscription-availability.md). Student purchase in [US-SB-1-2](../student/US-SB-1-2-purchase-a-subscription.md).
