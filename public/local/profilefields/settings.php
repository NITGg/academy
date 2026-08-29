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
 * Admin tree entries for local_profilefields.
 *
 * Both pages sit under Plugins > Local plugins, alongside the rest of the
 * academy's own screens, rather than under Users > Accounts next to core's
 * "User profile fields": an admin looking for something we added looks in one
 * place, and the reports page has no counterpart in the Accounts tree at all.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_profilefields_manage',
        get_string('managefields', 'local_profilefields'),
        new moodle_url('/local/profilefields/manage.php'),
        'moodle/site:config'
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_profilefields_reports',
        get_string('reportstitle', 'local_profilefields'),
        new moodle_url('/local/profilefields/reports.php'),
        'moodle/site:config'
    ));
}

// Keep the policy document editor reachable when we take over the asking.
//
// tool_policy registers its 'tool_policy_managedocs' admin page only while it is
// the site policy handler (see admin/tool/policy/settings.php). Our inline
// sign-up checkbox deliberately wants the handler on "Default" so the user is
// not asked twice - which would leave managedocs.php / editpolicydoc.php dead
// with "Section error!". Registering the same page name here restores it. The
// two registrations are mutually exclusive by construction: core adds it when
// the handler is tool_policy, we add it when it is not.
$policyinstalled = core_component::get_component_directory('tool_policy') !== null;
$policyishandler = ($CFG->sitepolicyhandler ?? '') === 'tool_policy';

if ($policyinstalled && !$policyishandler && $ADMIN->locate('privacy')) {
    $managecap = 'tool/policy:managedocs';
    if ($hassiteconfig || has_capability($managecap, context_system::instance())) {
        $ADMIN->add('privacy', new admin_externalpage(
            'tool_policy_managedocs',
            new lang_string('managepolicies', 'tool_policy'),
            new moodle_url('/admin/tool/policy/managedocs.php'),
            $managecap
        ));
    }
}
