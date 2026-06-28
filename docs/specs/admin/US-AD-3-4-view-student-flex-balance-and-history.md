# US-AD-3-4: View Student Flex Balance and History

[← spec index](../README.md) · Area: Admin · **Status:** Spec

As an admin, I want to view a student's Flex balance and transaction history, so that I can review every change to the balance.

## Display
Available Flexes · reserved Flexes · consumed Flexes · expired Flexes · active package · package expiration date.

## Transaction history (per row)
Date · type · Flex amount · balance before · balance after · related package · related lesson · performed by · reason/notes.

## Transaction types
Package purchase · package assigned by admin · Flex reserved · Flex consumed · Flex returned · Flex expired · admin adjustment.

## Actions
Filter · view details · export CSV.

## Notes
- Every Flex balance change creates a transaction record.
- Admin adjusts balance only through an authorized action; system records which admin performed it.
