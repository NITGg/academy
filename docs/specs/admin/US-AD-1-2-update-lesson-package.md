# US-AD-1-2: Update a Lesson Package

[← spec index](../README.md) · Area: Admin · **Status:** Built · API: [admin-packages](../../api/admin-packages.md) (`update_package`)

As an admin, I want to update a lesson package, so that I can change its price, Flex count, or expiration period.

## Flow
1. 🔧 Open package management → select a package
2. 🔧 Update the package information → save
3. ⚙️ Validate the new information
4. ⚙️ Update the package
5. ⚙️ Display updated information to students

## Notes
- Changes normally apply to **future purchases**.
- Existing purchased packages keep their original purchase conditions.
