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

namespace local_profilefields\form;

use html_writer;
use local_profilefields\account;
use local_profilefields\manager;
use local_profilefields\validation;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * WF-5.1 - the learner's own personal details.
 *
 * The form holds no list of its own of what a profile contains. Every row on it
 * comes from Site administration -> Profile fields:
 *
 *  - the Sign-up tab decides *whether* a field appears here at all (its
 *    placement) and what it is called (the label override);
 *  - the Profile tab decides *whether it can be typed into* (the "user can edit"
 *    column, which for core fields is Moodle's own per-auth field lock and for
 *    custom fields is `user_info_field.locked`);
 *  - a custom field created on Site administration -> User profile fields
 *    appears here on its next page load, drawn by the field plugin itself.
 *
 * That is the point of it: the screen used to name its nine rows in code, so a
 * field an administrator added, renamed or unlocked never showed up here and the
 * management page quietly meant nothing to this screen.
 *
 * A field the reader may not change is never drawn as a disabled input. A
 * disabled input posts nothing but still looks like somewhere you could type,
 * and this screen answers "you cannot change this" exactly one way wherever it
 * has to: the value, a padlock, and one sentence underneath
 * ({@see account::locked_value()} and {@see account::locked_note()}).
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class account_profile_form extends moodleform {

    /** @var int The largest profile picture accepted, per the SRS screen table. */
    const PICTURE_MAXBYTES = 2097152;

    /**
     * The picture upload's settings.
     *
     * Defined once because the same array has to be handed to three different
     * calls - file_prepare_draft_area(), the filemanager element, and
     * core_user::update_picture() - and a value that differs between them is a
     * file that uploads and then quietly fails to become an avatar.
     *
     * @return array
     */
    public static function filemanager_options(): array {
        return [
            'maxbytes' => self::PICTURE_MAXBYTES,
            'subdirs' => 0,
            'maxfiles' => 1,
            // The SRS screen table says "JPG or PNG", and the help text under the
            // control repeats it, so the control refuses anything else rather than
            // accepting a GIF the pipeline will drop later.
            'accepted_types' => ['.jpg', '.jpeg', '.png'],
        ];
    }

    /**
     * Whether the profile picture control is drawn at all.
     *
     * Two switches, either of which is enough to leave it off: the site-wide
     * `$CFG->disableuserimages`, and "Picture of user" set to Hidden on the
     * management page. account.php asks the same question before it saves an
     * upload, so a control that was never drawn cannot be posted into.
     *
     * @return bool
     */
    public static function picture_enabled(): bool {
        global $CFG;

        return empty($CFG->disableuserimages) && manager::on_profile('picture');
    }

    /**
     * Build the form.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $user = $this->_customdata['user'];

        // Labels above their fields, not in a column down the leading edge, which
        // is how WF-5.1 draws them. Core's own switch for it - it adds
        // `full-width-labels` to the form, and Boost has the rules ready. Worth
        // saying because the temptation is to override the Bootstrap columns from
        // the theme instead, and that fights a `:not(.full-width-labels)` rule
        // core already wrote for exactly this case.
        //
        // The pane is about 550px wide inside the account screen, so this is the
        // "narrow container" the method is documented for.
        $this->set_display_vertical();

        $mform->addElement('hidden', 'id', $user->id);
        $mform->setType('id', PARAM_INT);

        $this->add_picture($user);
        $this->add_core_fields($user);
        $this->add_profile_fields($user);
        $this->add_language();

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * The profile picture: the current one, then the control that replaces it.
     *
     * @param \stdClass $user the account being shown
     * @return void
     */
    protected function add_picture(\stdClass $user): void {
        if (!self::picture_enabled()) {
            return;
        }

        $mform = $this->_form;

        $mform->addElement('static', 'currentpicture', '',
            html_writer::div($this->_customdata['picture'], 'nit-account__avatar'));

        $mform->addElement('filemanager', 'imagefile',
            get_string('profilepicture', 'local_profilefields'), null, self::filemanager_options());
        $mform->addElement('static', 'picturehelp', '',
            get_string('picturehelp', 'local_profilefields'));

        if (!empty($user->picture)) {
            $mform->addElement('advcheckbox', 'deletepicture', get_string('deletepicture'));
            $mform->setDefault('deletepicture', 0);
        }
    }

    /**
     * The core user fields, exactly as the management page has them configured.
     *
     * @param \stdClass $user the account being shown
     * @return void
     */
    protected function add_core_fields(\stdClass $user): void {
        $mform = $this->_form;

        foreach (account::core_fields() as $name => $locked) {
            // The address is the one core field that is never a plain box - see
            // add_email() for why.
            if ($name === 'email') {
                $this->add_email($user, $locked);
                continue;
            }

            $label = account::core_label($name);

            if ($locked) {
                $mform->addElement('static', $name . 'locked', $label,
                    account::locked_value(account::core_display($user, $name))
                    . account::locked_note());
            } else if ($name === 'country') {
                // Core's own list, so the stored ISO code and the menu can never
                // disagree about what a country is called.
                $mform->addElement('select', 'country', $label,
                    ['' => get_string('selectacountry')] + get_string_manager()->get_list_of_countries());
            } else {
                $mform->addElement('text', $name, $label, ['maxlength' => 100]);
                $mform->setType($name, PARAM_NOTAGS);
            }

            // "Required" is the administrator's call on the two core fields that
            // offer the choice (city and country). The rest are required by Moodle
            // itself and are checked in validation() rather than announced here.
            if (!$locked && self::is_required($name)) {
                $mform->addRule($name, get_string('required'), 'required', null, 'client');
            }

            $this->add_core_help($name);
        }
    }

    /**
     * The sentence a core field owes the reader, under the field it is about.
     *
     * Two rows carry one: a corrected name does not reissue a certificate already
     * held, and the country of record is what decides the prices quoted. Both are
     * things somebody would otherwise only find out afterwards.
     *
     * @param string $name core field name
     * @return void
     */
    protected function add_core_help(string $name): void {
        $help = [
            'lastname' => 'namehelp',
            'country' => 'countryhelp',
        ];

        if (isset($help[$name])) {
            $this->_form->addElement('static', $name . 'help', '',
                get_string($help[$name], 'local_profilefields'));
        }
    }

    /**
     * The e-mail row.
     *
     * Read-only with a button beside it, never a box to type over: changing an
     * address is a two-step act with a password and a confirmation link, and a
     * field you can type into promises that "Save changes" is enough.
     *
     * Two independent reasons it may not be changeable at all, and they are not
     * the same reason, so they do not get the same sentence: the administrator has
     * locked the field, or the account signs in through Google and has no local
     * password to confirm a change with. Both are drawn the same way as every
     * other locked field on the screen - only the explanation differs.
     *
     * @param \stdClass $user the account being shown
     * @param bool $locked true when the administrator has locked the field
     * @return void
     */
    protected function add_email(\stdClass $user, bool $locked): void {
        $mform = $this->_form;
        $label = account::core_label('email');

        if ($locked || empty($this->_customdata['canchangeemail'])) {
            $mform->addElement('static', 'emaillocked', $label,
                account::locked_value((string) $user->email)
                . account::locked_note($locked ? 'lockedfield' : 'emailchangeexternal'));
            return;
        }

        $control = html_writer::link(
            (new \moodle_url(account::url(account::SECTION_PROFILE), ['changeemail' => 1]))->out(false),
            get_string('changeemailbutton', 'local_profilefields'),
            ['class' => 'btn btn-secondary nit-account__inlinebtn']);

        $mform->addElement('static', 'emailrow', $label,
            html_writer::div(
                html_writer::span(s($user->email), 'nit-account__readvalue') . $control,
                'nit-account__inlinerow'));
        $mform->addElement('static', 'emailhelp', '',
            get_string('emailchangehelp', 'local_profilefields'));
    }

    /**
     * Every custom profile field the site shows, drawn by the field plugin itself.
     *
     * Rendered by the field rather than as hand-built controls, so the option
     * list, the default, the validation and the save path stay the field's own -
     * which is also why a field type this plugin has never heard of works here.
     *
     * @param \stdClass $user the account being shown
     * @return void
     */
    protected function add_profile_fields(\stdClass $user): void {
        $mform = $this->_form;
        // One rule for every lock on this screen, core field and custom field alike.
        $canoverride = account::can_override_locks();
        $category = null;

        foreach (account::profile_fields((int) $user->id) as $inputname => $field) {
            // A heading whenever the profile-field category changes, the way
            // /user/edit.php breaks the same fields up. This screen shows every
            // field the administrator has not hidden - on this site that is
            // twenty-six of them across two categories - and twenty-six controls
            // in one undifferentiated column is a page nobody reads to the end.
            // The headings are the site's own category names, so an administrator
            // reorganising the fields reorganises this screen with them.
            $name = (string) $field->get_category_name();
            if ($name !== '' && $name !== $category) {
                $category = $name;
                $mform->addElement('static', 'cat_' . $field->field->categoryid, '',
                    \html_writer::tag('h3', format_string($name),
                        ['class' => 'nit-account__subtitle']));
            }

            if ($field->is_locked() && !$canoverride) {
                // Core answers a locked field by freezing its control, which leaves
                // a greyed-out box that still reads as somewhere you could type.
                // Said the one way this screen says it instead.
                $mform->addElement('static', $inputname . 'locked',
                    format_string($field->field->name),
                    account::locked_value((string) $field->display_data(), true)
                    . account::locked_note());
                continue;
            }

            if (!$field->edit_field($mform)) {
                continue;
            }

            $this->add_profile_field_help($field, $inputname);
        }
    }

    /**
     * The sentence a custom field owes the reader, where there is one.
     *
     * Only nationality has one today, and it answers the question the field
     * invites: whether saying where you are from changes what you are charged.
     * Anything else an administrator adds appears with its own label and no
     * commentary, which is the right default for a field this plugin has never
     * seen.
     *
     * @param \profile_field_base $field the field just added
     * @param string $inputname its form element name
     * @return void
     */
    protected function add_profile_field_help($field, string $inputname): void {
        $mform = $this->_form;

        if (($field->field->shortname ?? '') !== 'nationality' || !$mform->elementExists($inputname)) {
            return;
        }

        $mform->addElement('static', 'nationalityhelp', '',
            get_string('nationalityhelp', 'local_profilefields'));
    }

    /**
     * The interface language.
     *
     * A preference rather than a profile field, so it is not on the management
     * page and is always offered.
     *
     * @return void
     */
    protected function add_language(): void {
        $mform = $this->_form;

        $mform->addElement('select', 'lang', get_string('preferredlanguage'),
            get_string_manager()->get_list_of_translations());
        $mform->addElement('static', 'langhelp', '',
            get_string('preferredlanguagehelp', 'local_profilefields'));
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
     * Check the fields this form actually offered.
     *
     * Delegated to the same call the sign-up form makes, rather than repeating the
     * character class and the length here. A name that could not be typed at
     * registration must not be reachable by editing afterwards, and two copies of
     * that rule would eventually disagree about which characters an Arabic name
     * may contain.
     *
     * A locked field has no input on the form, so there is nothing submitted to
     * check and nothing the reader could do about a complaint if there were. The
     * e-mail address is not checked here either - it is not on this form; it is
     * changed through {@see changeemail_form}, which validates it there.
     *
     * @param array $data submitted values
     * @param array $files
     * @return array element name => message
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $check = [];
        foreach (account::core_fields() as $name => $locked) {
            if (!$locked && $name !== 'email' && array_key_exists($name, $data)) {
                $check[$name] = (string) $data[$name];
            }
        }

        return $errors + validation::signup_fields($check);
    }
}
