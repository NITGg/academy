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
 * Add / edit one card of the "Why choose us" section: picture, heading and text (en/ar).
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class whycard_form extends moodleform {

    #[\Override]
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'cardid', 0);
        $mform->setType('cardid', PARAM_INT);

        $mform->addElement('filemanager', 'image_filemanager',
            get_string('whycard_image', 'local_nit_category'), null, whychoose::image_options());
        $mform->addHelpButton('image_filemanager', 'whycard_image', 'local_nit_category');

        foreach (['title' => 'text', 'body' => 'textarea'] as $key => $type) {
            $mform->addElement('header', $key . 'header', get_string('whycard_' . $key, 'local_nit_category'));
            $mform->setExpanded($key . 'header');
            foreach (whychoose::LANGS as $lang) {
                $name  = $key . '_' . $lang;
                $label = get_string('whychoose_lang_' . $lang, 'local_nit_category');
                $attrs = ['dir' => $lang === 'ar' ? 'rtl' : 'ltr'];
                if ($type === 'textarea') {
                    $mform->addElement('textarea', $name, $label, $attrs + ['rows' => 4, 'cols' => 70]);
                } else {
                    $mform->addElement('text', $name, $label, $attrs + ['size' => 70]);
                }
                $mform->setType($name, PARAM_TEXT);
            }
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    #[\Override]
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        // A card needs a heading in at least one language; the page has nothing to print otherwise.
        if (trim((string) ($data['title_en'] ?? '')) === '' && trim((string) ($data['title_ar'] ?? '')) === '') {
            $errors['title_en'] = get_string('whycard_titlerequired', 'local_nit_category');
        }
        return $errors;
    }

    /**
     * Prime the form from a stored card (or from nothing, for a new one) plus its draft picture.
     *
     * @param \stdClass|null $card
     * @return void
     */
    public function set_card(?\stdClass $card): void {
        $id = $card ? (int) $card->id : 0;

        $draftid = file_get_submitted_draft_itemid('image_filemanager');
        file_prepare_draft_area($draftid, \context_system::instance()->id, 'local_nit_category',
            whychoose::FILEAREA, $id, whychoose::image_options());

        $data = ['cardid' => $id, 'image_filemanager' => $draftid];
        foreach (['title', 'body'] as $key) {
            foreach (whychoose::split($card->{$key} ?? '') as $lang => $val) {
                $data[$key . '_' . $lang] = $val;
            }
        }
        $this->set_data($data);
    }
}
