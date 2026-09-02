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
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * The questions and answers behind the Frequently Asked Questions page (AC-4.21.3).
 *
 * A row per question, not a row per translation. The pair "question in English" and
 * "question in Arabic" is one fact about the academy, and splitting it across two
 * rows would let half of it be deleted, reordered or hidden without the other half -
 * which is exactly the failure the page cannot survive, because the answer shown
 * would stop being the answer to the question above it.
 *
 * The columns are therefore named after the languages of {@see footer::langs()}. That
 * is a deliberate trade: a third language means an upgrade step here, and in the
 * footer's config keys, and in the page table's rows. Two languages is what the
 * academy has, and a schema honest about it beats a generic one nobody needs yet.
 *
 * Answers are rich text but hold no files. A FAQ answer is a paragraph and a link;
 * giving each row its own file area would mean an itemid, a pluginfile route and a
 * cleanup path for a picture nobody has ever wanted to put in one.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class faq {

    /** @var string The table. */
    const TABLE = 'local_profilefields_faq';

    /** @var int The gap left between sort orders, so a row can be moved between two. */
    const SORTSTEP = 10;

    /**
     * Every question, in display order, whether shown or not.
     *
     * @return stdClass[] keyed by id
     */
    public static function all(): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'sortorder ASC, id ASC');
    }

    /**
     * The questions the page shows, resolved to the display language.
     *
     * A row whose question is empty in every language is skipped rather than drawn
     * as a blank accordion header - that is a half-typed row, not a question.
     *
     * @return array<int, array{id: int, question: string, answer: string}>
     */
    public static function visible_items(): array {
        $items = [];

        foreach (self::all() as $row) {
            if (empty($row->visible)) {
                continue;
            }

            $question = self::text($row, 'question');
            if (trim($question) === '') {
                continue;
            }

            $items[] = [
                'id'       => (int) $row->id,
                'question' => $question,
                'answer'   => self::answer_html($row),
            ];
        }

        return $items;
    }

    /**
     * One field of one row in the language being displayed.
     *
     * The same ladder the rest of the site uses - interface language, English, then
     * whatever was actually written - so a question translated only into Arabic is
     * still asked on the English page instead of disappearing from it.
     *
     * @param stdClass $row
     * @param string $field question|answer
     * @return string raw stored value
     */
    public static function text(stdClass $row, string $field): string {
        foreach (self::lang_order() as $lang) {
            $value = trim((string) ($row->{$field . $lang} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * One row's answer as display HTML.
     *
     * @param stdClass $row
     * @return string HTML
     */
    public static function answer_html(stdClass $row): string {
        return format_text(self::text($row, 'answer'), (int) $row->answerformat,
            ['context' => context_system::instance()]);
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

    /**
     * Replace the whole list with what the form submitted.
     *
     * The list is saved whole rather than row by row because that is how it is
     * edited: one form, every question on it, one Save. Rows keep their id when the
     * form carried one, so an edit is an update and only a genuinely new question
     * gets a new id.
     *
     * @param array $items each with id, sortorder, visible, questionen, questionar,
     *                     answeren, answerar, answerformat
     * @return void
     */
    public static function save_all(array $items): void {
        global $DB, $USER;

        $existing = $DB->get_records_menu(self::TABLE, null, '', 'id, id AS keep');
        $now = time();
        $kept = [];

        foreach ($items as $item) {
            $record = (object) [
                'sortorder'    => (int) ($item['sortorder'] ?? 0),
                'visible'      => empty($item['visible']) ? 0 : 1,
                'questionen'   => (string) ($item['questionen'] ?? ''),
                'questionar'   => (string) ($item['questionar'] ?? ''),
                'answeren'     => (string) ($item['answeren'] ?? ''),
                'answerar'     => (string) ($item['answerar'] ?? ''),
                'answerformat' => (int) ($item['answerformat'] ?? FORMAT_HTML),
                'timemodified' => $now,
                'usermodified' => (int) ($USER->id ?? 0),
            ];

            $id = (int) ($item['id'] ?? 0);
            if ($id && isset($existing[$id])) {
                $record->id = $id;
                $DB->update_record(self::TABLE, $record);
                $kept[$id] = true;
            } else {
                $kept[(int) $DB->insert_record(self::TABLE, $record)] = true;
            }
        }

        // Whatever the form did not send back was deleted on it.
        $gone = array_diff(array_keys($existing), array_keys($kept));
        if (!empty($gone)) {
            $DB->delete_records_list(self::TABLE, 'id', $gone);
        }
    }

    /**
     * How many questions are on the page.
     *
     * @return int
     */
    public static function count(): int {
        global $DB;
        return $DB->count_records(self::TABLE);
    }
}
