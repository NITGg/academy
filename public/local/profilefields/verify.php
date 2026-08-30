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
 * The screen a learner sees between signing up and confirming their address.
 *
 * AC-4.2.8 words the notice, AC-4.2.2 puts a live countdown on the resend, and
 * AC-4.2.5 requires a way to correct a mistyped address without registering
 * again. Core has none of the three: it prints a static "we sent you an email"
 * box on the login page and leaves it there.
 *
 * Who may see this page
 * ---------------------
 * Nobody is logged in here - the whole point is that the account is not usable
 * yet - so the page is addressed by user id. That id is not a credential and is
 * easily guessed, which shapes everything the page is allowed to do:
 *
 * - it shows the address only masked, never in full;
 * - it will not touch an account that is already confirmed;
 * - resending is bounded by the same limits enforced in hook_callbacks, so
 *   walking the id space is a way to send somebody five emails, not more;
 * - changing the address demands the account's password, which is the one thing
 *   the id does not give you.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_profilefields\verification;

$id = required_param('id', PARAM_INT);
$expired = optional_param('expired', 0, PARAM_BOOL);
$action = optional_param('action', '', PARAM_ALPHA);

$PAGE->set_url(new moodle_url('/local/profilefields/verify.php', ['id' => $id]));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');
$PAGE->set_title(get_string('verifyheading', 'local_profilefields'));
$PAGE->set_heading(format_string($SITE->fullname));

$user = $DB->get_record('user', [
    'id' => $id,
    'mnethostid' => $CFG->mnet_localhost_id,
    'deleted' => 0,
], '*', IGNORE_MISSING);

// An account that does not exist and one that is already confirmed are told the
// same thing, on the same page, so that this URL cannot be used to find out
// which addresses are registered.
if (!$user || !empty($user->confirmed)) {
    redirect(new moodle_url('/login/index.php'),
        get_string('verifyalreadydone', 'local_profilefields'),
        null, \core\output\notification::NOTIFY_INFO);
}

$returnurl = new moodle_url('/local/profilefields/verify.php', ['id' => $id]);

// ---------------------------------------------------------------------------
// Resend (AC-4.2.2, AC-4.2.3, AC-4.2.4).
// ---------------------------------------------------------------------------
if ($action === 'resend' && confirm_sesskey()) {
    $refusal = verification::refuse_resend($user);

    if ($refusal !== null) {
        redirect($returnurl, $refusal, null, \core\output\notification::NOTIFY_WARNING);
    }

    // Rotate before sending, so the mail that goes out is the only live link.
    verification::rotate_secret($user);

    if (send_confirmation_email($user)) {
        redirect($returnurl, get_string('verifyresent', 'local_profilefields'),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }

    redirect($returnurl, get_string('emailconfirmsentfailure'),
        null, \core\output\notification::NOTIFY_ERROR);
}

// ---------------------------------------------------------------------------
// Correct the address (AC-4.2.5).
// ---------------------------------------------------------------------------
$form = new \local_profilefields\form\changeemail_form($PAGE->url, ['user' => $user]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/login/index.php'));

} else if ($data = $form->get_data()) {
    $newemail = core_text::strtolower(trim($data->newemail));

    // The username is derived from the address on this site, so it moves with it;
    // leaving it behind would strand the account under a name matching an address
    // it no longer has.
    $update = (object) ['id' => $user->id, 'email' => $newemail];
    if (\local_profilefields\manager::username_from_email()) {
        $update->username = \local_profilefields\manager::derive_username($newemail);
    }
    $DB->update_record('user', $update);

    $user = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);

    // A new address gets a new link, and the old address's link dies with the
    // secret it was built on.
    verification::rotate_secret($user);
    send_confirmation_email($user);

    redirect($returnurl, get_string('verifychangeemailsaved', 'local_profilefields'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// ---------------------------------------------------------------------------
// Render.
// ---------------------------------------------------------------------------
echo $OUTPUT->header();

if ($expired) {
    echo $OUTPUT->notification(get_string('verifylinkexpired', 'local_profilefields'),
        \core\output\notification::NOTIFY_ERROR);
}

echo $OUTPUT->heading(get_string('verifyheading', 'local_profilefields'), 3);

// AC-4.2.8's two sentences. The address is masked because this page is reachable
// by guessing an id.
echo html_writer::tag('p',
    get_string('verifysent', 'local_profilefields',
        \local_profilefields\form\changeemail_form::mask($user->email)));
echo html_writer::tag('p', get_string('verifysentdetail', 'local_profilefields'),
    ['class' => 'text-muted']);

$wait = verification::seconds_until_resend($user);
$exhausted = verification::send_limit_reached($user);

if ($exhausted) {
    echo $OUTPUT->notification(get_string('verifyresendtoomany', 'local_profilefields'),
        \core\output\notification::NOTIFY_WARNING);
} else {
    $resendurl = new moodle_url('/local/profilefields/verify.php', [
        'id' => $id, 'action' => 'resend', 'sesskey' => sesskey(),
    ]);

    // The button starts disabled whenever a wait is outstanding, and the module
    // below counts it down and enables it. With JS off it is simply a link the
    // server will refuse until the wait is over - which is the same rule, said
    // less gracefully.
    echo html_writer::tag('a', get_string('verifyresend', 'local_profilefields'), [
        'href' => $resendurl->out(false),
        'class' => 'btn btn-primary' . ($wait > 0 ? ' disabled' : ''),
        'data-nit-resend' => '1',
        'data-wait' => $wait,
        'data-label' => get_string('verifyresend', 'local_profilefields'),
        'aria-disabled' => $wait > 0 ? 'true' : 'false',
    ]);

    if ($wait > 0) {
        $PAGE->requires->js_call_amd('local_profilefields/resendcountdown', 'init', [[
            'waitLabel' => get_string('verifyresendwait', 'local_profilefields', '{seconds}'),
        ]]);
    }
}

echo html_writer::tag('h4', get_string('verifychangeemail', 'local_profilefields'), ['class' => 'mt-4 h5']);
$form->display();

echo $OUTPUT->footer();
