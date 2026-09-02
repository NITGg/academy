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
 * `/local/profilefields/deleteaccount.php` before anything is destroyed (WF-5.3).
 *
 * Ask this before drawing the screen. Two accounts may not delete themselves at
 * all - the guest account, and an administrator, because a site nobody can
 * administer is a worse outcome than an inconvenient account - and an account
 * that signs in through Google has no password here to confirm with, so the
 * confirmation the specification requires cannot be given. A client that draws
 * the form regardless offers all three of them a button that can only fail.
 *
 * The three sentences of the warning come back translated. They are AC-4.5.4's
 * wording and they matter: a learner who thinks deleting the account revokes the
 * certificate they earned will not click, and that would be the wrong reason to
 * stay.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_delete_account_info extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * What the delete pane has to say.
     *
     * @return array
     */
    public static function execute(): array {
        $user = profile_api::get_user(0);

        self::validate_context(context_user::instance($user->id));

        return account_api::deletion_info($user);
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'allowed' => new external_value(PARAM_BOOL,
                'True when local_profilefields_delete_account will accept this account. '
                . 'False: show `refusedreason` and no form.'),
            'refusedreason' => new external_value(PARAM_RAW,
                'Why not, ready to show. "" when it is allowed.'),
            'title' => new external_value(PARAM_RAW, 'The pane\'s heading.'),
            'cannotbeundone' => new external_value(PARAM_RAW,
                'The first line of the warning, drawn in bold: this cannot be undone.'),
            'warning' => new external_value(PARAM_RAW,
                'What is lost - access to purchased courses and to earned certificates.'),
            'retained' => new external_value(PARAM_RAW,
                'What survives - financial records, and certificates already issued stay verifiable. '
                . 'Show it: it is the part somebody hesitating most needs.'),
            'passwordlabel' => new external_value(PARAM_RAW, 'Label for the password box.'),
            'confirmword' => new external_value(PARAM_RAW,
                'The word the user must type, localised. Send it back as `confirmword`; it is compared '
                . 'case-insensitively and trimmed.'),
            'confirmlabel' => new external_value(PARAM_RAW, 'Label for the confirmation box, naming that word.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
