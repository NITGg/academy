<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_academysessions_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026062100) {
        $table = new xmldb_table('academy_live_sessions');
        $field = new xmldb_field('jitsiid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'googlemeetid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026062100, 'local', 'academysessions');
    }

    return true;
}
