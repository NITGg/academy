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

use context_system;
use core_text;
use MoodleQuickForm;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * The sign-up flow, expressed for a client that cannot render the web form.
 *
 * The academy's sign-up page is no longer the stock one: the username box is gone
 * (derived from the email), "Email (again)" is off, City and Country are hidden and
 * filled server-side, a phone field feeds the country, an inline consent checkbox
 * is required, and the whole list is relabelled and reordered from the management
 * page. `auth_email_signup_user` knows none of that - it still demands a username,
 * ignores the consent checkbox and leaves the country empty - so an app calling it
 * registers users through a form this site no longer has.
 *
 * This class is the one place that answers "what does sign-up look like right now,
 * and what happens when it is submitted", for the two web services in
 * `local_profilefields\external`.
 *
 * `describe()` does not re-implement the layout: it builds the real
 * `login_signup_form` - the same object the browser gets, with every plugin
 * callback already applied - and reads the elements back out. Anything an admin
 * changes on the management page therefore reaches the app on the next call, with
 * no second implementation to keep in step.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signup_api {

    /**
     * Fail unless email self-registration is the site's registration method.
     *
     * Mirrors `auth_email_external::check_signup_enabled()`, so a client sees the
     * same error it does today.
     *
     * @return void
     */
    public static function require_signup_enabled(): void {
        global $CFG;

        if (empty($CFG->registerauth) || $CFG->registerauth !== 'email') {
            throw new \moodle_exception('registrationdisabled', 'error');
        }
    }

    /**
     * Load the libraries the sign-up functions live in.
     *
     * A web-service request boots none of these: `signup_validate_data()`,
     * `signup_setup_new_user()` and `signup_captcha_enabled()` are in authlib, the
     * plugin callbacks in login/lib.php, the profile-field helpers in
     * user/profile/lib.php, and `useredit_get_required_name_fields()` - which
     * signup_setup_new_user() calls - in user/editlib.php. The same four files
     * auth_email's external class requires for the same reason.
     *
     * @return void
     */
    public static function require_libs(): void {
        global $CFG;

        require_once($CFG->libdir . '/authlib.php');
        require_once($CFG->dirroot . '/login/lib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/user/editlib.php');
    }

    /**
     * Build the live sign-up form, with every plugin callback applied.
     *
     * @return MoodleQuickForm the form the browser would be given
     */
    protected static function build_form(): MoodleQuickForm {
        global $CFG, $PAGE;

        $PAGE->set_context(context_system::instance());

        // login/signup_form.php ends with a *relative* `require_once('lib.php')`,
        // which PHP resolves against the include path (the working directory) before
        // the including file's own folder. A web-service request runs with a different
        // working directory from /login, and some of those folders have a lib.php of
        // their own - so point the working directory at /login for the one statement
        // that needs it, then put it back.
        self::require_libs();
        $cwd = getcwd();
        chdir($CFG->dirroot . '/login');
        try {
            require_once($CFG->dirroot . '/login/signup_form.php');
        } finally {
            if ($cwd !== false) {
                chdir($cwd);
            }
        }

        // Same arguments login/signup.php uses, so the form is built exactly as it is
        // for a visitor. signup_form_probe only exposes the QuickForm underneath.
        $form = new signup_form_probe(null, null, 'post', '', ['autocomplete' => 'on']);

        return $form->get_quickform();
    }

    /**
     * Everything a client needs to draw the current sign-up form.
     *
     * @return array the structure returned by local_profilefields_get_signup_form
     */
    public static function describe(): array {
        global $CFG;

        self::require_signup_enabled();

        $mform = self::build_form();

        $result = [
            'usernamefromemail'     => manager::username_from_email(),
            'usernamesource'        => manager::username_source(),
            'countryfromphone'      => manager::country_from_phone(),
            'ipmatchphone'          => manager::ip_match_phone(),
            'extendedusernamechars' => !empty($CFG->extendedusernamechars),
            'defaultcity'           => (string) ($CFG->defaultcity ?? ''),
            'defaultcountry'        => (string) ($CFG->country ?? ''),
            'passwordpolicy'        => empty($CFG->passwordpolicy) ? '' : print_password_policy(),
            'consent'               => [
                'required'  => manager::consent_enabled(),
                'label'     => manager::consent_enabled() ? policies::consent_label() : '',
                'documents' => [],
            ],
            'fields'                => self::read_fields($mform),
            'warnings'              => [],
        ];

        foreach (policies::signup_document_records() as $doc) {
            // The ids are what a client without a browser view needs: it fetches the
            // text with local_profilefields_get_policy_documents instead of opening
            // the URL.
            $result['consent']['documents'][] = [
                'name'      => $doc->name,
                'url'       => $doc->url,
                'policyid'  => $doc->policyid,
                'versionid' => $doc->versionid,
            ];
        }

        if (signup_captcha_enabled()) {
            // reCAPTCHA v2: the client renders the widget from the public key and
            // sends the response back to local_profilefields_signup_user.
            $result['recaptchapublickey'] = (string) $CFG->recaptchapublickey;
        }

        return $result;
    }

    /**
     * Turn the form's elements into a flat, client-friendly field list.
     *
     * Hidden inputs are the fields the site fills in itself (username, email2, and
     * City/Country when they are switched off), so they are left out - a client must
     * not ask for them. Buttons, headers and the password-policy blurb are not
     * fields either; the blurb is returned once, at the top level.
     *
     * @param MoodleQuickForm $mform the sign-up form
     * @return array[] one entry per field the user actually fills in
     */
    protected static function read_fields(MoodleQuickForm $mform): array {
        $customfields = self::custom_field_records();
        $skiptypes = ['hidden', 'submit', 'button', 'reset', 'header', 'static', 'recaptcha', 'html'];
        // The buttons (a group), and the site-policy checkbox: acceptance is recorded
        // from the `consent` parameter instead, exactly as auth_email_signup_user
        // records it from a site policy being defined at all.
        $skipnames = ['buttonar', 'submitbutton', 'cancel', 'policyagreed', 'sesskey'];

        $fields = [];
        $seen = [];
        foreach ($mform->_elements as $element) {
            $name = (string) $element->getName();
            $type = (string) $element->getType();

            if ($name === '' || in_array($name, $skipnames, true) || in_array($type, $skiptypes, true)) {
                continue;
            }
            // The consent checkbox is an advcheckbox, which QuickForm renders as a
            // hidden "off" input plus the box itself, under one name.
            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            $iscustom = strpos($name, signup::CUSTOM_PREFIX) === 0;
            $shortname = $iscustom ? substr($name, strlen(signup::CUSTOM_PREFIX)) : $name;
            $record = $iscustom ? ($customfields[$shortname] ?? null) : null;

            $field = [
                'name'         => $name,
                'shortname'    => $shortname,
                'type'         => self::field_type($element, $record),
                'label'        => self::field_label($element),
                'description'  => $record ? (string) $record->description : '',
                'required'     => self::is_required($mform, $name, $record),
                'iscustom'     => $iscustom,
                'defaultvalue' => $record ? (string) $record->defaultdata : '',
                'options'      => self::field_options($element),
            ];

            if ($name === signup::CONSENT) {
                // Not a profile value: a condition of submitting the form. The client
                // sends it back as the `consent` parameter, not as a custom field.
                $field['name'] = 'consent';
                $field['shortname'] = 'consent';
                $field['type'] = 'consent';
                $field['required'] = manager::consent_enabled();
                $field['label'] = policies::consent_label();
            }

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * The custom profile fields shown on sign-up, keyed by shortname.
     *
     * @return stdClass[] user_info_field records
     */
    protected static function custom_field_records(): array {
        global $DB;

        $records = [];
        foreach ($DB->get_records('user_info_field', ['signup' => 1]) as $record) {
            $records[$record->shortname] = $record;
        }
        return $records;
    }

    /**
     * The text shown beside one field.
     *
     * A checkbox carries its wording as the element's text rather than as a label
     * (that is what `addElement('advcheckbox', $name, '', $text)` means), so fall
     * back to that before giving up on an empty label.
     *
     * @param object $element a QuickForm element
     * @return string
     */
    protected static function field_label($element): string {
        $label = (string) $element->getLabel();
        if ($label === '' && method_exists($element, 'getText')) {
            $label = (string) $element->getText();
        }
        return $label;
    }

    /**
     * The client-facing type name for one form element.
     *
     * @param object $element a QuickForm element
     * @param stdClass|null $record the custom field record, when there is one
     * @return string
     */
    protected static function field_type($element, ?stdClass $record): string {
        // A custom field's own datatype is more precise than the element it renders
        // as: "phone" and "datetime" both arrive as a group, for instance.
        if ($record) {
            return (string) $record->datatype;
        }

        switch ((string) $element->getType()) {
            case 'password':
                return 'password';
            case 'select':
            case 'autocomplete':
                return 'select';
            case 'checkbox':
            case 'advcheckbox':
                return 'checkbox';
            case 'textarea':
            case 'editor':
                return 'textarea';
            case 'date_selector':
            case 'date_time_selector':
                return 'datetime';
            case 'group':
                return 'group';
            default:
                return (string) $element->getName() === 'email' ? 'email' : 'text';
        }
    }

    /**
     * The choices for a field the user picks from, if it has any.
     *
     * Covers a plain select (Country, a menu profile field) and the country select
     * nested inside the phone group - the one a client most needs, since its values
     * are ISO codes and its labels carry the dialling code.
     *
     * @param object $element a QuickForm element
     * @return array[] [['value' => .., 'label' => .., 'dialcode' => ..], ..]
     */
    protected static function field_options($element): array {
        $select = null;

        if (in_array((string) $element->getType(), ['select', 'autocomplete'], true)) {
            $select = $element;
        } else if ((string) $element->getType() === 'group') {
            foreach ($element->getElements() as $sub) {
                if ((string) $sub->getType() === 'select') {
                    $select = $sub;
                    break;
                }
            }
        }
        if ($select === null || !isset($select->_options)) {
            return [];
        }

        $hasdialcodes = class_exists('\profilefield_phone\dialcodes');

        $options = [];
        foreach ($select->_options as $option) {
            $value = (string) ($option['attr']['value'] ?? '');
            if ($value === '') {
                // The "Choose..." placeholder is a prompt, not a value.
                continue;
            }
            $dial = $hasdialcodes ? \profilefield_phone\dialcodes::code($value) : '';
            $options[] = [
                'value'    => $value,
                'label'    => (string) $option['text'],
                'dialcode' => $dial === '' ? '' : '+' . $dial,
            ];
        }

        return $options;
    }

    /**
     * Whether a field must be filled in.
     *
     * Three sources, because the form carries the answer in three ways: core adds a
     * "required" rule, this plugin adds or clears one from its own config, and a
     * custom field can enforce requiredness server-side with no rule at all
     * (profilefield_phone does exactly that).
     *
     * @param MoodleQuickForm $mform the sign-up form
     * @param string $name element name
     * @param stdClass|null $record the custom field record, when there is one
     * @return bool
     */
    protected static function is_required(MoodleQuickForm $mform, string $name, ?stdClass $record): bool {
        if ($record && !empty($record->required)) {
            return true;
        }
        if (in_array($name, $mform->_required, true)) {
            return true;
        }
        foreach ($mform->_rules[$name] ?? [] as $rule) {
            if (($rule['type'] ?? '') === 'required') {
                return true;
            }
        }
        return false;
    }

    /**
     * Apply the flow's server-side rules to what a client submitted.
     *
     * This is the API's half of what `signup::apply()` does to the web form: the
     * fields the browser never shows are filled in here instead of by a hidden
     * input, so both paths create the same user from the same answers.
     *
     * @param array $input keys: email, password, firstname, lastname, username, city,
     *                     country, consent, plus `profile_field_*` values
     * @return array the data array `signup_validate_data()` expects
     */
    public static function prepare_data(array $input): array {
        global $CFG;

        $data = $input;

        // 1. Username. Derived from the email exactly as the form's hidden input is,
        //    unless the site still asks for one and the client sent it.
        if (manager::username_from_email() || trim((string) ($data['username'] ?? '')) === '') {
            $data['username'] = manager::derive_username((string) ($data['email'] ?? ''));
        }
        $data['username'] = core_text::strtolower(trim((string) $data['username']));

        // 2. "Email (again)" catches typos, and is switched off here - in which case
        //    it mirrors the address, exactly as the hidden input does. A client that
        //    did show the box (because an admin turned it back on) sends it, and then
        //    the two are compared as they are on the web.
        if (trim((string) ($data['email2'] ?? '')) === '') {
            $data['email2'] = $data['email'] ?? '';
        }

        // 3. City and Country: whatever the client leaves empty falls back to the site
        //    default, which is the value the switched-off boxes submit.
        if (trim((string) ($data['city'] ?? '')) === '') {
            $data['city'] = (string) ($CFG->defaultcity ?? '');
        }
        if (trim((string) ($data['country'] ?? '')) === '') {
            $data['country'] = (string) ($CFG->country ?? '');
        }

        // 4. Country follows the phone field when the admin asked for that - the
        //    server-side twin of the sync script the web form injects.
        if (manager::country_from_phone()) {
            $iso = self::phone_country($data);
            if ($iso !== '') {
                $data['country'] = $iso;
            }
        }

        // 5. The consent checkbox, under the name the validation callback reads.
        $data[signup::CONSENT] = empty($data['consent']) ? 0 : 1;
        unset($data['consent']);

        // 6. A site policy handler records acceptance from that checkbox; this matches
        //    what auth_email_signup_user does, for the same reason.
        $manager = new \core_privacy\local\sitepolicy\manager();
        if ($manager->is_defined()) {
            $data['policyagreed'] = 1;
        }

        return $data;
    }

    /**
     * The country code chosen in the sign-up phone field, if any.
     *
     * @param array $data prepared sign-up data
     * @return string ISO alpha-2, or '' when there is no usable phone value
     */
    protected static function phone_country(array $data): string {
        global $DB;

        $field = $DB->get_record_select('user_info_field', 'datatype = ? AND signup = 1',
            ['phone'], 'shortname', IGNORE_MULTIPLE);
        if (!$field) {
            return '';
        }

        $value = $data[signup::CUSTOM_PREFIX . $field->shortname] ?? null;
        if (!is_array($value) || empty($value['country'])) {
            return '';
        }

        $iso = core_text::strtoupper(trim((string) $value['country']));
        $countries = get_string_manager()->get_list_of_countries(true);

        return isset($countries[$iso]) ? $iso : '';
    }

    /**
     * Run every check the web form runs, in the same order.
     *
     * `signup_validate_data()` covers the core boxes and the custom profile fields
     * (including profilefield_phone's location check), and
     * `core_login_validate_extend_signup_form()` covers the plugin callbacks - ours
     * being the consent checkbox. The web-service path skipped the second one
     * entirely, which is why consent was never enforced for the app.
     *
     * @param array $data prepared sign-up data
     * @param string $recaptcharesponse the reCAPTCHA response, when one is required
     * @return array element name => error message
     */
    public static function validate(array $data, string $recaptcharesponse = ''): array {
        global $CFG;

        self::require_libs();

        if ((string) ($data['username'] ?? '') === '') {
            // derive_username() only comes back empty for an unusable address, so the
            // honest error is about the email rather than about a box the client never
            // saw.
            return ['email' => get_string('invalidemail')];
        }

        $errors = signup_validate_data($data, []);
        $errors = array_merge($errors, core_login_validate_extend_signup_form($data));

        if (signup_captcha_enabled()) {
            require_once($CFG->libdir . '/recaptchalib_v2.php');
            $response = recaptcha_check_response(RECAPTCHA_VERIFY_URL, $CFG->recaptchaprivatekey,
                getremoteaddr(), $recaptcharesponse);
            if (empty($response['isvalid'])) {
                $errors['recaptcharesponse'] = $response['error'];
            }
        }

        return $errors;
    }

    /**
     * Create the account and send the confirmation email.
     *
     * @param array $data validated sign-up data
     * @param string $redirect local URL to send the user to after confirmation
     * @return stdClass the new user record
     */
    public static function create_user(array $data, string $redirect = ''): stdClass {
        self::require_libs();

        // Not a user field: it drives the validation callback and nothing else.
        unset($data[signup::CONSENT]);

        $user = signup_setup_new_user((object) $data);

        $confirmationurl = null;
        if ($redirect !== '') {
            $target = new \moodle_url($redirect);
            $confirmationurl = new \moodle_url('/login/confirm.php', ['redirect' => $target->out()]);
        }

        get_auth_plugin('email')->user_signup_with_confirmation($user, false, $confirmationurl);

        return $user;
    }
}
