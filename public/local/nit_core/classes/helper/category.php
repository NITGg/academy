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
 * Course-category questions that more than one NIT plugin has to answer the same way.
 *
 * @package    local_nit_core
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_core\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Category tree lookups shared by the catalogue, the subscriptions and the commerce plugins.
 *
 * Everything here reads {course_categories} directly rather than going through
 * {@see \core_course_category}, on purpose. That class filters by what the CURRENT viewer may
 * see, which is right for a listing and wrong for a rule: whether a coupon covers a course, or
 * which plans belong to a category, must not change depending on who is asking. Visibility is
 * applied where the courses themselves are listed, which is where it belongs.
 *
 * The tree is walked through `course_categories.path` ("/1/5/12"), so an ancestry or a subtree
 * is one string operation or one indexed query instead of a recursive descent.
 */
class category {

    /** @var array<int,array> Per-request cache of category rows, keyed by id. */
    protected static $rows = null;

    /**
     * Every category, keyed by id, with id / name / path / parent / sortorder.
     *
     * Loaded once per request: the callers below are used inside loops over coupons, offers
     * and plans, and the table is small enough that one read beats a query per question.
     *
     * @return array<int,\stdClass>
     */
    protected static function rows(): array {
        global $DB;
        if (self::$rows === null) {
            self::$rows = $DB->get_records('course_categories', null, 'sortorder ASC',
                'id, name, path, parent, sortorder, visible');
        }
        return self::$rows;
    }

    /**
     * Forget the cached tree. For tests and for code that has just moved a category.
     *
     * @return void
     */
    public static function reset_cache(): void {
        self::$rows = null;
    }

    /**
     * Does this category exist?
     *
     * @param int $categoryid
     * @return bool
     */
    public static function exists(int $categoryid): bool {
        return $categoryid > 0 && isset(self::rows()[$categoryid]);
    }

    /**
     * A category's display name, or '' when there is no such category.
     *
     * @param int $categoryid
     * @return string
     */
    public static function name(int $categoryid): string {
        $rows = self::rows();
        return isset($rows[$categoryid]) ? format_string($rows[$categoryid]->name) : '';
    }

    /**
     * The category and every category ABOVE it, nearest first.
     *
     * @param int $categoryid
     * @return int[] [] when the category does not exist
     */
    public static function ancestry(int $categoryid): array {
        $rows = self::rows();
        if (!isset($rows[$categoryid])) {
            return [];
        }
        $ids = array_map('intval', array_filter(explode('/', (string) $rows[$categoryid]->path), 'strlen'));
        return array_values(array_reverse($ids));
    }

    /**
     * The category and every category BENEATH it, at any depth.
     *
     * @param int $categoryid
     * @return int[] [] when the category does not exist
     */
    public static function subtree(int $categoryid): array {
        $rows = self::rows();
        if (!isset($rows[$categoryid])) {
            return [];
        }
        $path = (string) $rows[$categoryid]->path;
        $prefix = $path . '/';
        $out = [$categoryid];
        foreach ($rows as $row) {
            if ((int) $row->id !== $categoryid && strpos((string) $row->path, $prefix) === 0) {
                $out[] = (int) $row->id;
            }
        }
        return $out;
    }

    /**
     * Everything in the same branch as this category: itself, its ancestors and its descendants.
     *
     * This is what a category landing page covers. The page for "Programming" lists the courses
     * of "Web" and "Mobile" too, so a plan or a coupon attached to either of those is relevant
     * on it; and one attached to "Programming" is relevant on the "Web" page, because "Web" is
     * part of what "Programming" was scoped to. Siblings are NOT in the branch — a "Mobile"
     * coupon has no business on the "Web" page.
     *
     * @param int $categoryid
     * @return int[] [] when the category does not exist
     */
    public static function branch(int $categoryid): array {
        $ids = array_merge(self::ancestry($categoryid), self::subtree($categoryid));
        return array_values(array_unique($ids));
    }

    /**
     * Whether $categoryid sits inside $rootid's subtree (or IS it).
     *
     * @param int $categoryid
     * @param int $rootid
     * @return bool
     */
    public static function is_within(int $categoryid, int $rootid): bool {
        if ($categoryid <= 0 || $rootid <= 0) {
            return false;
        }
        if ($categoryid === $rootid) {
            return true;
        }
        return in_array($rootid, self::ancestry($categoryid), true);
    }

    /**
     * The category a course sits in.
     *
     * @param int $courseid
     * @return int 0 when the course is unknown (or is the site course)
     */
    public static function of_course(int $courseid): int {
        global $DB;
        static $cache = [];
        if ($courseid <= 0) {
            return 0;
        }
        if (!array_key_exists($courseid, $cache)) {
            $cache[$courseid] = (int) $DB->get_field('course', 'category', ['id' => $courseid]);
        }
        return $cache[$courseid];
    }

    /**
     * The categories a set of courses spans, deduplicated.
     *
     * @param int[] $courseids
     * @return int[]
     */
    public static function of_courses(array $courseids): array {
        global $DB;
        $courseids = array_values(array_unique(array_filter(array_map('intval', $courseids))));
        if (!$courseids) {
            return [];
        }
        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $cats = $DB->get_fieldset_select('course', 'DISTINCT category', "id {$insql}", $params);
        return array_values(array_unique(array_map('intval', $cats)));
    }

    /**
     * Every category as an option list for an admin picker, in tree order, children indented.
     *
     * Includes hidden categories and categories with no courses of their own: a parent that
     * only holds subcategories is exactly the one an admin wants to scope a plan to.
     *
     * @return array[] one row per category: ['id' => int, 'name' => string, 'depth' => int]
     */
    public static function options(): array {
        $out = [];
        foreach (self::rows() as $row) {
            $depth = max(0, count(array_filter(explode('/', (string) $row->path), 'strlen')) - 1);
            $out[] = [
                'id'    => (int) $row->id,
                'name'  => str_repeat('— ', $depth) . format_string($row->name),
                'depth' => $depth,
            ];
        }
        return $out;
    }
}
