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

/**
 * Reading the bilingual, multi-value strings our short-text course fields hold.
 *
 * A short-text course custom field is edited through the chips widget in
 * local_nit_core, so one field can hold several entries separated by "|", and each
 * entry can itself be a "{mlang en}…{mlang}{mlang ar}…{mlang}" pair. The course page
 * already knows how to read that shape; the catalogue needs the same reading to turn
 * those values into filters, which is what this class is for.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_util {

    /**
     * Resolve a possibly-bilingual value to plain text in the current language.
     *
     * The site's multilang filter normally does this, but it only runs on formatted
     * output — a value being compared or counted never passes through it — so the
     * {mlang} blocks are resolved here directly, exactly as the course page does.
     *
     * @param string|null $raw the stored value
     * @return string '' when there is nothing to show
     */
    public static function ml(?string $raw): string {
        if ($raw === null || trim($raw) === '') {
            return '';
        }
        if (stripos($raw, '{mlang') === false) {
            return trim($raw);
        }

        $lang = current_language();
        if (!preg_match_all('/\{mlang\s+([^}]+)\}(.*?)\{mlang\}/is', $raw, $matches, PREG_SET_ORDER)) {
            return trim($raw);
        }

        $matched = '';
        $other = '';
        $first = null;
        foreach ($matches as $block) {
            $langs = array_map('trim', explode(',', strtolower($block[1])));
            $content = $block[2];
            if ($first === null) {
                $first = $content;
            }
            if (in_array($lang, $langs, true)) {
                $matched .= $content;
            }
            if (in_array('other', $langs, true)) {
                $other .= $content;
            }
        }
        // Current language wins; then an explicit "other" block; then the first block,
        // so a value written in only one language is still shown rather than lost.
        return trim($matched !== '' ? $matched : ($other !== '' ? $other : ($first ?? '')));
    }

    /**
     * Split one short-text value into its separate entries.
     *
     * Splits on the chip separator "|" and on the other list separators a teacher may
     * have typed by hand (newline, bullet). Commas are deliberately NOT separators:
     * plenty of legitimate single values contain one ("Logistics, level 2"), and a
     * filter built from half a phrase is worse than one built from the whole phrase.
     *
     * @param string|null $raw the stored value
     * @return string[] entries in the current language, empties removed
     */
    public static function values(?string $raw): array {
        $text = self::ml($raw);
        if ($text === '') {
            return [];
        }
        $parts = preg_split('/[|\n•]+/u', $text);
        $parts = array_map('trim', $parts === false ? [] : $parts);
        return array_values(array_unique(array_filter($parts, static fn($p) => $p !== '')));
    }

    /**
     * Reduce an already-formatted label back to plain text.
     *
     * Names that came through format_string() arrive HTML-escaped, while a value typed
     * into a short-text field arrives raw. The catalogue puts both in the same option
     * lists, so everything is normalised to plain text here and escaped exactly once at
     * output — otherwise a category called "Health &amp; Safety" would print its own
     * entity on the page.
     *
     * @param string $formatted
     * @return string
     */
    public static function plain(string $formatted): string {
        return trim(html_entity_decode(strip_tags($formatted), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * A stable key for a filter value, so "Intermediate" and "intermediate" are one
     * option and the URL stays readable.
     *
     * @param string $value
     * @return string
     */
    public static function key(string $value): string {
        return \core_text::strtolower(trim($value));
    }

    /**
     * A number as a person would write it: no trailing ".00" on whole numbers, but any
     * genuine decimal part kept.
     *
     * @param float|int|string|null $value
     * @return string '' when there is no number
     */
    public static function number($value): string {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return '';
        }
        $float = (float) $value;
        return ($float == (int) $float) ? (string) (int) $float : rtrim(rtrim(number_format($float, 2, '.', ''), '0'), '.');
    }
}
