<?php
/**
 * VdoCipher token-authenticated JSON API (teacher CRUD).
 *
 * Protocol mirrors local_academy:
 *   GET|POST /local/vdocipher/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...}
 *   → {"status":"fail","error":"<translated>","errorcode":"<stable code>"}
 *
 * The token is validated by \local_academy\token_auth (expiry, IP, service
 * enabled, account state) and $USER is set to its owner. All CRUD functions
 * additionally require local/vdocipher:manage in the relevant course context.
 *
 * A dead token is answered with HTTP 401 — see vdocipher_fail() below.
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
ob_start();

/**
 * Emit a JSON envelope and stop, dropping any stray output first.
 *
 * @param mixed $payload the envelope to encode
 * @param int $http the HTTP status to send with it
 */
function vdocipher_respond($payload, int $http = 200) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($http);
    echo json_encode($payload);
    exit;
}

/**
 * Emit a failure and stop.
 *
 * The 401 is the part that matters beyond tidiness. A password change or a block
 * deletes every one of the user's web-service tokens
 * (\local_academy\session_terminator), and this endpoint is one of the places a
 * device with a dead token will next call. Answering 401 is what lets the app's
 * generic handler end the session and return to the login screen, exactly as it
 * does for the errorcode:"invalidtoken" envelope from Moodle's own WS endpoint.
 * Anything that is not a credentials problem stays 200.
 *
 * The `errorcode` is new here and matches the vocabulary local_academy's api.php
 * already uses, so one table of codes covers both endpoints; `error` stays the
 * translated sentence for a person to read.
 *
 * @param string $errorcode stable machine-readable code
 * @param string $message translated, ready to show
 * @param int $http HTTP status; 401 only when the caller's credentials are dead
 */
function vdocipher_fail(string $errorcode, string $message, int $http = 200) {
    vdocipher_respond(['status' => 'fail', 'error' => $message, 'errorcode' => $errorcode], $http);
}

/**
 * Reject non-POST requests for state-changing calls.
 */
function vdocipher_require_post() {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        vdocipher_fail('postrequired', get_string('err_postrequired', 'local_academy'));
    }
}

$function = optional_param('function', '', PARAM_ALPHANUMEXT);
$token    = optional_param('token', '', PARAM_ALPHANUM);

// ── Authenticate via web-service token (sets $USER to the token's user) ──
if (empty($token)) {
    vdocipher_fail('authrequired', get_string('err_authrequired', 'local_academy'), 401);
}
$USER = \local_academy\token_auth::validate($token);
if (!$USER) {
    vdocipher_fail('invalidtoken', get_string('err_invalidtoken', 'local_academy'), 401);
}
\core\session\manager::set_user($USER);

try {
    switch ($function) {

        // ── Playback ────────────────────────────────────────────────────────────
        // Mint a short-lived OTP for the video on this activity, watermarked with
        // the requesting user's identity. Call this immediately before playback.
        case 'get_playback':
            $cmid = required_param('cmid', PARAM_INT);
            vdocipher_respond(['status' => 'success',
                'data' => \local_vdocipher\playback_service::get_playback($cmid, $USER)]);
            break;

        // ── Teacher CRUD ────────────────────────────────────────────────────────

        // Get S3 upload credentials for a new video + record a pending row.
        // Teacher then uploads bytes straight to VdoCipher.
        case 'create_upload':
            vdocipher_require_post();
            $title    = required_param('title', PARAM_TEXT);
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $cmid     = optional_param('cmid', 0, PARAM_INT);
            vdocipher_respond(['status' => 'success',
                'data' => \local_vdocipher\video_service::create_upload($title, $courseid, $cmid)]);
            break;

        // Refresh + return a video's processing status (PRE-Upload…ready).
        case 'video_status':
            $videoid = required_param('videoid', PARAM_ALPHANUMEXT);
            vdocipher_respond(['status' => 'success',
                'data' => \local_vdocipher\video_service::refresh_status($videoid)]);
            break;

        // List videos, optionally scoped to a course.
        case 'list_videos':
            $courseid = optional_param('courseid', 0, PARAM_INT);
            vdocipher_respond(['status' => 'success',
                'data' => \local_vdocipher\video_service::list_videos($courseid)]);
            break;

        // Attach an existing video to a course module (resource2).
        case 'attach_video':
            vdocipher_require_post();
            $videoid = required_param('videoid', PARAM_ALPHANUMEXT);
            $cmid    = required_param('cmid', PARAM_INT);
            vdocipher_respond(['status' => 'success',
                'data' => \local_vdocipher\video_service::attach($videoid, $cmid)]);
            break;

        // Delete a video from VdoCipher and remove our mapping row.
        case 'delete_video':
            vdocipher_require_post();
            $videoid = required_param('videoid', PARAM_ALPHANUMEXT);
            vdocipher_respond(['status' => 'success',
                'data' => ['deleted' => \local_vdocipher\video_service::delete_video($videoid)]]);
            break;

        default:
            vdocipher_fail('unknownfunction', get_string('err_unknownfunction', 'local_academy'));
    }
} catch (\required_capability_exception $e) {
    // Not 401: the token is good, the permission is missing. Only a dead token
    // may tell the app to sign out.
    vdocipher_fail('nopermissions', $e->getMessage());
} catch (\local_vdocipher\api_exception $e) {
    // VdoCipher-side or mapping errors are safe and useful to surface to teachers.
    vdocipher_fail('vdocipherapi', $e->getMessage());
} catch (\Throwable $e) {
    debugging('local_vdocipher api error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    vdocipher_fail('internalerror', get_string('err_internal', 'local_academy'));
}
