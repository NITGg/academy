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
 * Return URL of Sign in with Apple on the website (register it on the Services ID).
 *
 * Apple POSTs: state, id_token, code, and - on the first authorisation only - user,
 * a JSON {"name": {"firstName", "lastName"}, "email"}; or error when the visitor
 * cancelled. The visitor is signed in and sent where they were going; any failure
 * lands on the log-in page with a message.
 *
 * @package    local_appleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_appleauth\account;
use local_appleauth\apple_token;
use local_appleauth\auth_error;
use local_appleauth\web;

require(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/login/lib.php');

$PAGE->set_url(web::callback_url());
$PAGE->set_context(context_system::instance());

$loginurl = new moodle_url('/login/index.php');

// The visitor closed Apple's page: back to log in, nothing to report.
if (optional_param('error', '', PARAM_ALPHANUMEXT) !== '') {
    redirect($loginurl);
}

try {
    if (!web::enabled()) {
        throw new auth_error('plugin_disabled', 503);
    }
    $request = web::take_state(optional_param('state', '', PARAM_ALPHANUM));
    $profile = apple_token::verify(optional_param('id_token', '', PARAM_RAW), [web::client_id()], $request['nonce']);

    // The name comes once, on the first authorisation; afterwards only the token.
    $applejson = json_decode(optional_param('user', '', PARAM_RAW), true);
    $name = is_array($applejson['name'] ?? null) ? $applejson['name'] : [];
    $clean = static fn($v) => core_text::substr(trim(clean_param((string) $v, PARAM_NOTAGS)), 0, 100);

    [$user] = account::resolve($profile, $clean($name['firstName'] ?? ''), $clean($name['lastName'] ?? ''),
        get_config('local_appleauth'));
    account::require_can_login($user);
} catch (auth_error $e) {
    // Shown in the log-in form by login/index.php, the way auth_oauth2 reports.
    $SESSION->loginerrormsg = web::error_message($e->errorcode);
    redirect($loginurl);
}

complete_user_login($user);
\core\session\manager::apply_concurrent_login_limit($user->id, session_id());

redirect($request['wantsurl'] !== '' ? $request['wantsurl'] : core_login_get_return_url());
