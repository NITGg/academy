# US-AD-7-1: Create a Coupon

[← spec index](../README.md) · Area: Admin · **Status:** Spec

As an admin, I want to create a coupon, so that users can get discounts on specific items.

## Flow
1. 🔧 Open coupon management
2. 🔧 Select "Create Coupon"
3. 🔧 Enter the coupon code
4. 🔧 Select the discount type (percentage / fixed)
5. 🔧 Enter the discount value
6. 🔧 Set the max discount amount
7. 🔧 Select the applicable types: Courses, Packages, Subscriptions, or any combination
8. 🔧 Select the target scope:
   - All courses or selected courses
   - All packages or selected packages
   - All subscriptions or selected subscriptions
9. 🔧 Set the start date and end date
10. 🔧 Set the usage type (one-time / multiple use)
11. 🔧 Activate the coupon
12. ⚙️ Save the coupon

## Notes
- The coupon code must be unique.
- The discount cannot exceed the item price.
- The max discount limits the applied discount.
- A coupon can target one or multiple item types.
- A coupon can apply to all or selected items.
- A coupon is valid only between its start and end date.

## Related
Users view coupons in [US-US-CP-1-1](../student/US-US-CP-1-1-view-available-coupons.md) and apply
them in [US-US-CP-1-2](../student/US-US-CP-1-2-apply-a-coupon.md).
