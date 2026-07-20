<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Adds: Site administration → Plugins → Local plugins → Manage lesson packages.
    // This page now also hosts, as tabs, what used to be three standalone entries: the package half
    // of "Admin settings", "Assign package to student" and "Flex platform reports". The subscription
    // half of "Admin settings" moved to the Manage subscriptions page below.
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

    // Paid programs: price the enrol_programs plugin's programs (the plugin itself is untouched).
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_academy_manageprograms',
        get_string('manageprograms', 'local_academy'),
        new moodle_url('/local/academy/manage_programs.php')
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

    // Financial Reports: platform money overview + per-area revenue, and teacher withdrawals.
    // The URL keeps its original name so existing bookmarks/links stay valid.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_academy_managewithdrawals',
        get_string('financialreports', 'local_academy'),
        new moodle_url('/local/academy/manage_withdrawals.php')
    ));

    // Certificate eligibility rules (plugin-agnostic: decides WHO is eligible for a certificate).
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_academy_certeligibility',
        get_string('certeligibility', 'local_academy'),
        new moodle_url('/local/academy/certificate_eligibility.php')
    ));
}
