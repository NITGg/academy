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
use local_profilefields\validation;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * WF-5.1 - the learner's own personal details, country and telephone.
 *
 * The screen is deliberately much smaller than core's `/user/edit.php`. The SRS
 * table for this screen names nine rows and no more, so the form carries those
 * nine and nothing else; a learner opening their account should not have to
 * scroll past forum digest preferences to correct a surname.
 *
 * Three of the rows cannot be typed into, and they are `static` elements rather
 * than disabled inputs. A disabled input still posts nothing but still *looks*
 * like somewhere you could type, and AC-4.5.3 wants the country of record to read
 * as a fact about the account rather than as a field that is refusing you.
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
     * A field the administrator has marked as not user-editable.
     *
     * Shown the same way as the country of record: the value, readable, with a
     * padlock - so the learner sees what is held rather than an input that will
     * not accept anything.
     *
     * @param string $value the current value
     * @return string HTML
     */
    protected static function locked_value(string $value): string {
        return html_writer::div(
            html_writer::span($value !== '' ? s($value)
                : get_string('notset', 'local_profilefields'), 'nit-account__lockedvalue')
            . html_writer::span('', 'nit-account__lock', [
                'aria-hidden' => 'true',
                'title' => get_string('lockedfield', 'local_profilefields'),
            ]),
            'nit-account__lockedbox');
    }

    /**
     * Build the form.
     *
     * @return void
     */
    protected function definition(): void {
        global $USER;

        $mform = $this->_form;
        $user = $this->_customdata['user'];

        $mform->addElement('hidden', 'id', $user->id);
        $mform->setType('id', PARAM_INT);

        // --- Picture -------------------------------------------------------

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

        // --- Name ----------------------------------------------------------

        // Whether these can be typed into is the administrator's call, made on
        // Site administration -> Profile fields ("user can edit"). A locked field
        // is shown as its value with a padlock, never as an input that silently
        // discards what is typed into it.
        $locks = (array) ($this->_customdata['locks'] ?? []);

        foreach (['firstname' => get_string('firstname'), 'lastname' => get_string('lastname')] as $name => $label) {
            if (!empty($locks[$name])) {
                $mform->addElement('static', $name . 'locked', $label,
                    self::locked_value((string) $user->{$name}));
                continue;
            }

            $mform->addElement('text', $name, $label, ['maxlength' => 100]);
            $mform->setType($name, PARAM_NOTAGS);
        }

        // AC-4.5.1, said where it matters: a learner correcting a spelling should
        // know it will not reissue the certificate they already hold.
        $mform->addElement('static', 'namehelp', '',
            get_string('namehelp', 'local_profilefields'));

        // --- Email ---------------------------------------------------------

        // Read-only with a button beside it, not an editable box. Changing an
        // address is a two-step act with a password and a confirmation link, and a
        // field you can type over promises that "Save changes" is enough.
        //
        // No button at all on an account that signs in through Google: its address
        // belongs to the Google account, and offering a change we would then refuse
        // for want of a password is worse than not offering it.
        // Two independent reasons the address may not be changeable: the account
        // has no local password to confirm with, or the administrator has locked
        // the field. Either one is enough to take the button away.
        $canchange = (bool) $this->_customdata['canchangeemail'] && empty($locks['email']);

        $control = $canchange
            ? html_writer::link(
                (new \moodle_url(account::url(account::SECTION_PROFILE), ['changeemail' => 1]))->out(false),
                get_string('changeemailbutton', 'local_profilefields'),
                ['class' => 'btn btn-secondary nit-account__inlinebtn'])
            : '';

        $mform->addElement('static', 'emailrow', get_string('email'),
            html_writer::div(
                html_writer::span(s($user->email), 'nit-account__readvalue') . $control,
                'nit-account__inlinerow'));

        if ($canchange) {
            $emailhelp = 'emailchangehelp';
        } else if (!empty($locks['email'])) {
            // Say which of the two reasons it is. "Your address comes from Google"
            // on an account that signs in with a password would just be wrong.
            $emailhelp = 'emailchangelocked';
        } else {
            $emailhelp = 'emailchangeexternal';
        }

        $mform->addElement('static', 'emailhelp', '',
            get_string($emailhelp, 'local_profilefields'));

        // --- Nationality ---------------------------------------------------

        // Rendered by the profile field itself rather than as a hand-built select,
        // so the option list, the default and the save path stay the field's own.
        // AC-4.5.5 keeps it optional, so any "required" flag an administrator has
        // set on it is overridden below.
        foreach (profile_get_user_fields_with_data($user->id) as $field) {
            if (($field->field->shortname ?? '') !== 'nationality') {
                continue;
            }
            $field->edit_field($mform);
            $name = $field->inputname;
            if ($mform->elementExists($name)) {
                $mform->addElement('static', 'nationalityhelp', '',
                    get_string('nationalityhelp', 'local_profilefields'));
            }
            break;
        }

        // --- Preferred language --------------------------------------------

        $mform->addElement('select', 'lang', get_string('preferredlanguage'),
            get_string_manager()->get_list_of_translations());
        $mform->addElement('static', 'langhelp', '',
            get_string('preferredlanguagehelp', 'local_profilefields'));

        // --- Country and telephone (locked) --------------------------------

        $mform->addElement('static', 'lockedgroup', '',
            $this->_customdata['lockedgroup']);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Check the name fields.
     *
     * Delegated to the same call the sign-up form makes, rather than repeating the
     * character class and the length here. A name that could not be typed at
     * registration must not be reachable by editing afterwards, and two copies of
     * that rule would eventually disagree about which characters an Arabic name
     * may contain.
     *
     * The email address is not checked here - it is not on this form. It is
     * changed through {@see changeemail_form}, which validates it there.
     *
     * @param array $data submitted values
     * @param array $files
     * @return array element name => message
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // A locked name has no input on the form, so there is nothing submitted to
        // check and nothing the learner could do about a complaint if there were.
        $locks = (array) ($this->_customdata['locks'] ?? []);
        $check = [];
        foreach (['firstname', 'lastname'] as $name) {
            if (empty($locks[$name])) {
                $check[$name] = (string) ($data[$name] ?? '');
            }
        }

        return $errors + validation::signup_fields($check);
    }
}
