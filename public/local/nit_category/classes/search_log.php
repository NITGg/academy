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
 * The record of what learners looked for and did not find (AC-4.22.4).
 *
 * A search that returns nothing is the most useful thing a catalogue can tell its owner:
 * it is a course somebody wanted to buy, named in their own words, on a day EAAC could
 * still do something about it. Successful searches are deliberately NOT recorded — they
 * answer nobody's question, and every row kept is one more thing to justify holding.
 *
 * Terms are aggregated rather than appended, on the folded key {@see text_util::normalise()}
 * produces, so "إدارة", "ادارة" and "اداره" are one line in the report with a count of
 * three rather than three lines that each look like a one-off. The spelling shown is the
 * one first typed; the folded key is never displayed, because folding destroys correct
 * spelling by design.
 *
 * Nothing here identifies who searched. That is not an oversight: the report is about the
 * gap in the catalogue, not about the learner, and a table with no user column cannot leak
 * one. It is also why the plugin can honestly declare it stores no personal data.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_log {

    /** @var string The table. Short of the plugin's full frankenname, which will not fit. */
    const TABLE = 'local_nit_cat_searchlog';

    /** @var int Longest term recorded; anything past this is a paste, not a search. */
    const MAX_TERM = 191;

    /** @var string Order the report by how often a term was searched. */
    const SORT_HITS = 'hits';
    /** @var string Order the report by when a term was last searched. */
    const SORT_RECENT = 'recent';
    /** @var string Order the report alphabetically. */
    const SORT_TERM = 'term';

    /**
     * Record one search that found nothing.
     *
     * Safe to call on any request, including one that found something: a term with no
     * searchable words in it (punctuation, a single letter) is dropped here rather than
     * at every call site.
     *
     * @param string $query the term as typed
     * @return void
     */
    public static function record_miss(string $query): void {
        global $DB;

        $words = text_util::search_words($query);
        if (empty($words)) {
            return;
        }

        $key = \core_text::substr(implode(' ', $words), 0, self::MAX_TERM);

        // "???" and "---" reach here as perfectly good words — folding strips Arabic
        // punctuation but not Latin — and they are somebody leaning on the keyboard, not
        // a course EAAC is missing. A term earns a line in the report by containing at
        // least one letter or digit.
        if (!preg_match('/[\p{L}\p{N}]/u', $key)) {
            return;
        }
        $term = \core_text::substr(trim($query), 0, self::MAX_TERM);
        $now = time();

        try {
            $existing = $DB->get_record(self::TABLE, ['termkey' => $key], 'id, hits', IGNORE_MISSING);
            if ($existing) {
                $DB->update_record(self::TABLE, (object) [
                    'id'       => $existing->id,
                    'hits'     => (int) $existing->hits + 1,
                    'lang'     => current_language(),
                    'timelast' => $now,
                ]);
                return;
            }

            $DB->insert_record(self::TABLE, (object) [
                'termkey'   => $key,
                'term'      => $term,
                'lang'      => current_language(),
                'hits'      => 1,
                'timefirst' => $now,
                'timelast'  => $now,
            ]);
        } catch (\dml_exception $e) {
            // Two visitors can miss on the same term in the same second, and the unique key
            // is what stops that becoming two lines in the report. Losing the second count
            // is not worth a fatal error on a search page, so the loser of the race retries
            // the increment once and then gives up quietly.
            $existing = $DB->get_record(self::TABLE, ['termkey' => $key], 'id, hits', IGNORE_MISSING);
            if ($existing) {
                try {
                    $DB->set_field(self::TABLE, 'hits', (int) $existing->hits + 1, ['id' => $existing->id]);
                    $DB->set_field(self::TABLE, 'timelast', $now, ['id' => $existing->id]);
                } catch (\dml_exception $ignored) {
                    debugging('local_nit_category: could not record the failed search term.', DEBUG_DEVELOPER);
                }
            }
        }
    }

    /**
     * The orders the report can be read in.
     *
     * @return array<string, string> key => label
     */
    public static function sort_options(): array {
        return [
            self::SORT_HITS   => get_string('searchlogsorthits', 'local_nit_category'),
            self::SORT_RECENT => get_string('searchlogsortrecent', 'local_nit_category'),
            self::SORT_TERM   => get_string('searchlogsortterm', 'local_nit_category'),
        ];
    }

    /**
     * One page of the report.
     *
     * @param string $sort one of {@see self::sort_options()}
     * @param int $page zero-based
     * @param int $perpage
     * @return \stdClass[]
     */
    public static function terms(string $sort = self::SORT_HITS, int $page = 0, int $perpage = 50): array {
        global $DB;

        $orders = [
            self::SORT_HITS   => 'hits DESC, timelast DESC',
            self::SORT_RECENT => 'timelast DESC, hits DESC',
            self::SORT_TERM   => 'term ASC',
        ];
        $order = $orders[$sort] ?? $orders[self::SORT_HITS];

        return array_values($DB->get_records(self::TABLE, null, $order, '*',
            $page * $perpage, $perpage));
    }

    /**
     * How many distinct terms are on record.
     *
     * @return int
     */
    public static function count_terms(): int {
        global $DB;
        return $DB->count_records(self::TABLE);
    }

    /**
     * How many failed searches those terms represent.
     *
     * @return int
     */
    public static function count_searches(): int {
        global $DB;
        return (int) $DB->get_field_sql('SELECT COALESCE(SUM(hits), 0) FROM {' . self::TABLE . '}');
    }

    /**
     * Forget one term — the answer to "we have added that course now".
     *
     * @param int $id
     * @return void
     */
    public static function delete(int $id): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * Empty the log.
     *
     * @return void
     */
    public static function purge(): void {
        global $DB;
        $DB->delete_records(self::TABLE);
    }
}
