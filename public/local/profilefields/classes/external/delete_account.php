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
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_profilefields\account_api;
use local_profilefields\accountdeletion;
use local_profilefields\profile_api;

defined('MOODLE_INTERNAL') || die();

/**
 * "Delete my account" (AC-4.5.7), for a client that cannot post the web form.
 *
 * Deletes only the account of whoever is calling - there is no user id
 * parameter, by design. An administrator removing somebody else's account does
 * it from Moodle's own user management, where it is audited as an
 * administrative action.
 *
 * Both confirmations the web form asks for are asked for here, and for the same
 * reasons: the password is the specification's, and it rules out the case that
 * matters most - an unattended signed-in phone; the typed word is ours, because
 * this is the one action on the site with no undo and a single tap behind a
 * saved password is not really a decision. Get the word from
 * `local_profilefields_get_delete_account_info`; it is localised, so an Arabic
 * interface asks for the Arabic one.
 *
 * The deletion is an anonymisation, not a hard delete: financial records stay
 * intact and certificates already issued stay publicly verifiable. What goes is
 * the personal data, every session, every remembered device and every
 * web-service token - including the one this call arrived on. Treat a successful
 * response as the end of the session and return to the sign-in screen.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_account extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'password' => new external_value(PARAM_RAW, 'The account\'s current password.'),
            'confirmword' => new external_value(PARAM_TEXT,
                'The confirmation word, exactly as local_profilefields_get_delete_account_info gave it. '
                . 'Compared trimmed and case-insensitively.'),
        ]);
    }

    /**
     * Delete the calling account.
     *
     * @param string $password the account's current password
     * @param string $confirmword the confirmation word, as typed
     * @return array
     */
    public static function execute($password, $confirmword): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'password' => $password,
            'confirmword' => $confirmword,
        ]);

        $user = profile_api::get_user(0);

        self::validate_context(context_user::instance($user->id));

        // The gate the page applies before it will even draw the form. Not a field
        // error - there is nothing the caller could type to get past it.
        if (!accountdeletion::allowed($user)) {
            throw new \moodle_exception('deleteaccountrefused', 'local_profilefields');
        }

        $errors = account_api::deletion_errors($user, (string) $params['password'],
            (string) $params['confirmword']);

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
                'deleted' => false,
                'message' => '',
                'warnings' => $warnings,
            ];
        }

        if (!account_api::delete($user)) {
            throw new \moodle_exception('deleteaccountrefused', 'local_profilefields');
        }

        return [
            'deleted' => true,
            'message' => get_string('deleteaccountdone', 'local_profilefields'),
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
            'deleted' => new external_value(PARAM_BOOL,
                'True when the account is gone. Every token this account held, this one included, '
                . 'has been destroyed - sign out locally and go to the sign-in screen.'),
            'message' => new external_value(PARAM_RAW, 'What to tell the user, already localised.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
