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
 * Set who each course lets its students message.
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

/**
 * Read one row's ticks back into a stored mode.
 *
 * "No restriction" wins over the three group ticks when both are posted: it is the master
 * switch, and honouring it here means a half-finished row can never lock a course by accident.
 *
 * @param string $suffix Field-name suffix identifying the row ('default' or a course id).
 * @return int
 */
function local_msgrules_read_mode(string $suffix): int {
    if (optional_param('open_' . $suffix, 0, PARAM_BOOL)) {
        return rules::OPEN;
    }

    $mode = rules::ALLOW_NOBODY;
    foreach (array_keys(rules::get_flags()) as $flag) {
        if (optional_param('flag_' . $suffix . '_' . $flag, 0, PARAM_BOOL)) {
            $mode |= $flag;
        }
    }

    return $mode;
}

/**
 * Render one row of ticks: the master "no restriction" plus one per group.
 *
 * @param string $suffix Field-name suffix identifying the row.
 * @param int|null $mode The mode to show, or null for "follow the site default".
 * @param string $rowlabel Accessible prefix so each tick says which row it belongs to.
 * @return string
 */
function local_msgrules_render_ticks(string $suffix, ?int $mode, string $rowlabel): string {
    $out = '';
    $inherits = $mode === null;
    $effective = $mode ?? rules::get_default_mode();

    $tick = function (string $name, string $label, bool $checked, string $extraclass = '') use ($rowlabel) {
        $id = 'id_' . $name;
        return html_writer::div(
            html_writer::empty_tag('input', array_filter([
                'type'    => 'checkbox',
                'class'   => 'form-check-input',
                'name'    => $name,
                'id'      => $id,
                'value'   => 1,
                'checked' => $checked ? 'checked' : null,
            ])) .
            html_writer::tag('label', $label, [
                'for'   => $id,
                'class' => 'form-check-label',
                'title' => $rowlabel . ' - ' . $label,
            ]),
            trim('form-check form-check-inline ' . $extraclass)
        );
    };

    // The master switch first, then the three groups it overrides.
    $out .= $tick('open_' . $suffix, get_string('modeopen', 'local_msgrules'),
        !$inherits && rules::is_open($effective), 'me-4 fw-bold');

    foreach (rules::get_flags() as $flag => $label) {
        $out .= $tick('flag_' . $suffix . '_' . $flag, $label,
            !$inherits && rules::allows($effective, $flag));
    }

    if ($inherits) {
        // Nothing of its own: say so, and show what it currently resolves to.
        $out .= html_writer::div(
            get_string('followsdefault', 'local_msgrules', rules::describe($effective)),
            'small text-muted mt-1'
        );
    }

    return $out;
}

$search = trim(optional_param('search', '', PARAM_TEXT));
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;

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
    rules::set_default_mode(local_msgrules_read_mode('default'));

    // Only the courses actually on this page are read. Anything else is untouched, so paging
    // through a long list does not quietly reset the courses you are not looking at.
    foreach (array_keys($courses) as $courseid) {
        if (optional_param('inherit_' . $courseid, 0, PARAM_BOOL)) {
            rules::set_course_mode((int) $courseid, null);
        } else {
            rules::set_course_mode((int) $courseid, local_msgrules_read_mode((string) $courseid));
        }
    }

    redirect(new moodle_url($pageurl, ['page' => $page]), sync::apply_now(), null, notification::NOTIFY_SUCCESS);
}

if (optional_param('rebuild', 0, PARAM_BOOL) && confirm_sesskey()) {
    redirect(new moodle_url($pageurl, ['page' => $page]), sync::apply_now(), null, notification::NOTIFY_SUCCESS);
}

$overrides = rules::get_course_modes();

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
echo html_writer::tag('p', get_string('ticksintro', 'local_msgrules'));
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

// The site-wide default is the first row of the same table rather than a separate settings
// field: it is the same question with the same four ticks, and splitting it across two screens
// was what made "which one is actually in force here" hard to answer.
echo html_writer::start_tag('tr', ['class' => 'table-active']);
echo html_writer::tag('th', html_writer::tag('strong', get_string('allcourses', 'local_msgrules')) .
    html_writer::div(get_string('allcourses_help', 'local_msgrules'), 'small text-muted'), ['scope' => 'row']);
echo html_writer::tag('td', local_msgrules_render_ticks('default', rules::get_default_mode(),
    get_string('allcourses', 'local_msgrules')));
echo html_writer::end_tag('tr');

if (!$courses) {
    echo html_writer::tag('tr', html_writer::tag('td', get_string('nocoursesfound', 'local_msgrules'),
        ['colspan' => 2, 'class' => 'text-muted']));
}

foreach ($courses as $course) {
    $name = format_string($course->fullname);
    $hasoverride = array_key_exists($course->id, $overrides);
    $label = html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]), $name) .
        html_writer::div(format_string($course->shortname), 'small text-muted');

    // An explicit "follow the site default" tick, so a course can be handed back rather than
    // only ever being pinned to whatever the default happened to be when it was saved.
    $inherit = html_writer::div(
        html_writer::empty_tag('input', array_filter([
            'type'    => 'checkbox',
            'class'   => 'form-check-input',
            'name'    => 'inherit_' . $course->id,
            'id'      => 'id_inherit_' . $course->id,
            'value'   => 1,
            'checked' => $hasoverride ? null : 'checked',
        ])) .
        html_writer::tag('label', get_string('usedefault', 'local_msgrules'),
            ['for' => 'id_inherit_' . $course->id, 'class' => 'form-check-label']),
        'form-check form-check-inline mb-1'
    );

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $label);
    echo html_writer::tag('td', $inherit . local_msgrules_render_ticks(
        (string) $course->id,
        $hasoverride ? $overrides[$course->id] : null,
        $name
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
