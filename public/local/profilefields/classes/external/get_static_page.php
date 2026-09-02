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
 * One static page, as data (AC-4.21).
 *
 * The same answer the web page renders, minus the markup around it: the body as
 * HTML, and for the two pages that are more than prose, the parts they are made of
 * as lists the app can lay out itself - the contact rows and social links of the
 * Contact page, the questions and answers of the FAQ.
 *
 * Everything is resolved to one language before it is returned. A page an
 * administrator has only written in Arabic comes back in Arabic even when English
 * was asked for, which is the same fallback the web site uses and better than an
 * empty screen.
 *
 * A legal page returns whichever tool_policy document is mapped to it, so the app
 * shows the text the learner agreed to on sign-up rather than a second copy of it.
 * `policyversionid` is included for a client that wants to record or check an
 * acceptance against that exact revision.
 *
 * Pre-login by design, like get_footer and get_static_pages.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_static_page extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'page'  => new external_value(PARAM_ALPHA,
                'Which page: about, contact, terms, privacy, refund or faq'),
            'lang'  => new external_value(PARAM_LANG, 'Display language, e.g. en or ar (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * One page's content.
     *
     * @param string $page the slug
     * @param string $lang display language, or '' for the caller's own
     * @param string $alang alias of $lang
     * @return array
     */
    public static function execute(string $page, string $lang = '', string $alang = ''): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(),
            ['page' => $page, 'lang' => $lang, 'alang' => $alang]);

        $context = context_system::instance();
        $PAGE->set_context($context);

        $slug = $params['page'];
        if (!staticpages::exists($slug)) {
            throw new \invalid_parameter_exception(
                get_string('staticpageunknown', 'local_profilefields', s($slug)));
        }

        // for_request(), not force_current_language(): see get_static_pages. The
        // bare call pins the whole session to the language one request asked for.
        $chosen = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($chosen !== '') {
            \local_nit_core\helper\lang::for_request($chosen);
        }

        // An unpublished page is not an error - the app hides the entry, the way it
        // hides a switched-off footer - but it must not hand out the text either.
        if (!staticpages::enabled($slug)) {
            return self::blank($slug);
        }

        $view = staticpages::view($slug);
        $opts = ['context' => $context, 'escape' => false];

        $contact = [];
        foreach ($view['contact'] as $row) {
            $contact[] = [
                'key'  => $row['key'],
                'icon' => $row['icon'],
                'text' => format_string($row['text'], true, $opts),
                'url'  => $row['url'],
            ];
        }

        $social = [];
        foreach ($view['social'] as $item) {
            $social[] = [
                'network' => $item['network'],
                'name'    => $item['name'],
                'icon'    => $item['icon'],
                'url'     => $item['url'],
            ];
        }

        $faq = [];
        foreach ($view['faq'] as $item) {
            $faq[] = [
                'id'       => $item['id'],
                'question' => format_string($item['question'], true, $opts),
                'answer'   => $item['answer'],
            ];
        }

        $version = staticpages::policy_version($slug);

        return [
            'slug'            => $slug,
            'kind'            => $view['kind'],
            'published'       => true,
            'title'           => format_string($view['title'], true, $opts),
            'content'         => $view['content'],
            'url'             => staticpages::url($slug)->out(false),
            'policyname'      => $view['policyname'],
            'policyversionid' => $version ? (int) $version->id : 0,
            'contact'         => $contact,
            'social'          => $social,
            'mapembed'        => $view['mapembed'],
            'maplink'         => $view['maplink'],
            'faq'             => $faq,
            'warnings'        => [],
        ];
    }

    /**
     * The answer for a page that exists but is not published.
     *
     * The whole structure, empty, rather than an exception: a client should be able
     * to ask for any slug it was given and get a shape it can render, not have to
     * guard every call.
     *
     * @param string $slug
     * @return array
     */
    protected static function blank(string $slug): array {
        return [
            'slug'            => $slug,
            'kind'            => staticpages::kind($slug),
            'published'       => false,
            'title'           => '',
            'content'         => '',
            'url'             => staticpages::url($slug)->out(false),
            'policyname'      => '',
            'policyversionid' => 0,
            'contact'         => [],
            'social'          => [],
            'mapembed'        => '',
            'maplink'         => '',
            'faq'             => [],
            'warnings'        => [],
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'slug' => new external_value(PARAM_ALPHA, 'The page asked for'),
            'kind' => new external_value(PARAM_ALPHA,
                'How the page is built: content, contact, policy or faq'),
            'published' => new external_value(PARAM_BOOL,
                'False when the page is switched off. Everything below is then empty; hide the entry.'),
            'title'   => new external_value(PARAM_TEXT, 'The page name in the requested language'),
            'content' => new external_value(PARAM_RAW,
                'The body as HTML, already formatted and with its image URLs resolved. Empty on a page that is '
                . 'only its lists (a FAQ with no introduction, for instance).'),
            'url' => new external_value(PARAM_URL, 'The page on the web site, for a browser view or a share link'),
            'policyname' => new external_value(PARAM_TEXT,
                'On a legal page, the name of the tool_policy document being shown. Empty otherwise.'),
            'policyversionid' => new external_value(PARAM_INT,
                'On a legal page, the id of the policy version the content came from - the same id '
                . 'local_profilefields_get_policy_documents reports. 0 when the page is not showing a document.'),
            'contact' => new external_multiple_structure(
                new external_single_structure([
                    'key'  => new external_value(PARAM_ALPHA, 'address, phone, hours or email'),
                    'icon' => new external_value(PARAM_TEXT, 'FontAwesome 6 classes for the row icon'),
                    'text' => new external_value(PARAM_TEXT, 'The line to show'),
                    'url'  => new external_value(PARAM_RAW,
                        'A mailto: or tel: link, or empty when the row is not a link'),
                ]), 'Contact page only: the same rows as the site footer, in display order.', VALUE_DEFAULT, []
            ),
            'social' => new external_multiple_structure(
                new external_single_structure([
                    'network' => new external_value(PARAM_ALPHA, 'Machine name of the network'),
                    'name'    => new external_value(PARAM_TEXT, 'The brand\'s own name, for a label'),
                    'icon'    => new external_value(PARAM_TEXT, 'FontAwesome 6 brand classes'),
                    'url'     => new external_value(PARAM_URL, 'The profile to open'),
                ]), 'Contact page only: the social links, in display order.', VALUE_DEFAULT, []
            ),
            'mapembed' => new external_value(PARAM_RAW,
                'Contact page only: an embeddable map URL, or empty. A native client should prefer its own map '
                . 'and use maplink instead.'),
            'maplink' => new external_value(PARAM_RAW,
                'Contact page only: the share link for the location, for opening the device\'s map app. May be empty.'),
            'faq' => new external_multiple_structure(
                new external_single_structure([
                    'id'       => new external_value(PARAM_INT, 'Stable id of the question'),
                    'question' => new external_value(PARAM_TEXT, 'The question, in the requested language'),
                    'answer'   => new external_value(PARAM_RAW, 'The answer as formatted HTML'),
                ]), 'FAQ page only: the questions, in display order. Hidden questions are absent.', VALUE_DEFAULT, []
            ),
            'warnings' => new external_warnings(),
        ]);
    }
}
