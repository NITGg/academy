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
 * Upgrade steps for local_msgrules.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_msgrules_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026090600) {
        // The first release drew rules between cohorts. That turned out to be the wrong model:
        // the restriction people actually want is per course - "students on this course may
        // write to each other and their teacher". Cohorts are dropped rather than migrated,
        // because there is no sensible mapping from a cohort pair onto a course mode.

        // Hand back every conversation the old model had closed, before the rules that
        // explain them disappear. Skipped when the class is not loadable for any reason -
        // an upgrade must not be the thing that fails here.
        try {
            \local_msgrules\sync::remove_all_managed();
        } catch (Throwable $e) {
            debugging('local_msgrules: could not clear old blocks: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $old = new xmldb_table('local_msgrules_rule');
        if ($dbman->table_exists($old)) {
            $dbman->drop_table($old);
        }

        $table = new xmldb_table('local_msgrules_course');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('mode', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        // One key, not a foreign key plus a unique index: both would want an index on the same
        // column and XMLDB rejects the collision. foreign-unique gives the reference and the
        // "one row per course" guarantee together.
        $table->add_key('courseid', XMLDB_KEY_FOREIGN_UNIQUE, ['courseid'], 'course', ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026090600, 'local', 'msgrules');
    }

    return true;
}
