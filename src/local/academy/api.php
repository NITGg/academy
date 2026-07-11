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
use local_academy\coupon_manager;
use local_academy\offer_manager;
use local_academy\discount_manager;
use local_academy\course_purchase_manager;
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

/**
 * Decode the JSON `seat_options` param into a clean list of ['seats','discount_percent'].
 * The manager re-validates seats>0 and 0<=discount<=100, so we only sanitise types here.
 */
function academy_decode_seat_options($raw) {
    if ($raw === '' || $raw === null) { return []; }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) { return []; }
    $out = [];
    foreach ($decoded as $opt) {
        if (!is_array($opt)) { continue; }
        $out[] = [
            'seats'            => (int)($opt['seats'] ?? 0),
            'discount_percent' => (float)($opt['discount_percent'] ?? 0),
        ];
    }
    return $out;
}

/**
 * Decode the JSON `items` param (coupon/offer applicable-types + scope) into a clean list of
 * ['item_type','item_id']. The manager re-validates the item type, so we only sanitise here.
 */
function academy_decode_scope_items($raw) {
    if ($raw === '' || $raw === null) { return []; }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) { return []; }
    $out = [];
    foreach ($decoded as $it) {
        if (!is_array($it)) { continue; }
        $out[] = [
            'item_type' => (string)($it['item_type'] ?? ''),
            'item_id'   => (int)($it['item_id'] ?? 0),
        ];
    }
    return $out;
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
    global $SESSION;
    // Restore the session's real language override before we exit. We force ?lang for THIS request
    // only; persisting $SESSION->forcelang would leak into the next normal page load and override
    // the navbar language switch (it has the highest priority in current_language()) — the cause of
    // the "first click does nothing, second click works" / "?lang differs from applied lang" bug.
    if (array_key_exists('academy_prev_forcelang', $GLOBALS)) {
        if ($GLOBALS['academy_prev_forcelang'] === null) {
            unset($SESSION->forcelang);
        } else {
            $SESSION->forcelang = $GLOBALS['academy_prev_forcelang'];
        }
    }
    // Drop any stray output captured so far so it can't corrupt the JSON.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode($payload);
    exit;
}

$function = optional_param('function', '', PARAM_ALPHANUMEXT);
$token    = optional_param('token', '', PARAM_TEXT);

// Optional ?alang=en|ar — render system messages (get_string) and multilang content (format_string)
// in the requested language. We read 'alang' (academy language), NOT 'lang', on purpose: core
// setup.php writes $SESSION->lang from any ?lang GET param on every request — including these AJAX
// calls — so an in-flight request from a page rendered in the previous language could silently
// reset the user's navbar language choice. 'alang' is invisible to core, so an API request can
// render its response in a language without ever touching the session/site language. (Falls back to
// 'lang' for any legacy caller.)
// We set $SESSION->forcelang directly rather than via force_current_language(): that helper gates on
// the STRICT installed-language list (translation_exists($lang, false)), which drops languages
// restricted from the language menu ($CFG->langlist) and can leave the request in English even when
// the language renders fine on normal pages. The lenient translation_exists($lang) check below
// matches the site's own behaviour.
$lang = optional_param('alang', optional_param('lang', '', PARAM_SAFEDIR), PARAM_SAFEDIR);
$canforcelang = ($lang !== '' && get_string_manager()->translation_exists($lang));
// Remember the session's real language override so academy_respond() can restore it before exiting.
// Forcing the language for this request must NOT persist to later page loads (see academy_respond()).
$GLOBALS['academy_prev_forcelang'] = isset($SESSION->forcelang) ? $SESSION->forcelang : null;
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
    'create_coupon'          => 'local/academy:managecoupons',
    'update_coupon'          => 'local/academy:managecoupons',
    'activate_coupon'        => 'local/academy:managecoupons',
    'deactivate_coupon'      => 'local/academy:managecoupons',
    'delete_coupon'          => 'local/academy:managecoupons',
    'get_coupons'            => 'local/academy:managecoupons',
    'get_coupon'             => 'local/academy:managecoupons',
    'create_offer'           => 'local/academy:manageoffers',
    'update_offer'           => 'local/academy:manageoffers',
    'activate_offer'         => 'local/academy:manageoffers',
    'deactivate_offer'       => 'local/academy:manageoffers',
    'delete_offer'           => 'local/academy:manageoffers',
    'get_offers'             => 'local/academy:manageoffers',
    'get_offer'              => 'local/academy:manageoffers',
    // Manage Courses (admin: list who bought which course + "unbuy"). Reuses the subscriptions
    // capability so no new capability/DB migration is needed — it is the same academy-admin role.
    'get_all_course_purchases' => 'local/academy:managesubscriptions',
    'revoke_course_purchase'   => 'local/academy:managesubscriptions',
    'update_lesson_settings' => 'local/academy:manageplatform',
    'reverse_flex'           => 'local/academy:manageplatform',
    'list_reversible_lessons' => 'local/academy:manageplatform',
    'list_withdrawals'       => 'local/academy:manageplatform',
    'process_withdrawal'     => 'local/academy:manageplatform',
    'get_platform_wallet'    => 'local/academy:manageplatform',
    'assign_package'         => 'local/academy:manageplatform',
    'report_lessons'         => 'local/academy:manageplatform',
    'report_platform_earnings' => 'local/academy:manageplatform',
    'report_packages'        => 'local/academy:manageplatform',
    'report_student_flex'    => 'local/academy:manageplatform',
    'report_lesson_events'   => 'local/academy:manageplatform',
    'report_user_activity'   => 'local/academy:manageplatform',
    'get_all_teachers'       => 'local/academy:manageplatform',
    'search_users'           => 'local/academy:manageplatform',
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
                'b2b_enabled'   => optional_param('b2b_enabled', 0, PARAM_BOOL),
                'seat_options'  => academy_decode_seat_options(optional_param('seat_options', '', PARAM_RAW)),
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
            if (isset($_REQUEST['b2b_enabled'])) {
                $data['b2b_enabled'] = optional_param('b2b_enabled', 0, PARAM_BOOL);
            }
            if (isset($_REQUEST['seat_options'])) {
                $data['seat_options'] = academy_decode_seat_options(optional_param('seat_options', '', PARAM_RAW));
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
            // PARAM_ALPHANUM (not PARAM_ALPHA): the value "b2b" contains a digit; PARAM_ALPHA would
            // strip it to "bb" and the purchase would silently be treated as a normal subscription.
            $subtype   = optional_param('type', 'normal', PARAM_ALPHANUM);
            $seats     = optional_param('seats', 0, PARAM_INT);
            academy_respond(['status' => 'success',
                'data' => subscription_purchase_manager::purchase_subscription($userid, $subid, $method, $reference, $subtype, $seats)]);
            break;

        case 'create_subscription_checkout':
            academy_require_post();
            $subid   = required_param('subscriptionid', PARAM_INT);
            // PARAM_ALPHANUM (not PARAM_ALPHA): "b2b" has a digit that PARAM_ALPHA would strip to "bb".
            $subtype = optional_param('type', 'normal', PARAM_ALPHANUM);
            $seats   = optional_param('seats', 0, PARAM_INT);
            $lang    = optional_param('alang', current_language(), PARAM_LANG);
            $coupon  = optional_param('coupon_code', '', PARAM_TEXT);
            require_once($CFG->dirroot . '/local/payments/classes/manager.php');
            try {
                $checkout = \local_payments\manager::create_subscription_checkout(
                    $subid, $userid, null, $lang, $subtype, $seats, $coupon);
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

        // ── B2B administrator functions (self-service; ownership enforced in b2b_manager) ──

        // US-B2B-1-8: the B2B subscriptions this user administers.
        case 'get_my_b2b_subscriptions':
            academy_respond(['status' => 'success',
                'data' => \local_academy\b2b_manager::get_my_b2b_subscriptions($userid)]);
            break;

        // US-B2B-1-8: capacity + members + invitations for one B2B subscription.
        case 'get_b2b_dashboard':
            $purchaseid = required_param('purchaseid', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => [
                'capacity'    => \local_academy\b2b_manager::capacity_stats($purchaseid, $userid),
                'members'     => \local_academy\b2b_manager::list_members($purchaseid, $userid),
                'invitations' => \local_academy\b2b_manager::list_invitations($purchaseid, $userid),
            ]]);
            break;

        // US-B2B-1-2: generate an invitation link.
        case 'b2b_generate_invite':
            academy_require_post();
            $purchaseid = required_param('purchaseid', PARAM_INT);
            $expiresat  = optional_param('expires_at', 0, PARAM_INT);
            academy_respond(['status' => 'success',
                'data' => \local_academy\b2b_manager::generate_invitation($purchaseid, $userid, $expiresat)]);
            break;

        // US-B2B-1-2: revoke an invitation link.
        case 'b2b_revoke_invite':
            academy_require_post();
            $invitationid = required_param('invitationid', PARAM_INT);
            \local_academy\b2b_manager::revoke_invitation($invitationid, $userid);
            academy_respond(['status' => 'success', 'data' => ['id' => $invitationid]]);
            break;

        // US-B2B-1-5: approve a pending membership.
        case 'b2b_approve_member':
            academy_require_post();
            $membershipid = required_param('membershipid', PARAM_INT);
            \local_academy\b2b_manager::approve_membership($membershipid, $userid);
            academy_respond(['status' => 'success', 'data' => ['id' => $membershipid]]);
            break;

        // US-B2B-1-6: reject a pending membership.
        case 'b2b_reject_member':
            academy_require_post();
            $membershipid = required_param('membershipid', PARAM_INT);
            $reason = optional_param('reason', '', PARAM_TEXT);
            \local_academy\b2b_manager::reject_membership($membershipid, $userid, $reason);
            academy_respond(['status' => 'success', 'data' => ['id' => $membershipid]]);
            break;

        // US-B2B-1-7: remove an approved member.
        case 'b2b_remove_member':
            academy_require_post();
            $membershipid = required_param('membershipid', PARAM_INT);
            \local_academy\b2b_manager::remove_member($membershipid, $userid);
            academy_respond(['status' => 'success', 'data' => ['id' => $membershipid]]);
            break;

        // US-B2B-1-3: join through an invitation link (called from b2b_join.php after login).
        case 'b2b_join':
            academy_require_post();
            $invtoken = required_param('t', PARAM_RAW_TRIMMED);
            academy_respond(['status' => 'success',
                'data' => \local_academy\b2b_manager::join($invtoken, $userid)]);
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
            $coupon    = optional_param('coupon_code', '', PARAM_TEXT);
            $lang      = optional_param('alang', current_language(), PARAM_LANG);
            require_once($CFG->dirroot . '/local/payments/classes/manager.php');
            try {
                $checkout = \local_payments\manager::create_package_checkout($packageid, $userid, null, $lang, $coupon);
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
                'expiry_reminder_days', 'teacher_percent', 'platform_percent', 'lessons_courseid',
                'b2b_auto_approve_invited_users', 'b2b_return_seat_after_user_removal'];
            $data = [];
            foreach ($fields as $f) {
                if (isset($_REQUEST[$f])) { $data[$f] = required_param($f, PARAM_INT); }
            }
            if (isset($_REQUEST['lesson_start_reminder_minutes'])) {
                $data['lesson_start_reminder_minutes'] = required_param('lesson_start_reminder_minutes', PARAM_TEXT);
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

        // Admin user picker (assign_package / reports / withdrawals): search accounts by name/email.
        case 'search_users':
            academy_respond(['status' => 'success', 'data' => local_academy_search_users(
                optional_param('query', '', PARAM_TEXT),
                optional_param('role', 'any', PARAM_ALPHA),
                optional_param('limit', 20, PARAM_INT)
            )]);
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

        // Admin picker: lessons whose Flex can still be reversed (have an active earning).
        case 'list_reversible_lessons':
            academy_respond(['status' => 'success', 'data' => finance_manager::list_reversible_lessons(
                optional_param('query', '', PARAM_TEXT),
                optional_param('limit', 50, PARAM_INT)
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

        // US-B2B-1-9: per-user activity report (accepts userid or email).
        case 'report_user_activity':
            $targetid = optional_param('userid', 0, PARAM_INT);
            if (!$targetid) {
                $email = trim(optional_param('email', '', PARAM_RAW_TRIMMED));
                if ($email !== '') {
                    $targetid = (int)$DB->get_field('user', 'id', ['email' => $email, 'deleted' => 0]);
                }
            }
            if (!$targetid) {
                academy_respond(['status' => 'fail', 'error' => get_string('err_studentnotfound', 'local_academy')]);
            }
            academy_respond(['status' => 'success',
                'data' => report_manager::user_activity_report($targetid, academy_report_filters())]);
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

        // ── Coupons: admin CRUD (US-AD-7-*, managecoupons) ──

        // US-AD-7-1
        case 'create_coupon':
            academy_require_post();
            $couponid = coupon_manager::create_coupon([
                'code'           => required_param('code', PARAM_TEXT),
                'discount_type'  => required_param('discount_type', PARAM_ALPHA),
                'discount_value' => required_param('discount_value', PARAM_FLOAT),
                'max_discount'   => (isset($_REQUEST['max_discount']) && $_REQUEST['max_discount'] !== '')
                                        ? required_param('max_discount', PARAM_FLOAT) : null,
                'usage_type'     => optional_param('usage_type', 'multiple', PARAM_ALPHA),
                'usage_limit'    => optional_param('usage_limit', 0, PARAM_INT),
                'startdate'      => optional_param('startdate', 0, PARAM_INT),
                'enddate'        => optional_param('enddate', 0, PARAM_INT),
                'active'         => optional_param('active', 1, PARAM_BOOL),
                'items'          => academy_decode_scope_items(optional_param('items', '', PARAM_RAW)),
            ], $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_coupon_created', 'local_academy'), 'data' => ['couponid' => $couponid]]);
            break;

        // US-AD-7-2
        case 'update_coupon':
            academy_require_post();
            $id = required_param('id', PARAM_INT);
            $data = [];
            if (isset($_REQUEST['code']))          { $data['code'] = required_param('code', PARAM_TEXT); }
            if (isset($_REQUEST['discount_type']))  { $data['discount_type'] = required_param('discount_type', PARAM_ALPHA); }
            if (isset($_REQUEST['discount_value'])) { $data['discount_value'] = required_param('discount_value', PARAM_FLOAT); }
            if (isset($_REQUEST['max_discount']))   { $data['max_discount'] = ($_REQUEST['max_discount'] === '') ? null : required_param('max_discount', PARAM_FLOAT); }
            if (isset($_REQUEST['usage_type']))     { $data['usage_type'] = required_param('usage_type', PARAM_ALPHA); }
            if (isset($_REQUEST['usage_limit']))    { $data['usage_limit'] = required_param('usage_limit', PARAM_INT); }
            if (isset($_REQUEST['startdate']))      { $data['startdate'] = required_param('startdate', PARAM_INT); }
            if (isset($_REQUEST['enddate']))        { $data['enddate'] = required_param('enddate', PARAM_INT); }
            if (isset($_REQUEST['status']))         { $data['status'] = required_param('status', PARAM_ALPHA); }
            if (isset($_REQUEST['items']))          { $data['items'] = academy_decode_scope_items(required_param('items', PARAM_RAW)); }
            coupon_manager::update_coupon($id, $data, $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_coupon_updated', 'local_academy'), 'data' => ['id' => $id]]);
            break;

        // US-AD-7-3
        case 'activate_coupon':
            academy_require_post();
            $id = required_param('id', PARAM_INT);
            coupon_manager::activate_coupon($id, $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_coupon_activated', 'local_academy'), 'data' => ['id' => $id, 'status' => 'active']]);
            break;

        case 'deactivate_coupon':
            academy_require_post();
            $id = required_param('id', PARAM_INT);
            coupon_manager::deactivate_coupon($id, $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_coupon_deactivated', 'local_academy'), 'data' => ['id' => $id, 'status' => 'inactive']]);
            break;

        case 'delete_coupon':
            academy_require_post();
            $id = required_param('id', PARAM_INT);
            coupon_manager::delete_coupon($id);
            academy_respond(['status' => 'success', 'message' => get_string('msg_coupon_deleted', 'local_academy'), 'data' => ['id' => $id, 'deleted' => true]]);
            break;

        case 'get_coupons':
            $status = optional_param('status', '', PARAM_ALPHA);
            academy_respond(['status' => 'success', 'data' => coupon_manager::get_coupons($status)]);
            break;

        case 'get_coupon':
            $id = required_param('id', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => coupon_manager::get_coupon($id)]);
            break;

        // ── Offers: admin CRUD (US-AD-8-*, manageoffers) ──

        // US-AD-8-1
        case 'create_offer':
            academy_require_post();
            $offerid = offer_manager::create_offer([
                'name'           => required_param('name', PARAM_TEXT),
                'discount_type'  => required_param('discount_type', PARAM_ALPHA),
                'discount_value' => required_param('discount_value', PARAM_FLOAT),
                'startdate'      => optional_param('startdate', 0, PARAM_INT),
                'enddate'        => optional_param('enddate', 0, PARAM_INT),
                'active'         => optional_param('active', 1, PARAM_BOOL),
                'items'          => academy_decode_scope_items(optional_param('items', '', PARAM_RAW)),
            ], $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_offer_created', 'local_academy'), 'data' => ['offerid' => $offerid]]);
            break;

        // US-AD-8-2
        case 'update_offer':
            academy_require_post();
            $id = required_param('id', PARAM_INT);
            $data = [];
            if (isset($_REQUEST['name']))           { $data['name'] = required_param('name', PARAM_TEXT); }
            if (isset($_REQUEST['discount_type']))  { $data['discount_type'] = required_param('discount_type', PARAM_ALPHA); }
            if (isset($_REQUEST['discount_value'])) { $data['discount_value'] = required_param('discount_value', PARAM_FLOAT); }
            if (isset($_REQUEST['startdate']))      { $data['startdate'] = required_param('startdate', PARAM_INT); }
            if (isset($_REQUEST['enddate']))        { $data['enddate'] = required_param('enddate', PARAM_INT); }
            if (isset($_REQUEST['status']))         { $data['status'] = required_param('status', PARAM_ALPHA); }
            if (isset($_REQUEST['items']))          { $data['items'] = academy_decode_scope_items(required_param('items', PARAM_RAW)); }
            offer_manager::update_offer($id, $data, $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_offer_updated', 'local_academy'), 'data' => ['id' => $id]]);
            break;

        // US-AD-8-3
        case 'activate_offer':
            academy_require_post();
            $id = required_param('id', PARAM_INT);
            offer_manager::activate_offer($id, $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_offer_activated', 'local_academy'), 'data' => ['id' => $id, 'status' => 'active']]);
            break;

        case 'deactivate_offer':
            academy_require_post();
            $id = required_param('id', PARAM_INT);
            offer_manager::deactivate_offer($id, $userid);
            academy_respond(['status' => 'success', 'message' => get_string('msg_offer_deactivated', 'local_academy'), 'data' => ['id' => $id, 'status' => 'inactive']]);
            break;

        case 'delete_offer':
            academy_require_post();
            $id = required_param('id', PARAM_INT);
            offer_manager::delete_offer($id);
            academy_respond(['status' => 'success', 'message' => get_string('msg_offer_deleted', 'local_academy'), 'data' => ['id' => $id, 'deleted' => true]]);
            break;

        case 'get_offers':
            $status = optional_param('status', '', PARAM_ALPHA);
            academy_respond(['status' => 'success', 'data' => offer_manager::get_offers($status)]);
            break;

        case 'get_offer':
            $id = required_param('id', PARAM_INT);
            academy_respond(['status' => 'success', 'data' => offer_manager::get_offer($id)]);
            break;

        // ── Manage Courses (admin) ──
        // List every user's paid single-course purchase (a completed local_payments transaction with
        // item_type=course), so the admin can see who bought what and revoke it.
        case 'get_all_course_purchases':
            academy_respond(['status' => 'success', 'data' => course_purchase_manager::get_all_course_purchases()]);
            break;

        // "Unbuy" a course: unenrol the buyer and mark the transaction cancelled (or refunded).
        case 'revoke_course_purchase':
            academy_require_post();
            $transactionid = required_param('transactionid', PARAM_INT);
            $refund = optional_param('refund', 0, PARAM_BOOL);
            course_purchase_manager::revoke_course_purchase($transactionid, $refund, $userid);
            academy_respond(['status' => 'success', 'message' => get_string('mc_revoked', 'local_academy'), 'data' => ['id' => $transactionid]]);
            break;

        // Admin scope picker (coupons + offers): the selectable courses / packages / subscriptions.
        // Accessible to either promotions capability.
        case 'get_discount_targets':
            if (!has_capability('local/academy:managecoupons', context_system::instance())
                    && !has_capability('local/academy:manageoffers', context_system::instance())) {
                academy_respond(['status' => 'fail', 'error' => get_string('err_permissiondenied', 'local_academy')]);
            }
            $packages = array_map(function($p) {
                return ['id' => (int)$p->id, 'name' => format_string($p->name)];
            }, array_values($DB->get_records('academy_packages', null, 'name ASC', 'id, name')));
            $subs = array_map(function($s) {
                return ['id' => (int)$s->id, 'name' => format_string($s->name)];
            }, array_values($DB->get_records('academy_subscriptions', null, 'name ASC', 'id, name')));
            academy_respond(['status' => 'success', 'data' => [
                'categories'    => subscription_manager::get_categories_with_courses(),
                'packages'      => $packages,
                'subscriptions' => $subs,
            ]]);
            break;

        // ── Coupons + Offers: student reads (token only) ──

        // US-US-CP-1-1
        case 'get_available_coupons':
            academy_respond(['status' => 'success', 'data' => coupon_manager::get_available_coupons()]);
            break;

        // US-US-CP-1-3
        case 'get_my_coupon_usages':
            academy_respond(['status' => 'success', 'data' => coupon_manager::get_my_usages($userid)]);
            break;

        // US-US-OF-1-1
        case 'get_available_offers':
            academy_respond(['status' => 'success', 'data' => offer_manager::get_available_offers()]);
            break;

        // US-US-OF-1-3
        case 'get_my_offer_usages':
            academy_respond(['status' => 'success', 'data' => offer_manager::get_my_usages($userid)]);
            break;

        // US-US-CP-1-2: preview the discounted price for the checkout modal. Applies the automatic
        // offer always; a coupon code on top when supplied. If the code is invalid, still return the
        // offer-only price plus a coupon_error so the modal can show both.
        case 'preview_discount':
            $itemtype = required_param('item_type', PARAM_ALPHA);
            $itemid   = required_param('item_id', PARAM_INT);
            $code     = optional_param('coupon_code', '', PARAM_TEXT);
            try {
                $resolved = discount_manager::resolve($itemtype, $itemid, $userid, $code);
                academy_respond(['status' => 'success', 'data' => $resolved]);
            } catch (\moodle_exception $e) {
                // Invalid coupon — recompute without it so the offer price still shows.
                $resolved = discount_manager::resolve($itemtype, $itemid, $userid, '');
                $resolved['coupon_error'] = $e->getMessage();
                academy_respond(['status' => 'success', 'data' => $resolved]);
            }
            break;

        default:
            academy_respond(['status' => 'fail', 'error' => get_string('err_unknownfunction', 'local_academy')]);
    }
} catch (Exception $e) {
    academy_respond(['status' => 'fail', 'error' => $e->getMessage()]);
}
