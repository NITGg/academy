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
use local_jobform\phone;

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
        $fields = array_values($this->_customdata['fields'] ?? []);
        /** @var object[] $groups group records keyed by id, in display order */
        $groups = $this->_customdata['groups'] ?? [];
        $readonly = !empty($this->_customdata['readonly']);

        // Bucket fields by group id (an unknown/zero group id falls to "General").
        $bygroup = [];
        foreach ($fields as $field) {
            $gid = (int) ($field->groupid ?? 0);
            if (!$gid || !isset($groups[$gid])) {
                $gid = 0;
            }
            $bygroup[$gid][] = $field;
        }

        // Use headed sections only when at least one field actually sits in a group.
        $grouped = array_diff(array_keys($bygroup), [0]);
        $usesections = count($groups) > 0 && count($grouped) > 0;
        $headercount = 0;

        if ($usesections) {
            // Each defined group becomes a section (in group order); ungrouped last.
            foreach ($groups as $group) {
                if (empty($bygroup[$group->id])) {
                    continue;
                }
                $this->add_section_header($mform, $headercount++,
                    \local_jobform\mlang::resolve($group->name));
                foreach ($bygroup[$group->id] as $field) {
                    $this->add_field_element($mform, $field);
                }
            }
            if (!empty($bygroup[0])) {
                $this->add_section_header($mform, $headercount++,
                    get_string('generalsection', 'mod_jobform'));
                foreach ($bygroup[0] as $field) {
                    $this->add_field_element($mform, $field);
                }
            }
        } else {
            foreach ($fields as $field) {
                $this->add_field_element($mform, $field);
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
     * Add an expanded section header for a group.
     *
     * @param \MoodleQuickForm $mform
     * @param int $index unique header index
     * @param string $label resolved section title
     * @return void
     */
    protected function add_section_header($mform, int $index, string $label): void {
        $mform->addElement('header', 'jfgroup_' . $index, $label);
        $mform->setExpanded('jfgroup_' . $index, true);
    }

    /**
     * Add the input element (and required rule) for a single field.
     *
     * @param \MoodleQuickForm $mform
     * @param object $field
     * @return void
     */
    protected function add_field_element($mform, object $field): void {
        $name = self::PREFIX . $field->id;
        $label = \local_jobform\mlang::display($field->name);
        $config = field_types::decode_config($field->configdata ?? null);

        switch ($field->type) {
            case field_types::TYPE_NUMBER:
            case field_types::TYPE_EMAIL:
            case field_types::TYPE_URL:
                $mform->addElement('text', $name, $label);
                $mform->setType($name, PARAM_RAW_TRIMMED);
                break;

            case field_types::TYPE_PHONE:
                $this->add_phone_element($mform, $field, $name, $label);
                break;

            case field_types::TYPE_DATE:
                // A non-required date is "optional": Moodle adds an Enable ("تمكين")
                // checkbox so the student can leave it blank. A help button explains
                // what that checkbox is for.
                $optional = empty($field->required);
                $mform->addElement('date_selector', $name, $label, ['optional' => $optional]);
                if ($optional) {
                    $mform->addHelpButton($name, 'dateoptional', 'mod_jobform');
                }
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
                if ($config['multiple']) {
                    // A searchable "open to pick" control instead of a giant list box.
                    $mform->addElement('autocomplete', $name, $label, $options, [
                        'multiple' => true,
                        'noselectionstring' => get_string('choosedots'),
                    ]);
                } else {
                    $mform->addElement('select', $name, $label,
                        ['' => get_string('choosedots')] + $options);
                }
                break;

            case field_types::TYPE_FIXED:
                // Admin-set, read-only for the student (shown in their language).
                $fixed = \local_jobform\mlang::resolve($config['fixedvalue']);
                $mform->addElement('static', $name . '_static', $label, s($fixed));
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
        // Phone adds its own rule: it is a group, and a group takes the rule per
        // sub-element rather than as a whole.
        if (!empty($field->required)
                && !in_array($field->type,
                    [field_types::TYPE_FIXED, field_types::TYPE_CHECKBOX,
                        field_types::TYPE_DATE, field_types::TYPE_PHONE], true)) {
            $mform->addRule($name, get_string('required'), 'required', null, 'client');
        }
    }

    /**
     * The phone control: a country dialling code beside the national number.
     *
     * AC-4.20.4 - the dialling code is a control of its own, and it is generic:
     * every country Moodle knows is in the list, not a shortlist of the ones an
     * example named. The list, the codes and the per-country length rules come from
     * the sign-up phone field, so an applicant meets the same control in both
     * places; {@see \local_jobform\phone} is the adapter.
     *
     * @param \MoodleQuickForm $mform
     * @param object $field
     * @param string $name the element name
     * @param string $label the field label, already resolved to the current language
     * @return void
     */
    protected function add_phone_element($mform, object $field, string $name, string $label): void {
        $countries = phone::menu();
        if (!$countries) {
            // No dialling code source on this site (profilefield_phone is a sibling
            // plugin, not a dependency). The field stays the plain box it was rather
            // than rendering an empty select - required rule included, since the
            // caller skips its own for this type.
            $mform->addElement('text', $name, $label);
            $mform->setType($name, PARAM_RAW_TRIMMED);
            if (!empty($field->required)) {
                $mform->addRule($name, get_string('required'), 'required', null, 'client');
            }
            return;
        }

        // A plain select, not core's searchable autocomplete. The native control is
        // the one every platform renders well - iOS a wheel, Android a full-screen
        // list, both with the system's own type-ahead - and the labels lead with the
        // localised country name, so pressing "E" finds Egypt and "م" finds مصر. The
        // classes are profilefield_phone's on purpose: it is the same control, and
        // theme_nit already butts the two boxes together into one under them.
        $group = [
            $mform->createElement('select', 'country',
                get_string('phonecountry', 'local_jobform'), $countries,
                ['class' => 'profilefield-phone-country']),
            // dir="ltr" rather than setForceLtr(): a phone number always reads
            // left to right, and setForceLtr() looks the element up by name - a
            // grouped sub-element is not a top-level element, so that lookup raises
            // a PEAR error that is fatal on PHP 8.
            $mform->createElement('text', 'number', get_string('phone'),
                ['size' => 20, 'dir' => 'ltr', 'class' => 'profilefield-phone-number']),
        ];
        $mform->addGroup($group, $name, $label, ' ', true);
        $mform->setType($name . '[number]', PARAM_TEXT);

        // Preselect the applicant's own country when the site can place them, so the
        // common case is one box to fill rather than two.
        $mform->setDefault($name, ['country' => phone::default_country(), 'number' => '']);

        if (!empty($field->required)) {
            // Per sub-element, not on the group as a whole: a group's value is an
            // array, which the "required" rule would stringify rather than check.
            // Both halves carry the rule because QuickForm only marks the row itself
            // required - the asterisk the applicant scans for - when every element in
            // the group does (HTML_QuickForm::addGroupRule). The country is genuinely
            // required too; it is simply never empty, since one is preselected.
            $mform->addGroupRule($name, [
                'country' => [[get_string('errphonecountry', 'local_jobform'),
                    'required', null, 'client']],
                'number'  => [[get_string('required'), 'required', null, 'client']],
            ]);
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
                case field_types::TYPE_PHONE:
                    // Two controls, one answer: the country and the number are
                    // checked together, because the length a number may have is a
                    // fact about the country (AC-4.20.4). A site without the
                    // dialling code source falls back to the old flat text box, and
                    // to the old flat check with it.
                    if (is_array($value)) {
                        $error = phone::validate((string) ($value['country'] ?? ''),
                            (string) ($value['number'] ?? ''), !empty($field->required));
                        if ($error !== null) {
                            $errors[$name] = $error;
                        }
                    } else {
                        // Optional leading +, digits and common separators, with
                        // 7-15 actual digits (E.164-ish).
                        $digits = preg_replace('/\D+/', '', (string) $value);
                        if ($value !== '' && (!preg_match('/^\+?[0-9\s().-]+$/', $value)
                                || strlen($digits) < 7 || strlen($digits) > 15)) {
                            $errors[$name] = get_string('errornotphone', 'mod_jobform');
                        }
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
                case field_types::TYPE_PHONE:
                    // The two controls collapse into one "EG:1012345678" (AC-4.20.4).
                    $values[$field->id] = is_array($raw)
                        ? phone::compose((string) ($raw['country'] ?? ''),
                            (string) ($raw['number'] ?? ''))
                        : (string) $raw;
                    break;
                default:
                    $values[$field->id] = (string) $raw;
                    break;
            }
        }
        return $values;
    }

    /**
     * The values taken from the applicant's own account (AC-4.20.2).
     *
     * Returned separately from the saved answers so the caller can let anything
     * the applicant already saved win over the account value.
     *
     * @param object[] $fields
     * @param object $user the account of record (usually $USER)
     * @return array element name => value
     */
    public static function autofill_formdata(array $fields, object $user): array {
        $data = [];
        foreach ($fields as $field) {
            $source = field_types::autofill_source($field);
            if ($source === '') {
                continue;
            }
            $value = field_types::autofill_value($source, $user);
            if ($value === '') {
                continue;
            }
            $data[self::PREFIX . $field->id] = $value;
        }
        return $data;
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
            if ($field->type === field_types::TYPE_PHONE && phone::available()) {
                // Back into the two controls. A saved answer names its own country,
                // so the preselect is only for the halves an older answer never
                // recorded (AC-4.20.4).
                [$iso, $number] = phone::split((string) $stored);
                $data[$name] = [
                    'country' => $iso !== '' ? $iso : phone::default_country(),
                    'number'  => $number,
                ];
                continue;
            }
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
