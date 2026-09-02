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

namespace local_profilefields\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_profilefields\staticpages;

defined('MOODLE_INTERNAL') || die();

/**
 * The site's static pages, as a menu (AC-4.21.1).
 *
 * What the app needs to build its "More" screen: which pages exist, what each is
 * called in the language being read, and where each one is. The text itself is one
 * call per page ({@see get_static_page}), because a menu does not need six bodies.
 *
 * Pre-login by design, like get_footer: these are the pages a visitor reads before
 * deciding to create an account, so the app has to be able to list them on its own
 * public screens.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_static_pages extends external_api {

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
     * The published pages, in display order.
     *
     * @param string $lang display language, or '' for the caller's own
     * @param string $alang alias of $lang
     * @return array
     */
    public static function execute(string $lang = '', string $alang = ''): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['lang' => $lang, 'alang' => $alang]);

        // Not validate_context(): that calls require_login(), and this is reachable
        // before anybody has a token. Nothing here is per-user - it is the same
        // menu every visitor sees.
        $context = context_system::instance();
        $PAGE->set_context($context);

        // for_request(), not force_current_language(): the bare call writes
        // $SESSION->forcelang, which outranks the site's own ?lang= switcher for
        // the rest of the session. One app fetch asking for English would pin a
        // learner's browser session to English until they logged out.
        $chosen = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($chosen !== '') {
            \local_nit_core\helper\lang::for_request($chosen);
        }

        $pages = [];
        foreach (staticpages::menu() as $item) {
            $pages[] = [
                'slug'  => $item['slug'],
                'kind'  => staticpages::kind($item['slug']),
                'title' => format_string($item['title'], true, ['context' => $context, 'escape' => false]),
                'url'   => $item['url'],
            ];
        }

        return ['pages' => $pages, 'warnings' => []];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'pages' => new external_multiple_structure(
                new external_single_structure([
                    'slug'  => new external_value(PARAM_ALPHA,
                        'Machine name: about, contact, terms, privacy, refund or faq. Pass this to '
                        . 'local_profilefields_get_static_page.'),
                    'kind'  => new external_value(PARAM_ALPHA,
                        'How the page is built: content, contact, policy or faq. A client that draws its own '
                        . 'contact or FAQ screen should branch on this rather than on the slug.'),
                    'title' => new external_value(PARAM_TEXT, 'The page name in the requested language'),
                    'url'   => new external_value(PARAM_URL, 'The page on the web site, for a browser view'),
                ]), 'The published pages, in display order. An unpublished page is absent.'
            ),
            'warnings' => new external_warnings(),
        ]);
    }
}
