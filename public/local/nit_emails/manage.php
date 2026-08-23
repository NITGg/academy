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
 * Site administration › Plugins › Local plugins › Purchase & registration emails.
 *
 * One tab per event (course purchase, subscription purchase, registration).
 * Each tab edits the English and the Arabic version of that email, lists the
 * placeholders it may use, and offers a preview and a test send.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_emails\form\template_form;
use local_nit_emails\mailer;
use local_nit_emails\templates;

admin_externalpage_setup('local_nit_emails_templates');
require_capability('local/nit_emails:manage', context_system::instance());

$event = optional_param('event', templates::EVENT_COURSE, PARAM_ALPHANUMEXT);
if (!templates::is_event($event)) {
    $event = templates::EVENT_COURSE;
}

$pageurl = new moodle_url('/local/nit_emails/manage.php', ['event' => $event]);
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('pluginname', 'local_nit_emails'));
$PAGE->set_heading(get_string('pluginname', 'local_nit_emails'));

// ── Test send ────────────────────────────────────────────────────────────────
$testlang = optional_param('testlang', '', PARAM_ALPHA);
if ($testlang !== '' && confirm_sesskey()) {
    $sent = mailer::send_test($event, $USER, $testlang);
    redirect($pageurl,
        get_string($sent ? 'testsent' : 'testfailed', 'local_nit_emails', s($USER->email)),
        null,
        $sent ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR);
}

// ── The template editor ──────────────────────────────────────────────────────
$form = new template_form($pageurl, ['event' => $event]);

if ($form->is_cancelled()) {
    redirect($pageurl);
}

if ($form->no_submit_button_pressed()) {
    // "Reset to defaults" — drop the stored copy so the shipped wording is used again.
    require_sesskey();
    templates::reset($event);
    redirect($pageurl, get_string('resetdone', 'local_nit_emails'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($data = $form->get_data()) {
    templates::save($event, $data);
    redirect($pageurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

if (!$form->is_submitted()) {
    // Only prime the form from storage on a fresh view — after a failed
    // validation the admin's own (unsaved) text has to survive on screen.
    $form->load_event($event);
}

// ── Output ───────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_nit_emails'));
echo html_writer::div(get_string('intro', 'local_nit_emails'), 'alert alert-info');

$tabs = [];
foreach (templates::events() as $key) {
    $label = templates::event_name($key);
    if (!templates::is_enabled($key)) {
        $label .= ' (' . get_string('off', 'local_nit_emails') . ')';
    }
    $tabs[] = new tabobject(
        $key,
        new moodle_url('/local/nit_emails/manage.php', ['event' => $key]),
        $label
    );
}
echo $OUTPUT->tabtree($tabs, $event);

echo html_writer::tag('p', get_string('event_' . $event . '_desc', 'local_nit_emails'), ['class' => 'text-muted']);

// Preview / test row — one pair of actions per authored language.
$actions = '';
foreach (templates::LANGS as $lang) {
    $langname = get_string('lang_' . $lang, 'local_nit_emails');
    $actions .= html_writer::link(
        new moodle_url('/local/nit_emails/preview.php', ['event' => $event, 'lang' => $lang]),
        get_string('previewlang', 'local_nit_emails', $langname),
        ['class' => 'btn btn-secondary me-2 mr-2 mb-2', 'target' => '_blank', 'rel' => 'noopener']
    );
    $actions .= html_writer::link(
        new moodle_url('/local/nit_emails/manage.php',
            ['event' => $event, 'testlang' => $lang, 'sesskey' => sesskey()]),
        get_string('sendtestlang', 'local_nit_emails', $langname),
        ['class' => 'btn btn-outline-secondary me-2 mr-2 mb-2']
    );
}
echo html_writer::div($actions, 'mb-3');
echo html_writer::tag('p', get_string('sendtest_desc', 'local_nit_emails', s($USER->email)),
    ['class' => 'text-muted small']);

$form->display();

// ── Placeholder reference ────────────────────────────────────────────────────
echo $OUTPUT->heading(get_string('placeholders', 'local_nit_emails'), 3);
echo html_writer::tag('p', get_string('placeholders_desc', 'local_nit_emails'), ['class' => 'text-muted']);

$table = new html_table();
$table->head = [get_string('placeholder', 'local_nit_emails'), get_string('description')];
$table->attributes['class'] = 'generaltable table table-sm';
foreach (templates::placeholders($event) as $name) {
    $table->data[] = [
        html_writer::tag('code', '{' . $name . '}'),
        get_string('ph_' . $name, 'local_nit_emails'),
    ];
}
echo html_writer::table($table);

echo $OUTPUT->footer();
