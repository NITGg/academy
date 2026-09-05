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
 * Web services for local_nit_category.
 *
 * One function, and it exists because the app could not ask the question the site's own
 * header box answers. Core's `core_course_search_courses` searches courses only — its
 * `criterianame` list is core Moodle's and no category belongs to it — so an app built on
 * it can never find a subject area. `local_nit_category_search` hands over the site's own
 * engine instead of a second one, which is the whole point: what "digital marketing" finds
 * is the same set on the phone, in the navbar drop-down and on the results page.
 *
 * The plugin's other reads stay on `/local/nit_category/home.php`, its own JSON feed. That
 * endpoint answers the home-page blocks, which are markup in a page and have a session; a
 * phone has a token, so this one is registered properly.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_nit_category_search' => [
        'classname'   => 'local_nit_category\external\search',
        'methodname'  => 'execute',
        'description' => 'Search courses and subject areas together, each group counted and paged '
            . 'separately, with priced course rows in the shape local_payments_get_courses_with_pricing '
            . 'returns.',
        'type'        => 'read',
        'ajax'        => true,
        'services'    => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
];
