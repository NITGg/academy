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

namespace local_nit_category\form;

use local_nit_category\whychoose;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/formslib.php");

/**
 * The three texts of the "Why choose us" section, each in English and Arabic.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class whychoose_texts_form extends moodleform {

    #[\Override]
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'action', 'savetexts');
        $mform->setType('action', PARAM_ALPHA);

        foreach (whychoose::TEXTS as $key) {
            $mform->addElement('header', $key . 'header', get_string('whychoose_' . $key, 'local_nit_category'));
            $mform->setExpanded($key . 'header');
            $mform->addElement('static', $key . 'hint', '', get_string('whychoose_' . $key . '_desc', 'local_nit_category'));

            foreach (whychoose::LANGS as $lang) {
                $name  = $key . '_' . $lang;
                $label = get_string('whychoose_lang_' . $lang, 'local_nit_category');
                $attrs = ['dir' => $lang === 'ar' ? 'rtl' : 'ltr'];
                if ($key === 'description') {
                    $mform->addElement('textarea', $name, $label, $attrs + ['rows' => 3, 'cols' => 70]);
                } else {
                    $mform->addElement('text', $name, $label, $attrs + ['size' => 70]);
                }
                $mform->setType($name, PARAM_TEXT);
            }
        }

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * Prime every per-language field from the stored {mlang} values.
     *
     * @return void
     */
    public function set_texts(): void {
        $data = [];
        foreach (whychoose::get_texts() as $key => $raw) {
            foreach (whychoose::split($raw) as $lang => $val) {
                $data[$key . '_' . $lang] = $val;
            }
        }
        $this->set_data($data);
    }
}
