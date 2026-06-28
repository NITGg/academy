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

// ── Admin functions require the site-level capability; student functions only need a valid token ──
$adminfunctions = ['create_package', 'update_package', 'deactivate_package', 'activate_package',
    'delete_package', 'get_packages', 'get_package'];
if (in_array($function, $adminfunctions, true)) {
    if (!has_capability('local/academy:managepackages', context_system::instance())) {
        academy_respond(['status' => 'fail', 'error' => 'Permission denied']);
    }
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

        default:
            academy_respond(['status' => 'fail', 'error' => 'Unknown function']);
    }
} catch (Exception $e) {
    academy_respond(['status' => 'fail', 'error' => $e->getMessage()]);
}
