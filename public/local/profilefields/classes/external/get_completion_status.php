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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_profilefields\completion;
use local_profilefields\manager;
use local_profilefields\policies;
use local_profilefields\profile_api;

defined('MOODLE_INTERNAL') || die();

/**
 * What the signed-in user still owes the sign-up flow.
 *
 * The app's counterpart to `/local/profilefields/complete.php`. A Google sign-in
 * hands the app a session for an account that never saw the sign-up form, so the
 * app calls this straight after login: if `complete` is false it draws the fields
 * this returns, then saves them with `local_profilefields_update_profile` - the
 * same writer the profile screen already uses, with the same validation. No
 * second save path exists, so the two cannot drift.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_completion_status extends external_api {

    /**
     * No parameters: the answer is always about the token's own user.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Describe what is outstanding.
     *
     * @return array
     */
    public static function execute(): array {
        global $USER;

        $context = context_system::instance();
        self::validate_context($context);

        $missing = completion::missing($USER);
        $described = profile_api::describe($USER);

        // Index the profile form's own field descriptions so each outstanding field
        // is returned with the label, type and options the client needs to draw it -
        // read from the live form, never restated here.
        $byname = [];
        foreach ($described['fields'] as $field) {
            $byname[$field['name']] = $field;
        }

        $fields = [];
        foreach ($missing['fields'] as $entry) {
            if (isset($byname[$entry['name']])) {
                $fields[] = $byname[$entry['name']];
            }
        }

        $documents = [];
        foreach (policies::signup_document_records() as $doc) {
            $documents[] = [
                'name'      => $doc->name,
                'url'       => $doc->url,
                'policyid'  => $doc->policyid,
                'versionid' => $doc->versionid,
            ];
        }

        return [
            'complete'         => completion::is_complete($USER),
            'gateenabled'      => completion::enabled(),
            'countryfromphone' => manager::country_from_phone(),
            'fields'           => $fields,
            'consent'          => [
                'required'  => !empty($missing['consent']),
                'label'     => manager::consent_enabled() ? policies::consent_label() : '',
                'documents' => $documents,
            ],
        ];
    }

    /**
     * The shape of the answer.
     *
     * `fields` matches the entries `local_profilefields_get_profile_form` returns,
     * so a client that can already draw the profile form can draw this with no new
     * rendering code.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'complete' => new external_value(PARAM_BOOL,
                'True when nothing is outstanding and the app can carry on.'),
            'gateenabled' => new external_value(PARAM_BOOL,
                'Whether the site is currently holding incomplete accounts at all.'),
            'countryfromphone' => new external_value(PARAM_BOOL,
                'Whether the country should follow the phone field\'s country code.'),
            // Deliberately the same entry shape local_profilefields_get_profile_form
            // returns - it is built from the same describe() call - so a client that
            // can already draw the profile form can draw this with no new code.
            'fields' => new external_multiple_structure(
                new external_single_structure([
                    'name'        => new external_value(PARAM_RAW, 'The name to send back to update_profile.'),
                    'shortname'   => new external_value(PARAM_RAW, 'Field shortname.'),
                    'type'        => new external_value(PARAM_ALPHANUMEXT,
                        'text, select, checkbox, editor, datetime, phone, tags, ...'),
                    'label'       => new external_value(PARAM_RAW, 'Visible label.'),
                    'description' => new external_value(PARAM_RAW, 'Help text.'),
                    'required'    => new external_value(PARAM_BOOL, 'Always true here.'),
                    'locked'      => new external_value(PARAM_BOOL, 'Read-only; do not send it back.'),
                    'iscustom'    => new external_value(PARAM_BOOL, 'Custom profile field?'),
                    'value'       => new external_value(PARAM_RAW,
                        'What the account currently holds. Prefill the box with it: on an OAuth2 account '
                        . 'the country arrives pre-filled from the site default, and the user is confirming '
                        . 'it rather than typing it.'),
                    'options'     => new external_multiple_structure(
                        new external_single_structure([
                            'value'    => new external_value(PARAM_RAW, 'The value to submit.'),
                            'label'    => new external_value(PARAM_RAW, 'What to show the user.'),
                            'dialcode' => new external_value(PARAM_RAW,
                                'Dialling code, for a country option (e.g. "+20").'),
                        ]), 'Choices, for a field the user picks from. Empty for free-text fields.'
                    ),
                ]),
                'The fields still to be answered, in sign-up order.'
            ),
            'consent' => new external_single_structure([
                'required'  => new external_value(PARAM_BOOL, 'Terms not yet accepted.'),
                'label'     => new external_value(PARAM_RAW, 'Checkbox wording, with document links.'),
                'documents' => new external_multiple_structure(
                    new external_single_structure([
                        'name'      => new external_value(PARAM_RAW, 'Document name.'),
                        'url'       => new external_value(PARAM_URL, 'Web view of the document.'),
                        'policyid'  => new external_value(PARAM_INT, 'tool_policy policy id.'),
                        'versionid' => new external_value(PARAM_INT, 'tool_policy version id.'),
                    ]),
                    'Documents to show beside the checkbox.'
                ),
            ]),
        ]);
    }
}
