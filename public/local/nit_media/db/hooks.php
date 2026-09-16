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
 * Hook callbacks for local_nit_media.
 *
 * @package    local_nit_media
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    // The "Course promo video" field of the course settings form (course/edit.php):
    // an upload or a link, drawn under "Course image", checked and saved with
    // the rest of the course.
    [
        'hook' => \core_course\hook\after_form_definition::class,
        'callback' => [\local_nit_media\hook\course_form::class, 'after_form_definition'],
    ],
    [
        'hook' => \core_course\hook\after_form_validation::class,
        'callback' => [\local_nit_media\hook\course_form::class, 'after_form_validation'],
    ],
    [
        'hook' => \core_course\hook\after_form_submission::class,
        'callback' => [\local_nit_media\hook\course_form::class, 'after_form_submission'],
    ],
];
