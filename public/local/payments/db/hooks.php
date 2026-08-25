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
 * Hook callbacks for local_payments.
 *
 * @package    local_payments
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    // Locked course preview: open /course/view.php to everyone, activities locked.
    // Must run before the page calls require_login(), hence after_config.
    [
        'hook' => \core\hook\after_config::class,
        'callback' => [\local_payments\hook_callbacks::class, 'after_config'],
    ],
    // Payment gate: route an enrolment attempt on an unpaid course to the buy page.
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => [\local_payments\hook_callbacks::class, 'before_http_headers'],
    ],
    // "This course is locked" bar at the top of a previewed course page.
    [
        'hook' => \core\hook\output\before_standard_top_of_body_html_generation::class,
        'callback' => [\local_payments\local\hooks\output::class, 'before_standard_top_of_body_html_generation'],
    ],
    // Load the catalog course-card price badge script into the page head.
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => [\local_payments\local\hooks\output::class, 'before_standard_head_html_generation'],
    ],
];
