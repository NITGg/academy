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
 * theme_nit upgrade steps.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the theme.
 *
 * @param int $oldversion the version being upgraded from
 * @return bool
 */
function xmldb_theme_nit_upgrade($oldversion): bool {
    if ($oldversion < 2026091401) {
        // The gear menu setting went through two earlier shapes on the same
        // day (2026-09-14): a text box whose lines were
        // `identifier,component|url|rule`, then six tick-box settings. Both
        // shipped. The text box is back, but its lines are now
        // `English|Arabic|link`, so a value saved in the first shape would be
        // read as names and links it never meant. Clear whatever either shape
        // left behind; admin_apply_default_settings() then writes the new
        // default at the end of this upgrade, exactly as on a fresh site.
        unset_config('gearmenuitems', 'theme_nit');
        foreach (['navigation', 'management'] as $group) {
            foreach (['show', 'name', 'pages'] as $what) {
                unset_config('gearmenu_' . $group . '_' . $what, 'theme_nit');
            }
        }

        upgrade_plugin_savepoint(true, 2026091401, 'theme', 'nit');
    }

    return true;
}
