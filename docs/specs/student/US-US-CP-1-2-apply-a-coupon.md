# US-US-CP-1-2: Apply a Coupon

[← spec index](../README.md) · Area: Student · **Status:** Spec

As a user, I want to apply a coupon during checkout, so that I get a discount.

## Flow
1. 🎓 Select an item (course / package / subscription)
2. 🎓 Open checkout
3. 🎓 Enter the coupon code
4. ⚙️ Validate:
   - The coupon is active
   - Within the date range
   - Within the usage limits
   - Matches the item type (course / package / subscription)
   - Matches the target scope (all or selected items)
5. ⚙️ Calculate the discount:
   - Apply the percentage or fixed value
   - Apply the max discount limit
6. ⚙️ Display the final price
7. 🎓 Complete the payment
8. ⚙️ Record the coupon usage

## Example
```
Price          = 200 EGP
Discount       = 50% → 100 EGP
Max discount   = 50 EGP
→ Final discount = 50 EGP
```

## Notes
- The coupon must match both the item type and the target scope.
- A coupon cannot be used beyond its usage limit.
- The discount is applied before payment.
