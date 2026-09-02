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
use local_profilefields\footer;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * The site footer, as data - what the Footer tab of the Site pages manager says.
 *
 * The web site draws this band from {@see footer::config()} through theme_nit;
 * an app that draws its own screens wants the same answer without the markup, so
 * that an administrator who edits the contact number, adds a link or switches the
 * footer off at
 * `/local/profilefields/manage.php?tab=footer` changes the app too, with no
 * release. There is one editor and one source of truth; this is a second reader
 * of it, not a second copy.
 *
 * Everything is already resolved for display: the prose comes back in one
 * language (the `lang` parameter, else the caller's own), every URL is absolute,
 * and rows an administrator left empty are simply absent rather than present and
 * blank. The FontAwesome class of each icon is included for a client that can
 * render it; a client that ships its own icon set should branch on the machine
 * `key` / `network` beside it instead.
 *
 * Pre-login by design (`loginrequired => false` in db/services.php): the footer
 * shows on the site's public pages, so the app needs it on its own public
 * screens, before anybody has a token.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_footer extends external_api {

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
     * The footer's content, in one language.
     *
     * @param string $lang display language, or '' for the caller's own
     * @param string $alang alias of $lang
     * @return array
     */
    public static function execute(string $lang = '', string $alang = ''): array {
        global $PAGE, $SITE;

        $params = self::validate_parameters(self::execute_parameters(), ['lang' => $lang, 'alang' => $alang]);

        // Not validate_context(): that calls require_login(), and this function is
        // reachable before anybody has logged in. The same choice, for the same
        // reason, as get_policy_documents. Nothing here is per-user or private -
        // it is the band at the bottom of every public page.
        $context = context_system::instance();
        $PAGE->set_context($context);

        // Before any string is read: footer::text() picks its language off
        // current_language(), and so does format_string()'s multilang filter.
        //
        // for_request(), not force_current_language(): the bare call writes
        // $SESSION->forcelang, which outranks the site's own ?lang= switcher for
        // the rest of the session, so one app fetch asking for English would pin
        // a learner's browser session to English until they logged out.
        $chosen = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($chosen !== '') {
            \local_nit_core\helper\lang::for_request($chosen);
        }

        $data = footer::config();

        // Switched off is an answer, not an error: the app hides its footer. The
        // rest of the structure still comes back, empty, so a client never has to
        // guard every field on `enabled`.
        if (empty($data['enabled'])) {
            return [
                'enabled'        => false,
                'contactheading' => '',
                'contact'        => [],
                'columns'        => [],
                'social'         => [],
                'logourl'        => '',
                'sitename'       => format_string($SITE->fullname, true, ['context' => $context, 'escape' => false]),
                'copyright'      => '',
                'warnings'       => [],
            ];
        }

        $opts = ['context' => $context];

        $contact = [];
        foreach ($data['contact']['rows'] as $row) {
            $contact[] = [
                'key'  => $row['key'],
                'icon' => $row['icon'],
                'text' => format_string($row['text'], true, $opts + ['escape' => false]),
                // 'mailto:' / 'tel:' on the two rows worth tapping, '' on the rest.
                'url'  => $row['url'],
            ];
        }

        $columns = [];
        foreach ($data['columns'] as $column) {
            $links = [];
            foreach ($column['links'] as $link) {
                $links[] = [
                    'label' => format_string($link['label'], true, $opts + ['escape' => false]),
                    // Stored values may be site-relative ('/course/'); out() makes
                    // them absolute so the app never has to know the wwwroot.
                    'url'   => (new moodle_url($link['url']))->out(false),
                ];
            }
            $columns[] = [
                'key'     => $column['key'],
                'heading' => format_string($column['heading'], true, $opts + ['escape' => false]),
                'links'   => $links,
            ];
        }

        $social = [];
        foreach ($data['social'] as $item) {
            $social[] = [
                'network' => $item['network'],
                'name'    => $item['name'],
                'icon'    => $item['icon'],
                'url'     => $item['url'],
            ];
        }

        return [
            'enabled'        => true,
            'contactheading' => format_string($data['contact']['heading'], true, $opts + ['escape' => false]),
            'contact'        => $contact,
            'columns'        => $columns,
            'social'         => $social,
            'logourl'        => self::logo_url(),
            'sitename'       => format_string($SITE->fullname, true, $opts + ['escape' => false]),
            'copyright'      => format_string($data['copyright'], true, $opts + ['escape' => false]),
            'warnings'       => [],
        ];
    }

    /**
     * The logo the footer shows, resolved the way theme_nit resolves it.
     *
     * The compact site logo first, then the full one, then the packaged mark - so
     * the app shows the same image as the web page, and an administrator who
     * replaces the site logo has replaced this one too.
     *
     * @return string absolute URL, never empty
     */
    protected static function logo_url(): string {
        global $PAGE;

        $output = $PAGE->get_renderer('core');

        $url = $output->get_compact_logo_url(null, 200);
        if (empty($url)) {
            $url = $output->get_logo_url(null, 200);
        }

        return !empty($url)
            ? $url->out(false)
            : (new moodle_url('/theme/nit/pix/footer-logo.png'))->out(false);
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL,
                'Whether the site shows a footer at all. False means draw nothing; the lists below are then empty.'),
            'contactheading' => new external_value(PARAM_TEXT, 'Heading of the contact column, may be empty'),
            'contact' => new external_multiple_structure(
                new external_single_structure([
                    'key'  => new external_value(PARAM_ALPHA, 'Which row this is: address, phone, hours or email'),
                    'icon' => new external_value(PARAM_TEXT, 'FontAwesome 6 classes for the row icon'),
                    'text' => new external_value(PARAM_TEXT, 'The line to show'),
                    'url'  => new external_value(PARAM_RAW,
                        'Where tapping the row goes: a mailto: or tel: link, or empty when the row is not a link'),
                ]), 'The contact rows, in display order. Rows the administrator left empty are absent.'
            ),
            'columns' => new external_multiple_structure(
                new external_single_structure([
                    'key'     => new external_value(PARAM_ALPHANUM, 'Which column this is: col2 or col3'),
                    'heading' => new external_value(PARAM_TEXT, 'Column heading, may be empty'),
                    'links'   => new external_multiple_structure(
                        new external_single_structure([
                            'label' => new external_value(PARAM_TEXT, 'Link text'),
                            'url'   => new external_value(PARAM_URL, 'Absolute URL to open'),
                        ]), 'The links of this column, in display order.'
                    ),
                ]), 'The link columns, in display order. An empty column is absent.'
            ),
            'social' => new external_multiple_structure(
                new external_single_structure([
                    'network' => new external_value(PARAM_ALPHA,
                        'Machine name: facebook, instagram, linkedin, twitter, youtube, tiktok, whatsapp or telegram'),
                    'name'    => new external_value(PARAM_TEXT, 'The brand\'s own name, for a label or aria-label'),
                    'icon'    => new external_value(PARAM_TEXT, 'FontAwesome 6 brand classes for the icon'),
                    'url'     => new external_value(PARAM_URL, 'The profile to open'),
                ]), 'The social links, in display order. A network with no URL set is absent.'
            ),
            'logourl'   => new external_value(PARAM_URL, 'The site logo the footer shows'),
            'sitename'  => new external_value(PARAM_TEXT, 'Site full name, shown beside the logo'),
            'copyright' => new external_value(PARAM_TEXT, 'The copyright line, with the year already substituted in'),
            'warnings'  => new external_warnings(),
        ]);
    }
}
