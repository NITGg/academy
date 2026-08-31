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
 * The learner's own account screen - WF-5.1 (profile) and WF-5.2 (security).
 *
 * Your own account only. There is no user id parameter, by design: an
 * administrator editing somebody else does it from Moodle's user management,
 * where it is audited as an administrative act. A screen that took a target would
 * be a second, less careful way of doing the same thing.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/user/editlib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
// file_prepare_draft_area() below. setup.php only loads filelib on a proxied
// site, so a page that calls it has to ask for it rather than inherit it from
// whatever else happened to be included first.
require_once($CFG->libdir . '/filelib.php');

use local_profilefields\account;
use local_profilefields\form\account_profile_form;
use local_profilefields\form\changeemail_form;
use local_profilefields\profile_api;

require_login();

if (isguestuser()) {
    redirect(new moodle_url('/'), get_string('noguest'), null,
        \core\output\notification::NOTIFY_ERROR);
}

$section = optional_param('section', account::SECTION_PROFILE, PARAM_ALPHA);
if (!in_array($section, account::OWN_SECTIONS, true)) {
    $section = account::SECTION_PROFILE;
}

// The email address is changed on a card of its own rather than in the profile
// form, so this is a mode of the profile pane and not a section in its own right.
$changeemail = ($section === account::SECTION_PROFILE) && optional_param('changeemail', 0, PARAM_BOOL);

$context = context_user::instance($USER->id);
$url = account::url($section);
if ($changeemail) {
    $url->param('changeemail', 1);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('accounttitle', 'local_profilefields'));
$PAGE->set_heading(get_string('accounttitle', 'local_profilefields'));
$PAGE->add_body_class('nit-account-page');

// The form to draw inside the pane, and the static HTML around it.
$form = null;
$before = '';
$after = '';
$cardtitle = '';
$cardlead = '';

if ($section === account::SECTION_SECURITY) {
    // ---------------------------------------------------------------- WF-5.2

    $cardtitle = get_string('navsecurity', 'local_profilefields');

    // AC-4.5.2's two facts about changing a password, said before it is changed
    // rather than discovered afterwards when every other session has dropped.
    $changed = account::password_changed((int) $USER->id);
    $when = $changed
        ? get_string('passwordlastchanged', 'local_profilefields', userdate($changed, get_string('strftimedate')))
        : get_string('passwordlastchangedunknown', 'local_profilefields');

    // An account that signs in through Google has no password here to change, so
    // it is told where its password actually lives instead of being sent to a
    // core screen that would turn it away.
    $canchange = account::can_verify_password($USER);

    $control = $canchange
        ? html_writer::link(
            new moodle_url('/login/change_password.php'),
            get_string('changepassword'),
            ['class' => 'btn btn-secondary nit-account__inlinebtn'])
        : '';

    $before = account::card(
        get_string('password'),
        html_writer::div(
            html_writer::div(
                html_writer::div($canchange ? $when
                    : get_string('passwordexternal', 'local_profilefields'),
                    'nit-account__readvalue')
                . $control,
                'nit-account__inlinerow')
            . ($canchange
                ? html_writer::div(get_string('passwordchangehelp', 'local_profilefields'),
                    'nit-account__help')
                : ''),
            ''
        )
    );

} else if ($changeemail) {
    // ------------------------------------------------- WF-5.1, "Change" email

    // The button that leads here is not drawn for an account without a local
    // password, but the URL can still be typed. Refused here rather than handed a
    // form whose password box it could never satisfy.
    if (!account::can_verify_password($USER)) {
        redirect(account::url(account::SECTION_PROFILE),
            get_string('emailchangeexternal', 'local_profilefields'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    $cardtitle = get_string('changeemailtitle', 'local_profilefields');
    $cardlead = get_string('emailchangehelp', 'local_profilefields');

    $form = new changeemail_form($url, ['user' => $USER]);

    if ($form->is_cancelled()) {
        redirect(account::url(account::SECTION_PROFILE));

    } else if ($data = $form->get_data()) {
        // Only the address is touched. profile_api::save() is what the mobile app
        // calls, so the confirmation key, the mail and the "not applied until
        // confirmed" rule are the same ones on both clients rather than a second
        // implementation that has to be kept in step.
        $usernew = clone $USER;
        $usernew->email = core_text::strtolower(trim($data->newemail));

        $pending = profile_api::save($USER, $usernew);

        redirect(
            account::url(account::SECTION_PROFILE),
            $pending !== ''
                ? get_string('changeemailsent', 'local_profilefields', s($pending))
                : get_string('changesaved', 'local_profilefields'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

} else {
    // ---------------------------------------------------------------- WF-5.1

    $cardtitle = get_string('navprofile', 'local_profilefields');

    $filemanageroptions = [
        'maxbytes' => account_profile_form::PICTURE_MAXBYTES,
        'subdirs' => 0,
        'maxfiles' => 1,
        'accepted_types' => ['web_image'],
    ];

    $user = clone $USER;
    profile_load_data($user);

    $draftitemid = 0;
    file_prepare_draft_area($draftitemid, $context->id, 'user', 'newicon', 0, $filemanageroptions);
    $user->imagefile = $draftitemid;

    $form = new account_profile_form($url, [
        'user' => $user,
        'picture' => $OUTPUT->user_picture($USER, ['size' => 100, 'link' => false]),
        'lockedgroup' => account::locked_group($USER),
        'canchangeemail' => account::can_verify_password($USER),
    ]);

    $form->set_data($user);

    if ($form->is_cancelled()) {
        redirect(account::url(account::SECTION_PROFILE));

    } else if ($data = $form->get_data()) {
        // Rebuilt from the stored record rather than from the posted one, so a
        // field that is not on this form cannot be set by adding it to the POST.
        $usernew = clone $USER;
        $usernew->id = $USER->id;
        $usernew->firstname = trim($data->firstname);
        $usernew->lastname = trim($data->lastname);
        $usernew->lang = $data->lang;

        // The email is never taken from this form - it has no email box. Saying so
        // explicitly stops profile_api::save() from starting a change nobody asked
        // for if a stale value ever reaches it.
        $usernew->email = $USER->email;

        foreach ((array) $data as $field => $value) {
            if (strpos($field, 'profile_field_') === 0) {
                $usernew->$field = $value;
            }
        }

        profile_api::save($USER, $usernew);

        if (empty($CFG->disableuserimages)) {
            $usernew->imagefile = $data->imagefile;
            $usernew->deletepicture = !empty($data->deletepicture);
            core_user::update_picture($usernew, $filemanageroptions);
        }

        redirect(account::url(account::SECTION_PROFILE),
            get_string('changesaved', 'local_profilefields'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

echo $OUTPUT->header();

account::open($section);

echo $before;

if ($form !== null) {
    echo html_writer::start_div('nit-account__card');
    if ($cardtitle !== '') {
        echo html_writer::tag('h2', $cardtitle, ['class' => 'nit-account__cardtitle']);
    }
    if ($cardlead !== '') {
        echo html_writer::div($cardlead, 'nit-account__cardlead');
    }
    $form->display();
    echo html_writer::end_div();
}

echo $after;

account::close();

echo $OUTPUT->footer();
