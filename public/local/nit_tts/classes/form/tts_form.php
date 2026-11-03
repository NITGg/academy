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

namespace local_nit_tts\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_nit_tts\gemini;

/**
 * Text, voice and style for one text-to-speech request.
 *
 * @package    local_nit_tts
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tts_form extends \moodleform {

    /** @var int Longest text accepted; Gemini TTS caps its input well above this. */
    const MAXLENGTH = 5000;

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('textarea', 'text', get_string('text', 'local_nit_tts'),
            ['rows' => 8, 'cols' => 80, 'dir' => 'rtl', 'lang' => 'ar', 'maxlength' => self::MAXLENGTH]);
        $mform->setType('text', PARAM_TEXT);
        $mform->addRule('text', null, 'required', null, 'client');

        $voices = [];
        foreach (gemini::VOICES as $name => $gender) {
            $voices[$name] = $name . ' (' . get_string('voice_' . $gender, 'local_nit_tts') . ')';
        }
        $mform->addElement('select', 'voice', get_string('voice', 'local_nit_tts'), $voices);
        $mform->setDefault('voice', 'Kore');

        $mform->addElement('text', 'style', get_string('style', 'local_nit_tts'), ['size' => 60, 'dir' => 'rtl']);
        $mform->setType('style', PARAM_TEXT);
        $mform->addHelpButton('style', 'style', 'local_nit_tts');

        $this->add_action_buttons(false, get_string('convert', 'local_nit_tts'));
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $text = trim($data['text'] ?? '');
        if ($text === '') {
            $errors['text'] = get_string('required');
        } else if (\core_text::strlen($text) > self::MAXLENGTH) {
            $errors['text'] = get_string('err_toolong', 'local_nit_tts', self::MAXLENGTH);
        }
        if (!isset(gemini::VOICES[$data['voice'] ?? ''])) {
            $errors['voice'] = get_string('required');
        }
        return $errors;
    }
}
