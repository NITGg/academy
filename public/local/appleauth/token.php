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
 * Exchange a verified Sign in with Apple identity token for a Moodle web service token.
 *
 * The iOS / Flutter app performs native Sign in with Apple and POSTs the identity
 * token here. We verify it against Apple's public keys, map it to a Moodle account
 * (by Apple subject, then verified e-mail, else optionally create one) and return a
 * web service token for the requested external service — same response as
 * /local/googleauth/token.php, plus is_new.
 *
 * POST parameters (form-encoded or JSON body):
 *   id_token (required) - the identity token (JWT); "idtoken" is accepted too.
 *   nonce    (optional) - the raw nonce the app generated, or the exact value it gave Apple.
 *   name     (optional) - {"first": "...", "last": "..."}; Apple gives it to the app only
 *                         on the very first authorisation, so send it then.
 *                         Form-encoded: firstname / lastname.
 *   service  (optional) - external service shortname, defaults to moodle_mobile_app.
 *
 * @package    local_appleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_appleauth\apple_token;
use local_appleauth\auth_error;

define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', true);
define('REQUIRE_CORRECT_ACCESS', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/user/lib.php');

/**
 * Per-IP request throttle. Approximate sliding window backed by MUC; a small
 * over-count under heavy concurrency is acceptable for rate limiting.
 *
 * @param int $maxperminute allowed requests per IP per minute (0 disables)
 * @throws auth_error
 */
function local_appleauth_rate_limit(int $maxperminute): void {
    if ($maxperminute <= 0) {
        return;
    }
    $cache = \cache::make('local_appleauth', 'ratelimit');
    $key = 'ip_' . sha1(getremoteaddr());
    $now = time();
    $entry = $cache->get($key);
    if (!is_array($entry) || ($entry['reset'] ?? 0) <= $now) {
        $entry = ['count' => 0, 'reset' => $now + 60];
    }
    $entry['count']++;
    $cache->set($key, $entry);
    if ($entry['count'] > $maxperminute) {
        header('Retry-After: ' . max(1, $entry['reset'] - $now));
        throw new auth_error('rate_limited', 429);
    }
}

/**
 * Never-deliverable address for an Apple account that hid its e-mail
 * (`.invalid` is reserved, RFC 2606).
 *
 * @param string $sub Apple subject
 * @return string
 */
function local_appleauth_placeholder_email(string $sub): string {
    return 'apple.' . substr(preg_replace('/[^a-zA-Z0-9]/', '', $sub), 0, 120) . '@privaterelay.invalid';
}

/**
 * Find the Moodle account for an Apple profile, creating it when allowed.
 *
 * By Apple subject first — Apple may stop sending the e-mail after the first
 * login — then by verified e-mail (links an existing account), else created.
 *
 * @param \stdClass $profile from apple_token::verify()
 * @param string $firstname name the app forwarded ('' if none)
 * @param string $lastname
 * @param \stdClass $config plugin config
 * @return array [user record, bool is_new]
 * @throws auth_error
 */
function local_appleauth_resolve_user(\stdClass $profile, string $firstname, string $lastname, \stdClass $config): array {
    global $CFG, $DB;

    $where = ['deleted' => 0, 'mnethostid' => $CFG->mnet_localhost_id];
    $link = $DB->get_record('local_appleauth_links', ['sub' => $profile->sub]);
    $user = $link ? $DB->get_record('user', ['id' => $link->userid] + $where) : false;

    if (!$user && $profile->email && $profile->email_verified) {
        if ($DB->count_records('user', ['email' => $profile->email] + $where) > 1) {
            throw new auth_error('email_not_unique', 409);
        }
        $user = $DB->get_record('user', ['email' => $profile->email] + $where);
    }

    $isnew = false;
    if (!$user) {
        if (empty($config->allowcreate)) {
            throw new auth_error('user_not_found', 404);
        }
        // An address another account already uses must not be taken over.
        $email = $profile->email;
        if (!$email || $DB->record_exists('user', ['email' => $email] + $where)) {
            $email = local_appleauth_placeholder_email($profile->sub);
        }

        $localpart = ($profile->email && !$profile->is_private_email) ? strstr($profile->email, '@', true) : '';
        $base = \core_user::clean_field($localpart, 'username');
        if (empty($base)) {
            $base = 'apple' . core_text::strtolower(substr(preg_replace('/[^a-zA-Z0-9]/', '', $profile->sub), 0, 12));
        }
        $username = $base;
        for ($i = 1; $DB->record_exists('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id]); $i++) {
            $username = $base . $i;
        }

        $newuser = (object) [
            'auth' => !empty($config->newuserauth) ? $config->newuserauth : 'oauth2',
            'confirmed' => 1,
            'mnethostid' => $CFG->mnet_localhost_id,
            'username' => $username,
            'email' => $email,
            'firstname' => $firstname !== '' ? $firstname : 'Apple',
            'lastname' => $lastname !== '' ? $lastname : 'User',
            'lang' => $CFG->lang ?? 'en',
        ];
        $user = $DB->get_record('user', ['id' => user_create_user($newuser, false, true)], '*', MUST_EXIST);
        $isnew = true;
    } else if ($profile->email && $profile->email_verified
            && substr($user->email, -strlen('@privaterelay.invalid')) === '@privaterelay.invalid'
            && !$DB->record_exists('user', ['email' => $profile->email] + $where)) {
        // Replace the placeholder once Apple does share a verified address.
        $DB->set_field('user', 'email', $profile->email, ['id' => $user->id]);
        $user->email = $profile->email;
    }

    // SSO link policy (same as local_googleauth): only accounts whose auth method
    // is allowed may be signed into via Apple, so a Sign in with Apple cannot take
    // over a local password account that merely shares the e-mail.
    $allowedlinkauth = array_filter(array_map('trim', explode(',', (string) ($config->allowedlinkauth ?? 'oauth2'))));
    if (!in_array($user->auth, $allowedlinkauth ?: ['oauth2'], true)) {
        throw new auth_error('auth_not_linkable', 403);
    }

    $now = time();
    if ($link) {
        $DB->update_record('local_appleauth_links', (object) [
            'id' => $link->id, 'userid' => $user->id, 'email' => $profile->email ?? $link->email, 'timemodified' => $now,
        ]);
    } else {
        $DB->insert_record('local_appleauth_links', (object) [
            'userid' => $user->id, 'sub' => $profile->sub, 'email' => $profile->email,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    return [$user, $isnew];
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = get_config('local_appleauth');

// CORS: the site's own origin only. Native apps send no Origin header.
$wwwrootparts = parse_url($CFG->wwwroot);
$siteorigin = ($wwwrootparts['scheme'] ?? 'https') . '://' . ($wwwrootparts['host'] ?? '')
    . (isset($wwwrootparts['port']) ? ':' . $wwwrootparts['port'] : '');
if (($_SERVER['HTTP_ORIGIN'] ?? '') === $siteorigin) {
    header('Access-Control-Allow-Origin: ' . $siteorigin);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Vary: Origin');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    // Only accept POST so the identity token never lands in URLs/logs.
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new auth_error('method_not_allowed', 405);
    }
    if (empty($CFG->enablewebservices)) {
        throw new auth_error('webservices_disabled', 503);
    }
    if (empty($config->enabled)) {
        throw new auth_error('plugin_disabled', 503);
    }
    local_appleauth_rate_limit((int) ($config->ratelimit ?? 20));

    // Form-encoded or JSON body; "id_token" and "idtoken" both accepted.
    $json = json_decode((string) file_get_contents('php://input'), true);
    $json = is_array($json) ? $json : [];
    $field = static function (string ...$names) use ($json): string {
        foreach ($names as $name) {
            $value = optional_param($name, '', PARAM_RAW);
            if ($value === '' && isset($json[$name]) && is_scalar($json[$name])) {
                $value = (string) $json[$name];
            }
            if (trim($value) !== '') {
                return trim($value);
            }
        }
        return '';
    };

    $idtoken = $field('id_token', 'idtoken');
    if ($idtoken === '') {
        throw new auth_error('idtoken_missing', 400);
    }
    $nonce = $field('nonce');
    $serviceshortname = preg_replace('/[^a-zA-Z0-9_-]/', '', $field('service')) ?: 'moodle_mobile_app';
    $name = is_array($json['name'] ?? null) ? $json['name'] : [];
    $clean = static fn($v) => core_text::substr(trim(clean_param((string) $v, PARAM_NOTAGS)), 0, 100);
    $firstname = $clean($name['first'] ?? $field('firstname'));
    $lastname = $clean($name['last'] ?? $field('lastname'));

    // Configured, accepted audiences: one per line or comma-separated.
    $audiences = array_values(array_filter(preg_split('/[\s,]+/', (string) ($config->clientids ?? ''))));
    $profile = apple_token::verify($idtoken, $audiences, $nonce !== '' ? $nonce : null);

    [$user, $isnew] = local_appleauth_resolve_user($profile, $firstname, $lastname, $config);

    // Standard account gates (mirror /login/token.php).
    if (isguestuser($user)) {
        throw new auth_error('guest_not_allowed', 403);
    }
    if (!empty($user->suspended)) {
        throw new auth_error('user_suspended', 403);
    }
    if (empty($user->confirmed)) {
        throw new auth_error('user_not_confirmed', 403);
    }
    $systemcontext = context_system::instance();
    if (!empty($CFG->maintenance_enabled)
            && !has_capability('moodle/site:maintenanceaccess', $systemcontext, $user)) {
        throw new auth_error('site_maintenance', 503);
    }

    $service = $DB->get_record('external_services', ['shortname' => $serviceshortname, 'enabled' => 1]);
    if (!$service) {
        throw new auth_error('service_not_available', 404);
    }

    // Set the current user and mint the web service token.
    enrol_check_plugins($user);
    \core\session\manager::set_user($user);
    $token = \core_external\util::generate_token_for_current_user($service);
    \core_external\util::log_token_request($token);

    // A reused token row without a privatetoken breaks tool_mobile_get_autologin_key
    // (see local_googleauth/token.php); backfill it the way core does.
    if (empty($token->privatetoken)) {
        $token->privatetoken = random_string(64);
        $DB->set_field('external_tokens', 'privatetoken', $token->privatetoken, ['id' => $token->id]);
    }

    $siteadmin = has_capability('moodle/site:config', $systemcontext, $user->id);
    echo json_encode([
        'token' => $token->token,
        // Same policy as core /login/token.php: only to non-admins over HTTPS.
        'privatetoken' => (is_https() && !$siteadmin) ? $token->privatetoken : null,
        'userid' => (int) $user->id,
        'is_new' => $isnew,
    ]);
} catch (auth_error $e) {
    http_response_code($e->status);
    echo json_encode(['error' => $e->errorcode] + $e->extra);
}
