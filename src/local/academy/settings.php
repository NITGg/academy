<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Adds: Site administration → Plugins → Local plugins → Manage lesson packages.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_academy_managepackages',
        get_string('managepackages', 'local_academy'),
        new moodle_url('/local/academy/manage_packages.php')
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_academy_managesettings',
        get_string('managesettings', 'local_academy'),
        new moodle_url('/local/academy/manage_settings.php')
    ));
}
