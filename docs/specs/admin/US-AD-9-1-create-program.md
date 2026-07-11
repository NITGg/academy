# US-AD-9-1: Create a Program

[← spec index](../README.md) · Area: Admin · **Status:** Spec

As an admin, I want to create a program, so that users can purchase a group of courses together.

## Flow
1. 🔧 Open program management
2. 🔧 Select "Create Program"
3. 🔧 Enter the program name
4. 🔧 Add a program description
5. 🔧 Select one or more courses
6. 🔧 Enter the program price

### Offer settings (optional)
7. 🔧 Enable Offer
8. 🔧 Select the discount type (percentage / fixed)
9. 🔧 Enter the discount value
10. 🔧 Set the max discount amount
11. 🔧 Set the offer start date
12. 🔧 Set the offer end date

### Activation
13. 🔧 Activate the program
14. ⚙️ Calculate the discounted price (with max cap)
15. ⚙️ Display the program to users

## Notes
- A program must contain at least one course.
- Purchasing a program gives access to all included courses.
- The offer is optional.
- Only one offer per program.
- The max discount limits the applied discount.
- The final price cannot be less than zero.
- The offer applies only during its active period.
- The offer applies only to future purchases.

## Related
A certificate can be assigned to a program in
[US-AD-10-2](US-AD-10-2-assign-certificate-to-program.md).
