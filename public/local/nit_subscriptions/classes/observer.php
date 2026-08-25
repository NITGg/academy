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
 * Event observers for local_nit_subscriptions.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_subscriptions;

defined('MOODLE_INTERNAL') || die();

/**
 * Keeps single-course purchases in step with real Moodle enrolment.
 */
class observer {

    /**
     * A user enrolment was deleted anywhere in Moodle — revoke the course purchase behind it.
     *
     * Without this, only the plugin's own "Unbuy" button revoked the purchase: core's Unenrol on
     * /user/index.php removed the enrolment but left the transaction COMPLETED, so the catalogue
     * card kept showing "Purchased" (no "Buy now") and the purchase could no longer be unbought.
     *
     * The manager makes this a no-op when another enrolment method still grants access, or when the
     * course is already gone.
     *
     * @param \core\event\user_enrolment_deleted $event
     * @return void
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event): void {
        course_purchase_manager::revoke_on_unenrolment(
            (int) $event->relateduserid,
            (int) $event->courseid
        );
    }
}
