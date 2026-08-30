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

use core_text;
use local_profilefields\validation;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Correct a mistyped address before the account has been confirmed (AC-4.2.5).
 *
 * The password box is the whole security model of this form. verify.php is
 * addressed by user id, which anyone can guess, so without it this would be a
 * way to move a stranger's unconfirmed registration to an address you control -
 * and since confirming that address activates the account, that is account
 * takeover with extra steps. Knowing the password proves the registration is
 * yours to correct.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class changeemail_form extends moodleform {

    /**
     * Build the form.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;

        // No intro line here: verify.php already prints "Change email address" as
        // the heading immediately above this form, and repeating it inside made
        // the same four words appear twice in a row.
        $mform->addElement('text', 'newemail', get_string('email'), ['maxlength' => 100]);
        $mform->setType('newemail', PARAM_RAW_TRIMMED);

        $mform->addElement('password', 'password', get_string('password'));
        $mform->setType('password', PARAM_RAW);

        // Not a required rule: both boxes are validated below, where the
        // specification's own wording is available.
        $this->add_action_buttons(true, get_string('savechanges'));

        // Opt this form into the AC-4.1.1 button gate. Ours to mark, so it is
        // marked here rather than named in the theme's page list.
        $mform->updateAttributes(['data-nit-gate' => '']);
    }

    /**
     * Check the new address and the password behind the request.
     *
     * @param array $data submitted values
     * @param array $files
     * @return array element name => message
     */
    public function validation($data, $files): array {
        global $DB, $CFG;

        $errors = parent::validation($data, $files);
        $user = $this->_customdata['user'];

        $email = core_text::strtolower(trim((string) ($data['newemail'] ?? '')));

        $shape = validation::email($email);
        if ($shape !== null) {
            $errors['newemail'] = $shape;

        } else if ($email === core_text::strtolower($user->email)) {
            // Nothing to do, and saying so is friendlier than sending a fresh
            // confirmation to the address they are already waiting on.
            $errors['newemail'] = get_string('verifyemailtaken', 'local_profilefields');

        } else {
            $taken = $DB->record_exists_select(
                'user',
                $DB->sql_equal('email', ':email', false, false) . ' AND mnethostid = :mnethostid AND deleted = 0',
                ['email' => $email, 'mnethostid' => $CFG->mnet_localhost_id]
            );
            if ($taken) {
                $errors['newemail'] = get_string('verifyemailtaken', 'local_profilefields');
            }
        }

        // An account created through Google has no password to check, and it is
        // also already confirmed, so it never reaches this form. Anything else
        // without a usable password is refused rather than waved through.
        if (!validate_internal_user_password($user, (string) ($data['password'] ?? ''))) {
            $errors['password'] = get_string('deleteaccountwrongpassword', 'local_profilefields');
        }

        return $errors;
    }

    /**
     * An address with its local part reduced to its first and last character.
     *
     * Shown wherever the page cannot be sure who is reading - AC-4.2's mobile
     * wording asks for "the masked email address" for the same reason.
     *
     * Addresses too short to hide anything (a@x.com) are masked completely rather
     * than returned intact.
     *
     * @param string $email
     * @return string e.g. "a****d@example.com"
     */
    public static function mask(string $email): string {
        $at = strrpos($email, '@');
        if ($at === false || $at < 1) {
            return str_repeat('*', max(3, core_text::strlen($email)));
        }

        $local = core_text::substr($email, 0, $at);
        $domain = core_text::substr($email, $at);

        if (core_text::strlen($local) <= 2) {
            return str_repeat('*', 4) . $domain;
        }

        // The run of asterisks is capped rather than matching the local part
        // character for character. A long address otherwise produces a wall of
        // stars wide enough to wrap the line - which reads as damage rather than
        // as privacy, and tells the reader nothing extra about how long their own
        // address is.
        $hidden = min(6, max(3, core_text::strlen($local) - 2));

        return core_text::substr($local, 0, 1)
            . str_repeat('*', $hidden)
            . core_text::substr($local, -1)
            . $domain;
    }
}
