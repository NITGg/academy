# US-US-OF-1-1: View Available Offers

[← spec index](../README.md) · Area: Student · **Status:** Spec

As a user, I want to view available offers, so that I can know which items have discounts.

## Flow
1. 🎓 Open courses / packages / subscriptions
2. ⚙️ Display items with active offers
3. 🎓 View item details

## Display
- Item name (course / package / subscription)
- Original price
- Discount type
- Discount value
- Discounted price
- Offer valid dates

## Notes
- Offers are applied automatically.
- Offers may apply to: one course, multiple courses, one package, or one subscription.
- Only items included in the offer scope show discounts.

## Related
Offers are created by an admin in [US-AD-8-1](../admin/US-AD-8-1-create-offer.md). Applied at checkout
in [US-US-OF-1-2](US-US-OF-1-2-apply-offer-automatically.md).
