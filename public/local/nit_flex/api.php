<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JSON API for NIT Flex packages. Mirrors the reference api.php protocol:
 *   ?function=<name> ... → {"status":"success","data":...} | {"status":"error","error":...}
 *
 * Auth is the logged-in session; state-changing calls require POST + sesskey. (A web-service token
 * path for the mobile app can be layered on later without changing the protocol.)
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');

use local_nit_flex\api\packages;
use local_nit_flex\api\purchase;
use local_nit_flex\api\flex;
use local_nit_flex\entity\package;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);

header('Content-Type: application/json; charset=utf-8');

/**
 * Emit a JSON envelope and stop.
 *
 * @param array $payload
 * @return void
 */
function nit_flex_respond(array $payload): void {
    echo json_encode($payload);
    exit;
}

/**
 * Convert minor units to a major-unit float for the UI.
 *
 * @param int $minor
 * @return float
 */
function nit_flex_major(int $minor): float {
    return $minor / 100;
}

/**
 * Convert a major-unit request value to integer minor units.
 *
 * @param mixed $major
 * @return int
 */
function nit_flex_minor($major): int {
    return (int) round(((float) $major) * 100);
}

$function = optional_param('function', '', PARAM_ALPHANUMEXT);
$ispost = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

try {
    // Writes must be POST + sesskey.
    $writes = ['create_package', 'update_package', 'activate_package', 'deactivate_package',
        'delete_package', 'assign_package', 'unassign_package', 'create_package_checkout'];
    if (in_array($function, $writes, true)) {
        if (!$ispost) {
            throw new \moodle_exception('err_postrequired', 'local_nit_flex');
        }
        require_sesskey();
    }
    // Admin-only functions.
    $adminfns = ['get_packages', 'create_package', 'update_package', 'activate_package',
        'deactivate_package', 'delete_package', 'assign_package', 'unassign_package',
        'get_all_user_packages', 'search_users'];
    if (in_array($function, $adminfns, true)) {
        require_capability('local/nit_flex:managepackages', $context);
    }

    switch ($function) {
        // ── Admin: catalog ──
        case 'get_packages':
            $rows = array_map(static function (array $p): array {
                $p['price'] = nit_flex_major($p['price_minor']);
                return $p;
            }, packages::all());
            nit_flex_respond(['status' => 'success', 'data' => $rows]);
            break;

        case 'create_package':
            $id = packages::create((object) [
                'name'            => required_param('name', PARAM_TEXT),
                'description'     => optional_param('description', '', PARAM_TEXT),
                'flex_count'      => required_param('flex_count', PARAM_INT),
                'price_minor'     => nit_flex_minor(required_param('price', PARAM_FLOAT)),
                'expiration_days' => optional_param('expiration_days', 0, PARAM_INT),
                'status'          => optional_param('active', 1, PARAM_INT) ? package::STATUS_ACTIVE : package::STATUS_INACTIVE,
            ]);
            nit_flex_respond(['status' => 'success', 'data' => ['id' => $id]]);
            break;

        case 'update_package':
            packages::update(required_param('id', PARAM_INT), (object) [
                'name'            => required_param('name', PARAM_TEXT),
                'description'     => optional_param('description', '', PARAM_TEXT),
                'flex_count'      => required_param('flex_count', PARAM_INT),
                'price_minor'     => nit_flex_minor(required_param('price', PARAM_FLOAT)),
                'expiration_days' => optional_param('expiration_days', 0, PARAM_INT),
                'status'          => optional_param('status', package::STATUS_ACTIVE, PARAM_ALPHA),
            ]);
            nit_flex_respond(['status' => 'success', 'data' => []]);
            break;

        case 'activate_package':
            packages::set_status(required_param('id', PARAM_INT), package::STATUS_ACTIVE);
            nit_flex_respond(['status' => 'success', 'data' => []]);
            break;

        case 'deactivate_package':
            packages::set_status(required_param('id', PARAM_INT), package::STATUS_INACTIVE);
            nit_flex_respond(['status' => 'success', 'data' => []]);
            break;

        case 'delete_package':
            packages::delete_unused(required_param('id', PARAM_INT));
            nit_flex_respond(['status' => 'success', 'data' => []]);
            break;

        // ── Admin: assign / user packages ──
        case 'assign_package':
            $result = purchase::assign(
                $USER->id,
                required_param('studentid', PARAM_INT),
                required_param('packageid', PARAM_INT),
                nit_flex_minor(optional_param('amount', 0, PARAM_FLOAT)),
                optional_param('method', 'offline', PARAM_ALPHANUMEXT),
                trim(optional_param('reference', '', PARAM_TEXT) . ' ' . optional_param('note', '', PARAM_TEXT))
            );
            $result['price_paid'] = nit_flex_major($result['price_paid_minor']);
            $pkg = package::get_record(['id' => required_param('packageid', PARAM_INT)]);
            $result['package_name'] = $pkg ? format_string($pkg->get('name')) : '';
            $student = \core_user::get_user(required_param('studentid', PARAM_INT), 'id, firstname, lastname');
            $result['student_name'] = $student ? fullname($student) : '';
            $result['flex_balance'] = $result['remaining_flex'];
            nit_flex_respond(['status' => 'success', 'data' => $result]);
            break;

        case 'unassign_package':
            purchase::unassign(required_param('purchaseid', PARAM_INT),
                (bool) optional_param('refund', 0, PARAM_INT), $USER->id);
            nit_flex_respond(['status' => 'success', 'data' => []]);
            break;

        case 'get_all_user_packages':
            nit_flex_respond(['status' => 'success', 'data' => nit_flex_all_user_packages()]);
            break;

        case 'search_users':
            nit_flex_respond(['status' => 'success', 'data' => nit_flex_search_users(
                required_param('query', PARAM_TEXT))]);
            break;

        // ── Student: browse / my packages / history ──
        case 'get_available_packages':
            $rows = array_map(static function (array $p): array {
                $p['name'] = format_string($p['name']);
                $p['description'] = $p['description'] !== null ? format_string($p['description']) : '';
                $p['price'] = nit_flex_major($p['price_minor']);
                return $p;
            }, packages::available());
            nit_flex_respond(['status' => 'success', 'data' => $rows]);
            break;

        case 'get_my_packages':
            $rows = array_map(static function (array $p): array {
                $p['name'] = format_string(nit_flex_package_name($p['packageid']));
                $p['price_paid'] = nit_flex_major($p['price_paid_minor']);
                return $p;
            }, purchase::my_packages($USER->id));
            nit_flex_respond(['status' => 'success', 'data' => $rows]);
            break;

        case 'get_payment_history':
            $rows = array_map(static function (array $p): array {
                $p['name'] = format_string(nit_flex_package_name($p['packageid']));
                $p['amount'] = nit_flex_major($p['amount_minor']);
                return $p;
            }, purchase::payment_history($USER->id));
            nit_flex_respond(['status' => 'success', 'data' => $rows]);
            break;

        case 'get_flex_history':
            nit_flex_respond(['status' => 'success', 'data' => flex::history($USER->id)]);
            break;

        case 'create_package_checkout':
            // Simplified checkout: fulfil the purchase immediately (real Kashier gateway comes later).
            require_capability('local/nit_flex:purchase', $context);
            $summary = purchase::fulfil($USER->id, required_param('packageid', PARAM_INT), 'online', 'checkout');
            nit_flex_respond(['status' => 'success', 'data' => [
                'purchased'    => true,
                'flex_balance' => $summary['remaining_flex'],
                'redirect'     => (new moodle_url('/local/nit_flex/student.php'))->out(false),
            ]]);
            break;

        default:
            throw new \moodle_exception('err_unknownfunction', 'local_nit_flex');
    }
} catch (\Throwable $e) {
    nit_flex_respond(['status' => 'error', 'error' => $e->getMessage()]);
}

/**
 * The raw stored name of a package (multilang markup preserved for format_string).
 *
 * @param int $packageid
 * @return string
 */
function nit_flex_package_name(int $packageid): string {
    $pkg = package::get_record(['id' => $packageid]);
    return $pkg ? (string) $pkg->get('name') : '';
}

/**
 * All user package purchases, enriched with user + package details, for the admin table.
 *
 * @return array
 */
function nit_flex_all_user_packages(): array {
    global $DB;
    $sql = "SELECT pu.*, p.name AS package_name, u.firstname, u.lastname, u.email
              FROM {nit_package_purchase} pu
              JOIN {nit_package} p ON p.id = pu.packageid
              JOIN {user} u ON u.id = pu.userid
          ORDER BY pu.timecreated DESC";
    $out = [];
    $service = new \local_nit_flex\service\purchase_service();
    foreach ($DB->get_records_sql($sql) as $r) {
        $entity = new \local_nit_flex\entity\package_purchase(0, $r);
        $out[] = [
            'id'             => (int) $r->id,
            'userid'         => (int) $r->userid,
            'user_fullname'  => fullname($r),
            'user_email'     => $r->email,
            'name'           => format_string($r->package_name),
            'total_flex'     => (int) $r->flex_count,
            'remaining_flex' => (int) $r->remaining_flex,
            'price_paid'     => nit_flex_major((int) $r->price_paid_minor),
            'status'         => $service->effective_status($entity),
            'expires_at'     => (int) $r->expires_at,
        ];
    }
    return $out;
}

/**
 * Search real, active users by name or email (for the assign-student picker).
 *
 * @param string $query
 * @return array
 */
function nit_flex_search_users(string $query): array {
    global $DB, $CFG;
    $query = trim($query);
    if (\core_text::strlen($query) < 2) {
        return [];
    }
    $like = '%' . $DB->sql_like_escape($query) . '%';
    $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
    $sql = "SELECT u.id, u.firstname, u.lastname, u.email
              FROM {user} u
             WHERE u.deleted = 0 AND u.suspended = 0 AND u.id <> :guestid
               AND (" . $DB->sql_like($fullname, ':q1', false) . "
                    OR " . $DB->sql_like('u.email', ':q2', false) . ")
          ORDER BY u.firstname, u.lastname";
    $params = ['guestid' => (int) ($CFG->siteguest ?? 0), 'q1' => $like, 'q2' => $like];
    $out = [];
    foreach ($DB->get_records_sql($sql, $params, 0, 20) as $u) {
        $out[] = ['id' => (int) $u->id, 'fullname' => fullname($u), 'email' => $u->email];
    }
    return $out;
}
