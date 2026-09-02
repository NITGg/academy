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

namespace local_academy\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_academy\password_reset_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * "Change password" on the account screen's security pane (WF-5.2), as a
 * standard web-service function.
 *
 * The same call `/local/academy/api.php?function=change_password` has always
 * made - {@see password_reset_manager::change_password()} is still the one
 * implementation - offered here as well so that an app building the account
 * screen out of `local_profilefields_*` functions does not have to change
 * protocol for one button. Either endpoint is fine; they do the same thing.
 *
 * Core has nothing for this. `core_auth_confirm_user` and the reset flow are
 * about forgotten passwords; changing a known one is `/login/change_password.php`,
 * a web form.
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class change_password extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'currentpassword' => new external_value(PARAM_RAW, 'The password on the account now.'),
            'newpassword' => new external_value(PARAM_RAW,
                'The new password. It must satisfy the site policy, which '
                . 'local_profilefields_get_security reports as `passwordpolicy`.'),
        ]);
    }

    /**
     * Change the calling account's password.
     *
     * @param string $currentpassword the password on the account now
     * @param string $newpassword the new one
     * @return array
     */
    public static function execute($currentpassword, $newpassword): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'currentpassword' => $currentpassword,
            'newpassword' => $newpassword,
        ]);

        self::validate_context(\context_user::instance($USER->id));

        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }

        // Throws on a wrong current password (err_wrongpassword), a policy the new
        // one fails (err_weakpassword, whose message is the policy itself), or an
        // account whose password is not ours to change (err_authnochange).
        $result = password_reset_manager::change_password((int) $USER->id,
            (string) $params['currentpassword'], (string) $params['newpassword']);

        return [
            'changed' => !empty($result['changed']),
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
            'changed' => new external_value(PARAM_BOOL,
                'True when the password was changed. Every session and every token this account '
                . 'held is now gone, the caller\'s token included (AC-4.5.2) - sign in again with '
                . 'the new password.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
