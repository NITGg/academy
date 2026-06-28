# US-AD-1-3: Deactivate a Lesson Package

[← spec index](../README.md) · Area: Admin · **Status:** Built · API: [admin-packages](../../api/admin-packages.md) (`deactivate_package` / `activate_package`)

As an admin, I want to deactivate a lesson package, so that students cannot purchase it anymore.

## Flow
1. 🔧 Open package management → select an active package
2. 🔧 Tap "Deactivate Package"
3. ⚙️ Show confirmation → 🔧 confirm
4. ⚙️ Set status to "Inactive"
5. ⚙️ Remove from available packages list
6. ⚙️ Prevent new purchases

## Notes
- Deactivating does not delete the package.
- Already-purchased packages stay active until fully used or expired; existing Flex balances unaffected.
- Admin can reactivate later.
