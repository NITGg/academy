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
 * "Delete my account" (AC-4.5.7).
 *
 * Deletes only the account of whoever is signed in - there is no user id
 * parameter, by design. An administrator removing somebody else's account does it
 * from Moodle's own user management, where it is audited as an administrative
 * action; letting this page take a target would turn a self-service screen into a
 * second, less careful way of doing the same thing.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_profilefields\account;
use local_profilefields\accountdeletion;
use local_profilefields\form\deleteaccount_form;

require_login();

if (isguestuser()) {
    redirect(new moodle_url('/'), get_string('noguest'), null,
        \core\output\notification::NOTIFY_ERROR);
}

$url = new moodle_url('/local/profilefields/deleteaccount.php');

$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('deleteaccount', 'local_profilefields'));
$PAGE->set_heading(get_string('deleteaccount', 'local_profilefields'));

// An administrator has to keep their account: a site that can be left with nobody
// able to administer it is a worse outcome than an inconvenient account.
if (!accountdeletion::allowed($USER)) {
    redirect(account::url(),
        get_string('deleteaccountrefused', 'local_profilefields'),
        null, \core\output\notification::NOTIFY_ERROR);
}

$form = new deleteaccount_form($url);

if ($form->is_cancelled()) {
    redirect(account::url());

} else if ($form->get_data()) {
    // Take a copy before the record is scrambled, so the goodbye can still be
    // addressed and sent - after this call the address on the row is a hash.
    $account = clone $USER;

    if (!accountdeletion::execute($account)) {
        redirect($url, get_string('deleteaccountrefused', 'local_profilefields'),
            null, \core\output\notification::NOTIFY_ERROR);
    }

    // Sent to the address as it was, not as it now is. Unconditional, like the
    // other security mail: somebody whose account was deleted by an attacker who
    // had their session needs to hear about it, and AC-4.5.5 puts these beyond
    // the reach of the preference screen anyway.
    try {
        email_to_user(
            $account,
            core_user::get_support_user(),
            get_string('deleteaccountdonesubject', 'local_profilefields'),
            get_string('deleteaccountdonebody', 'local_profilefields', (object) [
                'firstname' => $account->firstname,
                'sitename' => format_string(get_site()->fullname),
            ])
        );
    } catch (Throwable $e) {
        debugging('local_profilefields: could not send the account-deletion notice: '
            . $e->getMessage(), DEBUG_DEVELOPER);
    }

    // The session was destroyed inside execute(); require_logout() clears what is
    // left of it in this request and puts the browser back to a signed-out state.
    require_logout();

    redirect(new moodle_url('/'), get_string('deleteaccountdone', 'local_profilefields'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

// The confirmation box is a password box, and a password box with no reveal
// control is one you cannot check before committing to something irreversible.
account::password_toggle();

echo $OUTPUT->header();

// WF-5.3 is a pane of the account screen, not a page of its own, so it draws
// itself inside the same navigation box as the rest of it.
account::open(account::SECTION_DELETE);

echo html_writer::start_div('nit-account__card nit-account__card--danger');
echo html_writer::tag('h2', get_string('deleteaccount', 'local_profilefields'),
    ['class' => 'nit-account__cardtitle']);
$form->display();
echo html_writer::end_div();

account::close();

echo $OUTPUT->footer();
