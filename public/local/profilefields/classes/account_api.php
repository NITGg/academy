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
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * The account screen (WF-5.1 to WF-5.3) expressed for a client that cannot
 * render the pages.
 *
 * `profile_api` already answers "what does /user/edit.php look like". This class
 * answers the narrower question the app actually asks: *what does
 * /local/profilefields/account.php look like* - which is not the same form. The
 * account screen shows only the core fields the administrator has placed on the
 * profile, groups the custom fields by their category, never offers the e-mail
 * address as a box to type in, and adds two panes core has no equivalent of at
 * all (the security summary, and deleting the account).
 *
 * The field *metadata* is not re-derived here. `profile_api::describe()` builds
 * the real form and reads the types, options, requiredness and auth-plugin locks
 * off it; this class takes that answer and decides only which of those fields
 * the account screen shows, in what order, and what each one reads as. So a
 * custom field an administrator adds, renames or locks reaches the app the same
 * way it reaches the web page, with no second implementation to keep in step.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class account_api {

    /** @var string The section core fields are drawn in. */
    const SECTION_CORE = 'core';

    /** @var string The section the interface language is drawn in. */
    const SECTION_PREFS = 'preferences';

    /**
     * The one sentence a field owes the reader, under the field it is about.
     *
     * The same two core rows the web form annotates ({@see
     * form\account_profile_form::add_core_help()}), plus the one custom field
     * that has an answer to give. Kept here as well as there because both screens
     * are showing the same field and must not explain it differently.
     *
     * @var array<string,string> field name => string id in this plugin
     */
    const FIELD_HELP = [
        'lastname' => 'namehelp',
        'country' => 'countryhelp',
        'profile_field_nationality' => 'nationalityhelp',
    ];

    /**
     * The account screen's navigation, as data.
     *
     * The same entries, in the same order, that {@see account::nav()} draws down
     * the left of the web screen - including the two that are only there when the
     * plugin behind them is installed.
     *
     * @param string $active the SECTION_* constant (or nav key) of the current entry
     * @return array[]
     */
    public static function menu(string $active = account::SECTION_PROFILE): array {
        $items = [];

        foreach (account::nav($active) as $item) {
            $items[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'url' => $item['url'],
                'active' => (bool) $item['active'],
                'danger' => (bool) $item['danger'],
            ];
        }

        return $items;
    }

    /**
     * The profile pane (WF-5.1), field by field.
     *
     * @param stdClass $user the account being shown - the caller's own
     * @return array the structure returned by local_profilefields_get_account_profile
     */
    public static function describe(stdClass $user): array {
        // The live form is the authority on every field's type, options,
        // requiredness and lock. Read once; everything below only chooses from it.
        $described = profile_api::describe($user);
        $known = [];
        foreach ($described['fields'] as $field) {
            $known[$field['name']] = $field;
        }

        $sections = [];
        $fields = [];

        $corefields = account::core_fields();
        if ($corefields) {
            $sections[] = [
                'name' => self::SECTION_CORE,
                'label' => get_string('navprofile', 'local_profilefields'),
            ];
        }

        foreach ($corefields as $name => $locked) {
            // The address is never a field on this screen - it is changed on a
            // card of its own, behind the account password. It is reported in
            // `email` below instead.
            if ($name === 'email') {
                continue;
            }

            $fields[] = self::core_field($user, $name, $locked, $known[$name] ?? null);
        }

        // Custom fields, in the site's own field order, with a section wherever
        // the profile-field category changes - the same break the web form draws.
        $category = null;
        $section = self::SECTION_CORE;
        $sectionlabel = get_string('navprofile', 'local_profilefields');

        foreach (account::profile_fields((int) $user->id) as $inputname => $field) {
            $name = (string) $field->get_category_name();
            if ($name !== '' && $name !== $category) {
                $category = $name;
                $section = 'cat_' . (int) $field->field->categoryid;
                $sectionlabel = format_string($name);
                $sections[] = ['name' => $section, 'label' => $sectionlabel];
            }

            $fields[] = self::custom_field($field, $inputname, $section, $sectionlabel,
                $known[$inputname] ?? null);
        }

        // The interface language. Always offered - it is a preference rather than
        // a profile field, so it is not on the management page and nothing can
        // hide it. Built by hand rather than taken from `$known`, because core
        // draws the language menu only while an account is being *created* and
        // sends an existing user to /user/language.php instead, so /user/edit.php
        // has no such element for describe() to have found. Save it with
        // local_profilefields_update_profile, which accepts it by name for exactly
        // this reason.
        $sections[] = ['name' => self::SECTION_PREFS, 'label' => get_string('preferences')];
        $fields[] = self::lang_field($user);

        return [
            'userid' => (int) $user->id,
            'fullname' => fullname($user),
            'email' => self::email_block($user, $described),
            'picture' => self::picture_block($user, $described),
            'sections' => $sections,
            'fields' => $fields,
            'warnings' => [],
        ];
    }

    /**
     * One core field, as the account screen shows it.
     *
     * @param stdClass $user the account being shown
     * @param string $name core field name
     * @param bool $locked whether the management page has closed it
     * @param array|null $meta the same field as profile_api::describe() saw it
     * @return array
     */
    protected static function core_field(stdClass $user, string $name, bool $locked, ?array $meta): array {
        $label = account::core_label($name);

        // A field the real form did not offer - which should not happen for the
        // five core fields, but a site can surprise you. Described from what is
        // known here rather than dropped, so the screen still shows it.
        $field = $meta ?? [
            'name' => $name,
            'shortname' => $name,
            'type' => $name === 'country' ? 'select' : 'text',
            'label' => $label,
            'description' => '',
            'required' => false,
            'locked' => true,
            'iscustom' => false,
            'section' => self::SECTION_CORE,
            'sectionlabel' => '',
            'value' => (string) ($user->{$name} ?? ''),
            'format' => 0,
            'options' => $name === 'country' ? self::country_options() : [],
        ];

        $field['label'] = $label;
        // Either lock is enough: the management page's, or the auth plugin's.
        $field['locked'] = $locked || !empty($field['locked']);
        $field['required'] = !$field['locked'] && self::is_required($name);
        $field['section'] = self::SECTION_CORE;
        $field['sectionlabel'] = get_string('navprofile', 'local_profilefields');
        $field['displayvalue'] = account::core_display($user, $name);
        $field['help'] = isset(self::FIELD_HELP[$name])
            ? get_string(self::FIELD_HELP[$name], 'local_profilefields') : '';

        return $field;
    }

    /**
     * One custom profile field, as the account screen shows it.
     *
     * @param \profile_field_base $field the field, with this user's data loaded
     * @param string $inputname its form element name (profile_field_xxx)
     * @param string $section the section it falls in
     * @param string $sectionlabel that section's heading
     * @param array|null $meta the same field as profile_api::describe() saw it
     * @return array
     */
    protected static function custom_field($field, string $inputname, string $section,
            string $sectionlabel, ?array $meta): array {
        $canoverride = has_capability('moodle/user:update', \context_system::instance());

        $described = $meta ?? [
            'name' => $inputname,
            'shortname' => (string) $field->field->shortname,
            'type' => (string) $field->field->datatype,
            'label' => format_string($field->field->name),
            'description' => (string) $field->field->description,
            'required' => (bool) $field->field->required,
            'locked' => true,
            'iscustom' => true,
            'value' => (string) ($field->data ?? ''),
            'format' => 0,
            'options' => [],
        ];

        $described['locked'] = ($field->is_locked() && !$canoverride) || !empty($described['locked']);
        $described['section'] = $section;
        $described['sectionlabel'] = $sectionlabel;
        // display_data() is the field plugin's own rendering - a menu shows its
        // chosen option, a date shows a date - and it is already safe HTML.
        $described['displayvalue'] = (string) $field->display_data();
        $described['help'] = isset(self::FIELD_HELP[$inputname])
            ? get_string(self::FIELD_HELP[$inputname], 'local_profilefields') : '';

        return $described;
    }

    /**
     * The interface-language row.
     *
     * The account screen's own control ({@see form\account_profile_form::add_language()}),
     * offered to everybody: core's per-auth field locks have no entry for it and
     * the management page does not list it, so there is nothing that could close
     * it.
     *
     * @param stdClass $user the account being shown
     * @return array
     */
    protected static function lang_field(stdClass $user): array {
        global $CFG;

        $translations = get_string_manager()->get_list_of_translations();
        $current = (string) (!empty($user->lang) ? $user->lang : $CFG->lang);

        $options = [];
        foreach ($translations as $code => $name) {
            $options[] = ['value' => (string) $code, 'label' => (string) $name, 'dialcode' => ''];
        }

        return [
            'name' => 'lang',
            'shortname' => 'lang',
            'type' => 'select',
            'label' => get_string('preferredlanguage'),
            'description' => '',
            'required' => false,
            'locked' => false,
            'iscustom' => false,
            'section' => self::SECTION_PREFS,
            'sectionlabel' => get_string('preferences'),
            'value' => $current,
            'displayvalue' => (string) ($translations[$current] ?? $current),
            'format' => 0,
            'help' => get_string('preferredlanguagehelp', 'local_profilefields'),
            'options' => $options,
        ];
    }

    /**
     * The e-mail row: what it is, and whether it can be changed at all.
     *
     * Two independent reasons it may not be, and they are not the same reason, so
     * they do not carry the same sentence: the administrator has locked the
     * field, or the account signs in through Google and has no local password to
     * confirm a change with.
     *
     * @param stdClass $user the account being shown
     * @param array $described profile_api::describe() output, for the pending address
     * @return array
     */
    protected static function email_block(stdClass $user, array $described): array {
        $locked = core_locks::is_locked('email');
        $canverify = account::can_verify_password($user);
        $canchange = !$locked && $canverify;

        $reason = '';
        if ($locked) {
            $reason = get_string('lockedfield', 'local_profilefields');
        } else if (!$canverify) {
            $reason = get_string('emailchangeexternal', 'local_profilefields');
        }

        return [
            'address' => (string) $user->email,
            'masked' => form\changeemail_form::mask((string) $user->email),
            'label' => account::core_label('email'),
            'canchange' => $canchange,
            'lockedreason' => $reason,
            'help' => get_string('emailchangehelp', 'local_profilefields'),
            // While this is set the stored address is still the old one: the new
            // one applies only when its confirmation link is opened.
            'pending' => (string) $described['emailchangepending'],
        ];
    }

    /**
     * The profile picture, and what may replace it.
     *
     * The upload itself is core's `core_user_update_picture`, which already
     * handles the draft file area; what a client cannot read from anywhere is
     * whether this screen offers the control at all and what it will accept.
     *
     * @param stdClass $user the account being shown
     * @param array $described profile_api::describe() output, for the current URLs
     * @return array
     */
    protected static function picture_block(stdClass $user, array $described): array {
        $options = form\account_profile_form::filemanager_options();

        return [
            'enabled' => form\account_profile_form::picture_enabled(),
            'url' => (string) $described['profileimageurl'],
            'urlsmall' => (string) $described['profileimageurlsmall'],
            'hasownpicture' => !empty($user->picture),
            'label' => get_string('profilepicture', 'local_profilefields'),
            'help' => get_string('picturehelp', 'local_profilefields'),
            'maxbytes' => (int) $options['maxbytes'],
            'acceptedtypes' => array_values($options['accepted_types']),
        ];
    }

    /**
     * The security pane (WF-5.2).
     *
     * Read-only. Changing the password is `local_academy_change_password`, which
     * is the one implementation of that on this site - see
     * {@see \local_academy\password_reset_manager::change_password()}.
     *
     * @param stdClass $user the account being shown
     * @return array the structure returned by local_profilefields_get_security
     */
    public static function security(stdClass $user): array {
        $canchange = account::can_verify_password($user);
        $changed = account::password_changed((int) $user->id);

        // AC-4.5.2's two facts about changing a password, said before it is
        // changed rather than discovered afterwards when every other session has
        // dropped.
        $lastchangedtext = $changed
            ? get_string('passwordlastchanged', 'local_profilefields',
                userdate($changed, get_string('strftimedate')))
            : get_string('passwordlastchangedunknown', 'local_profilefields');

        $authname = '';
        try {
            $authname = get_string('pluginname', 'auth_' . $user->auth);
        } catch (\Throwable $e) {
            $authname = (string) $user->auth;
        }

        return [
            'userid' => (int) $user->id,
            'auth' => (string) $user->auth,
            'authname' => $authname,
            'canchangepassword' => $canchange,
            // 0 means "this site does not record it", not "never changed":
            // core only writes the password history while $CFG->passwordreuselimit
            // is above zero. Show `lastchangedtext`, which says the difference.
            'passwordlastchanged' => $changed,
            'lastchangedtext' => $canchange
                ? $lastchangedtext
                : get_string('passwordexternal', 'local_profilefields'),
            'changenote' => get_string('passwordchangehelp', 'local_profilefields'),
            'passwordpolicy' => self::password_policy(),
            'warnings' => [],
        ];
    }

    /**
     * The site's password policy, as a sentence to show under the new-password box.
     *
     * @return string plain text, '' when the site enforces no policy
     */
    protected static function password_policy(): string {
        global $CFG;

        if (empty($CFG->passwordpolicy)) {
            return '';
        }

        return trim(html_to_text(print_password_policy(), 0, false));
    }

    /**
     * What the delete pane (WF-5.3) has to say before anything is destroyed.
     *
     * @param stdClass $user the account being shown
     * @return array the structure returned by local_profilefields_get_delete_account_info
     */
    public static function deletion_info(stdClass $user): array {
        $allowed = accountdeletion::allowed($user);

        // The password box is AC-4.5.7's, and an account that signs in through
        // Google has no password here to check - the same wall the web form puts
        // in front of it. Said plainly rather than left to a failed attempt.
        $canverify = account::can_verify_password($user);

        return [
            'allowed' => $allowed && $canverify,
            'refusedreason' => ($allowed && $canverify)
                ? ''
                : ($allowed
                    ? get_string('passwordexternal', 'local_profilefields')
                    : get_string('deleteaccountrefused', 'local_profilefields')),
            'title' => get_string('deleteaccount', 'local_profilefields'),
            'cannotbeundone' => get_string('deleteaccountcannotbeundone', 'local_profilefields'),
            'warning' => get_string('deleteaccountwarning', 'local_profilefields'),
            'retained' => get_string('deleteaccountretained', 'local_profilefields'),
            'passwordlabel' => get_string('deleteaccountconfirm', 'local_profilefields'),
            // Localised, so an Arabic interface asks for the Arabic word - a
            // learner should not have to type a language they do not read to
            // leave. Compare case-insensitively; the server does.
            'confirmword' => get_string('deleteaccountword', 'local_profilefields'),
            'confirmlabel' => get_string('deleteaccounttype', 'local_profilefields',
                get_string('deleteaccountword', 'local_profilefields')),
            'warnings' => [],
        ];
    }

    /**
     * Check what the delete form checks.
     *
     * The same two gates, in the same order, with the same messages
     * ({@see form\deleteaccount_form::validation()}).
     *
     * @param stdClass $user the account being deleted
     * @param string $password the account password, as typed
     * @param string $confirmword the confirmation word, as typed
     * @return array<string,string> field name => message; empty when it may proceed
     */
    public static function deletion_errors(stdClass $user, string $password, string $confirmword): array {
        $errors = [];

        if (!account::verify_password((int) $user->id, $password)) {
            $errors['password'] = get_string('deleteaccountwrongpassword', 'local_profilefields');
        }

        $expected = get_string('deleteaccountword', 'local_profilefields');
        if (core_text::strtolower(trim($confirmword)) !== core_text::strtolower($expected)) {
            $errors['confirmword'] = get_string('deleteaccountwrongword', 'local_profilefields', $expected);
        }

        return $errors;
    }

    /**
     * Delete the account, and say goodbye to the address it had.
     *
     * The sequence `/local/profilefields/deleteaccount.php` runs once the form is
     * satisfied, kept here so the web page and the app cannot end up deleting an
     * account two subtly different ways. The caller is responsible for whatever
     * comes after - ending its own session, or letting the token die.
     *
     * @param stdClass $user the account to delete
     * @return bool whether the account was deleted
     */
    public static function delete(stdClass $user): bool {
        // Take a copy before the record is scrambled, so the goodbye can still be
        // addressed and sent - after this call the address on the row is a hash.
        $account = clone $user;

        if (!accountdeletion::execute($account)) {
            return false;
        }

        // Sent to the address as it was, not as it now is. Unconditional, like the
        // other security mail: somebody whose account was deleted by an attacker
        // who had their session needs to hear about it, and AC-4.5.5 puts these
        // beyond the reach of the preference screen anyway.
        try {
            email_to_user(
                $account,
                \core_user::get_support_user(),
                get_string('deleteaccountdonesubject', 'local_profilefields'),
                get_string('deleteaccountdonebody', 'local_profilefields', (object) [
                    'firstname' => $account->firstname,
                    'sitename' => format_string(get_site()->fullname),
                ])
            );
        } catch (\Throwable $e) {
            debugging('local_profilefields: could not send the account-deletion notice: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return true;
    }

    /**
     * Whether the administrator has made a core field required.
     *
     * @param string $name core field name
     * @return bool
     */
    protected static function is_required(string $name): bool {
        return !empty(manager::get_config()[$name]['required']);
    }

    /**
     * Core's country list, in the option shape the rest of this API uses.
     *
     * The "Choose a country" placeholder is deliberately absent: it is a prompt,
     * not a value, and every other option list in this API leaves it out too.
     *
     * @return array[] value/label/dialcode triples
     */
    protected static function country_options(): array {
        $hasdialcodes = class_exists('\profilefield_phone\dialcodes');
        $out = [];

        foreach (get_string_manager()->get_list_of_countries() as $iso => $name) {
            $dial = $hasdialcodes ? \profilefield_phone\dialcodes::code($iso) : '';
            $out[] = [
                'value' => (string) $iso,
                'label' => (string) $name,
                'dialcode' => $dial === '' ? '' : '+' . $dial,
            ];
        }

        return $out;
    }
}
