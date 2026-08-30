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
 * The review queue for instructor background changes (AC-4.5.14, AC-4.5.15).
 *
 * Each waiting change is shown as the learner would see it, beside the version it
 * would replace. That side-by-side is the point of the screen: an administrator
 * approving a change needs to see what is changing, and a queue that showed only
 * the new text would make "approve" a rubber stamp.
 *
 * @package    local_nit_instructors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_nit_instructors\output;
use local_nit_instructors\profile;

require_login();

$context = context_system::instance();
require_capability('local/nit_instructors:review', $context);

$url = new moodle_url('/local/nit_instructors/manage.php');

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('reviewqueue', 'local_nit_instructors'));
$PAGE->set_heading(get_string('reviewqueue', 'local_nit_instructors'));

$approve = optional_param('approve', 0, PARAM_INT);
$reject = optional_param('reject', 0, PARAM_INT);
$note = optional_param('note', '', PARAM_TEXT);

if (($approve || $reject) && confirm_sesskey()) {
    if ($approve) {
        $done = profile::approve($approve, $note);
        $message = $done ? 'approved' : 'decisionfailed';
    } else {
        // AC-4.5.15 shows the administrator's reason to the instructor, so a
        // rejection without one would leave them told "no" and nothing else.
        if (trim($note) === '') {
            redirect($url, get_string('reasonrequired', 'local_nit_instructors'),
                null, \core\output\notification::NOTIFY_ERROR);
        }
        $done = profile::reject($reject, $note);
        $message = $done ? 'rejected' : 'decisionfailed';
    }

    redirect($url, get_string($message, 'local_nit_instructors'), null,
        $done ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reviewqueue', 'local_nit_instructors'));

$queue = profile::queue();

if (!$queue) {
    echo html_writer::div(get_string('queueempty', 'local_nit_instructors'), 'alert alert-info');
    echo $OUTPUT->footer();
    die;
}

echo html_writer::tag('p', get_string('queueintro', 'local_nit_instructors'),
    ['class' => 'text-muted']);

foreach ($queue as $pending) {
    $user = $DB->get_record('user', ['id' => $pending->userid, 'deleted' => 0]);
    if (!$user) {
        continue;
    }

    $current = profile::approved((int) $pending->userid);

    echo html_writer::start_div('card mb-4');
    echo html_writer::div(
        html_writer::span(fullname($user), 'fw-semibold') . ' ' .
        html_writer::span(userdate($pending->timemodified,
            get_string('strftimedatetimeshort', 'langconfig')), 'text-muted small'),
        'card-header');

    echo html_writer::start_div('card-body');
    echo html_writer::start_div('row');

    echo html_writer::div(
        html_writer::tag('h4', get_string('currentversion', 'local_nit_instructors'),
            ['class' => 'h6 text-muted']) .
        ($current
            ? output::group($current, (int) $pending->userid, false)
            : html_writer::div(get_string('nobackground', 'local_nit_instructors'), 'text-muted')),
        'col-md-6 border-end');

    echo html_writer::div(
        html_writer::tag('h4', get_string('proposedversion', 'local_nit_instructors'),
            ['class' => 'h6 text-muted']) .
        output::group($pending, (int) $pending->userid, false),
        'col-md-6');

    echo html_writer::end_div();

    // One note box serving both decisions: optional when approving, required when
    // rejecting (checked above, because a browser cannot know which button was hit).
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false),
        'class' => 'mt-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo html_writer::tag('label', get_string('decisionnote', 'local_nit_instructors'),
        ['for' => 'note' . $pending->id, 'class' => 'form-label']);
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'note', 'id' => 'note' . $pending->id,
        'class' => 'form-control mb-2',
        'placeholder' => get_string('decisionnoteplaceholder', 'local_nit_instructors'),
    ]);

    echo html_writer::tag('button', get_string('approve', 'local_nit_instructors'), [
        'type' => 'submit', 'name' => 'approve', 'value' => $pending->id,
        'class' => 'btn btn-primary me-2',
    ]);
    echo html_writer::tag('button', get_string('reject', 'local_nit_instructors'), [
        'type' => 'submit', 'name' => 'reject', 'value' => $pending->id,
        'class' => 'btn btn-outline-danger',
    ]);

    echo html_writer::end_tag('form');
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
