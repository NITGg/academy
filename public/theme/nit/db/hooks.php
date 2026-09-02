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
 * Hook callbacks for theme_nit.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        // Switch app WebView pages to the chrome-free `embedded` layout.
        'hook'     => \core\hook\output\before_http_headers::class,
        'callback' => \theme_nit\local\hook_callbacks::class . '::before_http_headers',
    ],
    [
        // Password strength meter + reveal toggle on the sign-up form.
        'hook'     => \core\hook\output\before_footer_html_generation::class,
        'callback' => \theme_nit\local\hook_callbacks::class . '::before_footer_html_generation',
    ],
    [
        // AC-4.9.8: schema.org/Course JSON-LD (name, description, price) in the
        // <head> of the course details page.
        'hook'     => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => \theme_nit\local\course_seo::class . '::before_standard_head_html_generation',
    ],
];
