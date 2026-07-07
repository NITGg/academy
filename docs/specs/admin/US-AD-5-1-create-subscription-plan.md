# US-AD-5-1: Create a Subscription Plan

[← spec index](../README.md) · Area: Admin · **Status:** In progress · API: [admin-subscriptions](../../api/admin-subscriptions.md) (`create_subscription`)

As an admin, I want to create a subscription plan, so that users can purchase it normally or as a B2B subscription.

## Flow
1. 🔧 Open subscription management
2. 🔧 Select "Create Subscription"
3. 🔧 Enter the name, normal price, and number of days
4. 🔧 Add an optional description
5. 🔧 Choose whether B2B purchase is available
6. 🔧 If B2B is available, add one or more **seat options** (e.g. 10, 20, 50 seats)
7. 🔧 Define a separate **discount percentage** for each seat option
8. ⚙️ Calculate the B2B price for each seat option
9. 🔧 Activate the subscription
10. ⚙️ Display the subscription to users

## B2B price calculation
```
Original price = Normal price × Number of seats
Discount amount = Original price × Discount % ÷ 100
B2B price       = Original price − Discount amount
```
**Example** — normal price 100, seat option 10 seats, discount 10%:
Original = 100 × 10 = 1,000 · Discount = 1,000 × 10% = 100 · **B2B price = 900**.
Each seat option has its own discount (e.g. 10 seats → 10%, 20 seats → 20%, 50 seats → 30%).

## Notes
- The normal price must be ≥ 0. The number of days must be > 0.
- Each seat option: number of seats must be > 0; discount % must be between 0 and 100.
- Each seat option has its own discount percentage.
- Only active subscriptions are available for purchase.
- A **Normal** purchase keeps the buyer's role.
- A successful **B2B** purchase changes the buyer's role to **B2B Administrator** — only after
  successful payment and activation.

## Related
Seat options / B2B settings are edited in [US-AD-5-2](US-AD-5-2-update-subscription-plan.md). B2B
purchase in [US-B2B-1-1](../B2B%20Administrator/US-B2B-1-1-purchase-a-b2b-subscription.md). Course
access per subscription in [US-AD-6-1](US-AD-6-1-set-course-subscription-availability.md).
