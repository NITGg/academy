# Notifications — Mobile Integration Guide

This platform is Moodle-based, so notifications use Moodle's **standard message/notification
web services** over the **official mobile service** token your app already uses. There are no
custom Academy notification endpoints — Academy events flow into the same system.

## 0. Basics

- **REST endpoint:** `https://academy2026.nitg-eg.com/webservice/rest/server.php`
- **Every call needs:** `wstoken=<token>`, `wsfunction=<name>`, `moodlewsrestformat=json`
- **Token:** the existing official-mobile-service token (the same one the app already uses).
  All functions below are enabled on that service and AJAX-callable.
- `useridto: 0` means "the current (token) user" in all functions below.

---

## 1. ⭐ Mark notification as read

### Single notification — `core_message_mark_notification_read`

| Param | Type | Notes |
|---|---|---|
| `notificationid` | int | id of the notification (from the list call in §2) |
| `timeread` | int | optional unix timestamp; `0` = now |

Returns: `{ notificationid, warnings[] }`

### All notifications — `core_message_mark_all_notifications_as_read`

| Param | Type | Notes |
|---|---|---|
| `useridto` | int | `0` for current user |
| `useridfrom` | int | optional, `0` = from anyone |
| `timecreatedto` | int | optional, `0` = all |

Returns: `true`

Example:
```
POST /webservice/rest/server.php
  ?wstoken=TOKEN&moodlewsrestformat=json
  &wsfunction=core_message_mark_notification_read
  &notificationid=12345&timeread=0
```

---

## 2. Fetch notifications + unread count

### List — `message_popup_get_popup_notifications`

| Param | Type | Notes |
|---|---|---|
| `useridto` | int | `0` for current user |
| `newestfirst` | bool | `true` |
| `limit` | int | e.g. `20` (`0` = all) |
| `offset` | int | paging |

Each item returns: `id`, `subject`, `shortenedsubject`, `text`, `fullmessage`,
`fullmessageformat`, `contexturl` (deep-link to open in app), `timecreated`, `read`, plus sender
info. Use `id` for the mark-read call; use `read` to render read/unread state.

### Unread badge count — `message_popup_get_unread_popup_notification_count`

| Param | Type |
|---|---|
| `useridto` | int (`0` = current) |

Returns: an integer.

---

## 3. Push notifications setup (delivery when the app is closed)

Moodle pushes via the **airnotifier** message processor (installed on this server). Flow:

1. **Register the device** on login — `core_user_add_user_device`:
   `appid`, `name`, `model`, `platform` (`iOS`/`Android`), `version`,
   `pushid` (FCM/APNs token), `uuid`.
2. **Unregister** on logout / token refresh — `core_user_remove_user_device`.
3. Pushes are then delivered automatically for any notification type the user has enabled.

> **Server-side prerequisite (admin, not app):** mobile push must be configured —
> *Site admin → Messaging → Mobile notifications* enabled, with a working airnotifier endpoint
> and FCM/APNs keys. The processor is installed in this repo, but whether the keys / airnotifier
> URL are configured on `academy2026` must be confirmed with the admin. Without it, device
> registration succeeds but no pushes arrive.

---

## 4. Notification preferences (optional — settings screen)

- `core_message_get_user_notification_preferences` — lists each notification type and its enabled
  channels (popup / email / mobile), so the app can render a preferences screen.

---

## 5. Where these notifications come from

Academy events (lesson booked / accepted / rescheduled, etc.) are sent through Moodle's standard
`message_send()` with the **popup** (in-app) and **email** processors — see
`src/local/academy/classes/notification_manager.php`. They therefore appear automatically in all
the functions above; the mobile app needs no Academy-specific notification endpoint.

---

## TL;DR

1. Auth with the existing mobile token.
2. Poll `message_popup_get_popup_notifications` + `message_popup_get_unread_popup_notification_count`.
3. Mark read with **`core_message_mark_notification_read`** (or `core_message_mark_all_notifications_as_read`).
4. For background push, register the device with `core_user_add_user_device` (and verify
   airnotifier / FCM is configured server-side).
