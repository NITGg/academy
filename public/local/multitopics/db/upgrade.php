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
 * Upgrade steps for local_multitopics.
 *
 * @package    local_multitopics
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Run the upgrade steps.
 *
 * @param int $oldversion version we are upgrading from
 * @return bool
 */
function xmldb_local_multitopics_upgrade($oldversion) {
    if ($oldversion < 2026070403) {
        // The app's built-in Google web client id, so the feed publishes it on a
        // site whose settings page was saved with the field empty.
        if (trim((string) get_config('local_multitopics', 'google_client_id')) === '') {
            set_config('google_client_id',
                '31169484251-lkhbei8fv9eq6tc03u498569l9jobj9r.apps.googleusercontent.com', 'local_multitopics');
        }
        if (get_config('local_multitopics', 'app_in_review') === false) {
            set_config('app_in_review', 0, 'local_multitopics');
        }
        upgrade_plugin_savepoint(true, 2026070403, 'local', 'multitopics');
    }

    if ($oldversion < 2026070404.01) {
        // The feed no longer publishes a web-service token (the app keeps its
        // built-in one for now), so drop the setting it was read from.
        unset_config('admin_token', 'local_multitopics');
        upgrade_plugin_savepoint(true, 2026070404.01, 'local', 'multitopics');
    }
    return true;
}
