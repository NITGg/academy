# US-AD-8-1: Create an Offer

[← spec index](../README.md) · Area: Admin · **Status:** Spec

As an admin, I want to create an offer, so that users can get automatic discounts on specific items.

## Flow
1. 🔧 Open offer management
2. 🔧 Select "Create Offer"
3. 🔧 Enter the offer name
4. 🔧 Select the discount type (percentage / fixed)
5. 🔧 Enter the discount value
6. 🔧 Select the applicable types: Courses, Packages, Subscriptions, or any combination
7. 🔧 Select the target scope:
   - All courses or selected courses
   - All packages or selected packages
   - All subscriptions or selected subscriptions
8. 🔧 Set the start date and end date
9. 🔧 Activate the offer
10. ⚙️ Save the offer

## Notes
- The offer does not require a code.
- Multiple active offers on the same item **stack**: their discounts are summed on the base price (e.g. two 30% offers give 60% off). The combined discount is clamped so the final price never goes below zero.
- The offer is applied automatically during its valid period.
- An offer can target one or multiple items.
- An offer is valid only between its start and end date.

## Related
Users see and receive offers in [US-US-OF-1-1](../student/US-US-OF-1-1-view-available-offers.md) and
[US-US-OF-1-2](../student/US-US-OF-1-2-apply-offer-automatically.md).
