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

use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Which of the sign-up requirements an existing account still has outstanding.
 *
 * Why this exists
 * ---------------
 * `login/signup.php` is not the only way an account is born. An OAuth2 login
 * ("Log in with Google") builds the account straight from the provider's claims,
 * so it never renders the sign-up form and never collects anything the academy
 * added to it - phone, country, the terms checkbox. The account exists, but only
 * half of it was asked for.
 *
 * Moodle does notice part of this: `user_not_fully_set_up()` gates on required
 * *custom* profile fields and bounces the user to `/user/edit.php`. That covers
 * the phone but nothing else, and it lands them on the full profile editor,
 * which is not a registration step. It also misses two things that matter here:
 *
 *   - The built-in `country` field. Not a custom field, so core never checks it,
 *     yet `local_payments\country_detector` keys every price on it. An empty
 *     country falls back to IP geolocation and then to the course's default
 *     price - a guess, and the wrong one as soon as the buyer is travelling or
 *     behind a VPN. The profile country is the only answer they actually gave us.
 *   - The terms checkbox. This site keeps `sitepolicyhandler` on "Default" on
 *     purpose (see settings.php) so consent is one checkbox on the sign-up form
 *     rather than a separate tool_policy page - which also means tool_policy is
 *     not gating logins, so an OAuth2 account is never asked at all.
 *
 * So this class answers one question - "what would the sign-up form still be
 * asking this user for?" - and the gate, the page and the web service all read
 * the answer from here. Nothing re-implements the list a second time.
 *
 * The requirement is deliberately a *superset* of core's: whatever
 * `user_not_fully_set_up()` would gate on is included, so our page always fires
 * first and core's redirect to /user/edit.php never gets a turn.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion {

    /** @var string Admin setting: is the gate switched on. */
    const SETTING = 'completiongate';

    /** @var string User preference stamped once an account has been through this page. */
    const PREF_DONE = 'local_profilefields_completed';

    /**
     * Auth methods that build an account without ever showing the sign-up form.
     *
     * `email` is the sign-up form itself and `manual` is an admin filling the boxes
     * by hand - both mean a human answered the questions. `oauth2` (Log in with
     * Google / Apple) does not: it mints the account from the provider's claims.
     *
     * @var string[]
     */
    const SKIPS_SIGNUP = ['oauth2'];

    /**
     * Core fields that only exist while an account is being created.
     *
     * Asking an account that already exists for its password or username again is
     * meaningless, and the email is what the OAuth2 provider matched on, so it is
     * never blank. They are dropped from the requirement whatever the sign-up form
     * says about them.
     *
     * @var string[]
     */
    const SIGNUP_ONLY = ['username', 'password', 'email', 'email2'];

    /**
     * Built-in fields the completion form knows how to draw.
     *
     * These are every core field `manager::core_fields()` allows on sign-up and
     * allows to be made required, plus the two name boxes. The list is a hard gate,
     * not documentation: reporting a field the form cannot render would leave the
     * user submitting a page that never satisfies the requirement, and the gate
     * would bounce them back to it forever. Anything outside this list is left to
     * core's own `user_not_fully_set_up()` redirect.
     *
     * `form\complete_form::add_core_field()` must handle every name in here.
     *
     * @var string[]
     */
    const RENDERABLE_CORE = ['firstname', 'lastname', 'city', 'country'];

    /**
     * Fields an identity provider really does answer.
     *
     * Google and Apple return the name, so a value in these boxes came from a human
     * and is not re-asked. Nothing else on the sign-up form has a provider claim
     * behind it - a country or a city sitting on a fresh OAuth2 account is the site
     * default that user_create_user() stamped, which is exactly the case this class
     * exists to catch.
     *
     * @var string[]
     */
    const PROVIDER_SUPPLIED = ['firstname', 'lastname'];

    /**
     * Is the completion gate switched on?
     *
     * Defaults to off so that installing the plugin never locks a site out; the
     * upgrade step turns it on, and an admin can turn it back off from the field
     * management page if it ever misfires.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return (bool) get_config(manager::COMPONENT, self::SETTING);
    }

    /**
     * Does this user still owe the sign-up form anything?
     *
     * @param stdClass|null $user defaults to $USER
     * @return bool
     */
    public static function is_complete(?stdClass $user = null): bool {
        $missing = self::missing($user);

        return empty($missing['fields']) && empty($missing['consent']);
    }

    /**
     * Everything this user still has to fill in, in the order sign-up would ask.
     *
     * `fields` is one interleaved list, not core-then-custom: the admin's sign-up
     * order puts the phone between the name and the country, and the completion
     * page has to honour that or the two forms visibly differ. Each entry carries
     * the element name, and for a custom field the field object itself - it knows
     * how to render, validate and save itself, so nothing is restated here.
     *
     * @param stdClass|null $user defaults to $USER
     * @return array{fields: array[], consent: bool}
     */
    public static function missing(?stdClass $user = null): array {
        global $USER, $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $user = $user ?? $USER;

        // Nobody to ask: not logged in, the guest account, or a "log in as" session.
        if (empty($user->id) || isguestuser($user) || \core\session\manager::is_loggedinas()) {
            return ['fields' => [], 'consent' => false];
        }

        // "Still empty" is not a safe test on an account that never saw the sign-up
        // form. user_create_user() stamps `country` with the site's default country
        // (user/lib.php:104) and `city` with the site's default city, so a Google
        // account is born holding values nobody chose - and pricing keys on
        // `country`. For those accounts every required field is asked once, with
        // whatever the account currently holds prefilled, so the user confirms
        // rather than retypes.
        $askall = self::skipped_signup($user);
        $fields = [];

        foreach (manager::get_config() as $name => $settings) {
            if (in_array($name, self::SIGNUP_ONLY, true) || !in_array($name, self::RENDERABLE_CORE, true)) {
                continue;
            }
            if (empty($settings['required']) || !manager::on_signup($name)) {
                continue;
            }
            $current = trim((string) ($user->$name ?? ''));
            // The provider genuinely answered the name boxes, so those are only
            // asked when they really are blank. Everything else the provider cannot
            // know, and a value sitting in it came from the site defaults, not from
            // the user - so it is asked even though it looks filled in.
            $sweep = $askall && !in_array($name, self::PROVIDER_SUPPLIED, true);
            if ($sweep || $current === '') {
                $fields[] = [
                    'kind'    => 'core',
                    'token'   => $name,
                    'name'    => $name,
                    'field'   => null,
                    'current' => $current,
                ];
            }
        }

        foreach (profile_get_user_fields_with_data($user->id) as $field) {
            if ($field->is_locked() || !$field->get_field_config_for_external()['visible']) {
                continue;
            }
            // Otherwise the same test core runs in
            // profile_has_required_custom_fields_set(), so anything that would bounce
            // the user to /user/edit.php is asked here first - including a required
            // field the admin kept off sign-up.
            $unanswered = $field->is_empty() || ($askall && $field->is_signup_field());
            if ($field->is_required() && $unanswered) {
                $shortname = $field->field->shortname;
                $fields[] = [
                    'kind'    => 'custom',
                    'token'   => 'cf:' . $shortname,
                    'name'    => signup::CUSTOM_PREFIX . $shortname,
                    'field'   => $field,
                    'current' => '',
                ];
            }
        }

        return [
            'fields'  => self::order_by_signup($fields),
            'consent' => manager::consent_enabled() && empty($user->policyagreed),
        ];
    }

    /**
     * Sort the outstanding fields by where their token sits in the sign-up order.
     *
     * Anything the stored order does not mention goes to the end, keeping the
     * relative order it was collected in - which is how the sign-up form itself
     * treats a field the admin never arranged.
     *
     * @param array[] $fields entries from missing()
     * @return array[] the same entries, ordered
     */
    protected static function order_by_signup(array $fields): array {
        $order = array_flip(manager::signup_order());
        $last = count($order);

        // Decorate with the original index so unlisted fields keep their order:
        // usort() is not stable across every PHP build we might run on.
        $decorated = [];
        foreach ($fields as $i => $field) {
            $decorated[] = [$order[$field['token']] ?? $last, $i, $field];
        }

        usort($decorated, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_column($decorated, 2);
    }

    /**
     * Did this account come into being without anyone answering the sign-up form?
     *
     * True for an OAuth2 account that has not yet been through the completion page.
     * Stamping a preference rather than inferring it from the data is what makes
     * the answer stable: the fields the account was auto-filled with are
     * indistinguishable from fields the user genuinely chose, so the only reliable
     * record of "they have now been asked" is that we wrote it down.
     *
     * @param stdClass $user
     * @return bool
     */
    public static function skipped_signup(stdClass $user): bool {
        if (!in_array((string) ($user->auth ?? ''), self::SKIPS_SIGNUP, true)) {
            return false;
        }

        return !get_user_preferences(self::PREF_DONE, 0, $user->id);
    }

    /**
     * Record that this account has now answered the sign-up questions.
     *
     * @param stdClass $user
     * @return void
     */
    public static function mark_done(stdClass $user): void {
        set_user_preference(self::PREF_DONE, 1, $user->id);
    }

    /**
     * The page that collects what is outstanding.
     *
     * @param string $returnurl where to send the user once they are done
     * @return moodle_url
     */
    public static function url(string $returnurl = ''): moodle_url {
        $params = $returnurl === '' ? [] : ['returnurl' => $returnurl];

        return new moodle_url('/local/profilefields/complete.php', $params);
    }
}
