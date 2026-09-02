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

    /**
     * Every language variant of a possibly-bilingual value, joined into one string.
     *
     * {@see self::ml()} answers "what should the reader see", which is one language.
     * Searching asks the opposite question — AC-4.8.2 requires that typing an Arabic word
     * finds a course whose Arabic title is stored, even while the interface is in English
     * — so this keeps every block instead of choosing between them.
     *
     * @param string|null $raw the stored value
     * @return string '' when there is nothing to search
     */
    public static function ml_all(?string $raw): string {
        if ($raw === null || trim($raw) === '') {
            return '';
        }
        if (stripos($raw, '{mlang') === false) {
            return trim($raw);
        }

        if (!preg_match_all('/\{mlang\s+([^}]+)\}(.*?)\{mlang\}/is', $raw, $matches, PREG_SET_ORDER)) {
            return trim($raw);
        }

        $parts = [];
        foreach ($matches as $block) {
            $parts[] = $block[2];
        }
        // Text sitting outside any {mlang} block belongs to every language, so it is kept
        // too — a title written as "ISO 9001 {mlang ar}الجودة{mlang}" must match on "ISO".
        $outside = preg_replace('/\{mlang\s+[^}]+\}.*?\{mlang\}/is', ' ', $raw);
        if (trim((string) $outside) !== '') {
            $parts[] = $outside;
        }

        return trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)) ?? '');
    }

    /**
     * Fold text to the form a search compares against (AC-4.8.3).
     *
     * Arabic is written with optional marks and with letters that people spell more than
     * one way, so the literal string a learner types is very often not the literal string
     * an administrator stored. "إدارة" and "ادارة" are the same word; so are "دورة" and
     * "دوره". Both sides of the comparison are folded through here, so the search box
     * behaves the way an Arabic reader expects rather than demanding exact orthography.
     *
     * The folding is deliberately lossy and one-way. It is only ever used for matching —
     * never for anything that is displayed — because it destroys correct spelling.
     *
     * @param string|null $raw
     * @return string lower-cased, folded, whitespace-collapsed
     */
    public static function normalise(?string $raw): string {
        if ($raw === null) {
            return '';
        }
        $text = \core_text::strtolower(trim($raw));
        if ($text === '') {
            return '';
        }

        // Harakat, tatweel and the Quranic annotation marks: decoration over the letters,
        // and almost never typed into a search box even when they are present in the data.
        $text = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0640}\x{0670}\x{06D6}-\x{06ED}]/u', '', $text) ?? $text;

        // The letters with more than one accepted spelling. Each group folds to the form
        // people reach for first on a keyboard.
        $text = strtr($text, [
            // Alef with any hamza or madda, plus the wasla and the Quranic variants.
            "\u{0622}" => "\u{0627}", "\u{0623}" => "\u{0627}", "\u{0625}" => "\u{0627}",
            "\u{0671}" => "\u{0627}", "\u{0672}" => "\u{0627}", "\u{0673}" => "\u{0627}",
            "\u{0675}" => "\u{0627}",
            // Hamza carried on waw or yeh, and the bare hamza.
            "\u{0624}" => "\u{0648}", "\u{0626}" => "\u{064A}", "\u{0621}" => '',
            // Alef maqsura, routinely typed as a plain yeh.
            "\u{0649}" => "\u{064A}",
            // Taa marbuta, routinely typed as a haa.
            "\u{0629}" => "\u{0647}",
            // Farsi/Urdu forms that reach us from copied text.
            "\u{06CC}" => "\u{064A}", "\u{06A9}" => "\u{0643}",
            // Arabic-Indic and extended Arabic-Indic digits.
            "\u{0660}" => '0', "\u{0661}" => '1', "\u{0662}" => '2', "\u{0663}" => '3',
            "\u{0664}" => '4', "\u{0665}" => '5', "\u{0666}" => '6', "\u{0667}" => '7',
            "\u{0668}" => '8', "\u{0669}" => '9',
            "\u{06F0}" => '0', "\u{06F1}" => '1', "\u{06F2}" => '2', "\u{06F3}" => '3',
            "\u{06F4}" => '4', "\u{06F5}" => '5', "\u{06F6}" => '6', "\u{06F7}" => '7',
            "\u{06F8}" => '8', "\u{06F9}" => '9',
            // Arabic punctuation that would otherwise glue itself to a word.
            "\u{060C}" => ' ', "\u{061B}" => ' ', "\u{061F}" => ' ', "\u{06D4}" => ' ',
        ]);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * Split a typed query into the folded words every match must contain.
     *
     * @param string|null $query
     * @return string[] possibly empty
     */
    public static function search_words(?string $query): array {
        $folded = self::normalise($query);
        if ($folded === '') {
            return [];
        }
        return preg_split('/\s+/u', $folded, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Compare two labels the way the reader's language orders them.
     *
     * `core_collator` has no compare() — it only offers asort()/ksort() helpers — so
     * calling one was a fatal error waiting for a second thing to sort. It hid for a
     * while because usort() never invokes the comparator on a one-element array, which
     * is exactly what a development site with a single category has.
     *
     * This is the missing piece: PHP's own Collator, built on the locale Moodle declares
     * in langconfig, which is the same object core_collator sorts with internally. It
     * matters for Arabic, where byte order and alphabetical order are not the same
     * thing.
     *
     * Falls back to a case-folded strcmp() when ext-intl is absent — an imperfect order
     * is worth having where the alternative is a page that will not render.
     *
     * @param string $a
     * @param string $b
     * @return int less than, equal to, or greater than zero
     */
    public static function collate(string $a, string $b): int {
        static $collator = null;
        static $locale = null;

        $current = get_string('locale', 'langconfig');
        // Rebuilt when the language changes mid-request, which force_current_language()
        // does on the bilingual feeds.
        if ($collator === null || $locale !== $current) {
            $locale = $current;
            $collator = false;
            if (class_exists('Collator', false)) {
                $candidate = new \Collator($current);
                // A negative code is a fallback warning ("asked for de_CH, used de"),
                // which is fine. A positive one means the collator is unusable.
                if ($candidate instanceof \Collator && $candidate->getErrorCode() <= 0) {
                    $collator = $candidate;
                }
            }
        }

        if ($collator instanceof \Collator) {
            $result = $collator->compare($a, $b);
            if ($result !== false) {
                return (int) $result;
            }
        }

        return strcmp(\core_text::strtolower($a), \core_text::strtolower($b));
    }
}
