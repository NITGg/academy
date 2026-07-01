# US-SB-2-1: View My Subscriptions and Payment History

[← spec index](../README.md) · Area: Student (subscriptions) · **Status:** In progress · API: [subscriptions-mobile-guide](../../api/subscriptions-mobile-guide.md) (`get_my_subscriptions` / `get_subscription_payment_history`)

As a student, I want to view my subscriptions and payment history, so that I can track my course access and previous payments.

## Flow
1. 🎓 Open "My Subscriptions"
2. ⚙️ Display the active subscription
3. ⚙️ Display previous subscriptions
4. 🎓 Open payment history
5. ⚙️ Display previous payment transactions

## Subscription info (per subscription)
Subscription name · activation date · expiration date · remaining days · subscription status · available courses.

## Payment info (per payment)
Subscription name · payment amount · payment date · payment method · payment status · transaction number.

## Notes
- The student can only view their own subscriptions and payments.
- The active subscription appears first.
- Expired subscriptions remain in history.
- Failed payments do not activate a subscription.
- Payment records remain available after expiration.
