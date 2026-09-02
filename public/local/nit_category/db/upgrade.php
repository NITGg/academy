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
 * Database upgrades for local_nit_category.
 *
 * The plugin had no tables at all until AC-4.22.4 asked for the record of searches that
 * find nothing, so install.xml arrived at the same time as this file. install.xml only
 * runs on a fresh install; every site that already has the plugin gets the table from the
 * step below.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Apply the schema changes for a new plugin version.
 *
 * @param int $oldversion the version currently installed
 * @return bool
 */
function xmldb_local_nit_category_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090210) {
        // The failed-search log (AC-4.22.4).
        $table = new xmldb_table('local_nit_cat_searchlog');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('termkey', XMLDB_TYPE_CHAR, '191', null, XMLDB_NOTNULL, null, null);
        $table->add_field('term', XMLDB_TYPE_CHAR, '191', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lang', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('hits', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timefirst', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timelast', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('termkey_uk', XMLDB_INDEX_UNIQUE, ['termkey']);
        $table->add_index('hits_idx', XMLDB_INDEX_NOTUNIQUE, ['hits']);
        $table->add_index('timelast_idx', XMLDB_INDEX_NOTUNIQUE, ['timelast']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026090210, 'local', 'nit_category');
    }

    return true;
}
