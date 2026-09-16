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

defined('MOODLE_INTERNAL') || die();

/**
 * The parts of the About page that are not prose (AC-4.21.1).
 *
 * The About page started as a title and a body, like the other five. An academy's
 * "who we are" is more than a body, though: it has a picture or a film of the
 * place, a line that says what it is in one breath, a handful of facts a visitor
 * checks before trusting it (founded when, how many learners, accredited by whom),
 * what it stands for, and where it has been. Those are *structured* - a fact is a
 * value and a label, a milestone is a year and a sentence - and typing them into
 * the body editor gives an administrator a table to fight and gives the page
 * nothing it can lay out.
 *
 * So they live here, in one JSON document under the plugin's config, with both
 * languages on every item. The FAQ keeps a question and its translation on one row
 * for the same reason: a fact's value is the same in Arabic and English, and the
 * two labels must never be reordered or deleted apart. The document is small (a
 * dozen short strings), read once per page view, and needs no schema of its own.
 *
 * The hero picture is a file, in its own file area with a fixed item id, because
 * the page has one hero and the body's file area is keyed by row (one per
 * language) - a picture that is the same in both languages does not belong to
 * either row.
 *
 * Everything is resolved to the display language by {@see about::view()} the same
 * way {@see staticpages::view()} resolves the title: interface language, English,
 * then whatever was written.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class about {

    /** @var string The page these sections belong to. */
    const SLUG = 'about';

    /** @var string Config key holding the JSON document. */
    const CONFIG = 'page_about_sections';

    /** @var string The file area the hero picture lives in. Item id is always 0. */
    const HERO_FILEAREA = 'pagehero';

    /** @var int Facts are a strip under the picture; more than this and it wraps into a table. */
    const MAX_FACTS = 6;

    /** @var int Pillars are a grid of three; two rows of it is the most anyone reads. */
    const MAX_PILLARS = 6;

    /** @var int Milestones are a list; past this it is a history, and belongs in the body. */
    const MAX_MILESTONES = 10;

    /**
     * The empty document - every key present, so callers never test for one.
     *
     * @return array
     */
    public static function defaults(): array {
        $bylang = array_fill_keys(footer::langs(), '');

        return [
            'tagline'    => $bylang,
            'lede'       => $bylang,
            'video'      => '',
            'facts'      => [],
            'pillars'    => [],
            'milestones' => [],
        ];
    }

    /**
     * The stored document, exactly as typed, with the defaults filled in underneath.
     *
     * @return array
     */
    public static function load(): array {
        $raw = get_config(staticpages::COMPONENT, self::CONFIG);
        $stored = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $stored = $decoded;
            }
        }

        $data = self::defaults();
        foreach (['tagline', 'lede'] as $key) {
            foreach (footer::langs() as $lang) {
                $data[$key][$lang] = trim((string) ($stored[$key][$lang] ?? ''));
            }
        }
        $data['video'] = trim((string) ($stored['video'] ?? ''));
        $data['facts'] = self::clean_items($stored['facts'] ?? [], ['value'], ['label'], self::MAX_FACTS);
        $data['pillars'] = self::clean_items($stored['pillars'] ?? [], ['icon'], ['title', 'text'], self::MAX_PILLARS);
        $data['milestones'] = self::clean_items($stored['milestones'] ?? [], ['year'], ['title', 'text'],
            self::MAX_MILESTONES);

        return $data;
    }

    /**
     * Write the document.
     *
     * Items that say nothing in any language are dropped rather than stored: a row
     * the administrator added and never filled in is not a fact, and keeping it
     * would draw an empty cell on the page.
     *
     * @param array $data the same shape as {@see defaults()}
     * @return void
     */
    public static function save(array $data): void {
        $clean = self::defaults();
        foreach (['tagline', 'lede'] as $key) {
            foreach (footer::langs() as $lang) {
                $clean[$key][$lang] = trim((string) ($data[$key][$lang] ?? ''));
            }
        }
        $clean['video'] = trim((string) ($data['video'] ?? ''));
        $clean['facts'] = self::clean_items($data['facts'] ?? [], ['value'], ['label'], self::MAX_FACTS);
        $clean['pillars'] = self::clean_items($data['pillars'] ?? [], ['icon'], ['title', 'text'], self::MAX_PILLARS);
        $clean['milestones'] = self::clean_items($data['milestones'] ?? [], ['year'], ['title', 'text'],
            self::MAX_MILESTONES);

        set_config(self::CONFIG, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            staticpages::COMPONENT);
    }

    /**
     * Normalise a list of items to a known shape and drop the empty ones.
     *
     * @param mixed $items whatever was stored or posted
     * @param string[] $shared keys that are the same in every language
     * @param string[] $translated keys that exist once per language
     * @param int $max how many to keep
     * @return array
     */
    protected static function clean_items($items, array $shared, array $translated, int $max): array {
        if (!is_array($items)) {
            return [];
        }

        $clean = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $row = [];
            $saidsomething = false;
            foreach ($shared as $key) {
                $row[$key] = trim((string) ($item[$key] ?? ''));
                $saidsomething = $saidsomething || $row[$key] !== '';
            }
            foreach ($translated as $key) {
                $row[$key] = [];
                foreach (footer::langs() as $lang) {
                    $row[$key][$lang] = trim((string) ($item[$key][$lang] ?? ''));
                    $saidsomething = $saidsomething || $row[$key][$lang] !== '';
                }
            }

            // The icon of a pillar is decoration: a pillar that is nothing but an
            // icon has not been written yet.
            if (in_array('icon', $shared, true)) {
                $saidsomething = false;
                foreach ($translated as $key) {
                    $saidsomething = $saidsomething || implode('', $row[$key]) !== '';
                }
            }

            if ($saidsomething) {
                $clean[] = $row;
            }
            if (count($clean) >= $max) {
                break;
            }
        }

        return $clean;
    }

    // -----------------------------------------------------------------
    // The hero picture.
    // -----------------------------------------------------------------

    /**
     * The file options of the hero picture, shared by the form and whoever saves it.
     *
     * @return array
     */
    public static function hero_file_options(): array {
        global $CFG;

        return [
            'subdirs'        => 0,
            'maxbytes'       => $CFG->maxbytes,
            'maxfiles'       => 1,
            'accepted_types' => ['web_image'],
        ];
    }

    /**
     * The stored hero picture, or null.
     *
     * @return \stored_file|null
     */
    public static function hero_file(): ?\stored_file {
        $files = get_file_storage()->get_area_files(context_system::instance()->id,
            staticpages::COMPONENT, self::HERO_FILEAREA, 0, 'itemid, filepath, filename', false);

        $file = reset($files);
        return $file ?: null;
    }

    /**
     * Where the hero picture is served from, or '' when there is none.
     *
     * @return string URL
     */
    public static function hero_image_url(): string {
        $file = self::hero_file();
        if (!$file) {
            return '';
        }

        return moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
            $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename())->out(false);
    }

    // -----------------------------------------------------------------
    // The film.
    // -----------------------------------------------------------------

    /**
     * Turn the address an administrator pasted into something a page can play.
     *
     * Accepts the addresses people actually copy - a YouTube watch, share, shorts or
     * embed link, a Vimeo page or player link, or a direct .mp4/.webm file - and
     * says which it was, because the page plays a file with a <video> tag and the
     * other two inside a player iframe.
     *
     * @param string $url as typed
     * @return array{provider: string, embed: string, url: string} provider is
     *         youtube|vimeo|file, or '' when the address is not one the page can play
     */
    public static function video(string $url): array {
        $url = trim($url);
        $none = ['provider' => '', 'embed' => '', 'url' => $url];
        if ($url === '' || !preg_match('~^https://~i', $url)) {
            return $none;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $id = '';
        if (preg_match('~(^|\.)youtube(-nocookie)?\.com$~', $host)) {
            if (preg_match('~^/(embed|shorts|live|v)/([A-Za-z0-9_-]{6,})~', $path, $m)) {
                $id = $m[2];
            } else if (!empty($query['v']) && preg_match('~^[A-Za-z0-9_-]{6,}$~', (string) $query['v'])) {
                $id = (string) $query['v'];
            }
            if ($id !== '') {
                return [
                    'provider' => 'youtube',
                    'embed'    => 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0&modestbranding=1&playsinline=1',
                    'url'      => $url,
                ];
            }
            return $none;
        }

        if ($host === 'youtu.be' && preg_match('~^/([A-Za-z0-9_-]{6,})~', $path, $m)) {
            return [
                'provider' => 'youtube',
                'embed'    => 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0&modestbranding=1&playsinline=1',
                'url'      => $url,
            ];
        }

        if (preg_match('~(^|\.)vimeo\.com$~', $host) && preg_match('~/(?:video/)?(\d{5,})(?:/|$)~', $path, $m)) {
            return [
                'provider' => 'vimeo',
                'embed'    => 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1&title=0&byline=0&portrait=0',
                'url'      => $url,
            ];
        }

        if (preg_match('~\.(mp4|webm|m4v)$~i', $path)) {
            return ['provider' => 'file', 'embed' => $url, 'url' => $url];
        }

        return $none;
    }

    // -----------------------------------------------------------------
    // What the page draws.
    // -----------------------------------------------------------------

    /**
     * Everything the About page has beyond its title and body, in the display language.
     *
     * Presentation-free, like {@see staticpages::view()}: the web page and the app
     * draw this each in their own way.
     *
     * @return array
     */
    public static function view(): array {
        $data = self::load();

        $facts = [];
        foreach ($data['facts'] as $item) {
            $label = self::text($item['label']);
            if ($item['value'] === '' && $label === '') {
                continue;
            }
            $facts[] = ['value' => $item['value'], 'label' => $label];
        }

        $pillars = [];
        foreach ($data['pillars'] as $item) {
            $title = self::text($item['title']);
            $text = self::text($item['text']);
            if ($title === '' && $text === '') {
                continue;
            }
            $pillars[] = ['icon' => self::icon($item['icon']), 'title' => $title, 'text' => $text];
        }

        $milestones = [];
        foreach ($data['milestones'] as $item) {
            $title = self::text($item['title']);
            $text = self::text($item['text']);
            if ($item['year'] === '' && $title === '' && $text === '') {
                continue;
            }
            $milestones[] = ['year' => $item['year'], 'title' => $title, 'text' => $text];
        }

        return [
            'tagline'    => self::text($data['tagline']),
            'lede'       => self::text($data['lede']),
            'heroimage'  => self::hero_image_url(),
            'video'      => self::video($data['video']),
            'facts'      => $facts,
            'pillars'    => $pillars,
            'milestones' => $milestones,
        ];
    }

    /**
     * One translated value in the language being displayed.
     *
     * The same ladder as everywhere else on these pages - interface language,
     * English, then whatever was written - so a pillar translated only into Arabic
     * still appears on the English page instead of leaving a hole in the grid.
     *
     * @param array<string, string> $bylang language code => text
     * @return string
     */
    public static function text(array $bylang): string {
        foreach (self::lang_order() as $lang) {
            $value = trim((string) ($bylang[$lang] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * A FontAwesome class string an administrator typed, made safe for a class attribute.
     *
     * Accepts "fa-solid fa-gears", "fa fa-gears" or just "gears"; anything that is
     * not a class name is dropped. Empty means the page draws its default glyph.
     *
     * @param string $icon
     * @return string
     */
    public static function icon(string $icon): string {
        $icon = trim($icon);
        if ($icon === '') {
            return '';
        }

        $classes = preg_split('~\s+~', $icon) ?: [];
        $classes = array_values(array_filter($classes, static function (string $class): bool {
            return (bool) preg_match('~^[a-z0-9-]+$~i', $class);
        }));
        if (empty($classes)) {
            return '';
        }

        // A bare glyph name: give it the family it needs to draw.
        if (count($classes) === 1 && strpos($classes[0], 'fa-') !== 0) {
            return 'fa-solid fa-' . strtolower($classes[0]);
        }
        $hasfamily = false;
        foreach ($classes as $class) {
            if (in_array($class, ['fa', 'fas', 'far', 'fab', 'fa-solid', 'fa-regular', 'fa-brands'], true)) {
                $hasfamily = true;
            }
        }

        return ($hasfamily ? '' : 'fa-solid ') . implode(' ', $classes);
    }

    /**
     * The languages to try, in order.
     *
     * @return string[]
     */
    protected static function lang_order(): array {
        $order = array_merge([current_language()], ['en'], footer::langs());

        return array_values(array_unique(array_filter($order, static function (string $lang): bool {
            return in_array($lang, footer::langs(), true);
        })));
    }
}
