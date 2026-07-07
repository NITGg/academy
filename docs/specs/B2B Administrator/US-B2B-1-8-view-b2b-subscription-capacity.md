# US-B2B-1-8: View B2B Subscription Capacity

[← spec index](../README.md) · Area: B2B Administrator · **Status:** Spec

As a B2B administrator, I want to view my subscription capacity and members, so that I know how many additional users I can approve.

## Flow
1. 🏢 Open the B2B subscription dashboard
2. ⚙️ Display: purchased capacity · consumed seats · available seats · pending / approved / rejected /
   removed users · removed users whose seats were returned · removed users whose seats remain consumed ·
   subscription expiration date

## Capacity calculation
```
Consumed Seats  = number of membership records where consumes_seat = true
Available Seats = Purchased capacity − Consumed Seats
```
### Seat-consumption rules
- Approved → `status = Approved`, `consumes_seat = true`.
- Removed while seat-return **enabled** → `status = Removed`, `consumes_seat = false`.
- Removed while seat-return **disabled** → `status = Removed`, `consumes_seat = true`.
- Pending / rejected → `consumes_seat = false`.

### Mixed-policy example
Capacity 10 · 5 approved · 2 removed with seat-return enabled · 1 removed with seat-return disabled →
Consumed = 5 + 1 = 6 · Available = 10 − 6 = 4. (The 2 seat-returned removals don't consume seats.)

## Notes
- The current global seat-return setting must **not** be used to recalculate historical capacity — store
  the `consumes_seat` result on each membership record; changing the setting affects future removals
  only, and previously removed users keep their recorded value.
- A removed user may still consume a seat even though they no longer have access.
- Consumed seats must never exceed the purchased capacity.
- Approving the same membership twice must not consume multiple seats; removing the same user multiple
  times must not change the seat count multiple times.
- The dashboard must distinguish **membership status**, **access status**, and **seat-consumption status**.
