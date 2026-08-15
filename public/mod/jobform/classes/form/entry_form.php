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

namespace mod_jobform\form;

use local_jobform\field_types;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * The form a student fills in, built dynamically from the activity's fields.
 *
 * @package    mod_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry_form extends \moodleform {

    /** @var string Prefix for dynamic element names. */
    const PREFIX = 'field_';

    /**
     * Build one element per field.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id'); // Course module id.
        $mform->setType('id', PARAM_INT);

        /** @var object[] $fields */
        $fields = $this->_customdata['fields'] ?? [];
        $readonly = !empty($this->_customdata['readonly']);

        // When any field defines a group, the whole form is split into headed
        // sections (ungrouped fields fall under a "General" section).
        $anygroup = false;
        foreach ($fields as $field) {
            if (\local_jobform\mlang::resolve($field->groupname ?? '') !== '') {
                $anygroup = true;
                break;
            }
        }
        $currentheader = null;
        $headercount = 0;

        foreach ($fields as $field) {
            $name = self::PREFIX . $field->id;
            $label = \local_jobform\mlang::display($field->name);
            $config = field_types::decode_config($field->configdata ?? null);

            // Emit a section header when the group changes.
            if ($anygroup) {
                $group = \local_jobform\mlang::resolve($field->groupname ?? '');
                $headerlabel = $group !== '' ? $group : get_string('generalsection', 'mod_jobform');
                if ($headerlabel !== $currentheader) {
                    $mform->addElement('header', 'jfgroup_' . ($headercount++), $headerlabel);
                    $mform->setExpanded('jfgroup_' . ($headercount - 1), true);
                    $currentheader = $headerlabel;
                }
            }

            switch ($field->type) {
                case field_types::TYPE_NUMBER:
                    $mform->addElement('text', $name, $label);
                    $mform->setType($name, PARAM_RAW_TRIMMED);
                    break;

                case field_types::TYPE_EMAIL:
                    $mform->addElement('text', $name, $label);
                    $mform->setType($name, PARAM_RAW_TRIMMED);
                    break;

                case field_types::TYPE_PHONE:
                    $mform->addElement('text', $name, $label);
                    $mform->setType($name, PARAM_RAW_TRIMMED);
                    break;

                case field_types::TYPE_URL:
                    $mform->addElement('text', $name, $label);
                    $mform->setType($name, PARAM_RAW_TRIMMED);
                    break;

                case field_types::TYPE_DATE:
                    $mform->addElement('date_selector', $name, $label, ['optional' => empty($field->required)]);
                    break;

                case field_types::TYPE_CHECKBOX:
                    $mform->addElement('advcheckbox', $name, $label);
                    break;

                case field_types::TYPE_SELECT:
                    // Key = the raw (possibly {mlang}) stored value; label = resolved.
                    $options = [];
                    foreach ($config['options'] as $optraw) {
                        $options[$optraw] = \local_jobform\mlang::resolve($optraw);
                    }
                    if (!$config['multiple']) {
                        $options = ['' => get_string('choosedots')] + $options;
                    }
                    $el = $mform->addElement('select', $name, $label, $options);
                    if ($config['multiple']) {
                        $el->setMultiple(true);
                    }
                    break;

                case field_types::TYPE_FIXED:
                    // Admin-set, read-only for the student.
                    $mform->addElement('static', $name . '_static', $label, s($config['fixedvalue']));
                    $mform->addElement('hidden', $name, $config['fixedvalue']);
                    $mform->setType($name, PARAM_RAW);
                    break;

                case field_types::TYPE_TEXT:
                default:
                    $mform->addElement('text', $name, $label);
                    $mform->setType($name, PARAM_TEXT);
                    break;
            }

            // Required marker (fixed values are always supplied, so never required).
            if (!empty($field->required)
                    && !in_array($field->type, [field_types::TYPE_FIXED, field_types::TYPE_CHECKBOX, field_types::TYPE_DATE], true)) {
                $mform->addRule($name, get_string('required'), 'required', null, 'client');
            }
        }

        if ($readonly) {
            $mform->hardFreeze();
        } else {
            // Send the form.
            $buttonarray = [];
            $buttonarray[] = $mform->createElement('submit', 'submitform',
                get_string('sendform', 'mod_jobform'));
            $buttonarray[] = $mform->createElement('cancel');
            $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
        }
    }

    /**
     * Type-aware validation for the fields.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Only enforce full validation when actually sending (not saving a draft).
        if (!empty($data['savedraft'])) {
            return $errors;
        }

        $fields = $this->_customdata['fields'] ?? [];
        foreach ($fields as $field) {
            $name = self::PREFIX . $field->id;
            $value = $data[$name] ?? '';

            switch ($field->type) {
                case field_types::TYPE_NUMBER:
                    if ($value !== '' && !is_numeric($value)) {
                        $errors[$name] = get_string('errornotnumber', 'mod_jobform');
                    }
                    break;
                case field_types::TYPE_EMAIL:
                    if ($value !== '' && !validate_email($value)) {
                        $errors[$name] = get_string('errornotemail', 'mod_jobform');
                    }
                    break;
                case field_types::TYPE_URL:
                    if ($value !== '' && !preg_match('#^https?://#i', $value)) {
                        $errors[$name] = get_string('errornoturl', 'mod_jobform');
                    }
                    break;
            }

            // Required check for the types that can't use client-side 'required'.
            if (!empty($field->required)) {
                if ($field->type === field_types::TYPE_SELECT) {
                    if (empty($value) || (is_array($value) && !array_filter($value))) {
                        $errors[$name] = get_string('required');
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Convert submitted form data into fieldid => storable string.
     *
     * @param object $data validated form data
     * @param object[] $fields the activity's fields
     * @return array fieldid => value
     */
    public static function normalize_values(object $data, array $fields): array {
        $values = [];
        foreach ($fields as $field) {
            $name = self::PREFIX . $field->id;
            $raw = $data->$name ?? '';

            switch ($field->type) {
                case field_types::TYPE_FIXED:
                    $config = field_types::decode_config($field->configdata ?? null);
                    $values[$field->id] = $config['fixedvalue'];
                    break;
                case field_types::TYPE_SELECT:
                    $config = field_types::decode_config($field->configdata ?? null);
                    if ($config['multiple'] && is_array($raw)) {
                        $values[$field->id] = json_encode(array_values($raw));
                    } else {
                        $values[$field->id] = (string) $raw;
                    }
                    break;
                case field_types::TYPE_CHECKBOX:
                    $values[$field->id] = !empty($raw) ? '1' : '0';
                    break;
                case field_types::TYPE_DATE:
                    // date_selector returns 0 when the "optional" box is unticked.
                    $values[$field->id] = (string) (int) $raw;
                    break;
                default:
                    $values[$field->id] = (string) $raw;
                    break;
            }
        }
        return $values;
    }

    /**
     * Build the set_data array from stored answers so the form shows saved values.
     *
     * @param array $answers fieldid => stored value
     * @param object[] $fields
     * @return array element name => value
     */
    public static function values_to_formdata(array $answers, array $fields): array {
        $data = [];
        foreach ($fields as $field) {
            $name = self::PREFIX . $field->id;
            if (!array_key_exists($field->id, $answers)) {
                continue;
            }
            $stored = $answers[$field->id];
            if ($field->type === field_types::TYPE_SELECT) {
                $config = field_types::decode_config($field->configdata ?? null);
                if ($config['multiple']) {
                    $decoded = json_decode((string) $stored, true);
                    $data[$name] = is_array($decoded) ? $decoded : [];
                    continue;
                }
            }
            $data[$name] = $stored;
        }
        return $data;
    }
}
