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

/**
 * What the home page's dynamic sections need to know (Section 4.7).
 *
 * The home page is assembled from static HTML blocks pasted into block_nit_section,
 * each holding a hidden card template with `{{placeholder}}` markers that its own
 * inline script clones and fills from a JSON feed. The coupons and packages blocks
 * already worked that way; the categories and My-courses blocks shipped with the
 * markup and no feed, so they rendered as empty strips. This class is the missing
 * half.
 *
 * Everything here returns plain arrays of scalars ready for `json_encode` - no
 * renderables, no HTML - because the shape of a card is the block's business and
 * the numbers are ours.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class home {

    /**
     * The category grid: name, link, image and live course count (AC-4.7.6).
     *
     * Only top-level categories, because the grid is the site's six subject areas
     * rather than a tree. The count is recursive, so a category whose courses all
     * live in subcategories reports the number a visitor would actually find after
     * clicking, not zero.
     *
     * `core_course_category::get_all()` returns only what this viewer may see, so a
     * hidden category is absent for a guest and present for a manager without this
     * method having to know the difference.
     *
     * @param int $limit most categories to return
     * @return array[] one row per category
     */
    public static function categories(int $limit = 12): array {
        $limit = max(1, min(50, $limit));
        $rows = [];

        foreach (core_course_category::get_all(['returnhidden' => false]) as $category) {
            if ((int) $category->parent !== 0) {
                continue;
            }

            // A category with nothing in it is a dead link on the front page.
            $count = self::course_count($category);
            if ($count === 0) {
                continue;
            }

            $rows[] = [
                'id' => (int) $category->id,
                'name' => $category->get_formatted_name(),
                'url' => (new moodle_url('/local/nit_category/index.php',
                    ['id' => $category->id]))->out(false),
                'coursecount' => $count,
                'image' => local_nit_category_get_image_url((int) $category->id),
                'icon' => self::icon($category),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * How many courses a visitor would find under this category.
     *
     * Recursive, and visibility-aware for the same reason the listing is.
     *
     * @param core_course_category $category
     * @return int
     */
    protected static function course_count(core_course_category $category): int {
        try {
            return (int) $category->get_courses_count(['recursive' => true]);
        } catch (\Throwable $e) {
            // A category whose count cannot be computed is shown with none rather
            // than taking the whole grid down with it.
            return 0;
        }
    }

    /**
     * The category's icon: the uploaded glyph, or the emoji an admin typed for it.
     *
     * @param core_course_category $category
     * @return string a URL, a single emoji, or ''
     */
    protected static function icon(core_course_category $category): string {
        $url = local_nit_category_get_icon_url((int) $category->id);
        if ($url !== '') {
            return $url;
        }

        $map = json_decode((string) get_config('local_nit_category',
            LOCAL_NIT_CATEGORY_ICON_CONFIG), true);

        return is_array($map) ? (string) ($map[$category->id] ?? '') : '';
    }

    /**
     * The learner's active enrolments, with progress and a resume point.
     *
     * AC-4.7.7 to AC-4.7.9. An empty array means "render the guest or empty
     * state" - the block cannot tell the two apart and does not need to, because
     * both draw the same invitation to browse the catalogue.
     *
     * Expired enrolments are excluded, as AC-4.7.8 requires. That is what the
     * timeend check is doing: a subscription that has run out leaves the
     * enrolment row in place with a timeend in the past, so filtering on the
     * enrolment's status alone would keep showing courses the learner can no
     * longer open.
     *
     * @param int $limit most courses to return
     * @return array[] one row per course, most recently accessed first
     */
    public static function my_courses(int $limit = 12): array {
        global $USER, $DB;

        if (!isloggedin() || isguestuser()) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $rows = [];

        // Sorted by last access so "carry on where you left off" is the first card
        // rather than something bought a year ago.
        // $allaccessible stays false. With it on, enrol_get_my_courses() also
        // returns courses the visitor merely *can* open - guest-accessible ones,
        // and anything their role lets them view - and AC-4.7.8 asks for "the
        // learner's active enrolments". A course somebody can look at is not a
        // course they own, and listing it under My courses with a Resume button
        // tells them they bought something they did not.
        $courses = enrol_get_my_courses(
            ['id', 'fullname', 'shortname', 'summary', 'summaryformat', 'category', 'visible'],
            'ul.timeaccess DESC, c.fullname ASC',
            0,
            [],
            false
        );

        foreach ($courses as $course) {
            if (!$course->visible && !can_access_course($course)) {
                continue;
            }
            if (self::enrolment_expired((int) $course->id, (int) $USER->id)) {
                continue;
            }

            $context = \context_course::instance($course->id);
            $resume = self::resume_point($course, $USER);
            $activities = self::activities($course, (int) $resume['cmid']);

            $rows[] = [
                'id' => (int) $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'summary' => shorten_text(strip_tags(format_text(
                    $course->summary ?? '', $course->summaryformat ?? FORMAT_HTML,
                    ['context' => $context])), 140),
                'teacher' => self::teacher_name($context),
                'image' => self::course_image($course, $context),
                'progress' => self::progress($course, $USER),
                'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'resumeurl' => $resume['url'],
                // The card's second line: which lesson Resume would open.
                'subtitle' => $activities['lesson'],
                'lessons' => $activities['count'],
                'hours' => self::hours((int) $course->id),
                'price' => self::price_label((int) $course->id),
            ];

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * Has this learner's access to the course already run out?
     *
     * A subscription that has lapsed leaves the user_enrolments row behind with a
     * timeend in the past. `enrol_get_my_courses()` filters on the enrolment being
     * active, but "active" and "not yet ended" are not the same thing on every
     * enrolment plugin, so the end date is checked here as well. A `timeend` of 0
     * means no end date, which is the ordinary purchased-course case.
     *
     * @param int $courseid
     * @param int $userid
     * @return bool
     */
    protected static function enrolment_expired(int $courseid, int $userid): bool {
        global $DB;

        $sql = "SELECT MAX(CASE WHEN ue.timeend = 0 THEN 1 ELSE 0 END) AS unlimited,
                       MAX(ue.timeend) AS latest
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :userid
                   AND e.courseid = :courseid
                   AND ue.status = :active";

        $row = $DB->get_record_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
            'active' => ENROL_USER_ACTIVE,
        ]);

        if (!$row || $row->latest === null) {
            // No active enrolment row at all. Leave the decision to the caller's
            // own listing rather than second-guessing it here.
            return false;
        }

        if (!empty($row->unlimited)) {
            return false;
        }

        return (int) $row->latest > 0 && (int) $row->latest < time();
    }

    /**
     * Completion progress as a whole percentage, or null when there is none to show.
     *
     * Null rather than zero when the course has no completion tracking configured:
     * a bar reading 0% on a course the learner has half finished is worse than no
     * bar, because it looks like lost work rather than an absent feature.
     *
     * @param \stdClass $course
     * @param \stdClass $user
     * @return int|null 0-100, or null
     */
    protected static function progress(\stdClass $course, \stdClass $user): ?int {
        try {
            $completion = new \completion_info($course);
            if (!$completion->is_enabled() || !$completion->is_tracked_user($user->id)) {
                return null;
            }

            $percent = \core_completion\progress::get_course_progress_percentage($course, $user->id);

            return $percent === null ? null : (int) round($percent);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Where "Resume" should go: the last activity touched, or the course page.
     *
     * AC-4.7.8 asks for "a Resume action returning to the last position". Moodle
     * records the last module a user viewed in each course in its own log, which is
     * the closest thing to a bookmark it keeps.
     *
     * Falls back to the course page whenever the log cannot answer - the log store
     * may be off, may have been pruned, or the module may since have been deleted.
     * A resume that lands on the course page is a small disappointment; one that
     * 404s is a bug report.
     *
     * The module id comes back with the URL because the card names the lesson it
     * would open ("Lesson 4: Grammar rules"), and asking the log twice for the
     * same answer would be two queries for one fact.
     *
     * @param \stdClass $course
     * @param \stdClass $user
     * @return array{url: string, cmid: int} cmid 0 when the log cannot answer
     */
    protected static function resume_point(\stdClass $course, \stdClass $user): array {
        global $DB;

        $fallback = [
            'url' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'cmid' => 0,
        ];

        try {
            $manager = get_log_manager();
            $stores = $manager ? $manager->get_readers('\core\log\sql_reader') : [];
            $reader = reset($stores);
            if (!$reader) {
                return $fallback;
            }

            $select = "courseid = :courseid AND userid = :userid AND contextlevel = :modlevel
                       AND crud = 'r' AND target = 'course_module'";
            $events = $reader->get_events_select(
                $select,
                [
                    'courseid' => $course->id,
                    'userid' => $user->id,
                    'modlevel' => CONTEXT_MODULE,
                ],
                'timecreated DESC',
                0,
                1
            );

            $event = reset($events);
            if (!$event) {
                return $fallback;
            }

            $cm = get_coursemodule_from_id('', $event->contextinstanceid, 0, false, IGNORE_MISSING);
            if (!$cm || !$cm->visible) {
                return $fallback;
            }

            return [
                'url' => (new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false),
                'cmid' => (int) $cm->id,
            ];
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    /**
     * How many lessons the course holds, and which one Resume would open.
     *
     * Both answers come out of one `get_fast_modinfo()` pass because they are the
     * same walk over the same list. "Lesson" here means an activity the learner can
     * actually open: `has_view()` filters out labels and other page decorations,
     * which are modules to Moodle but not to anybody counting their lessons.
     *
     * The numbering is the learner's own - the fourth lesson they can see, not the
     * fourth row in the database - so a hidden or restricted activity does not
     * leave a gap in the count.
     *
     * @param \stdClass $course
     * @param int $resumecmid the module {@see self::resume_point()} found, or 0
     * @return array{count: int, lesson: string} lesson is '' when there is nothing to name
     */
    protected static function activities(\stdClass $course, int $resumecmid): array {
        $out = ['count' => 0, 'lesson' => ''];

        try {
            $modinfo = get_fast_modinfo($course);
        } catch (\Throwable $e) {
            return $out;
        }

        $number = 0;
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible || !$cm->has_view()) {
                continue;
            }
            $out['count']++;

            if ($resumecmid && (int) $cm->id === $resumecmid) {
                $number = $out['count'];
                $out['lesson'] = get_string('homelesson', 'local_nit_category', (object) [
                    'num' => $number,
                    'name' => format_string($cm->name, true, ['context' => $cm->context]),
                ]);
            }
        }

        return $out;
    }

    /**
     * The course's advertised length in hours, from the site's own custom field.
     *
     * `total_number_of_hours` is the field the catalogue already filters on, so the
     * card and the catalogue quote the same number. Null when the course does not
     * carry it, or when the field is hidden from this viewer - the card leaves the
     * stat out rather than printing a zero the course never claimed.
     *
     * @param int $courseid
     * @return int|null
     */
    protected static function hours(int $courseid): ?int {
        try {
            // No second argument, so this is only the fields this viewer may see.
            $data = \core_course\customfield\course_handler::create()->get_instance_data($courseid);
        } catch (\Throwable $e) {
            return null;
        }

        foreach ($data as $datum) {
            if ((string) $datum->get_field()->get('shortname') !== 'total_number_of_hours') {
                continue;
            }
            if (!$datum->get('id')) {
                // The field exists on the site but this course never filled it in.
                return null;
            }
            $value = (int) $datum->get_value();

            return $value > 0 ? $value : null;
        }

        return null;
    }

    /**
     * What the course costs, already formatted, or '' when there is no price to print.
     *
     * The discounted amount when an offer is running, because that is the price the
     * catalogue and the checkout would both quote - a card is not the place to
     * disagree with the till.
     *
     * @param int $courseid
     * @return string
     */
    protected static function price_label(int $courseid): string {
        try {
            if (!pricing::available()) {
                return '';
            }

            $info = pricing::info($courseid);
            if (empty($info['haspricing'])) {
                return '';
            }

            $amount = pricing::effective_price($info);

            return $amount > 0 ? pricing::money($amount, (string) $info['currency']) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * The course the hero's "Continue learning" button should open (AC-4.7.3).
     *
     * The most recently accessed active enrolment, which is what My courses puts
     * first - so the hero and the first card agree, which is what a learner
     * expects when both say "carry on".
     *
     * @return array{fullname: string, url: string}|null null for a guest, or a
     *         learner holding no active enrolment
     */
    public static function continue_learning(): ?array {
        $courses = self::my_courses(1);
        if (!$courses) {
            return null;
        }

        $first = $courses[0];

        return [
            'id' => $first['id'],
            'fullname' => $first['fullname'],
            'url' => $first['resumeurl'],
        ];
    }

    /**
     * The name of a teacher on the course, for the card's byline.
     *
     * One name, not a list: the card has room for one, and a card reading
     * "Dr Ahmed, Dr Sara, Dr Omar and 4 others" tells the learner nothing they
     * wanted to know at a glance.
     *
     * @param \context_course $context
     * @return string the teacher's name, or ''
     */
    protected static function teacher_name(\context_course $context): string {
        global $CFG;

        // $CFG->coursecontact is the site's own answer to "whose name goes on a
        // course?" - it is what the course listing and the catalogue already use,
        // so the home page naming somebody different would be a bug the moment an
        // administrator changed it.
        $roleids = array_filter(array_map('intval', explode(',', (string) ($CFG->coursecontact ?? ''))));
        if (!$roleids) {
            return '';
        }

        try {
            // Asking for several roles at once means the rows are role assignments,
            // not users, so `ra.id` has to lead the field list - get_role_users()
            // checks for exactly that and complains otherwise, because without it
            // two roles held by one person would collide on the same array key.
            $names = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

            // Parent contexts included: a teacher is often assigned at category
            // level rather than on each course.
            $users = get_role_users($roleids, $context, true, 'ra.id, u.id AS userid, ' . $names);
        } catch (\Throwable $e) {
            return '';
        }

        $first = reset($users);

        return $first ? fullname($first) : '';
    }

    /**
     * The course's own image, if it has one.
     *
     * @param \stdClass $course
     * @param \context_course $context
     * @return string a URL, or ''
     */
    protected static function course_image(\stdClass $course, \context_course $context): string {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false);

        foreach ($files as $file) {
            if (!$file->is_valid_image()) {
                continue;
            }

            return \moodle_url::make_pluginfile_url(
                $context->id, 'course', 'overviewfiles', null,
                $file->get_filepath(), $file->get_filename()
            )->out(false);
        }

        return '';
    }
}
