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

namespace local_nit_instructors\form;

use local_nit_instructors\profile;
use moodleform;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * The instructor's own edit form for their background.
 *
 * AC-4.5.12: "Qualifications, positions and certifications are entered as
 * repeating entries that can be added, edited, reordered and removed, not as a
 * single free-text block."
 *
 * `repeat_elements()` is Moodle's answer to the first three of those, and it is
 * used here rather than hand-rolled markup so that the "Add 3 more" button, the
 * numbering and the no-JavaScript fallback all behave the way they do everywhere
 * else in Moodle.
 *
 * Reordering and removing are the two it does not give us. Removing is emptying an
 * entry - {@see profile::save_draft()} drops blank rows - and reordering is the
 * order the boxes appear in, which is the order they are saved in. Both are said
 * plainly in the form's own help text, because neither is guessable.
 *
 * Every box is optional (AC-4.5.11). The form therefore has no required rules at
 * all, and an instructor who submits it empty has made a valid change.
 *
 * @package    local_nit_instructors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_form extends moodleform {

    /** @var int Entry slots added each time "add more" is pressed. */
    const REPEAT_STEP = 2;

    /** @var int Slots shown when an instructor has no entries of a kind yet. */
    const REPEAT_INITIAL = 1;

    /**
     * Build the form.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $counts = $this->_customdata['counts'] ?? [];

        $mform->addElement('header', 'backgroundheader',
            get_string('background', 'local_nit_instructors'));
        $mform->setExpanded('backgroundheader', true);

        $mform->addElement('static', 'bilingualnote', '',
            get_string('bilingualnote', 'local_nit_instructors'));

        // Specialty, one line per language (AC-4.5: max 120 characters each).
        $mform->addElement('text', 'specialtyen',
            get_string('specialty_en', 'local_nit_instructors'),
            ['maxlength' => profile::SPECIALTY_MAX, 'size' => 60]);
        $mform->setType('specialtyen', PARAM_TEXT);

        $mform->addElement('text', 'specialtyar',
            get_string('specialty_ar', 'local_nit_instructors'),
            ['maxlength' => profile::SPECIALTY_MAX, 'size' => 60, 'dir' => 'rtl']);
        $mform->setType('specialtyar', PARAM_TEXT);

        $mform->addElement('text', 'years',
            get_string('years', 'local_nit_instructors'), ['size' => 6]);
        $mform->setType('years', PARAM_INT);
        $mform->addHelpButton('years', 'years', 'local_nit_instructors');

        foreach (profile::entry_types() as $type) {
            $this->add_entry_group($type, (int) ($counts[$type] ?? 0));
        }

        // AC-4.5.13: derived, and shown here read-only so an instructor can see
        // what learners will see without being able to add to it.
        $mform->addElement('static', 'coursestaught',
            get_string('coursestaught', 'local_nit_instructors'),
            $this->_customdata['coursestaught'] ?? '');
        $mform->addHelpButton('coursestaught', 'coursestaught', 'local_nit_instructors');

        $this->add_action_buttons(true, get_string('submitforreview', 'local_nit_instructors'));
    }

    /**
     * One repeating group: qualifications, positions or certifications.
     *
     * @param string $type one of profile's TYPE_* constants
     * @param int $existing how many entries the instructor already has
     * @return void
     */
    protected function add_entry_group(string $type, int $existing): void {
        $mform = $this->_form;

        $mform->addElement('header', $type . 'header',
            get_string('type_' . $type, 'local_nit_instructors'));
        $mform->setExpanded($type . 'header', $existing > 0);

        $mform->addElement('static', $type . 'note', '',
            get_string('entrynote', 'local_nit_instructors'));

        $elements = [
            $mform->createElement('text', $type . '_titleen',
                get_string('entry_title_en', 'local_nit_instructors'), ['size' => 45]),
            $mform->createElement('text', $type . '_titlear',
                get_string('entry_title_ar', 'local_nit_instructors'), ['size' => 45, 'dir' => 'rtl']),
            $mform->createElement('text', $type . '_orgen',
                get_string('entry_org_en', 'local_nit_instructors'), ['size' => 45]),
            $mform->createElement('text', $type . '_orgar',
                get_string('entry_org_ar', 'local_nit_instructors'), ['size' => 45, 'dir' => 'rtl']),
            $mform->createElement('text', $type . '_perioden',
                get_string('entry_period_en', 'local_nit_instructors'), ['size' => 20]),
            $mform->createElement('text', $type . '_periodar',
                get_string('entry_period_ar', 'local_nit_instructors'), ['size' => 20, 'dir' => 'rtl']),
            $mform->createElement('html', '<hr class="my-3">'),
        ];

        $options = [];
        foreach (['titleen', 'titlear', 'orgen', 'orgar', 'perioden', 'periodar'] as $field) {
            $options[$type . '_' . $field] = ['type' => PARAM_TEXT];
        }

        // Always at least one empty slot, so adding a first entry needs no clicks.
        $repeats = max(self::REPEAT_INITIAL, $existing + 1);

        $this->repeat_elements($elements, $repeats, $options, $type . '_count',
            $type . '_add', self::REPEAT_STEP,
            get_string('addmore', 'local_nit_instructors'), true);
    }

    /**
     * Check the two values that have a range.
     *
     * Everything else is optional free text, so there is nothing to reject: an
     * empty form is a valid submission that clears the group.
     *
     * @param array $data submitted values
     * @param array $files
     * @return array element name => message
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $years = (int) ($data['years'] ?? 0);
        if ($years < 0 || $years > profile::YEARS_MAX) {
            $errors['years'] = get_string('yearsrange', 'local_nit_instructors', profile::YEARS_MAX);
        }

        foreach (['specialtyen', 'specialtyar'] as $field) {
            if (\core_text::strlen((string) ($data[$field] ?? '')) > profile::SPECIALTY_MAX) {
                $errors[$field] = get_string('specialtytoolong', 'local_nit_instructors',
                    profile::SPECIALTY_MAX);
            }
        }

        return $errors;
    }

    /**
     * Pull the repeating entries out of submitted data, in the order they appear.
     *
     * `repeat_elements()` returns each field as an array indexed by slot number, so
     * the entries have to be transposed back into a list of rows. The slot order is
     * the display order, which is what makes reordering work: move a value up the
     * form and it is saved higher up the list.
     *
     * @param stdClass $data the form's submitted data
     * @return array<string, array> type => list of value arrays
     */
    public static function extract_entries(\stdClass $data): array {
        $out = [];

        foreach (profile::entry_types() as $type) {
            $rows = [];
            $count = (int) ($data->{$type . '_count'} ?? 0);

            for ($i = 0; $i < $count; $i++) {
                $row = [];
                foreach (['titleen', 'titlear', 'orgen', 'orgar', 'perioden', 'periodar'] as $field) {
                    $values = $data->{$type . '_' . $field} ?? [];
                    $row[$field] = is_array($values) ? (string) ($values[$i] ?? '') : '';
                }
                $rows[] = $row;
            }

            $out[$type] = $rows;
        }

        return $out;
    }
}
