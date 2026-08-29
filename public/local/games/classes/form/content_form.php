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

namespace local_games\form;

use local_games\registry;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Add or edit one row of one game's content.
 *
 * One form serves every game. It reads the shape of a row out of
 * {@see registry::shapes()} and draws one element per field, so a question, a
 * colour and an arithmetic rule are all the same kind of edit as far as this
 * class is concerned - which is why there is one form and not ten.
 *
 * Translatable fields are submitted under `gametext[...]`. That prefix is what
 * local_nit_mlang recognises, and it is why those fields appear as one input per
 * installed language while `topic`, `hex` and the numbers do not: those hold a
 * key or a number, which is the same in every language.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_form extends \moodleform {

    /** @var string The submit-name prefix local_nit_mlang splits per language. */
    const MLANG_GROUP = 'gametext';

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        /** @var string $gameid */
        $gameid = $this->_customdata['gameid'];

        $mform->addElement('hidden', 'id', $gameid);
        $mform->setType('id', PARAM_ALPHANUMEXT);
        $mform->addElement('hidden', 'rowid', 0);
        $mform->setType('rowid', PARAM_INT);

        foreach (registry::fields_for($gameid) as $field => $definition) {
            $label = get_string('field_' . $field, 'local_games');
            $element = self::element_name($field, $definition);

            switch ($definition['type']) {
                case 'select':
                    $options = [];
                    foreach (registry::options($definition['options']) as $value) {
                        $options[$value] = get_string(
                            'option_' . $definition['options'] . '_' . $value, 'local_games');
                    }
                    $mform->addElement('select', $element, $label, $options);
                    break;

                case 'bool':
                    $mform->addElement('select', $element, $label, [
                        '1' => get_string('istrue_yes', 'local_games'),
                        '0' => get_string('istrue_no', 'local_games'),
                    ]);
                    $mform->setDefault($element, '1');
                    break;

                case 'int':
                    $mform->addElement('text', $element, $label, ['size' => 6]);
                    $mform->setType($element, PARAM_INT);
                    if (isset($definition['default'])) {
                        $mform->setDefault($element, $definition['default']);
                    }
                    break;

                case 'emoji':
                    $mform->addElement('text', $element, $label, ['size' => 6]);
                    $mform->setType($element, PARAM_TEXT);
                    break;

                case 'hex':
                    $mform->addElement('text', $element, $label, ['size' => 12]);
                    $mform->setType($element, PARAM_TEXT);
                    break;

                default:
                    $mform->addElement('text', $element, $label, ['size' => 60]);
                    $mform->setType($element, PARAM_TEXT);
                    break;
            }

            if (get_string_manager()->string_exists('field_' . $field . '_help', 'local_games')) {
                $mform->addHelpButton($element, 'field_' . $field, 'local_games');
            }
            if (!empty($definition['required'])) {
                $mform->addRule($element, get_string('required'), 'required', null, 'client');
            }
        }

        $this->add_action_buttons();
    }

    /**
     * The submit name of one field.
     *
     * @param string $field field name inside the shape
     * @param array $definition the field definition
     * @return string
     */
    public static function element_name(string $field, array $definition): string {
        return empty($definition['translatable']) ? $field : self::MLANG_GROUP . '[' . $field . ']';
    }

    /**
     * Turn the submitted form into the row to store.
     *
     * @param \stdClass $data submitted values
     * @param string $gameid game slug
     * @return array field name => value
     */
    public static function to_row(\stdClass $data, string $gameid): array {
        $group = (array) ($data->{self::MLANG_GROUP} ?? []);

        $row = [];
        foreach (registry::fields_for($gameid) as $field => $definition) {
            $row[$field] = empty($definition['translatable'])
                ? trim((string) ($data->$field ?? ''))
                : trim((string) ($group[$field] ?? ''));
        }

        return $row;
    }

    /**
     * Spread a stored row back across the form's elements.
     *
     * @param array $row field name => value
     * @param string $gameid game slug
     * @return array
     */
    public static function to_data(array $row, string $gameid): array {
        $data = [];
        $group = [];

        foreach (registry::fields_for($gameid) as $field => $definition) {
            if (empty($definition['translatable'])) {
                $data[$field] = $row[$field] ?? '';
            } else {
                $group[$field] = $row[$field] ?? '';
            }
        }

        if ($group) {
            $data[self::MLANG_GROUP] = $group;
        }

        return $data;
    }

    /**
     * Server-side validation.
     *
     * @param array $data submitted values
     * @param array $files submitted files
     * @return array errors keyed by element name
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $gameid = $this->_customdata['gameid'];
        $fields = registry::fields_for($gameid);
        $group = (array) ($data[self::MLANG_GROUP] ?? []);

        foreach ($fields as $field => $definition) {
            $element = self::element_name($field, $definition);
            $value = empty($definition['translatable'])
                ? trim((string) ($data[$field] ?? ''))
                : trim((string) ($group[$field] ?? ''));

            if (!empty($definition['required']) && $value === '') {
                $errors[$element] = get_string('required');
                continue;
            }

            if ($definition['type'] === 'hex' && $value !== ''
                    && !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                $errors[$element] = get_string('errorbadhex', 'local_games');
            }
        }

        // A question whose right answer is also one of its wrong answers puts two
        // correct buttons on screen and marks the child wrong for pressing one.
        if (isset($group['answer'])) {
            $answer = trim((string) $group['answer']);
            foreach (['wrong1', 'wrong2', 'wrong3'] as $field) {
                if (!isset($fields[$field])) {
                    continue;
                }
                if ($answer !== '' && trim((string) ($group[$field] ?? '')) === $answer) {
                    $errors[self::MLANG_GROUP . '[' . $field . ']'] =
                        get_string('errorwrongisright', 'local_games');
                }
            }
        }

        // A range that runs backwards produces no numbers at all, and the game
        // would sit there with nothing to ask.
        foreach ([['mina', 'maxa'], ['minb', 'maxb'], ['minn', 'maxn']] as [$min, $max]) {
            if (!isset($fields[$min], $fields[$max])) {
                continue;
            }
            if ((int) ($data[$min] ?? 0) > (int) ($data[$max] ?? 0)) {
                $errors[$max] = get_string('errorrangebackwards', 'local_games');
            }
        }

        return $errors;
    }
}
