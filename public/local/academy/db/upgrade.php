<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_academy_upgrade($oldversion) {
    global $CFG, $DB;
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

    if ($oldversion < 2026091301) {
        // The course custom fields' group. Its heading is DATA, not a lang
        // string: core writes get_string('otherfields') into
        // customfield_category.name once, at creation, and from then on the
        // course settings form prints format_string() of that row - so the
        // Arabic form showed "Other fields" in English whatever the lang pack
        // said. The group is renamed to what it actually holds, in both
        // languages via {mlang} (filter_multilang2 runs on headings on this
        // site), and "Level" moves up to be its second field.
        //
        // The group is found by the field it holds rather than by name, so the
        // step still lands if the heading was already edited by hand; the
        // rename itself only replaces a heading that still says "Other fields".
        try {
            $handler = \core_course\customfield\course_handler::create();
            $group = null;
            $level = null;
            foreach ($handler->get_categories_with_fields() as $category) {
                foreach ($category->get_fields() as $field) {
                    if ($field->get('shortname') === 'level') {
                        $group = $category;
                        $level = $field;
                        break 2;
                    }
                }
            }

            if ($group) {
                $name = (string) $group->get('name');
                if (stripos($name, 'Other fields') !== false && stripos($name, 'Course File Summary') === false) {
                    $handler->rename_category($group,
                        '{mlang en}Course File Summary{mlang}{mlang ar}ملخص الدورة التدريبية{mlang}');
                    mtrace('local_academy: renamed the "Other fields" course custom field group to "Course File Summary".');
                }

                // Second place = before whichever field is currently second once
                // Level itself is taken out of the line.
                $fields = array_values(array_filter($group->get_fields(), function ($field) use ($level) {
                    return $field->get('id') != $level->get('id');
                }));
                usort($fields, function ($a, $b) {
                    return $a->get('sortorder') <=> $b->get('sortorder') ?: $a->get('id') <=> $b->get('id');
                });
                $before = isset($fields[1]) ? (int) $fields[1]->get('id') : 0;
                $handler->move_field($level, (int) $group->get('id'), $before);
                mtrace('local_academy: moved the "Level" course custom field to second place in its group.');
            } else {
                mtrace('local_academy: no course custom field "level" found; nothing to rename or move.');
            }
        } catch (\Throwable $e) {
            // Cosmetic; must not stop the upgrade. Both can be done by hand at
            // Site administration > Courses > Course custom fields.
            mtrace('local_academy: could not rename/reorder the course custom field group: ' . $e->getMessage());
        }

        upgrade_plugin_savepoint(true, 2026091301, 'local', 'academy');
    }

    if ($oldversion < 2026091600) {
        // The site opens in Arabic. Two core settings decide what a visitor with
        // no language history sees (Site administration > Language > Language
        // settings): the default language, and "Language autodetect", which
        // would still hand an English-browser visitor English regardless of the
        // default. Only the stock 'en' is moved — a site whose admin already
        // chose a default keeps it — and only when the Arabic pack is installed,
        // or every page would fall back to English string by string.
        if (get_string_manager()->translation_exists('ar', false)) {
            if (empty($CFG->lang) || $CFG->lang === 'en') {
                set_config('lang', 'ar');
                mtrace('local_academy: default site language set to Arabic (ar).');
            }
            set_config('autolang', 0);
        } else {
            mtrace('local_academy: Arabic language pack not installed - default language left as is. '
                . 'Install it at Site administration > Language > Language packs, then set the default language to Arabic.');
        }

        upgrade_plugin_savepoint(true, 2026091600, 'local', 'academy');
    }

    return true;
}
