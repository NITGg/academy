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
 * Set the student messaging restriction for each course.
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

admin_externalpage_setup('local_msgrules_manage');
require_capability('local/msgrules:manage', context_system::instance());

/** @var int Value of the "no override" option. Never a real mode, so it cannot collide. */
const LOCAL_MSGRULES_INHERIT = -1;

$search = trim(optional_param('search', '', PARAM_TEXT));
$page = optional_param('page', 0, PARAM_INT);
$perpage = 25;

$pageurl = new moodle_url('/local/msgrules/manage.php', $search !== '' ? ['search' => $search] : []);

// ---- Which courses to show ---------------------------------------------------------------
$params = ['siteid' => SITEID];
$where = 'c.id <> :siteid';
if ($search !== '') {
    $like = $DB->sql_like('c.fullname', ':s1', false) . ' OR ' . $DB->sql_like('c.shortname', ':s2', false);
    $where .= " AND ($like)";
    $params['s1'] = '%' . $DB->sql_like_escape($search) . '%';
    $params['s2'] = '%' . $DB->sql_like_escape($search) . '%';
}

$total = $DB->count_records_sql("SELECT COUNT(1) FROM {course} c WHERE $where", $params);
$courses = $DB->get_records_sql(
    "SELECT c.id, c.fullname, c.shortname FROM {course} c WHERE $where ORDER BY c.fullname ASC",
    $params,
    $page * $perpage,
    $perpage
);

// ---- Saving ------------------------------------------------------------------------------
if (optional_param('save', 0, PARAM_BOOL) && confirm_sesskey()) {
    // Only the courses actually on this page are read. Anything else is untouched, so paging
    // through a long list does not quietly reset the courses you are not looking at.
    foreach (array_keys($courses) as $courseid) {
        $mode = optional_param('mode_' . $courseid, LOCAL_MSGRULES_INHERIT, PARAM_INT);
        if ($mode === LOCAL_MSGRULES_INHERIT) {
            rules::set_course_mode((int) $courseid, null);
        } else if (array_key_exists($mode, rules::get_modes())) {
            rules::set_course_mode((int) $courseid, $mode);
        }
    }
    redirect(new moodle_url($pageurl, ['page' => $page]), sync::apply_now(), null, notification::NOTIFY_SUCCESS);
}

if (optional_param('rebuild', 0, PARAM_BOOL) && confirm_sesskey()) {
    redirect(new moodle_url($pageurl, ['page' => $page]), sync::apply_now(), null, notification::NOTIFY_SUCCESS);
}

$modes = rules::get_modes();
$overrides = rules::get_course_modes();
$default = rules::get_default_mode();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecourses', 'local_msgrules'));

// ---- What is and is not in force right now ------------------------------------------------
// Three separate switches have to line up before a restriction closes anything, and each of
// them fails silently on its own, so each gets its own sentence rather than one vague warning.
if (empty($CFG->messaging)) {
    echo $OUTPUT->notification(get_string('messagingoffwarning', 'local_msgrules'), notification::NOTIFY_WARNING);
}
if (!rules::is_enabled()) {
    echo $OUTPUT->notification(get_string('disabledwarning', 'local_msgrules'), notification::NOTIFY_WARNING);
}

echo html_writer::tag('p', get_string('coursesintro', 'local_msgrules'));
echo html_writer::tag('p', get_string('currentdefault', 'local_msgrules', $modes[$default]));
echo html_writer::tag('p', get_string('adminexempt', 'local_msgrules'), ['class' => 'text-muted']);

// ---- Search -------------------------------------------------------------------------------
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/msgrules/manage.php'))->out(false),
    'class'  => 'mb-3',
]);
echo html_writer::start_div('input-group', ['style' => 'max-width: 32rem;']);
echo html_writer::empty_tag('input', [
    'type'        => 'text',
    'name'        => 'search',
    'value'       => $search,
    'class'       => 'form-control',
    'placeholder' => get_string('searchcourses', 'local_msgrules'),
    'aria-label'  => get_string('searchcourses', 'local_msgrules'),
]);
echo html_writer::tag('button', get_string('search'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_div();
echo html_writer::end_tag('form');

if (!$courses) {
    echo $OUTPUT->notification(get_string('nocoursesfound', 'local_msgrules'), notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    die();
}

// ---- The list -----------------------------------------------------------------------------
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => 1]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'page', 'value' => $page]);

echo html_writer::start_div('table-responsive');
echo html_writer::start_tag('table', ['class' => 'table generaltable']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('course', 'local_msgrules'), ['scope' => 'col']);
echo html_writer::tag('th', get_string('restriction', 'local_msgrules'), ['scope' => 'col']);
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

// "Site default" is spelled out with the mode it currently resolves to, so a course row says
// what will actually happen without the reader holding the settings page in their head.
$options = [LOCAL_MSGRULES_INHERIT => get_string('usedefault', 'local_msgrules', $modes[$default])] + $modes;

foreach ($courses as $course) {
    $selected = array_key_exists($course->id, $overrides) ? $overrides[$course->id] : LOCAL_MSGRULES_INHERIT;
    $name = format_string($course->fullname);
    $label = html_writer::link(
        new moodle_url('/course/view.php', ['id' => $course->id]),
        $name
    ) . html_writer::tag('div', format_string($course->shortname), ['class' => 'small text-muted']);

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $label);
    echo html_writer::tag('td', html_writer::select(
        $options,
        'mode_' . $course->id,
        $selected,
        false,
        ['class' => 'custom-select', 'aria-label' => $name]
    ));
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

echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);

// A reapply with no change, for when enrolments moved outside the observers - a restored
// database, or rows edited directly. Its own form, after the list one is closed.
$rebuildurl = new moodle_url($pageurl, ['rebuild' => 1, 'page' => $page, 'sesskey' => sesskey()]);
echo html_writer::div($OUTPUT->single_button($rebuildurl, get_string('rebuildnow', 'local_msgrules'), 'get'), 'mb-3');

// ---- Current state ------------------------------------------------------------------------
echo $OUTPUT->heading(get_string('currentstate', 'local_msgrules'), 3);
echo html_writer::tag('p', get_string('managedblocks', 'local_msgrules', sync::count_managed()));

// ---- Who ignores all of this ----------------------------------------------------------------
// core lets these two capabilities skip the blocked-users list entirely, which means a role
// holding either one is outside the restrictions no matter what a course says. Silently
// letting an administrator believe otherwise is the worst outcome here, so the roles are named.
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

echo $bypass ? html_writer::alist($bypass) : html_writer::tag('p', get_string('bypassnone', 'local_msgrules'));

echo $OUTPUT->footer();
