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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_profilefields\signup_api;

defined('MOODLE_INTERNAL') || die();

/**
 * Describes the sign-up form as the site currently renders it.
 *
 * The replacement for `auth_email_get_signup_settings`, which only knows the
 * stock eight core boxes plus the raw custom-field records - not which of them
 * this site shows, what it calls them, what order they are in, or that consent
 * is asked for on the form itself.
 *
 * Pre-login by design (`loginrequired => false` in db/services.php): a visitor
 * creating an account has no token yet.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_signup_form extends external_api {

    /**
     * No parameters: the form is the same for everyone.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * The current sign-up form.
     *
     * @return array
     */
    public static function execute(): array {
        return signup_api::describe();
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'usernamefromemail' => new external_value(PARAM_BOOL,
                'True when the site builds the username from the email address. The client must NOT ask for a username.'),
            'usernamesource' => new external_value(PARAM_ALPHA,
                'Which part of the email becomes the username: "email" (whole address) or "localpart".'),
            'countryfromphone' => new external_value(PARAM_BOOL,
                'True when the country is taken from the phone field (the client need not ask for it separately).'),
            'ipmatchphone' => new external_value(PARAM_BOOL,
                'True when sign-up is refused if the caller\'s IP country differs from the phone country.'),
            'extendedusernamechars' => new external_value(PARAM_BOOL, 'Whether extended characters are allowed in usernames.'),
            'defaultcity' => new external_value(PARAM_NOTAGS, 'City stored when the form does not ask for one.'),
            'defaultcountry' => new external_value(PARAM_RAW, 'Country stored when the form does not ask for one.'),
            'passwordpolicy' => new external_value(PARAM_RAW, 'The password policy to show under the password box.'),
            'consent' => new external_single_structure([
                'required' => new external_value(PARAM_BOOL, 'True when the user must agree before the account is created.'),
                'label' => new external_value(PARAM_RAW, 'The checkbox label, with the policy documents linked from it.'),
                'documents' => new external_multiple_structure(
                    new external_single_structure([
                        'name' => new external_value(PARAM_RAW, 'Document name'),
                        'url' => new external_value(PARAM_URL, 'Where the document can be read in a browser'),
                        'policyid' => new external_value(PARAM_INT, 'Policy id'),
                        'versionid' => new external_value(PARAM_INT,
                            'Policy version id - pass it to local_profilefields_get_policy_documents to render the text '
                            . 'natively instead of opening the URL'),
                    ]), 'The policy documents the label links to.'
                ),
            ], 'The inline "I agree to the policies" checkbox.'),
            'fields' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_RAW,
                        'Submit the answer under this name: a plain name for a core field, "profile_field_x" for a custom '
                        . 'one (send those in customprofilefields), or "consent" for the agreement checkbox.'),
                    'shortname' => new external_value(PARAM_RAW, 'Field shortname, without the profile_field_ prefix.'),
                    'type' => new external_value(PARAM_RAW,
                        'text, email, password, select, checkbox, textarea, datetime, menu, phone, consent, ...'),
                    'label' => new external_value(PARAM_RAW, 'The label the site shows for this field, already localised.'),
                    'description' => new external_value(PARAM_RAW, 'Help text, when the field has any.'),
                    'required' => new external_value(PARAM_BOOL, 'Whether the field must be filled in.'),
                    'iscustom' => new external_value(PARAM_BOOL, 'True for a custom profile field.'),
                    'defaultvalue' => new external_value(PARAM_RAW, 'Default value, when the field has one.'),
                    'options' => new external_multiple_structure(
                        new external_single_structure([
                            'value' => new external_value(PARAM_RAW, 'The value to submit'),
                            'label' => new external_value(PARAM_RAW, 'What to show the user'),
                            'dialcode' => new external_value(PARAM_RAW, 'Dialling code, for a country option (e.g. "+20").'),
                        ]), 'Choices, for a field the user picks from. Empty for free-text fields.'
                    ),
                ]), 'The fields to show, in the order the site shows them.'
            ),
            'recaptchapublickey' => new external_value(PARAM_RAW, 'reCAPTCHA public key, when a captcha is required.',
                VALUE_OPTIONAL),
            'warnings' => new external_warnings(),
        ]);
    }
}
