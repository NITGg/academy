# Academy Platform — Auto-Recording & Mobile API Context

## Project Overview

**Platform**: Moodle LMS + Jitsi Meet + Jibri + BunnyStream CDN + Excalidraw whiteboard  
**Domain**: https://academy2026.nitg-eg.com  
**Server**: Single Linux VPS running everything via Docker Compose  
**Repos**:
- Academy (Moodle): `/var/www/html/academy` → GitHub: `NITGg/academy`
- BunnyStream API: `/var/www/html/bunnyStream` → separate repo

---

## Docker Containers

| Container | Image | Role |
|-----------|-------|------|
| `academy_app` | custom moodle | Moodle PHP app |
| `academy_db` | mariadb:10.6 | Moodle DB (`academy2022_moodle`) |
| `academy_jitsi_web` | jitsi/web | Jitsi Meet web UI |
| `academy_prosody` | jitsi/prosody | XMPP server |
| `academy_jicofo` | jitsi/jicofo | Conference focus |
| `academy_jvb` | jitsi/jvb | Video bridge |
| `academy_jibri` | jitsi/jibri | Recording service |
| `bunny_demo_api` | custom node | BunnyStream API (port 3000→4000) |
| `bunny_demo_db` | postgres:16 | BunnyStream DB (`bunny_demo`) |
| `academy_excalidraw_app` | custom nginx | Excalidraw whiteboard |
| `academy_minio` | minio | Local recording storage |

**Important network note**: `academy_app` was manually connected to `bunnystream_default` network so it can reach `bunny_demo_api`. The BunnyStream API URL configured in Moodle is `http://172.17.0.1:3000` (host gateway, since container DNS still doesn't resolve cross-network reliably).

---

## Key Config Files

### Moodle (`academy_app`)
- Config: `/var/www/html/config.php` uses `getenv('MOODLE_WWWROOT')`
- Internal API calls spoof `$_SERVER['HTTP_HOST']` before loading config to avoid redirect
- Moodle DB credentials: `root` / `aafec26b749488e19d24d5f432987c05` / `academy2022_moodle`

### Jitsi `.env` (at `/var/www/html/academy/.env`)
```
ENABLE_AUTH=1
AUTH_TYPE=jwt
JWT_APP_ID=academy_jitsi
JWT_APP_SECRET=4e7bd83f195cfa6d9fbb165adb952a9d10f59f287a1e32f4d288aaafe02b1272
JWT_ACCEPTED_ISSUERS=academy_jitsi
JWT_ACCEPTED_AUDIENCES=academy_jitsi
XMPP_MUC_MODULES=token_affiliation,muc_auto_record
```

### Jitsi Web (`/var/www/html/academy/jitsi/web/custom-config.js`)
- Restricts toolbar buttons for all users (moderator role controls actual permissions via JWT)
- `config.toolbarButtons` set to remove recording/invite/security/breakout from UI

### BunnyStream API (`bunny_demo_api`)
- Internal key: `academy-internal-secret-2024`
- Moodle config: `local_academysessions / bunny_demo_url = http://172.17.0.1:3000`
- Moodle config: `local_academysessions / bunny_demo_key = academy-internal-secret-2024`

---

## Recording Pipeline

```
Jitsi session ends
  → Jibri finalize.sh
    → POST /api/internal/upload-intent (bunny_demo_api)
    → TUS upload to BunnyStream CDN
    → Bunny webhook fires when transcoded (READY)
      → webhook.ts → POST record_notify.php (Moodle)
        → UPDATE mdl_academy_session_recordings SET status='ready', bunny_video_id=...
          → Moodle view.php polling detects ready → shows player
```

### Key DB Table: `mdl_academy_session_recordings`
Columns: `id, cmid, title, status (syncing|ready), bunny_video_id, timecreated`

### Key Files
- `src/mod/jitsi/record_notify.php` — webhook receiver from BunnyStream
- `src/local/academysessions/` — JWT generation, Jitsi config
- `D:/NIT/bunnyStreamDemo/apps/api/src/routes/internal.ts` — BunnyStream internal API
- `D:/NIT/bunnyStreamDemo/apps/api/src/routes/webhook.ts` — Bunny CDN webhook handler

---

## Mobile API

### Main Endpoint
```
GET https://academy2026.nitg-eg.com/local/multitopics/getalltopics.php
    ?courseid={id}&wstoken={moodle_token}
```
File: `src/local/multitopics/getalltopics.php`

Returns full course structure:
```json
{
  "courseid": 54,
  "parents": [{
    "activities": [{
      "modname": "jitsi",
      "jitsi_session": {
        "server_url": "https://academy2026.nitg-eg.com:8443",
        "room": "academy_jitsi_{cmid}_{hash}",
        "jwt": "...",
        "whiteboard_url": "https://academy2026.nitg-eg.com/whiteboard/#room=...",
        "recordings": [{
          "status": "ready",
          "playback_url": "https://vz-....b-cdn.net/.../playlist.m3u8?token=...",
          "thumbnail_url": "https://vz-....b-cdn.net/.../thumbnail.jpg?token=...",
          "embed_url": "https://iframe.mediadelivery.net/embed/..."
        }],
        "feature_flags": {
          "recording.enabled": false,
          "invite.enabled": false,
          "security-options.enabled": false,
          "breakout-rooms.enabled": false,
          "kick-out.enabled": false,
          "screen-sharing.enabled": true,
          "chat.enabled": true
        }
      }
    }]
  }]
}
```

**Mobile usage**:
- `server_url` + `room` + `jwt` → native Jitsi SDK (NOT WebView)
- `whiteboard_url` → WebView
- `playback_url` (.m3u8) → native HLS video player
- `feature_flags` → pass directly to `JitsiMeetConferenceOptions.featureFlags`

### Jitsi JWT Structure
```json
{
  "context": {
    "user": { "name": "...", "email": "...", "moderator": true/false },
    "features": { "recording": false, "screen-sharing": true }
  }
}
```
- `moderator: true` → owner affiliation in room (can record, kick, mute all)
- `moderator: false` → member affiliation (student)

### Web Service (Moodle standard API)
- `src/mod/jitsi/db/services.php`
- `src/mod/jitsi/classes/external/get_session_info.php`
- Function: `mod_jitsi_get_session_info` (param: `cmid`)

---

## Auto-Recording (IN PROGRESS)

**Goal**: Automatically start Jibri recording when a teacher/moderator joins any Jitsi room.

**Approach**: Custom Prosody Lua module (`mod_muc_auto_record`) hooks `muc-occupant-joined`, detects moderator role, sends Jibri start IQ to Jicofo.

### Module file
```
Host path:      /var/www/html/academy/jitsi/prosody/prosody-plugins-custom/mod_muc_auto_record.lua
Container path: /prosody-plugins-custom/mod_muc_auto_record.lua (copied to /usr/lib/prosody/modules/ on start)
```

### Enabling the module
The module must be listed in the `muc.meet.jitsi` Component block inside:
```
/var/www/html/academy/jitsi/prosody/config/conf.d/jitsi-meet.cfg.lua
```

**Problem**: This file is regenerated when `docker compose up --force-recreate prosody` is run.  
**Solution**: Use `docker compose restart prosody` (NOT `--force-recreate`) to preserve the patched config.

**Current status**: Module file exists, config patch applied, testing in progress.

### What was tried and failed
- `config.autoRecord = true` in Jitsi web config — not a real feature
- `jicofo.conference.auto-record = true` in Jicofo config — not supported in stable-9823
- `XMPP_MUC_MODULES=token_affiliation,muc_auto_record` env var — only adds to `internal-muc`, not `muc.meet.jitsi`

---

## Security Notes

- PostgreSQL port **not** exposed publicly (ransomware incident — attacker wiped `bunny_demo` DB via exposed port 5432)
- Redis port **not** exposed publicly
- BunnyStream postgres password reset periodically — if API crashes, run:
  ```bash
  docker exec bunny_demo_db psql -U postgres -c "ALTER USER postgres WITH PASSWORD 'postgres';"
  ```
- Internal API protected by `X-Internal-Key` header

---

## Jicofo Config
File: `/var/www/html/academy/jitsi/jicofo/custom-jicofo.conf`
```hocon
jicofo {
  conference {
    enable-auto-owner = false
  }
}
```
`enable-auto-owner = false` prevents first-joiner from becoming moderator when no JWT moderator is present.

---

## Whiteboard (Excalidraw)

- Container: `academy_excalidraw_app` at `https://academy2026.nitg-eg.com/whiteboard`
- Iframe guard (`window.self !== window.top`) patched in SOURCE before Vite build
- Dockerfile: `D:/NIT/academy/excalidraw/Dockerfile`
- Room URL format: `{excalidraw_app}/#room=academy_wb_jitsi_{cmid}`
- **Status**: Iframe embedding still showing "I'm not a pretzel!" on some builds — needs `docker compose build --no-cache excalidraw-app && docker compose up -d excalidraw-app` after any Dockerfile change

---

## Common Commands

```bash
# Deploy academy changes
cd /var/www/html/academy && git checkout -- jitsi/jibri/logs/ && git pull

# Deploy bunnyStream changes
cd /var/www/html/bunnyStream && git pull origin main && docker compose up -d --build api

# Fix BunnyStream DB auth (if API crash-loops)
docker exec bunny_demo_db psql -U postgres -c "ALTER USER postgres WITH PASSWORD 'postgres';"

# Set Moodle config value
docker exec academy_app php /var/www/html/admin/cli/cfg.php \
  --component=local_academysessions --name=KEY --set=VALUE

# Purge Moodle cache
docker exec academy_app php /var/www/html/admin/cli/purge_caches.php

# Watch Jibri recording activity
docker logs academy_jibri -f --tail=20

# Watch Prosody for module errors
docker logs academy_prosody -f --tail=20

# Restart Prosody WITHOUT losing patched config
docker compose restart prosody   # NOT --force-recreate

# Query Moodle recordings table
docker exec academy_db mysql -u root -paafec26b749488e19d24d5f432987c05 academy2022_moodle \
  -e "SELECT id, status, bunny_video_id FROM mdl_academy_session_recordings ORDER BY id DESC LIMIT 10;"
```
