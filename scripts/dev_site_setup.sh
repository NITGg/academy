#!/usr/bin/env bash
#
# Build the development copy of the EAAC site at https://academy2026.nitg-eg.com/eaac
# from the client site (eaac-learning.com) on the same server.
#
# Run once, as root, from a fresh clone of NITGg/academy (branch moodle-new-version):
#
#   git clone -b moodle-new-version https://github.com/NITGg/academy.git /var/www/html/eaac-dev
#   bash /var/www/html/eaac-dev/scripts/dev_site_setup.sh
#
# The copy is made safe before it can run a single cron job: no e-mail, no mobile
# push and no real payment keys. VdoCipher is left to the site's own settings
# (Site administration > Plugins > Local plugins > VdoCipher): the copy starts
# with the client's key, so point it at a development account there.

set -euo pipefail

DEV=/var/www/html/eaac-dev
PROD=/var/www/html/moodle-new-version
WWWROOT=https://academy2026.nitg-eg.com/eaac
PORT=8083
VHOST=/etc/apache2/sites-available/academy2026.nitg-eg.com-le-ssl.conf

cd "$DEV"

if [ ! -f "$VHOST" ] || ! grep -q "# Moodle — must be LAST" "$VHOST"; then
    echo "$VHOST is not the file this script expects; nothing was changed." >&2
    exit 1
fi

if [ -f config.php ]; then
    echo "config.php already exists in $DEV — this script only builds a new copy." >&2
    exit 1
fi

echo "== 1/7 Secrets"
ROOTPASS=$(openssl rand -hex 16)
DBPASS=$(openssl rand -hex 16)
WHISPER_TOKEN=$(openssl rand -hex 24)
cat > .env <<EOF
DEV_DB_ROOT_PASSWORD=$ROOTPASS
DEV_DB_PASSWORD=$DBPASS
WHISPER_TOKEN=$WHISPER_TOKEN
EOF
chmod 600 .env

echo "== 2/7 Container configuration"
# Same image as the client site (moodle-new:latest); only the path it answers on differs.
cat > 000-default.conf <<'EOF'
<VirtualHost *:80>
    DocumentRoot /var/www/html/public
    Alias /eaac /var/www/html/public

    <Directory /var/www/html/public>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

cat > docker-compose.yml <<EOF
name: eaac-dev

services:
  db:
    image: mariadb:11.4
    container_name: eaac_dev_db
    restart: unless-stopped
    command: >
      --character-set-server=utf8mb4
      --collation-server=utf8mb4_unicode_ci
      --innodb-file-per-table=1
      --skip-log-bin
    environment:
      MARIADB_ROOT_PASSWORD: \${DEV_DB_ROOT_PASSWORD}
      MARIADB_DATABASE: moodle
      MARIADB_USER: moodle
      MARIADB_PASSWORD: \${DEV_DB_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
    networks:
      - eaac_dev_network
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 10s
      timeout: 5s
      retries: 10

  moodle:
    image: moodle-new:latest
    container_name: eaac_dev_app
    restart: unless-stopped
    depends_on:
      db:
        condition: service_healthy
    ports:
      - "127.0.0.1:$PORT:80"
    volumes:
      - ./:/var/www/html
      - ./moodledata:/var/www/moodledata
      - ./000-default.conf:/etc/apache2/sites-available/000-default.conf:ro
    networks:
      - eaac_dev_network

  cron:
    image: moodle-new:latest
    container_name: eaac_dev_cron
    restart: unless-stopped
    depends_on:
      - moodle
    volumes:
      - ./:/var/www/html
      - ./moodledata:/var/www/moodledata
    entrypoint: ["bash", "-c", "while true; do php /var/www/html/admin/cli/cron.php; sleep 60; done"]
    networks:
      - eaac_dev_network

  # Automatic transcripts (local_nit_ai). See services/whisper/README.md.
  whisper:
    build: ./services/whisper
    container_name: eaac_dev_whisper
    restart: unless-stopped
    cpus: 2
    mem_limit: 4g
    environment:
      WHISPER_TOKEN: \${WHISPER_TOKEN}
      WHISPER_THREADS: "2"
    volumes:
      - whisper_models:/models
      - whisper_data:/data
    networks:
      - eaac_dev_network

volumes:
  db_data:
  whisper_models:
  whisper_data:

networks:
  eaac_dev_network:
    driver: bridge
EOF

echo "== 3/7 Uploaded files (moodledata) and plugins kept out of git"
cp -a "$PROD/moodledata" ./moodledata
# filter_multilang2 is git-ignored, so a clone lacks it and every {mlang} shows raw.
cp -a "$PROD/public/filter/multilang2" ./public/filter/
rm -rf moodledata/cache/* moodledata/localcache/* moodledata/sessions/* moodledata/temp/* moodledata/lock/* 2>/dev/null || true

echo "== 4/7 Database copy"
docker compose up -d db
until [ "$(docker inspect -f '{{.State.Health.Status}}' eaac_dev_db)" = "healthy" ]; do sleep 3; done
docker exec moodle_db_new sh -c 'exec mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction --routines moodle' \
    | docker exec -i eaac_dev_db sh -c 'exec mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" moodle'

echo "== 5/7 config.php"
# The client's config with three changes: its own database, its own address,
# and the safety switches — written before the setup.php line so they win.
cp "$PROD/config.php" config.php
sed -i -E \
    -e "s/'moodle_db_new'/'eaac_dev_db'/" \
    -e "s#^(\\\$CFG->wwwroot\s*=\s*)'[^']*'#\1'$WWWROOT'#" \
    -e "s/^(\\\$CFG->dbuser\s*=\s*)'[^']*'/\1'moodle'/" \
    -e "s/^(\\\$CFG->dbpass\s*=\s*)'[^']*'/\1'$DBPASS'/" \
    config.php
sed -i "/require_once(__DIR__ . '\/lib\/setup.php');/i \\
// ===== Development copy: nothing here may reach real people or real money. =====\\
\$CFG->noemailever = true;                 // No e-mail at all.\\
\$CFG->airnotifieraccesskey = '';          // No push notifications to the mobile app.\\
\$CFG->forced_plugin_settings = [\\
    'paymentprovider_kashier' => ['api_key' => '', 'secret_key' => ''],\\
    'paymentprovider_fawaterk' => ['vendor_key' => '', 'provider_key' => '', 'client_secret' => ''],\\
    'local_nit_ai' => ['stt_url' => 'http://whisper:9000', 'stt_token' => '$WHISPER_TOKEN'],\\
];\\
" config.php

grep -q "eaac_dev_db" config.php && grep -q "$WWWROOT" config.php && grep -q "noemailever" config.php \
    || { echo "config.php was not rewritten as expected — check it before going on." >&2; exit 1; }

echo "== 6/7 Start the site and upgrade it to the development code"
docker compose up -d --build
docker exec eaac_dev_app php admin/cli/upgrade.php --non-interactive
docker exec eaac_dev_app php admin/cli/purge_caches.php

echo "== 7/7 Apache: serve $WWWROOT"
if ! grep -q "127.0.0.1:$PORT/eaac/" "$VHOST"; then
    cp "$VHOST" "$VHOST.bak-eaacdev"
    sed -i "/# Moodle — must be LAST/i \\
    # EAAC development copy (NITGg/academy moodle-new-version), containers eaac_dev_*\\
    RewriteRule ^/eaac\$ /eaac/ [R=301,L]\\
    ProxyPass        /eaac/ http://127.0.0.1:$PORT/eaac/\\
    ProxyPassReverse /eaac/ http://127.0.0.1:$PORT/eaac/\\
" "$VHOST"
    if ! apache2ctl configtest; then
        cp "$VHOST.bak-eaacdev" "$VHOST"
        echo "Apache rejected the change; the original file is back. Nothing else was touched." >&2
        exit 1
    fi
    systemctl reload apache2
fi

echo
echo "Done: $WWWROOT"
echo "Check: curl -sI $WWWROOT/login/index.php | head -1"
