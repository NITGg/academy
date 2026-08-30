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
 * Admin tree entry for local_nit_instructors.
 *
 * The review queue is the only screen, and it carries a count of what is waiting -
 * an approval queue nobody can see the size of is an approval queue nobody clears.
 *
 * @package    local_nit_instructors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig || has_capability('local/nit_instructors:review', context_system::instance())) {
    $label = get_string('reviewqueue', 'local_nit_instructors');

    // Guarded: during the very first install the table does not exist yet, and the
    // admin tree is built before the upgrade that creates it.
    $waiting = \local_nit_instructors\profile::queue_count();
    if ($waiting > 0) {
        $label .= ' (' . $waiting . ')';
    }

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_nit_instructors_manage',
        $label,
        new moodle_url('/local/nit_instructors/manage.php'),
        'local/nit_instructors:review'
    ));
}
