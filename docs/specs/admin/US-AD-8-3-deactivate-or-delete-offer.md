# US-AD-8-3: Deactivate or Delete an Offer

[← spec index](../README.md) · Area: Admin · **Status:** Spec

As an admin, I want to deactivate or delete an offer, so that I can stop applying the offer or remove unused offers from the platform.

## Flow
1. 🔧 Open offer management
2. 🔧 Select an offer
3. 🔧 Choose "Deactivate" or "Delete"

### If Deactivate is selected
4. ⚙️ Display a confirmation message
5. 🔧 Confirm the action
6. ⚙️ Change the offer status to "Inactive"
7. ⚙️ Prevent the offer from being applied to future purchases

### If Delete is selected
4. ⚙️ Check whether the offer has ever been used
5. **If the offer has never been used:**
   - ⚙️ Display a deletion confirmation message
   - 🔧 Confirm the deletion
   - ⚙️ Permanently delete the offer
   - ⚙️ Remove the offer from offer management
6. **If the offer has been used:**
   - ⚙️ Prevent the deletion
   - ⚙️ Display a message that the offer can only be deactivated

## Notes
- Deactivated offers are not applied at checkout.
- Existing purchases that used the offer remain unchanged.
- An offer can only be deleted if it has never been used.
- Offers with usage records cannot be deleted.
- Used offers must be deactivated instead.
- Deletion is permanent and cannot be undone.
