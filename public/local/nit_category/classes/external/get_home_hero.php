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

namespace local_nit_category\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_nit_category\home_hero;

defined('MOODLE_INTERNAL') || die();

/**
 * The front-page hero's words, for the app's home screen.
 *
 * "Learn Today / Advance Tomorrow" and the paragraph under it, as the site's front page
 * currently shows them - read from the pasted hero block by {@see home_hero}, so an
 * administrator who re-words the hero in the block editor has re-worded the app too.
 * The heading comes back as one entry per line so the app can colour the second line
 * the way the web page does (the accent words); `title` is the same lines joined, for
 * a client that wants one string.
 *
 * Already resolved for display: one language (the `lang` parameter, else the caller's
 * own), no markup, no {mlang} tags.
 *
 * Pre-login by design (`loginrequired => false` in db/services.php): the hero is the
 * first thing a visitor sees on the site, so the app needs it before anybody has a token.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_home_hero extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'lang'  => new external_value(PARAM_LANG, 'Display language, e.g. en or ar (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * The hero's words, in one language.
     *
     * @param string $lang display language, or '' for the caller's own
     * @param string $alang alias of $lang
     * @return array
     */
    public static function execute(string $lang = '', string $alang = ''): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['lang' => $lang, 'alang' => $alang]);

        // Not validate_context(): that calls require_login(), and this function is
        // reachable before anybody has logged in - the same choice, for the same reason,
        // as local_profilefields_get_footer. Nothing here is per-user or private.
        $PAGE->set_context(context_system::instance());

        // for_request(), not force_current_language(): the bare call pins the whole
        // session to that language (see local_nit_core\helper\lang).
        $chosen = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($chosen !== '') {
            \local_nit_core\helper\lang::for_request($chosen);
        }

        $hero = home_hero::resolved();

        return [
            'found'       => $hero['source'] !== '',
            'source'      => $hero['source'],
            'lang'        => current_language(),
            'headline'    => $hero['headline'],
            'title'       => implode(' ', $hero['headline']),
            'description' => $hero['description'],
            'warnings'    => [],
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'found'       => new external_value(PARAM_BOOL,
                'False when no hero could be read anywhere; the text fields are then empty.'),
            'source'      => new external_value(PARAM_ALPHA,
                '"block" when read from the live front-page block, "file" from the packaged copy, "" when not found.'),
            'lang'        => new external_value(PARAM_LANG, 'The language the text is in.'),
            'headline'    => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'One line of the heading, in page order.'),
                'The heading, one entry per line (e.g. "Learn Today", "Advance Tomorrow"). '
                . 'The web page paints the last line in the accent colour.'
            ),
            'title'       => new external_value(PARAM_TEXT, 'The heading lines joined with a space.'),
            'description' => new external_value(PARAM_TEXT, 'The paragraph under the heading.'),
            'warnings'    => new external_warnings(),
        ]);
    }
}
