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

namespace local_jobform\form;

use local_jobform\field_types;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Add / edit a single Job Form field.
 *
 * Reused by both the admin template editor (local_jobform) and — through the
 * same field shape — the per-activity editor. The caller decides where to save.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        // The field's own id (0 = new). Named 'fieldid' so it never collides with
        // the activity's course-module 'id' when this form is used inside mod_jobform.
        $mform->addElement('hidden', 'fieldid');
        $mform->setType('fieldid', PARAM_INT);
        $mform->setDefault('fieldid', 0);

        // Field label.
        $mform->addElement('text', 'name', get_string('fieldname', 'local_jobform'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        // Field type.
        $mform->addElement('select', 'type', get_string('fieldtype', 'local_jobform'), field_types::menu());
        $mform->setDefault('type', field_types::TYPE_TEXT);

        // Required flag.
        $mform->addElement('advcheckbox', 'required', get_string('fieldrequired', 'local_jobform'));
        $mform->setDefault('required', 0);

        // --- Dropdown-only settings ---------------------------------------
        $mform->addElement('textarea', 'options', get_string('fieldoptions', 'local_jobform'),
            ['rows' => 6, 'cols' => 50]);
        $mform->setType('options', PARAM_TEXT);
        $mform->addHelpButton('options', 'fieldoptions', 'local_jobform');
        $mform->hideIf('options', 'type', 'neq', field_types::TYPE_SELECT);

        $mform->addElement('advcheckbox', 'multiple', get_string('fieldmultiple', 'local_jobform'));
        $mform->setDefault('multiple', 0);
        $mform->hideIf('multiple', 'type', 'neq', field_types::TYPE_SELECT);

        // --- Fixed-value-only setting -------------------------------------
        $mform->addElement('text', 'fixedvalue', get_string('fieldfixedvalue', 'local_jobform'), ['size' => 50]);
        $mform->setType('fixedvalue', PARAM_TEXT);
        $mform->hideIf('fixedvalue', 'type', 'neq', field_types::TYPE_FIXED);

        $this->add_action_buttons();
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ($data['type'] === field_types::TYPE_SELECT) {
            $options = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $data['options'] ?? '')));
            if (count($options) < 1) {
                $errors['options'] = get_string('erroroptionsrequired', 'local_jobform');
            }
        }
        if ($data['type'] === field_types::TYPE_FIXED && trim($data['fixedvalue'] ?? '') === '') {
            $errors['fixedvalue'] = get_string('errorfixedvaluerequired', 'local_jobform');
        }

        return $errors;
    }

    /**
     * Prime the form from a stored field record (decoding configdata).
     *
     * @param object $field
     * @return void
     */
    public function set_field_data(object $field): void {
        $config = field_types::decode_config($field->configdata ?? null);
        $this->set_data([
            'fieldid'    => $field->id,
            'name'       => $field->name,
            'type'       => $field->type,
            'required'   => $field->required,
            'options'    => implode("\n", $config['options']),
            'multiple'   => $config['multiple'] ? 1 : 0,
            'fixedvalue' => $config['fixedvalue'],
        ]);
    }
}
