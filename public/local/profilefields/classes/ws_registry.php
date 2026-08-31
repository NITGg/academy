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

namespace local_profilefields;

defined('MOODLE_INTERNAL') || die();

/**
 * Keeps this plugin's web-service functions together on whichever service uses them.
 *
 * Declaring a function in db/services.php registers it - it appears in
 * `external_functions` and in the "Add functions" picker - but it does not put it
 * on any service beyond the ones its own `services` key names, and the only name
 * a plugin can hard-code there is Moodle's built-in mobile service. A site that
 * calls these functions with a token of its own is therefore using a service an
 * admin created by hand, and every function added to this plugin afterwards has
 * to be added to that service by hand as well. Miss it and the call fails with
 * `accessexception` before a line of the function runs - which looks exactly like
 * a broken token, and is what happened to local_profilefields_resend_confirmation.
 *
 * So: treat the functions as families that belong together. If a service carries
 * any member of a family, it should carry all of them, and {@see self::sync()}
 * makes that true on every plugin upgrade. Nothing is created and no service is
 * touched that was not already using this plugin.
 *
 * A function an admin deliberately removed from a service will come back on the
 * next upgrade. That is the trade: the families are all-or-nothing by design -
 * the app's sign-up screen needs the whole of the sign-up family to work - and a
 * missing one is far more often the oversight above than a decision.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ws_registry {

    /**
     * The pre-login sign-up flow: describe the form, submit it, read the policies,
     * resend the confirmation link. All four are `loginrequired => false`, and a
     * client that can do one of them needs all four to finish an account.
     */
    public const SIGNUP = [
        'local_profilefields_get_signup_form',
        'local_profilefields_get_policy_documents',
        'local_profilefields_signup_user',
        'local_profilefields_resend_confirmation',
    ];

    /**
     * The signed-in profile flow: read the profile, describe the edit form, save
     * it, and ask what the sign-up flow is still owed. These act on the token's
     * own user, under the same capability checks /user/edit.php applies.
     */
    public const PROFILE = [
        'local_profilefields_get_profile',
        'local_profilefields_get_profile_form',
        'local_profilefields_update_profile',
        'local_profilefields_get_completion_status',
    ];

    /**
     * The families, keyed by name.
     *
     * @return array<string, string[]>
     */
    public static function families(): array {
        return ['signup' => self::SIGNUP, 'profile' => self::PROFILE];
    }

    /**
     * Every function this class looks after.
     *
     * @return string[]
     */
    public static function all(): array {
        return array_merge(self::SIGNUP, self::PROFILE);
    }

    /**
     * Complete each family on every service that already uses part of it.
     *
     * Idempotent, and cheap enough to run on each upgrade: on a site with no
     * hand-made service it is two queries per family and no writes.
     *
     * @param bool $apply false to report what is missing without writing it
     * @return array[] one row per missing function: [serviceid, servicename, function]
     */
    public static function sync(bool $apply = true): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/webservice/lib.php');

        $webservice = new \webservice();
        $missing = [];

        foreach (self::families() as $family) {
            [$insql, $inparams] = $DB->get_in_or_equal($family, SQL_PARAMS_NAMED);

            // Only functions this version actually registers - an older codebase on
            // the same database has fewer, and adding a name with no
            // `external_functions` row behind it just breaks the service page.
            $installed = $DB->get_fieldset_select('external_functions', 'name', "name {$insql}", $inparams);
            if (empty($installed)) {
                continue;
            }

            // The services already using this family. Deliberately not "all
            // services": a site's other services are none of our business.
            $serviceids = $DB->get_fieldset_select('external_services_functions',
                'DISTINCT externalserviceid', "functionname {$insql}", $inparams);

            foreach ($serviceids as $serviceid) {
                $service = $DB->get_record('external_services', ['id' => $serviceid]);
                if (!$service) {
                    continue;
                }
                foreach ($installed as $name) {
                    if ($DB->record_exists('external_services_functions',
                            ['externalserviceid' => $serviceid, 'functionname' => $name])) {
                        continue;
                    }
                    $missing[] = [
                        'serviceid' => (int) $serviceid,
                        'servicename' => $service->name,
                        'function' => $name,
                    ];
                    if ($apply) {
                        $webservice->add_external_function_to_service($name, $serviceid);
                    }
                }
            }
        }

        return $missing;
    }
}
