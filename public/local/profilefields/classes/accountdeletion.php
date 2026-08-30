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

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * "Delete my account", as AC-4.5.7 defines it.
 *
 * The clause is short but it pulls in three directions at once:
 *
 *   "Deleting your account removes your access to every course you have
 *    purchased and to the certificates you have earned. This cannot be undone."
 *    ... "Deletion is executed as an anonymisation rather than a hard delete so
 *    that financial records remain intact. Certificates already issued remain
 *    publicly verifiable."
 *
 * So the row cannot go (payments reference the user id), the personal data
 * should go, and the name has to stay legible or an issued certificate stops
 * verifying. Moodle's own `delete_user()` already sits almost exactly on that
 * line - it marks the row deleted, scrambles the username, replaces the email
 * with a hash, unenrols from everything and unassigns every role, while leaving
 * the id and the name in place. So this class calls it rather than reimplementing
 * a soft delete, and adds the parts core has no opinion about:
 *
 * - the credentials that could rebuild a session (remember-me tokens, and every
 *   live session), which core does not touch on delete;
 * - the custom profile fields, which core leaves entirely alone - the phone
 *   number, national id, passport and date of birth would otherwise outlive the
 *   account they belong to;
 * - the profile picture files.
 *
 * What is deliberately kept: the first and last name. That is not an oversight.
 * mod_customcert stores only a user id against an issued certificate and renders
 * the name live from the user record, so scrubbing the name would blank out every
 * certificate the learner had already earned - which is the one thing AC-4.5.7
 * says must survive. The proper fix is for a certificate to carry the name it was
 * issued with (AC-4.5.1 wants that too), and until it does, the name has to stay.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class accountdeletion {

    /**
     * @var string[] Custom profile fields cleared on deletion.
     *
     * Matched by shortname, and absent ones are simply skipped, so a site that
     * never provisioned one of these is not a special case. Everything here is
     * personal data with no life after the account: a phone number, a government
     * identifier, a date of birth. Fields describing the *work* - job title,
     * company, industry - are cleared too, because together they re-identify a
     * person as surely as a name does.
     */
    const PERSONAL_FIELDS = [
        'phone', 'nationality', 'gender', 'dateofbirth', 'nationalid', 'passport',
        'jobtitle', 'company', 'industry', 'education', 'linkedin', 'website',
        'facebook', 'instagram', 'twitter', 'youtube', 'biography', 'resume',
    ];

    /**
     * Is this account allowed to delete itself?
     *
     * Two accounts are refused, both because the site would be worse off:
     * the guest account is shared, and an administrator deleting themselves can
     * leave a site nobody can administer.
     *
     * @param stdClass $user
     * @return bool
     */
    public static function allowed(stdClass $user): bool {
        return !empty($user->id)
            && empty($user->deleted)
            && !isguestuser($user)
            && !is_siteadmin($user);
    }

    /**
     * Anonymise the account and end every way back into it.
     *
     * Ordering matters. The tokens and sessions go *before* `delete_user()`,
     * because that call fires events other code listens to and there must be no
     * window in which a half-deleted account is still reachable from a browser.
     *
     * @param stdClass $user the account to delete
     * @return bool whether the account was deleted
     */
    public static function execute(stdClass $user): bool {
        global $DB;

        if (!self::allowed($user)) {
            return false;
        }

        // Nothing may be able to rebuild a session for this account afterwards.
        rememberme::revoke_all((int) $user->id);
        \core\session\manager::destroy_user_sessions((int) $user->id);

        // Before delete_user(), which scrambles the username these are keyed to.
        self::clear_custom_fields((int) $user->id);
        self::clear_picture((int) $user->id);
        verification::clear($user);

        // Core's soft delete: marks the row deleted, scrambles username and email,
        // unenrols from every course, unassigns every role, clears grades. Keeps
        // the id, so payments and certificate issues still resolve.
        return (bool) delete_user($user);
    }

    /**
     * Empty the custom profile fields that carry personal data.
     *
     * The rows are deleted rather than blanked. A blank row still says "this
     * account had a phone number recorded", and an empty string in a field that
     * is elsewhere required has a habit of confusing whatever reads it next.
     *
     * @param int $userid
     * @return void
     */
    protected static function clear_custom_fields(int $userid): void {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(self::PERSONAL_FIELDS, SQL_PARAMS_NAMED);
        $fieldids = $DB->get_fieldset_select('user_info_field', 'id', "shortname $insql", $params);

        if (!$fieldids) {
            return;
        }

        [$idsql, $idparams] = $DB->get_in_or_equal($fieldids, SQL_PARAMS_NAMED);
        $idparams['userid'] = $userid;

        $DB->delete_records_select('user_info_data', "userid = :userid AND fieldid $idsql", $idparams);
    }

    /**
     * Remove the uploaded profile picture.
     *
     * `delete_user()` sets `picture` to 0, which stops it being shown, but leaves
     * the image files themselves in the file pool. A photograph of somebody is
     * personal data whether or not a page currently renders it.
     *
     * @param int $userid
     * @return void
     */
    protected static function clear_picture(int $userid): void {
        try {
            $context = \context_user::instance($userid, IGNORE_MISSING);
            if (!$context) {
                return;
            }

            get_file_storage()->delete_area_files($context->id, 'user', 'icon');
        } catch (\Throwable $e) {
            // A picture that cannot be removed must not stop the deletion; the
            // account going is the part the learner asked for.
            debugging('local_profilefields: could not remove profile picture on deletion: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
