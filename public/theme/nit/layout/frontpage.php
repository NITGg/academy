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
// NIT: who is looking, for the front-page section blocks. Core only adds
// 'notloggedin' when NOBODY is signed in, and the guest account IS signed in,
// so a block that wants "anonymous or guest" (the hero's Start Now goes to the
// login page for both) needs this class rather than the core one.
if (!isloggedin() || isguestuser()) {
    $extraclasses[] = 'nit-guest';
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

// NIT: does core's own content column have anything to show? The Site home is
// built from the block regions above; the column in the middle (`.main-inner`:
// page heading, site section 1, the frontpage course lists) is hidden piece by
// piece in post.scss — but display:none on its children leaves the column's
// own margins and padding standing, and that was a ~200px blank band between
// the hero region and the footer. The column cannot be inspected here (core
// writes it into the page after this layout has been emitted), so this works
// out from the same inputs core uses whether anything visible will land in it,
// and post.scss collapses the column only on that word.
//
// Kept open (space and all) whenever: editing (the column carries the drop
// zones); a block sits in above-content / below-content (both live inside the
// column); side-pre holds blocks (its toggle does too); a custom front page
// include is configured; or the frontpage layout setting names a part that
// prints — the news forum when the site has news items, the enrolled-course
// list when this visitor is enrolled somewhere, and the all-courses /
// category / combo / search parts always. Site section 1 is not a reason: the
// theme never shows it here (`.course-content` is display:none on this page).
$nitmainempty = !$editing && !$hasabovecontent && !$hasbelowcontent && !$hasblocks
    && empty($CFG->customfrontpageinclude);
if ($nitmainempty) {
    $nitfrontpagelayout = (isloggedin() && !isguestuser() && isset($CFG->frontpageloggedin))
        ? $CFG->frontpageloggedin : ($CFG->frontpage ?? '');
    foreach (explode(',', (string) $nitfrontpagelayout) as $nitpart) {
        if ($nitpart === '') {
            continue;
        }
        if ((int) $nitpart === FRONTPAGENEWS) {
            $nitmainempty = empty($SITE->newsitems);
        } else if ((int) $nitpart === FRONTPAGEENROLLEDCOURSELIST) {
            $nitmainempty = !isloggedin() || isguestuser() || !enrol_get_my_courses('id', null, 1);
        } else {
            $nitmainempty = false;
        }
        if (!$nitmainempty) {
            break;
        }
    }
}
if ($nitmainempty) {
    $extraclasses[] = 'nit-frontpage-nomain';
}
// NIT: end empty-column check.

// NIT: navigation bar / site footer on the Site home ("Home page chrome" on the
// gallery's Change style tab — see theme_nit_home_chrome()). Either may be
// switched off for this page only; both are forced back on while editing, as
// the edit-mode switch and the user menu are on the bar. The navbar is simply
// not rendered (nothing left behind to take up room). Boost's `#page-footer`
// popover has to stay — core writes the page's closing scripts into it — so
// the footer switch drops the site-footer band (core_renderer::nit_site_footer)
// and a body class lets post.scss zero the popover's own box.
$nithomechrome = theme_nit_home_chrome();
$nitshownavbar = $editing || $nithomechrome['navbar'];
$nitshowfooter = $editing || $nithomechrome['footer'];
if (!$nitshownavbar) {
    $extraclasses[] = 'nit-home-nonavbar';
}
if (!$nitshowfooter) {
    $extraclasses[] = 'nit-home-nofooter';
}
// NIT: end home chrome.

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

// NIT: front-page course/stat data — computed once (both are cached in lib.php)
// and reused for the client-side render, the crawlable SEO fallback, and the
// JSON-LD structured data below.
$nitcourses = theme_nit_get_courses(12);
$nitmycourses = theme_nit_get_enrolled_courses(12);
$nitstats = theme_nit_get_site_stats();

// NIT (SEO): a server-rendered, crawlable list of course links. The visible
// grid is built client-side from window.NIT_COURSES, which non-JS crawlers do
// not execute; this <noscript> fallback keeps the catalogue indexable and its
// internal links followable. fullname is already format_string()-escaped and
// the url is a clean moodle_url, so html_writer::link is safe.
$nitcoursesnoscript = '';
foreach ($nitcourses as $nitc) {
    $nitcoursesnoscript .= html_writer::link($nitc['url'], $nitc['fullname'],
        ['class' => 'nit-course-crawl-link']) . "\n";
}

// NIT (SEO): schema.org ItemList of Course for rich results / better indexing.
// JSON-LD values are plain text, so decode the display-escaped entities back to
// text; json_encode then re-escapes for JSON. Slashes stay escaped (default),
// so "</script>" cannot break out of the embedding <script> element.
$nitld = ['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => []];
$nitpos = 1;
foreach ($nitcourses as $nitc) {
    $item = [
        '@type' => 'Course',
        'name'  => html_entity_decode((string) $nitc['fullname'], ENT_QUOTES, 'UTF-8'),
        'url'   => $nitc['url'],
    ];
    if (!empty($nitc['summary'])) {
        $item['description'] = html_entity_decode((string) $nitc['summary'], ENT_QUOTES, 'UTF-8');
    }
    if (!empty($nitc['image'])) {
        $item['image'] = $nitc['image'];
    }
    $nitld['itemListElement'][] = ['@type' => 'ListItem', 'position' => $nitpos++, 'item' => $item];
}
$nitcoursesldjson = json_encode($nitld, JSON_UNESCAPED_UNICODE);

// NIT (SEO): Open Graph / Twitter meta + a meta description for the Site home,
// injected into <head> for this request only (additionalhtmlhead is emitted by
// standard_head_html). Not persisted; attribute values are escaped with s().
$nitsitectx = context_course::instance(SITEID);
$nitogtitle = format_string($SITE->fullname, true, ['context' => $nitsitectx, 'escape' => false]);
$nitogdesc = '';
if (!empty($SITE->summary)) {
    $nitogdesc = shorten_text(trim(html_to_text(
        format_text($SITE->summary, $SITE->summaryformat ?? FORMAT_HTML, ['context' => $nitsitectx]), 0, false)), 300);
}
$nitoglogo = $OUTPUT->get_logo_url();
$nitogmeta  = '<meta property="og:type" content="website">' . "\n";
$nitogmeta .= '<meta property="og:site_name" content="' . s($nitogtitle) . '">' . "\n";
$nitogmeta .= '<meta property="og:title" content="' . s($nitogtitle) . '">' . "\n";
$nitogmeta .= '<meta property="og:url" content="' . s((new moodle_url('/'))->out(false)) . '">' . "\n";
if ($nitogdesc !== '') {
    $nitogmeta .= '<meta name="description" content="' . s($nitogdesc) . '">' . "\n";
    $nitogmeta .= '<meta property="og:description" content="' . s($nitogdesc) . '">' . "\n";
}
if ($nitoglogo) {
    $nitogmeta .= '<meta property="og:image" content="' . s($nitoglogo->out(false)) . '">' . "\n";
    $nitogmeta .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
} else {
    $nitogmeta .= '<meta name="twitter:card" content="summary">' . "\n";
}

// NIT (SEO): canonical URL + hreflang alternates. Tells search engines the
// front page's preferred URL and that it exists in each installed language
// (Moodle switches language via ?lang=xx), so the AR and EN versions aren't
// treated as duplicates.
$nithomeurl = (new moodle_url('/'))->out(false);
$nitogmeta .= '<link rel="canonical" href="' . s($nithomeurl) . '">' . "\n";
$nittranslations = get_string_manager()->get_list_of_translations();
if (count($nittranslations) > 1) {
    foreach ($nittranslations as $nitlangcode => $nitlangname) {
        $nithreflang = str_replace('_', '-', $nitlangcode);
        $nitalturl = (new moodle_url('/', ['lang' => $nitlangcode]))->out(false);
        $nitogmeta .= '<link rel="alternate" hreflang="' . s($nithreflang)
            . '" href="' . s($nitalturl) . '">' . "\n";
    }
    $nitogmeta .= '<link rel="alternate" hreflang="x-default" href="' . s($nithomeurl) . '">' . "\n";
}
$CFG->additionalhtmlhead = ($CFG->additionalhtmlhead ?? '') . "\n" . $nitogmeta;

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
    'nitstatsjson' => json_encode($nitstats, JSON_UNESCAPED_UNICODE),
    // NIT: course view-models exposed as window.NIT_COURSES.
    'nitcoursesjson' => json_encode($nitcourses, JSON_UNESCAPED_UNICODE),
    // NIT: the current user's enrolled-course view-models exposed as window.NIT_MY_COURSES.
    'nitmycoursesjson' => json_encode($nitmycourses, JSON_UNESCAPED_UNICODE),
    // NIT (SEO): schema.org JSON-LD + crawlable fallback links for the courses.
    'nitcoursesldjson' => $nitcoursesldjson,
    'nitcoursesnoscript' => $nitcoursesnoscript,
    // NIT: category view-models exposed as window.NIT_CATEGORIES.
    'nitcategoriesjson' => json_encode(theme_nit_get_categories(12), JSON_UNESCAPED_UNICODE),
    // NIT: site/navbar logo URL exposed as window.NIT_LOGO for front-page section blocks.
    'nitlogojson' => json_encode(($OUTPUT->get_compact_logo_url(null, 200) ?: $OUTPUT->get_logo_url(null, 200))?->out(false) ?: '', JSON_UNESCAPED_UNICODE),
    // NIT: the full site logo exposed as window.NIT_LOGO_FULL. NIT_LOGO above
    // prefers the compact mark because it fills navbar-sized slots; a block that
    // shows the brand as a section of its own wants the wide lock-up, and only
    // falls back to the compact one when no full logo has been uploaded.
    'nitlogofulljson' => json_encode(($OUTPUT->get_logo_url(null, 300) ?: $OUTPUT->get_compact_logo_url(null, 300))?->out(false) ?: '', JSON_UNESCAPED_UNICODE),
    // NIT: full-width region payloads for theme_nit/frontpage.
    'fullwidthtop' => $fullwidthtop,
    'hasfullwidthtop' => $hasfullwidthtop,
    'abovecontent' => $abovecontent,
    'hasabovecontent' => $hasabovecontent,
    'belowcontent' => $belowcontent,
    'hasbelowcontent' => $hasbelowcontent,
    'fullwidthbottom' => $fullwidthbottom,
    'hasfullwidthbottom' => $hasfullwidthbottom,
    // NIT: whether the navigation bar is drawn on this page at all (see above).
    'nitshownavbar' => $nitshownavbar,
    // NIT: end.
];

echo $OUTPUT->render_from_template('theme_nit/frontpage', $templatecontext);
