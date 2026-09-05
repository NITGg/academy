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

/**
 * The header search, answered over the web-service layer for the mobile app (SRS 4.22).
 *
 * The app had no way to search subject areas: it asks core's `core_course_search_courses`,
 * whose `criterianame` is core Moodle's own list and which returns courses and nothing
 * else. Rather than grow a second matcher inside the app or a second one here, this exposes
 * the search the site already runs — {@see site_search}, the one engine behind the navbar
 * drop-down and the results page — so the app, the panel and the page can never disagree
 * about what a word finds.
 *
 * Everything that engine was built with therefore arrives for free:
 *
 *   AC-4.22.2 — every language of a value is searched whatever the caller's language is,
 *               and Arabic spelling variants are folded on both sides, so an app running
 *               in English finds a course stored only in Arabic.
 *   AC-4.22.3 — courses and subject areas are counted and returned separately, so the app
 *               can head each section with its own count.
 *   Visibility — the pool is core_course_category::get_courses(), which drops what the
 *               caller may not see. The app's old path filtered only `id != 1` and trusted
 *               the server; here that trust is actually warranted.
 *
 * Two things are new, and both exist because a phone is not the navbar drop-down:
 *
 *   Paging — groups() slices from the top, because a drop-down shows five and a page shows
 *            everything. A list that scrolls needs an offset, so the two groups are paged
 *            independently here rather than by bending groups(), which the web pages are
 *            using as it stands.
 *   Rows   — a course hit comes back in exactly the shape
 *            `local_payments_get_courses_with_pricing` returns, because that is what the
 *            app's Course.fromJson already parses. The point is that a search stops costing
 *            two round trips: the ids were resolved here, so the rehydration is done here
 *            too. `format=ids` keeps the old two-call flow available for a client that
 *            would rather not change.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_category\external;

defined('MOODLE_INTERNAL') || die();

use core_course_category;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use local_nit_category\site_search;

global $CFG;
require_once($CFG->dirroot . '/course/externallib.php');
require_once($CFG->dirroot . '/local/nit_category/lib.php');

/**
 * local_nit_category_search — courses and subject areas for one query.
 */
class search extends external_api {

    /** @var string Full course rows, priced and ready to render. */
    const FORMAT_FULL = 'full';

    /** @var string Ids only, for a caller that rehydrates them itself. */
    const FORMAT_IDS = 'ids';

    /** @var int Rows per group when the caller does not say. */
    const DEFAULT_PERPAGE = 20;

    /**
     * @var int Most rows one page may ask for, per group.
     *
     * Every course row prices itself and looks up a picture, so an unbounded page is a way
     * to make one request do a hundred requests' work. The cap is not a limit on what can
     * be reached — `count` and `more` say what is behind it, and the next page fetches it.
     */
    const MAX_PERPAGE = 100;

    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(
                PARAM_TEXT,
                'What the visitor typed. Shorter than minlength answers nothing rather than '
                . 'matching most of the catalogue.',
                VALUE_DEFAULT,
                ''
            ),
            'courseperpage' => new external_value(PARAM_INT,
                'Course rows per page (default 20, max 100).', VALUE_DEFAULT, self::DEFAULT_PERPAGE),
            'coursepage' => new external_value(PARAM_INT,
                'Zero-based page of course results.', VALUE_DEFAULT, 0),
            'categoryperpage' => new external_value(PARAM_INT,
                'Category rows per page (default 20, max 100).', VALUE_DEFAULT, self::DEFAULT_PERPAGE),
            'categorypage' => new external_value(PARAM_INT,
                'Zero-based page of category results.', VALUE_DEFAULT, 0),
            'format' => new external_value(PARAM_ALPHA,
                'full (default) returns whole priced course rows; ids returns the ids only, for '
                . 'a caller that already has its own rehydration call.',
                VALUE_DEFAULT, self::FORMAT_FULL),
            'country' => new external_value(PARAM_ALPHA,
                'ISO country to price against, as local_payments_get_courses_with_pricing takes '
                . 'it. Ignored where the account has a country of its own.', VALUE_DEFAULT, ''),
            'lang' => new external_value(PARAM_LANG,
                'Display language, e.g. en or ar (optional).', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG,
                'Display language (alias of lang, optional).', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Run the search.
     *
     * @param string $query
     * @param int $courseperpage
     * @param int $coursepage
     * @param int $categoryperpage
     * @param int $categorypage
     * @param string $format one of the FORMAT_* constants
     * @param string $country ISO country for pricing
     * @param string $lang
     * @param string $alang
     * @return array
     */
    public static function execute(string $query = '', int $courseperpage = self::DEFAULT_PERPAGE,
            int $coursepage = 0, int $categoryperpage = self::DEFAULT_PERPAGE, int $categorypage = 0,
            string $format = self::FORMAT_FULL, string $country = '', string $lang = '',
            string $alang = ''): array {

        $params = self::validate_parameters(self::execute_parameters(), [
            'query'           => $query,
            'courseperpage'   => $courseperpage,
            'coursepage'      => $coursepage,
            'categoryperpage' => $categoryperpage,
            'categorypage'    => $categorypage,
            'format'          => $format,
            'country'         => $country,
            'lang'            => $lang,
            'alang'           => $alang,
        ]);

        self::validate_context(\context_system::instance());

        // Set before the search runs, not after: the matcher folds every language of a value
        // either way, but the names, the group labels and the prices come back in one
        // language and it should be the caller's.
        $wslang = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($wslang !== '') {
            force_current_language($wslang);
        }

        $search = new site_search($params['query']);
        $answerable = $search->is_answerable();

        // An unanswerable query is not an error, and not an empty result either — the app
        // shows "keep typing" for one and "nothing found" for the other, and cannot tell them
        // apart from an empty list. Hence the flag, and hence the group scaffolding being
        // returned whole even when there is nothing to put in it.
        $courserows = $answerable ? $search->courses() : [];
        $categoryrows = $answerable ? $search->categories() : [];

        $coursepaging = self::paginate($courserows, $params['coursepage'], $params['courseperpage']);
        $categorypaging = self::paginate($categoryrows, $params['categorypage'], $params['categoryperpage']);

        $ids = array_map(static fn(array $row): int => (int) $row['id'], $coursepaging['rows']);

        $hydrated = ['courses' => [], 'warnings' => []];
        if ($params['format'] !== self::FORMAT_IDS) {
            $hydrated = self::hydrate($ids, $params['country'], $wslang);
        }

        return [
            'query'      => $search->query(),
            'answerable' => $answerable,
            'minlength'  => site_search::MIN_LENGTH,
            'total'      => count($courserows) + count($categoryrows),
            'courses'    => [
                'label'   => get_string('searchgroupcourses', 'local_nit_category'),
                'count'   => $coursepaging['count'],
                'page'    => $coursepaging['page'],
                'perpage' => $coursepaging['perpage'],
                'more'    => $coursepaging['more'],
                'ids'     => $ids,
                'rows'    => $hydrated['courses'],
            ],
            'categories' => [
                'label'   => get_string('searchgroupcategories', 'local_nit_category'),
                'count'   => $categorypaging['count'],
                'page'    => $categorypaging['page'],
                'perpage' => $categorypaging['perpage'],
                'more'    => $categorypaging['more'],
                'rows'    => array_map([self::class, 'present_category'], $categorypaging['rows']),
            ],
            'warnings'   => $hydrated['warnings'],
        ];
    }

    // =========================================================================
    // Building the answer.
    // =========================================================================

    /**
     * One page of a ranked list, with the numbers a scrolling client needs to ask for the
     * next one.
     *
     * @param array[] $rows every match, in the order the engine ranked them
     * @param int $page zero-based
     * @param int $perpage as asked for; clamped
     * @return array{count: int, page: int, perpage: int, more: int, rows: array[]}
     */
    private static function paginate(array $rows, int $page, int $perpage): array {
        $count = count($rows);
        $perpage = $perpage > 0 ? min($perpage, self::MAX_PERPAGE) : self::DEFAULT_PERPAGE;
        $page = max(0, $page);
        $offset = $page * $perpage;

        return [
            'count'   => $count,
            'page'    => $page,
            'perpage' => $perpage,
            'more'    => max(0, $count - ($offset + $perpage)),
            'rows'    => array_slice($rows, $offset, $perpage),
        ];
    }

    /**
     * The full course rows for one page of hits.
     *
     * Handed straight to local_payments_get_courses_with_pricing, rather than assembled here
     * from {@see \local_nit_category\pricing}, for one reason: the app already parses that
     * function's output. A second course shape returned by a second endpoint is a second
     * parser in the app and a second thing to keep in step every time a pricing field is
     * added. Where the commerce stack is not installed, core's own course shape is the honest
     * answer and the pricing keys are simply absent — they are all VALUE_OPTIONAL.
     *
     * @param int[] $ids course ids, most relevant first
     * @param string $country ISO country for pricing
     * @param string $wslang
     * @return array{courses: array[], warnings: array[]}
     */
    private static function hydrate(array $ids, string $country, string $wslang): array {
        global $CFG;

        // An empty `ids` value means "every course" to both functions below, which is the
        // opposite of what no hits means.
        if (empty($ids)) {
            return ['courses' => [], 'warnings' => []];
        }

        $value = implode(',', $ids);
        if (class_exists('\local_payments\external\get_courses_with_pricing')) {
            $result = \local_payments\external\get_courses_with_pricing::execute('ids', $value, $country, $wslang);
        } else {
            require_once($CFG->dirroot . '/course/externallib.php');
            $result = \core_course_external::get_courses_by_field('ids', $value);
        }

        // Both answer in id order. A search is aimed rather than browsed, so the ranking is
        // the answer — put the rows back into it, and drop any id the course function
        // declined to return rather than emitting a hole.
        $byid = [];
        foreach ($result['courses'] as $course) {
            $byid[(int) $course['id']] = $course;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byid[$id])) {
                $ordered[] = $byid[$id];
            }
        }

        return ['courses' => $ordered, 'warnings' => $result['warnings'] ?? []];
    }

    /**
     * One subject-area hit.
     *
     * The card fields are the browser's, unchanged, so a category looks the same in the app
     * as on the site. `parent` and `sortorder` are added because a client rebuilding the tree
     * needs them and a card never did: the browser is drawing a flat grid.
     *
     * `name` and `description` arrive with {@see \local_nit_category\text_util::ml()} already
     * applied — resolved to the caller's language with an explicit fall back to the other, so
     * no {mlang} markup reaches the client and nothing depends on which multilang filter an
     * administrator happens to have switched on.
     *
     * @param array $row a category_browser row
     * @return array
     */
    private static function present_category(array $row): array {
        $id = (int) $row['id'];
        $category = core_course_category::get($id, IGNORE_MISSING, true);

        return [
            'id'          => $id,
            'name'        => (string) $row['name'],
            'description' => (string) $row['description'],
            'parent'      => $category ? (int) $category->parent : 0,
            'parentname'  => (string) $row['parentname'],
            // What the card prints, which is the count *after* the search — the courses in
            // this area that the query actually found, not everything filed under it.
            'coursecount' => (int) $row['count'],
            'sortorder'   => $category ? (int) $category->sortorder : 0,
            'url'         => (string) $row['url'],
            // Three ways a category can be pictured, in the order a card tries them: the
            // uploaded image, then the small icon, then the emoji an administrator typed. The
            // first two are pluginfile URLs and need the caller's token appended.
            'image'       => (string) $row['image'],
            'icon'        => (string) $row['icon'],
            'emoji'       => (string) $row['emoji'],
        ];
    }

    // =========================================================================
    // Shape.
    // =========================================================================

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'query'      => new external_value(PARAM_TEXT, 'the query as received, trimmed and capped'),
            'answerable' => new external_value(PARAM_BOOL,
                'false when the query is too short to answer: show a hint, not "nothing found"'),
            'minlength'  => new external_value(PARAM_INT, 'shortest query this site answers'),
            'total'      => new external_value(PARAM_INT, 'matches across both groups, before paging'),
            'courses'    => new external_single_structure([
                'label'   => new external_value(PARAM_TEXT, 'localised heading for this group'),
                'count'   => new external_value(PARAM_INT, 'matching courses in total, not on this page'),
                'page'    => new external_value(PARAM_INT, 'zero-based page returned'),
                'perpage' => new external_value(PARAM_INT, 'rows per page actually applied, after clamping'),
                'more'    => new external_value(PARAM_INT, 'matches left after this page'),
                'ids'     => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'),
                    'ids on this page, most relevant first - returned in either format'
                ),
                'rows'    => self::course_structure(),
            ], 'matching courses, most relevant first'),
            'categories' => new external_single_structure([
                'label'   => new external_value(PARAM_TEXT, 'localised heading for this group'),
                'count'   => new external_value(PARAM_INT, 'matching categories in total, not on this page'),
                'page'    => new external_value(PARAM_INT, 'zero-based page returned'),
                'perpage' => new external_value(PARAM_INT, 'rows per page actually applied, after clamping'),
                'more'    => new external_value(PARAM_INT, 'matches left after this page'),
                'rows'    => new external_multiple_structure(new external_single_structure([
                    'id'          => new external_value(PARAM_INT, 'category id'),
                    'name'        => new external_value(PARAM_TEXT, 'resolved to the caller language, no mlang markup'),
                    'description' => new external_value(PARAM_TEXT, 'shortened plain text, may be empty'),
                    'parent'      => new external_value(PARAM_INT, 'parent category id, 0 at the top level'),
                    'parentname'  => new external_value(PARAM_TEXT, 'top-level area this sits under, empty if it is one'),
                    'coursecount' => new external_value(PARAM_INT, 'courses in this area that the query found'),
                    'sortorder'   => new external_value(PARAM_INT, 'the order an administrator arranged the areas in'),
                    'url'         => new external_value(PARAM_URL, 'the category page on the site'),
                    'image'       => new external_value(PARAM_URL, 'pluginfile URL, or empty'),
                    'icon'        => new external_value(PARAM_URL, 'pluginfile URL, or empty'),
                    'emoji'       => new external_value(PARAM_TEXT, 'fallback glyph, or empty'),
                ])),
            ], 'matching subject areas, biggest first'),
            'warnings'   => new external_warnings(),
        ]);
    }

    /**
     * The shape a course hit comes back in.
     *
     * Borrowed from local_payments_get_courses_with_pricing rather than restated, so the app
     * parses a search hit with the class it already has and the two can never drift.
     *
     * @return external_multiple_structure
     */
    private static function course_structure(): external_multiple_structure {
        global $CFG;

        if (class_exists('\local_payments\external\get_courses_with_pricing')) {
            return \local_payments\external\get_courses_with_pricing::execute_returns()->keys['courses'];
        }

        require_once($CFG->dirroot . '/course/externallib.php');
        return \core_course_external::get_courses_by_field_returns()->keys['courses'];
    }
}
