# US-AD-1-4: Delete an Unused Lesson Package

[← spec index](../README.md) · Area: Admin · **Status:** Built · API: [admin-packages](../../api/admin-packages.md) (`delete_package`)

As an admin, I want to delete a package that has never been purchased, so that I can remove unnecessary packages.

## Flow
1. 🔧 Open package management → select a package → tap "Delete Package"
2. ⚙️ Check whether the package has ever been purchased/used
3. ⚙️ Confirm it was never purchased → show deletion confirmation
4. 🔧 Confirm deletion
5. ⚙️ Permanently delete and remove from management

## Notes
- Deletable only if no student ever purchased it (no payment/purchase/usage records).
- Otherwise admin can only **deactivate**. Deletion is permanent and irreversible.
