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

namespace theme_nit\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * The Gear menu items box: a textarea that refuses a page line it could not use.
 *
 * gear_menu::parse() drops a page line with no link, because there is nothing
 * to open. Dropping it at render time means the administrator types a line,
 * saves, opens the gear and the row is simply not there - with no hint why
 * (the first report was a `|` forgotten before the link). So the box checks
 * the text on save and hands the line number and the line back as the error;
 * Moodle then keeps the typed text in the box, unsaved, until it is fixed.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gear_menu_setting extends \admin_setting_configtextarea {

    /**
     * Validate the typed text: core's checks first, then one message per line
     * gear_menu::problems() objects to.
     *
     * @param string $data the text as typed
     * @return true|string true when it may be saved, the error message otherwise
     */
    public function validate($data) {
        $valid = parent::validate($data);
        if ($valid !== true) {
            return $valid;
        }

        $messages = [];
        foreach (gear_menu::problems((string) $data) as $problem) {
            $messages[] = get_string('gearmenuerror' . $problem['problem'], 'theme_nit', (object) [
                'line' => $problem['line'],
                // Plain text: core escapes the message when it draws it under the box.
                'text' => $problem['text'],
                'word' => $problem['word'] ?? '',
            ]);
        }

        return empty($messages) ? true : implode(' ', $messages);
    }
}
