# US-B2B-1-2: Generate a B2B Invitation Link

[← spec index](../README.md) · Area: B2B Administrator · **Status:** Spec

As a B2B administrator, I want to generate an invitation link, so that users can join my B2B subscription.

## Flow
1. 🏢 Open the active B2B subscription
2. 🏢 Select "Generate Invitation Link"
3. ⚙️ Check that the subscription is active
4. ⚙️ Generate a unique invitation link
5. ⚙️ Link the invitation to the B2B administrator, the B2B subscription, and the invitation configuration
6. 🏢 Copy and share the invitation link

## Notes
- The link must not directly grant subscription access.
- The link identifies the related B2B administrator and subscription.
- The link may have an expiration date and may be active, expired, disabled, or revoked.
- The administrator may generate a new link or revoke an existing one.
- Possessing the link does not guarantee approval or access.

## Related
Join through the link: [US-B2B-1-3](US-B2B-1-3-join-through-a-b2b-invitation-link.md).
