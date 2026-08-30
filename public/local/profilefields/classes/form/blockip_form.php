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

use local_profilefields\allowlist;
use local_profilefields\blocklist;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The "add an address to the registration deny list" form.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class blockip_form extends moodleform {

    /**
     * Whether this instance is adding to the exemption list rather than the deny list.
     *
     * The two lists take the same two boxes and validate identically, so one form
     * serves both; only the tab it posts to, its button label and which list it
     * checks for duplicates change.
     *
     * @return bool
     */
    protected function is_allow_mode(): bool {
        return ($this->_customdata['mode'] ?? '') === 'allow';
    }

    /**
     * Two boxes: which address, and why.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $allow = $this->is_allow_mode();

        $mform->addElement('hidden', 'tab', $allow ? 'allowlist' : 'blacklist');
        $mform->setType('tab', PARAM_ALPHA);

        $mform->addElement('text', 'ip', get_string('blockipaddress', 'local_profilefields'),
            ['size' => 32, 'placeholder' => '1.2.3.4']);
        $mform->setType('ip', PARAM_RAW_TRIMMED);
        $mform->addRule('ip', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('ip', 'blockipaddress', 'local_profilefields');

        $mform->addElement('text', 'note', get_string('blockipnote', 'local_profilefields'),
            ['size' => 48]);
        $mform->setType('note', PARAM_TEXT);

        $this->add_action_buttons(false, get_string(
            $allow ? 'ipallowlistadd' : 'blockipadd', 'local_profilefields'));
    }

    /**
     * Reject anything the deny list could not match against later.
     *
     * A typo stored verbatim would sit in the list looking effective while matching
     * nothing, so the notation is checked here rather than discovered months later.
     *
     * @param array $data submitted values
     * @param array $files
     * @return array element name => message
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $allow = $this->is_allow_mode();

        $ip = $allow
            ? allowlist::normalise((string) $data['ip'])
            : blocklist::normalise((string) $data['ip']);

        if ($ip === '') {
            $errors['ip'] = get_string('blockipinvalid', 'local_profilefields');
        } else if ($allow ? allowlist::listed($ip) : blocklist::listed($ip)) {
            $errors['ip'] = get_string(
                $allow ? 'alreadyallowed' : 'blockipduplicate', 'local_profilefields');
        }

        return $errors;
    }
}
