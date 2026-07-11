# US-AD-7-3: Deactivate or Delete a Coupon

[← spec index](../README.md) · Area: Admin · **Status:** Spec

As an admin, I want to deactivate or delete a coupon, so that I can stop coupon usage or remove unused coupons from the platform.

## Flow
1. 🔧 Open coupon management
2. 🔧 Select a coupon
3. 🔧 Choose "Deactivate" or "Delete"

### If Deactivate is selected
4. ⚙️ Display a confirmation message
5. 🔧 Confirm the action
6. ⚙️ Change the coupon status to "Inactive"
7. ⚙️ Prevent the coupon from being used in future purchases

### If Delete is selected
4. ⚙️ Check whether the coupon has ever been used
5. **If the coupon has never been used:**
   - ⚙️ Display a deletion confirmation message
   - 🔧 Confirm the deletion
   - ⚙️ Permanently delete the coupon
   - ⚙️ Remove the coupon from coupon management
6. **If the coupon has been used:**
   - ⚙️ Prevent the deletion
   - ⚙️ Display a message that the coupon can only be deactivated

## Notes
- Deactivated coupons cannot be used in future purchases.
- A coupon can only be deleted if it has never been used.
- Coupons with usage records cannot be deleted.
- Used coupons must be deactivated instead.
- Deletion is permanent and cannot be undone.
