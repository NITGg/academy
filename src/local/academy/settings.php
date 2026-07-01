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
        'local_academy_managesubscriptions',
        get_string('managesubscriptions', 'local_academy'),
        new moodle_url('/local/academy/manage_subscriptions.php')
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_academy_managesettings',
        get_string('managesettings', 'local_academy'),
        new moodle_url('/local/academy/manage_settings.php')
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_academy_managewithdrawals',
        get_string('managewithdrawals', 'local_academy'),
        new moodle_url('/local/academy/manage_withdrawals.php')
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_academy_assignpackage',
        get_string('assignpackage', 'local_academy'),
        new moodle_url('/local/academy/assign_package.php')
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_academy_reports',
        get_string('reports', 'local_academy'),
        new moodle_url('/local/academy/manage_reports.php')
    ));
}
