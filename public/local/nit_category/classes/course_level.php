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

use core_course\customfield\course_handler;

/**
 * The "Level" of a course, as the category page shows it.
 *
 * Level is a course custom field ("Course File Summary" → Level on the course settings
 * form), the same field the catalogue's Level filter reads: its shortname comes from the
 * `filterfield_level` setting and defaults to `level`. A select field gives a ladder —
 * "Level 1 … Level 4" in the order the admin typed the options — which is what lets a
 * course print "rung 2 of 4" as a little meter and lets a category page group its courses
 * rung by rung. A short-text field has no ladder, so it is shown as a plain label only.
 *
 * Everything is read in one query for the whole page (a category page asks about every
 * course under it) and every label is resolved to the current language once, here.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_level {

    /** @var \core_customfield\field_controller|null|false false = not looked up yet */
    private static $field = false;

    /** @var string[] rung index (1-based) => label, in ladder order; [] for a text field */
    private static array $ladder = [];

    /**
     * The Level custom field, or null when the site has none the page may show.
     *
     * Only a select or short-text field can answer "what level is this?"; anything else
     * (a checkbox, a date) is treated as no field. A field hidden from everybody but
     * teachers is also treated as no field: the category page is public.
     *
     * @return \core_customfield\field_controller|null
     */
    public static function field(): ?\core_customfield\field_controller {
        if (self::$field !== false) {
            return self::$field;
        }
        self::$field = null;

        $shortname = trim((string) get_config('local_nit_category', 'filterfield_level'));
        if ($shortname === '') {
            $shortname = 'level';
        }
        try {
            foreach (course_handler::create()->get_fields() as $field) {
                if (\core_text::strtolower((string) $field->get('shortname')) !== \core_text::strtolower($shortname)) {
                    continue;
                }
                $type = (string) $field->get('type');
                $visibility = $field->get_configdata_property('visibility') ?? course_handler::VISIBLETOALL;
                if (!in_array($type, ['select', 'text'], true)
                        || (int) $visibility !== course_handler::VISIBLETOALL) {
                    break;
                }
                self::$field = $field;
                if ($type === 'select') {
                    // Index 0 is the "not set" placeholder core prepends; the rungs start at 1.
                    // get_options() has already run format_string(), so {mlang} is resolved
                    // and the labels are HTML-escaped — they print as they are.
                    foreach ($field->get_options() as $index => $label) {
                        if ($index > 0 && trim((string) $label) !== '') {
                            self::$ladder[(int) $index] = (string) $label;
                        }
                    }
                }
                break;
            }
        } catch (\Throwable $e) {
            self::$field = null;
        }
        return self::$field;
    }

    /**
     * The ladder: rung index => label, lowest first. Empty for a text field or no field.
     *
     * @return string[]
     */
    public static function ladder(): array {
        self::field();
        return self::$ladder;
    }

    /**
     * How many rungs the ladder has (the "of 4" in "2 of 4"); 0 when there is no ladder.
     *
     * @return int
     */
    public static function max(): int {
        return count(self::ladder());
    }

    /**
     * The level of each course, in one query.
     *
     * @param int[] $courseids
     * @return array<int, array{index: int, label: string}> course id => level; a course
     *      with no level is simply absent. `index` is the rung (1-based) for a select field
     *      and 0 for a text field, whose labels have no order.
     */
    public static function for_courses(array $courseids): array {
        global $DB;

        $courseids = array_values(array_unique(array_map('intval', $courseids)));
        $field = self::field();
        if ($field === null || empty($courseids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');
        $params['fieldid'] = (int) $field->get('id');
        $records = $DB->get_records_sql(
            "SELECT d.instanceid, d.intvalue, d.charvalue, d.value
               FROM {customfield_data} d
              WHERE d.fieldid = :fieldid AND d.instanceid $insql",
            $params
        );

        $out = [];
        $type = (string) $field->get('type');
        foreach ($records as $record) {
            if ($type === 'select') {
                $index = (int) $record->intvalue;
                if (isset(self::$ladder[$index])) {
                    $out[(int) $record->instanceid] = ['index' => $index, 'label' => self::$ladder[$index]];
                }
            } else {
                // A short-text value may be bilingual ("{mlang en}…") and may hold several
                // chips; the first one is the level.
                $raw = ($record->charvalue !== null && $record->charvalue !== '') ? $record->charvalue : $record->value;
                $values = text_util::values($raw);
                if (!empty($values)) {
                    $out[(int) $record->instanceid] = ['index' => 0, 'label' => s($values[0])];
                }
            }
        }
        return $out;
    }
}
