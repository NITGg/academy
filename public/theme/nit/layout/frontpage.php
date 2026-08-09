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
 * Front page (Site home) layout for theme_nit.
 *
 * A faithful copy of theme_boost/layout/drawers.php, extended with four
 * full-width block regions so the Site home can host page-wide marketing
 * sections (hero, category grid, CTA) instead of only a sidebar.
 *
 * Re-diff against theme_boost/layout/drawers.php on each Moodle upgrade.
 * NIT-added lines are fenced with "NIT:" comments to make that diff cheap.
 *
 * @package   theme_nit
 * @copyright 2026 NIT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

// Add block button in editing mode.
$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers', 'nit-frontpage'];
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

// NIT: the "Add a block" entry lives at the top of the page (not in the right
// drawer), so the drawer is shown only when side-pre actually holds blocks.
$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false);
if (!$hasblocks) {
    $blockdraweropen = false;
}

// NIT: full-width front-page block regions. Rendered whenever they hold a block
// or when editing (so the "add block" drop zone appears). $OUTPUT->blocks()
// already emits an <aside data-region=…> wrapper that supports drag/drop.
$editing = $PAGE->user_is_editing();
$fullwidthtop = $OUTPUT->blocks('fullwidth-top');
$abovecontent = $OUTPUT->blocks('above-content');
$belowcontent = $OUTPUT->blocks('below-content');
$fullwidthbottom = $OUTPUT->blocks('fullwidth-bottom');
$hasfullwidthtop = $editing || (strpos($fullwidthtop, 'data-block=') !== false);
$hasabovecontent = $editing || (strpos($abovecontent, 'data-block=') !== false);
$hasbelowcontent = $editing || (strpos($belowcontent, 'data-block=') !== false);
$hasfullwidthbottom = $editing || (strpos($fullwidthbottom, 'data-block=') !== false);
// NIT: end full-width regions.

$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $selectmenu = new \core\output\select_menu(
            'tertiarynavigation',
            $overflowdata->urls,
            $overflowdata->selected,
        );
        $selectmenu->set_label($overflowdata->label, $overflowdata->labelattributes);
        $overflow = $selectmenu->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

$coursefullname = ($PAGE->course?->fullname) ? format_string(
    $PAGE->course->fullname,
    true,
    ['context' => context_course::instance($PAGE->course->id), 'escape' => false],
) : '';
$courseurl = $PAGE->course ? new \core\url('/course/view.php', ['id' => $PAGE->course->id]) : null;

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'coursefullname' => $coursefullname,
    'courseurl' => $courseurl ? $courseurl->out(false) : null,
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,
    // NIT: live site counters exposed to front-page section blocks as
    // window.NIT_STATS (see theme_nit_get_site_stats()).
    'nitstatsjson' => json_encode(theme_nit_get_site_stats(), JSON_UNESCAPED_UNICODE),
    // NIT: course view-models exposed as window.NIT_COURSES.
    'nitcoursesjson' => json_encode(theme_nit_get_courses(12), JSON_UNESCAPED_UNICODE),
    // NIT: category view-models exposed as window.NIT_CATEGORIES.
    'nitcategoriesjson' => json_encode(theme_nit_get_categories(12), JSON_UNESCAPED_UNICODE),
    // NIT: full-width region payloads for theme_nit/frontpage.
    'fullwidthtop' => $fullwidthtop,
    'hasfullwidthtop' => $hasfullwidthtop,
    'abovecontent' => $abovecontent,
    'hasabovecontent' => $hasabovecontent,
    'belowcontent' => $belowcontent,
    'hasbelowcontent' => $hasbelowcontent,
    'fullwidthbottom' => $fullwidthbottom,
    'hasfullwidthbottom' => $hasfullwidthbottom,
    // NIT: end.
];

echo $OUTPUT->render_from_template('theme_nit/frontpage', $templatecontext);
