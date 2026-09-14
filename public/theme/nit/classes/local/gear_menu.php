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
 * Manage coupons, ...). Which groups there are, what they are called in each
 * language, which pages sit under them and in what order is one setting -
 * `theme_nit/gearmenuitems`, on Site administration → Appearance → Advanced
 * theme settings, written the way the Custom menu items box beside it is:
 *
 *   English name|Arabic name              a line without a dash starts a group
 *   -English name|Arabic name|/link.php   a line with one is a page in it
 *
 * The Arabic name may be left out (`-English name|/link.php`): the one name
 * then serves both languages. A viewer whose interface is Arabic reads the
 * Arabic column, everyone else the English one; a column left empty falls back
 * to the other. Nothing else has to be typed: who may see a link is decided
 * here, from the link itself (see rule_for()), because a "Manage coupons" row
 * that a student can see is a broken link, and the administrator should not
 * have to know the capability names to avoid that.
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

    /** @var string Rule: everyone, visitors included. */
    public const RULE_EVERYONE = '';

    /** @var string Rule: any logged-in user, guest account excluded. */
    public const RULE_LOGGEDIN = 'loggedin';

    /** @var string Rule: anyone who may open Site administration. */
    public const RULE_ADMIN = 'admin';

    /**
     * The screens the theme knows, keyed by the path (and, for a settings
     * section, the `section` parameter) a line would name them by.
     *
     * Two jobs. The rule is what gates the row when the administrator links to
     * that page — the same capability the page itself requires. The label is
     * how default_definition() writes the shipped menu: from the language
     * packs, so the default text carries the real English and Arabic names.
     *
     * @return array list of ['path' => string, 'section' => ?string, 'rule' => string,
     *                        'label' => [identifier, component], 'component' => string]
     */
    public static function catalogue(): array {
        return [
            'mycourses' => ['path' => '/my/courses.php', 'section' => null, 'rule' => self::RULE_LOGGEDIN,
                'label' => ['mycourses', 'core'], 'component' => 'core'],
            'dashboard' => ['path' => '/my/', 'section' => null, 'rule' => self::RULE_LOGGEDIN,
                'label' => ['myhome', 'core'], 'component' => 'core'],
            'siteadmin' => ['path' => '/admin/search.php', 'section' => null, 'rule' => self::RULE_ADMIN,
                'label' => ['administrationsite', 'core'], 'component' => 'core'],
            'managecoupons' => ['path' => '/local/nit_commerce/manage_coupons.php', 'section' => null,
                'rule' => 'local/nit_commerce:managecoupons',
                'label' => ['managecoupons', 'local_nit_commerce'], 'component' => 'local_nit_commerce'],
            'manageoffers' => ['path' => '/local/nit_commerce/manage_offers.php', 'section' => null,
                'rule' => 'local/nit_commerce:manageoffers',
                'label' => ['manageoffers', 'local_nit_commerce'], 'component' => 'local_nit_commerce'],
            'managesubscriptions' => ['path' => '/local/nit_subscriptions/manage_subscriptions.php', 'section' => null,
                'rule' => 'local/nit_subscriptions:managesubscriptions',
                'label' => ['managesubscriptions', 'local_nit_subscriptions'], 'component' => 'local_nit_subscriptions'],
            'managejobform' => ['path' => '/local/jobform/manage.php', 'section' => null,
                'rule' => 'local/jobform:manage',
                'label' => ['managejobform', 'local_jobform'], 'component' => 'local_jobform'],
            'gallery' => ['path' => '/theme/nit/gallery.php', 'section' => null, 'rule' => 'moodle/site:config',
                'label' => ['navgallery', 'theme_nit'], 'component' => 'theme_nit'],
            'sitemedia' => ['path' => '/admin/settings.php', 'section' => 'local_nit_media_settings',
                'rule' => 'moodle/site:config',
                'label' => ['pluginname', 'local_nit_media'], 'component' => 'local_nit_media'],
            // Course purchases + the enrolment-source report (AC-4.10.5). Last:
            // it is the screen that gets *read* rather than edited, so it sits
            // after the things an administrator goes to the menu to change.
            'managecourses' => ['path' => '/local/nit_subscriptions/manage_courses.php', 'section' => null,
                'rule' => 'local/nit_subscriptions:managesubscriptions',
                'label' => ['managecourses', 'local_nit_subscriptions'], 'component' => 'local_nit_subscriptions'],
        ];
    }

    /**
     * The menu a fresh site gets, as text: the groups and rows the theme
     * hard-coded before the setting existed, in the same order, with each name
     * taken from the English and the Arabic language packs. Upgrading changes
     * nothing until an administrator edits the box.
     *
     * Rows for plugins the site does not run are left out - there would be no
     * page to open and no strings to name it by.
     *
     * @return string
     */
    public static function default_definition(): string {
        $groups = [
            [['navigation', 'core'], ['mycourses', 'siteadmin']],
            [['navmanagement', 'theme_nit'], ['managecoupons', 'manageoffers', 'managesubscriptions',
                'managejobform', 'gallery', 'sitemedia', 'managecourses']],
        ];
        $catalogue = self::catalogue();
        $lines = [];

        foreach ($groups as [$heading, $keys]) {
            $lines[] = self::names_column($heading);
            foreach ($keys as $key) {
                $page = $catalogue[$key];
                if ($page['component'] !== 'core'
                        && \core_component::get_component_directory($page['component']) === null) {
                    continue;
                }
                $url = $page['path'] . ($page['section'] !== null ? '?section=' . $page['section'] : '');
                $lines[] = '-' . self::names_column($page['label']) . '|' . $url;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * `English|Arabic` for a language string, as a default line spells a name.
     *
     * The string manager hands back the English text for a language whose
     * pack is not installed, so a site without Arabic gets `Name|Name` - which
     * is still right, just not translated.
     *
     * @param array $label [identifier, component]
     * @return string
     */
    protected static function names_column(array $label): string {
        $manager = get_string_manager();
        return $manager->get_string($label[0], $label[1], null, 'en')
            . '|' . $manager->get_string($label[0], $label[1], null, 'ar');
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
     * that page_line() cannot find a link in is dropped here - there is nothing
     * to open - but the settings page refuses to save such a line in the first
     * place (problems()), so a saved definition never loses a row silently.
     *
     * @param string $text the setting text
     * @return array list of ['names' => ['en' => string, 'ar' => string],
     *               'items' => list of ['names' => [...], 'url' => string, 'rule' => string]]
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
                $bits = array_map('trim', explode('|', $line, 2));
                $current = ['names' => ['en' => $bits[0], 'ar' => $bits[1] ?? ''], 'items' => []];
                continue;
            }

            $item = self::page_line($line);
            if ($item['url'] === '' || ($item['names']['en'] === '' && $item['names']['ar'] === '')) {
                continue;
            }

            if ($current === null) {
                $current = ['names' => ['en' => '', 'ar' => ''], 'items' => []];
            }
            $current['items'][] = $item;
        }

        if ($current !== null) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * Read one page line - `-English|Arabic|link`, with some forgiveness.
     *
     * The link is the third part when there are three. With fewer, the last
     * part is the link if it reads like one (`-English|link`, one name for
     * both languages), and when it does not, a link glued onto the end of a
     * name is peeled off it: `-Calendar|التقويم/calendar/view.php` is the most
     * common slip - a `|` forgotten before the link - and is read as the
     * writer meant. A fourth part, when present, is an explicit rule
     * (rule_for() explains). `url` is '' when no link could be found.
     *
     * @param string $line a trimmed line starting with '-'
     * @return array ['names' => ['en' => string, 'ar' => string], 'url' => string, 'rule' => string]
     */
    public static function page_line(string $line): array {
        $bits = array_map('trim', explode('|', ltrim($line, '-'), 4));

        if (count($bits) >= 3) {
            [$en, $ar, $url] = $bits;
            $rule = $bits[3] ?? '';
        } else {
            $rule = '';
            $last = array_pop($bits);
            if (self::looks_like_link($last)) {
                $url = $last;
            } else if (preg_match('~^(.*?)((?:/|[a-z][a-z0-9+.-]*://)\S*)$~iu', $last, $found)) {
                $bits[] = trim($found[1]);
                $url = $found[2];
            } else {
                $bits[] = $last;
                $url = '';
            }
            $en = $bits[0];
            $ar = $bits[1] ?? '';
        }

        return ['names' => ['en' => $en, 'ar' => $ar], 'url' => $url, 'rule' => $rule];
    }

    /**
     * What is wrong with a definition, for the settings page to show before
     * it saves: every page line that names no link, and every one that has a
     * link but no name. A heading line can never be wrong.
     *
     * @param string $text the text as typed
     * @return array list of ['line' => int (1-based), 'text' => string, 'problem' => 'nolink'|'noname']
     */
    public static function problems(string $text): array {
        $problems = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) as $number => $line) {
            $line = trim($line);
            if ($line === '' || $line[0] !== '-') {
                continue;
            }
            $item = self::page_line($line);
            if ($item['url'] === '') {
                $problems[] = ['line' => $number + 1, 'text' => $line, 'problem' => 'nolink'];
            } else if ($item['names']['en'] === '' && $item['names']['ar'] === '') {
                $problems[] = ['line' => $number + 1, 'text' => $line, 'problem' => 'noname'];
            }
        }
        return $problems;
    }

    /**
     * Does a column read as a link rather than a name?
     *
     * @param string $value
     * @return bool
     */
    protected static function looks_like_link(string $value): bool {
        return $value !== '' && ($value[0] === '/' || preg_match('~^[a-z][a-z0-9+.-]*://~i', $value) === 1);
    }

    /**
     * The name the viewer reads: the column of their language, the other one
     * when theirs is empty.
     *
     * Run through format_string() so markup is neutralised — the templates
     * print it unescaped, as they do core's own navigation text.
     *
     * @param array $names ['en' => string, 'ar' => string]
     * @return string
     */
    public static function label(array $names): string {
        $arabic = substr(current_language(), 0, 2) === 'ar';
        $text = $arabic ? ($names['ar'] ?: $names['en']) : ($names['en'] ?: $names['ar']);

        return format_string($text, true, ['context' => context_system::instance()]);
    }

    /**
     * Who may see a link, worked out from the link itself.
     *
     * An explicit rule on the line wins. Otherwise a link the catalogue knows
     * gets that page's rule - the capability the page requires. Anything else
     * under /admin/ is for whoever may open Site administration, since every
     * page there checks at least that; any other link is for everyone, the way
     * a Custom menu items line is.
     *
     * @param string $url the link as written
     * @param string $explicit the line's fourth part, '' when absent
     * @return string RULE_EVERYONE, RULE_LOGGEDIN, RULE_ADMIN, or a capability
     */
    public static function rule_for(string $url, string $explicit = ''): string {
        $explicit = trim($explicit);
        if ($explicit !== '') {
            return $explicit;
        }

        $moodleurl = self::url($url);
        $path = self::path_key($moodleurl->get_path());
        $section = $moodleurl->get_param('section');

        foreach (self::catalogue() as $page) {
            if (self::path_key($page['path']) !== $path) {
                continue;
            }
            if ($page['section'] !== null && $page['section'] !== $section) {
                continue;
            }
            return $page['rule'];
        }

        if (strpos($path, '/admin/') === 0) {
            return self::RULE_ADMIN;
        }

        return self::RULE_EVERYONE;
    }

    /**
     * One path in one spelling: `/my/` and `/my/index.php` are the same page,
     * and so are `/local/x` and `/local/x/`. Site-relative, so a full address
     * and a path compare alike.
     *
     * @param string $path
     * @return string
     */
    protected static function path_key(string $path): string {
        global $CFG;

        $root = (string) parse_url($CFG->wwwroot, PHP_URL_PATH);
        if ($root !== '' && strpos($path, $root) === 0) {
            $path = substr($path, strlen($root));
        }
        $path = preg_replace('~/index\.php$~', '/', $path);
        return '/' . trim($path, '/') . '/';
    }

    /**
     * Does this viewer get to see a link with this rule?
     *
     * @param string $rule from rule_for()
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
     * A link as written, as a moodle_url: a path is taken relative to the site,
     * a full address is left alone.
     *
     * @param string $url
     * @return moodle_url
     */
    public static function url(string $url): moodle_url {
        return new moodle_url(trim($url));
    }
}
