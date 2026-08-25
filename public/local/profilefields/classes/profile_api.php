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

use context;
use context_course;
use context_system;
use context_user;
use core_date;
use core_tag_tag;
use core_user;
use moodle_url;
use MoodleQuickForm;
use stdClass;
use user_picture;

defined('MOODLE_INTERNAL') || die();

/**
 * The profile flow - view and edit - expressed for a client that cannot render
 * the web pages.
 *
 * The site's profile pages are `/user/profile.php` (view) and `/user/edit.php`
 * (edit). Moodle ships no web service for either as the owner of the account
 * sees them: `core_user_get_users_by_field` returns a fixed handful of columns
 * and only the custom fields the *caller* may see, and `core_user_update_users`
 * demands `moodle/user:update`, which an ordinary student does not have - so an
 * app cannot use it to let someone edit their own profile at all. That is why
 * the app has had to open `/user/edit.php` in a WebView.
 *
 * This class is the one place that answers "what does the profile look like
 * right now, and what happens when the edit form is submitted", for the three
 * web services in `local_profilefields\external`.
 *
 * As with sign-up, `describe()` does not re-implement the layout: it builds the
 * real `user_edit_form` - the same object the browser gets, with every custom
 * profile field, every auth-plugin field lock and every admin setting already
 * applied - and reads the elements back out. Anything an admin changes on
 * *Sign-up and profile field layout* (or on the auth plugin, or on a profile
 * field) therefore reaches the app on the next call, with no second
 * implementation to keep in step.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_api {

    /** @var string The element core uses for the description; this API calls the field "description". */
    const DESCRIPTION_ELEMENT = 'description_editor';

    /** @var string[] Element types that are not fields the user fills in. */
    const SKIP_TYPES = ['hidden', 'submit', 'button', 'reset', 'html', 'recaptcha', 'warning', 'static'];

    /**
     * Elements a client must not be shown as editable fields.
     *
     * The picture ones are deliberate: a photo is uploaded with
     * `core_user_update_picture`, which already exists and already handles the
     * draft file area, so this API reports the current picture and stays out of
     * the way. The rest are the form's plumbing.
     *
     * @var string[]
     */
    const SKIP_NAMES = [
        'id', 'course', 'sesskey', 'buttonar', 'submitbutton', 'cancel',
        'currentpicture', 'imagefile', 'deletepicture', 'imagealt',
        'emailpending', 'forcedtimezone', 'userpicturewarning',
    ];

    /**
     * Load the libraries the profile pages live in.
     *
     * A web-service request boots none of these: the form class is in
     * user/edit_form.php, the shared definition and the save helpers in
     * user/editlib.php, the custom-field helpers in user/profile/lib.php, and
     * `user_update_user()` in user/lib.php.
     *
     * @return void
     */
    public static function require_libs(): void {
        global $CFG;

        require_once($CFG->libdir . '/gdlib.php');
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/user/editlib.php');
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/user/edit_form.php');
    }

    /**
     * The account a call is about, defaulting to the caller's own.
     *
     * @param int $userid 0 for the calling user
     * @return stdClass the user record
     */
    public static function get_user(int $userid = 0): stdClass {
        global $DB, $USER;

        $userid = $userid ?: (int) $USER->id;
        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user || $user->deleted) {
            throw new \moodle_exception('invaliduserid', 'error');
        }

        return $user;
    }

    /**
     * Fail unless the caller may see this profile.
     *
     * The same question `/user/profile.php` asks, through the same function, so
     * a profile hidden on the web is hidden here too.
     *
     * @param stdClass $user the profile owner
     * @return void
     */
    public static function require_can_view(stdClass $user): void {
        self::require_libs();

        if (!user_can_view_profile($user, null, context_user::instance($user->id))) {
            throw new \moodle_exception('usernotavailable', 'error');
        }
    }

    /**
     * Fail unless the caller may edit this profile.
     *
     * Every gate `/user/edit.php` puts in front of the form, in the same order
     * and with the same error strings: no guests, no remote users, an auth
     * plugin that allows editing at all, and then the capability - own profile
     * or someone else's.
     *
     * @param stdClass $user the profile owner
     * @return void
     */
    public static function require_can_edit(stdClass $user): void {
        global $USER;

        if (isguestuser() || isguestuser($user)) {
            throw new \moodle_exception('guestnoeditprofile');
        }

        if (is_mnet_remote_user($user)) {
            throw new \moodle_exception('usernotfullysetup', 'mnet');
        }

        $userauth = get_auth_plugin($user->auth);
        if (!$userauth->can_edit_profile() || $userauth->edit_profile_url()) {
            // The account is edited somewhere else entirely - an external
            // directory, or the auth plugin's own page.
            throw new \moodle_exception('noprofileedit', 'auth');
        }

        if ($user->id == $USER->id) {
            // Editing own profile - a capability check, never require_login().
            if (!has_capability('moodle/user:editownprofile', context_system::instance())) {
                throw new \moodle_exception('cannotedityourprofile');
            }
            return;
        }

        require_capability('moodle/user:editprofile', context_user::instance($user->id));
        if (is_siteadmin($user) && !is_siteadmin($USER)) {
            throw new \moodle_exception('useradmineditadmin');
        }
    }

    /**
     * Whether the caller may edit this profile, as a plain answer.
     *
     * @param stdClass $user the profile owner
     * @return bool
     */
    public static function can_edit(stdClass $user): bool {
        try {
            self::require_can_edit($user);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The user record the edit form is built from.
     *
     * `/user/edit.php` does exactly this before constructing the form: tags,
     * preferences and custom field values are not columns on `user`, so they
     * have to be folded in by hand or the form opens empty.
     *
     * @param stdClass $user the stored user record
     * @return stdClass a copy, with the form's extra properties filled in
     */
    protected static function prepare_user(stdClass $user): stdClass {
        self::require_libs();

        $data = clone $user;
        $data->interests = core_tag_tag::get_item_tags_array('core', 'user', $data->id);
        useredit_load_preferences($data);
        profile_load_data($data);

        // The form asks the file manager for a draft area; nothing here uploads a
        // picture (that is core_user_update_picture's job), so an empty one is
        // enough to keep the element happy.
        $data->imagefile = 0;

        return $data;
    }

    /**
     * Build the live profile edit form, finalised.
     *
     * @param stdClass $user the profile owner
     * @return MoodleQuickForm the form the browser would be given
     */
    protected static function build_form(stdClass $user): MoodleQuickForm {
        global $CFG, $PAGE;

        self::require_libs();

        $context = context_user::instance($user->id);
        $PAGE->set_context($context);

        $editoroptions = [
            'maxfiles'   => EDITOR_UNLIMITED_FILES,
            'maxbytes'   => $CFG->maxbytes,
            'trusttext'  => false,
            'forcehttps' => false,
            'context'    => $context,
        ];
        $filemanageroptions = [
            'maxbytes'       => $CFG->maxbytes,
            'subdirs'        => 0,
            'maxfiles'       => 1,
            'accepted_types' => 'optimised_image',
        ];

        $form = new user_edit_form_probe(new moodle_url('/user/edit.php', ['id' => $user->id]), [
            'editoroptions'      => $editoroptions,
            'filemanageroptions' => $filemanageroptions,
            'user'               => self::prepare_user($user),
        ]);
        // Without this the auth-plugin locks have not been applied yet, and every
        // field would be reported as editable.
        $form->finalise();

        return $form->get_quickform();
    }

    /**
     * Everything a client needs to draw the current profile edit form.
     *
     * @param stdClass $user the profile owner
     * @return array the structure returned by local_profilefields_get_profile_form
     */
    public static function describe(stdClass $user): array {
        self::require_libs();

        $mform = self::build_form($user);
        $customfields = self::custom_fields($user->id);

        $sectionorder = ['moodle'];
        $sectionlabels = ['moodle' => get_string('general')];
        $fields = [];
        $seen = [];
        $section = 'moodle';
        $sectionlabel = $sectionlabels['moodle'];

        foreach ($mform->_elements as $element) {
            $name = (string) $element->getName();
            $type = (string) $element->getType();

            if ($type === 'header') {
                $section = $name;
                $sectionlabel = self::header_label($element);
                if (!isset($sectionlabels[$section])) {
                    $sectionorder[] = $section;
                }
                $sectionlabels[$section] = $sectionlabel;
                continue;
            }

            if ($name === '' || strpos($name, '_qf__') === 0
                    || in_array($type, self::SKIP_TYPES, true)
                    || in_array($name, self::SKIP_NAMES, true)) {
                continue;
            }
            // An advcheckbox renders as a hidden "off" input plus the box itself,
            // both under one name.
            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;

            $iscustom = strpos($name, signup::CUSTOM_PREFIX) === 0;
            $field = $iscustom ? ($customfields[$name] ?? null) : null;
            if ($iscustom && $field === null) {
                continue;
            }

            // The description is the one element whose form name is not its field
            // name; a client sends "description", as it reads it back everywhere else.
            $apiname = $name === self::DESCRIPTION_ELEMENT ? 'description' : $name;

            $fields[] = [
                'name'         => $apiname,
                'shortname'    => $iscustom ? substr($name, strlen(signup::CUSTOM_PREFIX)) : $apiname,
                'type'         => self::field_type($element, $field, $apiname),
                'label'        => signup_api::field_label($element),
                'description'  => $field ? (string) $field->field->description : '',
                'required'     => signup_api::is_required($mform, $name, $field ? $field->field : null),
                // A file field's value lives in a draft area, which a web service
                // parameter cannot carry; report it read-only rather than let a
                // client send a string that would destroy the stored file.
                'locked'       => (bool) $element->isFrozen()
                    || ($field && (string) $field->field->datatype === 'file'),
                'iscustom'     => $iscustom,
                'section'      => $section,
                'sectionlabel' => $sectionlabel,
                'value'        => self::current_value($user, $apiname, $field),
                'format'       => $apiname === 'description'
                    ? (int) ($user->descriptionformat ?? FORMAT_HTML) : 0,
                'options'      => signup_api::field_options($element),
            ];
        }

        // Only sections that ended up with a field in them: the picture section, for
        // one, has no fields left once the upload controls are removed, and a client
        // should not draw an empty heading for it.
        $used = array_flip(array_column($fields, 'section'));
        $sections = [];
        foreach ($sectionorder as $name) {
            if (isset($used[$name])) {
                $sections[] = ['name' => $name, 'label' => $sectionlabels[$name]];
            }
        }

        return [
            'userid'               => (int) $user->id,
            'emailchangepending'   => (string) (get_user_preferences('newemail', '', $user->id) ?: ''),
            'profileimageurl'      => self::picture_url($user, 200),
            'profileimageurlsmall' => self::picture_url($user, 100),
            'sections'             => $sections,
            'fields'               => $fields,
            'warnings'             => [],
        ];
    }

    /**
     * The text of a section heading.
     *
     * A header carries its wording in `_text` rather than as a label, and
     * `HTML_QuickForm_static` offers no getter for it - hence the direct read.
     *
     * @param object $element a QuickForm header element
     * @return string
     */
    protected static function header_label($element): string {
        $label = signup_api::field_label($element);
        if ($label === '' && isset($element->_text)) {
            $label = (string) $element->_text;
        }
        return $label;
    }

    /**
     * The profile as `/user/profile.php` shows it.
     *
     * The category/node list is the real profile page: `myprofile\manager` is
     * what the page itself renders, so whatever a plugin contributes to the
     * profile - and whatever core hides from this viewer - is reflected here
     * without a second implementation.
     *
     * @param stdClass $user the profile owner
     * @return array the structure returned by local_profilefields_get_profile
     */
    public static function view(stdClass $user): array {
        global $PAGE, $USER;

        self::require_libs();

        $context = context_user::instance($user->id);
        $PAGE->set_context($context);

        $iscurrentuser = ($user->id == $USER->id);
        $countries = get_string_manager()->get_list_of_countries(true);

        $result = [
            'id'                   => (int) $user->id,
            'fullname'             => fullname($user),
            'firstname'            => (string) $user->firstname,
            'lastname'             => (string) $user->lastname,
            'username'             => $iscurrentuser ? (string) $user->username : '',
            'email'                => self::visible_email($user, $iscurrentuser),
            'city'                 => (string) $user->city,
            'country'              => (string) $user->country,
            'countryname'          => (string) ($countries[$user->country] ?? ''),
            'timezone'             => (string) core_date::get_user_timezone($user),
            'lang'                 => (string) $user->lang,
            'description'          => self::description($user, $context),
            'descriptionformat'    => (int) ($user->descriptionformat ?? FORMAT_HTML),
            'interests'            => implode(', ', core_tag_tag::get_item_tags_array('core', 'user', $user->id)),
            'profileimageurl'      => self::picture_url($user, 200),
            'profileimageurlsmall' => self::picture_url($user, 100),
            'firstaccess'          => (int) $user->firstaccess,
            'lastaccess'           => (int) $user->lastaccess,
            'canedit'              => self::can_edit($user),
            'editurl'              => (new moodle_url('/user/edit.php',
                ['id' => $user->id, 'returnto' => 'profile']))->out(false),
            'customfields'         => [],
            'categories'           => [],
            'warnings'             => [],
        ];

        foreach (self::custom_fields($user->id) as $field) {
            // is_visible() is the profile page's own test: a field only this user's
            // teachers may see must not leak to anyone else.
            if (!$field->is_visible($context)) {
                continue;
            }
            $result['customfields'][] = [
                'shortname'    => (string) $field->field->shortname,
                'name'         => format_string($field->field->name),
                'datatype'     => (string) $field->field->datatype,
                'value'        => (string) ($field->data ?? ''),
                'displayvalue' => (string) $field->display_data(),
                'categoryname' => format_string((string) $field->get_category_name()),
            ];
        }

        $tree = \core_user\output\myprofile\manager::build_tree($user, $iscurrentuser);
        foreach ($tree->categories as $category) {
            $nodes = [];
            foreach ($category->nodes as $node) {
                $url = $node->url;
                $nodes[] = [
                    'name'    => (string) $node->name,
                    'title'   => (string) $node->title,
                    'content' => (string) $node->content,
                    'url'     => $url ? $url->out(false) : '',
                    'classes' => (string) $node->classes,
                ];
            }
            $result['categories'][] = [
                'name'  => (string) $category->name,
                'title' => (string) $category->title,
                'nodes' => $nodes,
            ];
        }

        return $result;
    }

    /**
     * Apply a submitted set of values on top of the stored account.
     *
     * Only the fields a client actually sent are touched, so an app may save one
     * screen of a multi-step profile without blanking the rest. Anything the form
     * does not offer, and anything the auth plugin has locked, is dropped rather
     * than rejected - the same thing `setConstant()` does to a tampered POST on
     * the web.
     *
     * @param stdClass $user the stored user record
     * @param array $described the output of describe()
     * @param array $submitted name => value, as sent by the client
     * @param int $descriptionformat FORMAT_* for the description, when one was sent
     * @return stdClass the object the validators and the save step work on
     */
    public static function prepare_data(stdClass $user, array $described, array $submitted,
            int $descriptionformat = FORMAT_HTML): stdClass {
        self::require_libs();

        $editable = [];
        foreach ($described['fields'] as $field) {
            if (empty($field['locked'])) {
                $editable[$field['name']] = $field;
            }
        }

        // Start from the account as it stands, so a partial submission cannot blank
        // a field the client never saw.
        $usernew = self::prepare_user($user);
        $usernew->id = (int) $user->id;

        foreach ($submitted as $name => $value) {
            if (!isset($editable[$name])) {
                continue;
            }
            $field = $editable[$name];

            if (!empty($field['iscustom'])) {
                $usernew->{signup::CUSTOM_PREFIX . $field['shortname']} =
                    self::decode_custom_value($field['type'], (string) $value);
                continue;
            }

            switch ($name) {
                case 'description':
                    $usernew->description = (string) $value;
                    $usernew->descriptionformat = $descriptionformat;
                    break;
                case 'interests':
                    $usernew->interests = array_values(array_filter(
                        array_map('trim', explode(',', (string) $value)), 'strlen'));
                    break;
                default:
                    $usernew->{$name} = $value;
            }
        }

        // The editor element is not a user column; it only exists on the web form.
        unset($usernew->{self::DESCRIPTION_ELEMENT});

        return $usernew;
    }

    /**
     * Run every check the profile edit form runs, in the same order.
     *
     * `user_edit_form::validation()` is the source: the e-mail rules (format,
     * uniqueness, bounce threshold, allowed-domain list), the description length
     * limit, and then each custom field's own validator through
     * `profile_validation()`. Requiredness is added on top because on the web it
     * is carried by QuickForm rules, which only run against a real submission.
     *
     * @param stdClass $user the stored user record
     * @param stdClass $usernew the prepared new values
     * @param array $described the output of describe()
     * @param array $submitted name => value, as sent by the client
     * @return array field name => error message
     */
    public static function validate(stdClass $user, stdClass $usernew, array $described, array $submitted): array {
        global $CFG, $DB;

        self::require_libs();

        $errors = [];

        // 1. Requiredness, but only for a field the client actually sent: leaving
        //    one out means "do not change it", which is not an error.
        foreach ($described['fields'] as $field) {
            if (empty($field['required']) || !empty($field['locked'])
                    || !array_key_exists($field['name'], $submitted)) {
                continue;
            }
            if (trim((string) $submitted[$field['name']]) === '') {
                $errors[$field['name']] = get_string('required');
            }
        }

        // 2. E-mail, exactly as user_edit_form::validation() checks it.
        if (isset($usernew->email) && !isset($errors['email'])) {
            if (!validate_email($usernew->email)) {
                $errors['email'] = get_string('invalidemail');
            } else if (($usernew->email !== $user->email) && empty($CFG->allowaccountssameemail)) {
                $select = $DB->sql_equal('email', ':email', false)
                    . ' AND mnethostid = :mnethostid AND id <> :userid';
                $params = [
                    'email'      => $usernew->email,
                    'mnethostid' => $CFG->mnet_localhost_id,
                    'userid'     => $user->id,
                ];
                if ($DB->record_exists_select('user', $select, $params)) {
                    $errors['email'] = get_string('emailexists');
                }
            }

            if (!isset($errors['email']) && $usernew->email === $user->email && over_bounce_threshold($user)) {
                $errors['email'] = get_string('toomanybounces');
            }

            if (!isset($errors['email']) && !empty($CFG->verifychangedemail)
                    && !has_capability('moodle/user:update', context_system::instance())) {
                $errorstr = email_is_not_allowed($usernew->email);
                if ($errorstr !== false) {
                    $errors['email'] = $errorstr;
                }
            }
        }

        $errors += useredit_validate_description_length((array) $usernew);

        // 3. The custom fields validate themselves - uniqueness, the phone field's
        //    country/number check, and anything else a field type adds. Only the
        //    fields the client actually sent are reported on: on the web every box
        //    is posted at once, so core can refuse the whole form over a required
        //    field the user never reached, but here a screen that saves only the
        //    address must not be blocked by a phone number it does not show. A
        //    client that posts the whole form gets the whole form validated.
        foreach (profile_validation($usernew, []) as $item => $message) {
            if (array_key_exists($item, $submitted) && !isset($errors[$item])) {
                $errors[$item] = $message;
            }
        }

        // The validators answer under the form element name; a client knows the
        // field by the name get_profile_form gave it.
        $renamed = [];
        foreach ($errors as $item => $message) {
            $renamed[$item === self::DESCRIPTION_ELEMENT ? 'description' : $item] = $message;
        }

        return $renamed;
    }

    /**
     * Save the profile, doing everything `/user/edit.php` does after `get_data()`.
     *
     * @param stdClass $user the stored user record
     * @param stdClass $usernew the validated new values
     * @return string the address awaiting confirmation, '' when the e-mail did not change
     */
    public static function save(stdClass $user, stdClass $usernew): string {
        global $CFG, $DB, $USER;

        self::require_libs();

        $emailchanged = '';
        $emailchangedkey = null;

        // A changed address is not applied until it has been confirmed, unless the
        // caller is allowed to change addresses outright.
        if (!empty($CFG->emailchangeconfirmation) && isset($usernew->email)
                && $user->email !== $usernew->email
                && !has_capability('moodle/user:update', context_system::instance())) {
            $emailchangedkey = create_user_key('core_user/email_change', $user->id, null, null, time() + 600);
            set_user_preference('newemail', $usernew->email, $user->id);
            set_user_preference('newemailattemptsleft', 3, $user->id);
            $emailchanged = $usernew->email;
            $usernew->email = $user->email;
        }

        $usernew->timemodified = time();

        $authplugin = get_auth_plugin($user->auth);
        if (!$authplugin->user_update($user, $usernew)) {
            throw new \moodle_exception('cannotupdateprofile');
        }

        user_update_user($usernew, false, false);
        useredit_update_user_preference($usernew);

        if (isset($usernew->interests)) {
            useredit_update_interests($usernew, $usernew->interests);
        }

        useredit_update_bounces($user, $usernew);
        useredit_update_trackforums($user, $usernew);
        profile_save_data($usernew);

        \core\event\user_updated::create_from_userid($user->id)->trigger();

        if ($emailchanged !== '' && $emailchangedkey !== null) {
            self::send_email_change_confirmation($user, $emailchanged, $emailchangedkey);
        }

        // Keep the session in step, the way the web page does, so the very next
        // call does not read stale values back.
        $fresh = $DB->get_record('user', ['id' => $user->id], '*', MUST_EXIST);
        if ($USER->id == $fresh->id) {
            foreach ((array) $fresh as $variable => $value) {
                if ($variable === 'description' || $variable === 'password') {
                    // Not kept in the session, for security and performance.
                    continue;
                }
                $USER->$variable = $value;
            }
            profile_load_custom_fields($USER);
        }

        return $emailchanged;
    }

    /**
     * Send the "confirm your new address" mail, as `/user/edit.php` sends it.
     *
     * @param stdClass $user the stored user record
     * @param string $newemail the address awaiting confirmation
     * @param string $key the confirmation key
     * @return void
     */
    protected static function send_email_change_confirmation(stdClass $user, string $newemail, string $key): void {
        global $CFG, $OUTPUT, $SITE;

        $tempuser = clone $user;
        $tempuser->email = $newemail;

        $a = new stdClass();
        $a->url = $CFG->wwwroot . '/user/emailupdate.php?key=' . $key . '&id=' . $user->id;
        $a->site = format_string($SITE->fullname, true, ['context' => context_course::instance(SITEID)]);
        foreach (core_user::get_name_placeholders($user) as $field => $value) {
            $a->{$field} = $value;
        }
        $a->supportemail = $OUTPUT->supportemail();

        email_to_user($tempuser, core_user::get_noreply_user(),
            get_string('emailupdatetitle', 'auth', $a), get_string('emailupdatemessage', 'auth', $a));
    }

    /**
     * The custom profile fields for one user, keyed by form element name.
     *
     * @param int $userid
     * @return \profile_field_base[] keyed by `profile_field_<shortname>`
     */
    protected static function custom_fields(int $userid): array {
        self::require_libs();

        $fields = [];
        foreach (profile_get_user_fields_with_data($userid) as $field) {
            $fields[$field->inputname] = $field;
        }
        return $fields;
    }

    /**
     * The client-facing type name for one form element.
     *
     * @param object $element a QuickForm element
     * @param \profile_field_base|null $field the custom field, when there is one
     * @param string $apiname the name this API gives the field
     * @return string
     */
    protected static function field_type($element, $field, string $apiname): string {
        if ($field) {
            // A custom field's own datatype is more precise than the element it
            // renders as: "phone" and "datetime" both arrive as a group.
            return (string) $field->field->datatype;
        }

        if ($apiname === 'description') {
            return 'editor';
        }

        switch ((string) $element->getType()) {
            case 'select':
            case 'autocomplete':
                return 'select';
            case 'checkbox':
            case 'advcheckbox':
                return 'checkbox';
            case 'textarea':
            case 'editor':
                return 'editor';
            case 'tags':
                return 'tags';
            case 'date_selector':
            case 'date_time_selector':
                return 'datetime';
            default:
                return $apiname === 'email' ? 'email' : 'text';
        }
    }

    /**
     * The value a field currently holds, in the form a client sends back.
     *
     * Read from the account rather than from the form: a QuickForm element hands
     * back whatever shape it renders with (a select returns an array, a date
     * selector returns day/month/year parts), while the stored value is the one
     * the client will echo back to us on save.
     *
     * @param stdClass $user the stored user record
     * @param string $name the API field name
     * @param \profile_field_base|null $field the custom field, when there is one
     * @return string
     */
    protected static function current_value(stdClass $user, string $name, $field): string {
        if ($field) {
            return (string) ($field->data ?? '');
        }

        if ($name === 'interests') {
            return implode(', ', core_tag_tag::get_item_tags_array('core', 'user', $user->id));
        }

        return (string) ($user->{$name} ?? '');
    }

    /**
     * Decode one submitted custom-field value into what the field's save and
     * validate steps expect.
     *
     * The mirror of what `signup_user` accepts, so a value read from
     * `get_profile_form` (or sent at sign-up) round-trips unchanged.
     *
     * @param string $datatype the field's datatype (text, menu, phone, ...)
     * @param string $value the raw submitted value
     * @return mixed string, or an array for a composite field
     */
    protected static function decode_custom_value(string $datatype, string $value) {
        $decoded = json_decode($value, true);
        if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // The phone field is stored as "ISO:number", which is what this API hands
        // out - so accept it on the way back in.
        if ($datatype === 'phone' && preg_match('/^([A-Za-z]{2}):(.+)$/', trim($value), $m)) {
            return ['country' => strtoupper($m[1]), 'number' => $m[2]];
        }

        return $value;
    }

    /**
     * The user's picture, at the requested size.
     *
     * @param stdClass $user the profile owner
     * @param int $size pixels
     * @return string absolute url
     */
    protected static function picture_url(stdClass $user, int $size): string {
        global $PAGE;

        $userpicture = new user_picture($user);
        $userpicture->size = $size;

        return $userpicture->get_url($PAGE)->out(false);
    }

    /**
     * The address to report, honouring the user's "who can see my email" choice.
     *
     * @param stdClass $user the profile owner
     * @param bool $iscurrentuser
     * @return string
     */
    protected static function visible_email(stdClass $user, bool $iscurrentuser): string {
        if ($iscurrentuser || has_capability('moodle/user:viewalldetails', context_user::instance($user->id))) {
            return (string) $user->email;
        }

        return (int) $user->maildisplay === 1 ? (string) $user->email : '';
    }

    /**
     * The description, with file urls rewritten and filters applied.
     *
     * @param stdClass $user the profile owner
     * @param context $context the user context
     * @return string
     */
    protected static function description(stdClass $user, context $context): string {
        self::require_libs();

        if (empty($user->description)) {
            return '';
        }

        $description = file_rewrite_pluginfile_urls($user->description, 'pluginfile.php',
            $context->id, 'user', 'profile', null);

        return format_text($description, $user->descriptionformat ?? FORMAT_HTML, ['context' => $context]);
    }
}
