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

namespace local_games\external;

use local_games\content;

defined('MOODLE_INTERNAL') || die();

// Moodle 3.11 keeps the external-API base classes in lib/externallib.php and
// in the global namespace - there is no core_external namespace before 4.2.
// This file can be autoloaded before a web-service entry point has pulled that
// library in, so it asks for it itself.
global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * The material every game is built from, in one call.
 *
 * The banks are shared - six games read the same questions, four read the same
 * picture words - so they travel once and not with every game a child opens.
 * They only change when the language pack does, which is why the response
 * carries a revision an app can cache against.
 *
 * @package    local_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_content extends external_api {

    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'banks' => new external_multiple_structure(
                new external_value(PARAM_ALPHA, 'One of: words, shopitems, wordlist, quiz, truefalse, whoami, colours'),
                'Which banks to return. Omit or leave empty for all of them.',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Hand back the banks and the string bag.
     *
     * @param array $banks which banks the caller wants; empty means all
     * @return array
     */
    public static function execute(array $banks = []): array {
        global $CFG;

        ['banks' => $banks] = self::validate_parameters(self::execute_parameters(), ['banks' => $banks]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/games:play', $context);

        $wanted = static function (string $bank) use ($banks): bool {
            return !$banks || in_array($bank, $banks, true);
        };

        // A bank the caller did not ask for comes back empty rather than
        // missing: an app can read every field without checking which ones it
        // requested, and the response shape never changes.
        $out = [
            'lang'         => current_language(),
            'arabicdigits' => content::arabic_digits(),
            // Moodle's string revision. It moves whenever caches are purged -
            // which is when a language pack may have changed - so an app can
            // keep the banks it already has until this number does not match.
            'revision'     => (int) ($CFG->langrev ?? 1),
            'strings'      => [],
            'words'        => [],
            'shopitems'    => [],
            'wordlist'     => [],
            'quiz'         => [],
            'truefalse'    => [],
            'whoami'       => [],
            'colours'      => [],
        ];

        // The string bag always travels: it is what the game screens are
        // written in, and no app can draw a round without it.
        foreach (content::strings() as $key => $value) {
            $out['strings'][] = ['key' => $key, 'value' => $value];
        }

        if ($wanted('words')) {
            $out['words'] = content::words();
        }
        if ($wanted('shopitems')) {
            $out['shopitems'] = content::shopitems();
        }
        if ($wanted('wordlist')) {
            $out['wordlist'] = content::wordlist();
        }
        if ($wanted('quiz')) {
            $out['quiz'] = content::quiz();
        }
        if ($wanted('truefalse')) {
            $out['truefalse'] = content::truefalse();
        }
        if ($wanted('whoami')) {
            $out['whoami'] = content::whoami();
        }
        if ($wanted('colours')) {
            $out['colours'] = content::colours();
        }

        return $out;
    }

    /**
     * Return description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'lang'         => new external_value(PARAM_RAW, 'Language the banks came back in'),
            'arabicdigits' => new external_value(PARAM_BOOL, 'Show numbers in Arabic-Indic digits'),
            'revision'     => new external_value(PARAM_INT, 'Changes when the language pack may have; cache against it'),
            'strings'      => new external_multiple_structure(
                new external_single_structure([
                    'key'   => new external_value(PARAM_RAW, 'String key, without the js_ prefix'),
                    'value' => new external_value(PARAM_RAW, 'Text in the current language'),
                ]),
                'Every message the game screens are written in'
            ),
            'words' => new external_multiple_structure(
                new external_single_structure([
                    'word'  => new external_value(PARAM_TEXT, 'The word'),
                    'emoji' => new external_value(PARAM_TEXT, 'Its picture'),
                    'clue'  => new external_value(PARAM_TEXT, 'A one-line clue'),
                ]),
                'Picture words - used by the letters games, memory and the jigsaw'
            ),
            'shopitems' => new external_multiple_structure(
                new external_single_structure([
                    'emoji' => new external_value(PARAM_TEXT, 'Its picture'),
                    'name'  => new external_value(PARAM_TEXT, 'What it is called'),
                ]),
                'The shop shelf, for Math Shop'
            ),
            'wordlist' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'A word'),
                'The wider vocabulary Word Builder validates against'
            ),
            'quiz' => new external_multiple_structure(
                new external_single_structure([
                    'topic'    => new external_value(PARAM_TEXT, 'Wheel segment the question belongs to'),
                    'question' => new external_value(PARAM_TEXT, 'The question'),
                    'answer'   => new external_value(PARAM_TEXT, 'The right answer'),
                    'wrong'    => new external_multiple_structure(
                        new external_value(PARAM_TEXT, 'A wrong answer'),
                        'One to three wrong answers'
                    ),
                ]),
                'The question bank six games are built on'
            ),
            'truefalse' => new external_multiple_structure(
                new external_single_structure([
                    'text' => new external_value(PARAM_TEXT, 'The statement'),
                    'true' => new external_value(PARAM_BOOL, 'Whether it is true'),
                    'why'  => new external_value(PARAM_TEXT, 'The reason, shown after the answer'),
                ]),
                'Statements for True or False'
            ),
            'whoami' => new external_multiple_structure(
                new external_single_structure([
                    'answer' => new external_value(PARAM_TEXT, 'What is being described'),
                    'emoji'  => new external_value(PARAM_TEXT, 'Its picture, revealed with the answer'),
                    'clues'  => new external_multiple_structure(
                        new external_value(PARAM_TEXT, 'A clue'),
                        'Clues in the order they should be given'
                    ),
                ]),
                'Clue sets for Who Am I'
            ),
            'colours' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'Colour name in the current language'),
                    'hex'  => new external_value(PARAM_TEXT, 'Its hex value, e.g. #e04b4b'),
                ]),
                'Colours for Colour Challenge'
            ),
        ]);
    }
}
