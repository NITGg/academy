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
use local_profilefields\profile_api;

defined('MOODLE_INTERNAL') || die();

/**
 * `/local/profilefields/account.php?section=security` - the security pane (WF-5.2).
 *
 * Read-only, and that is the whole of it: the pane shows when the password was
 * last changed, whether it can be changed here at all, and what changing it will
 * cost - and none of those three facts is reachable from any existing web
 * service. An account that signs in through Google has no password held here, so
 * a client that draws the "Change password" button unconditionally sends that
 * user to a form they can never satisfy.
 *
 * The change itself is `local_academy_change_password`, which is the site's one
 * implementation of it.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_security extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * The caller's own security pane.
     *
     * @return array
     */
    public static function execute(): array {
        $user = profile_api::get_user(0);

        self::validate_context(context_user::instance($user->id));

        if (isguestuser($user)) {
            throw new \moodle_exception('noguest');
        }

        return account_api::security($user);
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'The account described.'),
            'auth' => new external_value(PARAM_PLUGIN, 'The auth plugin the account signs in with.'),
            'authname' => new external_value(PARAM_RAW, 'That plugin\'s name, ready to show.'),
            'canchangepassword' => new external_value(PARAM_BOOL,
                'True when there is a password here to change. False for an account that signs in '
                . 'through Google or another external directory - draw no button, show `lastchangedtext`.'),
            'passwordlastchanged' => new external_value(PARAM_INT,
                'When the password last changed, or 0. 0 means "this site does not record it" - core '
                . 'keeps the history only while $CFG->passwordreuselimit is above zero - not "never '
                . 'changed". Show `lastchangedtext`, which says the difference.'),
            'lastchangedtext' => new external_value(PARAM_RAW,
                'The sentence the pane shows beside the password, already localised.'),
            'changenote' => new external_value(PARAM_RAW,
                'What changing the password will do, said before it is done: it needs the current '
                . 'password, and it ends every other session - including this app\'s token.'),
            'passwordpolicy' => new external_value(PARAM_RAW,
                'The site\'s password rules as plain text, to show under the new-password box. '
                . '"" when the site enforces none.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
