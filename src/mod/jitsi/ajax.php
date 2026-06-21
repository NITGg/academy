<?php
/**
 * AJAX handler for mod_jitsi — called from the browser during/after a session.
 * Uses Moodle session auth + sesskey (no external token needed).
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');
require_login();

header('Content-Type: application/json');

require_sesskey();

$function  = required_param('function', PARAM_ALPHANUMEXT);

// ── end_session ────────────────────────────────────────────────────────────
if ($function === 'end_session') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $session   = $DB->get_record('academy_live_sessions', ['id' => $sessionid], '*', MUST_EXIST);

    $context = context_course::instance($session->courseid);
    require_capability('local/academysessions:managesessions', $context);

    $upd               = new stdClass();
    $upd->id           = $sessionid;
    $upd->status       = 'ended';
    $upd->timemodified = time();
    $DB->update_record('academy_live_sessions', $upd);

    echo json_encode(['status' => 'success']);
    exit;
}

// ── get_session_recordings ─────────────────────────────────────────────────
if ($function === 'get_session_recordings') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $session   = $DB->get_record('academy_live_sessions', ['id' => $sessionid], '*', MUST_EXIST);

    // Students must have attended; teachers always see recordings.
    $context   = context_course::instance($session->courseid);
    $isteacher = has_capability('local/academysessions:managesessions', $context);

    if (!$isteacher) {
        $attended = $DB->record_exists('academy_session_attendance', [
            'sessionid' => $sessionid,
            'userid'    => $USER->id,
        ]);
        if (!$attended) {
            echo json_encode(['status' => 'fail', 'error' => 'Access denied']);
            exit;
        }
    }

    $rows = $DB->get_records('academy_session_recordings', ['sessionid' => $sessionid], 'timecreated DESC');
    $out  = [];

    $minio_endpoint = get_config('local_academysessions', 'minio_endpoint') ?: 'http://minio:9000';
    $minio_bucket   = get_config('local_academysessions', 'minio_bucket')   ?: 'academy-recordings';

    foreach ($rows as $rec) {
        $item = [
            'id'        => (int)$rec->id,
            'title'     => $rec->title ?: 'Recording',
            'status'    => $rec->status,
            'embed_url' => null,
            'storage'   => null,
        ];

        if ($rec->status === 'ready' && !empty($rec->bunny_video_id)) {
            try {
                $bunny          = new \local_academysessions\bunny_client();
                $item['embed_url'] = $bunny->get_embed_url($rec->bunny_video_id, time() + 7200);
            } catch (Exception $e) {
                // embed_url stays null
            }
        } elseif (!empty($rec->bunny_video_url)) {
            $item['embed_url'] = $rec->bunny_video_url;
        }

        if ($isteacher) {
            $storage = $minio_endpoint . '/' . $minio_bucket;
            if (!empty($rec->bunny_video_id)) {
                $storage .= ' → Bunny ID: ' . $rec->bunny_video_id;
            }
            $item['storage'] = $storage;
        }

        $out[] = $item;
    }

    echo json_encode(['status' => 'success', 'data' => $out]);
    exit;
}

echo json_encode(['status' => 'fail', 'error' => 'Unknown function']);
