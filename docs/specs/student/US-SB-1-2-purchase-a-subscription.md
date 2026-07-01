# US-SB-1-2: Purchase a Subscription

[← spec index](../README.md) · Area: Student (subscriptions) · **Status:** In progress · API: [subscriptions-mobile-guide](../../api/subscriptions-mobile-guide.md) (`purchase_subscription`)

As a student, I want to purchase a subscription, so that I can access its available courses.

## Flow
1. 🎓 Select a subscription
2. 🎓 Select "Buy Subscription"
3. ⚙️ Display the payment screen
4. 🎓 Complete the payment
5. ⚙️ Confirm that the payment is successful
6. ⚙️ Activate the subscription
7. ⚙️ Calculate the expiration date
8. ⚙️ Give the student access to eligible courses
9. ⚙️ Add the subscription price to the platform wallet
10. ⚙️ Record the full subscription price as platform revenue
11. ⚙️ Record the payment and financial transaction
12. 🎓 Receive a purchase confirmation

## Notes
- The subscription is activated only after successful payment.
- The full subscription price belongs to the platform.
- Subscription revenue is not divided between the platform and teachers.
- Failed or cancelled payments do not activate the subscription or add platform revenue.
- The expiration date is calculated from the activation date and number of days.
- An expired subscription cannot be used to access courses.

## Subscription status model
`Pending Payment` → (`Payment Failed` | `Cancelled`) | `Active` → `Expired`.
