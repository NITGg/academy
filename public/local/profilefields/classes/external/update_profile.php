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
        global $USER;

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

        // What the sign-up flow is still owed. Read before the save, so the answer
        // is about the account as it arrived, not as this call leaves it.
        $outstanding = \local_profilefields\completion::missing($user);

        // The app's half of the rule /local/profilefields/complete.php applies: while
        // this call is finishing a registration, `country` follows the phone the user
        // just gave, so a Google account ends up with the same country an ordinary
        // sign-up would have stored. An ordinary later profile edit is untouched -
        // nothing is outstanding by then.
        self::apply_country_from_phone($user, $outstanding, $usernew, $submitted);

        $errors = profile_api::validate($user, $usernew, $described, $submitted);
        $errors = array_merge($errors, self::signup_only_errors($user, $outstanding, $usernew));
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
        // behalf. policies::agree() is the one place that records a tick, so the
        // app leaves exactly what the web form leaves: our own marker of it, the
        // `policyagreed` flag, and - since this caller is logged in - the versioned
        // tool_policy row. Setting the flag by hand here left no audit row at all,
        // and tool_policy would have recomputed the flag away at the first chance.
        if (!empty($params['consent']) && \local_profilefields\manager::consent_enabled()
                && !\local_profilefields\policies::has_agreed($user)
                && (int) $user->id === (int) $USER->id) {
            \local_profilefields\policies::agree((int) $user->id);
        }

        // This is the app's half of /local/profilefields/complete.php: when the call
        // answered everything that was outstanding, the account has been asked and
        // the gate is done with it. Deliberately strict - a partial profile edit
        // that merely happens to validate must not count, or an account could slip
        // past the questions it was never asked.
        self::mark_completion_done($user, $outstanding, $submitted, !empty($params['consent']));

        return [
            'success' => true,
            'emailchangepending' => $emailchanged,
            'warnings' => [],
        ];
    }

    /**
     * Store the phone's country in `country`, while this call is finishing sign-up.
     *
     * The web form does this in the browser when the Country box is on the page and
     * in complete.php when it is not; the sign-up web service does it in
     * `signup_api::normalise()`. This is the fourth and last path into the same
     * rule, so every way of registering leaves the same value behind. Only `country`
     * is written - `nationality`, where a site has one, is a different question and
     * is never derived from a dialling code.
     *
     * @param \stdClass $user the profile being saved
     * @param array $outstanding completion::missing() as it stood before the save
     * @param \stdClass $usernew the prepared values, modified in place
     * @param array $submitted the field values this call sent, keyed by element name
     * @return void
     */
    protected static function apply_country_from_phone(
        \stdClass $user,
        array $outstanding,
        \stdClass $usernew,
        array $submitted
    ): void {
        global $USER;

        if ((int) $user->id !== (int) $USER->id
                || !\local_profilefields\manager::country_from_phone()
                || empty(\local_profilefields\completion::blocking($outstanding['fields']))
                || array_key_exists('country', $submitted)) {
            return;
        }

        $iso = \local_profilefields\signup::phone_country((array) $usernew);
        if ($iso === '') {
            $iso = \local_profilefields\signup::stored_phone_country($user);
        }
        if ($iso !== '' && $iso !== (string) ($user->country ?? '')) {
            $usernew->country = $iso;
        }
    }

    /**
     * Rules that belong to the sign-up flow rather than to profile editing.
     *
     * Today that is two rules: the registration IP deny list, and the phone's
     * country having to match where the visitor appears to be (which also covers
     * "we could not place this address at all"). profilefield_phone applies the
     * second to a visitor creating an account and to nobody else, because an
     * ordinary profile edit is legitimately done from another country. But a call
     * that is finishing a registration IS the sign-up questions, just asked late -
     * so without this an OAuth2 account is the way around rules the sign-up form
     * enforces.
     *
     * The location rule only fires while the phone is one of the outstanding
     * fields, so a later, ordinary profile edit is untouched.
     *
     * @param \stdClass $user the profile being saved
     * @param array $outstanding completion::missing() as it stood before the save
     * @param \stdClass $usernew the prepared values
     * @return array element name => message
     */
    protected static function signup_only_errors(
        \stdClass $user,
        array $outstanding,
        \stdClass $usernew
    ): array {
        global $USER;

        if ((int) $user->id !== (int) $USER->id) {
            return [];
        }

        // The deny list, pinned to a field the client is actually showing - a
        // warning about an element the app never rendered is a warning nobody sees.
        $first = reset($outstanding['fields']);
        $blocked = \local_profilefields\signup::validate_ip_allowed(
            $first ? $first['name'] : 'consent');
        if ($blocked) {
            return $blocked;
        }

        if (!\local_profilefields\manager::ip_match_phone()) {
            return [];
        }

        foreach ($outstanding['fields'] as $entry) {
            if ($entry['kind'] !== 'custom'
                    || $entry['field']->field->datatype !== 'phone') {
                continue;
            }
            $value = $usernew->{$entry['name']} ?? null;
            $iso = is_array($value) ? (string) ($value['country'] ?? '') : '';
            if ($iso === '') {
                continue;
            }
            $mismatch = \local_profilefields\signup::ip_country_error($iso);
            if ($mismatch !== null) {
                return [$entry['name'] => $mismatch];
            }
        }

        return [];
    }

    /**
     * Stamp the "has answered the sign-up questions" marker, if this call earned it.
     *
     * @param \stdClass $user the profile that was saved
     * @param array $outstanding completion::missing() as it stood before the save
     * @param array $submitted the field values this call sent, keyed by element name
     * @param bool $consent whether the call also accepted the policies
     * @return void
     */
    protected static function mark_completion_done(
        \stdClass $user,
        array $outstanding,
        array $submitted,
        bool $consent
    ): void {
        global $USER;

        // Nobody completes a registration on someone else's behalf.
        if ((int) $user->id !== (int) $USER->id) {
            return;
        }
        // Only the requirements have to have been answered. An optional sign-up box
        // is offered by the form, and a client that leaves it out has still answered
        // everything that was asked of it - waiting for it would hold the account in
        // the gate for a value nobody insists on.
        $required = \local_profilefields\completion::blocking($outstanding['fields']);
        if (empty($required) && empty($outstanding['consent'])) {
            return;
        }
        if (!empty($outstanding['consent']) && !$consent) {
            return;
        }
        foreach ($required as $entry) {
            if (!array_key_exists($entry['name'], $submitted)) {
                return;
            }
        }

        \local_profilefields\completion::mark_done($user);
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
