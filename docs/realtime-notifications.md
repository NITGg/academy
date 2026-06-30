# Realtime notifications (Socket.IO)

Pushes "new notification" events to a logged-in user's browser so the navbar bell can **chime + auto-refresh** the instant a notification is sent — no 30s polling. Mirrors the existing Excalidraw whiteboard socket setup.

## How it works

```
message_send()  ─►  \core\event\notification_sent
                          │  (event observer)
                          ▼
   \local_academy\observer::notification_sent
                          │  realtime::emit()  → POST /emit  (x-internal-key)
                          ▼
              notify-ws  (Socket.IO relay)
                          │  emit "notification" to room "user:<id>"
                          ▼
   browser (authenticated socket)  ─►  chime + page reload
```

- **Relay:** `notify-socket/` — a small Node + Socket.IO server (container `academy_notify_ws`, port `3100`). No DB, no Moodle bootstrap.
- **Auth:** the page injects a short-lived HMAC token (`<userid>.<exp>.<sig>`) minted by `\local_academy\realtime::mint_token()`. The relay verifies the signature and joins the socket to a private room `user:<id>`. Forged tokens are disconnected immediately.
- **Server→relay:** `\local_academy\observer::notification_sent` calls `realtime::emit()`, a fire-and-forget POST to `/emit` (1s connect / 2s total timeout) authenticated by the shared `x-internal-key`. It can never delay or break message sending.
- **Client:** injected in `local_academy_before_footer()`. Connects the socket; on each `notification` event it plays one chime and debounces a single page reload. **If the socket can't connect, it automatically falls back to polling** `message_popup_get_unread_popup_notification_count` every 30s — notifications are never lost.

## Configuration

All optional. Local-docker defaults work out of the box; override in `src/config.php` for production.

| `$CFG->` setting | Default | Meaning |
|---|---|---|
| `academy_notify_enabled` | `true` | Master switch. `false` → client uses polling fallback only, observer stops emitting. |
| `academy_notify_secret` | `academy-internal-secret` | HMAC secret + internal `/emit` key. **Must match** the relay's `NOTIFY_SECRET` / `INTERNAL_KEY`. |
| `academy_notify_internalurl` | `http://notify-ws:3100` | Base URL Moodle POSTs to (docker network). |
| `academy_notify_publicurl` | `http://localhost:3100` | Base URL the browser connects to. |
| `academy_notify_path` | `/socket.io` | Socket.IO path. **Must match** the relay's `SOCKET_PATH`. |

Relay env vars (in `docker-compose.yml` → `notify-ws`): `PORT`, `NOTIFY_SECRET`, `INTERNAL_KEY`, `SOCKET_PATH`, `ALLOWED_ORIGIN`.

## Production deployment (academy2026)

1. **Build & run the relay** (already a compose service):
   ```
   docker compose up -d --build notify-ws
   ```

2. **Reverse-proxy a WebSocket path** to it — exactly like `/whiteboard-ws`. Example nginx:
   ```nginx
   location /notify-ws/ {
       proxy_pass http://127.0.0.1:3100;
       proxy_http_version 1.1;
       proxy_set_header Upgrade $http_upgrade;
       proxy_set_header Connection "upgrade";
       proxy_set_header Host $host;
   }
   ```

3. **Set a matching Socket.IO path** so the proxied prefix is preserved. In `docker-compose.yml` set `NOTIFY_SOCKET_PATH=/notify-ws/socket.io`, and in `src/config.php`:
   ```php
   $CFG->academy_notify_publicurl = 'https://academy2026.nitg-eg.com';
   $CFG->academy_notify_path      = '/notify-ws/socket.io';
   $CFG->academy_notify_secret    = '<a long random secret>';   // also set NOTIFY_SECRET/INTERNAL_KEY to this
   ```
   (`academy_notify_internalurl` stays `http://notify-ws:3100`.)

4. **Set a strong shared secret** via env (`NOTIFY_SECRET` / `INTERNAL_API_KEY`) and `$CFG->academy_notify_secret` — don't ship the dev default.

5. **Restrict CORS** in prod: set `NOTIFY_ALLOWED_ORIGIN=https://academy2026.nitg-eg.com` instead of `*`.

6. **Purge Moodle caches** after deploying the plugin changes (the event observer is cached):
   ```
   docker exec academy_app php admin/cli/upgrade.php --non-interactive
   docker exec academy_app php admin/cli/purge_caches.php
   ```

> **CSP note:** because the relay is served same-origin under `/notify-ws`, a `script-src 'self'` / `connect-src 'self'` policy already allows it. CSP is currently Report-Only, so nothing is blocked today. If you switch `local/csp` to enforcing, keep the relay same-origin (it is) or add the origin to `script-src` and `connect-src`.

## Notes & limits

- The chime needs a prior user interaction on the page (browser autoplay policy). The reload happens regardless.
- The mobile app is unaffected by this (it uses push/FCM — see `docs/api/mobile-notifications-guide.md`). This is for the web UI.
- Verified end-to-end in local docker: a real `message_send` notification reached a connected client (`RECEIVED {"id":...}`), and a forged token was rejected.
