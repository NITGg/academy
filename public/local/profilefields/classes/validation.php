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

use core_text;

defined('MOODLE_INTERNAL') || die();

/**
 * The field rules of AC-4.1.15 and the password rules of AC-4.1.6.
 *
 * The specification does not merely require that a bad value is rejected: it
 * fixes the sentence shown for each way of being bad, in each language, and
 * acceptance is tested against that sentence. Two consequences shape this class:
 *
 * 1. Moodle's own password policy prints every broken rule in one paragraph of
 *    its own wording. AC-4.1.6 asks for one message naming one rule, so
 *    {@see password()} re-checks the four rules in the order the specification
 *    lists them and returns the first that fails. The core policy stays switched
 *    on underneath - it is what protects the paths this class does not reach
 *    (admin-created accounts, CLI, web services other than ours) - so the site
 *    settings must still say 8 / 1 / 1 / 1 with `minpasswordnonalphanum` at 0,
 *    or core will reject a password this class considers fine and the learner
 *    will see both messages at once.
 *
 * 2. Every registration path has to give the same answer. The three paths are
 *    the web sign-up form, the app's `signup_user` web service, and
 *    `complete.php` where an account minted from Google claims its missing
 *    fields. They reach different Moodle callbacks, so they all call in here
 *    rather than each repeating the rules.
 *
 * Nothing in this class talks to the database or to the form API: it takes
 * values and returns messages, which is what makes it usable from all three.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validation {

    /** @var int Shortest accepted first or last name (AC-4.1.15). */
    const NAME_MIN = 2;

    /** @var int Longest accepted first or last name (AC-4.1.15). */
    const NAME_MAX = 50;

    /** @var int Shortest accepted password (AC-4.1.6). */
    const PASSWORD_MIN = 8;

    /**
     * The first password rule that fails, as a localised message.
     *
     * The order is the specification's own: length, uppercase, lowercase, digit.
     * A password that breaks three rules reports the length one, because that is
     * the rule the learner will fix first and re-reporting after each fix is the
     * behaviour the wireframe shows.
     *
     * `\p{Lu}` and `\p{Ll}` rather than `[A-Z]`/`[a-z]`: the messages say "English
     * letter" but the input may be pasted from anywhere, and a Cyrillic capital
     * satisfying the rule is better than a confusing rejection. The digit test
     * stays ASCII, since `\d` with `/u` would accept Arabic-Indic digits that the
     * learner's keyboard cannot reliably reproduce at login.
     *
     * @param string $password the candidate password
     * @return string|null the message, or null when every rule passes
     */
    public static function password(string $password): ?string {
        if (core_text::strlen($password) < self::PASSWORD_MIN) {
            return get_string('pwtooshort', 'local_profilefields');
        }
        if (!preg_match('/\p{Lu}/u', $password)) {
            return get_string('pwnoupper', 'local_profilefields');
        }
        if (!preg_match('/\p{Ll}/u', $password)) {
            return get_string('pwnolower', 'local_profilefields');
        }
        if (!preg_match('/[0-9]/', $password)) {
            return get_string('pwnodigit', 'local_profilefields');
        }

        return null;
    }

    /**
     * What is wrong with a first or last name, if anything.
     *
     * Three failures with three different messages: empty, out of range, and
     * disallowed characters. The character class is the specification's -
     * letters, spaces, hyphens, apostrophes - with `\p{L}` and `\p{M}` covering
     * Arabic script and its diacritics as well as Latin, per the sign-up screen's
     * "Arabic or Latin script accepted".
     *
     * Both curly and straight apostrophes are allowed: a name typed on an iPhone
     * arrives with U+2019 and refusing it would be a rule about keyboards rather
     * than about names.
     *
     * @param string $value the submitted name
     * @param string $emptykey lang string to use when the value is empty
     * @return string|null the message, or null when the name is acceptable
     */
    public static function name(string $value, string $emptykey): ?string {
        $value = trim($value);

        if ($value === '') {
            return get_string($emptykey, 'local_profilefields');
        }

        $length = core_text::strlen($value);
        if ($length < self::NAME_MIN || $length > self::NAME_MAX) {
            return get_string('errnamelength', 'local_profilefields');
        }

        if (!preg_match('/^[\p{L}\p{M} \'\x{2019}\-]+$/u', $value)) {
            return get_string('errnamechars', 'local_profilefields');
        }

        return null;
    }

    /**
     * What is wrong with the email address, if anything.
     *
     * Uniqueness is not checked here - that needs the database and core already
     * does it, with the login link of AC-4.1.2 bolted on in {@see signup}. This
     * covers only the two messages the specification words itself.
     *
     * @param string $value the submitted address
     * @return string|null the message, or null when the address is acceptable
     */
    public static function email(string $value): ?string {
        $value = trim($value);

        if ($value === '') {
            return get_string('erremailempty', 'local_profilefields');
        }
        if (!validate_email($value)) {
            return get_string('erremailformat', 'local_profilefields');
        }

        return null;
    }

    /**
     * What is wrong with the phone number, if anything.
     *
     * Deliberately only two rules. AC-4.1.5 removes uniqueness and length from
     * this field: "The platform applies no length rule, no format rule beyond
     * digits only, no uniqueness rule". Length is nevertheless still enforced by
     * profilefield_phone, per country, as an agreed departure from that sentence
     * - it catches a dropped or doubled digit, which is an everyday mistake, and
     * EAAC preferred the check to the letter of the clause. Uniqueness is off in
     * the field's own settings.
     *
     * Spaces and dashes are stripped before the digits-only test rather than
     * failing it: a learner typing "0100 123 4567" has entered digits only in
     * every sense they care about.
     *
     * @param string $value the submitted national number
     * @return string|null the message, or null when the number is acceptable
     */
    public static function phone(string $value): ?string {
        $value = trim($value);

        if ($value === '') {
            return get_string('errphoneempty', 'local_profilefields');
        }
        if (preg_match('/[^0-9 \-()]/', $value)) {
            return get_string('errphonedigits', 'local_profilefields');
        }

        return null;
    }

    /**
     * Is this address already spoken for, and what should we say about it?
     *
     * AC-4.1.2 asks for a message "together with a link to the login screen".
     * Core raises its own duplicate-email error in `signup_validate_data()` with
     * a link to *forgot password* instead, which sends a returning learner to
     * reset a password they very likely remember.
     *
     * We cannot edit that line - it is core - but we do not have to lose to it.
     * `login_signup_form::validation()` merges core's result with `+=`, which
     * keeps keys that are already set, and our callback runs first. Setting
     * `email` here therefore replaces core's message rather than competing with
     * it, and only for this one case: every other email failure still falls
     * through to core.
     *
     * The query is core's, including the case-insensitive comparison and the
     * mnet host restriction, so the two agree on what "already registered" means.
     *
     * @param string $email the submitted address
     * @return string|null the message, or null when the address is free
     */
    public static function email_taken(string $email): ?string {
        global $DB, $CFG;

        $email = trim($email);
        if ($email === '' || !validate_email($email)) {
            return null;
        }

        $sql = "SELECT id
                  FROM {user}
                 WHERE " . $DB->sql_equal('email', ':email1', false, true) . "
                   AND id IN (SELECT id
                                FROM {user}
                               WHERE " . $DB->sql_equal('email', ':email2', false, false) . "
                                 AND mnethostid = :mnethostid)";

        $exists = $DB->record_exists_sql($sql, [
            'email1' => $email,
            'email2' => $email,
            'mnethostid' => $CFG->mnet_localhost_id,
        ]);

        if (!$exists) {
            return null;
        }

        $link = \html_writer::link(
            new \moodle_url('/login/index.php'),
            get_string('emailexistsloginlink', 'local_profilefields')
        );

        return get_string('emailexistsloginhint', 'local_profilefields', $link);
    }

    /**
     * Every AC-4.1.15 message the submitted values earn, keyed by element name.
     *
     * Callers merge the result into whatever Moodle's own validation produced.
     * Ours are added second and therefore win, which is the point: core's wording
     * is not the wording the specification is tested against.
     *
     * Only keys actually present in `$data` are examined, so this is safe to call
     * from the completion form, which carries a subset of the fields.
     *
     * @param array $data submitted values, as the form exports them
     * @return array element name => message
     */
    public static function signup_fields(array $data): array {
        $errors = [];

        if (array_key_exists('firstname', $data)) {
            $error = self::name((string) $data['firstname'], 'errfirstnameempty');
            if ($error !== null) {
                $errors['firstname'] = $error;
            }
        }

        if (array_key_exists('lastname', $data)) {
            $error = self::name((string) $data['lastname'], 'errlastnameempty');
            if ($error !== null) {
                $errors['lastname'] = $error;
            }
        }

        if (array_key_exists('email', $data)) {
            // Shape first, then availability: telling someone a malformed address
            // is "already registered" would be nonsense, and the shape test is
            // the cheaper of the two.
            $error = self::email((string) $data['email'])
                ?? self::email_taken((string) $data['email']);
            if ($error !== null) {
                $errors['email'] = $error;
            }
        }

        // Deliberately not the password. It is checked by
        // local_profilefields_check_password_policy(), which core calls from
        // get_password_policy_errors() on every path there is - including this
        // one, through signup_validate_data(). Repeating it here would put the
        // same sentence on the same box twice.

        return $errors;
    }
}
