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

namespace local_profilefields\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_user;
use local_profilefields\resend_api;

defined('MOODLE_INTERNAL') || die();

/**
 * Sends the confirmation link again, for the app's Resend button.
 *
 * Core's equivalent is a query parameter on the login page
 * (`/login/index.php?resendconfirmemail=1`), which needs a username the app does
 * not have, replies with a rendered HTML page, and - until this plugin's hook got
 * hold of it - resent the *same* link rather than a new one. None of that is
 * usable from a client, so this is the app's door onto the same rules.
 *
 * Pre-login by design (`loginrequired => false` in db/services.php): the account
 * whose address is being confirmed cannot log in yet, which is the entire reason
 * this call exists.
 *
 * Being callable by anybody shapes the contract, and the shape is the interesting
 * part of this function - see {@see resend_api} for why an address with no
 * account behind it is answered as though a mail had gone out, and why it is
 * rate-limited anyway.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resend_confirmation extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'email' => new external_value(core_user::get_property_type('email'),
                'The address entered at sign-up. Trimmed and lower-cased before it is matched, exactly as '
                . 'local_profilefields_signup_user stored it.'),
        ]);
    }

    /**
     * Issue a new confirmation link, or say nothing while appearing to.
     *
     * @param string $email the address entered at sign-up
     * @return array
     */
    public static function execute($email): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['email' => $email]);

        // The confirmation email runs its subject through format_string(), which
        // needs a context, and a web-service request has set none.
        $PAGE->set_context(context_system::instance());

        return resend_api::resend((string) $params['email']);
    }

    /**
     * Describes the return value.
     *
     * One shape, whatever happened - including when nothing happened.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL,
                'True when the request was accepted. Note that this says nothing about whether an account exists: '
                . 'an address with no account behind it is answered exactly as one with.'),
            'message' => new external_value(PARAM_RAW,
                'Why the request was refused, already localised per moodlewssettinglang. Null on success.',
                VALUE_REQUIRED, null, NULL_ALLOWED),
            'errorcode' => new external_value(PARAM_ALPHANUMEXT,
                'Machine-readable reason: "toomanyrequests" when a rate limit was hit. Null on success.',
                VALUE_REQUIRED, null, NULL_ALLOWED),
            'retryafter' => new external_value(PARAM_INT,
                'Seconds before another request may be made - the countdown to put on the Resend button. Always '
                . 'present: the cooldown after an accepted request, the time left in the window after a refusal.'),
        ]);
    }
}
