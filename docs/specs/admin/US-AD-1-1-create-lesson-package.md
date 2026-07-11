# US-AD-1-1: Create a Lesson Package

[← spec index](../README.md) · Area: Admin · **Status:** In progress · API: [admin-packages](../../api/admin-packages.md) (`create_package`)

As an admin, I want to create a lesson package with an optional offer, so that students can purchase Flexes with promotional pricing.

## Flow
1. 🔧 Open package management
2. 🔧 Tap "Create Package"
3. 🔧 Enter the package name
4. 🔧 Add a package description
5. 🔧 Enter the number of Flexes
6. 🔧 Enter the price

### Offer settings (optional)
7. 🔧 Enable Offer
8. 🔧 Select the discount type (percentage / fixed)
9. 🔧 Enter the discount value
10. 🔧 Set the offer start date
11. 🔧 Set the offer end date
12. 🔧 Set the expiration period
13. 🔧 Activate the package
14. ⚙️ Calculate the discounted price
15. ⚙️ Display the package to students

## Notes
- The offer is optional.
- Only one offer per package.
- The offer is applied automatically during its active period.
- The final price cannot be less than zero.
- Deactivating a package does not affect packages already purchased.
- The offer applies only to future purchases.
