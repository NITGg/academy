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
 * Google mobile authentication - upgrade steps.
 *
 * @package    local_googleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run the upgrade steps.
 *
 * @param int $oldversion version we are upgrading from
 * @return bool
 */
function xmldb_local_googleauth_upgrade($oldversion) {
    if ($oldversion < 2026080803) {
        // On iOS Google issues the ID token for the iOS OAuth client, not the web
        // one, so both must be accepted. Add whichever is missing; keep the rest.
        $ids = array_values(array_filter(preg_split('/[\s,]+/',
            (string) get_config('local_googleauth', 'clientids'))));
        foreach ([
            '31169484251-lkhbei8fv9eq6tc03u498569l9jobj9r.apps.googleusercontent.com', // Web (Android).
            '31169484251-750r71bpnfim0t3daedc01ppklhf0pvn.apps.googleusercontent.com', // iOS.
        ] as $id) {
            if (!in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        set_config('clientids', implode("\n", $ids), 'local_googleauth');
        upgrade_plugin_savepoint(true, 2026080803, 'local', 'googleauth');
    }
    return true;
}
