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
 * Web-service (token) function: the subscription governing the caller's access right now.
 *
 * Mobile-facing twin of the ?function=get_my_active_subscription endpoint in api.php, which is what
 * the web pricing cards read to mark one card "your current plan", print the days left and turn the
 * button into Renew. An app drawing the same cards needs the same answer, and must not recompute it:
 * which purchase governs, how stacked renewals add up and when renewing is offered are server rules
 * (see nit_subscriptions_active_summary()).
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_subscriptions\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

global $CFG;
require_once($CFG->dirroot . '/local/nit_subscriptions/lib.php');

/**
 * Return the caller's active subscription state for a plan card.
 */
class get_my_active_subscription extends external_api {

    /**
     * Parameters: optional display language (mobile apps send it on every call).
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'lang'  => new external_value(PARAM_LANG, 'Display language, e.g. en or ar (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Fetch the state of the token user's live subscription.
     *
     * @param string $lang
     * @param string $alang
     * @return array
     */
    public static function execute(string $lang = '', string $alang = ''): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['lang' => $lang, 'alang' => $alang]);
        self::validate_context(\context_system::instance());
        $chosen = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($chosen !== '') {
            force_current_language($chosen);
        }
        return nit_subscriptions_active_summary((int) $USER->id);
    }

    /**
     * Return structure: every field is always present, zeroed when nothing is live.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'has_active'     => new external_value(PARAM_BOOL,
                'True when a subscription is live now; every other field is zeroed when false'),
            'subscriptionid' => new external_value(PARAM_INT,
                'Plan id that is live — match it against get_available_subscriptions to mark that card'),
            'expires_at'     => new external_value(PARAM_INT,
                'Unix time access ends, counting any renewal already paid for (0 = never)'),
            'expires_text'   => new external_value(PARAM_TEXT, 'expires_at as a localised date, ready to print'),
            'name'           => new external_value(PARAM_TEXT, 'Plan name in the requested language'),
            'days_left'      => new external_value(PARAM_INT,
                'Whole days until expires_at — the total, including any stacked renewal'),
            'price_paid'     => new external_value(PARAM_FLOAT, 'Amount charged for the governing purchase'),
            'renew_due'      => new external_value(PARAM_BOOL,
                'True when the plan is inside the admin\'s reminder window: show Renew instead of a '
                . 'disabled Active button. Renewing adds a period on top, it does not restart the clock'),
            'renew_window_days' => new external_value(PARAM_INT,
                'How many days before expiry renewing starts being offered (0 = reminders off)'),
            'renewed_expires_at' => new external_value(PARAM_INT,
                'Unix time access would end if the user renewed now (expires_at + one more period)'),
            'periods'        => new external_value(PARAM_INT, 'How many live purchases are stacked on this plan'),
            'renewed'        => new external_value(PARAM_BOOL,
                'True when periods > 1 — days_left covers more than the period running now, so say so'),
            'current_ends_at' => new external_value(PARAM_INT, 'Unix time the period running now ends'),
            'current_days_left' => new external_value(PARAM_INT, 'Whole days left in the period running now'),
        ]);
    }
}
