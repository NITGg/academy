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
 * What an administrator may change about one game's card.
 *
 * The name and description are single fields. They are translatable, so
 * local_nit_mlang draws one input per installed language over each of them and
 * composes the stored value itself - the same treatment a course name gets, and
 * the reason this form carries no language selector of its own.
 *
 * Leaving a name empty is a real answer: it means "use the name the corner ships
 * with", so the shipped text is offered as a hint rather than filled in.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class game_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;

        /** @var string $gameid */
        $gameid = $this->_customdata['gameid'];
        $key = registry::key($gameid);

        $mform->addElement('hidden', 'id', $gameid);
        $mform->setType('id', PARAM_ALPHANUMEXT);

        $mform->addElement('text', 'name', get_string('gamename', 'local_games'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addHelpButton('name', 'gamename', 'local_games');
        $mform->addElement('static', 'namedefault', '', get_string('shippedvalue', 'local_games',
            s(get_string('game_' . $key, 'local_games'))));

        $mform->addElement('text', 'description', get_string('gamedescription', 'local_games'), ['size' => 60]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addHelpButton('description', 'gamedescription', 'local_games');
        $mform->addElement('static', 'descdefault', '', get_string('shippedvalue', 'local_games',
            s(get_string('gamedesc_' . $key, 'local_games'))));

        $mform->addElement('advcheckbox', 'enabled', get_string('gameenabled', 'local_games'));
        $mform->addHelpButton('enabled', 'gameenabled', 'local_games');
        $mform->setDefault('enabled', 1);

        $default = registry::get_defaults()[$gameid] ?? ['level' => 1];
        $levels = [0 => get_string('leveldefault', 'local_games', str_repeat('⭐', (int) $default['level']))];
        for ($star = 1; $star <= 3; $star++) {
            $levels[$star] = str_repeat('⭐', $star);
        }
        $mform->addElement('select', 'level', get_string('gamelevel', 'local_games'), $levels);
        $mform->addHelpButton('level', 'gamelevel', 'local_games');
        $mform->setDefault('level', 0);

        $mform->addElement('text', 'sortorder', get_string('gamesortorder', 'local_games'), ['size' => 6]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->addHelpButton('sortorder', 'gamesortorder', 'local_games');
        $mform->setDefault('sortorder', 0);

        $this->add_action_buttons();
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

        if (isset($data['sortorder']) && (int) $data['sortorder'] < 0) {
            $errors['sortorder'] = get_string('errornegativeorder', 'local_games');
        }

        // What is stored is the composed multilingual value, which is longer than
        // anything typed into one language's input - so the length that matters
        // is the composed one.
        if (!empty($data['name']) && \core_text::strlen($data['name']) > 1000) {
            $errors['name'] = get_string('errornametoolong', 'local_games');
        }

        return $errors;
    }

    /**
     * The stored values, ready for the form.
     *
     * @param \stdClass|null $override the local_games_game row, or null
     * @return array
     */
    public static function to_data(?\stdClass $override): array {
        return [
            'name'        => $override->name ?? '',
            'description' => $override->description ?? '',
            'enabled'     => $override !== null ? (int) $override->enabled : 1,
            'level'       => $override !== null ? (int) $override->level : 0,
            'sortorder'   => $override !== null ? (int) $override->sortorder : 0,
        ];
    }
}
