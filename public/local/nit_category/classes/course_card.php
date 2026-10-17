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

use context_course;
use core_course_list_element;
use moodle_url;

/**
 * The top half of the course card (Figma "Course Card", file "الوجيستيات").
 *
 * Accent strip, cover picture, title + second line, a rule, and the stats row
 * (lessons · hours · language). Both the category page and the catalogue print it, so
 * the two cards can never drift apart again; each page then prints its own footer
 * (price row + buttons) inside `.nitcc__foot`, because the two pages resolve the price
 * through different helpers. The look lives in styles.css under "Course card".
 *
 * Every number comes from data the course already has: lessons are its visible
 * activities (labels and sub-sections are not lessons), and hours and language are the
 * fields the catalogue's Duration and Language filters read. A stat with nothing behind
 * it is left out, never shown as 0.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_card {

    /** @var string[] module names that are not a "lesson" a learner works through */
    private const NOT_LESSONS = ['label', 'subsection', 'qbank'];

    /** @var array<int,int>|null course id => lesson count, loaded for the whole site at once */
    private static $lessons = null;

    /** @var array<int,float>|null course id => total hours */
    private static $hours = null;

    /** @var array<int,string>|null course id => stored Language value (may be {mlang}) */
    private static $languages = null;

    /**
     * The card's opening markup, up to (not including) the footer.
     *
     * The caller prints its footer and then {@see self::close()}.
     *
     * @param core_course_list_element $course
     * @param array $opts optional: 'context' (plain text above the title, e.g. the
     *     category name), 'teacherhtml' (already-escaped HTML), 'chips' (plain strings)
     * @return string
     */
    public static function open(core_course_list_element $course, array $opts = []): string {
        $courseid   = (int) $course->id;
        $detailsurl = (new moodle_url('/course/view.php', ['id' => $courseid]))->out();
        $coursename = $course->get_formatted_name();
        $imageurl   = self::image_url($course);
        $subtitle   = self::subtitle($course);

        $h = '<article class="nitcc" data-nit-course="' . $courseid . '">';
        $h .= '<span class="nitcc__strip" aria-hidden="true"></span>';

        $h .= '<a class="nitcc__media" href="' . $detailsurl . '" tabindex="-1" aria-hidden="true">';
        if ($imageurl !== '') {
            $h .= '<img src="' . s($imageurl) . '" alt="" loading="lazy">';
        } else {
            // No picture: the tile keeps the card's proportions and shows the name.
            $h .= '<span class="nitcc__noimage">' . s(shorten_text(text_util::plain($coursename), 60)) . '</span>';
        }
        $h .= '</a>';

        $h .= '<div class="nitcc__body">';
        $h .= '<div class="nitcc__titles">';
        if (!empty($opts['context'])) {
            $h .= '<div class="nitcc__context">' . s($opts['context']) . '</div>';
        }
        $h .= '<h3 class="nitcc__title"><a href="' . $detailsurl . '">' . $coursename . '</a></h3>';
        if ($subtitle !== '') {
            $h .= '<p class="nitcc__sub">' . s($subtitle) . '</p>';
        }
        if (!empty($opts['teacherhtml'])) {
            $h .= '<div class="nitcc__teacher">' . $opts['teacherhtml'] . '</div>';
        }
        $h .= '</div>';

        $stats = self::stats($course);
        // A chip that repeats a stat (the language) is dropped.
        $stattexts = array_column($stats, 1);
        $chips = array_values(array_filter((array) ($opts['chips'] ?? []),
            static fn($c) => (string) $c !== '' && !in_array((string) $c, $stattexts, true)));
        if ($stats || $chips) {
            $h .= '<hr class="nitcc__rule">';
        }
        if ($stats) {
            $h .= '<ul class="nitcc__stats">';
            foreach ($stats as [$icon, $text]) {
                $h .= '<li class="nitcc__stat">' . self::icon($icon) . '<span>' . s($text) . '</span></li>';
            }
            $h .= '</ul>';
        }
        if ($chips) {
            $h .= '<div class="nitcc__chips">';
            foreach ($chips as $chip) {
                $h .= '<span class="nitcc__chip">' . s($chip) . '</span>';
            }
            $h .= '</div>';
        }
        return $h;
    }

    /**
     * Close what {@see self::open()} opened.
     *
     * @return string
     */
    public static function close(): string {
        return '</div></article>';
    }

    /**
     * The course's own overview picture, or '' for none.
     *
     * @param core_course_list_element $course
     * @return string
     */
    public static function image_url(core_course_list_element $course): string {
        foreach ($course->get_course_overviewfiles() as $file) {
            if (!$file->is_valid_image()) {
                continue;
            }
            return moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(),
                $file->get_filearea(), null, $file->get_filepath(), $file->get_filename())->out(false);
        }
        return '';
    }

    /**
     * The muted second line under the title.
     *
     * The design puts the course name in the other language there. A bilingual name
     * ({mlang en}…{mlang}{mlang ar}…{mlang}) has one, so that is used; otherwise the
     * opening of the summary stands in, which says more than repeating the title.
     *
     * @param core_course_list_element $course
     * @return string plain text, '' for none
     */
    public static function subtitle(core_course_list_element $course): string {
        $current = text_util::ml($course->fullname);
        if (stripos((string) $course->fullname, '{mlang') !== false
                && preg_match_all('/\{mlang\s+([^}]+)\}(.*?)\{mlang\}/is', $course->fullname, $m, PREG_SET_ORDER)) {
            foreach ($m as $block) {
                $text = trim(strip_tags($block[2]));
                if ($text !== '' && $text !== $current) {
                    return $text;
                }
            }
        }
        if (!$course->has_summary()) {
            return '';
        }
        $context = context_course::instance((int) $course->id);
        $plain = html_to_text(format_text($course->summary, $course->summaryformat,
            ['context' => $context, 'noclean' => true]), 0, false);
        return shorten_text(trim(preg_replace('/\s+/u', ' ', $plain)), 140);
    }

    /**
     * The stats row: [icon, text] pairs, only for the numbers the course has.
     *
     * @param core_course_list_element $course
     * @return array<int, array{0: string, 1: string}>
     */
    public static function stats(core_course_list_element $course): array {
        $courseid = (int) $course->id;
        self::load();

        $stats = [];
        $lessons = self::$lessons[$courseid] ?? 0;
        if ($lessons > 0) {
            $stats[] = ['book', get_string('cardlessons', 'local_nit_category', $lessons)];
        }

        $hours = self::$hours[$courseid] ?? 0.0;
        if ($hours > 0) {
            $stats[] = ['clock', get_string('cardhours', 'local_nit_category', text_util::number($hours))];
        }
        // Not "weeks" from the course dates, although the design shows one: Moodle gives
        // every new course an end date a year out, so nearly every card would say 53 weeks.
        $language = text_util::ml(self::$languages[$courseid] ?? '');
        if ($language !== '' && catalogue::is_label($language)) {
            $stats[] = ['globe', $language];
        }
        return $stats;
    }

    /**
     * Load the lesson counts and hours for every course in two queries.
     *
     * A page prints dozens of cards; one query per card would be dozens of queries.
     */
    private static function load(): void {
        global $DB;
        if (self::$lessons !== null) {
            return;
        }

        [$notin, $params] = $DB->get_in_or_equal(self::NOT_LESSONS, SQL_PARAMS_NAMED, 'nl', false);
        $sql = "SELECT cm.course, COUNT(cm.id) AS n
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.visible = 1 AND cm.deletioninprogress = 0 AND m.name $notin
              GROUP BY cm.course";
        self::$lessons = array_map('intval', $DB->get_records_sql_menu($sql, $params));

        // The same two fields the catalogue's Duration and Language filters read.
        self::$hours = [];
        foreach (self::field_values('duration', 'total_number_of_hours') as $id => $row) {
            $h = (float) ($row->decvalue ?? $row->value);
            if ($h > 0) {
                self::$hours[$id] = $h;
            }
        }
        self::$languages = [];
        foreach (self::field_values('language', 'language') as $id => $row) {
            self::$languages[$id] = (string) $row->value;
        }
    }

    /**
     * Every course's stored value of one course custom field.
     *
     * @param string $role the catalogue filter role whose setting names the field
     * @param string $default the field's shortname when that setting is empty
     * @return array<int, \stdClass> course id => {value, decvalue}
     */
    private static function field_values(string $role, string $default): array {
        global $DB;
        $shortname = trim((string) get_config('local_nit_category', 'filterfield_' . $role));
        if ($shortname === '') {
            $shortname = $default;
        }
        $sql = "SELECT d.instanceid, d.value, d.decvalue
                  FROM {customfield_data} d
                  JOIN {customfield_field} f ON f.id = d.fieldid
                  JOIN {customfield_category} c ON c.id = f.categoryid
                 WHERE c.component = 'core_course' AND c.area = 'course' AND f.shortname = :sn";
        $rows = [];
        foreach ($DB->get_records_sql($sql, ['sn' => $shortname]) as $row) {
            $rows[(int) $row->instanceid] = $row;
        }
        return $rows;
    }

    /**
     * One of the three stat icons, drawn in the brand colour by CSS (currentColor).
     *
     * @param string $name book|clock|globe
     * @return string
     */
    private static function icon(string $name): string {
        $paths = [
            'book'     => '<path d="M2 4.5h5a3 3 0 0 1 3 3V20a2.2 2.2 0 0 0-2.2-2.2H2z"/>'
                        . '<path d="M22 4.5h-5a3 3 0 0 0-3 3V20a2.2 2.2 0 0 1 2.2-2.2H22z"/>',
            'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        ];
        return '<svg class="nitcc__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
            . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . $paths[$name] . '</svg>';
    }
}
