# US-US-OF-1-2: Apply Offer Automatically

[← spec index](../README.md) · Area: Student · **Status:** Spec

As a user, I want to get the offer automatically during checkout, so that I receive the discounted price.

## Flow
1. 🎓 Select an item (course / package / subscription)
2. 🎓 Open checkout
3. ⚙️ Validate:
   - The offer is active
   - Within the date range
   - Applies to this specific item
4. ⚙️ Calculate the discount:
   - Apply the percentage or fixed value
   - Ensure the final price ≥ 0
5. ⚙️ Display the final price
6. 🎓 Complete the payment

## Notes
- The offer is applied automatically.
- If multiple offers exist, only one is applied (system rule).
- The offer works for multi-course targeting (only selected courses get the discount).
