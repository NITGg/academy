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

namespace local_nit_category;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * The front-page hero's words - "Learn Today / Advance Tomorrow" and the paragraph under it.
 *
 * The hero is not a template of ours: it is `theme/nit/blocks/home_6_categories1.html`
 * pasted into an NIT Section block on the site front page, and edited there. So the words
 * an administrator sees on the site live in that block instance's stored HTML, still
 * carrying their {mlang en}…{mlang}{mlang ar}…{mlang} pairs, and that is where this class
 * reads them from. The packaged file is only the fallback for a site that has not pasted
 * the block yet (a fresh install, a dev copy), so the app is never shown an empty hero.
 *
 * What is read is decided by the block's own structure, not by its wording: the element
 * marked `data-nit-hero-split`, its first `h1` (one `span` per headline line) and the first
 * `p` after that heading. Re-wording the hero in the editor therefore changes the app with
 * no release; only re-arranging those elements would.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class home_hero {

    /** @var string the attribute that marks the hero's text + artwork row in the block */
    const MARKER = 'data-nit-hero-split';

    /** @var string the packaged copy of the block, read when no front-page block holds it */
    const SOURCE_FILE = '/theme/nit/blocks/home_6_categories1.html';

    /** @var string the block type the hero is pasted into */
    const BLOCKNAME = 'nit_section';

    /**
     * The hero's words, still bilingual, with where they came from.
     *
     * @return array{source: string, blockinstanceid: int, headline: string[], description: string}
     *         `source` is 'block' or 'file'; `headline` is one raw value per line of the
     *         heading; every value may still contain {mlang} pairs. All empty when the
     *         hero cannot be found anywhere.
     */
    public static function raw(): array {
        $empty = ['source' => '', 'blockinstanceid' => 0, 'headline' => [], 'description' => ''];

        [$html, $source, $instanceid] = self::locate();
        if ($html === '') {
            return $empty;
        }

        $parsed = self::parse($html);
        if ($parsed === null) {
            return $empty;
        }

        return ['source' => $source, 'blockinstanceid' => $instanceid] + $parsed;
    }

    /**
     * The hero's words resolved to the current language, whitespace collapsed, no markup.
     *
     * Resolution is {@see text_util::ml()} - the same reading the catalogue gives every
     * other stored {mlang} value - rather than the multilang filter, so the answer does
     * not depend on which filter is switched on, or on whether it runs for format_string().
     *
     * @return array{source: string, blockinstanceid: int, headline: string[], description: string}
     */
    public static function resolved(): array {
        $raw = self::raw();
        $raw['headline'] = array_values(array_filter(array_map(
            static fn(string $line): string => self::clean(text_util::ml($line)),
            $raw['headline']
        ), static fn(string $line): bool => $line !== ''));
        $raw['description'] = self::clean(text_util::ml($raw['description']));
        return $raw;
    }

    /**
     * Find the HTML that holds the hero: the live front-page block first, then the file.
     *
     * "Front page" is any nit_section instance whose parent context is the front-page
     * course's (the site course, SITEID - not the system context, which is where the
     * dashboard's blocks live) and whose page type is the site index, in weight order -
     * the same set the front page renders. The first one carrying the marker wins.
     *
     * @return array{0: string, 1: string, 2: int} html, source ('block'|'file'|''), block instance id
     */
    protected static function locate(): array {
        global $DB, $CFG;

        $instances = $DB->get_records(
            'block_instances',
            ['blockname' => self::BLOCKNAME, 'parentcontextid' => \context_course::instance(SITEID)->id],
            'defaultweight ASC, id ASC',
            'id, pagetypepattern, configdata'
        );
        foreach ($instances as $instance) {
            if (strpos((string) $instance->pagetypepattern, 'site-index') !== 0) {
                continue;
            }
            $config = $instance->configdata !== '' ? unserialize_object(base64_decode($instance->configdata)) : null;
            $text = (string) ($config->text ?? '');
            if ($text !== '' && strpos($text, self::MARKER) !== false) {
                return [$text, 'block', (int) $instance->id];
            }
        }

        $file = $CFG->dirroot . self::SOURCE_FILE;
        if (is_readable($file)) {
            $text = (string) file_get_contents($file);
            if (strpos($text, self::MARKER) !== false) {
                return [$text, 'file', 0];
            }
        }

        return ['', '', 0];
    }

    /**
     * Pull the headline lines and the paragraph out of the block's HTML.
     *
     * The {mlang} tags are plain text to the parser, so they survive into the returned
     * values untouched, which is what the resolver needs.
     *
     * @param string $html the block's stored text
     * @return array{headline: string[], description: string}|null null when the marker or
     *         the heading is missing
     */
    protected static function parse(string $html): ?array {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        // The XML declaration is the one reliable way to tell DOMDocument the bytes are
        // UTF-8; without it the Arabic comes back double-encoded.
        $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);

        $split = $xpath->query('//*[@' . self::MARKER . ']')->item(0);
        if (!$split instanceof DOMElement) {
            return null;
        }

        $heading = $xpath->query('.//h1', $split)->item(0);
        if (!$heading instanceof DOMElement) {
            return null;
        }

        $headline = [];
        foreach ($xpath->query('./span', $heading) as $span) {
            $headline[] = trim($span->textContent);
        }
        // A heading written without spans is one line.
        if ($headline === []) {
            $headline[] = trim($heading->textContent);
        }

        $description = '';
        $paragraph = $xpath->query('./following-sibling::p', $heading)->item(0);
        if ($paragraph instanceof DOMElement) {
            $description = trim($paragraph->textContent);
        }

        return ['headline' => $headline, 'description' => $description];
    }

    /**
     * One line of plain text: the editor's line breaks and the file's indentation collapsed.
     *
     * @param string $text
     * @return string
     */
    protected static function clean(string $text): string {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
    }
}
