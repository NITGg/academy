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
 * Finish a registration that never went through the sign-up form.
 *
 * An OAuth2 login ("Log in with Google") creates the account from the provider's
 * claims and skips `login/signup.php` entirely, so everything the academy added
 * to sign-up - phone, country, the terms checkbox - was never asked for. This
 * page asks for exactly what is outstanding and nothing else.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

use local_profilefields\completion;
use local_profilefields\form\complete_form;
use local_profilefields\signup;

// require_login() MUST NOT be used here. It runs user_not_fully_set_up(), which
// would bounce an incomplete user to /user/edit.php before this page ever draws -
// and our own gate would bounce them back. /user/edit.php avoids the same loop
// the same way (see the comment at user/edit.php:105).
if (!isloggedin() || isguestuser()) {
    $SESSION->wantsurl = $SESSION->wantsurl ?? (new moodle_url('/local/profilefields/complete.php'))->out(false);
    redirect(get_login_url());
}

$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$PAGE->set_url(completion::url());
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('completetitle', 'local_profilefields'));
$PAGE->set_heading(get_string('completetitle', 'local_profilefields'));

// Nothing outstanding: either they just finished, or they typed the URL. Either
// way this page has no business holding them up.
$missing = completion::missing($USER);
if (completion::is_complete($USER)) {
    redirect($returnurl !== '' ? new moodle_url($returnurl) : new moodle_url('/'));
}

$form = new complete_form(completion::url(), ['missing' => $missing]);
$form->set_data(['returnurl' => $returnurl]);

if ($data = $form->get_data()) {
    $usernew = (object) ['id' => $USER->id];

    foreach ($missing['fields'] as $entry) {
        $name = $entry['name'];
        if ($entry['kind'] === 'core' && isset($data->$name)) {
            $usernew->$name = $data->$name;
        }
    }

    // The terms checkbox records the same flag `auth_email_signup_user()` sets
    // when a site policy is defined - see local_profilefields\signup_api.
    if (!empty($missing['consent']) && !empty($data->{signup::CONSENT})) {
        $usernew->policyagreed = 1;
    }

    if (count((array) $usernew) > 1) {
        // user_update_user() rather than a raw update_record(): it stamps
        // timemodified and fires \core\event\user_updated, which anything watching
        // for a profile change (and the app's own sync) relies on.
        user_update_user($usernew, false, true);
    }

    // Custom fields save themselves, exactly as they do from /user/edit.php.
    $data->id = $USER->id;
    profile_save_data($data);

    // Refresh the session copy, otherwise the gate would fire again on the very
    // next page and bounce the user straight back here. `fullysetupstrict` is
    // core's own one-hour cache of the same answer, so it has to go too.
    foreach ((array) $usernew as $key => $value) {
        $USER->$key = $value;
    }
    profile_load_custom_fields($USER);
    unset($SESSION->fullysetupstrict);

    \core\notification::success(get_string('completedone', 'local_profilefields'));

    redirect($returnurl !== '' ? new moodle_url($returnurl) : new moodle_url('/'));
}

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox local-profilefields-complete');
echo html_writer::tag('p', get_string('completeintro', 'local_profilefields'), ['class' => 'lead']);
$form->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
