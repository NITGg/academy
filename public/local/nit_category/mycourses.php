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
 * Every course the learner is enrolled in, drawn as the home page's cards.
 *
 * The home block shows the first two and links here with its "More" chevron;
 * this page is the same section with the limit taken off.
 *
 * The markup is not written out again here. It is read from the block file the
 * home page is pasted from, and the only things changed are the three data
 * attributes that make it a page rather than a teaser: every course instead of
 * two, the empty state drawn instead of the whole section disappearing, and no
 * "More" link, which here would only lead back to itself.
 *
 * Reading the file rather than keeping a second copy is deliberate. The card is
 * a design that changes - it has already changed twice - and a copy would be
 * wrong the first time somebody edited one of them. This way the page cannot
 * disagree with the block about what a course card looks like.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_category/lib.php');

require_login();

// A guest is signed in but owns nothing, so the page would only ever be empty.
if (isguestuser()) {
    redirect(new moodle_url('/local/nit_category/catalogue.php'));
}

// Arriving from a category page's "More" link, which is showing that category's slice of the
// learner's courses; the full list has to keep the same slice or the link would silently widen
// what it was pointing at. 0 = every course, which is what the home page's own link sends.
$categoryid = optional_param('categoryid', 0, PARAM_INT);
$category = null;
if ($categoryid > 0) {
    try {
        $category = core_course_category::get($categoryid);
    } catch (\Throwable $e) {
        // A category that has since been deleted, or one this viewer may not see: fall back to
        // the whole list rather than refusing a page they are entitled to.
        $categoryid = 0;
    }
}

$context = context_system::instance();

$PAGE->set_url(new moodle_url('/local/nit_category/mycourses.php',
    $categoryid ? ['categoryid' => $categoryid] : []));
$PAGE->set_context($context);
$PAGE->set_pagelayout('nit_fullwidth');

$heading = $category
    ? get_string('mycoursesincategory', 'local_nit_category', $category->get_formatted_name())
    : get_string('mycourses', 'local_nit_category');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);

// Turn the teaser into the full list. Matching on the whole attribute with its value keeps
// this from firing on anything else in the file.
//
// data-empty is left alone: the block already ships with the AC-4.7.7 invitation on, and this
// page wants it for the same reason the home page does - a learner who reached "My courses"
// with nothing enrolled should be pointed at the catalogue rather than shown a blank column.
// Guests never get here; they were sent to the catalogue above.
$replace = [
    'data-limit="2"' => 'data-limit="50"',
    'data-viewall="/local/nit_category/mycourses.php"' => 'data-viewall=""',
];
if ($categoryid > 0) {
    // data-category is what the block's script turns into &categoryid= on the feed call, and
    // "browse" should lead back to the category rather than to the whole catalogue.
    $replace['data-nit-mycourse=""'] = 'data-nit-mycourse="" data-category="' . $categoryid . '"';
    $replace['data-browse="/local/nit_category/catalogue.php"'] =
        'data-browse="/local/nit_category/index.php?id=' . $categoryid . '"';
}

$markup = local_nit_category_render_home_block('home_my_course_block.html', $replace, $context);

echo $OUTPUT->header();

if ($markup === '') {
    // The theme is installed but the file is not readable. Say so rather than
    // rendering a page that is silently empty.
    echo $OUTPUT->notification(get_string('mycoursesunavailable', 'local_nit_category'),
        \core\output\notification::NOTIFY_ERROR);
} else {
    echo $markup;
}

echo $OUTPUT->footer();
