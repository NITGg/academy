# Wallet Model (reference, not a user story)

[← spec index](../README.md) · Area: Financial

## Teacher wallet (one per teacher)
- **Current Balance** — earnings available for withdrawal.
- **Total Withdrawn** — money already paid to the teacher.
- On withdrawal request, the amount is deducted from current balance; if rejected it returns; if paid it adds to total withdrawn.

## Platform wallet
All money currently held by the platform, divided into:
- **Current Money** — all actual money held by the platform.
- **Undistributed Package Money** — value of Flexes not consumed yet.
- **Teachers' Money** — earnings belonging to teachers but not yet paid.
- **Platform Earnings** — the platform's share from consumed Flexes.

## Worked example (Flex20 = 20 EGP, 1 Flex = 1 EGP, split 40/60)
| Event | Student Flex | Teacher balance | Platform current | Undistributed | Teachers' money | Platform earnings |
|-------|-------------|-----------------|------------------|---------------|-----------------|-------------------|
| After purchase | 20 | 0 | 20 | 20 | 0 | 0 |
| After 1 lesson completed | 19 | 0.40 | 20 | 19 | 0.40 | 0.60 |
| After teacher withdraws 0.40 (paid) | 19 | 0 (withdrawn 0.40) | 19.60 | 19 | 0 | 0.60 |

Platform current money decreases **only when money is actually paid out**.

## Main rules
- Teacher has one wallet. Platform holds the actual money until paid to the teacher.
- Teacher earnings appear in both the teacher wallet and the platform's teachers' money.
- Teacher money is **not** platform profit. Only consumed Flexes generate teacher & platform earnings.
- Paying a withdrawal reduces the platform's current money.
