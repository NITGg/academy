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
 * Certificate auto-login end-point for the mobile app.
 *
 * The app authenticates its API calls with a web-service token, but a certificate lives on a normal
 * web page (/mod/customcert/view.php) that needs a logged-in browser session. This end-point lets the
 * app open that page: it redeems a one-time key minted by
 * {@see \local_academy\cert\customcert_link::mint_autologin_url()} (via the open_certificate API),
 * logs the user in, and redirects straight to the certificate.
 *
 * Security: the key is single-use (deleted on redemption), short-lived, and IP-restricted — exactly
 * the guarantees Moodle's own tool_mobile autologin relies on. It is also bound to a specific cmid,
 * and the redirect target is built here from that cmid (never taken from the request), so there is no
 * open-redirect surface: a redeemed key can only ever land on its own certificate page.
 *
 * @package    local_academy
 * @copyright  2026 Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_academy\cert\customcert_link;

$key  = required_param('key', PARAM_ALPHANUMEXT);   // One-time key from open_certificate.
$cmid = required_param('cmid', PARAM_INT);          // The customcert activity the key is bound to.

$PAGE->set_context(context_system::instance());
$target = new moodle_url('/mod/customcert/view.php', ['id' => $cmid]);

// Already logged in as the same user? Nothing to do — just go to the certificate. (If it is a
// different user, refuse rather than silently swapping sessions.)
if (isloggedin() && !isguestuser()) {
    $key = validate_user_key($key, customcert_link::AUTOLOGIN_SCRIPT, (string)$cmid);
    delete_user_key(customcert_link::AUTOLOGIN_SCRIPT, $key->userid);
    if ((int)$USER->id === (int)$key->userid) {
        redirect($target);
    }
    throw new moodle_exception('alreadyloggedin', 'error', '', format_string(fullname($USER)));
}

// Validate the key (checks expiry + IP + cmid binding, dies on failure), then burn it: single use.
$key = validate_user_key($key, customcert_link::AUTOLOGIN_SCRIPT, (string)$cmid);
delete_user_key(customcert_link::AUTOLOGIN_SCRIPT, $key->userid);

// Load the user the key belongs to and make sure they are still allowed to log in.
$user = core_user::get_user($key->userid, '*', MUST_EXIST);
core_user::require_active_user($user, true, true);
if (!$user = get_complete_user_data('id', $user->id)) {
    throw new moodle_exception('cannotfinduser', '', '', $key->userid);
}

complete_user_login($user);
\core\session\manager::apply_concurrent_login_limit($user->id, session_id());

redirect($target);
