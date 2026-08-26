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
 * Scheduled task: end subscriptions whose deadline has passed.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_subscriptions\task;

defined('MOODLE_INTERNAL') || die();

use local_nit_subscriptions\subscription_purchase_manager;

/**
 * Close out subscriptions that have run out.
 *
 * A subscription is sold for a fixed number of days; when that window closes the student must
 * lose the courses the plan unlocked. This task is what enforces it: it flags every due
 * nit_sub_purchase `expired` and unenrols the student from the plan's courses (never from a
 * course they bought on its own, and never from one another live subscription still covers).
 *
 * Hourly is deliberate — a plan is sold in whole days, so an hour of grace either way is
 * invisible to the student while keeping the job cheap.
 */
class expire_subscriptions extends \local_nit_core\base\scheduled_task {

    /**
     * Name shown on Site administration › Server › Scheduled tasks.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_expire_subscriptions', 'local_nit_subscriptions');
    }

    /**
     * Expire what is due and report what changed.
     *
     * @return void
     */
    protected function run(): void {
        $result = subscription_purchase_manager::expire_due_purchases();

        if ($result['purchases'] > 0) {
            mtrace("local_nit_subscriptions: expired {$result['purchases']} subscription(s), "
                . "unenrolled from {$result['unenrolments']} course(s).");
        }
    }
}
