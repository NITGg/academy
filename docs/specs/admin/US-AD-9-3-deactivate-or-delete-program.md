# US-AD-9-3: Deactivate or Delete a Program

[← spec index](../README.md) · Area: Admin · **Status:** Spec

As an admin, I want to deactivate or delete a program, so that I can stop new purchases or remove unused programs from the platform.

## Flow
1. 🔧 Open program management
2. 🔧 Select a program
3. 🔧 Choose "Deactivate Program" or "Delete Program"

### If Deactivate Program is selected
4. ⚙️ Display a confirmation message
5. 🔧 Confirm the action
6. ⚙️ Change the program status to "Inactive"
7. ⚙️ Remove the program from the available programs list
8. ⚙️ Prevent new users from purchasing the program

### If Delete Program is selected
4. ⚙️ Check whether the program has ever been purchased
5. **If the program has never been purchased:**
   - ⚙️ Display a deletion confirmation message
   - 🔧 Confirm the deletion
   - ⚙️ Permanently delete the program
   - ⚙️ Remove the program from program management
6. **If the program has been purchased before:**
   - ⚙️ Prevent the deletion
   - ⚙️ Display a message that the program can only be deactivated

## Notes
- Deactivated programs cannot be purchased by new users.
- Existing purchased programs remain active.
- Users retain access to courses already granted through the program.
- A program can only be deleted if it has never been purchased.
- Programs with purchase records cannot be deleted and must be deactivated instead.
- Deletion is permanent and cannot be undone.
