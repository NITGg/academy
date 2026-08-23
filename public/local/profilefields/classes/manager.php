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
use core_user;

defined('MOODLE_INTERNAL') || die();

/**
 * The single place that decides where each user field is allowed to appear.
 *
 * Moodle splits this knowledge in two. Custom profile fields carry their own
 * `signup` / `visible` flags in `user_info_field`, so they are configurable per
 * field already. The eight fields hard-coded into `login/signup_form.php`
 * (username, password, email, email confirmation, first/last name, city,
 * country) are not configurable at all. This class stores the missing half in
 * plugin config and applies it through the `extend_signup_form` callback, so
 * core stays untouched.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {

    /** @var string Component name used for get_config()/set_config(). */
    const COMPONENT = 'local_profilefields';

    /** @var array|null Decoded per-field configuration, built once per request. */
    protected static $configcache = null;

    /** @var string Shown on the sign-up form and on the profile edit form. */
    const MODE_BOTH = 'both';

    /** @var string Shown on the sign-up form only. */
    const MODE_SIGNUP = 'signup';

    /** @var string Shown on the profile edit form only - hidden from sign-up. */
    const MODE_PROFILE = 'profile';

    /** @var string Shown nowhere. */
    const MODE_HIDDEN = 'hidden';

    /** @var string Username is the whole email address. */
    const USERNAME_EMAIL = 'email';

    /** @var string Username is the part of the email address before the "@". */
    const USERNAME_LOCALPART = 'localpart';

    /**
     * The core user fields this plugin knows how to move around.
     *
     * Per entry:
     *  - label:      language string id used as the row heading and as the default
     *                form label (core string unless labelcomponent says otherwise).
     *  - onsignup:   the field is part of `login_signup_form::definition()`.
     *  - onprofile:  the field is part of `useredit_shared_definition()`.
     *  - modes:      the placements an admin may choose. A single-entry list means
     *                the placement is fixed and the page shows it as read-only text.
     *  - canrequire: "required" is an admin decision rather than a core rule.
     *  - selectors:  CSS selectors for the profile edit form, used when the field
     *                is switched off there (see hook\output_callbacks).
     *
     * Fields Moodle cannot run without - password, email, first and last name -
     * are deliberately fixed: an account with no password or no name is broken,
     * not customised.
     *
     * @return array[] keyed by field name, in the order core adds them to sign-up
     */
    public static function core_fields(): array {
        return [
            'username' => [
                'label'      => 'username',
                'onsignup'   => true,
                'onprofile'  => false,
                'modes'      => [self::MODE_SIGNUP],
                'canrequire' => false,
                'selectors'  => [],
            ],
            'password' => [
                'label'      => 'password',
                'onsignup'   => true,
                'onprofile'  => false,
                'modes'      => [self::MODE_SIGNUP],
                'canrequire' => false,
                'selectors'  => [],
            ],
            'email' => [
                'label'      => 'email',
                'onsignup'   => true,
                'onprofile'  => true,
                'modes'      => [self::MODE_BOTH],
                'canrequire' => false,
                'selectors'  => ['#fitem_id_email'],
            ],
            'email2' => [
                'label'      => 'emailagain',
                'onsignup'   => true,
                'onprofile'  => false,
                'modes'      => [self::MODE_SIGNUP, self::MODE_HIDDEN],
                'canrequire' => false,
                'selectors'  => [],
            ],
            'firstname' => [
                'label'      => 'firstname',
                'onsignup'   => true,
                'onprofile'  => true,
                'modes'      => [self::MODE_BOTH],
                'canrequire' => false,
                'selectors'  => ['#fitem_id_firstname'],
            ],
            'lastname' => [
                'label'      => 'lastname',
                'onsignup'   => true,
                'onprofile'  => true,
                'modes'      => [self::MODE_BOTH],
                'canrequire' => false,
                'selectors'  => ['#fitem_id_lastname'],
            ],
            'city' => [
                'label'      => 'city',
                'onsignup'   => true,
                'onprofile'  => true,
                'modes'      => [self::MODE_BOTH, self::MODE_PROFILE, self::MODE_SIGNUP, self::MODE_HIDDEN],
                'canrequire' => true,
                'selectors'  => ['#fitem_id_city'],
            ],
            'country' => [
                'label'      => 'country',
                'onsignup'   => true,
                'onprofile'  => true,
                'modes'      => [self::MODE_BOTH, self::MODE_PROFILE, self::MODE_SIGNUP, self::MODE_HIDDEN],
                'canrequire' => true,
                'selectors'  => ['#fitem_id_country'],
            ],

            // Profile-only core fields. These never reach the sign-up form, so the
            // only choice is whether the user sees them when editing their profile.
            'maildisplay' => [
                'label'      => 'emaildisplay',
                'onsignup'   => false,
                'onprofile'  => true,
                'modes'      => [self::MODE_PROFILE, self::MODE_HIDDEN],
                'canrequire' => false,
                'selectors'  => ['#fitem_id_maildisplay'],
            ],
            'timezone' => [
                'label'      => 'timezone',
                'onsignup'   => false,
                'onprofile'  => true,
                'modes'      => [self::MODE_PROFILE, self::MODE_HIDDEN],
                'canrequire' => false,
                'selectors'  => ['#fitem_id_timezone'],
            ],
            'description' => [
                'label'      => 'userdescription',
                'onsignup'   => false,
                'onprofile'  => true,
                'modes'      => [self::MODE_PROFILE, self::MODE_HIDDEN],
                'canrequire' => false,
                'selectors'  => ['#fitem_id_description_editor'],
            ],
            'picture' => [
                'label'      => 'pictureofuser',
                'onsignup'   => false,
                'onprofile'  => true,
                'modes'      => [self::MODE_PROFILE, self::MODE_HIDDEN],
                'canrequire' => false,
                'selectors'  => ['fieldset[name="moodle_picture"]'],
            ],
            'additionalnames' => [
                'label'      => 'additionalnames',
                'onsignup'   => false,
                'onprofile'  => true,
                'modes'      => [self::MODE_PROFILE, self::MODE_HIDDEN],
                'canrequire' => false,
                'selectors'  => ['fieldset[name="moodle_additional_names"]'],
            ],
            'interests' => [
                'label'      => 'interests',
                'onsignup'   => false,
                'onprofile'  => true,
                'modes'      => [self::MODE_PROFILE, self::MODE_HIDDEN],
                'canrequire' => false,
                'selectors'  => ['fieldset[name="moodle_interests"]'],
            ],
            'optional' => [
                'label'          => 'optionalcorefields',
                'labelcomponent' => 'local_profilefields',
                'onsignup'       => false,
                'onprofile'      => true,
                'modes'          => [self::MODE_PROFILE, self::MODE_HIDDEN],
                'canrequire'     => false,
                'selectors'      => ['fieldset[name="moodle_optional"]'],
            ],
        ];
    }

    /**
     * Factory defaults, used until an admin saves the page for the first time.
     *
     * The two departures from stock Moodle are the ones the academy asked for:
     * the username box is gone from sign-up (derived from the email instead) and
     * the "Email (again)" box is switched off. Both are ordinary settings, so
     * either can be put back from the management page.
     *
     * @return array config map keyed by field name
     */
    public static function default_config(): array {
        $alwaysrequired = ['username', 'password', 'email', 'email2', 'firstname', 'lastname'];

        $defaults = [];
        $order = 0;
        foreach (self::core_fields() as $name => $meta) {
            $defaults[$name] = [
                'mode'     => $meta['modes'][0],
                'required' => in_array($name, $alwaysrequired, true),
                'label'    => '',
                'order'    => ($order += 10),
            ];
        }
        $defaults['email2']['mode'] = self::MODE_HIDDEN;

        return $defaults;
    }

    /**
     * Read the stored per-field configuration, filled in with defaults.
     *
     * Anything the stored blob does not mention falls back to the default, so a
     * core field added by a later Moodle release appears with sane settings
     * instead of an empty row.
     *
     * @return array config map keyed by field name
     */
    public static function get_config(): array {
        // Applying the layout asks this question once per field, several times over.
        if (self::$configcache !== null) {
            return self::$configcache;
        }

        $defaults = self::default_config();
        $stored = get_config(self::COMPONENT, 'corefields');
        $stored = empty($stored) ? null : json_decode($stored, true);
        if (!is_array($stored)) {
            return self::$configcache = $defaults;
        }

        $fields = self::core_fields();
        $config = [];
        foreach ($defaults as $name => $default) {
            $config[$name] = is_array($stored[$name] ?? null) ? ($stored[$name] + $default) : $default;
            // A mode that is no longer offered for this field (settings written by an
            // older version, or a hand-edited blob) must not silently hide a field.
            if (!in_array($config[$name]['mode'], $fields[$name]['modes'], true)) {
                $config[$name]['mode'] = $default['mode'];
            }
        }

        return self::$configcache = $config;
    }

    /**
     * Persist the per-field configuration.
     *
     * @param array $config config map keyed by field name
     * @return void
     */
    public static function save_config(array $config): void {
        set_config('corefields', json_encode($config), self::COMPONENT);
        self::$configcache = null;
    }

    /**
     * Where a single core field is configured to appear.
     *
     * @param string $name core field name
     * @return string one of the MODE_* constants
     */
    public static function mode(string $name): string {
        $config = self::get_config();
        return $config[$name]['mode'] ?? self::MODE_BOTH;
    }

    /**
     * Whether the field is shown on the sign-up form.
     *
     * @param string $name core field name
     * @return bool
     */
    public static function on_signup(string $name): bool {
        return in_array(self::mode($name), [self::MODE_BOTH, self::MODE_SIGNUP], true);
    }

    /**
     * Whether the field is shown on the profile edit form.
     *
     * @param string $name core field name
     * @return bool
     */
    public static function on_profile(string $name): bool {
        return in_array(self::mode($name), [self::MODE_BOTH, self::MODE_PROFILE], true);
    }

    /**
     * Whether the username box is replaced by a value derived from the email.
     *
     * @return bool
     */
    public static function username_from_email(): bool {
        $value = get_config(self::COMPONENT, 'usernamefromemail');
        // Never saved means "still on factory settings", and the shipped default is on.
        return $value === false ? true : (bool) $value;
    }

    /**
     * Which part of the email address becomes the username.
     *
     * @return string one of the USERNAME_* constants
     */
    public static function username_source(): string {
        $value = get_config(self::COMPONENT, 'usernamesource');
        return $value === self::USERNAME_LOCALPART ? self::USERNAME_LOCALPART : self::USERNAME_EMAIL;
    }

    /**
     * Turn an email address into a username that is free and legal.
     *
     * `PARAM_USERNAME` keeps "@", ".", "-" and "_", so a whole email address is a
     * valid username on a stock site; the local-part option exists for sites that
     * prefer something shorter. Either way a numeric suffix is appended until the
     * name is unused, which also covers `allowaccountssameemail` being on.
     *
     * @param string $email the address typed into the sign-up form
     * @return string the username, or '' when the address is unusable
     */
    public static function derive_username(string $email): string {
        global $CFG, $DB;

        $email = core_text::strtolower(trim($email));
        if ($email === '' || !validate_email($email)) {
            return '';
        }

        $base = $email;
        if (self::username_source() === self::USERNAME_LOCALPART) {
            $base = substr($email, 0, (int) strpos($email, '@'));
        }
        $base = core_user::clean_field($base, 'username');
        if ($base === '') {
            return '';
        }

        // The column holds 100 characters; leave room for the disambiguating suffix
        // so truncation can never make two different addresses collapse onto one name.
        $base = core_text::substr($base, 0, 90);

        $candidate = $base;
        $suffix = 1;
        while ($DB->record_exists('user', ['username' => $candidate, 'mnethostid' => $CFG->mnet_localhost_id])) {
            $suffix++;
            $candidate = $base . $suffix;
        }

        return $candidate;
    }

    /**
     * CSS selectors for the core fields switched off on the profile edit form.
     *
     * @return string[] selector list, possibly empty
     */
    public static function profile_hidden_selectors(): array {
        $selectors = [];
        foreach (self::core_fields() as $name => $meta) {
            if (empty($meta['onprofile']) || empty($meta['selectors'])) {
                continue;
            }
            if (!self::on_profile($name)) {
                $selectors = array_merge($selectors, $meta['selectors']);
            }
        }
        return $selectors;
    }
}
