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
 * Install-time conversion for the nitstudentname element.
 *
 * @package    customcertelement_nitstudentname
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Point every existing 'Student name' element at this element instead.
 *
 * Without this, installing the plugin would fix nothing: certificates already
 * designed keep their stock 'studentname' element and go on reprinting the live
 * name, and AC-4.5.1 would only hold for templates an administrator edited by
 * hand afterwards. Certificates are usually designed once and then left alone,
 * so "by hand afterwards" means "never".
 *
 * Safe to convert in place: the two elements store identical data (font, size,
 * colour, width and position - neither keeps anything of its own), so the row
 * needs no other change and nothing moves on the page. The only difference is
 * where the name is read from.
 *
 * Writing to customcert_elements from a customcert subplugin is proper: it is
 * this plugin type's own table, and the row being changed is this element's row.
 *
 * @return bool
 */
function xmldb_customcertelement_nitstudentname_install() {
    global $DB;

    if (!$DB->get_manager()->table_exists('customcert_elements')) {
        return true;
    }

    $converted = $DB->count_records('customcert_elements', ['element' => 'studentname']);

    $DB->set_field('customcert_elements', 'element', 'nitstudentname', ['element' => 'studentname']);

    if ($converted) {
        mtrace("customcertelement_nitstudentname: converted {$converted} 'Student name' element(s) "
            . 'so issued certificates keep the name they were earned under.');
    }

    return true;
}
