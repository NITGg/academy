#!/bin/bash
# Jibri finalize script — uploads the finished MP4 to MinIO.
# Called by Jibri as: finalize.sh <recording_dir>

RECORDING_DIR="$1"

MINIO_ENDPOINT="${MINIO_ENDPOINT:-http://minio:9000}"
MINIO_ACCESS_KEY="${MINIO_ACCESS_KEY:-minioadmin}"
MINIO_SECRET_KEY="${MINIO_SECRET_KEY:-minioadmin123}"
MINIO_BUCKET="${MINIO_BUCKET:-academy-recordings}"
MOODLE_URL="${MOODLE_URL:-http://academy_app}"
MOODLE_CRON_KEY="${MOODLE_CRON_KEY:-}"   # optional — speeds up sync if set

log() { echo "[finalize] $*" >&2; }

if [ -z "$RECORDING_DIR" ] || [ ! -d "$RECORDING_DIR" ]; then
    log "ERROR: recording dir not found: '$RECORDING_DIR'"
    exit 1
fi

MP4_FILE=$(find "$RECORDING_DIR" -maxdepth 2 -name "*.mp4" | head -1)
if [ -z "$MP4_FILE" ]; then
    log "No MP4 found in $RECORDING_DIR"
    exit 0
fi

log "Found recording: $MP4_FILE"

DIRNAME=$(basename "$RECORDING_DIR")
FILENAME=$(basename "$MP4_FILE")
OBJECT_KEY="recordings/${DIRNAME}/${FILENAME}"

# ── Ensure bucket exists ─────────────────────────────────────────────────────
DATE=$(date -u "+%a, %d %b %Y %H:%M:%S GMT")
RESOURCE="/${MINIO_BUCKET}/"
# S3 V2 StringToSign: VERB\nContent-MD5\nContent-Type\nDate\nResource
SIG_STRING=$(printf "PUT\n\n\n%s\n%s" "$DATE" "$RESOURCE")
SIG=$(printf '%s' "$SIG_STRING" | openssl dgst -sha1 -hmac "$MINIO_SECRET_KEY" -binary | base64)
curl -sf -X PUT \
    -H "Date: ${DATE}" \
    -H "Authorization: AWS ${MINIO_ACCESS_KEY}:${SIG}" \
    "${MINIO_ENDPOINT}/${MINIO_BUCKET}/" >/dev/null 2>&1 || true

# ── Upload file ──────────────────────────────────────────────────────────────
log "Uploading to MinIO: ${MINIO_ENDPOINT}/${MINIO_BUCKET}/${OBJECT_KEY}"

CONTENT_TYPE="video/mp4"
DATE=$(date -u "+%a, %d %b %Y %H:%M:%S GMT")
RESOURCE="/${MINIO_BUCKET}/${OBJECT_KEY}"
# S3 V2 StringToSign: VERB\nContent-MD5\nContent-Type\nDate\nResource
SIG_STRING=$(printf "PUT\n\n%s\n%s\n%s" "$CONTENT_TYPE" "$DATE" "$RESOURCE")
SIG=$(printf '%s' "$SIG_STRING" | openssl dgst -sha1 -hmac "$MINIO_SECRET_KEY" -binary | base64)

HTTP_CODE=$(curl -s -o /tmp/minio_upload_response.txt -w "%{http_code}" -X PUT \
    -H "Date: ${DATE}" \
    -H "Content-Type: ${CONTENT_TYPE}" \
    -H "Authorization: AWS ${MINIO_ACCESS_KEY}:${SIG}" \
    --data-binary @"$MP4_FILE" \
    "${MINIO_ENDPOINT}/${MINIO_BUCKET}/${OBJECT_KEY}")

if [ "$HTTP_CODE" -ge 200 ] && [ "$HTTP_CODE" -lt 300 ]; then
    log "Upload successful (HTTP ${HTTP_CODE}). Removing local recording."
    rm -rf "$RECORDING_DIR"

    # Trigger Moodle's cron immediately so the sync_recordings task picks this
    # up right away instead of waiting for the next scheduled cron cycle.
    if [ -n "$MOODLE_CRON_KEY" ]; then
        log "Triggering Moodle cron..."
        curl -sf "${MOODLE_URL}/admin/cron.php?password=${MOODLE_CRON_KEY}" >/dev/null 2>&1 && \
            log "Moodle cron triggered." || log "Moodle cron trigger failed (non-fatal)."
    else
        log "MOODLE_CRON_KEY not set — Moodle cron will run on its normal schedule."
    fi
    exit 0
else
    RESPONSE=$(cat /tmp/minio_upload_response.txt 2>/dev/null)
    log "Upload FAILED (HTTP ${HTTP_CODE}): ${RESPONSE}"
    exit 1
fi
