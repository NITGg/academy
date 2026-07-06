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
use local_academy\subscription_manager;
use local_academy\subscription_purchase_manager;
use local_academy\settings_manager;
use local_academy\teacher_manager;
use local_academy\lesson_manager;
use local_academy\flex_manager;
use local_academy\finance_manager;
use local_academy\report_manager;
use local_academy\quiz_manager;

/** Reject non-POST for state-changing actions. */
function academy_require_post() {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        academy_respond(['status' => 'fail', 'error' => get_string('err_postrequired', 'local_academy')]);
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

// Capture anything a handler might accidentally print (e.g. a mail-processor warning when SMTP is
// misconfigured, or a debug message). Without this, that text gets mixed into the JSON body and the
// app fails to parse it — surfacing as "Session expired". academy_respond() discards the buffer
// before emitting the real JSON, so responses are always clean JSON.
ob_start();

/**
 * Emit a JSON response and stop.
 */
function academy_respond($payload) {
    // Drop any stray output captured so far so it can't corrupt the JSON.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($payload);
    exit;
}

$function = optional_param('function', '', PARAM_ALPHANUMEXT);
$token    = optional_param('token', '', PARAM_TEXT);

// Optional ?lang=en|ar — render system messages (get_string) and multilang content (format_string)
// in the requested language. We set $SESSION->forcelang directly rather than via
// force_current_language(): that helper gates on the STRICT installed-language list
// (translation_exists($lang, false)), which drops languages restricted from the language menu
// ($CFG->langlist) and can leave the request in English even when the language renders fine on
// normal pages. The lenient translation_exists($lang) check below matches the site's own behaviour.
$lang = optional_param('lang', '', PARAM_SAFEDIR);
$canforcelang = ($lang !== '' && get_string_manager()->translation_exists($lang));
if ($canforcelang) {
    $SESSION->forcelang = $lang;
}

// ── Authenticate via web-service token (sets $USER to the token's user) ──
if (empty($token)) {
    academy_respond(['status' => 'fail', 'error' => get_string('err_authrequired', 'local_academy')]);
}
// Note: lib/setup.php already authenticates any ?token= globally (project core patch), so a bad
// token errors out before reaching here. This block keeps behaviour correct if that ever changes.
$api = new webservice();
try {
    $authresult = json_decode(json_encode($api->authenticate_user($token)), true);
    $userid = $authresult['user']['id'];
} catch (Exception $e) {
    academy_respond(['status' => 'fail', 'error' => get_string('err_invalidtoken', 'local_academy')]);
}
if (empty($userid)) {
    academy_respond(['status' => 'fail', 'error' => get_string('err_authrequired', 'local_academy')]);
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
    'get_all_user_packages' => 'local/academy:managepackages',
    'unassign_package'     => 'local/academy:managepackages',
    'create_subscription'      => 'local/academy:managesubscriptions',
    'update_subscription'      => 'local/academy:managesubscriptions',
    'deactivate_subscription'  => 'local/academy:managesubscriptions',
    'activate_subscription'    => 'local/academy:managesubscriptions',
    'delete_subscription'      => 'local/academy:managesubscriptions',
    'get_subscriptions'        => 'local/academy:managesubscriptions',
    'get_subscription'         => 'local/academy:managesubscriptions',
    'unsubscribe_user'         => 'local/academy:managesubscriptions',
    'set_course_subscriptions' => 'local/academy:managesubscriptions',
    'set_subscription_courses' => 'local/academy:managesubscriptions',
    'get_course_access'        => 'local/academy:managesubscriptions',
    'get_categories_with_courses'=> 'local/academy:managesubscriptions',
    'get_all_user_subscriptions'=> 'local/academy:managesubscriptions',
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
    'report_lesson_events'   => 'local/academy:manageplatform',
    'get_all_teachers'       => 'local/academy:manageplatform',
];
if (isset($capmap[$function]) && !has_capability($capmap[$function], context_system::instance())) {
    academy_respond(['status' => 'fail', 'error' => get_string('err_permissiondenied', 'local_academy')]);
}

// Re-apply the requested language right before dispatch. Token authentication above sets $USER to
// the token's user, which can re-initialise the language to that user's preference — so we set the
// override again here to guarantee format_string()/multilang content resolves to ?lang.
if ($canforcelang) {
    $SESSION->forcelang = $lang;
}

// Make the request token available to the quiz API so returned image URLs
// (webservice/pluginfile.php/...) carry ?token= and can be loaded directly.
quiz_manager::set_token($token);

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
            academy_respond(['status' => 'success', 'message' => get_string('msg_package_created', 'local_academy'), 'data' => ['packageid' => $packageid]]);
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
            academy_respond(['status' => 'success', 'message' => get_string('msg_package_updated', 'local_academy'), 'data' => ['id' => $id]]);
            break;

        // US-AD-1-3
        case 'deactivate_package':
            $id = required_param('id', PARAM_INT);
            package_manager::deactivate_package($id, $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_package_deactivated', 'local_academy'), 'data' => ['id' => $id, 'status' => 'inactive']]);
            break;

        case 'activate_package':
            $id = required_param('id', PARAM_INT);
            package_manager::activate_package($id, $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_package_activated', 'local_academy'), 'data' => ['id' => $id, 'status' => 'active']]);
            break;

        // US-AD-1-4
        case 'delete_package':
            $id = required_param('id', PARAM_INT);
            package_manager::delete_package($id);
            academy_respond(['status' => 'success', 'message' => get_string('msg_package_deleted', 'local_academy'), 'data' => ['id' => $id, 'deleted' => true]]);
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

        case 'get_all_user_packages':
            academy_respond(['status' => 'success', 'data' => purchase_manager::get_all_user_packages()]);
            break;

        case 'unassign_package':
            academy_require_post();
            $purchaseid = required_param('purchaseid', PARAM_INT);
            $refund = optional_param('refund', 0, PARAM_BOOL);
            purchase_manager::unassign_package($purchaseid, $refund, $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_package_unassigned', 'local_academy'), 'data' => true]);
            break;

        // ── Subscriptions: admin plan CRUD (US-AD-5-*, managesubscriptions) ──

        // US-AD-5-1
        case 'create_subscription':
            academy_require_post();
            $subid = subscription_manager::create_subscription([
                'name'          => required_param('name', PARAM_TEXT),
                'description'   => optional_param('description', '', PARAM_TEXT),
                'price'         => required_param('price', PARAM_FLOAT),
                'duration_days' => required_param('duration_days', PARAM_INT),
                'active'        => optional_param('active', 1, PARAM_BOOL),
            ], $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_subscription_created', 'local_academy'), 'data' => ['subscriptionid' => $subid]]);
            break;

        // US-AD-5-2
        case 'update_subscription':
            academy_require_post();
            $id = required_param('id', PARAM_INT);
            $data = [];
            foreach (['name', 'description', 'status'] as $key) {
                if (isset($_REQUEST[$key])) {
                    $data[$key] = ($key === 'name' || $key === 'status')
                        ? required_param($key, PARAM_TEXT)
                        : optional_param($key, '', PARAM_TEXT);
                }
            }
            if (isset($_REQUEST['duration_days'])) {
                $data['duration_days'] = required_param('duration_days', PARAM_INT);
            }
            if (isset($_REQUEST['price'])) {
                $data['price'] = required_param('price', PARAM_FLOAT);
            }
            subscription_manager::update_subscription($id, $data, $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_subscription_updated', 'local_academy'), 'data' => ['id' => $id]]);
            break;

        // US-AD-5-3
        case 'deactivate_subscription':
            academy_require_post();
            $id = required_param('id', PARAM_INT);
            subscription_manager::deactivate_subscription($id, $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_subscription_deactivated', 'local_academy'), 'data' => ['id' => $id, 'status' => 'inactive']]);
            break;

        case 'activate_subscription':
            academy_require_post();
            $id = required_param('id', PARAM_INT);
            subscription_manager::activate_subscription($id, $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_subscription_activated', 'local_academy'), 'data' => ['id' => $id, 'status' => 'active']]);
            break;

        // US-AD-5-4
        case 'delete_subscription':
            academy_require_post();
            $id = required_param('id', PARAM_INT);
            subscription_manager::delete_subscription($id);
            academy_respond(['status' => 'success', 'message' => get_string('msg_subscription_deleted', 'local_academy'), 'data' => ['id' => $id, 'deleted' => true]]);
            break;

        case 'get_subscriptions':
            $status = optional_param('status', '', PARAM_ALPHA);
            academy_respond(['status' => 'success', 'data' => subscription_manager::get_subscriptions($status)]);
            break;

        case 'get_subscription':
            $id = required_param('id', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => subscription_manager::get_subscription($id)]);
            break;

        // US-AD-6-1: set which subscriptions can access a course.
        case 'set_course_subscriptions':
            academy_require_post();
            $courseid = required_param('courseid', PARAM_INT);
            $mode     = required_param('mode', PARAM_ALPHA); // 'all' | 'specific'
            $subids   = [];
            if (isset($_REQUEST['subscriptionids'])) {
                $decoded = json_decode(required_param('subscriptionids', PARAM_RAW), true);
                $subids  = is_array($decoded) ? $decoded : [];
            }
            academy_respond(['status' => 'success',
                'data' => subscription_manager::set_course_subscriptions($courseid, $mode, $subids, $userid)]);
            break;

        // US-AD-6-1: read a course's current access rule.
        case 'get_course_access':
            $courseid = required_param('courseid', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => subscription_manager::get_course_access($courseid)]);
            break;

        case 'set_subscription_courses':
            academy_require_post();
            $subscriptionid = required_param('subscriptionid', PARAM_INT);
            $courseids   = [];
            if (isset($_REQUEST['courseids'])) {
                $decoded = json_decode(required_param('courseids', PARAM_RAW), true);
                $courseids = is_array($decoded) ? $decoded : [];
            }
            academy_respond(['status' => 'success', 'message' => get_string('msg_subscription_courses_set', 'local_academy'),
                'data' => subscription_manager::set_subscription_courses($subscriptionid, $courseids, $userid)]);
            break;

        case 'get_categories_with_courses':
            academy_respond(['status' => 'success', 'data' => subscription_manager::get_categories_with_courses()]);
            break;

        case 'unsubscribe_user':
            academy_require_post();
            $purchaseid = required_param('purchaseid', PARAM_INT);
            $refund = optional_param('refund', 0, PARAM_BOOL);
            subscription_purchase_manager::unsubscribe_user($purchaseid, $refund, $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_user_unsubscribed', 'local_academy'), 'data' => true]);
            break;

        case 'get_all_user_subscriptions':
            academy_respond(['status' => 'success', 'data' => subscription_purchase_manager::get_all_user_subscriptions()]);
            break;

        // ── Subscriptions: student (any authenticated user, acting as themselves) ──

        // US-SB-1-1
        case 'get_available_subscriptions':
            academy_respond(['status' => 'success',
                'data' => subscription_purchase_manager::get_available_subscriptions()]);
            break;

        // US-SB-1-2 (payment gateway skipped — assumed paid)
        case 'purchase_subscription':
            academy_require_post();
            $subid     = required_param('subscriptionid', PARAM_INT);
            $method    = optional_param('method', 'online', PARAM_ALPHANUMEXT);
            $reference = optional_param('reference', '', PARAM_TEXT);
            academy_respond(['status' => 'success',
                'data' => subscription_purchase_manager::purchase_subscription($userid, $subid, $method, $reference)]);
            break;

        case 'create_subscription_checkout':
            academy_require_post();
            $subid = required_param('subscriptionid', PARAM_INT);
            require_once($CFG->dirroot . '/local/payments/classes/manager.php');
            try {
                $checkout = \local_payments\manager::create_subscription_checkout($subid, $userid);
                academy_respond(['status' => 'success', 'data' => $checkout]);
            } catch (\Exception $e) {
                academy_respond(['status' => 'fail', 'error' => $e->getMessage()]);
            }
            break;

        // US-SB-2-1 (subscriptions)
        case 'get_my_subscriptions':
            academy_respond(['status' => 'success',
                'data' => subscription_purchase_manager::get_my_subscriptions($userid)]);
            break;

        // US-SB-2-1 (payment history)
        case 'get_subscription_payment_history':
            academy_respond(['status' => 'success',
                'data' => subscription_purchase_manager::get_payment_history($userid)]);
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
                academy_respond(['status' => 'fail', 'error' => get_string('err_postrequired', 'local_academy')]);
            }
            $packageid = required_param('packageid', PARAM_INT);
            $method    = optional_param('method', 'online', PARAM_ALPHANUMEXT);
            $reference = optional_param('reference', '', PARAM_TEXT);
            academy_respond(['status' => 'success', 'message' => get_string('msg_package_purchased', 'local_academy'),
                'data' => purchase_manager::purchase_package($userid, $packageid, $method, $reference)]);
            break;

        case 'create_package_checkout':
            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                academy_respond(['status' => 'fail', 'error' => get_string('err_postrequired', 'local_academy')]);
            }
            $packageid = required_param('packageid', PARAM_INT);
            require_once($CFG->dirroot . '/local/payments/classes/manager.php');
            try {
                $checkout = \local_payments\manager::create_package_checkout($packageid, $userid);
                academy_respond(['status' => 'success', 'data' => $checkout]);
            } catch (\Exception $e) {
                academy_respond(['status' => 'fail', 'error' => $e->getMessage()]);
            }
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
                'start_allowed_minutes', 'complete_allowed_minutes', 'absence_report_minutes',
                'teacher_percent', 'platform_percent', 'lessons_courseid'];
            $data = [];
            foreach ($fields as $f) {
                if (isset($_REQUEST[$f])) { $data[$f] = required_param($f, PARAM_INT); }
            }
            academy_respond(['status' => 'success', 'data' => settings_manager::update_settings($data)]);
            break;

        // ── Admin: list all teachers with filters (manageplatform) ──
        case 'get_all_teachers':
            $filters = [];
            foreach (['approved', 'available', 'courseid', 'categoryid', 'page', 'perpage'] as $f) {
                if (isset($_REQUEST[$f]) && $_REQUEST[$f] !== '') {
                    $filters[$f] = required_param($f, PARAM_INT);
                }
            }
            foreach (['subject', 'year', 'search', 'phone'] as $f) {
                if (isset($_REQUEST[$f]) && $_REQUEST[$f] !== '') {
                    $filters[$f] = required_param($f, PARAM_TEXT);
                }
            }
            academy_respond(['status' => 'success', 'data' => teacher_manager::get_all_teachers($filters)]);
            break;

        // Any authenticated user — used to populate year/grade filter dropdowns.
        case 'get_years':
            academy_respond(['status' => 'success', 'data' => teacher_manager::get_years()]);
            break;

        // ── Teacher profile (US-TR-1-1) ──
        case 'update_teacher_profile': // teacher edits own profile
            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                academy_respond(['status' => 'fail', 'error' => get_string('err_postrequired', 'local_academy')]);
            }
            $data = [];
            $data['phone'] = required_param('phone', PARAM_TEXT);
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
            if (isset($_REQUEST['years'])) {
                $data['years'] = json_decode(required_param('years', PARAM_RAW), true) ?: [];
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

        // US-AD-3-1: decrypted action timeline (audit trail) for one lesson.
        case 'report_lesson_events':
            academy_respond(['status' => 'success', 'data' => report_manager::lesson_events_report(
                required_param('lessonid', PARAM_INT))]);
            break;

        // ── Quiz API ──────────────────────────────────────────────────────────────

        // List quizzes. Students only see their enrolled courses; admins see all.
        case 'get_quizzes':
            $courseid = optional_param('courseid', 0, PARAM_INT);
            $is_admin = has_capability('local/academy:manageplatform', context_system::instance());
            academy_respond(['status' => 'success', 'data' => quiz_manager::get_quizzes($userid, $is_admin, $courseid)]);
            break;

        // Get a quiz with structured questions. Correct answers shown to admin only.
        case 'get_quiz':
            $cmid     = required_param('cmid', PARAM_INT);
            $is_admin = has_capability('local/academy:manageplatform', context_system::instance());
            academy_respond(['status' => 'success', 'data' => quiz_manager::get_quiz($cmid, $userid, $is_admin, $is_admin)]);
            break;

        // Start a new attempt (any authenticated user, acts as themselves).
        case 'start_quiz_attempt':
            academy_require_post();
            $quizid = required_param('quizid', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => quiz_manager::start_attempt($quizid, $userid)]);
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
            academy_respond(['status' => 'success', 'data' => quiz_manager::submit_attempt($attemptid, $userid, $answers)]);
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
            academy_respond(['status' => 'success', 'data' => quiz_manager::save_answer($attemptid, $userid, $questionid, $answer)]);
            break;

        // Submit all saved answers and finish the attempt.
        case 'finish_quiz_attempt':
            academy_require_post();
            $attemptid = required_param('attemptid', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => quiz_manager::finish_attempt($attemptid, $userid)]);
            break;

        // Review a finished attempt. Correct answers shown to admin only.
        case 'get_quiz_attempt':
            $attemptid = required_param('attemptid', PARAM_INT);
            $is_admin  = has_capability('local/academy:manageplatform', context_system::instance());
            academy_respond(['status' => 'success', 'data' => quiz_manager::get_attempt($attemptid, $userid, $is_admin)]);
            break;

        // List the current user's attempts on a quiz.
        case 'get_my_quiz_attempts':
            $quizid = required_param('quizid', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => quiz_manager::get_my_attempts($quizid, $userid)]);
            break;

        default:
            academy_respond(['status' => 'fail', 'error' => get_string('err_unknownfunction', 'local_academy')]);
    }
} catch (Exception $e) {
    academy_respond(['status' => 'fail', 'error' => $e->getMessage()]);
}
