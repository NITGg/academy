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
 * Admin navigation and settings for local_msgrules.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $category = new admin_category('local_msgrules_cat', get_string('pluginname', 'local_msgrules'));
    $ADMIN->add('localplugins', $category);

    $settings = new admin_settingpage(
        'local_msgrules_settings',
        get_string('settings', 'local_msgrules')
    );

    // Off by default. Installing the plugin must not close a single conversation before an
    // administrator has chosen the modes and decided to turn it on.
    $enabled = new admin_setting_configcheckbox(
        'local_msgrules/enabled',
        get_string('enabled', 'local_msgrules'),
        get_string('enabled_desc', 'local_msgrules'),
        0
    );
    $enabled->set_updatedcallback('local_msgrules_enabled_changed');
    $settings->add($enabled);

    // "All courses" lives here rather than on the per-course page: it is a site policy, and
    // repeating it on a paginated list of courses would make it look like a per-course choice.
    $defaultmode = new admin_setting_configselect(
        'local_msgrules/defaultmode',
        get_string('defaultmode', 'local_msgrules'),
        get_string('defaultmode_desc', 'local_msgrules'),
        \local_msgrules\rules::MODE_OPEN,
        \local_msgrules\rules::get_modes()
    );
    $defaultmode->set_updatedcallback('local_msgrules_enabled_changed');
    $settings->add($defaultmode);

    $settings->add(new admin_setting_configtext(
        'local_msgrules/maxusers',
        get_string('maxusers', 'local_msgrules'),
        get_string('maxusers_desc', 'local_msgrules'),
        2000,
        PARAM_INT
    ));

    $ADMIN->add('local_msgrules_cat', $settings);

    $ADMIN->add('local_msgrules_cat', new admin_externalpage(
        'local_msgrules_manage',
        get_string('managecourses', 'local_msgrules'),
        new moodle_url('/local/msgrules/manage.php'),
        'local/msgrules:manage'
    ));
}
