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

    // Manage single-course purchases (see who bought which course; "unbuy" to unenrol).
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_academy_managecourses',
        get_string('managecourses', 'local_academy'),
        new moodle_url('/local/academy/manage_courses.php')
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_academy_managecoupons',
        get_string('managecoupons', 'local_academy'),
        new moodle_url('/local/academy/manage_coupons.php')
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_academy_manageoffers',
        get_string('manageoffers', 'local_academy'),
        new moodle_url('/local/academy/manage_offers.php')
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

    // Certificate eligibility rules (plugin-agnostic: decides WHO is eligible for a certificate).
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_academy_certeligibility',
        get_string('certeligibility', 'local_academy'),
        new moodle_url('/local/academy/certificate_eligibility.php')
    ));
}
