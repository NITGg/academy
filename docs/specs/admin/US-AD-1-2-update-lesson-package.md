# US-AD-1-2: Update a Lesson Package

[← spec index](../README.md) · Area: Admin · **Status:** In progress · API: [admin-packages](../../api/admin-packages.md) (`update_package`)

As an admin, I want to update a lesson package and its offer, so that I can manage pricing and promotions.

## Flow
1. 🔧 Open package management
2. 🔧 Select a package
3. 🔧 Update the package information

### Offer update
4. 🔧 Enable / disable the offer
5. 🔧 Update the discount value or dates
6. 🔧 Save the changes
7. ⚙️ Update the package and recalculate the price

## Notes
- Changes apply only to future purchases.
- Existing purchases keep their original price.
- Offer changes do not affect active packages.
