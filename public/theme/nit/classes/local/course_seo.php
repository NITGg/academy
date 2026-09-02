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

namespace theme_nit\local;

use context_course;
use moodle_url;

/**
 * schema.org structured data for the course details page (AC-4.9.8).
 *
 * The Site home already emits a schema.org ItemList of Course (see
 * theme/nit/layout/frontpage.php); this is the per-course counterpart, so a
 * crawler that follows a catalogue link - or arrives from the sitemap at
 * /theme/nit/sitemap.php - finds the course's own name, description and price
 * in machine-readable form rather than having to guess them from the page.
 *
 * Why a hook and not a layout file
 * --------------------------------
 * /course/view.php renders through theme_nit's parent (boost) layouts; the
 * theme overrides `frontpage`, `login` and `nit_fullwidth` only. There is no
 * course layout of ours to hang this on, and copying boost's just to add a
 * <script> tag would be a large override to maintain for one line. The
 * `before_standard_head_html_generation` hook injects into <head> for this one
 * request without owning any markup.
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_seo {

    /** @var int Longest description we will publish, in characters. */
    protected const DESCRIPTION_MAX = 500;

    /**
     * Emit a schema.org/Course JSON-LD block in the <head> of a course page.
     *
     * Only fires on /course/view.php for a real, visible course. Everything
     * else - the site "course", hidden courses, the app's chrome-free WebView
     * layout, print/maintenance pages - is left exactly as it was.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     * @return void
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        global $PAGE;

        if (during_initial_install() || empty($PAGE)) {
            return;
        }

        // The course details page and nothing else. The suffix is the course
        // format (course-view-topics, course-view-nittopics, ...), so match the
        // stem rather than listing formats we would have to keep in step.
        if (strpos((string) $PAGE->pagetype, 'course-view-') !== 0) {
            return;
        }

        // Layouts no crawler will ever see, and that must stay byte-clean: the
        // app WebView is switched to `embedded` by hook_callbacks, and a print
        // stylesheet has no use for structured data.
        $skiplayouts = ['embedded', 'maintenance', 'redirect', 'print', 'popup'];
        if (in_array($PAGE->pagelayout, $skiplayouts, true)) {
            return;
        }

        $course = $PAGE->course ?? null;
        if (empty($course->id) || (int) $course->id == SITEID) {
            return;
        }

        // A hidden course is not part of the catalogue. Publishing its name and
        // price to a search engine would advertise something a visitor cannot
        // reach - the teacher previewing it still sees the page, just without
        // the markup.
        if (empty($course->visible)) {
            return;
        }

        $json = self::build_jsonld($course);
        if ($json === '') {
            return;
        }

        $hook->add_html('<script type="application/ld+json">' . $json . '</script>');
    }

    /**
     * Build the JSON-LD document for one course.
     *
     * @param \stdClass $course a full course record (as carried by $PAGE->course)
     * @return string encoded JSON, or '' when there is nothing worth emitting
     */
    public static function build_jsonld(\stdClass $course): string {
        $courseid = (int) $course->id;
        $context = context_course::instance($courseid);
        $url = (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false);

        // JSON-LD values are plain text, so undo the display escaping that
        // format_string() applies; json_encode() re-escapes for JSON. Slashes
        // stay escaped (encoder default), so a "</script>" inside a course name
        // cannot break out of the <script> element we embed this in.
        $name = trim(html_entity_decode(
            format_string($course->fullname, true, ['context' => $context, 'escape' => false]),
            ENT_QUOTES,
            'UTF-8'
        ));
        if ($name === '') {
            return '';
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type'    => 'Course',
            '@id'      => $url,
            'name'     => $name,
            'url'      => $url,
        ];

        $description = self::description($course, $context);
        if ($description !== '') {
            $data['description'] = $description;
        }

        $image = self::image($context);
        if ($image !== '') {
            $data['image'] = $image;
        }

        $data['inLanguage'] = str_replace('_', '-', current_language());
        $data['provider'] = self::provider();

        $offer = self::offer($courseid, $url);
        if ($offer !== null) {
            $data['offers'] = $offer;
        }

        $data['hasCourseInstance'] = self::course_instance($course);

        return (string) json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * The course summary as plain text.
     *
     * format_text() is what applies the multilang filter, so a summary written
     * with {mlang} tags arrives here already reduced to the current language -
     * which is why the raw $course->summary is never used directly.
     *
     * @param \stdClass $course
     * @param \context $context
     * @return string
     */
    protected static function description(\stdClass $course, \context $context): string {
        if (empty($course->summary)) {
            return '';
        }

        $html = format_text(
            $course->summary,
            $course->summaryformat ?? FORMAT_HTML,
            ['context' => $context, 'noclean' => true]
        );

        $plain = trim(html_to_text($html, 0, false));
        if ($plain === '') {
            return '';
        }

        return shorten_text($plain, self::DESCRIPTION_MAX);
    }

    /**
     * The course overview image, as an absolute URL.
     *
     * Only a real uploaded image counts. theme_nit_get_courses() falls back to
     * get_generated_image_for_id() for the catalogue grid, but that is a coloured
     * placeholder pattern - offering it to a search engine as "the image of this
     * course" would put a meaningless swatch in the rich result.
     *
     * @param \context $context the course context
     * @return string absolute URL, or '' when the course has no overview file
     */
    protected static function image(\context $context): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'filename', false);
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                return moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    null,
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
            }
        }

        return '';
    }

    /**
     * The academy itself, as the course provider.
     *
     * @return array
     */
    protected static function provider(): array {
        global $SITE;

        $sitename = html_entity_decode(
            format_string($SITE->fullname, true,
                ['context' => context_course::instance(SITEID), 'escape' => false]),
            ENT_QUOTES,
            'UTF-8'
        );

        return [
            '@type' => 'Organization',
            'name'  => $sitename,
            'url'   => (new moodle_url('/'))->out(false),
        ];
    }

    /**
     * The schema.org Offer for a course: its price and currency.
     *
     * Prices are per country (see local_payments\price_resolver), so the amount
     * published here is the one this visitor is actually quoted. A crawler is a
     * guest with no resolvable country, which lands on the course's Default
     * price row - the catalogue price - so that is what search engines index.
     *
     * The two failure modes get different answers on purpose:
     *   - the course has NO pricing rules at all -> genuinely free, price 0;
     *   - the course HAS rules but none resolve for this viewer (signed in with
     *     no profile country, or a country with no row and no default) -> fall
     *     back to any active row rather than advertise a paid course as free.
     *
     * @param int $courseid
     * @param string $courseurl
     * @return array|null the Offer, or null when no amount can be established
     */
    protected static function offer(int $courseid, string $courseurl): ?array {
        $price = self::price($courseid);
        if ($price === null) {
            return null;
        }

        [$amount, $currency] = $price;
        if ($currency === '') {
            return null;
        }

        return [
            '@type'         => 'Offer',
            'price'         => number_format($amount, 2, '.', ''),
            'priceCurrency' => $currency,
            'category'      => $amount > 0 ? 'Paid' : 'Free',
            'availability'  => 'https://schema.org/InStock',
            'url'           => $amount > 0
                ? (new moodle_url('/local/payments/buy.php', ['courseid' => $courseid]))->out(false)
                : $courseurl,
        ];
    }

    /**
     * Resolve a course's numeric price and currency.
     *
     * theme_nit_course_price() answers the same question but returns a formatted
     * display string ("199.00 EGP", or the "set your country" label), which is
     * not something JSON-LD can carry - schema.org wants the number and the ISO
     * code in separate properties.
     *
     * @param int $courseid
     * @return array{0: float, 1: string}|null [amount, ISO 4217 code], or null when unknown
     */
    protected static function price(int $courseid): ?array {
        global $DB, $USER;

        $haspaymentsplugin = class_exists('\local_payments\price_resolver');

        if ($haspaymentsplugin && \local_payments\price_resolver::has_pricing($courseid)) {
            try {
                $pricing = \local_payments\price_resolver::resolve(
                    $courseid,
                    !empty($USER->id) ? (int) $USER->id : null
                );
                return [(float) $pricing->price, strtoupper((string) $pricing->currency)];
            } catch (\Throwable $e) {
                // country_required_exception (signed in, no profile country) or no
                // matching rule. The course is priced either way, so read the
                // catalogue price straight off the default row - that is also the
                // figure a crawler would have been given.
                $row = $DB->get_record_select(
                    'local_payments_course_prices',
                    'courseid = :courseid AND is_default = 1 AND is_active = 1',
                    ['courseid' => $courseid],
                    'price, currency',
                    IGNORE_MULTIPLE
                ) ?: $DB->get_record_select(
                    'local_payments_course_prices',
                    'courseid = :courseid AND is_active = 1',
                    ['courseid' => $courseid],
                    'price, currency',
                    IGNORE_MULTIPLE
                );

                if ($row) {
                    return [(float) $row->price, strtoupper((string) $row->currency)];
                }

                // Priced, but no amount can be named. Better to publish no offer
                // than a wrong one.
                return null;
            }
        }

        // Sites (or courses) still on a core paid enrolment method.
        $enrolments = $DB->get_records_select(
            'enrol',
            "courseid = :courseid AND status = 0 AND enrol IN ('fee', 'paypal')",
            ['courseid' => $courseid],
            'sortorder ASC',
            'id, cost, currency'
        );
        foreach ($enrolments as $enrolment) {
            if ((float) $enrolment->cost > 0) {
                return [(float) $enrolment->cost, strtoupper((string) $enrolment->currency)];
            }
        }

        // No pricing anywhere: the course is free. It still needs a currency to
        // be a valid Offer, so borrow the site's.
        $currency = $haspaymentsplugin ? \local_payments\price_resolver::default_currency() : '';
        if ($currency === '') {
            $currency = strtoupper(trim((string) get_config('local_payments', 'default_currency')));
        }

        return $currency !== '' ? [0.0, $currency] : null;
    }

    /**
     * The offering of the course, as schema.org models it.
     *
     * Google's "Course info" rich result wants a CourseInstance alongside the
     * Course. Ours is online by definition; start/end dates are published only
     * when the course actually carries them, so a rolling-enrolment course is
     * not given an enrolment window it does not have.
     *
     * @param \stdClass $course
     * @return array
     */
    protected static function course_instance(\stdClass $course): array {
        $instance = [
            '@type'      => 'CourseInstance',
            'courseMode' => 'online',
        ];

        if (!empty($course->startdate)) {
            $instance['startDate'] = date('Y-m-d', (int) $course->startdate);
        }
        if (!empty($course->enddate)) {
            $instance['endDate'] = date('Y-m-d', (int) $course->enddate);
        }

        return $instance;
    }
}
