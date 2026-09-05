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
 * The rule matrix: which cohort may open a conversation with which other cohort.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use core\output\notification;
use local_msgrules\rules;
use local_msgrules\sync;
use local_msgrules\task\rebuild;

admin_externalpage_setup('local_msgrules_manage');
require_capability('local/msgrules:manage', context_system::instance());

$pageurl = new moodle_url('/local/msgrules/manage.php');
$cohorts = rules::get_cohort_menu();

if (optional_param('save', 0, PARAM_BOOL) && confirm_sesskey()) {
    // The form posts the whole grid, so a cell that is absent from the submission was cleared.
    // Reading it cell by cell against the cohort list - rather than trusting whatever keys
    // arrived - is also what keeps a hand-built post from inventing rules for cohorts that do
    // not exist.
    $allowed = [];
    foreach (array_keys($cohorts) as $from) {
        foreach (array_keys($cohorts) as $to) {
            if (optional_param('rule_' . $from . '_' . $to, 0, PARAM_BOOL)) {
                $allowed[$from][$to] = true;
            }
        }
    }
    rules::set_rules($allowed);
    rebuild::queue();
    redirect($pageurl, get_string('rulessaved', 'local_msgrules'), null, notification::NOTIFY_SUCCESS);
}

if (optional_param('rebuild', 0, PARAM_BOOL) && confirm_sesskey()) {
    rebuild::queue();
    redirect($pageurl, get_string('rebuildqueued', 'local_msgrules'), null, notification::NOTIFY_SUCCESS);
}

$rules = rules::get_rules();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managematrix', 'local_msgrules'));

// ---- What is and is not in force right now ---------------------------------------------
// Three separate switches have to line up before a rule closes anything, and each of them
// fails silently on its own, so each gets its own sentence rather than one vague warning.
if (empty($CFG->messaging)) {
    echo $OUTPUT->notification(get_string('messagingoffwarning', 'local_msgrules'), notification::NOTIFY_WARNING);
}
if (!rules::is_enabled()) {
    echo $OUTPUT->notification(get_string('disabledwarning', 'local_msgrules'), notification::NOTIFY_WARNING);
}

if (count($cohorts) <= 1) {
    // Only the "not in any cohort" pseudo-entry: a one-cell grid is not worth drawing.
    echo $OUTPUT->notification(get_string('nocohortsyet', 'local_msgrules'), notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    die();
}

echo html_writer::tag('p', get_string('matrixintro', 'local_msgrules'));
echo html_writer::tag('p', get_string('adminexempt', 'local_msgrules'), ['class' => 'text-muted']);

// ---- The grid ---------------------------------------------------------------------------
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);

// The grid is as wide as the site has cohorts, so it scrolls inside its own box rather than
// pushing the whole admin page sideways.
echo html_writer::start_div('table-responsive');
echo html_writer::start_tag('table', ['class' => 'table table-bordered generaltable']);

echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('sendercohort', 'local_msgrules') . ' \\ '
    . get_string('recipientcohort', 'local_msgrules'), ['scope' => 'col']);
foreach ($cohorts as $id => $name) {
    echo html_writer::tag('th', $name, ['scope' => 'col']);
}
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');
foreach ($cohorts as $fromid => $fromname) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', $fromname, ['scope' => 'row']);
    foreach ($cohorts as $toid => $toname) {
        $name = 'rule_' . $fromid . '_' . $toid;
        $attrs = [
            'type'  => 'checkbox',
            'name'  => $name,
            'id'    => $name,
            'value' => 1,
        ];
        if (!empty($rules[$fromid][$toid])) {
            $attrs['checked'] = 'checked';
        }
        // A bare checkbox in a grid tells a screen reader nothing about which pair it is, so
        // each one carries the two cohort names as its own label.
        $label = html_writer::tag('label', $fromname . ' → ' . $toname, [
            'for'   => $name,
            'class' => 'visually-hidden',
        ]);
        echo html_writer::tag('td', $label . html_writer::empty_tag('input', $attrs), [
            'class' => 'text-center',
        ]);
    }
    echo html_writer::end_tag('tr');
}
echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();

echo html_writer::div(html_writer::empty_tag('input', [
    'type'  => 'submit',
    'class' => 'btn btn-primary',
    'value' => get_string('savechanges'),
]), 'mb-3');
echo html_writer::end_tag('form');

// A rebuild with no save, for when cohort membership changed outside the observers - a
// restored database, or rows edited directly. Its own form, after the matrix one is closed:
// a button nested inside the grid form would submit the grid instead.
$rebuildurl = new moodle_url($pageurl, ['rebuild' => 1, 'sesskey' => sesskey()]);
echo html_writer::div($OUTPUT->single_button($rebuildurl, get_string('rebuildnow', 'local_msgrules'), 'get'), 'mb-3');

// ---- Current state ----------------------------------------------------------------------
echo $OUTPUT->heading(get_string('currentstate', 'local_msgrules'), 3);
echo html_writer::tag('p', get_string('managedblocks', 'local_msgrules', sync::count_managed()));

// ---- Who ignores all of this ------------------------------------------------------------
// core lets these two capabilities skip the blocked-users list entirely, which means a role
// holding either one is outside the matrix no matter what it says. Silently letting an
// administrator believe otherwise is the worst outcome here, so the roles are named.
echo $OUTPUT->heading(get_string('bypassheading', 'local_msgrules'), 3);
echo html_writer::tag('p', get_string('bypassintro', 'local_msgrules'));

$bypass = [];
foreach (['moodle/site:messageanyuser', 'moodle/site:readallmessages'] as $capability) {
    foreach (get_roles_with_capability($capability, CAP_ALLOW) as $role) {
        $bypass[] = get_string('bypassrole', 'local_msgrules', (object) [
            'role'       => role_get_name($role, context_system::instance()),
            'capability' => $capability,
        ]);
    }
}

if ($bypass) {
    echo html_writer::alist($bypass);
} else {
    echo html_writer::tag('p', get_string('bypassnone', 'local_msgrules'));
}

echo $OUTPUT->footer();
