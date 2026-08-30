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
 * Where an instructor edits their Academic and Professional Background.
 *
 * Editing your own only. An administrator who needs to correct somebody's
 * background approves or rejects it from the review queue instead - AC-4.5.14
 * makes the administrator the reviewer, not a second author, and letting them type
 * into an instructor's profile would leave nobody able to say who wrote what.
 *
 * @package    local_nit_instructors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_nit_instructors\form\profile_form;
use local_nit_instructors\output;
use local_nit_instructors\profile;

require_login();

if (isguestuser()) {
    redirect(new moodle_url('/'), get_string('noguest'), null,
        \core\output\notification::NOTIFY_ERROR);
}

$url = new moodle_url('/local/nit_instructors/edit.php');

$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('background', 'local_nit_instructors'));
$PAGE->set_heading(get_string('background', 'local_nit_instructors'));

// AC-4.5.9: this group belongs to instructors. A learner who guesses the URL gets
// told it is not theirs, rather than a form whose contents would never be shown.
if (!profile::is_instructor((int) $USER->id)) {
    redirect(new moodle_url('/user/profile.php'),
        get_string('notaninstructor', 'local_nit_instructors'),
        null, \core\output\notification::NOTIFY_ERROR);
}

$version = profile::editable((int) $USER->id);
$entries = $version ? profile::entries((int) $version->id) : [];

$counts = [];
foreach (profile::entry_types() as $type) {
    $counts[$type] = count($entries[$type] ?? []);
}

$courses = profile::courses_taught((int) $USER->id);
$courselist = $courses
    ? html_writer::alist(array_map(
        static fn(array $c): string => html_writer::link($c['url'], s($c['fullname'])), $courses))
    : html_writer::span(get_string('nocoursestaught', 'local_nit_instructors'), 'text-muted');

$form = new profile_form($url, ['counts' => $counts, 'coursestaught' => $courselist]);

// Fill the form from the version being edited. The repeating groups want each
// field as an array indexed by slot, which is the transpose of how they are stored.
if ($version) {
    $defaults = (object) [
        'specialtyen' => $version->specialtyen,
        'specialtyar' => $version->specialtyar,
        'years' => (int) $version->years,
    ];

    foreach (profile::entry_types() as $type) {
        foreach (['titleen', 'titlear', 'orgen', 'orgar', 'perioden', 'periodar'] as $field) {
            $values = [];
            foreach ($entries[$type] ?? [] as $i => $entry) {
                $values[$i] = $entry->$field;
            }
            $defaults->{$type . '_' . $field} = $values;
        }
    }

    $form->set_data($defaults);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/user/profile.php'));

} else if ($data = $form->get_data()) {
    profile::save_draft((int) $USER->id, $data, profile_form::extract_entries($data));

    // AC-4.5.14's sentence, said at the moment it becomes true.
    redirect($url, get_string('pendingnotice', 'local_nit_instructors'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('background', 'local_nit_instructors'));

echo output::status_banner((int) $USER->id);

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/nit_instructors/view.php', ['id' => $USER->id]),
        get_string('viewpublic', 'local_nit_instructors')),
    'mb-3');

$form->display();

echo $OUTPUT->footer();
