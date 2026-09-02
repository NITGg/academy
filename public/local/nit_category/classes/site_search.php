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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/local/nit_category/lib.php');

/**
 * The one search behind the header search box (SRS 4.22).
 *
 * There is a single control in the navbar and it answers about the whole shop window at
 * once: the courses and the subject areas. That is the only thing new here — the matching
 * itself is the catalogue's, not a second engine written alongside it, because two engines
 * would eventually disagree and the header would start finding things the catalogue page
 * could not (AC-4.22.1).
 *
 * Because the catalogue is doing the matching, both of the properties it was built with
 * come free:
 *
 *   AC-4.22.2 — every language of a value is searched whatever the interface language is
 *               ({@see text_util::ml_all()}), and Arabic spelling variants are folded on
 *               both sides ({@see text_util::normalise()}), so an English interface finds
 *               a course stored only in Arabic.
 *   AC-4.22.3 — the two kinds of answer are counted and returned separately, so the page
 *               and the drop-down can head each group with its own count and print a total.
 *
 * The one deliberate difference from the catalogue is the order. A catalogue is browsed, so
 * it sorts by popularity or price; a search is aimed, so results come back by relevance —
 * a title hit above a category hit above a hit buried in a course description — with
 * enrolments as the tie-break.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_search {

    /** @var string Group key: matching courses. */
    const GROUP_COURSES = 'courses';

    /** @var string Group key: matching subject areas. */
    const GROUP_CATEGORIES = 'categories';

    /**
     * @var int Shortest query answered.
     *
     * One character matches most of the catalogue, which is not an answer to anything, and
     * it would fire a full scan on every keystroke of the drop-down. Two is the shortest
     * query that can be meant: Arabic in particular has real two-letter words.
     */
    const MIN_LENGTH = 2;

    /** @var int Longest query kept. Past this it is a paste, not a search. */
    const MAX_LENGTH = 120;

    /** @var string the query as the visitor typed it, trimmed and capped */
    private $query;

    /** @var string[] the folded words every result must contain */
    private $words;

    /** @var catalogue|null the shared engine, built on first use */
    private $catalogue = null;

    /** @var array[]|null ranked course rows, or null until first asked for */
    private $courses = null;

    /** @var array[]|null category cards, or null until first asked for */
    private $categories = null;

    /**
     * @param string $query as typed
     */
    public function __construct(string $query) {
        $this->query = \core_text::substr(trim($query), 0, self::MAX_LENGTH);
        $this->words = text_util::search_words($this->query);
    }

    /**
     * The search this request is asking for.
     *
     * @return self
     */
    public static function from_request(): self {
        return new self((string) optional_param('q', '', PARAM_TEXT));
    }

    /**
     * The query as typed — safe to print, unlike the folded form.
     *
     * @return string
     */
    public function query(): string {
        return $this->query;
    }

    /**
     * Whether there is enough of a query to answer at all.
     *
     * @return bool
     */
    public function is_answerable(): bool {
        return !empty($this->words) && \core_text::strlen(implode('', $this->words)) >= self::MIN_LENGTH;
    }

    // =========================================================================
    // Results.
    // =========================================================================

    /**
     * The shared matching engine, with this search's term already in force.
     *
     * @return catalogue
     */
    private function catalogue(): catalogue {
        if ($this->catalogue === null) {
            // Whole site, no root: the header box is not standing inside a category.
            $this->catalogue = new catalogue(0);
            $this->catalogue->set_search($this->query);
        }
        return $this->catalogue;
    }

    /**
     * Matching courses, most relevant first.
     *
     * @return array[] catalogue rows
     */
    public function courses(): array {
        if ($this->courses === null) {
            $this->courses = $this->is_answerable() ? $this->rank($this->catalogue()->matches()) : [];
        }
        return $this->courses;
    }

    /**
     * Matching subject areas, biggest first.
     *
     * Subcategories are included — a learner searching "Customs" means the subject, and
     * whether the academy filed it under International Trade is not their problem.
     *
     * @return array[] category cards, as {@see category_browser} builds them
     */
    public function categories(): array {
        if ($this->categories === null) {
            if (!$this->is_answerable()) {
                $this->categories = [];
            } else {
                $browser = new category_browser($this->catalogue(), true,
                    category_browser::SORT_COURSES, true);
                $this->categories = $browser->rows();
            }
        }
        return $this->categories;
    }

    /**
     * Everything found, across both kinds (AC-4.22.3).
     *
     * @return int
     */
    public function total(): int {
        return count($this->courses()) + count($this->categories());
    }

    /**
     * Whether the search found anything at all.
     *
     * @return bool
     */
    public function has_results(): bool {
        return $this->total() > 0;
    }

    /**
     * The results grouped by kind, in the order a learner reads them.
     *
     * Courses lead because a course is what somebody came to buy; the subject area is the
     * broader second answer. An empty group is still returned, so a caller can say "no
     * courses, 2 categories" rather than silently dropping half the question.
     *
     * @param int $limit most rows to return per group, or 0 for all of them
     * @return array[] one entry per group: key, label, count, rows, more, url
     */
    public function groups(int $limit = 0): array {
        $courses = $this->courses();
        $categories = $this->categories();

        return [
            [
                'key'   => self::GROUP_COURSES,
                'label' => get_string('searchgroupcourses', 'local_nit_category'),
                'count' => count($courses),
                'rows'  => $limit > 0 ? array_slice($courses, 0, $limit) : $courses,
                'more'  => $limit > 0 ? max(0, count($courses) - $limit) : 0,
                'url'   => $this->catalogue_url(),
            ],
            [
                'key'   => self::GROUP_CATEGORIES,
                'label' => get_string('searchgroupcategories', 'local_nit_category'),
                'count' => count($categories),
                'rows'  => $limit > 0 ? array_slice($categories, 0, $limit) : $categories,
                'more'  => $limit > 0 ? max(0, count($categories) - $limit) : 0,
                'url'   => $this->categories_url(),
            ],
        ];
    }

    // =========================================================================
    // Presentation.
    // =========================================================================

    /**
     * Everything one course result prints, ready for the page and for the drop-down.
     *
     * Built here rather than in either template so the two never drift: the row in the
     * header panel and the row on the results page are the same row, drawn at two sizes.
     *
     * @param array $row a catalogue row
     * @return array
     */
    public function present_course(array $row): array {
        $course = $row['course'];
        $context = \context_course::instance((int) $row['id']);

        // The course's own overview picture; '' leaves the tinted placeholder in its place.
        $image = '';
        foreach (get_file_storage()->get_area_files($context->id, 'course', 'overviewfiles', 0,
                'sortorder DESC, id DESC', false) as $file) {
            $image = \moodle_url::make_pluginfile_url($context->id, 'course', 'overviewfiles', null,
                $file->get_filepath(), $file->get_filename())->out(false);
            break;
        }

        ['chips' => $chips] = $this->catalogue()->card_labels($row);

        return [
            'id'        => (int) $row['id'],
            // Already escaped by format_string(), like every other card on the site.
            'namehtml'  => $course->get_formatted_name(),
            'nameplain' => text_util::plain($course->get_formatted_name()),
            'url'       => (new \moodle_url('/course/view.php', ['id' => $row['id']]))->out(false),
            'image'     => $image,
            'catname'   => $row['catname'],
            'chips'     => $chips,
            'pricing'   => pricing::info((int) $row['id']),
        ];
    }

    // =========================================================================
    // Links.
    // =========================================================================

    /**
     * This search as its own page.
     *
     * @return string
     */
    public function url(): string {
        return (new \moodle_url('/local/nit_category/search.php', ['q' => $this->query]))->out(false);
    }

    /**
     * The same term handed to the catalogue, where it can be filtered and sorted.
     *
     * @return string
     */
    public function catalogue_url(): string {
        return (new \moodle_url('/local/nit_category/catalogue.php', ['q' => $this->query]))->out(false);
    }

    /**
     * The same term handed to the all-categories grid.
     *
     * @return string
     */
    public function categories_url(): string {
        return (new \moodle_url('/local/nit_category/categories.php',
            ['q' => $this->query, 'subs' => 1]))->out(false);
    }

    // =========================================================================
    // Ranking.
    // =========================================================================

    /**
     * Order matches by how well they answer the question.
     *
     * @param array[] $rows catalogue rows
     * @return array[]
     */
    private function rank(array $rows): array {
        $scores = [];
        foreach ($rows as $index => $row) {
            $scores[$index] = $this->score($row);
        }

        // Sorting the keys rather than the rows keeps the comparator cheap: the row arrays
        // carry a course object apiece and are never copied by this.
        $keys = array_keys($rows);
        usort($keys, function ($a, $b) use ($rows, $scores) {
            if ($scores[$a] !== $scores[$b]) {
                return $scores[$b] <=> $scores[$a];
            }
            if ($rows[$a]['popularity'] !== $rows[$b]['popularity']) {
                return $rows[$b]['popularity'] <=> $rows[$a]['popularity'];
            }
            return text_util::collate(
                text_util::plain($rows[$a]['course']->get_formatted_name()),
                text_util::plain($rows[$b]['course']->get_formatted_name()));
        });

        $out = [];
        foreach ($keys as $key) {
            $out[] = $rows[$key];
        }
        return $out;
    }

    /**
     * How strongly one course answers the query.
     *
     * A hit in the title is what the learner meant; a hit in the subject area is the next
     * best thing; a hit anywhere else (the summary, a field value) still belongs in the
     * results but not at the top of them.
     *
     * @param array $row
     * @return int 0-3, higher is better
     */
    private function score(array $row): int {
        $title = text_util::normalise(text_util::ml_all((string) $row['course']->fullname));
        if ($title === implode(' ', $this->words)) {
            return 3;
        }
        if ($this->contains_all($title)) {
            return 2;
        }
        if ($this->contains_all(text_util::normalise($row['catname']))) {
            return 1;
        }
        return 0;
    }

    /**
     * Does this folded text carry every word of the query?
     *
     * @param string $haystack already folded
     * @return bool
     */
    private function contains_all(string $haystack): bool {
        if ($haystack === '') {
            return false;
        }
        foreach ($this->words as $word) {
            if (strpos($haystack, $word) === false) {
                return false;
            }
        }
        return true;
    }
}
