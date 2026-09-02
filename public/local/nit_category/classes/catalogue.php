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
use core_course\customfield\course_handler;

/**
 * The course catalogue: which courses match, and which filters to offer for them.
 *
 * The problem this solves is that no two courses here are described the same way — one
 * carries a level and a duration, another carries neither, a third has fields the others
 * have never heard of. So the filter list is NOT written down anywhere: it is derived from
 * the course custom fields that actually exist, and each filter is derived from the values
 * the matching courses actually hold. Add a custom field in Site administration and a
 * filter for it appears here on the next page load; leave it empty on every course and no
 * filter appears at all.
 *
 * Each custom-field type becomes the kind of filter it can honestly support:
 *
 *   select    -> a checkbox list of its configured options
 *   text      -> a checkbox list of the distinct values courses hold (our short-text
 *                fields are chip lists, so one course can sit under several options)
 *   checkbox  -> a single yes/no toggle ("Carries a certificate")
 *   number    -> a from/to range ("Duration, hours")
 *   textarea  -> nothing: prose is searched, not faceted
 *   date      -> nothing: no course-shopping question is asked in dates today
 *
 * Values within one filter are OR-ed (Foundation *or* Intermediate) and filters are AND-ed
 * across each other, which is what a shopper means by ticking several boxes.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue {

    /** @var string A checkbox list of values. */
    const KIND_OPTIONS = 'options';
    /** @var string A single on/off toggle. */
    const KIND_BOOL = 'bool';
    /** @var string A numeric from/to range. */
    const KIND_RANGE = 'range';
    /** @var string A number field offered as named bands — "Under 10 hours". */
    const KIND_BUCKETS = 'buckets';

    /** @var int Cards per page unless the visitor picks otherwise. */
    const DEFAULT_PERPAGE = 12;
    /** @var int[] Page sizes offered. */
    const PERPAGE_OPTIONS = [12, 24, 48];

    /** @var int Options listed in a filter group before the rest fold behind "Show all". */
    const OPTIONS_VISIBLE = 6;

    /** @var int Most options one filter group will ever list. */
    const OPTIONS_MAX = 40;

    /**
     * @var int Longest a value may be and still be treated as a label.
     *
     * Short-text fields are free typing, and several of ours hold prose: "Intended Learning
     * Outcomes" holds "Analyze supply-chain data to drive operational decisions", which is a
     * sentence about one course, not a category shared with others. A tick box needs a label,
     * so a value past this length is kept for searching and for the course page but is never
     * offered as a filter or printed on a card.
     */
    const MAX_OPTION_LENGTH = 32;

    /** @var int And a label is a phrase, not a clause — the same test counted in words. */
    const MAX_OPTION_WORDS = 5;

    /** @var int Root category, or 0 for the whole site. */
    private $rootid;

    /** @var array[] Filter definitions, keyed by custom-field shortname. */
    private $filters = [];

    /** @var array[] One row per candidate course. */
    private $rows = [];

    /** @var array The filter values in force, keyed by filter name. */
    private $active = [];

    /**
     * @param int $rootid category to browse within (recursively), or 0 for every course
     */
    public function __construct(int $rootid = 0) {
        $this->rootid = $rootid;
        $this->filters = $this->discover_filters();
        $this->rows = $this->load_rows();
    }

    // =========================================================================
    // Filter discovery.
    // =========================================================================

    /**
     * The filter panel the design asks for, and nothing else.
     *
     * This used to offer every custom field whose type made a plausible control, which is
     * how the panel ended up listing "Intended Learning Outcomes" and "By the end of this
     * training program, the trainee will be able to competently" next to Level. A shop
     * window is a curated thing: SRS §4.8 names exactly six filters — category, level,
     * price, language, duration and whether the course carries a certificate — so those
     * six are what this builds, in that order.
     *
     * Category and price are not custom fields and are handled elsewhere ({@see
     * self::category_facet()} and {@see self::matches_price()}); the four here are. Each is
     * a *role* rather than a field name, and the field behind a role is a setting, so a
     * site that calls its hours field something else is rewired in the admin UI rather
     * than in this file.
     *
     * Only fields visible to everyone are used: a field an admin restricted to teachers
     * must not become a public facet that leaks its values through the option list.
     *
     * @return array[] definition per shortname
     */
    private function discover_filters(): array {
        $excluded = $this->excluded_shortnames();

        // Indexed by shortname so a role can find its field without a second query.
        $available = [];
        try {
            foreach (course_handler::create()->get_fields() as $field) {
                $available[\core_text::strtolower((string) $field->get('shortname'))] = $field;
            }
        } catch (\Throwable $e) {
            return [];
        }

        $filters = [];
        foreach (self::filter_roles() as $role => $spec) {
            $shortname = trim((string) get_config('local_nit_category', 'filterfield_' . $role));
            if ($shortname === '') {
                $shortname = $spec['field'];
            }
            $field = $available[\core_text::strtolower($shortname)] ?? null;
            if ($field === null) {
                continue;       // The site does not have this field; the group is simply absent.
            }
            if (isset($excluded[\core_text::strtolower($shortname)])) {
                continue;
            }
            // The shortname becomes a URL parameter, so anything that would not survive the
            // round trip is skipped rather than silently mangled into another field's name.
            if ($shortname !== clean_param($shortname, PARAM_ALPHANUMEXT)) {
                continue;
            }
            // Missing means "visible to all", the same default core_course\customfield\
            // course_handler::can_view() applies — a field created before the setting
            // existed must not silently disappear from the catalogue.
            $visibility = $field->get_configdata_property('visibility') ?? course_handler::VISIBLETOALL;
            if ((int) $visibility !== course_handler::VISIBLETOALL) {
                continue;
            }

            $type = (string) $field->get('type');
            if (!in_array($type, $spec['types'], true)) {
                // The field exists but cannot answer this question — a checkbox cannot hold
                // a level. Better an absent group than one that silently matches nothing.
                continue;
            }

            $options = [];
            $optionsraw = [];
            if ($type === 'select') {
                // Index 0 is the "not set" placeholder core prepends; it is not an option.
                // get_options() formats its labels, so they come back to plain text here —
                // see text_util::plain() for why the catalogue escapes only at output.
                $options = array_map([text_util::class, 'plain'],
                    array_filter($field->get_options(), static fn($label) => trim((string) $label) !== ''));

                // The same list before formatting. get_options() runs format_string(), which
                // resolves {mlang} to the *current* language — so a level stored as an
                // English/Arabic pair loses half of itself there. AC-4.8.2 needs both halves,
                // so the raw configdata is read again here purely for searching. Split
                // exactly as the select field itself splits it, and offset by one so the
                // indexes line up with the stored intvalue.
                $config = (string) $field->get_configdata_property('options');
                $optionsraw = array_merge([''],
                    preg_split("/\s*\n\s*/", trim($config), -1, PREG_SPLIT_NO_EMPTY) ?: []);
            }

            $filters[$shortname] = [
                'shortname'  => $shortname,
                'role'       => $role,
                // Formatted names arrive HTML-escaped; the page escapes once at output.
                'name'       => $spec['label'](),
                'type'       => $type,
                'kind'       => $spec['kind'],
                'options'    => $options,
                'optionsraw' => $optionsraw,
                'buckets'    => $spec['buckets'] ?? [],
                'sortorder'  => $spec['order'],
            ];
        }

        uasort($filters, static fn($a, $b) => $a['sortorder'] <=> $b['sortorder']);
        return $filters;
    }

    /**
     * The four custom-field filters of SRS §4.8, in the order the design shows them.
     *
     * `field` is the shortname assumed when the matching admin setting is empty; `types`
     * are the field types that can answer the question; `label` is deferred because a
     * string cannot be fetched while the class is being loaded. The panel's own wording is
     * used rather than the field's name, so a field titled "Total Number of Hours" still
     * appears under the heading "Duration" the design asks for.
     *
     * @return array[] keyed by role
     */
    public static function filter_roles(): array {
        return [
            'level' => [
                'field' => 'level',
                'types' => ['select', 'text'],
                'kind'  => self::KIND_OPTIONS,
                'order' => 20,
                'label' => static fn() => get_string('filterlevel', 'local_nit_category'),
            ],
            // 30 is the price range, which is not a custom field — see the page templates.
            'language' => [
                'field' => 'language',
                'types' => ['select', 'text'],
                'kind'  => self::KIND_OPTIONS,
                'order' => 40,
                'label' => static fn() => get_string('filterlanguage', 'local_nit_category'),
            ],
            'duration' => [
                'field' => 'total_number_of_hours',
                'types' => ['number'],
                'kind'  => self::KIND_BUCKETS,
                'order' => 50,
                'label' => static fn() => get_string('filterduration', 'local_nit_category'),
                // Named bands rather than a from/to box. Nobody shopping for a course thinks
                // "between 10 and 25 hours"; they think "a short one". Bounds are inclusive
                // of min and exclusive of max, so every hour count lands in exactly one.
                'buckets' => [
                    'short'  => ['min' => null, 'max' => 10.0],
                    'medium' => ['min' => 10.0, 'max' => 25.0],
                    'long'   => ['min' => 25.0, 'max' => null],
                ],
            ],
            'certificate' => [
                'field' => 'certificate',
                'types' => ['checkbox'],
                'kind'  => self::KIND_BOOL,
                'order' => 60,
                'label' => static fn() => get_string('filtercertificate', 'local_nit_category'),
            ],
        ];
    }

    /**
     * The range the price slider spans: zero to the dearest course in scope.
     *
     * Read from the courses rather than from a setting, so the far end of the slider is
     * always reachable and always meaningful. Rounded up to a round number, because a
     * slider ending at "EGP 4,850" looks like a bug even when it is the truth.
     *
     * @return array{min: float, max: float, currency: string}
     */
    public function price_bounds(): array {
        $max = 0.0;
        $currency = '';

        foreach ($this->rows as $row) {
            $info = pricing::info($row['id']);
            if (empty($info['haspricing']) || !empty($info['countryrequired'])) {
                continue;
            }
            $max = max($max, pricing::effective_price($info));
            if ($currency === '' && $info['currency'] !== '') {
                $currency = $info['currency'];
            }
        }

        if ($max <= 0) {
            return ['min' => 0.0, 'max' => 0.0, 'currency' => $currency];
        }

        // Up to the next 500 (or 100 when everything is cheap), so the handle can always
        // reach past the dearest course rather than stopping exactly on it.
        $step = $max > 1000 ? 500 : 100;
        return ['min' => 0.0, 'max' => (float) (ceil($max / $step) * $step), 'currency' => $currency];
    }

    /**
     * One facet by the job it does, so a template can lay the panel out in the design's
     * order without knowing what the fields behind it are called.
     *
     * @param array[] $facets from {@see self::facets()}
     * @param string $role
     * @return array|null
     */
    public static function facet_by_role(array $facets, string $role): ?array {
        foreach ($facets as $facet) {
            if (($facet['role'] ?? '') === $role) {
                return $facet;
            }
        }
        return null;
    }

    /**
     * Shortnames an admin has asked the catalogue to leave alone.
     *
     * @return array<string, true> lower-cased shortnames
     */
    private function excluded_shortnames(): array {
        $raw = (string) get_config('local_nit_category', 'excludefilterfields');
        $out = [];
        foreach (preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $name) {
            $out[\core_text::strtolower(trim($name))] = true;
        }
        return $out;
    }

    // =========================================================================
    // Loading.
    // =========================================================================

    /**
     * Every course the viewer may see under the root category, with the values the filters
     * need already attached.
     *
     * Custom-field values for the whole candidate set are fetched in ONE query rather than
     * per course: a catalogue asks about every course on the page and every course behind
     * the facet counts, so the per-course version of this is a few hundred queries.
     *
     * @return array[]
     */
    private function load_rows(): array {
        global $DB;

        $root = $this->rootid ? core_course_category::get($this->rootid, IGNORE_MISSING, true) : core_course_category::top();
        if (!$root) {
            return [];
        }
        $courses = $root->get_courses(['recursive' => true, 'sort' => ['sortorder' => 1], 'summary' => true]);
        if (empty($courses)) {
            return [];
        }

        $ids = array_map(static fn($c) => (int) $c->id, array_values($courses));
        $values = $this->load_field_values($ids);
        $created = $DB->get_records_list('course', 'id', $ids, '', 'id, timecreated, category');
        $popularity = $this->load_popularity($ids);

        $rows = [];
        foreach ($courses as $course) {
            $id = (int) $course->id;
            $category = core_course_category::get((int) $course->category, IGNORE_MISSING, true);
            $rows[] = [
                'id'          => $id,
                'course'      => $course,
                'catid'       => (int) $course->category,
                'catname'     => $category ? text_util::plain($category->get_formatted_name()) : '',
                'values'      => $values[$id] ?? [],
                'timecreated' => (int) ($created[$id]->timecreated ?? 0),
                'popularity'  => (int) ($popularity[$id] ?? 0),
                'haystack'    => $this->haystack($course, $values[$id] ?? [],
                    $category ? (string) $category->name : ''),
            ];
        }
        return $rows;
    }

    /**
     * Read every filterable field value for a set of courses in one go.
     *
     * @param int[] $courseids
     * @return array<int, array<string, array>> course id -> shortname -> value bundle
     */
    private function load_field_values(array $courseids): array {
        global $DB;

        if (empty($courseids) || empty($this->filters)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $params['component'] = 'core_course';
        $params['area'] = 'course';

        $sql = "SELECT d.id, d.instanceid, f.shortname, f.type, d.intvalue, d.decvalue, d.charvalue, d.value
                  FROM {customfield_data} d
                  JOIN {customfield_field} f ON f.id = d.fieldid
                 WHERE d.component = :component AND d.area = :area AND d.instanceid $insql";

        $out = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $shortname = (string) $record->shortname;
            if (!isset($this->filters[$shortname])) {
                continue;
            }
            $bundle = $this->bundle($this->filters[$shortname], $record);
            if ($bundle !== null) {
                $out[(int) $record->instanceid][$shortname] = $bundle;
            }
        }
        return $out;
    }

    /**
     * Turn one stored value into the shape its filter compares against.
     *
     * @param array $filter the filter definition
     * @param \stdClass $record a customfield_data row
     * @return array|null null when the course simply has no value for this field
     */
    private function bundle(array $filter, \stdClass $record): ?array {
        if ($filter['type'] === 'select') {
            $index = (int) $record->intvalue;
            $label = $filter['options'][$index] ?? '';
            if (trim($label) === '') {
                return null;
            }
            // 'search' is every language of the value, 'labels' is the one the reader sees.
            // They are kept apart because one is compared and the other is printed.
            return [
                'labels' => [$label],
                'keys'   => [text_util::key($label)],
                'search' => text_util::ml_all($filter['optionsraw'][$index] ?? $label),
            ];
        }

        if ($filter['type'] === 'text') {
            // A short-text field is a chip list: one course legitimately holds several
            // values, and every one of them is a filter option.
            $raw = $record->charvalue !== null && $record->charvalue !== ''
                ? $record->charvalue : $record->value;
            $labels = text_util::values($raw);
            if (empty($labels)) {
                return null;
            }
            return [
                'labels' => $labels,
                'keys'   => array_map([text_util::class, 'key'], $labels),
                'search' => text_util::ml_all((string) $raw),
            ];
        }

        if ($filter['type'] === 'checkbox') {
            return ['bool' => (bool) $record->intvalue];
        }

        if ($filter['type'] === 'number') {
            if ($record->decvalue === null || $record->decvalue === '') {
                return null;
            }
            return ['number' => (float) $record->decvalue];
        }

        return null;
    }

    /**
     * Enrolment counts, used by the "Most popular" sort.
     *
     * @param int[] $courseids
     * @return array<int, int>
     */
    private function load_popularity(array $courseids): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $sql = "SELECT e.courseid, COUNT(DISTINCT ue.userid) AS learners
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid $insql
              GROUP BY e.courseid";
        $out = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $out[(int) $record->courseid] = (int) $record->learners;
        }
        return $out;
    }

    /**
     * The text one course is searched against: its name, its summary, its category and its
     * own field values, so typing "certificate" or "forklift" finds a course however it was
     * described.
     *
     * Everything goes in raw rather than formatted, then through text_util::ml_all(), so
     * both halves of a bilingual value are present at once (AC-4.8.2) — get_formatted_name()
     * would have thrown one of them away before we ever saw it. The result is folded with
     * text_util::normalise() so that the stored spelling and the typed spelling meet in the
     * middle (AC-4.8.3); the query is folded the same way in matches_search().
     *
     * @param \core_course_list_element $course
     * @param array $values the course's field bundles
     * @param string $catname the enclosing category's raw name
     * @return string folded for comparison, never for display
     */
    private function haystack($course, array $values, string $catname = ''): string {
        $parts = [text_util::ml_all((string) $course->fullname)];
        if ($catname !== '') {
            $parts[] = text_util::ml_all($catname);
        }
        if ($course->has_summary()) {
            $parts[] = text_util::ml_all(html_to_text((string) $course->summary, 0, false));
        }
        foreach ($values as $bundle) {
            // The bilingual original where the bundle carries one, otherwise the labels,
            // which is all a checkbox or a number ever has.
            if (!empty($bundle['search'])) {
                $parts[] = $bundle['search'];
                continue;
            }
            foreach ($bundle['labels'] ?? [] as $label) {
                $parts[] = $label;
            }
        }
        return text_util::normalise(implode(' ', $parts));
    }

    // =========================================================================
    // Applying the request.
    // =========================================================================

    /**
     * Read the filters out of the current request.
     *
     * @return void
     */
    public function read_request(): void {
        $active = [];

        $search = trim(optional_param('q', '', PARAM_TEXT));
        if ($search !== '') {
            $active['q'] = $search;
        }

        $categories = optional_param_array('cat', [], PARAM_INT);
        $categories = array_values(array_filter(array_map('intval', $categories)));
        if (!empty($categories)) {
            $active['cat'] = $categories;
        }

        if (optional_param('free', 0, PARAM_BOOL)) {
            $active['free'] = true;
        }
        $pricemin = optional_param('pricemin', '', PARAM_RAW_TRIMMED);
        $pricemax = optional_param('pricemax', '', PARAM_RAW_TRIMMED);
        if (is_numeric($pricemin)) {
            $active['pricemin'] = (float) $pricemin;
        }
        if (is_numeric($pricemax)) {
            $active['pricemax'] = (float) $pricemax;
        }

        foreach ($this->filters as $shortname => $filter) {
            if ($filter['kind'] === self::KIND_OPTIONS) {
                $picked = optional_param_array('f_' . $shortname, [], PARAM_TEXT);
                $picked = array_values(array_filter(array_map([text_util::class, 'key'], $picked),
                    static fn($k) => $k !== ''));
                if (!empty($picked)) {
                    $active[$shortname] = $picked;
                }
            } else if ($filter['kind'] === self::KIND_BUCKETS) {
                // Ticked band names, not numbers — anything not a band we defined is
                // dropped rather than trusted into a comparison.
                $picked = optional_param_array('f_' . $shortname, [], PARAM_ALPHANUMEXT);
                $picked = array_values(array_intersect($picked, array_keys($filter['buckets'])));
                if (!empty($picked)) {
                    $active[$shortname] = $picked;
                }
            } else if ($filter['kind'] === self::KIND_BOOL) {
                if (optional_param('f_' . $shortname, 0, PARAM_BOOL)) {
                    $active[$shortname] = true;
                }
            } else if ($filter['kind'] === self::KIND_RANGE) {
                $min = optional_param('min_' . $shortname, '', PARAM_RAW_TRIMMED);
                $max = optional_param('max_' . $shortname, '', PARAM_RAW_TRIMMED);
                $range = [];
                if (is_numeric($min)) {
                    $range['min'] = (float) $min;
                }
                if (is_numeric($max)) {
                    $range['max'] = (float) $max;
                }
                if (!empty($range)) {
                    $active[$shortname] = $range;
                }
            }
        }

        $this->active = $active;
    }

    /**
     * Set (or clear) the search term without going through the request.
     *
     * The header search box asks this engine the same question the catalogue page does,
     * but sometimes about a term that is not this request's `q` — the "did this term find
     * nothing?" check behind AC-4.22.4 asks about a term the caller is already holding.
     * Rather than grow a second matching engine, callers hand the term to this one.
     * See {@see site_search}.
     *
     * @param string $query as typed; '' removes the search
     * @return void
     */
    public function set_search(string $query): void {
        $query = trim($query);
        if ($query === '') {
            unset($this->active['q']);
        } else {
            $this->active['q'] = $query;
        }
    }

    /**
     * The filters currently in force.
     *
     * @return array
     */
    public function active(): array {
        return $this->active;
    }

    /**
     * The filter definitions, in field order.
     *
     * @return array[]
     */
    public function filters(): array {
        return $this->filters;
    }

    /**
     * How many courses are in scope before any filter is applied.
     *
     * @return int
     */
    public function total(): int {
        return count($this->rows);
    }

    /**
     * The rows passing every active filter except the search box.
     *
     * The categories page needs both answers at once: which courses the ticked filters
     * leave (that is the number a category card prints), and which of those also match
     * what was typed (that is whether the card is shown at all). A category whose own
     * name matches the search is shown with its filtered count even though none of its
     * courses mention the typed word — see category_browser.
     *
     * @return array[]
     */
    public function matches_ignoring_search(): array {
        $active = $this->active;
        unset($active['q']);
        return $this->filter_rows($this->rows, $active);
    }

    /**
     * The courses matching every active filter.
     *
     * @return array[]
     */
    public function matches(): array {
        return $this->filter_rows($this->rows, $this->active);
    }

    /**
     * Apply a set of filters to a set of rows.
     *
     * @param array[] $rows
     * @param array $active
     * @return array[]
     */
    private function filter_rows(array $rows, array $active): array {
        $needsprice = isset($active['free']) || isset($active['pricemin']) || isset($active['pricemax']);

        return array_values(array_filter($rows, function (array $row) use ($active, $needsprice) {
            if (isset($active['q']) && !$this->matches_search($row, $active['q'])) {
                return false;
            }
            if (isset($active['cat']) && !in_array($row['catid'], $active['cat'], true)) {
                return false;
            }
            if ($needsprice && !$this->matches_price($row, $active)) {
                return false;
            }
            foreach ($this->filters as $shortname => $filter) {
                if (!isset($active[$shortname])) {
                    continue;
                }
                if (!$this->matches_field($row, $filter, $active[$shortname])) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Every word typed must appear somewhere in the course, so extra words narrow the
     * result the way a search box is expected to.
     *
     * @param array $row
     * @param string $search
     * @return bool
     */
    private function matches_search(array $row, string $search): bool {
        // Folded the same way the haystack was, so an Arabic word typed without its
        // diacritics — or with the other accepted spelling of an alef — still meets the
        // stored value. See text_util::normalise().
        foreach (text_util::search_words($search) as $word) {
            if (strpos($row['haystack'], $word) === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Price filters read the amount the viewer would actually pay, offers included — the
     * number printed on the card. Filtering on the pre-discount price would hide a course
     * from a budget its own card says it fits.
     *
     * @param array $row
     * @param array $active
     * @return bool
     */
    private function matches_price(array $row, array $active): bool {
        $info = pricing::info($row['id']);

        if (!empty($active['free'])) {
            return !$info['haspricing'];
        }
        if (!$info['haspricing']) {
            // A free course sits at zero, so it belongs in any range that starts at zero.
            $price = 0.0;
        } else if (!empty($info['countryrequired'])) {
            // No price exists for this viewer at all; a range filter cannot honestly keep it.
            return false;
        } else {
            $price = pricing::effective_price($info);
        }

        if (isset($active['pricemin']) && $price < $active['pricemin']) {
            return false;
        }
        if (isset($active['pricemax']) && $price > $active['pricemax']) {
            return false;
        }
        return true;
    }

    /**
     * Whether one course satisfies one custom-field filter.
     *
     * @param array $row
     * @param array $filter
     * @param mixed $wanted
     * @return bool
     */
    private function matches_field(array $row, array $filter, $wanted): bool {
        $bundle = $row['values'][$filter['shortname']] ?? null;

        if ($filter['kind'] === self::KIND_OPTIONS) {
            if ($bundle === null) {
                return false;
            }
            // OR within a filter: any ticked value is enough.
            return (bool) array_intersect($wanted, $bundle['keys'] ?? []);
        }

        if ($filter['kind'] === self::KIND_BOOL) {
            return $bundle !== null && !empty($bundle['bool']);
        }

        if ($filter['kind'] === self::KIND_BUCKETS) {
            if ($bundle === null || !isset($bundle['number'])) {
                return false;
            }
            // OR across the ticked bands, like any other multi-value group.
            foreach ((array) $wanted as $key) {
                if (self::in_bucket((float) $bundle['number'], $filter['buckets'][$key] ?? null)) {
                    return true;
                }
            }
            return false;
        }

        if ($filter['kind'] === self::KIND_RANGE) {
            if ($bundle === null || !isset($bundle['number'])) {
                return false;
            }
            $number = (float) $bundle['number'];
            if (isset($wanted['min']) && $number < $wanted['min']) {
                return false;
            }
            if (isset($wanted['max']) && $number > $wanted['max']) {
                return false;
            }
            return true;
        }

        return true;
    }

    /**
     * Does a number fall in one band?
     *
     * Inclusive of the lower bound, exclusive of the upper, so 10 hours is "10 – 25" and
     * never also "under 10" — the bands have to partition the range or the counts beside
     * them add up to more than the result set.
     *
     * @param float $number
     * @param array|null $bucket ['min' => float|null, 'max' => float|null]
     * @return bool
     */
    private static function in_bucket(float $number, ?array $bucket): bool {
        if ($bucket === null) {
            return false;
        }
        if ($bucket['min'] !== null && $number < $bucket['min']) {
            return false;
        }
        if ($bucket['max'] !== null && $number >= $bucket['max']) {
            return false;
        }
        return true;
    }

    // =========================================================================
    // Facets.
    // =========================================================================

    /**
     * The option lists to draw, with a count beside each.
     *
     * A filter's own selection is left out when counting its options — otherwise ticking
     * "Foundation" would drop every other level to zero and the group would become a
     * dead end. Every OTHER active filter still applies, so the counts tell the truth
     * about what one more tick would give you.
     *
     * @return array[] one entry per filter, in field order
     */
    public function facets(): array {
        $out = [];

        foreach ($this->filters as $shortname => $filter) {
            $others = $this->active;
            unset($others[$shortname]);
            $scope = $this->filter_rows($this->rows, $others);

            if ($filter['kind'] === self::KIND_BOOL) {
                $count = 0;
                foreach ($scope as $row) {
                    if (!empty($row['values'][$shortname]['bool'])) {
                        $count++;
                    }
                }
                // Nothing in scope carries this flag: a toggle that can only empty the page
                // is not a filter, it is a trap.
                if ($count === 0 && empty($this->active[$shortname])) {
                    continue;
                }
                $out[] = $filter + [
                    'count'    => $count,
                    'selected' => !empty($this->active[$shortname]),
                ];
                continue;
            }

            if ($filter['kind'] === self::KIND_BUCKETS) {
                $picked = $this->active[$shortname] ?? [];
                $values = [];
                foreach ($filter['buckets'] as $key => $bucket) {
                    $count = 0;
                    foreach ($scope as $row) {
                        $number = $row['values'][$shortname]['number'] ?? null;
                        if ($number !== null && self::in_bucket((float) $number, $bucket)) {
                            $count++;
                        }
                    }
                    // All three bands, always — like a select field's options and unlike a
                    // text field's, these are a fixed vocabulary the design promises, so
                    // the group reads the same on every page of the catalogue. The count
                    // beside an empty one says plainly that it will find nothing.
                    $values[] = [
                        'key'      => $key,
                        'label'    => get_string('duration' . $key, 'local_nit_category'),
                        'count'    => $count,
                        'selected' => in_array($key, $picked, true),
                    ];
                }
                if (empty($values)) {
                    continue;
                }
                $out[] = $filter + ['values' => $values];
                continue;
            }

            if ($filter['kind'] === self::KIND_RANGE) {
                $numbers = [];
                foreach ($scope as $row) {
                    if (isset($row['values'][$shortname]['number'])) {
                        $numbers[] = (float) $row['values'][$shortname]['number'];
                    }
                }
                if (count($numbers) < 2 && !isset($this->active[$shortname])) {
                    // One value (or none) is not a range worth offering — unless a range is
                    // already in force, in which case hiding the group would hide the only
                    // control that can undo it.
                    continue;
                }
                $out[] = $filter + [
                    'bound_min' => $numbers ? min($numbers) : 0.0,
                    'bound_max' => $numbers ? max($numbers) : 0.0,
                    'min'       => $this->active[$shortname]['min'] ?? null,
                    'max'       => $this->active[$shortname]['max'] ?? null,
                ];
                continue;
            }

            // Options: count the courses behind every value that is actually in use, so a
            // select option no course has chosen never appears.
            $counts = [];
            $labels = [];
            foreach ($scope as $row) {
                $bundle = $row['values'][$shortname] ?? null;
                if ($bundle === null) {
                    continue;
                }
                foreach ($bundle['keys'] as $i => $key) {
                    $label = $bundle['labels'][$i] ?? $key;
                    if (!self::is_label($label)) {
                        continue;
                    }
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                    $labels[$key] = $labels[$key] ?? $label;
                }
            }

            // A select field's options are a vocabulary an administrator chose on purpose,
            // so the whole list is offered even before any course carries a value — the
            // four levels of AC-4.8.5 are a promise about the catalogue, not a summary of
            // what happens to be filled in today. A text field is the opposite: its values
            // exist only because some course holds them, so it lists what is there.
            if ($filter['type'] === 'select') {
                foreach ($filter['options'] as $label) {
                    $key = text_util::key($label);
                    $counts[$key] = $counts[$key] ?? 0;
                    $labels[$key] = $labels[$key] ?? $label;
                }
            }

            // A value the visitor has already ticked stays listed even when nothing else in
            // scope carries it, so the tick can always be undone from where it was made.
            foreach (($this->active[$shortname] ?? []) as $key) {
                $counts[$key] = $counts[$key] ?? 0;
                $labels[$key] = $labels[$key] ?? $key;
            }
            if (empty($counts)) {
                continue;
            }

            // Already-ticked values first — they must survive the cap below, or a filter
            // could not be unticked from the panel that set it — then most-used, then
            // alphabetically, so the useful ticks are the visible ones.
            $picked = array_flip($this->active[$shortname] ?? []);
            uksort($counts, static function ($a, $b) use ($counts, $labels, $picked) {
                $sa = isset($picked[$a]) ? 1 : 0;
                $sb = isset($picked[$b]) ? 1 : 0;
                if ($sa !== $sb) {
                    return $sb <=> $sa;
                }
                if ($counts[$a] !== $counts[$b]) {
                    return $counts[$b] <=> $counts[$a];
                }
                return text_util::collate($labels[$a], $labels[$b]);
            });

            // A short-text field is free typing, so it can hold a phrase no other course
            // will ever repeat. When nothing in the field is shared between courses, it is
            // prose rather than a category and every "filter" it offers would return one
            // course — the search box already does that better. A select field is exempt:
            // its options are a deliberate list, however sparsely used.
            if ($filter['type'] === 'text' && empty($this->active[$shortname])
                    && count($counts) > self::OPTIONS_VISIBLE && max($counts) < 2) {
                continue;
            }

            $options = [];
            foreach ($counts as $key => $count) {
                $options[] = [
                    'key'      => $key,
                    'label'    => $labels[$key],
                    'count'    => $count,
                    'selected' => in_array($key, $this->active[$shortname] ?? [], true),
                ];
                // Sorted by count, so the cap keeps the values that actually group courses
                // and drops the long tail nobody would scroll to.
                if (count($options) >= self::OPTIONS_MAX) {
                    break;
                }
            }
            $out[] = $filter + ['values' => $options];
        }

        return $out;
    }

    /**
     * The categories present in the result set, with counts — the same facet treatment as
     * any custom field, so browsing by category is one more tick rather than a separate page.
     *
     * @return array[]
     */
    public function category_facet(): array {
        $others = $this->active;
        unset($others['cat']);
        $scope = $this->filter_rows($this->rows, $others);

        $counts = [];
        $names = [];
        foreach ($scope as $row) {
            $counts[$row['catid']] = ($counts[$row['catid']] ?? 0) + 1;
            $names[$row['catid']] = $row['catname'];
        }
        foreach (($this->active['cat'] ?? []) as $catid) {
            if (!isset($counts[$catid])) {
                $category = core_course_category::get($catid, IGNORE_MISSING, true);
                $counts[$catid] = 0;
                $names[$catid] = $category ? $category->get_formatted_name() : (string) $catid;
            }
        }
        if (count($counts) < 2 && empty($this->active['cat'])) {
            // Everything sits in one category: the filter would be decoration.
            return [];
        }

        uksort($counts, static function ($a, $b) use ($counts, $names) {
            if ($counts[$a] !== $counts[$b]) {
                return $counts[$b] <=> $counts[$a];
            }
            return text_util::collate($names[$a], $names[$b]);
        });

        $out = [];
        foreach ($counts as $catid => $count) {
            $out[] = [
                'key'      => (string) $catid,
                'label'    => $names[$catid],
                'count'    => $count,
                'selected' => in_array((int) $catid, $this->active['cat'] ?? [], true),
            ];
        }
        return $out;
    }

    /**
     * Whether a stored value is short enough to work as a tick-box label or a card chip.
     *
     * @param string $value
     * @return bool
     */
    public static function is_label(string $value): bool {
        $value = trim($value);
        if ($value === '' || \core_text::strlen($value) > self::MAX_OPTION_LENGTH) {
            return false;
        }
        // Length alone is a blunt test across two scripts — Arabic says the same thing in
        // fewer characters — so the word count backs it up.
        return count(preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: []) <= self::MAX_OPTION_WORDS;
    }

    /**
     * The chips and the meta line one card prints.
     *
     * Both are read from the same field values the filters were built from, and through the
     * same is_label() test, so a card never advertises something the panel cannot filter on
     * — and never prints a paragraph in a pill.
     *
     * @param array $row a row from {@see self::matches()}
     * @return array{chips: string[], meta: string[]}
     */
    public function card_labels(array $row): array {
        $chips = [];
        $meta = [];

        foreach ($this->filters as $shortname => $filter) {
            $bundle = $row['values'][$shortname] ?? null;
            if ($bundle === null) {
                continue;
            }
            if ($filter['kind'] === self::KIND_OPTIONS) {
                foreach ($bundle['labels'] as $label) {
                    if (self::is_label($label)) {
                        $chips[] = $label;
                    }
                }
            } else if ($filter['kind'] === self::KIND_BOOL && !empty($bundle['bool'])) {
                $chips[] = $filter['name'];
            } else if ($filter['kind'] === self::KIND_BUCKETS && isset($bundle['number'])) {
                // The design's card shows "16 h" as a chip beside the level, so the hours
                // go in as a chip rather than on the meta line a bare number would need.
                $chips[] = get_string('hoursshort', 'local_nit_category',
                    text_util::number($bundle['number']));
            } else if ($filter['kind'] === self::KIND_RANGE && isset($bundle['number'])) {
                // A bare number on a card says nothing, so a numeric field is printed as
                // "<field name>: <value>" on its own line rather than squeezed into a chip.
                $meta[] = $filter['name'] . ': ' . text_util::number($bundle['number']);
            }
        }

        return [
            'chips' => array_slice(array_values(array_unique($chips)), 0, 4),
            'meta'  => array_slice($meta, 0, 2),
        ];
    }

    // =========================================================================
    // Sorting.
    // =========================================================================

    /**
     * The sort keys the catalogue offers.
     *
     * @return array<string, string> key => label
     */
    public static function sort_options(): array {
        return [
            'popular'  => get_string('sortpopular', 'local_nit_category'),
            'newest'   => get_string('sortnewest', 'local_nit_category'),
            'name'     => get_string('sortname', 'local_nit_category'),
            'pricelow' => get_string('sortpricelow', 'local_nit_category'),
            'pricehigh' => get_string('sortpricehigh', 'local_nit_category'),
        ];
    }

    /**
     * Order a result set.
     *
     * @param array[] $rows
     * @param string $sort one of {@see self::sort_options()}
     * @return array[]
     */
    public static function sort(array $rows, string $sort): array {
        if ($sort === 'newest') {
            usort($rows, static fn($a, $b) => $b['timecreated'] <=> $a['timecreated']);
        } else if ($sort === 'name') {
            usort($rows, static fn($a, $b) => text_util::collate(
                $a['course']->get_formatted_name(), $b['course']->get_formatted_name()));
        } else if ($sort === 'pricelow' || $sort === 'pricehigh') {
            $prices = [];
            foreach ($rows as $row) {
                $prices[$row['id']] = pricing::effective_price(pricing::info($row['id']));
            }
            usort($rows, static function ($a, $b) use ($prices, $sort) {
                $cmp = $prices[$a['id']] <=> $prices[$b['id']];
                return $sort === 'pricelow' ? $cmp : -$cmp;
            });
        } else {
            usort($rows, static fn($a, $b) => $b['popularity'] <=> $a['popularity']);
        }
        return $rows;
    }
}
