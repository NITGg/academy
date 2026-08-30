<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_academy_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026070404) {
        // Password-reset OTP table.
        $table = new xmldb_table('academy_password_otps');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('otphash', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('resettoken', XMLDB_TYPE_CHAR, '64', null, null, null, null);
            $table->add_field('verified', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('attempts', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('expires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('email_idx', XMLDB_INDEX_NOTUNIQUE, ['email']);
            $table->add_index('resettoken_idx', XMLDB_INDEX_NOTUNIQUE, ['resettoken']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026070404, 'local', 'academy');
    }

    if ($oldversion < 2026083000) {
        // AC-4.5.1: the name a certificate was earned under. mod_customcert
        // stores nothing but userid/template/code and redraws the PDF live on
        // every download, so without a captured name a profile rename rewrites
        // every certificate the person already holds.
        $table = new xmldb_table('academy_certificate_names');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('issueid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('fullname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('issueid_idx', XMLDB_INDEX_UNIQUE, ['issueid']);
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $dbman->create_table($table);
        }

        // Certificates issued before today have no record of the name they were
        // earned under - nothing kept one - so today's name is the best answer
        // available, and capturing it at least freezes them from here on.
        $backfilled = \local_academy\certificate_names::backfill();
        if ($backfilled) {
            mtrace("local_academy: captured holder names for {$backfilled} previously issued certificate(s).");
        }

        upgrade_plugin_savepoint(true, 2026083000, 'local', 'academy');
    }

    return true;
}
