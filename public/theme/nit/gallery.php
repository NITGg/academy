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
 * NIT design-system component gallery (admin/dev only).
 *
 * Living documentation and the accessibility test surface for the design
 * system. Reached from Site administration → Appearance → NIT Design System.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Handles login, the moodle/site:config capability, admin page layout and
// breadcrumb, using the external page registered in settings.php.
admin_externalpage_setup('theme_nit_gallery');

$gallery = new \theme_nit\output\gallery();

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_nit/gallery/showcase', $gallery->export_for_template($OUTPUT));
echo $OUTPUT->footer();
