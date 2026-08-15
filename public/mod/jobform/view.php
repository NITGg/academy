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
 * Displays a Job Form activity.
 *
 *  - Managers / teachers see the field editor (default) and a submissions tab.
 *  - Students see the form once they have earned the linked certificate.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_jobform\fields_ui;
use mod_jobform\instance_manager;
use mod_jobform\submission_manager;
use mod_jobform\form\entry_form;
use mod_jobform\output\activity_submissions;

$id = required_param('id', PARAM_INT); // Course module id.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'jobform');
$jobform = $DB->get_record('jobform', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/jobform:view', $context);

$tab = optional_param('tab', 'fields', PARAM_ALPHA);
$cansmanage = has_capability('mod/jobform:managefields', $context);

$PAGE->set_url(new moodle_url('/mod/jobform/view.php', ['id' => $cm->id, 'tab' => $tab]));
$PAGE->set_title(format_string($jobform->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->add_body_class('mod-jobform');

$viewurl = new moodle_url('/mod/jobform/view.php', ['id' => $cm->id]);
$fieldsurl = new moodle_url('/mod/jobform/view.php', ['id' => $cm->id, 'tab' => 'fields']);

// Log the view and update completion.
$event = \core\event\course_module_viewed::create([
    'objectid' => $jobform->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('jobform', $jobform);
$event->trigger();
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// ---------------------------------------------------------------------------
// Manager / teacher field actions (delete / reorder), before any output.
// ---------------------------------------------------------------------------
if ($cansmanage) {
    $fieldaction = optional_param('fieldaction', '', PARAM_ALPHA);
    $fieldid = optional_param('fieldid', 0, PARAM_INT);
    $confirm = optional_param('confirm', 0, PARAM_BOOL);

    if ($fieldaction === 'moveup' && $fieldid > 0 && confirm_sesskey()) {
        instance_manager::reorder($fieldid, $jobform->id, -1);
        redirect($fieldsurl);
    } else if ($fieldaction === 'movedown' && $fieldid > 0 && confirm_sesskey()) {
        instance_manager::reorder($fieldid, $jobform->id, 1);
        redirect($fieldsurl);
    } else if ($fieldaction === 'delete' && $fieldid > 0 && confirm_sesskey() && $confirm) {
        instance_manager::delete_field($fieldid, $jobform->id);
        redirect($fieldsurl, get_string('changessaved'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

// ---------------------------------------------------------------------------
// Student form submission handling, before output.
// ---------------------------------------------------------------------------
$studentmode = !$cansmanage && has_capability('mod/jobform:submit', $context);
$fields = instance_manager::get_fields($jobform->id);
$entryform = null;

if ($studentmode) {
    $gated = !submission_manager::has_certificate($jobform, $USER->id);
    $existing = submission_manager::get_submission($jobform->id, $USER->id);
    $locked = $existing && $existing->status === submission_manager::STATUS_SUBMITTED
        && empty($jobform->allowresubmit);

    if (!$gated && !$locked) {
        $entryform = new entry_form($viewurl, ['fields' => array_values($fields)]);

        if ($entryform->is_cancelled()) {
            redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
        } else if ($data = $entryform->get_data()) {
            $status = !empty($data->savedraft)
                ? submission_manager::STATUS_DRAFT
                : submission_manager::STATUS_SUBMITTED;
            $values = entry_form::normalize_values($data, array_values($fields));
            submission_manager::save($jobform->id, $USER->id, $values, $status);
            $msg = $status === submission_manager::STATUS_SUBMITTED
                ? get_string('formsent', 'mod_jobform')
                : get_string('draftsaved', 'mod_jobform');
            redirect($viewurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($existing) {
            // Prime with any saved values.
            $answers = submission_manager::get_answers($existing->id);
            $entryform->set_data(
                ['id' => $cm->id] + entry_form::values_to_formdata($answers, array_values($fields)));
        } else {
            $entryform->set_data(['id' => $cm->id]);
        }
    }
}

// ---------------------------------------------------------------------------
// Output.
// ---------------------------------------------------------------------------
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($jobform->name));

if (!empty($jobform->intro)) {
    echo $OUTPUT->box(format_module_intro('jobform', $jobform, $cm->id), 'generalbox mod_introbox');
}

if ($cansmanage) {
    // Confirm dialog before deleting a field.
    $fieldaction = optional_param('fieldaction', '', PARAM_ALPHA);
    $fieldid = optional_param('fieldid', 0, PARAM_INT);
    if ($fieldaction === 'delete' && $fieldid > 0 && confirm_sesskey()
            && !optional_param('confirm', 0, PARAM_BOOL)) {
        $field = instance_manager::get_field($fieldid, $jobform->id);
        if ($field) {
            $yesurl = new moodle_url($fieldsurl,
                ['fieldaction' => 'delete', 'fieldid' => $fieldid, 'sesskey' => sesskey(), 'confirm' => 1]);
            echo $OUTPUT->confirm(
                get_string('confirmdeletefield', 'local_jobform') . ' (' . format_string($field->name) . ')',
                $yesurl, $fieldsurl);
            echo $OUTPUT->footer();
            exit;
        }
    }

    // Tabs: Fields (default) + Submissions.
    $tabs = [
        new tabobject('fields', $fieldsurl, get_string('tabfields', 'local_jobform')),
        new tabobject('submissions',
            new moodle_url('/mod/jobform/view.php', ['id' => $cm->id, 'tab' => 'submissions']),
            get_string('tabsubmissions', 'local_jobform')),
    ];
    echo $OUTPUT->tabtree($tabs, $tab);

    if ($tab === 'submissions') {
        require_capability('mod/jobform:viewsubmissions', $context);
        echo activity_submissions::render($cm, $jobform);
    } else {
        echo html_writer::div(get_string('activityfieldsintro', 'mod_jobform'), 'text-muted mb-3');
        $editurl = new moodle_url('/mod/jobform/edit_field.php', ['id' => $cm->id]);
        echo fields_ui::render($fields, $editurl, $fieldsurl);
    }
} else if ($studentmode) {
    if (submission_manager::has_certificate($jobform, $USER->id) === false) {
        echo $OUTPUT->notification(get_string('certificaterequired', 'mod_jobform'),
            \core\output\notification::NOTIFY_WARNING);
    } else if ($entryform === null) {
        // Locked: already submitted and resubmission disabled — show read-only answers.
        echo $OUTPUT->notification(get_string('alreadysubmitted', 'mod_jobform'),
            \core\output\notification::NOTIFY_INFO);
        $existing = submission_manager::get_submission($jobform->id, $USER->id);
        $readform = new entry_form($viewurl, ['fields' => array_values($fields), 'readonly' => true]);
        $answers = submission_manager::get_answers($existing->id);
        $readform->set_data(
            ['id' => $cm->id] + entry_form::values_to_formdata($answers, array_values($fields)));
        $readform->display();
    } else {
        if (!$fields) {
            echo $OUTPUT->notification(get_string('noformfields', 'mod_jobform'),
                \core\output\notification::NOTIFY_INFO);
        } else {
            $entryform->display();
        }
    }
} else {
    echo $OUTPUT->notification(get_string('nothingtodisplay', 'mod_jobform'),
        \core\output\notification::NOTIFY_INFO);
}

echo $OUTPUT->footer();
