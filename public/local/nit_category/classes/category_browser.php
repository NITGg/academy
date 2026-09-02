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

use core_course_category;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// The picture helpers below are plain functions in lib.php, which is not autoloaded.
// Required here rather than left to the caller so that this class works wherever it is
// constructed — a CLI script or a unit test has no page to have done it first.
require_once($CFG->dirroot . '/local/nit_category/lib.php');

/**
 * The catalogue's courses, regrouped into category cards.
 *
 * This began as the engine behind an all-categories grid of its own. That page was retired
 * once the header search arrived — it answered a question nobody was asking twice — and
 * what is left is the part that earned its keep: the "Categories" group of the site search
 * results (see {@see site_search}).
 *
 * The idea is unchanged. Ticking "Advanced" on the catalogue removes courses; here it
 * removes categories that hold no Advanced course, and rewrites the count on the cards that
 * survive to the number of Advanced courses they hold. So the filtering is the catalogue's
 * — same discovery, same facets, same URL parameters, one engine (see {@see catalogue})
 * — and only the grouping is new.
 *
 * Matching rule, in one sentence: a category is shown when at least one course inside it
 * survives the ticked filters, and either nothing was typed, or the category's own
 * bilingual name and description match what was typed, or one of those surviving courses
 * does.
 *
 * The middle case is what makes searching "تجارة" useful — it finds the International
 * Trade category itself, whose individual course titles may never contain that word.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class category_browser {

    /** @var string Most courses first — the default, so the biggest subject areas lead. */
    const SORT_COURSES = 'courses';
    /** @var string Alphabetical in the reader's language. */
    const SORT_NAME = 'name';

    /** @var catalogue the course engine whose filters and rows this groups */
    private $catalogue;

    /** @var bool whether subcategories are listed alongside the main areas */
    private $subs;

    /** @var bool a category must match the typed words itself, not merely hold a course that did */
    private $ownmatchonly;

    /** @var string one of the SORT_* constants */
    private $sort;

    /** @var array[]|null the built rows, or null until first asked for */
    private $rows = null;

    /** @var int categories in scope before the filters were applied */
    private $total = 0;

    /**
     * @param catalogue $catalogue a catalogue that has already read the request
     * @param bool $subs list subcategories as well as the main areas
     * @param string $sort one of {@see self::sort_options()}
     * @param bool $ownmatchonly keep only categories whose own text matches the search
     */
    public function __construct(catalogue $catalogue, bool $subs = false, string $sort = self::SORT_COURSES,
            bool $ownmatchonly = false) {
        $this->catalogue = $catalogue;
        $this->subs = $subs;
        $this->sort = array_key_exists($sort, self::sort_options()) ? $sort : self::SORT_COURSES;
        $this->ownmatchonly = $ownmatchonly;
    }

    /**
     * The orders the grid can be read in.
     *
     * @return array<string, string> key => label
     */
    public static function sort_options(): array {
        return [
            self::SORT_COURSES => get_string('sortmostcourses', 'local_nit_category'),
            self::SORT_NAME    => get_string('sortname', 'local_nit_category'),
        ];
    }

    /**
     * The cards to draw, in the chosen order.
     *
     * @return array[] one row per category
     */
    public function rows(): array {
        if ($this->rows === null) {
            $this->rows = $this->build();
        }
        return $this->rows;
    }

    /**
     * How many categories were in scope before any filter was applied.
     *
     * The denominator in "6 of 14 categories", so the reader can tell a narrow filter from
     * a small site.
     *
     * @return int
     */
    public function total(): int {
        $this->rows();
        return $this->total;
    }

    // =========================================================================
    // Building.
    // =========================================================================

    /**
     * Group the catalogue's courses into category cards.
     *
     * @return array[]
     */
    private function build(): array {
        $bycategory = $this->spread($this->catalogue->matches_ignoring_search());
        $bysearch = $this->ownmatchonly ? [] : $this->spread($this->catalogue->matches());
        $words = text_util::search_words($this->catalogue->active()['q'] ?? '');

        $out = [];
        $this->total = 0;

        foreach (core_course_category::get_all(['returnhidden' => false]) as $category) {
            $id = (int) $category->id;

            if (!$this->subs && (int) $category->parent !== 0) {
                continue;
            }

            // An empty category is a dead card. This is counted before the search is
            // considered so that "6 of 14" compares like with like.
            $filtered = count($bycategory[$id] ?? []);
            if ($filtered === 0) {
                continue;
            }
            $this->total++;

            $count = $filtered;
            if ($words) {
                if (!$this->text_matches($category, $words)) {
                    if ($this->ownmatchonly) {
                        // The site search lists categories under their own heading
                        // (AC-4.22.3), so a category earns its place there by matching the
                        // typed words itself. A category that merely holds a course that
                        // matched is not a result: that course is already listed, under
                        // "Courses", and repeating its category as a second hit would
                        // inflate the totals with the same answer twice.
                        continue;
                    }
                    // Nothing in the category's own name or description; fall back to
                    // whether any course inside it matched.
                    $count = count($bysearch[$id] ?? []);
                    if ($count === 0) {
                        continue;
                    }
                }
            }

            $out[] = $this->card($category, $count);
        }

        return $this->order($out);
    }

    /**
     * Which courses sit under each category, counting every ancestor.
     *
     * A course in "International Trade / Customs" belongs to Customs *and* to International
     * Trade, because a reader clicking the International Trade card expects to find it.
     * That is also why the count on a main-area card is not the sum of its children — a
     * course is counted once per ancestor, never twice within one.
     *
     * @param array[] $rows catalogue rows
     * @return array<int, array<int, true>> category id => set of course ids
     */
    private function spread(array $rows): array {
        $out = [];

        // One category object per category rather than per course: get_parents() is cheap
        // but get() on a 200-course catalogue is not.
        $chains = [];

        foreach ($rows as $row) {
            $catid = (int) $row['catid'];
            if (!isset($chains[$catid])) {
                $category = core_course_category::get($catid, IGNORE_MISSING, true);
                $chains[$catid] = $category
                    ? array_merge([$catid], array_map('intval', $category->get_parents()))
                    : [$catid];
            }
            foreach ($chains[$catid] as $id) {
                if ($id > 0) {
                    $out[$id][(int) $row['id']] = true;
                }
            }
        }

        return $out;
    }

    /**
     * Does the category's own text contain every word typed?
     *
     * Both languages are searched whichever the interface is in (AC-4.8.2) and both sides
     * are folded so spelling variants meet (AC-4.8.3) — the same treatment the catalogue
     * gives a course.
     *
     * @param core_course_category $category
     * @param string[] $words already folded by text_util::search_words()
     * @return bool
     */
    private function text_matches(core_course_category $category, array $words): bool {
        $haystack = text_util::normalise(
            text_util::ml_all((string) $category->name) . ' '
            . text_util::ml_all(html_to_text((string) $category->description, 0, false))
        );

        foreach ($words as $word) {
            if (strpos($haystack, $word) === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Everything one card prints.
     *
     * @param core_course_category $category
     * @param int $count courses behind this card under the filters in force
     * @return array
     */
    private function card(core_course_category $category, int $count): array {
        $id = (int) $category->id;

        // text_util::ml() rather than get_formatted_name(): AC-4.8.4 asks for an explicit
        // fall back to English where an Arabic value is missing, and ml() gives that
        // guarantee here rather than leaving it to whichever multilang filter an
        // administrator happens to have enabled.
        $name = text_util::ml((string) $category->name);
        if ($name === '') {
            $name = text_util::plain($category->get_formatted_name());
        }

        return [
            'id'          => $id,
            'name'        => $name,
            'description' => $this->description($category),
            'parentname'  => $this->parent_name($category),
            'url'         => $this->url($id),
            // Three ways a category can be pictured, in the order the card tries them:
            // the uploaded image (inherited from an ancestor where the category has none),
            // then the small uploaded icon, then the emoji an administrator typed.
            'image'       => \local_nit_category_get_image_url($id),
            'icon'        => \local_nit_category_get_icon_url($id),
            'emoji'       => \local_nit_category_get_icon_emoji($id),
            'count'       => $count,
        ];
    }

    /**
     * The card's second line: the category description, as plain text and short enough to
     * sit under a title without pushing the button off the card.
     *
     * @param core_course_category $category
     * @return string
     */
    private function description(core_course_category $category): string {
        $raw = text_util::ml((string) $category->description);
        if (trim($raw) === '') {
            return '';
        }

        $context = \context_coursecat::instance((int) $category->id, IGNORE_MISSING);
        $html = format_text($raw, $category->descriptionformat ?? FORMAT_HTML,
            ['context' => $context ?: \context_system::instance(), 'noclean' => true]);

        return shorten_text(text_util::plain($html), 110);
    }

    /**
     * The main area a subcategory belongs to, so a card in the "include subcategories" view
     * says where it sits. Empty for a main area, which is its own answer.
     *
     * @param core_course_category $category
     * @return string
     */
    private function parent_name(core_course_category $category): string {
        $parents = $category->get_parents();
        if (empty($parents)) {
            return '';
        }
        $top = core_course_category::get((int) reset($parents), IGNORE_MISSING, true);
        return $top ? text_util::ml((string) $top->name) : '';
    }

    /**
     * Where the card's action goes.
     *
     * With no filter in force that is the category's own branded page, exactly as the home
     * page block links. With a filter in force it is the catalogue scoped to this category
     * and carrying the same filters — otherwise a card reading "3 courses" would open a
     * page showing twenty-one, which is the sort of thing that makes people stop trusting
     * a filter panel.
     *
     * @param int $categoryid
     * @return string
     */
    private function url(int $categoryid): string {
        $active = $this->catalogue->active();
        if (empty($active)) {
            return (new moodle_url('/local/nit_category/index.php', ['id' => $categoryid]))->out(false);
        }

        $query = ['id' => $categoryid];
        if (isset($active['q'])) {
            $query['q'] = $active['q'];
        }
        if (!empty($active['free'])) {
            $query['free'] = 1;
        }
        foreach (['pricemin', 'pricemax'] as $key) {
            if (isset($active[$key])) {
                $query[$key] = $active[$key];
            }
        }
        foreach ($this->catalogue->filters() as $shortname => $filter) {
            if (!isset($active[$shortname])) {
                continue;
            }
            if ($filter['kind'] === catalogue::KIND_OPTIONS) {
                $query['f_' . $shortname] = $active[$shortname];
            } else if ($filter['kind'] === catalogue::KIND_BOOL) {
                $query['f_' . $shortname] = 1;
            } else {
                foreach (['min', 'max'] as $edge) {
                    if (isset($active[$shortname][$edge])) {
                        $query[$edge . '_' . $shortname] = $active[$shortname][$edge];
                    }
                }
            }
        }

        // http_build_query rather than moodle_url: the ticked values are repeated
        // parameters, which moodle_url does not model.
        $base = (new moodle_url('/local/nit_category/catalogue.php'))->out_omit_querystring();
        return $base . '?' . http_build_query($query, '', '&');
    }

    /**
     * Put the cards in the chosen order.
     *
     * @param array[] $rows
     * @return array[]
     */
    private function order(array $rows): array {
        if ($this->sort === self::SORT_NAME) {
            usort($rows, static fn($a, $b) => text_util::collate($a['name'], $b['name']));
            return $rows;
        }

        // Most courses first, and alphabetically within a tie so the grid does not reshuffle
        // itself between two requests that are the same size.
        usort($rows, static function (array $a, array $b) {
            if ($a['count'] !== $b['count']) {
                return $b['count'] <=> $a['count'];
            }
            return text_util::collate($a['name'], $b['name']);
        });
        return $rows;
    }
}
