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

use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The confirmation AC-4.5.7 puts in front of deleting an account.
 *
 * "Account deletion requires password confirmation and displays this warning."
 *
 * Two deliberate choices about how hard this is to do by accident. The password
 * box is the specification's, and it also rules out the case that matters most -
 * an unattended logged-in browser. The typed confirmation word is ours: this is
 * the one action on the site with no undo, and a single click behind a password
 * the browser has already filled in is not really a decision.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class deleteaccount_form extends moodleform {

    /**
     * Build the form.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('html', \html_writer::div(
            get_string('deleteaccountwarning', 'local_profilefields'),
            'alert alert-danger'
        ));

        $mform->addElement('password', 'password',
            get_string('deleteaccountconfirm', 'local_profilefields'));
        $mform->setType('password', PARAM_RAW);

        $mform->addElement('text', 'confirmword',
            get_string('deleteaccounttype', 'local_profilefields',
                get_string('deleteaccountword', 'local_profilefields')));
        $mform->setType('confirmword', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('deleteaccount', 'local_profilefields'));

        // The submit button waits for both boxes (AC-4.1.1's behaviour, applied
        // here because this is the screen where a mis-click costs the most).
        $mform->updateAttributes(['data-nit-gate' => '']);
    }

    /**
     * Check the password and the typed confirmation.
     *
     * @param array $data submitted values
     * @param array $files
     * @return array element name => message
     */
    public function validation($data, $files): array {
        global $USER;

        $errors = parent::validation($data, $files);

        if (!validate_internal_user_password($USER, (string) ($data['password'] ?? ''))) {
            $errors['password'] = get_string('deleteaccountwrongpassword', 'local_profilefields');
        }

        // Compared against the localised word, so an Arabic interface asks for the
        // Arabic one - a learner should not have to type a language they do not
        // read to leave.
        $expected = get_string('deleteaccountword', 'local_profilefields');
        if (\core_text::strtolower(trim((string) ($data['confirmword'] ?? '')))
                !== \core_text::strtolower($expected)) {
            $errors['confirmword'] = get_string('deleteaccountwrongword', 'local_profilefields', $expected);
        }

        return $errors;
    }
}
