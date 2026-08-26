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
use local_profilefields\profile_api;

defined('MOODLE_INTERNAL') || die();

/**
 * Saves the profile edit form, as `/user/edit.php` saves it.
 *
 * The replacement for `core_user_update_users`, which cannot be used for this
 * at all: it requires `moodle/user:update`, a site-management capability, so an
 * ordinary user editing their own profile is refused. This function asks the
 * question the web page asks - `moodle/user:editownprofile` for yourself,
 * `moodle/user:editprofile` for someone else - and then runs the same
 * validation and the same save.
 *
 * A submission is a **partial update**: only the fields sent are changed, so an
 * app can save one screen at a time without blanking the rest. Field problems
 * come back in `warnings`, not as exceptions, so a client can point at the box.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_profile extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'fields' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_RAW,
                        'The field name, exactly as local_profilefields_get_profile_form gave it '
                        . '(e.g. firstname, city, description, profile_field_phone).'),
                    'value' => new external_value(PARAM_RAW,
                        'The new value. A phone field takes "EG:1012345678" or an encoded JSON object '
                        . '{"country":"EG","number":"1012345678"}; a datetime field takes a unix timestamp; '
                        . 'interests take a comma separated list.'),
                ]), 'The fields to change. Anything left out keeps its current value.'
            ),
            'userid' => new external_value(PARAM_INT,
                'Whose profile to update. 0 (the default) means the calling user.', VALUE_DEFAULT, 0),
            'descriptionformat' => new external_value(PARAM_INT,
                'The format the "description" value is in - 1 = HTML, 2 = plain text. Ignored when no '
                . 'description is sent.', VALUE_DEFAULT, FORMAT_HTML),
            'consent' => new external_value(PARAM_BOOL,
                'Set to 1 to record that the user accepted the site policies. Only needed when '
                . 'local_profilefields_get_completion_status reports consent.required - an account created '
                . 'outside the sign-up form (an OAuth2 login) was never asked. Ignored otherwise.',
                VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Save the profile.
     *
     * @param array $fields the fields to change
     * @param int $userid whose profile, 0 for the caller's own
     * @param int $descriptionformat the format the description value is in
     * @param bool $consent record acceptance of the site policies
     * @return array
     */
    public static function execute($fields, $userid = 0, $descriptionformat = FORMAT_HTML, $consent = 0): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'fields' => $fields,
            'userid' => $userid,
            'descriptionformat' => $descriptionformat,
            'consent' => $consent,
        ]);

        $user = profile_api::get_user((int) $params['userid']);

        self::validate_context(context_user::instance($user->id));
        profile_api::require_can_edit($user);

        $submitted = [];
        foreach ($params['fields'] as $one) {
            $submitted[$one['name']] = $one['value'];
        }

        // The live form is the authority on what exists, what is required and what
        // is locked - the same object the browser would be given.
        $described = profile_api::describe($user);

        $known = [];
        foreach ($described['fields'] as $field) {
            $known[$field['name']] = true;
        }
        foreach (array_keys($submitted) as $name) {
            if (!isset($known[$name])) {
                throw new \invalid_parameter_exception('Invalid field ' . $name);
            }
        }

        $usernew = profile_api::prepare_data($user, $described, $submitted, (int) $params['descriptionformat']);

        $errors = profile_api::validate($user, $usernew, $described, $submitted);
        if (!empty($errors)) {
            return [
                'success' => false,
                'emailchangepending' => (string) $described['emailchangepending'],
                'warnings' => self::as_warnings($errors),
            ];
        }

        $emailchanged = profile_api::save($user, $usernew);

        // The terms checkbox. Only ever moves from "not agreed" to "agreed", and
        // only for the profile's own owner - nobody consents on someone else's
        // behalf. This is the flag auth_email_signup_user() sets on a normal
        // sign-up; the account being updated here simply never went through one.
        if (!empty($params['consent']) && \local_profilefields\manager::consent_enabled()
                && empty($user->policyagreed) && (int) $user->id === (int) $USER->id) {
            $DB->set_field('user', 'policyagreed', 1, ['id' => $user->id]);
            $USER->policyagreed = 1;
        }

        return [
            'success' => true,
            'emailchangepending' => $emailchanged,
            'warnings' => [],
        ];
    }

    /**
     * Present field errors the way the sign-up service does.
     *
     * One deliberate difference from core: the message is plain text. Some of
     * them are built as HTML for the web form, and a native app wants the
     * sentence, not the markup.
     *
     * @param array $errors field name => message
     * @return array[] warning records
     */
    protected static function as_warnings(array $errors): array {
        $warnings = [];
        foreach ($errors as $item => $message) {
            $message = (string) $message;
            if (strpos($message, '<') !== false) {
                $message = trim(html_to_text($message, 0, false));
            }
            $warnings[] = [
                'item' => $item,
                'itemid' => 0,
                'warningcode' => 'fielderror',
                'message' => $message,
            ];
        }
        return $warnings;
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL,
                'True when the profile was saved. False means nothing was written and warnings says why.'),
            'emailchangepending' => new external_value(PARAM_RAW,
                'When the site asks for email changes to be confirmed, the new address it has just written to. '
                . 'The account still carries the old address until the user follows the link. "" otherwise.'),
            'warnings' => new external_warnings('The field the error belongs to',
                'Always 0', 'fielderror, with the message to show the user'),
        ]);
    }
}
