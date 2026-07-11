# US-AD-5-1: Create a Subscription Plan

[← spec index](../README.md) · Area: Admin · **Status:** In progress · API: [admin-subscriptions](../../api/admin-subscriptions.md) (`create_subscription`)

As an admin, I want to create a subscription plan, so that users can purchase it normally or as a B2B subscription.

## Flow
1. 🔧 Open subscription management
2. 🔧 Select "Create Subscription"
3. 🔧 Enter the name, normal price, and number of days
4. 🔧 Add an optional description

### Offer settings (optional)
5. 🔧 Enable Offer
6. 🔧 Select the discount type (percentage / fixed)
7. 🔧 Enter the discount value
8. 🔧 Set the offer start date and end date

### B2B settings
9. 🔧 Choose whether B2B purchase is available
10. 🔧 If B2B is enabled, add seat options (e.g. 10, 20, 50 seats)
11. 🔧 Define a separate discount percentage for each seat option
12. ⚙️ Calculate the B2B price for each seat option

### Activation
13. 🔧 Activate the subscription
14. ⚙️ Calculate the final displayed prices
15. ⚙️ Display the subscription to users

## B2B price calculation
```
Original price  = Normal price × Number of seats
Discount amount = Original price × Discount % ÷ 100
B2B price       = Original price − Discount amount
```
**Example** — normal price 100, seat option 10 seats, discount 10%:
Original = 100 × 10 = 1,000 · Discount = 1,000 × 10% = 100 · **B2B price = 900**.
Each seat option has its own discount (e.g. 10 seats → 10%, 20 seats → 20%, 50 seats → 30%).

## Notes
- The normal price must be ≥ 0.
- The number of days must be > 0.
- The number of seats must be > 0.
- The discount percentage must be between 0% and 100%.
- Each seat option has its own discount.

### Offer rules
- The offer is optional.
- Only one offer per subscription.
- The offer applies only to normal purchase.
- The offer does **not** apply to B2B pricing.
- The offer is active only between its start and end date.
- The final price cannot be less than zero.

### General rules
- Only active subscriptions are available.
- A normal purchase keeps the user's role.
- A B2B purchase changes the role to **B2B Administrator**.
- Role changes only after successful payment.

## Related
Seat options / B2B settings are edited in [US-AD-5-2](US-AD-5-2-update-subscription-plan.md). B2B
purchase in [US-B2B-1-1](../B2B%20Administrator/US-B2B-1-1-purchase-a-b2b-subscription.md). Course
access per subscription in [US-AD-6-1](US-AD-6-1-set-course-subscription-availability.md).
