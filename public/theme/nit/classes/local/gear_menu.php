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

use context_system;
use moodle_page;
use moodle_url;

/**
 * The navbar gear menu, as the administrator wrote it down.
 *
 * The gear dropdown on the navigation bar is a list of groups, each a heading
 * over a few links (Navigation: My courses, Site administration; Management:
 * Manage coupons, ...). Which groups there are, what they are called, which
 * pages sit under them and in what order is the administrator's to decide, so
 * the whole list is one setting - `theme_nit/gearmenuitems`, on Site
 * administration → Appearance → Advanced theme settings, beside the two core
 * menus (`custommenuitems`, `customusermenuitems`) that are written the same way.
 *
 * The syntax, line by line
 * ------------------------
 *   Heading                      a line without a leading dash starts a group
 *   -Label|/some/page.php|rule   a line with one is a page in the current group
 *
 * `Heading` and `Label` are either plain text (multilang `{mlang}` spans are
 * honoured) or a language string as `identifier,component` - `mycourses,core`
 * - exactly as the user menu setting on the same page spells them, so a site
 * that runs in two languages does not have to type both.
 *
 * `rule` says who sees the row and is optional:
 *   (empty)                   everyone, visitors included
 *   loggedin                  any logged-in user (the guest account excluded)
 *   admin                     whoever may open Site administration - the same
 *                             test core applies to that row
 *   local/plugin:capability   holders of that capability in the system context;
 *                             a capability the site does not have (the plugin is
 *                             not installed) hides the row rather than raising
 *
 * A page whose lines all fail their rule takes its heading with it, and a menu
 * with no group left in it shows only the Edit mode switch, if the viewer has
 * one; the renderer drops the gear altogether when there is nothing at all.
 *
 * This class is the parser and the rule; it holds no state. What the viewer's
 * page needs - the active row, the rendered HTML - is the renderer's business
 * (theme_nit\output\core_renderer::navbar_gear_menu).
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gear_menu {

    /** @var string Rule: any logged-in user, guest account excluded. */
    public const RULE_LOGGEDIN = 'loggedin';

    /** @var string Rule: anyone who may open Site administration. */
    public const RULE_ADMIN = 'admin';

    /**
     * The menu a fresh site gets: the groups and rows the theme hard-coded
     * before the setting existed, in the same order, so upgrading changes
     * nothing until an administrator edits the text.
     *
     * Rows for plugins the site may not run (`local_nit_commerce`, ...) are
     * safe to list: their capability is unknown on such a site, and an unknown
     * capability hides the row.
     *
     * @return string
     */
    public static function default_definition(): string {
        $lines = [
            'navigation,core',
            '-mycourses,core|/my/courses.php|' . self::RULE_LOGGEDIN,
            '-administrationsite,core|/admin/search.php|' . self::RULE_ADMIN,
            'navmanagement,theme_nit',
            '-managecoupons,local_nit_commerce|/local/nit_commerce/manage_coupons.php|local/nit_commerce:managecoupons',
            '-manageoffers,local_nit_commerce|/local/nit_commerce/manage_offers.php|local/nit_commerce:manageoffers',
            '-managesubscriptions,local_nit_subscriptions|/local/nit_subscriptions/manage_subscriptions.php'
                . '|local/nit_subscriptions:managesubscriptions',
            '-managejobform,local_jobform|/local/jobform/manage.php|local/jobform:manage',
            '-navgallery,theme_nit|/theme/nit/gallery.php|moodle/site:config',
            '-pluginname,local_nit_media|/admin/settings.php?section=local_nit_media_settings|moodle/site:config',
            // Course purchases + the enrolment-source report (AC-4.10.5). Last in
            // the group: it is the screen that gets *read* rather than edited, so
            // it sits after the things an administrator goes to the menu to change.
            '-managecourses,local_nit_subscriptions|/local/nit_subscriptions/manage_courses.php'
                . '|local/nit_subscriptions:managesubscriptions',
        ];
        return implode("\n", $lines);
    }

    /**
     * The definition in force: the setting when it has been saved, the default
     * until then. A setting saved empty is honoured as empty - the administrator
     * asked for no groups - which is why this is not a plain `?:`.
     *
     * @return string
     */
    public static function definition(): string {
        $text = get_config('theme_nit', 'gearmenuitems');
        if ($text === false) {
            return self::default_definition();
        }
        return (string) $text;
    }

    /**
     * Read a definition into groups.
     *
     * Blank lines are skipped. A page line before any heading opens a group
     * with no heading, so a menu that is one flat list still works. A page line
     * without a URL is dropped - there is nothing to link to - the way core
     * drops such a line from the user menu.
     *
     * @param string $text the setting text
     * @return array list of ['heading' => string, 'items' => list of ['label' => string, 'url' => string, 'rule' => string]]
     *               with labels and headings still raw (see label())
     */
    public static function parse(string $text): array {
        $groups = [];
        $current = null;

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if ($line[0] !== '-') {
                if ($current !== null) {
                    $groups[] = $current;
                }
                $current = ['heading' => $line, 'items' => []];
                continue;
            }

            $bits = array_map('trim', explode('|', ltrim($line, '-'), 3));
            $label = $bits[0];
            $url = $bits[1] ?? '';
            $rule = $bits[2] ?? '';
            if ($label === '' || $url === '') {
                continue;
            }

            if ($current === null) {
                $current = ['heading' => '', 'items' => []];
            }
            $current['items'][] = ['label' => $label, 'url' => $url, 'rule' => $rule];
        }

        if ($current !== null) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * A heading or label as the viewer should read it.
     *
     * `identifier,component` names a language string when such a string exists
     * (`mycourses,core`; `mycourses,` means core, as it does for the user menu).
     * Anything else is the administrator's own text, run through format_string()
     * so `{mlang}` spans resolve and markup is neutralised - the templates print
     * it unescaped, as they do core's own navigation text.
     *
     * @param string $raw
     * @return string
     */
    public static function label(string $raw): string {
        $bits = explode(',', $raw, 2);
        if (count($bits) === 2) {
            $identifier = clean_param(trim($bits[0]), PARAM_STRINGID);
            $component = clean_param(trim($bits[1]) ?: 'core', PARAM_COMPONENT);
            if ($identifier !== '' && $component !== ''
                    && get_string_manager()->string_exists($identifier, $component)) {
                return get_string($identifier, $component);
            }
        }

        return format_string($raw, true, ['context' => context_system::instance()]);
    }

    /**
     * Does this viewer get to see a row with this rule?
     *
     * @param string $rule the third column of a page line, '' for "everyone"
     * @param moodle_page $page the page being rendered - the `admin` rule reads
     *                          its settings navigation, as core does
     * @return bool
     */
    public static function rule_allows(string $rule, moodle_page $page): bool {
        $rule = trim($rule);
        if ($rule === '') {
            return true;
        }

        if ($rule === self::RULE_LOGGEDIN) {
            return isloggedin() && !isguestuser();
        }

        // Core's own test for the Site administration row
        // (\core\navigation\views\primary::get_site_admin_node): the row is
        // there when the settings navigation has an admin root, which it has
        // for anyone who may open any part of the admin tree.
        if ($rule === self::RULE_ADMIN) {
            $settingsnav = $page->settingsnav;
            $node = $settingsnav->find('siteadministration', \navigation_node::TYPE_SITE_ADMIN)
                ?: $settingsnav->find('root', \navigation_node::TYPE_SITE_ADMIN);
            return (bool) $node;
        }

        // A capability. Asked about first, because has_capability() on a name
        // the site never installed logs a developer warning on every page.
        if (get_capability_info($rule) === null) {
            return false;
        }
        return has_capability($rule, context_system::instance());
    }

    /**
     * The URL of a page line as a moodle_url: a path is taken relative to the
     * site, an absolute address is left alone.
     *
     * @param string $url the second column of a page line
     * @return moodle_url
     */
    public static function url(string $url): moodle_url {
        return new moodle_url(trim($url));
    }
}
