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
 * Admin navigation for local_nit_emails.
 *
 * Two pages under Local plugins: the wording of the three transactional emails, and the
 * on/off switches for every event the site can notify about.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_nit_emails_templates',
        get_string('pluginname', 'local_nit_emails'),
        new moodle_url('/local/nit_emails/manage.php'),
        'local/nit_emails:manage'
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_nit_emails_events',
        get_string('events', 'local_nit_emails'),
        new moodle_url('/local/nit_emails/events.php'),
        'local/nit_emails:manage'
    ));
}
