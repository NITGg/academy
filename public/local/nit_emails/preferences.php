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
 * "Which emails do you want from us?" (AC-4.5.5).
 *
 * Marketing rows are switches. Transactional and security rows are shown too -
 * with a tick and no control - because the specification requires the interface
 * to state that they cannot be turned off, and a screen that simply omitted them
 * would leave a learner wondering what else we send.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_nit_emails\preferences;

require_login();

if (isguestuser()) {
    redirect(new moodle_url('/'), get_string('noguest'), null,
        \core\output\notification::NOTIFY_ERROR);
}

$url = new moodle_url('/local/nit_emails/preferences.php');

$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('prefstitle', 'local_nit_emails'));
$PAGE->set_heading(get_string('prefstitle', 'local_nit_emails'));

if (optional_param('save', 0, PARAM_BOOL) && confirm_sesskey()) {
    // Only the optional kinds are read from the form. A crafted POST naming a
    // transactional kind changes nothing, because save() intersects what it is
    // given with the marketing list rather than trusting it.
    $wanted = [];
    foreach (preferences::kinds_in(preferences::GROUP_MARKETING) as $kind) {
        if (optional_param('kind_' . $kind, 0, PARAM_BOOL)) {
            $wanted[] = $kind;
        }
    }

    preferences::save((int) $USER->id, $wanted);

    redirect($url, get_string('prefssaved', 'local_nit_emails'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

/**
 * One row of the table.
 *
 * @param string $kind the email kind
 * @param bool $optional whether the learner may switch it off
 * @param bool $on whether it is currently on
 * @return string HTML
 */
function local_nit_emails_pref_row(string $kind, bool $optional, bool $on): string {
    $label = html_writer::div(
        html_writer::span(get_string('kind_' . $kind, 'local_nit_emails'), 'fw-semibold') .
        html_writer::div(get_string('kind_' . $kind . '_desc', 'local_nit_emails'), 'text-muted small')
    );

    if ($optional) {
        $control = html_writer::empty_tag('input', [
            'type' => 'checkbox', 'name' => 'kind_' . $kind, 'value' => 1,
            'class' => 'form-check-input', 'id' => 'kind_' . $kind,
        ] + ($on ? ['checked' => 'checked'] : []));
    } else {
        // A ticked, disabled box plus the sentence AC-4.5.5 asks for. Disabled
        // rather than absent so the row reads as "always on", not "unavailable".
        $control = html_writer::empty_tag('input', [
            'type' => 'checkbox', 'checked' => 'checked', 'disabled' => 'disabled',
            'class' => 'form-check-input',
        ]) . html_writer::div(
            get_string('lockedmessages', 'local_profilefields'), 'text-muted small mt-1'
        );
    }

    return html_writer::tag('tr',
        html_writer::tag('td', $label) .
        html_writer::tag('td', $control, ['class' => 'text-center', 'style' => 'width:12rem']));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('prefstitle', 'local_nit_emails'));
echo html_writer::tag('p', get_string('prefsintro', 'local_nit_emails'), ['class' => 'text-muted']);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);

$optedout = preferences::opted_out((int) $USER->id);

foreach ([
    preferences::GROUP_MARKETING,
    preferences::GROUP_TRANSACTIONAL,
    preferences::GROUP_SECURITY,
] as $group) {
    $kinds = preferences::kinds_in($group);
    if (!$kinds) {
        continue;
    }

    echo html_writer::tag('h3', get_string('group_' . $group, 'local_nit_emails'),
        ['class' => 'h5 mt-4']);

    $rows = '';
    foreach ($kinds as $kind) {
        $optional = preferences::is_optional($kind);
        $rows .= local_nit_emails_pref_row($kind, $optional,
            !$optional || !in_array($kind, $optedout, true));
    }

    echo html_writer::tag('table', $rows, ['class' => 'generaltable w-100']);
}

echo html_writer::tag('div',
    html_writer::tag('button', get_string('savechanges'),
        ['type' => 'submit', 'class' => 'btn btn-primary']),
    ['class' => 'mt-3']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
