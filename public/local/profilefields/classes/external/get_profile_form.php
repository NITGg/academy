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
 * Describes `/user/edit.php` as the site currently renders it for one user.
 *
 * The profile-side twin of `local_profilefields_get_signup_form`, and the thing
 * Moodle has never had: the sign-up form can at least be read out of
 * `auth_email_get_signup_settings`, but the profile edit form could only be
 * seen by loading the page in a browser. That is why the app has been opening
 * it in a WebView.
 *
 * Everything it returns is live: the field list, their order, their labels
 * (including any admin rename), which are required, and - the part a client
 * cannot guess - which are **locked** by the auth plugin and must be shown
 * read-only.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_profile_form extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT,
                'Whose profile form to describe. 0 (the default) means the calling user.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * The current profile edit form.
     *
     * @param int $userid whose form to describe, 0 for the caller's own
     * @return array
     */
    public static function execute($userid = 0): array {
        $params = self::validate_parameters(self::execute_parameters(), ['userid' => $userid]);

        $user = profile_api::get_user((int) $params['userid']);

        self::validate_context(context_user::instance($user->id));
        profile_api::require_can_edit($user);

        return profile_api::describe($user);
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'The user the form describes'),
            'emailchangepending' => new external_value(PARAM_RAW,
                'An address waiting for the user to confirm it by email, or "" when there is none. '
                . 'While this is set, the stored address is still the old one.'),
            'profileimageurl' => new external_value(PARAM_URL, 'Current profile picture, large'),
            'profileimageurlsmall' => new external_value(PARAM_URL, 'Current profile picture, small'),
            'sections' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_RAW, 'Section id, matching a field\'s "section"'),
                    'label' => new external_value(PARAM_RAW, 'The heading to show above the section'),
                ]), 'The form\'s sections, in order. "moodle" is the first, unheaded one.'
            ),
            'fields' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_RAW,
                        'Submit the answer under this name. Custom fields keep their "profile_field_" prefix; '
                        . 'the "About me" box is called "description".'),
                    'shortname' => new external_value(PARAM_RAW, 'Field shortname, without the profile_field_ prefix.'),
                    'type' => new external_value(PARAM_RAW,
                        'text, email, select, checkbox, editor, tags, datetime, menu, phone, ...'),
                    'label' => new external_value(PARAM_RAW, 'The label the site shows for this field, already localised.'),
                    'description' => new external_value(PARAM_RAW, 'Help text, when the field has any.'),
                    'required' => new external_value(PARAM_BOOL, 'Whether the field must be filled in.'),
                    'locked' => new external_value(PARAM_BOOL,
                        'True when the auth plugin forbids changing it. Show it read-only; a value sent for a '
                        . 'locked field is ignored, exactly as the web form ignores it.'),
                    'iscustom' => new external_value(PARAM_BOOL, 'True for a custom profile field.'),
                    'section' => new external_value(PARAM_RAW, 'Which section of the form the field belongs to.'),
                    'sectionlabel' => new external_value(PARAM_RAW, 'That section\'s heading.'),
                    'value' => new external_value(PARAM_RAW,
                        'What the field holds now. A phone field reads back as "EG:1012345678"; a datetime field '
                        . 'as a unix timestamp; a checkbox as 0 or 1.'),
                    'format' => new external_value(PARAM_INT,
                        'For "description", the FORMAT_* constant its value is in. 0 for every other field.'),
                    'options' => new external_multiple_structure(
                        new external_single_structure([
                            'value' => new external_value(PARAM_RAW, 'The value to submit'),
                            'label' => new external_value(PARAM_RAW, 'What to show the user'),
                            'dialcode' => new external_value(PARAM_RAW, 'Dialling code, for a country option (e.g. "+20").'),
                        ]), 'Choices, for a field the user picks from. Empty for free-text fields.'
                    ),
                ]), 'The fields to show, in the order the site shows them.'
            ),
            'warnings' => new external_warnings(),
        ]);
    }
}
