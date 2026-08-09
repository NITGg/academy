<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_payments_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Future upgrades go here.

    return true;
}
