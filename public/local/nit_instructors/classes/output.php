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

namespace local_nit_instructors;

use html_writer;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Rendering for the Academic and Professional Background group.
 *
 * One implementation, three places it appears: the public instructor page, the
 * instructor's own profile, and the preview an administrator reads before
 * approving. They must show the same thing or the approval means nothing - an
 * administrator has to be looking at what learners will get.
 *
 * @package    local_nit_instructors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class output {

    /**
     * The whole group, as HTML.
     *
     * Every part is optional (AC-4.5.11), so each section is omitted when it is
     * empty rather than rendered as a heading over nothing. A profile with nothing
     * filled in produces an empty string, and the caller decides whether that is
     * worth a heading of its own.
     *
     * @param stdClass|null $version the profile version to show, or null
     * @param int $userid the instructor, for the derived Courses Taught list
     * @param bool $showcourses whether to include Courses Taught
     * @return string HTML, or '' when there is nothing to show
     */
    public static function group(?stdClass $version, int $userid, bool $showcourses = true): string {
        $parts = [];

        if ($version) {
            $specialty = profile::pick($version->specialtyen, $version->specialtyar);
            if ($specialty !== '') {
                $parts[] = self::field(get_string('specialty', 'local_nit_instructors'), s($specialty));
            }

            if ((int) $version->years > 0) {
                $parts[] = self::field(
                    get_string('years', 'local_nit_instructors'),
                    get_string('yearsvalue', 'local_nit_instructors', (int) $version->years)
                );
            }

            $entries = profile::entries((int) $version->id);
            foreach (profile::entry_types() as $type) {
                $list = self::entry_list($entries[$type] ?? []);
                if ($list !== '') {
                    $parts[] = self::field(
                        get_string('type_' . $type, 'local_nit_instructors'), $list);
                }
            }
        }

        if ($showcourses) {
            $courses = profile::courses_taught($userid);
            if ($courses) {
                $links = array_map(
                    static fn(array $c): string => html_writer::tag('li',
                        html_writer::link($c['url'], s($c['fullname']))),
                    $courses
                );
                $parts[] = self::field(
                    get_string('coursestaught', 'local_nit_instructors'),
                    html_writer::tag('ul', implode('', $links), ['class' => 'mb-0 ps-3'])
                );
            }
        }

        if (!$parts) {
            return '';
        }

        return html_writer::div(implode('', $parts), 'nit-instructor-background');
    }

    /**
     * One labelled row of the group.
     *
     * @param string $label
     * @param string $value already-escaped HTML
     * @return string
     */
    protected static function field(string $label, string $value): string {
        return html_writer::div(
            html_writer::div(s($label), 'fw-semibold small text-muted') .
            html_writer::div($value, 'nit-instructor-value'),
            'mb-3'
        );
    }

    /**
     * A list of repeating entries, each as "title - organisation (period)".
     *
     * Every part of an entry is optional in practice, so the separators are built
     * from what is actually present: an entry with only a title reads as a title,
     * not as a title followed by a dash and an empty bracket.
     *
     * @param stdClass[] $entries
     * @return string HTML, or '' when there is nothing to list
     */
    protected static function entry_list(array $entries): string {
        $items = [];

        foreach ($entries as $entry) {
            $title = profile::pick($entry->titleen, $entry->titlear);
            $org = profile::pick($entry->orgen, $entry->orgar);
            $period = profile::pick($entry->perioden, $entry->periodar);

            $line = [];
            if ($title !== '') {
                $line[] = html_writer::span(s($title), 'fw-semibold');
            }
            if ($org !== '') {
                $line[] = html_writer::span(s($org), 'text-muted');
            }
            if ($period !== '') {
                $line[] = html_writer::span(s($period), 'text-muted small');
            }

            if ($line) {
                $items[] = html_writer::tag('li', implode(' &middot; ', $line), ['class' => 'mb-1']);
            }
        }

        return $items ? html_writer::tag('ul', implode('', $items), ['class' => 'list-unstyled mb-0']) : '';
    }

    /**
     * The banner an instructor sees about the state of their last change.
     *
     * Two of the specification's sentences live here: AC-4.5.14's "sent for review"
     * and AC-4.5.15's rejection with its reason. Shown on the instructor's own
     * pages only - a learner has no business knowing that a change is in flight.
     *
     * @param int $userid the instructor
     * @return string HTML, or '' when there is nothing to say
     */
    public static function status_banner(int $userid): string {
        $out = '';

        if (profile::pending($userid)) {
            $out .= html_writer::div(
                get_string('pendingnotice', 'local_nit_instructors'), 'alert alert-info');
        }

        $rejected = profile::rejected($userid);
        if ($rejected) {
            $reason = trim((string) $rejected->decisionnote);
            $out .= html_writer::div(
                get_string('rejectednotice', 'local_nit_instructors',
                    $reason !== '' ? s($reason) : get_string('noreasongiven', 'local_nit_instructors')),
                'alert alert-warning');
        }

        return $out;
    }
}
