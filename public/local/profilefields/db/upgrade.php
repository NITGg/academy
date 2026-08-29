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
 * Upgrade steps for local_profilefields.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Run the plugin upgrade steps.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool
 */
function xmldb_local_profilefields_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026082402) {
        // Repair any recommended datetime field created without valid year
        // parameters, which otherwise makes every profile edit page fatal.
        \local_profilefields\provision::repair();

        upgrade_plugin_savepoint(true, 2026082402, 'local', 'profilefields');
    }

    if ($oldversion < 2026082403) {
        // Also collapse any {mlang} field names that leaked onto a site without a
        // multilang filter to render them.
        \local_profilefields\provision::repair();

        upgrade_plugin_savepoint(true, 2026082403, 'local', 'profilefields');
    }

    if ($oldversion < 2026082404) {
        // The first provisioning run aborted before setting the default sign-up
        // order (a since-fixed fatal in the cache purge), so set it now if the admin
        // has not arranged the fields themselves.
        \local_profilefields\provision::ensure_signup_order();

        upgrade_plugin_savepoint(true, 2026082404, 'local', 'profilefields');
    }

    if ($oldversion < 2026082600) {
        // Switch the completion gate on. Accounts created outside the sign-up form
        // (a Google login) skipped every field this plugin manages, which among
        // other things leaves `country` empty - and local_payments prices on it.
        // Off by default in the class so a fresh install never locks itself out;
        // an existing site that already relies on these fields wants it on.
        set_config(\local_profilefields\completion::SETTING, 1, \local_profilefields\manager::COMPONENT);

        upgrade_plugin_savepoint(true, 2026082600, 'local', 'profilefields');
    }

    if ($oldversion < 2026082900) {
        // The plugin had no tables of its own until now, so these are created here
        // rather than by install.xml on every existing site.
        $table = new xmldb_table('local_profilefields_log');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('ip', XMLDB_TYPE_CHAR, '45', null, XMLDB_NOTNULL, null, '');
        $table->add_field('declared', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, '');
        $table->add_field('detected', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, '');
        $table->add_field('reason', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, '');
        $table->add_field('origin', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'signup');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        $table->add_index('ip_idx', XMLDB_INDEX_NOTUNIQUE, ['ip']);
        $table->add_index('reason_idx', XMLDB_INDEX_NOTUNIQUE, ['reason']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_profilefields_ip');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ip', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('note', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('ip_uix', XMLDB_INDEX_UNIQUE, ['ip']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Make the "refuse an address we cannot place" rule explicit rather than
        // leaving it to the class default, so it shows as set on the register tab.
        set_config('blockunresolvedip', 1, \local_profilefields\manager::COMPONENT);

        upgrade_plugin_savepoint(true, 2026082900, 'local', 'profilefields');
    }

    return true;
}
