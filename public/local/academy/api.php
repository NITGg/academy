<?php
/**
 * Academy token-authenticated JSON API (quiz slice).
 *
 * Protocol (unchanged from the old academy):
 *   GET|POST /local/academy/api.php?function=<name>&token=<wstoken>&...
 *   → {"status":"success","data":...} | {"status":"fail","error":"..."}
 *
 * The token is validated against Moodle's external_tokens table and $USER is set
 * to the token's owner; state-changing calls require POST.
 */

define('NO_MOODLE_COOKIES', true);
require(__DIR__ . '/../../config.php');

header('Content-Type: application/json; charset=utf-8');
ob_start();

/**
 * Emit a JSON envelope and stop, dropping any stray output first.
 */
function academy_respond($payload) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($payload);
    exit;
}

/**
 * Reject non-POST requests for state-changing calls.
 */
function academy_require_post() {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        academy_respond(['status' => 'fail', 'error' => get_string('err_postrequired', 'local_academy')]);
    }
}

$function = optional_param('function', '', PARAM_ALPHANUMEXT);
$token    = optional_param('token', '', PARAM_ALPHANUM);

// ── Authenticate via web-service token (sets $USER to the token's user) ──
if (empty($token)) {
    academy_respond(['status' => 'fail', 'error' => get_string('err_authrequired', 'local_academy')]);
}
// Full web-service token validation (expiry, IP restriction, service enabled,
// account state) — not just a raw token→user lookup.
$USER = \local_academy\token_auth::validate($token);
if (!$USER) {
    academy_respond(['status' => 'fail', 'error' => get_string('err_invalidtoken', 'local_academy')]);
}
\core\session\manager::set_user($USER);
$userid = (int) $USER->id;

// The quiz manager appends this token to returned question-image URLs.
\local_academy\quiz_manager::set_token($token);

try {
    switch ($function) {
        // ── Quiz API ──────────────────────────────────────────────────────────────

        // List quizzes. Students only see their enrolled courses; admins see all.
        case 'get_quizzes':
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $is_admin = has_capability('local/academy:manageplatform', context_system::instance());
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::get_quizzes($userid, $is_admin, $courseid)]);
            break;

        // Get a quiz with structured questions. Correct answers shown to admin only.
        case 'get_quiz':
            $cmid     = required_param('cmid', PARAM_INT);
            $is_admin = has_capability('local/academy:manageplatform', context_system::instance());
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::get_quiz($cmid, $userid, $is_admin, $is_admin)]);
            break;

        // Start a new attempt (any authenticated user, acts as themselves).
        case 'start_quiz_attempt':
            academy_require_post();
            $quizid = required_param('quizid', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::start_attempt($quizid, $userid)]);
            break;

        // Submit answers and finish an attempt (one-shot: grade all + close).
        case 'submit_quiz_attempt':
            academy_require_post();
            $attemptid = required_param('attemptid', PARAM_INT);
            $raw       = required_param('answers', PARAM_RAW);
            $answers   = json_decode($raw, true);
            if (!is_array($answers)) {
                academy_respond(['status' => 'fail', 'error' => 'answers must be a JSON array']);
            }
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::submit_attempt($attemptid, $userid, $answers)]);
            break;

        // Save the answer to ONE question without finishing the attempt.
        case 'save_quiz_answer':
            academy_require_post();
            $attemptid  = required_param('attemptid', PARAM_INT);
            $questionid = required_param('questionid', PARAM_INT);
            $raw        = required_param('answer', PARAM_RAW);
            // Accept either a JSON value ("3" or "[3,5]") or a bare scalar (3).
            $answer = json_decode($raw, true);
            if ($answer === null && trim($raw) !== 'null') {
                $answer = is_numeric($raw) ? (int)$raw : $raw;
            }
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::save_answer($attemptid, $userid, $questionid, $answer)]);
            break;

        // Submit all saved answers and finish the attempt.
        case 'finish_quiz_attempt':
            academy_require_post();
            $attemptid = required_param('attemptid', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::finish_attempt($attemptid, $userid)]);
            break;

        // Review a finished attempt. Correct answers shown to admin only.
        case 'get_quiz_attempt':
            $attemptid = required_param('attemptid', PARAM_INT);
            $is_admin  = has_capability('local/academy:manageplatform', context_system::instance());
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::get_attempt($attemptid, $userid, $is_admin)]);
            break;

        // List the current user's attempts on a quiz.
        case 'get_my_quiz_attempts':
            $quizid = required_param('quizid', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => \local_academy\quiz_manager::get_my_attempts($quizid, $userid)]);
            break;

        default:
            academy_respond(['status' => 'fail', 'error' => get_string('err_unknownfunction', 'local_academy')]);
    }
} catch (\Throwable $e) {
    // Log the real cause for developers, but never leak internal exception text
    // (DB errors, paths, stack internals) to the client.
    debugging('local_academy api error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    academy_respond(['status' => 'fail', 'error' => get_string('err_internal', 'local_academy')]);
}
