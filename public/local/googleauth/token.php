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
 * Exchange a verified Google ID token for a Moodle web service token.
 *
 * The mobile app performs native Google Sign-In, obtains an ID token, and POSTs it here.
 * We verify the token with Google, map it to a Moodle account (optionally creating one),
 * and return a web service token for the requested external service.
 *
 * POST parameters:
 *   idtoken  (required) - the Google ID token (JWT) from native sign-in.
 *   service  (optional) - external service shortname, defaults to moodle_mobile_app.
 *
 * @package    local_googleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', true);
define('REQUIRE_CORRECT_ACCESS', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/user/lib.php');

/**
 * Emit a JSON error and stop.
 *
 * @param string $code short machine-readable error code
 * @param int $status HTTP status code
 */
function local_googleauth_fail(string $code, int $status = 400): void {
    http_response_code($status);
    echo json_encode(['error' => $code]);
    exit;
}

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

// Only accept POST so the ID token never lands in URLs/logs.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    local_googleauth_fail('method_not_allowed', 405);
}

if (empty($CFG->enablewebservices)) {
    local_googleauth_fail('webservices_disabled', 503);
}

$config = get_config('local_googleauth');
if (empty($config->enabled)) {
    local_googleauth_fail('plugin_disabled', 503);
}

$idtoken = required_param('idtoken', PARAM_RAW);
$serviceshortname = optional_param('service', 'moodle_mobile_app', PARAM_ALPHANUMEXT);

// Configured, accepted audiences (client IDs).
$allowedaud = array_values(array_filter(array_map('trim', explode(',', (string)$config->clientids))));
if (empty($allowedaud)) {
    local_googleauth_fail('no_clientids_configured', 503);
}

// 1) Verify the ID token with Google's tokeninfo endpoint (validates signature + expiry server-side).
$curl = new \curl();
$curl->setopt(['CURLOPT_TIMEOUT' => 10, 'CURLOPT_CONNECTTIMEOUT' => 5]);
$resp = $curl->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idtoken]);
$info = json_decode((string)$resp);

if (!is_object($info) || isset($info->error) || isset($info->error_description)) {
    local_googleauth_fail('invalid_idtoken', 401);
}

// 2) Validate claims ourselves.
$validiss = ['accounts.google.com', 'https://accounts.google.com'];
if (empty($info->iss) || !in_array($info->iss, $validiss, true)) {
    local_googleauth_fail('invalid_issuer', 401);
}
if (empty($info->aud) || !in_array($info->aud, $allowedaud, true)) {
    local_googleauth_fail('invalid_audience', 401);
}
if (empty($info->exp) || (int)$info->exp < time()) {
    local_googleauth_fail('token_expired', 401);
}
$emailverified = isset($info->email_verified)
    && ($info->email_verified === true || $info->email_verified === 'true');
if (empty($info->email) || !$emailverified) {
    local_googleauth_fail('email_not_verified', 401);
}

$email = core_text::strtolower(trim($info->email));

// Optional hosted-domain restriction.
if (!empty($config->restrictdomain)) {
    $allowdomains = array_filter(array_map('trim', explode(',', core_text::strtolower($config->restrictdomain))));
    $emaildomain = core_text::strtolower((string)substr(strrchr($email, '@'), 1));
    if (!in_array($emaildomain, $allowdomains, true)) {
        local_googleauth_fail('domain_not_allowed', 403);
    }
}

// 3) Map to a Moodle account by email (must be unique).
$usercount = $DB->count_records('user', [
    'email' => $email,
    'deleted' => 0,
    'mnethostid' => $CFG->mnet_localhost_id,
]);
if ($usercount > 1) {
    local_googleauth_fail('email_not_unique', 409);
}

$user = $DB->get_record('user', [
    'email' => $email,
    'deleted' => 0,
    'mnethostid' => $CFG->mnet_localhost_id,
]);

if (!$user) {
    if (empty($config->allowcreate)) {
        local_googleauth_fail('user_not_found', 404);
    }
    // 3b) Auto-create the account.
    $newuser = new stdClass();
    $newuser->auth = !empty($config->newuserauth) ? $config->newuserauth : 'oauth2';
    $newuser->confirmed = 1;
    $newuser->mnethostid = $CFG->mnet_localhost_id;
    $newuser->email = $email;

    $localpart = strpos($email, '@') !== false ? substr($email, 0, strpos($email, '@')) : $email;
    $base = \core_user::clean_field($localpart, 'username');
    if (empty($base)) {
        $base = 'guser';
    }
    $username = $base;
    $i = 1;
    while ($DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id])) {
        $username = $base . $i;
        $i++;
    }
    $newuser->username = $username;
    $newuser->firstname = !empty($info->given_name) ? $info->given_name
        : (!empty($info->name) ? $info->name : 'Google');
    $newuser->lastname = !empty($info->family_name) ? $info->family_name : 'User';
    $newuser->lang = $CFG->lang ?? 'en';

    $newid = user_create_user($newuser, false, true);
    $user = $DB->get_record('user', ['id' => $newid], '*', MUST_EXIST);
}

// 4) Standard account gates (mirror /login/token.php).
if (isguestuser($user)) {
    local_googleauth_fail('guest_not_allowed', 403);
}
if (!empty($user->suspended)) {
    local_googleauth_fail('user_suspended', 403);
}
if (empty($user->confirmed)) {
    local_googleauth_fail('user_not_confirmed', 403);
}

$systemcontext = context_system::instance();
if (!empty($CFG->maintenance_enabled)
        && !has_capability('moodle/site:maintenanceaccess', $systemcontext, $user)) {
    local_googleauth_fail('site_maintenance', 503);
}

// 5) Set the current user and mint the web service token.
enrol_check_plugins($user);
\core\session\manager::set_user($user);

$service = $DB->get_record('external_services', ['shortname' => $serviceshortname, 'enabled' => 1]);
if (!$service) {
    local_googleauth_fail('service_not_available', 404);
}

$token = \core_external\util::generate_token_for_current_user($service);
\core_external\util::log_token_request($token);

$siteadmin = has_capability('moodle/site:config', $systemcontext, $user->id);

$result = new stdClass();
$result->token = $token->token;
// Private token is only returned to non-admins over HTTPS (same policy as core).
$result->privatetoken = (is_https() && !$siteadmin) ? $token->privatetoken : null;
$result->userid = (int)$user->id;

echo json_encode($result);
