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

    if ($oldversion < 2026091300) {
        // Two course custom fields nobody should have had to tick. "Free" and
        // "Certificate" were checkboxes under "Other fields" on the course settings
        // form, and each said whatever the last editor remembered: a course whose
        // prices were removed stayed "paid" on its own page, a course that gained
        // a certificate activity advertised none. Both facts are now read from
        // the plugin that decides them - free = no active local_payments price
        // rule, certificate = the course contains a certificate activity
        // (\local_academy\certificates_api::course_has_certificate()) - by the
        // course page, the catalogue facet and the app's is_course_free call.
        //
        // Nothing reads the fields any more, so they go, and with them the
        // checkboxes on the form. Matched by shortname AND type, so a field an
        // administrator later gives one of these names for another purpose is
        // left alone; the handler call also drops their customfield_data rows.
        try {
            $handler = \core_course\customfield\course_handler::create();
            foreach ($handler->get_fields() as $field) {
                $shortname = (string) $field->get('shortname');
                if (!in_array($shortname, ['free', 'certificate'], true) || $field->get('type') !== 'checkbox') {
                    continue;
                }
                $handler->delete_field_configuration($field);
                mtrace("local_academy: removed the hand-ticked course custom field '{$shortname}'; " .
                    "the fact is now computed.");
            }
        } catch (\Throwable $e) {
            // A broken field definition must not stop the upgrade; the field can
            // still be deleted by hand under Site administration > Courses >
            // Course custom fields.
            mtrace('local_academy: could not remove the free/certificate custom fields: ' . $e->getMessage());
        }

        upgrade_plugin_savepoint(true, 2026091300, 'local', 'academy');
    }

    return true;
}
