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

require_login();

// A guest is signed in but owns nothing, so the page would only ever be empty.
if (isguestuser()) {
    redirect(new moodle_url('/local/nit_category/catalogue.php'));
}

$context = context_system::instance();

$PAGE->set_url(new moodle_url('/local/nit_category/mycourses.php'));
$PAGE->set_context($context);
$PAGE->set_pagelayout('nit_fullwidth');

$heading = get_string('mycourses', 'local_nit_category');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);

$blockfile = $CFG->dirroot . '/theme/nit/blocks/home_my_course_block.html';
$markup = is_readable($blockfile) ? (string) file_get_contents($blockfile) : '';

if ($markup !== '') {
    // Turn the teaser into the full list. Matching on the whole attribute with
    // its value keeps this from firing on anything else in the file.
    $markup = str_replace(
        ['data-limit="2"', 'data-empty="hide"', 'data-viewall="/local/nit_category/mycourses.php"'],
        ['data-limit="50"', 'data-empty="show"', 'data-viewall=""'],
        $markup
    );

    // noclean: this is our own file, and the cleaner would strip the <script>
    // that loads the card behaviour along with most of the inline styles the
    // design is made of. filter: on, because the strings in it are {mlang}
    // pairs that only the multilang filter can resolve.
    $markup = format_text($markup, FORMAT_HTML, [
        'noclean' => true,
        'filter' => true,
        'context' => $context,
    ]);
}

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
