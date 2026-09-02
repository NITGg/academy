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
use local_profilefields\account_api;
use local_profilefields\profile_api;

defined('MOODLE_INTERNAL') || die();

/**
 * `/local/profilefields/account.php` - the profile pane (WF-5.1).
 *
 * Not the same form as `local_profilefields_get_profile_form`, which describes
 * `/user/edit.php`: this screen shows only the core fields the administrator has
 * placed on the profile, groups the custom fields under their category headings,
 * carries the display value of every locked field, and never offers the e-mail
 * address as a box to type into.
 *
 * Save what comes back with `local_profilefields_update_profile` - the field
 * names here are the names it takes. The two exceptions are on purpose:
 *
 * - the picture is uploaded with core's `core_user_update_picture`;
 * - the e-mail address goes through `local_profilefields_request_email_change`,
 *   which asks for the account password first.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_account_profile extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * The caller's own account screen.
     *
     * There is no user id parameter, by design: the web screen has none either.
     * An administrator editing somebody else does it from Moodle's user
     * management, where it is audited as an administrative act.
     *
     * @return array
     */
    public static function execute(): array {
        global $USER;

        $user = profile_api::get_user(0);

        self::validate_context(context_user::instance($user->id));
        profile_api::require_can_edit($user);

        return account_api::describe($user);
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'The account described.'),
            'fullname' => new external_value(PARAM_RAW, 'The name to show at the top of the screen.'),
            'email' => new external_single_structure([
                'address' => new external_value(PARAM_RAW, 'The address on the account now.'),
                'masked' => new external_value(PARAM_RAW,
                    'The same address with its local part hidden, for a screen somebody else may be reading.'),
                'label' => new external_value(PARAM_RAW, 'The label the site uses for the address.'),
                'canchange' => new external_value(PARAM_BOOL,
                    'True when local_profilefields_request_email_change will accept a change - '
                    . 'show the "Change" button only then.'),
                'lockedreason' => new external_value(PARAM_RAW,
                    'Why it cannot be changed, ready to show. "" when it can.'),
                'help' => new external_value(PARAM_RAW, 'The sentence shown under the address.'),
                'pending' => new external_value(PARAM_RAW,
                    'An address waiting for its confirmation link to be opened, or "". While this is '
                    . 'set, `address` is still the old one.'),
            ], 'The e-mail row, which is never an editable field on this screen.'),
            'picture' => new external_single_structure([
                'enabled' => new external_value(PARAM_BOOL,
                    'False when the site or the administrator has turned pictures off - draw no control.'),
                'url' => new external_value(PARAM_URL, 'Current picture, large.'),
                'urlsmall' => new external_value(PARAM_URL, 'Current picture, small.'),
                'hasownpicture' => new external_value(PARAM_BOOL,
                    'True when the user has uploaded one - offer "Delete picture" only then.'),
                'label' => new external_value(PARAM_RAW, 'Label for the upload control.'),
                'help' => new external_value(PARAM_RAW, 'The sentence shown under it.'),
                'maxbytes' => new external_value(PARAM_INT, 'Largest file accepted, in bytes.'),
                'acceptedtypes' => new external_multiple_structure(
                    new external_value(PARAM_RAW, 'An accepted extension, e.g. ".jpg"'),
                    'What the upload will accept. Upload with core_user_update_picture.'
                ),
            ], 'The profile picture and what may replace it.'),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_RAW, 'Section id, matching a field\'s "section".'),
                    'label' => new external_value(PARAM_RAW, 'The heading to show above the section.'),
                ]), 'The screen\'s sections, in order.'
            ),
            'fields' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_RAW,
                        'Send the answer back to local_profilefields_update_profile under this name.'),
                    'shortname' => new external_value(PARAM_RAW, 'Field shortname, without the profile_field_ prefix.'),
                    'type' => new external_value(PARAM_RAW, 'text, select, checkbox, datetime, menu, phone, ...'),
                    'label' => new external_value(PARAM_RAW, 'The label the site shows, already localised.'),
                    'description' => new external_value(PARAM_RAW, 'Help text the field itself carries.'),
                    'required' => new external_value(PARAM_BOOL, 'Whether the field must be filled in.'),
                    'locked' => new external_value(PARAM_BOOL,
                        'True when it may not be changed. Show `displayvalue` read-only with a padlock; '
                        . 'a value sent for a locked field is ignored, exactly as the web form ignores it.'),
                    'iscustom' => new external_value(PARAM_BOOL, 'True for a custom profile field.'),
                    'section' => new external_value(PARAM_RAW, 'Which section the field belongs to.'),
                    'sectionlabel' => new external_value(PARAM_RAW, 'That section\'s heading.'),
                    'value' => new external_value(PARAM_RAW,
                        'The value to put in the control. A phone field reads back as "EG:1012345678"; '
                        . 'a datetime field as a unix timestamp; a checkbox as 0 or 1.'),
                    'displayvalue' => new external_value(PARAM_RAW,
                        'The same value written out for a reader - a country as its name, a menu as its '
                        . 'chosen option. This is what to show for a locked field.'),
                    'format' => new external_value(PARAM_INT, 'FORMAT_* constant, for a text-area field. 0 otherwise.'),
                    'help' => new external_value(PARAM_RAW,
                        'A sentence this screen owes the reader about this field, or "".'),
                    'options' => new external_multiple_structure(
                        new external_single_structure([
                            'value' => new external_value(PARAM_RAW, 'The value to submit.'),
                            'label' => new external_value(PARAM_RAW, 'What to show the user.'),
                            'dialcode' => new external_value(PARAM_RAW, 'Dialling code, for a country option (e.g. "+20").'),
                        ]), 'Choices, for a field the user picks from. Empty for free-text fields.'
                    ),
                ]), 'The fields to show, in the order the screen shows them.'
            ),
            'warnings' => new external_warnings(),
        ]);
    }
}
