# US-AD-6-1: Set Course Subscription Availability

[← spec index](../README.md) · Area: Admin · **Status:** In progress · API: [admin-subscriptions](../../api/admin-subscriptions.md) (`set_course_subscriptions`)

As an admin, I want to select which subscriptions can access a course, so that only eligible students can view its content.

## Flow
1. 🔧 Open course management
2. 🔧 Select a course
3. 🔧 Open subscription access settings
4. 🔧 Select all subscriptions or specific subscriptions
5. 🔧 Save the changes
6. ⚙️ Apply the new course access rules

## Example
- Arabic course → Available with all subscriptions
- English course → Available only with the 365-day subscription

## Notes
- The student must have an active, unexpired subscription.
- The student's subscription must match one of the course subscriptions.
- Course access changes apply immediately.

## Related
Subscription plans are created in [US-AD-5-1](US-AD-5-1-create-subscription-plan.md).
