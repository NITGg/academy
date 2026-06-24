<?php
/**
 * Server-to-server notification: called by finalize.sh after a Bunny TUS upload
 * completes. Creates (or updates) the academy_session_recordings record so
 * view.php can show the polling spinner immediately.
 *
 * Auth: X-Notify-Key header (or ?key= param) matching the configured notify key.
 * No Moodle session needed — this is a background process call.
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');

header('Content-Type: application/json');

// ── Auth ──────────────────────────────────────────────────────────────────────
$notify_key     = get_config('local_academysessions', 'jibri_notify_key') ?: 'academy-cron-2024';
$provided_key   = $_SERVER['HTTP_X_NOTIFY_KEY'] ?? optional_param('key', '', PARAM_RAW_TRIMMED);
if (!hash_equals($notify_key, $provided_key)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Params ────────────────────────────────────────────────────────────────────
$bunny_video_id = required_param('bunny_video_id', PARAM_TEXT);
$status         = optional_param('status',         'syncing', PARAM_ALPHA);
$title          = optional_param('title',          '',        PARAM_TEXT);
$cmid           = optional_param('cmid',           0,         PARAM_INT);
$sessionid      = optional_param('sessionid',      0,         PARAM_INT);

// Status-only update from Bunny webhook (transcoding finished).
if ($status === 'ready') {
    $existing = $DB->get_record('academy_session_recordings', ['bunny_video_id' => $bunny_video_id]);
    if ($existing) {
        $DB->update_record('academy_session_recordings', (object)[
            'id' => $existing->id, 'status' => 'ready', 'timemodified' => time(),
        ]);
        echo json_encode(['success' => true, 'id' => $existing->id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'not found']);
    }
    exit;
}

if (!$title) {
    http_response_code(400);
    echo json_encode(['error' => 'title required']);
    exit;
}

// If cmid is provided but no explicit sessionid, try to resolve session from activity.
if ($cmid && !$sessionid) {
    $jitsi = $DB->get_record_sql(
        "SELECT j.id FROM {jitsi} j
         JOIN {course_modules} cm ON cm.instance = j.id AND cm.module = (
             SELECT id FROM {modules} WHERE name = 'jitsi'
         )
         WHERE cm.id = :cmid",
        ['cmid' => $cmid]
    );
    if ($jitsi) {
        $sess = $DB->get_record('academy_live_sessions', ['jitsiid' => $jitsi->id]);
        if ($sess) {
            $sessionid = (int)$sess->id;
        }
    }
}

// ── Upsert recording record ───────────────────────────────────────────────────
$existing = $bunny_video_id
    ? $DB->get_record('academy_session_recordings', ['bunny_video_id' => $bunny_video_id])
    : null;

if ($existing) {
    $upd               = new stdClass();
    $upd->id           = $existing->id;
    $upd->status       = 'syncing';
    $upd->title        = $title;
    $upd->timemodified = time();
    if ($cmid)      $upd->cmid      = $cmid;
    if ($sessionid) $upd->sessionid = $sessionid;
    $DB->update_record('academy_session_recordings', $upd);
    $rec_id = $existing->id;
} else {
    $rec               = new stdClass();
    $rec->title        = $title;
    $rec->bunny_video_id = $bunny_video_id;
    $rec->status       = 'syncing';
    $rec->cmid         = $cmid ?: null;
    $rec->sessionid    = $sessionid ?: null;
    $rec->timecreated  = time();
    $rec->timemodified = time();
    $rec_id = $DB->insert_record('academy_session_recordings', $rec);
}

echo json_encode(['success' => true, 'id' => $rec_id]);
