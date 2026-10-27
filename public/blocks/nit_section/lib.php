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
 * Library callbacks for block_nit_section.
 *
 * @package    block_nit_section
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Search and replace inside every section's content, e.g. when the site moves to a new URL.
 *
 * Called by core's db_replace() (Site administration > Development > Search and replace, or
 * admin/tool/replace/cli/replace.php). Block config is stored base64-serialised, which the
 * generic table-by-table pass cannot see into, so without this the home page keeps the old
 * address however many times the tool is run. Mirrors block_html_global_db_replace().
 *
 * @param string $search
 * @param string $replace
 */
function block_nit_section_global_db_replace($search, $replace) {
    global $DB;

    $instances = $DB->get_recordset('block_instances', ['blockname' => 'nit_section']);
    foreach ($instances as $instance) {
        $config = unserialize_object(base64_decode($instance->configdata));
        $changed = false;
        // One field per editing mode: raw HTML, the visual editor, and pre-mode-toggle blocks.
        foreach (['htmltext', 'visualtext', 'text'] as $field) {
            if (isset($config->$field) && is_string($config->$field)) {
                $new = str_replace($search, $replace, $config->$field);
                if ($new !== $config->$field) {
                    $config->$field = $new;
                    $changed = true;
                }
            }
        }
        if ($changed) {
            $DB->update_record('block_instances', ['id' => $instance->id,
                'configdata' => base64_encode(serialize($config)), 'timemodified' => time()]);
        }
    }
    $instances->close();
}
