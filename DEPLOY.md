# Production Deployment Guide

## Architecture Decision: Full Docker

Run everything in Docker even though the server has other native PHP projects.
This avoids conflicts with the existing Apache/PHP on the server — each project stays isolated.
The native Apache on port 80/443 handles other projects; your Docker containers run on separate ports
and a reverse proxy (or direct domain + port) routes traffic to them.

---

## What to Push to GitHub

**Push these (already tracked):**
- `Dockerfile`
- `docker-compose.yml`
- `excalidraw/`
- `jitsi/` (scripts only — configs are gitignored and auto-generated)
- `src/` — all Moodle source + custom plugins

**Never push (gitignored):**
- `.env` — create this manually on the server
- `moodleData/` — transfer separately with rsync/scp
- `jitsi/web/keys/` — SSL certs go here on the server
- Jitsi auto-generated configs (`jicofo.conf`, `jvb.conf`, `prosody/`, `web/nginx/`, `web/config.js`)
- Database — export/import separately via phpMyAdmin

**Before pushing — update `src/config.php`** to read from environment variables
so you don't have to edit the file on the server every time:

```php
<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('DB_HOST') ?: 'academy_db';
$CFG->dbname    = getenv('DB_NAME') ?: 'academy2022_moodle';
$CFG->dbuser    = getenv('DB_USER') ?: 'root';
$CFG->dbpass    = getenv('DB_PASS') ?: 'root';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array(
  'dbpersist'   => 0,
  'dbport'      => 3306,
  'dbsocket'    => '',
  'dbcollation' => 'utf8mb4_general_ci',
);

$CFG->wwwroot  = getenv('MOODLE_WWWROOT') ?: 'http://localhost:8081';
$CFG->dataroot = '/var/www/moodledata';
$CFG->admin    = 'admin';

$CFG->directorypermissions = 0777;
$CFG->cachejs          = true;
$CFG->langstringcache  = true;
$CFG->cachetemplates   = true;
$CFG->themedesignermode = false;
$CFG->localcachedir    = '/var/www/moodledata/localcache';

if (getenv('MOODLE_SSLPROXY')) {
    $CFG->sslproxy    = true;
    $CFG->cookiesecure = true;
}

@ini_set('session.auto_start', '0');

if (isset($_COOKIE["userdata"])) {
    if ($_COOKIE["userdata"] == 14) {
        $CFG->logouturl  = "$CFG->wwwroot/login/index.php?id=" . $_COOKIE['userdata'] . "&lang=ar";
        $CFG->logouturl2 = "$CFG->wwwroot/?id=" . $_COOKIE['userdata'] . "&lang=ar";
        $CFG->signup     = "$CFG->wwwroot/login/signup.php?id=" . $_COOKIE['userdata'] . "&lang=ar";
    } else {
        $CFG->logouturl  = "$CFG->wwwroot/login/index.php?id=" . $_COOKIE['userdata'];
        $CFG->logouturl2 = "$CFG->wwwroot/?id=" . $_COOKIE['userdata'];
        $CFG->signup     = "$CFG->wwwroot/login/signup.php?id=" . $_COOKIE['userdata'] . "&lang=ar";
    }
    $CFG->userId = $_COOKIE['userdata'];
} else {
    $CFG->logouturl = "$CFG->wwwroot/login/index.php";
}

require_once(__DIR__ . '/lib/setup.php');
```

Also add the env vars to the `app` service in `docker-compose.yml`:

```yaml
  app:
    build: .
    container_name: academy_app
    ports:
      - "8081:80"
    volumes:
      - ./src:/var/www/html
      - ./moodleData:/var/www/moodledata
    environment:
      - MOODLE_WWWROOT
      - DB_HOST
      - DB_NAME
      - DB_USER
      - DB_PASS
      - MOODLE_SSLPROXY
    depends_on:
      - db
    entrypoint: >
      sh -c "chown -R www-data:www-data /var/www/moodledata &&
             chmod -R 755 /var/www/moodledata &&
             docker-php-entrypoint apache2-foreground"
```

Then commit everything:

```bash
git add src/config.php docker-compose.yml
git commit -m "Read config from env vars for production deploy"
git push origin master
```

---

## Server Setup (when you get root access)

### Step 1 — Install Docker

```bash
sudo apt update
sudo apt install -y docker.io docker-compose-plugin git

# Let your user run docker without sudo
sudo usermod -aG docker $USER
newgrp docker

# Verify
docker --version
docker compose version
```

### Step 2 — Clone the Repo

```bash
git clone https://github.com/YOUR_ORG/YOUR_REPO.git /opt/academy
cd /opt/academy
```

### Step 3 — Create `.env` on the Server

```bash
nano /opt/academy/.env
```

Paste this and fill in your real values:

```env
# ── Moodle ─────────────────────────────
MOODLE_WWWROOT=https://yourdomain.com
DB_HOST=academy_db
DB_NAME=academy2022_moodle
DB_USER=academy_user
DB_PASS=CHANGE_THIS_STRONG_PASSWORD
MOODLE_SSLPROXY=1

# ── Jitsi ───────────────────────────────
CONFIG=./jitsi
HTTP_PORT=8080
HTTPS_PORT=8443
TZ=Africa/Cairo
PUBLIC_URL=https://meet.yourdomain.com

JICOFO_AUTH_PASSWORD=CHANGE_RANDOM_32_CHARS
JVB_AUTH_PASSWORD=CHANGE_RANDOM_32_CHARS
JIBRI_RECORDER_PASSWORD=CHANGE_RANDOM_32_CHARS
JIBRI_XMPP_PASSWORD=CHANGE_RANDOM_32_CHARS

DISABLE_HTTPS=0
ENABLE_HTTP_REDIRECT=1
ENABLE_AUTH=1
ENABLE_GUESTS=1
AUTH_TYPE=jwt
JWT_APP_ID=academy_jitsi
JWT_APP_SECRET=CHANGE_RANDOM_SECRET
JWT_ACCEPTED_ISSUERS=academy_jitsi
JWT_ACCEPTED_AUDIENCES=academy_jitsi
ENABLE_RECORDING=1
ENABLE_BREAKOUT_ROOMS=1
XMPP_MUC_MODULES=token_affiliation

# ── MinIO ───────────────────────────────
MINIO_ROOT_USER=minioadmin
MINIO_ROOT_PASSWORD=CHANGE_THIS_STRONG_PASSWORD
```

Generate random secrets:
```bash
openssl rand -hex 16    # for passwords
openssl rand -hex 32    # for JWT_APP_SECRET
```

### Step 4 — Get SSL Certificate

```bash
sudo apt install -y certbot

# Stop anything on port 80 temporarily (native Apache)
sudo systemctl stop apache2

sudo certbot certonly --standalone -d yourdomain.com

# Restart native Apache
sudo systemctl start apache2
```

Copy certs for Jitsi:
```bash
mkdir -p /opt/academy/jitsi/web/keys
cp /etc/letsencrypt/live/yourdomain.com/fullchain.pem /opt/academy/jitsi/web/keys/cert.crt
cp /etc/letsencrypt/live/yourdomain.com/privkey.pem   /opt/academy/jitsi/web/keys/cert.key
```

### Step 5 — Import the Database

On your **local machine**, export:
```bash
docker exec academy_db mysqldump -uroot -proot academy2022_moodle > academy_backup.sql
scp academy_backup.sql user@yourserver:/opt/academy/
```

On the **server**, import via phpMyAdmin (already installed):
1. Open phpMyAdmin
2. Create database `academy2022_moodle` with collation `utf8mb4_unicode_ci`
3. Select that database → **Import** → upload `academy_backup.sql`

Then fix the URLs inside the database (SQL tab in phpMyAdmin):
```sql
UPDATE mdl_config SET value='https://yourdomain.com' WHERE name='wwwroot';
UPDATE mdl_config SET value='1' WHERE name='cookiesecure';
```

Also update Jitsi plugin settings stored in DB:
```sql
UPDATE mdl_config_plugins
SET value='yourdomain.com:8443'
WHERE plugin='local_academysessions' AND name='jitsi_host';
```

### Step 6 — Transfer User Files

```bash
# From your local machine
rsync -avz --exclude='sessions/' --exclude='cache/' --exclude='localcache/' \
  ./moodleData/ user@yourserver:/opt/academy/moodleData/
```

### Step 7 — Start the Containers

```bash
cd /opt/academy
docker compose up -d --build
```

Check everything is running:
```bash
docker compose ps
docker logs academy_app --tail=30
```

### Step 8 — Set Up Domain Routing

The server's native Apache is already on port 80. Add a virtual host to proxy your domain to the Docker Moodle container on port 8081:

```bash
sudo nano /etc/apache2/sites-available/academy.conf
```

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    Redirect permanent / https://yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com

    SSLEngine on
    SSLCertificateFile     /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile  /etc/letsencrypt/live/yourdomain.com/privkey.pem

    ProxyPreserveHost On
    ProxyPass        / http://127.0.0.1:8081/
    ProxyPassReverse / http://127.0.0.1:8081/

    RequestHeader set X-Forwarded-Proto "https"
</VirtualHost>
```

```bash
sudo a2enmod proxy proxy_http ssl headers
sudo a2ensite academy
sudo systemctl reload apache2
```

### Step 9 — Set Up Cron

```bash
crontab -e
```

Add:
```
* * * * * docker exec academy_app php /var/www/html/admin/cli/cron.php >/dev/null 2>&1
```

### Step 10 — Purge Caches After Everything is Up

```bash
docker exec academy_app php /var/www/html/admin/cli/purge_caches.php
```

---

## Port Reference

| Service | Port | Notes |
|---|---|---|
| Moodle | 8081 | Proxied via Apache → yourdomain.com |
| Jitsi HTTP | 8080 | Redirect to HTTPS |
| Jitsi HTTPS | 8443 | meet.yourdomain.com |
| Jitsi media | 10000/udp | Must be open in firewall |
| MinIO API | 9000 | Internal only or subdomain |
| MinIO console | 9001 | Internal only |
| Excalidraw | 9091 | Optional subdomain |

Open firewall ports:
```bash
sudo ufw allow 80
sudo ufw allow 443
sudo ufw allow 8443
sudo ufw allow 10000/udp
```

---

## Environment Variables Reference

| Variable | Example value | Purpose |
|---|---|---|
| `MOODLE_WWWROOT` | `https://yourdomain.com` | Full public URL of Moodle |
| `DB_HOST` | `academy_db` | DB container name (keep as-is) |
| `DB_NAME` | `academy2022_moodle` | Database name |
| `DB_USER` | `academy_user` | DB username |
| `DB_PASS` | *(strong password)* | DB password |
| `MOODLE_SSLPROXY` | `1` | Tells Moodle it's behind HTTPS proxy |
| `PUBLIC_URL` | `https://meet.yourdomain.com` | Jitsi public URL |
| `JWT_APP_SECRET` | *(32-char random)* | Shared between Moodle + Jitsi |
| `JICOFO_AUTH_PASSWORD` | *(random)* | Internal Jitsi XMPP |
| `JVB_AUTH_PASSWORD` | *(random)* | Internal Jitsi XMPP |
| `JIBRI_RECORDER_PASSWORD` | *(random)* | Internal Jitsi XMPP |
| `JIBRI_XMPP_PASSWORD` | *(random)* | Internal Jitsi XMPP |
| `MINIO_ROOT_PASSWORD` | *(strong password)* | MinIO admin |

> `JWT_APP_SECRET` must match the value saved in Moodle admin:
> **Site administration → Plugins → Local plugins → Academy Sessions → JWT secret**

---

## Quick Checklist

- [ ] `src/config.php` reads from env vars (done before push)
- [ ] `docker-compose.yml` passes env vars to `app` container (done before push)
- [ ] Pushed to GitHub
- [ ] Docker installed on server
- [ ] `.env` created on server with real domain + strong passwords
- [ ] SSL cert obtained and copied to `jitsi/web/keys/`
- [ ] Database imported via phpMyAdmin + URLs updated in SQL
- [ ] `moodleData/` synced to server
- [ ] Containers started with `docker compose up -d --build`
- [ ] Apache reverse proxy configured for yourdomain.com → port 8081
- [ ] Firewall ports open: 80, 443, 8443, 10000/udp
- [ ] Cron job added
- [ ] Caches purged
