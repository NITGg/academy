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
 * Upload / replace / remove the image of one course category.
 *
 * Reached from the "Category image" tab that lib.php adds to the category settings
 * navigation, next to the stock Category / Settings / Upload courses tabs.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/nit_category/lib.php');
require_once($CFG->libdir . '/filelib.php');

$categoryid = required_param('id', PARAM_INT);

$category = core_course_category::get($categoryid, MUST_EXIST, true);
$context  = context_coursecat::instance($categoryid);

require_login();
require_capability('moodle/category:manage', $context);

$url = new moodle_url('/local/nit_category/image.php', ['id' => $categoryid]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title($category->get_formatted_name() . ': ' . get_string('categoryimage', 'local_nit_category'));
$PAGE->set_heading($category->get_formatted_name());
// Keep the category branch of the nav open and this tab highlighted.
navigation_node::override_active_url(new moodle_url('/course/index.php', ['categoryid' => $categoryid]));
$PAGE->set_secondary_active_tab('nitcategoryimage');

$options = local_nit_category_image_options();

// Load whatever is stored into a draft area for the filemanager to work on.
$draftitemid = file_get_submitted_draft_itemid('categoryimage_filemanager');
file_prepare_draft_area(
    $draftitemid,
    $context->id,
    'local_nit_category',
    LOCAL_NIT_CATEGORY_IMAGE_FILEAREA,
    0,
    $options
);

$mform = new \local_nit_category\form\image_form($url->out(false));
$mform->set_data([
    'id'                         => $categoryid,
    'categoryimage_filemanager'  => $draftitemid,
]);

$returnurl = new moodle_url('/course/index.php', ['categoryid' => $categoryid]);

if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    file_save_draft_area_files(
        $data->categoryimage_filemanager,
        $context->id,
        'local_nit_category',
        LOCAL_NIT_CATEGORY_IMAGE_FILEAREA,
        0,
        $options
    );
    redirect(
        $url,
        get_string('imagesaved', 'local_nit_category'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Work out which link in the fallback chain is actually feeding this category right
// now, so the admin can see at a glance whether the picture comes from this page, from
// the description, from a parent category, or from the site logo.
$ownfile        = local_nit_category_get_image_file($categoryid);
$owndescription = local_nit_category_description_image_url($categoryid);
$resolved       = local_nit_category_get_image_url($categoryid);

if ($ownfile) {
    $sourcekey = 'sourceuploaded';
} else if ($owndescription !== '') {
    $sourcekey = 'sourcedescription';
} else if ($resolved !== '') {
    $sourcekey = 'sourceinherited';
} else {
    $sourcekey = 'sourcelogo';
    $logo = $OUTPUT->get_logo_url() ?: $OUTPUT->get_compact_logo_url();
    $resolved = $logo ? $logo->out(false) : '';
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('categoryimage', 'local_nit_category'));

if ($resolved !== '') {
    echo html_writer::start_div('mb-4');
    echo html_writer::tag('h3', get_string('currentimage', 'local_nit_category'), ['class' => 'h5']);
    echo html_writer::empty_tag('img', [
        'src'   => $resolved,
        'alt'   => $category->get_formatted_name(),
        'class' => 'img-fluid rounded border',
        'style' => 'max-height: 180px;',
    ]);
    echo html_writer::div(
        get_string($sourcekey, 'local_nit_category'),
        'text-muted small mt-2'
    );
    echo html_writer::end_div();
}

echo html_writer::div(get_string('fallbackinfo', 'local_nit_category'), 'alert alert-info');

$mform->display();
echo $OUTPUT->footer();
