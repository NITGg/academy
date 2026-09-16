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
 * The hero picture and the film are files, each in its own file area with a fixed
 * item id, because the page has one of each and the body's file area is keyed by
 * row (one per language) - a picture that is the same in both languages does not
 * belong to either row.
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

    /** @var string The file area the film lives in. Item id is always 0. */
    const VIDEO_FILEAREA = 'pagevideo';

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
    // The hero picture and the film.
    //
    // Both are files, one each, in their own file areas at item id 0 - the page
    // has one picture and one film whatever language it is read in. The film is
    // uploaded rather than linked: an administrator's phone footage of the
    // workshop is what this page wants, and a hosted-video link would bring a
    // third party's player, cookies and branding onto the site's front door.
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
     * The file options of the film.
     *
     * Only the formats a browser plays natively: an uploaded .mov or .avi would
     * save fine and then draw a dead player, so those are refused at the picker.
     *
     * @return array
     */
    public static function video_file_options(): array {
        global $CFG;

        return [
            'subdirs'        => 0,
            'maxbytes'       => $CFG->maxbytes,
            'maxfiles'       => 1,
            'accepted_types' => ['.mp4', '.m4v', '.webm', '.ogv'],
        ];
    }

    /**
     * The one file in a hero file area, or null.
     *
     * @param string $filearea HERO_FILEAREA or VIDEO_FILEAREA
     * @return \stored_file|null
     */
    protected static function area_file(string $filearea): ?\stored_file {
        $files = get_file_storage()->get_area_files(context_system::instance()->id,
            staticpages::COMPONENT, $filearea, 0, 'itemid, filepath, filename', false);

        $file = reset($files);
        return $file ?: null;
    }

    /**
     * Where a stored file is served from.
     *
     * @param \stored_file $file
     * @return string URL
     */
    protected static function file_url(\stored_file $file): string {
        return moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
            $file->get_filearea(), $file->get_itemid(), $file->get_filepath(), $file->get_filename())->out(false);
    }

    /**
     * The stored hero picture, or null.
     *
     * @return \stored_file|null
     */
    public static function hero_file(): ?\stored_file {
        return self::area_file(self::HERO_FILEAREA);
    }

    /**
     * Where the hero picture is served from, or '' when there is none.
     *
     * @return string URL
     */
    public static function hero_image_url(): string {
        $file = self::hero_file();
        return $file ? self::file_url($file) : '';
    }

    /**
     * The stored film, or null.
     *
     * @return \stored_file|null
     */
    public static function video_file(): ?\stored_file {
        return self::area_file(self::VIDEO_FILEAREA);
    }

    /**
     * The film, as the page and the app play it.
     *
     * The shape is kept general - `provider` says how to play `embed` - so a
     * client written against it keeps working if a hosted provider is ever
     * allowed again. Today the only provider is `file`: a direct address the
     * platform's own <video> tag (or the app's native player) plays.
     *
     * @return array{provider: string, embed: string, url: string, mimetype: string}
     *         provider is 'file', or '' when there is no film
     */
    public static function video(): array {
        $file = self::video_file();
        if (!$file) {
            return ['provider' => '', 'embed' => '', 'url' => '', 'mimetype' => ''];
        }

        $url = self::file_url($file);
        return ['provider' => 'file', 'embed' => $url, 'url' => $url, 'mimetype' => (string) $file->get_mimetype()];
    }

    /**
     * Where "Browse courses" goes - the course catalogue, the same door the home
     * page opens. One place, so the web page and the app agree.
     *
     * @return moodle_url
     */
    public static function courses_url(): moodle_url {
        return new moodle_url('/local/nit_category/catalogue.php');
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
            'video'      => self::video(),
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
