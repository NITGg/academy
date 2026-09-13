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
 * Database upgrade steps for local_nit_commerce.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin database.
 *
 * @param int $oldversion the currently installed version
 * @return bool
 */
function xmldb_local_nit_commerce_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026083100) {
        // Bilingual display name for coupons, stored as {mlang} markup like nit_offer.name.
        $table = new xmldb_table('nit_coupon');
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'code');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026083100, 'local', 'nit_commerce');
    }

    if ($oldversion < 2026090100) {
        // Bilingual display description for coupons and offers, stored as {mlang} markup like the names.
        foreach (['nit_coupon' => 'name', 'nit_offer' => 'name'] as $tablename => $after) {
            $table = new xmldb_table($tablename);
            $field = new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, $after);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026090100, 'local', 'nit_commerce');
    }

    if ($oldversion < 2026091203) {
        // The "Max discount amount" cap was withdrawn (2026-09-12): a bare number applied
        // against whichever currency the buyer is quoted in meant different things to
        // different buyers, and the business did not want it. The column goes with it, so a
        // value an admin once typed can never silently shrink a discount again.
        $table = new xmldb_table('nit_coupon');
        $field = new xmldb_field('max_discount');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026091203, 'local', 'nit_commerce');
    }

    if ($oldversion < 2026091300) {
        // "Usage type" (one-time | multiple) was withdrawn (2026-09-13): a coupon is always open
        // to every student, and how often is now two plain caps — usage_limit (all students
        // together, as before) and the new user_limit (one student). Existing rows keep the
        // behaviour they had under the old rule: a one-time coupon was a global cap of 1, and
        // every coupon was "each student once", so nothing that worked yesterday is refused or
        // over-accepted today. New coupons default to unlimited per student.
        $table = new xmldb_table('nit_coupon');
        $field = new xmldb_field('user_limit', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'usage_limit');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->set_field('nit_coupon', 'user_limit', 1);
        }
        $old = new xmldb_field('usage_type');
        if ($dbman->field_exists($table, $old)) {
            $DB->execute("UPDATE {nit_coupon} SET usage_limit = 1 WHERE usage_type = 'once'");
            $dbman->drop_field($table, $old);
        }
        upgrade_plugin_savepoint(true, 2026091300, 'local', 'nit_commerce');
    }

    return true;
}
