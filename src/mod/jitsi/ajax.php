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

$function = required_param('function', PARAM_ALPHANUMEXT);

// ── end_session ────────────────────────────────────────────────────────────
if ($function === 'end_session') {
    $sessionid = required_param('sessionid', PARAM_INT);
    $session   = $DB->get_record('academy_live_sessions', ['id' => $sessionid], '*', MUST_EXIST);

    // Require mod/jitsi:moderate on the linked jitsi activity.
    $jitsi = $DB->get_record('jitsi', ['id' => $session->jitsiid]);
    if ($jitsi) {
        $cm      = get_coursemodule_from_instance('jitsi', $jitsi->id, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        require_capability('mod/jitsi:moderate', $context);
    } else {
        // Fallback: course-level teacher check.
        $context = context_course::instance($session->courseid);
        require_capability('moodle/course:manageactivities', $context);
    }

    \local_academysessions\session_manager::end_session($sessionid);

    echo json_encode(['status' => 'success']);
    exit;
}

// ── end_room ───────────────────────────────────────────────────────────────
// Locks a standalone Jitsi activity (no linked academy session) so no one
// can rejoin after the teacher ends it.
if ($function === 'end_room') {
    $cmid = required_param('cmid', PARAM_INT);
    $cm   = get_coursemodule_from_id('jitsi', $cmid, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    require_capability('mod/jitsi:moderate', $context);

    set_config('ended_' . $cm->id, time(), 'mod_jitsi');

    echo json_encode(['status' => 'success']);
    exit;
}

// ── get_session_recordings ─────────────────────────────────────────────────
if ($function === 'get_session_recordings') {
    $sessionid = optional_param('sessionid', 0, PARAM_INT);
    $cmid      = optional_param('cmid', 0, PARAM_INT);

    if ($sessionid) {
        $session = $DB->get_record('academy_live_sessions', ['id' => $sessionid], '*', MUST_EXIST);
        $context = context_course::instance($session->courseid);
    } elseif ($cmid) {
        $cm_rec  = get_coursemodule_from_id('jitsi', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm_rec->id);
        $session = null;
    } else {
        echo json_encode(['status' => 'fail', 'error' => 'Missing sessionid or cmid']);
        exit;
    }

    $isteacher = has_capability('moodle/course:manageactivities', $context)
              || has_capability('local/academysessions:managesessions', $context);

    if (!$isteacher && $sessionid) {
        $attended = $DB->record_exists('academy_session_attendance', [
            'sessionid' => $sessionid,
            'userid'    => $USER->id,
        ]);
        if (!$attended) {
            echo json_encode(['status' => 'fail', 'error' => 'Access denied']);
            exit;
        }
    }

    if ($sessionid) {
        $rows = $DB->get_records('academy_session_recordings', ['sessionid' => $sessionid], 'timecreated DESC');
        // Also include standalone recordings for the same cmid.
        if ($cmid) {
            $extra = $DB->get_records_sql(
                "SELECT * FROM {academy_session_recordings}
                 WHERE cmid = :cmid AND (sessionid IS NULL OR sessionid = 0)
                 ORDER BY timecreated DESC",
                ['cmid' => $cmid]
            );
            $rows = array_merge($rows, $extra);
        }
    } else {
        $rows = $DB->get_records_sql(
            "SELECT * FROM {academy_session_recordings}
             WHERE cmid = :cmid ORDER BY timecreated DESC",
            ['cmid' => $cmid]
        );
    }
    $out  = [];

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
                $bunny             = new \local_academysessions\bunny_client();
                $item['embed_url'] = $bunny->get_embed_url($rec->bunny_video_id, time() + 7200);
            } catch (Exception $e) {
                // embed_url stays null
            }
        } elseif (!empty($rec->bunny_video_url)) {
            $item['embed_url'] = $rec->bunny_video_url;
        }

        if ($isteacher) {
            $minio_endpoint = get_config('local_academysessions', 'minio_endpoint') ?: 'http://minio:9000';
            $minio_bucket   = get_config('local_academysessions', 'minio_bucket')   ?: 'academy-recordings';
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

// ── check_recording_status ────────────────────────────────────────────────────
// Polls the Bunny demo API for current transcoding status of a single recording.
// Updates the Moodle DB record and returns embed_url once READY.
if ($function === 'check_recording_status') {
    $rec_id = required_param('rec_id', PARAM_INT);
    $rec    = $DB->get_record('academy_session_recordings', ['id' => $rec_id], '*', MUST_EXIST);

    // Access check: must be enrolled/attending or a teacher.
    $can_view = false;
    if ($rec->sessionid) {
        $sess = $DB->get_record('academy_live_sessions', ['id' => $rec->sessionid]);
        if ($sess) {
            $ctx       = context_course::instance($sess->courseid);
            $can_view  = has_capability('moodle/course:manageactivities', $ctx)
                      || $DB->record_exists('academy_session_attendance', ['sessionid' => $rec->sessionid, 'userid' => $USER->id]);
        }
    } elseif ($rec->cmid) {
        $cm_rec   = get_coursemodule_from_id('jitsi', $rec->cmid, 0, false, MUST_EXIST);
        $ctx      = context_module::instance($cm_rec->id);
        $can_view = has_capability('mod/jitsi:view', $ctx);
    }
    if (!$can_view) {
        echo json_encode(['status' => 'fail', 'error' => 'Access denied']);
        exit;
    }

    // Already ready — just return embed URL.
    if ($rec->status === 'ready') {
        $embed_url = null;
        if (!empty($rec->bunny_video_url)) {
            $embed_url = $rec->bunny_video_url;
        } elseif (!empty($rec->bunny_video_id)) {
            try {
                $bunny     = new \local_academysessions\bunny_client();
                $embed_url = $bunny->get_embed_url($rec->bunny_video_id, time() + 7200);
            } catch (Exception $e) {}
        }
        echo json_encode(['status' => 'ready', 'embed_url' => $embed_url]);
        exit;
    }

    // Poll the Bunny demo API.
    if (!empty($rec->bunny_video_id)) {
        $demo_url = get_config('local_academysessions', 'bunny_demo_url') ?: 'http://host.docker.internal:3000';
        $demo_key = get_config('local_academysessions', 'bunny_demo_key') ?: 'academy-internal-secret-2024';
        $ch = curl_init($demo_url . '/api/internal/videos/' . urlencode($rec->bunny_video_id) . '/embed');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['X-Internal-Key: ' . $demo_key],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        $resp = json_decode($body, true);

        if ($resp && isset($resp['status']) && strtolower($resp['status']) === 'ready') {
            $upd                  = new stdClass();
            $upd->id              = $rec->id;
            $upd->status          = 'ready';
            $upd->bunny_video_url = $resp['embedUrl'] ?? null;
            $upd->expires_at      = !empty($resp['expiresAt']) ? strtotime($resp['expiresAt']) : time() + 86400 * 30;
            $upd->timemodified    = time();
            $DB->update_record('academy_session_recordings', $upd);

            echo json_encode(['status' => 'ready', 'embed_url' => $resp['embedUrl'] ?? null]);
            exit;
        }

        // Still processing — return current bunny status for label display.
        $bunny_status = $resp['status'] ?? 'PROCESSING';
        echo json_encode(['status' => 'syncing', 'bunny_status' => $bunny_status]);
        exit;
    }

    echo json_encode(['status' => $rec->status]);
    exit;
}

echo json_encode(['status' => 'fail', 'error' => 'Unknown function']);
