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

/**
 * Edit one event's email: the on/off switch plus the English and Arabic
 * subject + body.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_emails\form;

use local_nit_emails\templates;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The per-event template editor.
 */
class template_form extends \moodleform {

    /**
     * Build the form.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $event = $this->_customdata['event'];

        $mform->addElement('hidden', 'event', $event);
        $mform->setType('event', PARAM_ALPHANUMEXT);

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_nit_emails'), '', null, [0, 1]);
        $mform->addHelpButton('enabled', 'enabled', 'local_nit_emails');
        $mform->setDefault('enabled', 1);

        // The body is authored as an HTML fragment — the branded shell (header
        // band, footer, RTL flip) is added when the email is sent, so an admin
        // only ever edits the wording.
        $editoroptions = [
            'maxfiles'              => 0,
            'noclean'               => true,
            'trusttext'             => false,
            'enable_filemanagement' => false,
            'autosave'              => false,
        ];

        foreach (templates::LANGS as $lang) {
            $mform->addElement('header', 'hdr_' . $lang, get_string('lang_' . $lang, 'local_nit_emails'));
            $mform->setExpanded('hdr_' . $lang, true);

            $mform->addElement('text', 'subject_' . $lang, get_string('subject', 'local_nit_emails'),
                ['size' => 70, 'dir' => $lang === 'ar' ? 'rtl' : 'ltr']);
            $mform->setType('subject_' . $lang, PARAM_TEXT);
            $mform->addRule('subject_' . $lang, get_string('required'), 'required', null, 'client');

            $mform->addElement('editor', 'body_' . $lang, get_string('body', 'local_nit_emails'),
                null, $editoroptions);
            $mform->setType('body_' . $lang, PARAM_RAW);
        }

        $buttons = [
            $mform->createElement('submit', 'savechanges', get_string('savechanges')),
            $mform->createElement('submit', 'resetdefaults', get_string('resetdefaults', 'local_nit_emails')),
        ];
        $mform->addGroup($buttons, 'actionbuttons', '', ' ', false);
        // "Reset to defaults" throws the edits away, so it must not be blocked
        // by the required-subject rule on a field the admin just emptied.
        $mform->registerNoSubmitButton('resetdefaults');
    }

    /**
     * Load the stored (or shipped) template into the form.
     *
     * @param string $event
     * @return void
     */
    public function load_event(string $event): void {
        $data = ['event' => $event, 'enabled' => templates::is_enabled($event) ? 1 : 0];
        foreach (templates::LANGS as $lang) {
            $data['subject_' . $lang] = templates::subject($event, $lang);
            $data['body_' . $lang] = [
                'text'   => templates::body($event, $lang),
                'format' => FORMAT_HTML,
            ];
        }
        $this->set_data($data);
    }
}
