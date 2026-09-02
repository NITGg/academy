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
 * English strings for local_nit_category.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Categories';

$string['categorymedia'] = 'Category image & icon';
$string['mediasaved'] = 'Category image and icon saved.';

$string['categoryimage'] = 'Category image';
$string['categoryimagefile'] = 'Image file';
$string['categoryimage_help'] = 'The picture shown for this category on the category page. One web image (JPG, PNG, GIF, SVG, WebP).

If you leave this empty, the first image found inside the category Description is used instead, then the image of the nearest parent category, and finally the site logo.';
$string['currentimage'] = 'Currently shown';
$string['fallbackinfo'] = 'Moodle categories have no picture of their own, so this page adds one. The category page picks the first available of: this uploaded image, the first image inside the category Description, the nearest parent category\'s image, the site logo.';
$string['sourceuploaded'] = 'Source: uploaded on this page.';
$string['sourcedescription'] = 'Source: the first image inside this category\'s Description. Upload a file below to override it.';
$string['sourceinherited'] = 'Source: inherited from a parent category. Upload a file below to give this category its own image.';
$string['sourcelogo'] = 'Source: the site logo (this category has no image of its own).';

// Icon: the small glyph printed next to the category name.
$string['categoryicon'] = 'Category icon';
$string['categoryiconemoji'] = 'Emoji icon';
$string['categoryiconemoji_help'] = 'A single emoji shown next to this category\'s name — in the page badge, the filter buttons and the section headings. Paste one, for example 💻 or 🎨.

Leave it empty for no icon. If you also upload an icon file below, the file is used instead.';
$string['categoryiconfile'] = 'Icon image';
$string['categoryiconfile_help'] = 'A small image used instead of the emoji — best as a square, transparent PNG or SVG, since it is printed at about 32px next to the category name.

For the large picture on cards and the category hero, use the Category image field above instead.';
$string['currenticon'] = 'Icon next to the name';
$string['noicon'] = 'No icon set for this category.';

$string['privacy:metadata'] = 'The NIT Categories plugin stores category images only. It stores no personal data.';

// ── Course catalogue (catalogue.php) ────────────────────────────────────────────
$string['catalogue'] = 'Course catalogue';
$string['breadcrumb'] = 'You are here';
$string['searchcourses'] = 'Search courses';
$string['coursesinscope'] = '{$a} courses';
$string['coursesfound'] = '{$a} courses';
$string['filters'] = 'Filters';
$string['clearall'] = 'Clear all';
$string['applyfilters'] = 'Apply filters';
$string['showall'] = 'Show all {$a}';
$string['showfewer'] = 'Show fewer';
$string['from'] = 'From';
$string['to'] = 'To';
$string['price'] = 'Price';
$string['freeonly'] = 'Free courses only';
$string['sortby'] = 'Sort';
$string['sortpopular'] = 'Most popular';
$string['sortnewest'] = 'Newest';
$string['sortname'] = 'Name (A–Z)';
$string['sortpricelow'] = 'Price: low to high';
$string['sortpricehigh'] = 'Price: high to low';
$string['nomatches'] = 'No courses match these filters';
$string['nomatcheshint'] = 'Try removing a filter or searching for something broader.';
$string['pagination'] = 'Result pages';
$string['perpage'] = 'Courses per page:';

// Card wording — shared with the category page so a course reads the same on both.
$string['enrolled'] = 'Enrolled';
$string['purchased'] = 'Purchased';
$string['insubscription'] = 'In your subscription';
$string['free'] = 'Free';
$string['coursedetails'] = 'Course details';
$string['enrol'] = 'Enroll';
$string['buynow'] = 'Buy now';
$string['defaultcurrency'] = 'EGP';

// Settings.
$string['catalogueheading'] = 'Course catalogue';
$string['cataloguedesc'] = 'The catalogue at /local/nit_category/catalogue.php builds its filters from the course custom fields that exist on this site: select fields and short-text fields become checkbox lists, checkbox fields become a single toggle, and number fields become a from/to range. A filter only appears when courses in view actually carry that field, so nothing has to be configured here for the page to work.';
$string['excludefilterfields'] = 'Fields never offered as filters';
$string['excludefilterfields_desc'] = 'Custom-field short names, separated by commas, that the catalogue should ignore even though their type would otherwise make a usable filter. Leave empty to offer every suitable field.';
$string['onecourse'] = '1 course';

// ── Categories ─────────────────────────────────────────────────────────────────
// What survives the retired all-categories grid: the subcategory switch and the
// depth label the catalogue's filter panel still draws, and the order a list of
// categories is read in (category_browser::sort_options()).
$string['includesubcategories'] = 'Include subcategories';
$string['categorydepth'] = 'How much of the tree to show';
$string['sortmostcourses'] = 'Most courses';

// Home-page "My courses" card (theme_nit home_my_course_block).
$string['homelesson'] = 'Lesson {$a->num}: {$a->name}';
$string['mycourses'] = 'My courses';
$string['mycoursesunavailable'] = 'The course cards cannot be shown: the theme_nit block file is missing or unreadable.';

// -- Site search (SRS 4.22) ---------------------------------------------------------------
// One control in the header, searching courses and categories at once, with the results
// grouped by kind and counted.
$string['searchtitle'] = 'Search';
$string['searchplaceholder'] = 'Search courses and categories';
$string['searchsite'] = 'Search the site';
$string['searchopen'] = 'Open search';
$string['searchclose'] = 'Close search';
$string['searchhint'] = 'Search the whole academy: course titles, subject areas and what a course covers.';
$string['searchtooshort'] = 'Type at least {$a} letters to search.';
$string['searchresults'] = '{$a->count} results for “{$a->query}”';
$string['searchoneresult'] = '1 result for “{$a}”';
$string['searchgroupcourses'] = 'Courses';
$string['searchgroupcategories'] = 'Categories';
$string['searchnothing'] = 'Nothing found for “{$a}”';
$string['searchnothinghint'] = 'Check the spelling, try a single broader word, or browse the catalogue instead.';
$string['searchseeall'] = 'See all {$a} results';
$string['searchseeallone'] = 'See the result';
$string['searchmoreresults'] = 'Show the other {$a}';
$string['searchrefine'] = 'Narrow it down:';
$string['searchrefinecourses'] = 'Filter these courses';
$string['searchsearching'] = 'Searching…';

// -- Failed-search report (AC-4.22.4) -----------------------------------------------------
$string['searchlog'] = 'Searches that found nothing';
$string['searchlogintro'] = 'Every term a learner searched for that returned no course and no category. Spelling variants of one word are counted as one term. Nothing here identifies who searched.';
$string['searchlogempty'] = 'Nothing yet: every search so far has found something.';
$string['searchlogsummary'] = '{$a->terms} terms, searched {$a->searches} times in all.';
$string['searchlogterm'] = 'Search term';
$string['searchloghits'] = 'Times searched';
$string['searchlogfirst'] = 'First searched';
$string['searchloglast'] = 'Last searched';
$string['searchlogsorthits'] = 'Most searched';
$string['searchlogsortrecent'] = 'Most recent';
$string['searchlogsortterm'] = 'Alphabetical';
$string['searchlogdeleted'] = 'Term removed from the report.';
$string['searchlogpurge'] = 'Empty the report';
$string['searchlogpurgeconfirm'] = 'Remove every term from the report? This is the only record of what learners could not find.';
$string['searchlogpurged'] = 'The report is now empty.';

// -- Privacy ------------------------------------------------------------------------------
$string['privacy:metadata'] = 'The catalogue pages store nothing about the person reading them. The record of searches that found nothing keeps the term and a count, and has no user column.';

// ── Filter panel (SRS §4.8: exactly six filters, in this order) ──────────────────
// The panel's own headings, used instead of the custom field's name so that a field
// titled "Total Number of Hours" still appears under the heading the design asks for.
$string['filtercategory'] = 'Category';
$string['filterlevel'] = 'Level';
$string['filterprice'] = 'Price range';
$string['filterlanguage'] = 'Language';
$string['filterduration'] = 'Duration';
$string['filtercertificate'] = 'Carries a certificate';
$string['durationshort'] = 'Under 10 hours';
$string['durationmedium'] = '10 – 25 hours';
$string['durationlong'] = 'Over 25 hours';
$string['hoursshort'] = '{$a} h';
$string['pricefrom'] = 'Lowest price';
$string['priceto'] = 'Highest price';

// Settings: which course field answers which filter.
$string['filterfieldsheading'] = 'Filter fields';
$string['filterfieldsdesc'] = 'The catalogue offers exactly the six filters of SRS §4.8 — category, level, price, language, duration and certificate. Category and price are built in; the other four read a course custom field, named below. Leave a box empty to use the default shortname; name a field that does not exist and that filter is simply left out of the panel.';
$string['filterfield_level'] = 'Level field';
$string['filterfield_level_desc'] = 'Short name of the course custom field holding the level. A select field gives a controlled list, which is what AC-4.8.5 requires. Default: level';
$string['filterfield_language'] = 'Language field';
$string['filterfield_language_desc'] = 'Short name of the course custom field holding the language of delivery. Default: language';
$string['filterfield_duration'] = 'Duration field';
$string['filterfield_duration_desc'] = 'Short name of the number field holding the course length in hours. It is offered as three bands — under 10, 10 to 25, over 25 — rather than as a from/to box. Default: total_number_of_hours';
$string['filterfield_certificate'] = 'Certificate field';
$string['filterfield_certificate_desc'] = 'Short name of the checkbox field marking a course as carrying a certificate. Default: certificate';
