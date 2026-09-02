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

namespace local_profilefields;

use context_system;
use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * The six static pages of AC-4.21 - About, Contact, Terms, Privacy, Refund, FAQ.
 *
 * Three of them Moodle already has and three it does not, and this class keeps that
 * distinction rather than papering over it.
 *
 * Terms, Privacy and Refund are *policy documents*. tool_policy already stores them,
 * versions them, records who accepted which revision and serves them at
 * `/admin/tool/policy/view.php`; building a second store for the same three texts
 * would mean the sign-up consent checkbox and the footer link pointed at different
 * copies of the same document. So those three are a *mapping*: the administrator
 * says "the Terms page is this tool_policy document in English and that one in
 * Arabic", and everything else - the page, the footer, the app - reads through the
 * mapping. The text stays authored and versioned where Moodle already keeps it.
 * A page with nothing mapped falls back to text typed here, so the page is never
 * broken while the documents are still being written.
 *
 * About, Contact and FAQ have no counterpart in Moodle at all - there is no CMS in
 * core, and a "site page" is not a thing Moodle models - so those are ours: a title
 * and a body per language in {local_profilefields_page}, and for the FAQ a list of
 * question/answer pairs in {local_profilefields_faq}.
 *
 * Bilingual by construction, the same way the footer is (AC-4.21.2): one row per
 * language, never one field with {mlang} markup in it. Missing translations fall
 * back to the interface language, then English, then whatever was actually written,
 * so a half-translated page still shows something.
 *
 * The Contact page deliberately owns *no* contact details of its own. The address,
 * phone, opening hours, email and the social links are already edited on the Footer
 * tab and already drawn at the bottom of every page; a second copy here would be a
 * second thing to keep in step and a second thing to get wrong. This page reads
 * {@see footer::config()} and adds only what the footer has no room for: the map.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class staticpages {

    /** @var string Config component. */
    const COMPONENT = 'local_profilefields';

    /** @var string The table holding one title + body per page per language. */
    const TABLE = 'local_profilefields_page';

    /** @var string The file area embedded images in a page body live in. */
    const FILEAREA = 'pagecontent';

    /** @var string Our own prose, written here. */
    const KIND_CONTENT = 'content';

    /** @var string Our own prose, plus the footer's contact block and a map. */
    const KIND_CONTACT = 'contact';

    /** @var string A tool_policy document, chosen per language. */
    const KIND_POLICY = 'policy';

    /** @var string Our own prose, plus the question and answer list. */
    const KIND_FAQ = 'faq';

    /**
     * Every static page, in the order they are offered to an administrator.
     *
     * The keys are the `page` parameter of the public URL and the suffix of every
     * config key, so they are part of the site's addresses: do not rename one
     * without leaving a redirect behind.
     *
     * @return array<string, string> slug => KIND_*
     */
    public static function pages(): array {
        return [
            'about'   => self::KIND_CONTENT,
            'contact' => self::KIND_CONTACT,
            'terms'   => self::KIND_POLICY,
            'privacy' => self::KIND_POLICY,
            'refund'  => self::KIND_POLICY,
            'faq'     => self::KIND_FAQ,
        ];
    }

    /**
     * The slugs, in display order.
     *
     * @return string[]
     */
    public static function slugs(): array {
        return array_keys(self::pages());
    }

    /**
     * Whether a slug names a page this site has.
     *
     * @param string $slug
     * @return bool
     */
    public static function exists(string $slug): bool {
        return array_key_exists($slug, self::pages());
    }

    /**
     * What sort of page a slug is.
     *
     * @param string $slug
     * @return string one of the KIND_* constants, '' when the slug is unknown
     */
    public static function kind(string $slug): string {
        return self::pages()[$slug] ?? '';
    }

    /**
     * The languages a page is written in, in fallback order.
     *
     * Delegated to the footer so the site has one answer to "which languages does
     * an administrator type content in", not two that can drift apart.
     *
     * @return string[] language codes
     */
    public static function langs(): array {
        return footer::langs();
    }

    // -----------------------------------------------------------------
    // Settings.
    // -----------------------------------------------------------------

    /**
     * Whether a page is published.
     *
     * Pages ship switched on: the footer links to all six from the day this is
     * installed, and a link to a page that answers "not found" is worse than a
     * link to a page that is still being written.
     *
     * @param string $slug
     * @return bool
     */
    public static function enabled(string $slug): bool {
        $value = get_config(self::COMPONENT, 'page_' . $slug . '_enabled');
        return $value === false || $value === null ? true : ((string) $value === '1');
    }

    /**
     * Publish or unpublish a page.
     *
     * @param string $slug
     * @param bool $enabled
     * @return void
     */
    public static function set_enabled(string $slug, bool $enabled): void {
        set_config('page_' . $slug . '_enabled', $enabled ? '1' : '0', self::COMPONENT);
    }

    /**
     * The tool_policy document mapped to one language of a policy page.
     *
     * Stored as the *policy* id, not the version id, deliberately: publishing a new
     * version of the Terms is the normal thing to do, and a mapping made of version
     * ids would quietly keep showing the superseded text. The current version is
     * resolved at read time.
     *
     * @param string $slug
     * @param string $lang language code
     * @return int policy id, 0 when nothing is mapped
     */
    public static function policy_id(string $slug, string $lang): int {
        return (int) get_config(self::COMPONENT, 'page_' . $slug . '_policy_' . $lang);
    }

    /**
     * Map a tool_policy document onto one language of a policy page.
     *
     * @param string $slug
     * @param string $lang language code
     * @param int $policyid tool_policy document id, 0 to unmap
     * @return void
     */
    public static function set_policy_id(string $slug, string $lang, int $policyid): void {
        set_config('page_' . $slug . '_policy_' . $lang, $policyid, self::COMPONENT);
    }

    /**
     * One of the Contact page's own settings.
     *
     * @param string $name mapembed|maplink
     * @return string
     */
    public static function contact_setting(string $name): string {
        return (string) get_config(self::COMPONENT, 'pagecontact' . $name);
    }

    /**
     * Store one of the Contact page's own settings.
     *
     * @param string $name mapembed|maplink
     * @param string $value
     * @return void
     */
    public static function set_contact_setting(string $name, string $value): void {
        set_config('pagecontact' . $name, $value, self::COMPONENT);
    }

    // -----------------------------------------------------------------
    // Stored text.
    // -----------------------------------------------------------------

    /**
     * The stored row for one language of one page, creating it if it is not there.
     *
     * A row is made before it has anything in it because its id is the itemid of
     * the file area the body's images live in: an editor needs somewhere to put an
     * uploaded picture before the administrator has pressed Save.
     *
     * @param string $slug
     * @param string $lang language code
     * @return stdClass
     */
    public static function ensure_row(string $slug, string $lang): stdClass {
        global $DB, $USER;

        $row = $DB->get_record(self::TABLE, ['slug' => $slug, 'lang' => $lang]);
        if ($row) {
            return $row;
        }

        $row = (object) [
            'slug'          => $slug,
            'lang'          => $lang,
            'title'         => '',
            'content'       => '',
            'contentformat' => FORMAT_HTML,
            'timemodified'  => time(),
            'usermodified'  => (int) ($USER->id ?? 0),
        ];
        $row->id = $DB->insert_record(self::TABLE, $row);

        return $row;
    }

    /**
     * The stored row for one language of one page, or null.
     *
     * @param string $slug
     * @param string $lang language code
     * @return stdClass|null
     */
    public static function row(string $slug, string $lang): ?stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['slug' => $slug, 'lang' => $lang]) ?: null;
    }

    /**
     * Write one language of one page.
     *
     * @param string $slug
     * @param string $lang language code
     * @param string $title
     * @param string $content already run through file_save_draft_area_files()
     * @param int $format
     * @return void
     */
    public static function save_row(string $slug, string $lang, string $title,
            string $content, int $format = FORMAT_HTML): void {
        global $DB, $USER;

        $row = self::ensure_row($slug, $lang);
        $row->title = $title;
        $row->content = $content;
        $row->contentformat = $format;
        $row->timemodified = time();
        $row->usermodified = (int) ($USER->id ?? 0);

        $DB->update_record(self::TABLE, $row);
    }

    /**
     * The title an administrator typed for one language, exactly as stored.
     *
     * @param string $slug
     * @param string $lang language code
     * @return string
     */
    public static function raw_title(string $slug, string $lang): string {
        $row = self::row($slug, $lang);
        return $row ? (string) $row->title : '';
    }

    /**
     * The body an administrator typed for one language, exactly as stored.
     *
     * @param string $slug
     * @param string $lang language code
     * @return string
     */
    public static function raw_content(string $slug, string $lang): string {
        $row = self::row($slug, $lang);
        return $row ? (string) $row->content : '';
    }

    /**
     * The page's name in the language being displayed.
     *
     * Falls back through the interface language, English, any language that was
     * filled in, and finally the shipped name - so the footer link and the browser
     * tab always say something, even on a page nobody has opened the editor for.
     *
     * @param string $slug
     * @return string plain text
     */
    public static function title(string $slug): string {
        foreach (self::lang_order() as $lang) {
            $title = trim(self::raw_title($slug, $lang));
            if ($title !== '') {
                return $title;
            }
        }

        return self::default_title($slug, current_language());
    }

    /**
     * The shipped name of a page in one language.
     *
     * @param string $slug
     * @param string $lang language code
     * @return string plain text
     */
    public static function default_title(string $slug, string $lang): string {
        return get_string_manager()->get_string('staticpagename_' . $slug, 'local_profilefields', null, $lang);
    }

    /**
     * The body, resolved to the display language and ready to echo.
     *
     * @param string $slug
     * @return string HTML, '' when nothing has been written in any language
     */
    public static function content(string $slug): string {
        foreach (self::lang_order() as $lang) {
            $row = self::row($slug, $lang);
            if (!$row || !self::has_text((string) $row->content)) {
                continue;
            }
            return self::format_body($row);
        }

        return '';
    }

    /**
     * Whether a stored body is anything more than empty markup.
     *
     * An editor left alone still posts something - a stray paragraph, a line break,
     * a non-breaking space - and treating that as content is what makes a page fall
     * back to the wrong language, or draw an empty box under its heading.
     *
     * @param string $html
     * @return bool
     */
    public static function has_text(string $html): bool {
        // Tags that are content in their own right survive the strip, so a body
        // that is nothing but a picture or an embedded video still counts.
        $stripped = strip_tags($html, '<img><iframe><video><audio><embed><object><table><hr>');
        $stripped = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\xc2\xa0", ' ', $stripped)) !== '';
    }

    /**
     * One stored row's body as display HTML, with its images resolved.
     *
     * @param stdClass $row a row of {local_profilefields_page}
     * @return string HTML
     */
    public static function format_body(stdClass $row): string {
        global $CFG;

        // filelib is not loaded on every request - lib/setup.php only pulls it in
        // behind a proxy setting - and this is reached from the public page and
        // from a web service, neither of which has necessarily touched a form.
        require_once($CFG->libdir . '/filelib.php');

        $context = context_system::instance();

        $text = file_rewrite_pluginfile_urls((string) $row->content, 'pluginfile.php',
            $context->id, self::COMPONENT, self::FILEAREA, (int) $row->id);

        return format_text($text, (int) $row->contentformat, ['context' => $context]);
    }

    /**
     * The languages to try, in order, for a value shown to the current visitor.
     *
     * @return string[] language codes
     */
    protected static function lang_order(): array {
        $order = array_merge([current_language()], ['en'], self::langs());

        return array_values(array_unique(array_filter($order, static function (string $lang): bool {
            return in_array($lang, self::langs(), true);
        })));
    }

    // -----------------------------------------------------------------
    // Policy documents.
    // -----------------------------------------------------------------

    /**
     * Every tool_policy document, as an id => name list for a chooser.
     *
     * The current version's name, because that is what an administrator recognises;
     * a document with no published version cannot be shown to anyone, so it is not
     * offered.
     *
     * @return array<int, string> policy id => document name
     */
    public static function policy_choices(): array {
        if (!policies::tool_available()) {
            return [];
        }

        try {
            $policies = \tool_policy\api::list_policies();
        } catch (\Throwable $e) {
            return [];
        }

        $choices = [];
        foreach ($policies as $policy) {
            if (empty($policy->currentversion)) {
                continue;
            }
            $choices[(int) $policy->id] = format_string($policy->currentversion->name);
        }

        return $choices;
    }

    /**
     * The current version of the document mapped to a policy page, for display.
     *
     * Tries the display language's mapping first, then the same ladder every other
     * piece of text on these pages uses - so a site that has only mapped the Arabic
     * Terms shows the Arabic Terms to an English visitor rather than an empty page.
     *
     * @param string $slug
     * @return stdClass|null the exported tool_policy version, or null when the page
     *                       has no document mapped in any language
     */
    public static function policy_version(string $slug): ?stdClass {
        if (self::kind($slug) !== self::KIND_POLICY || !policies::tool_available()) {
            return null;
        }

        try {
            $policies = \tool_policy\api::list_policies();
        } catch (\Throwable $e) {
            return null;
        }

        $byid = [];
        foreach ($policies as $policy) {
            $byid[(int) $policy->id] = $policy;
        }

        foreach (self::lang_order() as $lang) {
            $policyid = self::policy_id($slug, $lang);
            if ($policyid && !empty($byid[$policyid]->currentversion)) {
                return $byid[$policyid]->currentversion;
            }
        }

        return null;
    }

    // -----------------------------------------------------------------
    // Addresses.
    // -----------------------------------------------------------------

    /**
     * Where a page lives.
     *
     * Always our own address, even for the three policy pages. The tool_policy view
     * URL carries a version id, so it changes every time the document is revised: a
     * link printed in an email, indexed by a search engine or bookmarked by a
     * learner would rot on the next revision. This one does not.
     *
     * @param string $slug
     * @return moodle_url
     */
    public static function url(string $slug): moodle_url {
        return new moodle_url('/local/profilefields/page.php', ['page' => $slug]);
    }

    /**
     * The published pages, as a menu.
     *
     * @return array<int, array{slug: string, title: string, url: string}>
     */
    public static function menu(): array {
        $menu = [];
        foreach (self::slugs() as $slug) {
            if (!self::enabled($slug)) {
                continue;
            }
            $menu[] = [
                'slug'  => $slug,
                'title' => self::title($slug),
                'url'   => self::url($slug)->out(false),
            ];
        }

        return $menu;
    }

    // -----------------------------------------------------------------
    // What a page is made of, for whoever draws it.
    // -----------------------------------------------------------------

    /**
     * Everything one page says, resolved to the display language.
     *
     * Presentation-free on purpose, the way {@see footer::config()} is: the web page
     * renders this through a Mustache template and the app renders the same answer
     * through its own screens, so an administrator edits one thing and both change.
     *
     * @param string $slug
     * @return array
     */
    public static function view(string $slug): array {
        $kind = self::kind($slug);

        $view = [
            'slug'       => $slug,
            'kind'       => $kind,
            'title'      => self::title($slug),
            'content'    => self::content($slug),
            'policyname' => '',
            'policyurl'  => '',
            'contact'    => [],
            'social'     => [],
            'mapembed'   => '',
            'maplink'    => '',
            'faq'        => [],
        ];

        if ($kind === self::KIND_POLICY) {
            $version = self::policy_version($slug);
            if ($version) {
                // The exporter has already run format_text() over the content and
                // rewritten its pluginfile URLs, so this is ready to echo.
                $view['content'] = (string) $version->content;
                $view['policyname'] = format_string($version->name);
                $view['policyurl'] = (new moodle_url('/admin/tool/policy/view.php', [
                    'policyid'  => (int) $version->policyid,
                    'versionid' => (int) $version->id,
                ]))->out(false);
            }
        }

        if ($kind === self::KIND_CONTACT) {
            $footer = footer::config();
            $view['contact'] = $footer['contact']['rows'];
            $view['social'] = $footer['social'];
            $view['mapembed'] = self::contact_setting('mapembed');
            $view['maplink'] = self::contact_setting('maplink');
        }

        if ($kind === self::KIND_FAQ) {
            $view['faq'] = faq::visible_items();
        }

        return $view;
    }
}
