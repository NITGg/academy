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
 * The navbar gear menu, as the administrator configured it.
 *
 * The gear dropdown on the navigation bar is two groups, each a heading over a
 * few links: Navigation (My courses, Site administration) and Management (the
 * coupons, offers, subscriptions ... screens). Whether each group is shown,
 * what it is called and which pages it lists are three settings per group on
 * Site administration → Appearance → Advanced theme settings — a tick box, a
 * text box and a list of tick boxes — beside the two core navbar menus.
 *
 * The pages themselves are a fixed catalogue (pages()): every screen the menu
 * has ever carried, each with the URL it opens and the rule that says who may
 * see it. The rule is not the administrator's to change - a row is only ever
 * shown to a user who could open the page - so the settings page never shows
 * it. A page may be ticked in either group, or in both, or in neither.
 *
 * This class is the catalogue and the rule; it holds no state. What the
 * viewer's page needs - the active row, the rendered HTML - is the renderer's
 * business (theme_nit\output\core_renderer::navbar_gear_menu).
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

    /** @var string Rule: everyone, visitors included. */
    public const RULE_EVERYONE = '';

    /**
     * The two groups of the menu, in the order they are drawn.
     *
     * Each has the language string its heading falls back to when the
     * administrator leaves the name empty, and the pages ticked on a fresh
     * site - which is the menu the theme hard-coded before the settings
     * existed, so upgrading changes nothing until someone edits them.
     *
     * @return array key => ['heading' => [identifier, component], 'default' => list of page keys]
     */
    public static function groups(): array {
        return [
            'navigation' => [
                'heading' => ['navigation', 'core'],
                'default' => ['mycourses', 'siteadmin'],
            ],
            'management' => [
                'heading' => ['navmanagement', 'theme_nit'],
                'default' => ['managecoupons', 'manageoffers', 'managesubscriptions', 'managejobform',
                    'gallery', 'sitemedia', 'managecourses'],
            ],
        ];
    }

    /**
     * Every page the menu can carry, in the order they are drawn within a group.
     *
     * `component` is the plugin the page belongs to: a site that does not run
     * it has neither the page nor its strings, so such a row is left out of
     * the settings page and the menu alike (see installed_pages()).
     *
     * @return array key => ['label' => [identifier, component], 'url' => string, 'rule' => string, 'component' => string]
     */
    public static function pages(): array {
        return [
            'mycourses' => [
                'label' => ['mycourses', 'core'],
                'url' => '/my/courses.php',
                'rule' => self::RULE_LOGGEDIN,
                'component' => 'core',
            ],
            'siteadmin' => [
                'label' => ['administrationsite', 'core'],
                'url' => '/admin/search.php',
                'rule' => self::RULE_ADMIN,
                'component' => 'core',
            ],
            'managecoupons' => [
                'label' => ['managecoupons', 'local_nit_commerce'],
                'url' => '/local/nit_commerce/manage_coupons.php',
                'rule' => 'local/nit_commerce:managecoupons',
                'component' => 'local_nit_commerce',
            ],
            'manageoffers' => [
                'label' => ['manageoffers', 'local_nit_commerce'],
                'url' => '/local/nit_commerce/manage_offers.php',
                'rule' => 'local/nit_commerce:manageoffers',
                'component' => 'local_nit_commerce',
            ],
            'managesubscriptions' => [
                'label' => ['managesubscriptions', 'local_nit_subscriptions'],
                'url' => '/local/nit_subscriptions/manage_subscriptions.php',
                'rule' => 'local/nit_subscriptions:managesubscriptions',
                'component' => 'local_nit_subscriptions',
            ],
            'managejobform' => [
                'label' => ['managejobform', 'local_jobform'],
                'url' => '/local/jobform/manage.php',
                'rule' => 'local/jobform:manage',
                'component' => 'local_jobform',
            ],
            'gallery' => [
                'label' => ['navgallery', 'theme_nit'],
                'url' => '/theme/nit/gallery.php',
                'rule' => 'moodle/site:config',
                'component' => 'theme_nit',
            ],
            'sitemedia' => [
                'label' => ['pluginname', 'local_nit_media'],
                'url' => '/admin/settings.php?section=local_nit_media_settings',
                'rule' => 'moodle/site:config',
                'component' => 'local_nit_media',
            ],
            // Course purchases + the enrolment-source report (AC-4.10.5). Last:
            // it is the screen that gets *read* rather than edited, so it sits
            // after the things an administrator goes to the menu to change.
            'managecourses' => [
                'label' => ['managecourses', 'local_nit_subscriptions'],
                'url' => '/local/nit_subscriptions/manage_courses.php',
                'rule' => 'local/nit_subscriptions:managesubscriptions',
                'component' => 'local_nit_subscriptions',
            ],
        ];
    }

    /**
     * pages(), minus the ones whose plugin this site does not run.
     *
     * @return array same shape as pages()
     */
    public static function installed_pages(): array {
        return array_filter(self::pages(), function (array $page): bool {
            return $page['component'] === 'core'
                || \core_component::get_component_directory($page['component']) !== null;
        });
    }

    /**
     * The name of one of a group's three settings, as stored under theme_nit.
     *
     * @param string $group a key of groups()
     * @param string $what 'show' | 'name' | 'pages'
     * @return string
     */
    public static function setting_name(string $group, string $what): string {
        return 'gearmenu_' . $group . '_' . $what;
    }

    /**
     * The menu as configured: each group with its resolved heading and the
     * pages ticked for it, before any per-viewer filtering.
     *
     * A group switched off, or left with no page, is not returned. A setting
     * that was never saved (the site has not opened the settings page since the
     * upgrade) reads as its default, so a fresh site gets the menu it had.
     *
     * @return array list of ['key' => string, 'heading' => string, 'items' => list of ['key', 'url', 'rule', 'label' => [identifier, component]]]
     */
    public static function configured_groups(): array {
        $pages = self::installed_pages();
        $groups = [];

        foreach (self::groups() as $key => $group) {
            $show = get_config('theme_nit', self::setting_name($key, 'show'));
            if ($show !== false && !$show) {
                continue;
            }

            $ticked = get_config('theme_nit', self::setting_name($key, 'pages'));
            $ticked = $ticked === false ? $group['default'] : array_filter(explode(',', (string) $ticked));

            $items = [];
            foreach ($pages as $pagekey => $page) {
                if (in_array($pagekey, $ticked, true)) {
                    $items[] = ['key' => $pagekey] + $page;
                }
            }
            if (empty($items)) {
                continue;
            }

            $name = trim((string) get_config('theme_nit', self::setting_name($key, 'name')));
            $groups[] = [
                'key' => $key,
                'heading' => $name === ''
                    ? get_string($group['heading'][0], $group['heading'][1])
                    // The administrator's own text: format_string() resolves
                    // `{mlang}` spans and neutralises markup, and the template
                    // prints it unescaped, as it does core's own navigation text.
                    : format_string($name, true, ['context' => context_system::instance()]),
                'items' => $items,
            ];
        }

        return $groups;
    }

    /**
     * Does this viewer get to see a page with this rule?
     *
     * @param string $rule a page's rule: '' for everyone, RULE_LOGGEDIN, RULE_ADMIN, or a capability
     * @param moodle_page $page the page being rendered - the `admin` rule reads
     *                          its settings navigation, as core does
     * @return bool
     */
    public static function rule_allows(string $rule, moodle_page $page): bool {
        if ($rule === self::RULE_EVERYONE) {
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
     * A page's URL as a moodle_url: the catalogue writes paths relative to the site.
     *
     * @param string $url
     * @return moodle_url
     */
    public static function url(string $url): moodle_url {
        return new moodle_url($url);
    }
}
