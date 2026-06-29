<?php
// Package API for the Academy Flex platform.
// Mirrors the local_academysessions/api.php style: ?function=...&token=..., JSON {status,data|error}.
// Admin (US-AD-1-1..1-4): create / update / deactivate / activate / delete / get_packages / get_package.
// Student (US-PK-1-1, US-PK-1-2, US-PK-2-1): get_available_packages / purchase_package /
//          get_my_packages / get_payment_history.

require_once('../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');

use local_academy\package_manager;
use local_academy\purchase_manager;
use local_academy\settings_manager;
use local_academy\teacher_manager;
use local_academy\lesson_manager;
use local_academy\flex_manager;
use local_academy\finance_manager;
use local_academy\report_manager;

/** Reject non-POST for state-changing actions. */
function academy_require_post() {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        academy_respond(['status' => 'fail', 'error' => 'This action requires POST']);
    }
}

/** Collect common report filters from the request (only those actually sent). */
function academy_report_filters() {
    $f = [];
    foreach (['teacherid', 'studentid', 'from', 'to'] as $k) {
        if (isset($_REQUEST[$k]) && $_REQUEST[$k] !== '') { $f[$k] = required_param($k, PARAM_INT); }
    }
    foreach (['status', 'source'] as $k) {
        if (isset($_REQUEST[$k]) && $_REQUEST[$k] !== '') { $f[$k] = required_param($k, PARAM_ALPHANUMEXT); }
    }
    return $f;
}

header('Content-Type: application/json');

/**
 * Emit a JSON response and stop.
 */
function academy_respond($payload) {
    echo json_encode($payload);
    exit;
}

$function = optional_param('function', '', PARAM_ALPHANUMEXT);
$token    = optional_param('token', '', PARAM_TEXT);

// ── Authenticate via web-service token (sets $USER to the token's user) ──
if (empty($token)) {
    academy_respond(['status' => 'fail', 'error' => 'Authentication required']);
}
// Note: lib/setup.php already authenticates any ?token= globally (project core patch), so a bad
// token errors out before reaching here. This block keeps behaviour correct if that ever changes.
$api = new webservice();
try {
    $authresult = json_decode(json_encode($api->authenticate_user($token)), true);
    $userid = $authresult['user']['id'];
} catch (Exception $e) {
    academy_respond(['status' => 'fail', 'error' => 'Invalid token']);
}
if (empty($userid)) {
    academy_respond(['status' => 'fail', 'error' => 'Authentication required']);
}

// ── Capability gate: admin-only functions map to a capability; others only need a valid token ──
$capmap = [
    'create_package'       => 'local/academy:managepackages',
    'update_package'       => 'local/academy:managepackages',
    'deactivate_package'   => 'local/academy:managepackages',
    'activate_package'     => 'local/academy:managepackages',
    'delete_package'       => 'local/academy:managepackages',
    'get_packages'         => 'local/academy:managepackages',
    'get_package'          => 'local/academy:managepackages',
    'update_lesson_settings' => 'local/academy:manageplatform',
    'reverse_flex'           => 'local/academy:manageplatform',
    'list_withdrawals'       => 'local/academy:manageplatform',
    'process_withdrawal'     => 'local/academy:manageplatform',
    'get_platform_wallet'    => 'local/academy:manageplatform',
    'assign_package'         => 'local/academy:manageplatform',
    'report_lessons'         => 'local/academy:manageplatform',
    'report_platform_earnings' => 'local/academy:manageplatform',
    'report_packages'        => 'local/academy:manageplatform',
    'report_student_flex'    => 'local/academy:manageplatform',
];
if (isset($capmap[$function]) && !has_capability($capmap[$function], context_system::instance())) {
    academy_respond(['status' => 'fail', 'error' => 'Permission denied']);
}

try {
    switch ($function) {

        // US-AD-1-1
        case 'create_package':
            $packageid = package_manager::create_package([
                'name'            => required_param('name', PARAM_TEXT),
                'description'     => optional_param('description', '', PARAM_TEXT),
                'flex_count'      => required_param('flex_count', PARAM_INT),
                'price'           => required_param('price', PARAM_FLOAT),
                'expiration_days' => optional_param('expiration_days', 0, PARAM_INT),
                'active'          => optional_param('active', 1, PARAM_BOOL),
            ], $userid);
            academy_respond(['status' => 'success', 'data' => ['packageid' => $packageid]]);
            break;

        // US-AD-1-2
        case 'update_package':
            $id = required_param('id', PARAM_INT);
            $data = [];
            // Only forward fields that were actually sent in the request.
            foreach (['name', 'description', 'status'] as $key) {
                if (isset($_REQUEST[$key])) {
                    $data[$key] = ($key === 'status' || $key === 'name')
                        ? required_param($key, PARAM_TEXT)
                        : optional_param($key, '', PARAM_TEXT);
                }
            }
            foreach (['flex_count', 'expiration_days'] as $key) {
                if (isset($_REQUEST[$key])) {
                    $data[$key] = required_param($key, PARAM_INT);
                }
            }
            if (isset($_REQUEST['price'])) {
                $data['price'] = required_param('price', PARAM_FLOAT);
            }
            package_manager::update_package($id, $data, $userid);
            academy_respond(['status' => 'success', 'data' => ['id' => $id]]);
            break;

        // US-AD-1-3
        case 'deactivate_package':
            $id = required_param('id', PARAM_INT);
            package_manager::deactivate_package($id, $userid);
            academy_respond(['status' => 'success', 'data' => ['id' => $id, 'status' => 'inactive']]);
            break;

        case 'activate_package':
            $id = required_param('id', PARAM_INT);
            package_manager::activate_package($id, $userid);
            academy_respond(['status' => 'success', 'data' => ['id' => $id, 'status' => 'active']]);
            break;

        // US-AD-1-4
        case 'delete_package':
            $id = required_param('id', PARAM_INT);
            package_manager::delete_package($id);
            academy_respond(['status' => 'success', 'data' => ['id' => $id, 'deleted' => true]]);
            break;

        // Helpers (listing / single fetch) — handy for the admin UI and testing.
        case 'get_packages':
            $status = optional_param('status', '', PARAM_ALPHA);
            academy_respond(['status' => 'success', 'data' => package_manager::get_packages($status)]);
            break;

        case 'get_package':
            $id = required_param('id', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => package_manager::get_package($id)]);
            break;

        // ── Student functions (any authenticated user, acting as themselves) ──

        // US-PK-1-1
        case 'get_available_packages':
            academy_respond(['status' => 'success', 'data' => purchase_manager::get_available_packages()]);
            break;

        // US-PK-1-2 (payment gateway skipped — assumed paid)
        case 'purchase_package':
            // Purchasing changes state + records a payment — require POST (not a safe GET).
            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                academy_respond(['status' => 'fail', 'error' => 'This action requires POST']);
            }
            $packageid = required_param('packageid', PARAM_INT);
            $method    = optional_param('method', 'online', PARAM_ALPHANUMEXT);
            $reference = optional_param('reference', '', PARAM_TEXT);
            academy_respond(['status' => 'success',
                'data' => purchase_manager::purchase_package($userid, $packageid, $method, $reference)]);
            break;

        // US-PK-2-1 (packages)
        case 'get_my_packages':
            academy_respond(['status' => 'success', 'data' => purchase_manager::get_my_packages($userid)]);
            break;

        // US-PK-2-1 (payment history)
        case 'get_payment_history':
            academy_respond(['status' => 'success', 'data' => purchase_manager::get_payment_history($userid)]);
            break;

        // ── Lesson settings (US-AD-2-1) ──
        case 'get_lesson_settings': // readable by any authenticated user (app needs the deadlines)
            academy_respond(['status' => 'success', 'data' => settings_manager::get_settings()]);
            break;

        case 'update_lesson_settings': // admin (manageplatform)
            $fields = ['min_booking_minutes', 'cancel_deadline_minutes', 'update_deadline_minutes',
                'start_allowed_minutes', 'absence_report_minutes', 'teacher_percent', 'platform_percent',
                'lessons_courseid'];
            $data = [];
            foreach ($fields as $f) {
                if (isset($_REQUEST[$f])) { $data[$f] = required_param($f, PARAM_INT); }
            }
            academy_respond(['status' => 'success', 'data' => settings_manager::update_settings($data)]);
            break;

        // ── Teacher profile (US-TR-1-1) ──
        case 'update_teacher_profile': // teacher edits own profile
            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                academy_respond(['status' => 'fail', 'error' => 'This action requires POST']);
            }
            $data = [];
            foreach (['headline', 'bio', 'experience', 'photourl'] as $f) {
                if (isset($_REQUEST[$f])) { $data[$f] = required_param($f, PARAM_TEXT); }
            }
            if (isset($_REQUEST['available'])) { $data['available'] = required_param('available', PARAM_BOOL); }
            // subjects / hours are JSON arrays.
            if (isset($_REQUEST['subjects'])) {
                $data['subjects'] = json_decode(required_param('subjects', PARAM_RAW), true) ?: [];
            }
            if (isset($_REQUEST['hours'])) {
                $data['hours'] = json_decode(required_param('hours', PARAM_RAW), true) ?: [];
            }
            academy_respond(['status' => 'success', 'data' => teacher_manager::update_profile($userid, $data)]);
            break;

        case 'get_teacher_profile': // teacher views own profile
            academy_respond(['status' => 'success', 'data' => teacher_manager::get_profile($userid)]);
            break;

        // ── Browse teachers (US-ST-2-1) ──
        case 'browse_teachers':
            $subject = optional_param('subject', '', PARAM_TEXT);
            academy_respond(['status' => 'success', 'data' => teacher_manager::browse_teachers($subject)]);
            break;

        case 'get_teacher':
            $teacherid = required_param('teacherid', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => teacher_manager::get_teacher($teacherid)]);
            break;

        // ── Lessons + Flex engine (Phase 2) ──

        // US-LS-1-1: student requests a lesson.
        case 'request_lesson':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => lesson_manager::request_lesson(
                $userid,
                required_param('teacherid', PARAM_INT),
                required_param('subject', PARAM_TEXT),
                required_param('requested_time', PARAM_INT),
                optional_param('note', '', PARAM_TEXT)
            )]);
            break;

        // US-LS-2-1 / US-LS-2-3: teacher accept / reject / suggest.
        case 'teacher_respond_lesson':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => lesson_manager::teacher_respond(
                $userid,
                required_param('lessonid', PARAM_INT),
                required_param('action', PARAM_ALPHA),
                [
                    'suggested_time' => optional_param('suggested_time', 0, PARAM_INT),
                    'reject_reason'  => optional_param('reject_reason', '', PARAM_TEXT),
                ]
            )]);
            break;

        // US-LS-2-2: student accept / reject / suggest.
        case 'student_respond_lesson':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => lesson_manager::student_respond(
                $userid,
                required_param('lessonid', PARAM_INT),
                required_param('action', PARAM_ALPHA),
                [
                    'suggested_time' => optional_param('suggested_time', 0, PARAM_INT),
                    'reject_reason'  => optional_param('reject_reason', '', PARAM_TEXT),
                ]
            )]);
            break;

        // US-ST-2-2: student withdraws an un-confirmed request.
        case 'cancel_lesson_request':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => lesson_manager::cancel_request_as_student(
                $userid,
                required_param('lessonid', PARAM_INT),
                optional_param('reason', '', PARAM_TEXT)
            )]);
            break;

        // US-LS-3-1: teacher starts the lesson.
        case 'start_lesson':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => lesson_manager::start_lesson(
                $userid, required_param('lessonid', PARAM_INT))]);
            break;

        // US-LS-3-2: teacher completes the lesson (consumes the Flex).
        case 'complete_lesson':
            academy_require_post();
            $note = isset($_REQUEST['note']) ? required_param('note', PARAM_TEXT) : null;
            academy_respond(['status' => 'success', 'data' => lesson_manager::complete_lesson(
                $userid, required_param('lessonid', PARAM_INT), $note)]);
            break;

        // US-LS-3-3: teacher reports student absence.
        case 'report_student_absent':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => lesson_manager::report_student_absent(
                $userid, required_param('lessonid', PARAM_INT))]);
            break;

        // US-LS-3-4: student reports teacher absence (returns the Flex).
        case 'report_teacher_absent':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => lesson_manager::report_teacher_absent(
                $userid, required_param('lessonid', PARAM_INT))]);
            break;

        // US-LS-4-1: student cancels a confirmed lesson.
        case 'cancel_lesson_student':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => lesson_manager::cancel_as_student(
                $userid,
                required_param('lessonid', PARAM_INT),
                optional_param('reason', '', PARAM_TEXT)
            )]);
            break;

        // US-LS-4-2: teacher cancels a confirmed lesson (returns the Flex).
        case 'cancel_lesson_teacher':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => lesson_manager::cancel_as_teacher(
                $userid,
                required_param('lessonid', PARAM_INT),
                optional_param('reason', '', PARAM_TEXT)
            )]);
            break;

        // US-LS-5-1: request a time update (either party).
        case 'request_time_update':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => lesson_manager::request_time_update(
                $userid,
                required_param('lessonid', PARAM_INT),
                required_param('proposed_time', PARAM_INT)
            )]);
            break;

        // US-LS-5-2: respond to a time-update request (the other party).
        case 'respond_time_update':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => lesson_manager::respond_time_update(
                $userid,
                required_param('lessonid', PARAM_INT),
                required_param('action', PARAM_ALPHA)
            )]);
            break;

        // US-TR-1-2 / US-ST-2-2: list my lessons.
        case 'get_my_lessons':
            academy_respond(['status' => 'success', 'data' => lesson_manager::get_my_lessons(
                $userid,
                optional_param('role', '', PARAM_ALPHA),
                optional_param('status', '', PARAM_ALPHANUMEXT)
            )]);
            break;

        // US-TR-1-2 / US-ST-2-2: a single lesson with proposals + available actions.
        case 'get_lesson':
            academy_respond(['status' => 'success', 'data' => lesson_manager::get_lesson(
                $userid, required_param('lessonid', PARAM_INT))]);
            break;

        // Student's own Flex ledger (reserve/consume/return history).
        case 'get_flex_history':
            academy_respond(['status' => 'success', 'data' => flex_manager::get_history($userid)]);
            break;

        // ── Financial (Phase 3) ──

        // Teacher wallet: balance + earnings + withdrawals (acts on the token's user).
        case 'get_teacher_wallet':
            academy_respond(['status' => 'success', 'data' => finance_manager::get_teacher_wallet($userid)]);
            break;

        // US-FN-2-1: teacher requests a withdrawal.
        case 'request_withdrawal':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => finance_manager::request_withdrawal(
                $userid,
                required_param('amount', PARAM_FLOAT),
                optional_param('method', 'bank', PARAM_ALPHANUMEXT),
                optional_param('account', '', PARAM_TEXT)
            )]);
            break;

        // Teacher's own withdrawals.
        case 'get_my_withdrawals':
            academy_respond(['status' => 'success', 'data' => finance_manager::get_my_withdrawals($userid)]);
            break;

        // US-FN-1-5: admin reverses a consumed/distributed Flex (manageplatform).
        case 'reverse_flex':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => finance_manager::reverse_flex(
                required_param('lessonid', PARAM_INT),
                $userid,
                required_param('reason', PARAM_TEXT)
            )]);
            break;

        // US-FN-2-2: admin lists withdrawal requests (manageplatform).
        case 'list_withdrawals':
            academy_respond(['status' => 'success', 'data' => finance_manager::list_withdrawals(
                optional_param('status', '', PARAM_ALPHA))]);
            break;

        // US-FN-2-2: admin processes a withdrawal (manageplatform).
        case 'process_withdrawal':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => finance_manager::process_withdrawal(
                $userid,
                required_param('withdrawalid', PARAM_INT),
                required_param('action', PARAM_ALPHA),
                [
                    'reason'    => optional_param('reason', '', PARAM_TEXT),
                    'reference' => optional_param('reference', '', PARAM_TEXT),
                ]
            )]);
            break;

        // Admin platform wallet overview (manageplatform).
        case 'get_platform_wallet':
            academy_respond(['status' => 'success', 'data' => finance_manager::get_platform_wallet()]);
            break;

        // ── Admin reports + assign (Phase 4, manageplatform) ──

        // US-AD-4-1: admin assigns a package to a student (offline payment).
        case 'assign_package':
            academy_require_post();
            academy_respond(['status' => 'success', 'data' => purchase_manager::assign_package(
                $userid,
                required_param('studentid', PARAM_INT),
                required_param('packageid', PARAM_INT),
                optional_param('amount', 0, PARAM_FLOAT),
                optional_param('method', 'offline', PARAM_ALPHANUMEXT),
                optional_param('reference', '', PARAM_TEXT),
                optional_param('note', '', PARAM_TEXT)
            )]);
            break;

        // US-AD-3-1: lessons & attendance report.
        case 'report_lessons':
            academy_respond(['status' => 'success', 'data' => report_manager::lessons_report(academy_report_filters())]);
            break;

        // US-AD-3-2: platform earnings report.
        case 'report_platform_earnings':
            academy_respond(['status' => 'success', 'data' => report_manager::platform_earnings_report(academy_report_filters())]);
            break;

        // US-AD-3-3: package & flex report.
        case 'report_packages':
            academy_respond(['status' => 'success', 'data' => report_manager::package_flex_report(academy_report_filters())]);
            break;

        // US-AD-3-4: student flex balance + history.
        case 'report_student_flex':
            academy_respond(['status' => 'success', 'data' => report_manager::student_flex_report(
                required_param('studentid', PARAM_INT))]);
            break;

        default:
            academy_respond(['status' => 'fail', 'error' => 'Unknown function']);
    }
} catch (Exception $e) {
    academy_respond(['status' => 'fail', 'error' => $e->getMessage()]);
}
