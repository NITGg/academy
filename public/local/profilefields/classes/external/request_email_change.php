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

use context_user;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_text;
use local_profilefields\account;
use local_profilefields\core_locks;
use local_profilefields\profile_api;
use local_profilefields\validation;

defined('MOODLE_INTERNAL') || die();

/**
 * The "Change" button beside the e-mail address on the account screen (WF-5.1).
 *
 * The address is deliberately not an editable field on that screen, and this is
 * why: changing it is a two-step act that starts with proving the account is
 * yours. `local_profilefields_update_profile` will move an address, but it asks
 * for no password, so an app that used it for this button would be quietly
 * weaker than the web page - an unattended signed-in phone would be enough to
 * move somebody's account to an address the finder controls, and confirming that
 * address is how you take an account over.
 *
 * Nothing is applied here. The new address is held as a preference and becomes
 * the account's address only when the confirmation link sent to it is opened -
 * the same rule, through the same code, as the web form
 * ({@see \local_profilefields\form\changeemail_form}).
 *
 * Field problems come back in `warnings`, not as exceptions, so a client can
 * point at the box that is wrong.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_email_change extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'newemail' => new external_value(PARAM_RAW_TRIMMED, 'The address to move the account to.'),
            'password' => new external_value(PARAM_RAW,
                'The account\'s current password. Required: it is what proves the account is the '
                . 'caller\'s to move.'),
        ]);
    }

    /**
     * Start an address change.
     *
     * @param string $newemail the address to move to
     * @param string $password the account's current password
     * @return array
     */
    public static function execute($newemail, $password): array {
        global $DB, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'newemail' => $newemail,
            'password' => $password,
        ]);

        $user = profile_api::get_user(0);

        self::validate_context(context_user::instance($user->id));
        profile_api::require_can_edit($user);

        // The two refusals the web page makes before it even draws the form. They
        // are not field errors - there is nothing the caller could type to get
        // past them - so they are exceptions, as they are redirects there.
        if (core_locks::is_locked('email')) {
            throw new \moodle_exception('emailchangelocked', 'local_profilefields');
        }
        if (!account::can_verify_password($user)) {
            throw new \moodle_exception('emailchangeexternal', 'local_profilefields');
        }

        $email = core_text::strtolower(trim((string) $params['newemail']));
        $errors = [];

        // Exactly the checks changeemail_form::validation() makes, in the same
        // order and with the same messages.
        $shape = validation::email($email);
        if ($shape !== null) {
            $errors['newemail'] = $shape;

        } else if ($email === core_text::strtolower((string) $user->email)) {
            // Nothing to do, and saying so is friendlier than sending a fresh
            // confirmation to the address they are already waiting on.
            $errors['newemail'] = get_string('verifyemailtaken', 'local_profilefields');

        } else {
            $taken = $DB->record_exists_select(
                'user',
                $DB->sql_equal('email', ':email', false, false) . ' AND mnethostid = :mnethostid AND deleted = 0',
                ['email' => $email, 'mnethostid' => $CFG->mnet_localhost_id]
            );
            if ($taken) {
                $errors['newemail'] = get_string('verifyemailtaken', 'local_profilefields');
            }
        }

        if (!account::verify_password((int) $user->id, (string) $params['password'])) {
            $errors['password'] = get_string('deleteaccountwrongpassword', 'local_profilefields');
        }

        if ($errors) {
            $warnings = [];
            foreach ($errors as $item => $message) {
                $warnings[] = [
                    'item' => $item,
                    'itemid' => (int) $user->id,
                    'warningcode' => 'invalidvalue',
                    'message' => $message,
                ];
            }

            return [
                'sent' => false,
                'pending' => '',
                'message' => '',
                'warnings' => $warnings,
            ];
        }

        // Only the address is touched, and through the same call the web page
        // makes, so the confirmation key, the mail and the "not applied until
        // confirmed" rule are one implementation rather than two.
        $usernew = clone $user;
        $usernew->email = $email;

        $pending = profile_api::save($user, $usernew);

        return [
            'sent' => $pending !== '',
            'pending' => $pending,
            'message' => $pending !== ''
                ? get_string('changeemailsent', 'local_profilefields', $pending)
                : get_string('changesaved', 'local_profilefields'),
            'warnings' => [],
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sent' => new external_value(PARAM_BOOL,
                'True when a confirmation link has gone to the new address. The account\'s address '
                . 'has NOT changed yet, and will not until that link is opened.'),
            'pending' => new external_value(PARAM_RAW,
                'The address now waiting for confirmation, or "".'),
            'message' => new external_value(PARAM_RAW, 'What to tell the user, already localised.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
