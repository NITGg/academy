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
 * What learners looked for and did not find (AC-4.22.4).
 *
 * The report is short by design: a term, how often it was searched, when it was last asked
 * for, and a link that runs the search again so the reader can see for themselves that it
 * still finds nothing. A row can be forgotten once the gap it names has been filled — that
 * is the "we have added that course now" button, not a tidy-up.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_category\search_log;

admin_externalpage_setup('local_nit_category_searchlog');

$sort    = optional_param('sort', search_log::SORT_HITS, PARAM_ALPHA);
$page    = optional_param('page', 0, PARAM_INT);
$delete  = optional_param('delete', 0, PARAM_INT);
$purge   = optional_param('purge', 0, PARAM_BOOL);
$perpage = 50;

if (!array_key_exists($sort, search_log::sort_options())) {
    $sort = search_log::SORT_HITS;
}

$baseurl = new moodle_url('/local/nit_category/searchlog.php', ['sort' => $sort]);

if ($delete && confirm_sesskey()) {
    search_log::delete($delete);
    redirect($baseurl, get_string('searchlogdeleted', 'local_nit_category'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Emptying the log throws away the only record of demand nobody has answered yet, so it
// asks first — a stray click on a link should not be able to do it.
if ($purge) {
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        search_log::purge();
        redirect($baseurl, get_string('searchlogpurged', 'local_nit_category'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('searchlog', 'local_nit_category'));
    echo $OUTPUT->confirm(
        get_string('searchlogpurgeconfirm', 'local_nit_category'),
        new moodle_url('/local/nit_category/searchlog.php',
            ['sort' => $sort, 'purge' => 1, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl);
    echo $OUTPUT->footer();
    exit;
}

$terms = search_log::terms($sort, $page, $perpage);
$termcount = search_log::count_terms();
$searchcount = search_log::count_searches();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('searchlog', 'local_nit_category'));
echo html_writer::tag('p', get_string('searchlogintro', 'local_nit_category'),
    ['class' => 'text-muted']);

if ($termcount === 0) {
    echo $OUTPUT->notification(get_string('searchlogempty', 'local_nit_category'),
        \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', get_string('searchlogsummary', 'local_nit_category',
    ['terms' => $termcount, 'searches' => $searchcount]), ['class' => 'lead']);

// Sort links.
$links = [];
foreach (search_log::sort_options() as $key => $label) {
    $url = new moodle_url('/local/nit_category/searchlog.php', ['sort' => $key]);
    $links[] = $key === $sort
        ? html_writer::tag('strong', $label)
        : html_writer::link($url, $label);
}
echo html_writer::tag('p', get_string('sortby', 'local_nit_category') . ' ' . implode(' · ', $links));

$table = new html_table();
$table->head = [
    get_string('searchlogterm', 'local_nit_category'),
    get_string('searchloghits', 'local_nit_category'),
    get_string('searchlogfirst', 'local_nit_category'),
    get_string('searchloglast', 'local_nit_category'),
    get_string('language'),
    '',
];
$table->attributes['class'] = 'generaltable';
$table->data = [];

foreach ($terms as $term) {
    $tryurl = new moodle_url('/local/nit_category/search.php', ['q' => $term->term]);
    $deleteurl = new moodle_url('/local/nit_category/searchlog.php',
        ['sort' => $sort, 'delete' => $term->id, 'sesskey' => sesskey()]);

    $table->data[] = [
        html_writer::link($tryurl, s($term->term), ['target' => '_blank', 'rel' => 'noopener']),
        (int) $term->hits,
        userdate($term->timefirst, get_string('strftimedatetimeshort')),
        userdate($term->timelast, get_string('strftimedatetimeshort')),
        s($term->lang),
        html_writer::link($deleteurl, get_string('delete'), ['class' => 'btn btn-sm btn-outline-secondary']),
    ];
}

echo html_writer::table($table);

if ($termcount > $perpage) {
    echo $OUTPUT->paging_bar($termcount, $page, $perpage, $baseurl);
}

$purgeurl = new moodle_url('/local/nit_category/searchlog.php', ['sort' => $sort, 'purge' => 1]);
echo html_writer::div(
    html_writer::link($purgeurl, get_string('searchlogpurge', 'local_nit_category'),
        ['class' => 'btn btn-outline-danger']),
    'mt-3');

echo $OUTPUT->footer();
