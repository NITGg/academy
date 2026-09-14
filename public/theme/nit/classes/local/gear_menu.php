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
 * language, which pages sit under them, in what order and for whom is one
 * setting - `theme_nit/gearmenuitems`, on Site administration → Appearance →
 * Advanced theme settings, written the way the Custom menu items box beside
 * it is:
 *
 *   English name|Arabic name                    a line without a dash starts a group
 *   -English name|Arabic name|/link.php|who     a line with one is a page in it
 *
 * `who` is one or more of `guest` (a visitor who is not logged in), `user` (a
 * logged-in user who is not an administrator), `admin` (whoever may open Site
 * administration) or `all`, comma-separated: `guest,user`. Left out, it is
 * worked out from the link (audience_for()). The Arabic name may be left out
 * too (`-English name|/link.php`): the one name then serves both languages. A
 * viewer whose interface is Arabic reads the Arabic column, everyone else the
 * English one; a column left empty falls back to the other.
 *
 * Whatever `who` says, a page the theme knows to require a capability - the
 * management screens - is never shown to a viewer who lacks it: a "Manage
 * coupons" row that opens onto an error is a broken link, and the setting
 * cannot make one.
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

    /** @var string Audience: everyone, visitors included. */
    public const AUDIENCE_ALL = 'all';

    /** @var string Audience: a visitor who is not logged in (the guest account counts as one). */
    public const AUDIENCE_GUEST = 'guest';

    /** @var string Audience: a logged-in user who may not open Site administration. */
    public const AUDIENCE_USER = 'user';

    /** @var string Audience: anyone who may open Site administration. */
    public const AUDIENCE_ADMIN = 'admin';

    /** @var string Audience: any logged-in user - `user,admin` in one word, kept for lines already saved. */
    public const AUDIENCE_LOGGEDIN = 'loggedin';

    /**
     * The screens the theme knows, keyed by the path (and, for a settings
     * section, the `section` parameter) a line would name them by.
     *
     * Three jobs. `capability` is what the page itself requires, and gates the
     * row whatever the line says. `audience` is who the row is for when the
     * line does not say. `label` is how default_definition() writes the
     * shipped menu: from the language packs, so the default text carries the
     * real English and Arabic names.
     *
     * @return array list of ['path' => string, 'section' => ?string, 'capability' => ?string,
     *                        'audience' => string, 'label' => [identifier, component], 'component' => string]
     */
    public static function catalogue(): array {
        $signedin = self::AUDIENCE_USER . ',' . self::AUDIENCE_ADMIN;
        return [
            'mycourses' => ['path' => '/my/courses.php', 'section' => null, 'capability' => null,
                'audience' => $signedin, 'label' => ['mycourses', 'core'], 'component' => 'core'],
            'dashboard' => ['path' => '/my/', 'section' => null, 'capability' => null,
                'audience' => $signedin, 'label' => ['myhome', 'core'], 'component' => 'core'],
            'siteadmin' => ['path' => '/admin/search.php', 'section' => null, 'capability' => null,
                'audience' => self::AUDIENCE_ADMIN, 'label' => ['administrationsite', 'core'], 'component' => 'core'],
            'managecoupons' => ['path' => '/local/nit_commerce/manage_coupons.php', 'section' => null,
                'capability' => 'local/nit_commerce:managecoupons', 'audience' => self::AUDIENCE_ADMIN,
                'label' => ['managecoupons', 'local_nit_commerce'], 'component' => 'local_nit_commerce'],
            'manageoffers' => ['path' => '/local/nit_commerce/manage_offers.php', 'section' => null,
                'capability' => 'local/nit_commerce:manageoffers', 'audience' => self::AUDIENCE_ADMIN,
                'label' => ['manageoffers', 'local_nit_commerce'], 'component' => 'local_nit_commerce'],
            'managesubscriptions' => ['path' => '/local/nit_subscriptions/manage_subscriptions.php', 'section' => null,
                'capability' => 'local/nit_subscriptions:managesubscriptions', 'audience' => self::AUDIENCE_ADMIN,
                'label' => ['managesubscriptions', 'local_nit_subscriptions'], 'component' => 'local_nit_subscriptions'],
            'managejobform' => ['path' => '/local/jobform/manage.php', 'section' => null,
                'capability' => 'local/jobform:manage', 'audience' => self::AUDIENCE_ADMIN,
                'label' => ['managejobform', 'local_jobform'], 'component' => 'local_jobform'],
            'gallery' => ['path' => '/theme/nit/gallery.php', 'section' => null,
                'capability' => 'moodle/site:config', 'audience' => self::AUDIENCE_ADMIN,
                'label' => ['navgallery', 'theme_nit'], 'component' => 'theme_nit'],
            'sitemedia' => ['path' => '/admin/settings.php', 'section' => 'local_nit_media_settings',
                'capability' => 'moodle/site:config', 'audience' => self::AUDIENCE_ADMIN,
                'label' => ['pluginname', 'local_nit_media'], 'component' => 'local_nit_media'],
            // Course purchases + the enrolment-source report (AC-4.10.5). Last:
            // it is the screen that gets *read* rather than edited, so it sits
            // after the things an administrator goes to the menu to change.
            'managecourses' => ['path' => '/local/nit_subscriptions/manage_courses.php', 'section' => null,
                'capability' => 'local/nit_subscriptions:managesubscriptions', 'audience' => self::AUDIENCE_ADMIN,
                'label' => ['managecourses', 'local_nit_subscriptions'], 'component' => 'local_nit_subscriptions'],
        ];
    }

    /**
     * The menu a fresh site gets, as text: the groups and rows the theme
     * hard-coded before the setting existed, in the same order, with each name
     * taken from the English and the Arabic language packs and each row's
     * audience written out - so the box shows the syntax by example.
     * Upgrading changes nothing until an administrator edits the box.
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
                $lines[] = '-' . self::names_column($page['label']) . '|' . $url . '|' . $page['audience'];
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
     *               'items' => list of ['names' => [...], 'url' => string, 'audience' => string]]
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
                $bits = self::parts($line, 2);
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
     * Read one page line - `-English|Arabic|link|who` - with some forgiveness.
     *
     * The link is found by what it looks like (it starts with `/` or a
     * scheme), not by its position, so `-English|link|who` - one name for both
     * languages, and an audience - reads right too. Everything before the link
     * is names, the part after it is the audience (audience_allows() lists the
     * words). When no part is a link, a link glued onto the end of a name is
     * peeled off it: `-Calendar|التقويم/calendar/view.php` is the most common
     * slip - a `|` forgotten before the link - and is read as the writer
     * meant. A `|` left dangling at the end of a line, or typed twice, is
     * ignored (parts()). `url` is '' when no link could be found; `audience` is
     * '' when none was written.
     *
     * @param string $line a trimmed line starting with '-'
     * @return array ['names' => ['en' => string, 'ar' => string], 'url' => string, 'audience' => string]
     */
    public static function page_line(string $line): array {
        $bits = self::parts(ltrim($line, '-'), 4);

        $linkat = null;
        foreach ($bits as $i => $bit) {
            if (self::looks_like_link($bit)) {
                $linkat = $i;
                break;
            }
        }
        if ($linkat === null) {
            foreach ($bits as $i => $bit) {
                if (preg_match('~^(.*?)((?:/|[a-z][a-z0-9+.-]*://)\S*)$~iu', $bit, $found)) {
                    $bits[$i] = trim($found[1]);
                    array_splice($bits, $i + 1, 0, [$found[2]]);
                    $linkat = $i + 1;
                    break;
                }
            }
        }

        if ($linkat === null) {
            $names = $bits;
            $url = '';
            $audience = '';
        } else {
            $names = array_slice($bits, 0, $linkat);
            $url = $bits[$linkat];
            $audience = $bits[$linkat + 1] ?? '';
        }

        return [
            'names' => ['en' => $names[0] ?? '', 'ar' => $names[1] ?? ''],
            'url' => $url,
            'audience' => strtolower($audience),
        ];
    }

    /**
     * What is wrong with a definition, for the settings page to show before
     * it saves: every page line that names no link, every one that has a link
     * but no name, and every audience word that is not one of the known ones.
     * A heading line can never be wrong.
     *
     * @param string $text the text as typed
     * @return array list of ['line' => int (1-based), 'text' => string,
     *               'problem' => 'nolink'|'noname'|'audience', 'word' => string (audience only)]
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
                continue;
            }
            if ($item['names']['en'] === '' && $item['names']['ar'] === '') {
                $problems[] = ['line' => $number + 1, 'text' => $line, 'problem' => 'noname'];
                continue;
            }
            foreach (self::audience_words($item['audience']) as $word) {
                if (!self::is_audience_word($word)) {
                    $problems[] = ['line' => $number + 1, 'text' => $line, 'problem' => 'audience', 'word' => $word];
                }
            }
        }
        return $problems;
    }

    /**
     * A line split on `|`, trimmed, with the slips that cost nothing taken out:
     * a `|` left dangling at the end (`...|admin,user|`), one typed twice
     * (`name||link`) and a blank part between two others are all dropped, so
     * they can neither become an empty name nor swallow the audience.
     *
     * @param string $line the line, without its leading dash
     * @param int $limit the most parts wanted; the last one keeps any remainder
     * @return string[] non-empty, trimmed parts
     */
    protected static function parts(string $line, int $limit): array {
        $parts = array_values(array_filter(array_map('trim', explode('|', $line)), fn($part) => $part !== ''));
        if (count($parts) > $limit) {
            $parts = array_merge(array_slice($parts, 0, $limit - 1), [implode('|', array_slice($parts, $limit - 1))]);
        }
        return $parts === [] ? [''] : $parts;
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
     * The catalogue entry a link points at, if the theme knows the page.
     *
     * @param string $url the link as written
     * @return array|null one entry of catalogue(), or null
     */
    public static function catalogue_entry(string $url): ?array {
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
            return $page;
        }
        return null;
    }

    /**
     * Who a row is for.
     *
     * What the line says, when it says. Otherwise a link the catalogue knows
     * gets that page's audience; anything else under /admin/ is for
     * administrators, since every page there checks at least that; any other
     * link is for everyone, the way a Custom menu items line is.
     *
     * @param string $url the link as written
     * @param string $typed the line's fourth part, '' when absent
     * @return string comma-separated audience words
     */
    public static function audience_for(string $url, string $typed = ''): string {
        $typed = trim($typed);
        if ($typed !== '') {
            return $typed;
        }

        $page = self::catalogue_entry($url);
        if ($page !== null) {
            return $page['audience'];
        }

        if (strpos(self::path_key(self::url($url)->get_path()), '/admin/') === 0) {
            return self::AUDIENCE_ADMIN;
        }

        return self::AUDIENCE_ALL;
    }

    /**
     * Does this viewer get to see this row?
     *
     * The audience decides - and, for a page the theme knows to require a
     * capability, the viewer must hold it too, whatever the line says: the
     * administrator may widen who is *offered* a management screen, but not
     * who may open it, so the row is withheld rather than shown broken.
     *
     * @param string $url the link as written
     * @param string $typed the line's fourth part, '' when absent
     * @param moodle_page $page the page being rendered
     * @return bool
     */
    public static function visible(string $url, string $typed, moodle_page $page): bool {
        $entry = self::catalogue_entry($url);
        if ($entry !== null && $entry['capability'] !== null
                && !self::audience_allows($entry['capability'], $page)) {
            return false;
        }

        return self::audience_allows(self::audience_for($url, $typed), $page);
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
     * The words of an audience column.
     *
     * @param string $audience e.g. 'guest, user'
     * @return string[] lower-case, trimmed, empties dropped
     */
    public static function audience_words(string $audience): array {
        $words = array_map('trim', explode(',', strtolower($audience)));
        return array_values(array_filter($words, fn($word) => $word !== ''));
    }

    /**
     * Is this a word audience_allows() understands?
     *
     * The four everyday words, the legacy `loggedin`, or a capability the site
     * has - the last for an administrator who knows one and wants a row for
     * exactly its holders.
     *
     * @param string $word
     * @return bool
     */
    public static function is_audience_word(string $word): bool {
        if (in_array($word, [self::AUDIENCE_ALL, self::AUDIENCE_GUEST, self::AUDIENCE_USER,
                self::AUDIENCE_ADMIN, self::AUDIENCE_LOGGEDIN], true)) {
            return true;
        }
        return strpos($word, ':') !== false && get_capability_info($word) !== null;
    }

    /**
     * Does this viewer belong to any of these audiences?
     *
     * @param string $audience comma-separated words; '' means everyone
     * @param moodle_page $page the page being rendered - `admin` reads its
     *                          settings navigation, as core does
     * @return bool
     */
    public static function audience_allows(string $audience, moodle_page $page): bool {
        $words = self::audience_words($audience);
        if (empty($words)) {
            return true;
        }

        foreach ($words as $word) {
            switch ($word) {
                case self::AUDIENCE_ALL:
                    return true;

                case self::AUDIENCE_GUEST:
                    if (!isloggedin() || isguestuser()) {
                        return true;
                    }
                    break;

                case self::AUDIENCE_USER:
                    if (isloggedin() && !isguestuser() && !self::is_admin_viewer($page)) {
                        return true;
                    }
                    break;

                case self::AUDIENCE_ADMIN:
                    if (self::is_admin_viewer($page)) {
                        return true;
                    }
                    break;

                case self::AUDIENCE_LOGGEDIN:
                    if (isloggedin() && !isguestuser()) {
                        return true;
                    }
                    break;

                default:
                    // A capability. Asked about first, because has_capability()
                    // on a name the site never installed logs a developer
                    // warning on every page.
                    if (get_capability_info($word) !== null && has_capability($word, context_system::instance())) {
                        return true;
                    }
            }
        }

        return false;
    }

    /**
     * May this viewer open Site administration?
     *
     * Core's own test for the Site administration row
     * (\core\navigation\views\primary::get_site_admin_node): the settings
     * navigation has an admin root for anyone who may open any part of the
     * admin tree. The navigation is built once per request, so asking it for
     * every `admin` and `user` word on the page costs two lookups each.
     *
     * @param moodle_page $page
     * @return bool
     */
    protected static function is_admin_viewer(moodle_page $page): bool {
        $settingsnav = $page->settingsnav;
        return (bool) ($settingsnav->find('siteadministration', \navigation_node::TYPE_SITE_ADMIN)
            ?: $settingsnav->find('root', \navigation_node::TYPE_SITE_ADMIN));
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
