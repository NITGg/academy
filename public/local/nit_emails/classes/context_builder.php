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
 * Turns a real purchase / registration into the {placeholder} => HTML values
 * a template is rendered with.
 *
 * The course email reproduces the academy's "Course File Summary", so the
 * values come from exactly the same places the course landing page reads
 * (theme_nit\output\format_topics_renderer): the course record, the section /
 * activity tree, the enrolled teachers, and the course custom fields keyed by
 * shortname — total_number_of_hours, target_audience, prerequisites, ilos and
 * by_the_end_of_training. A field holding several items separated by "|" (or a
 * newline / bullet) becomes a list, the same convention the page uses.
 *
 * Every value returned here is ready-to-insert HTML: plain values are escaped,
 * list values are <ul> markup. A value that has no data resolves to an em dash
 * rather than leaving a raw {placeholder} in the email.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * Placeholder value builder.
 */
class context_builder {

    /** @var string shown where a course/plan simply has no value for a field. */
    const EMPTY_VALUE = '—';

    /**
     * The values every email shares: who it is going to and where the site is.
     *
     * @param \stdClass $user recipient
     * @return array<string, string>
     */
    public static function common(\stdClass $user): array {
        global $CFG;

        $site = get_site();
        $admin = get_admin();

        return [
            'firstname'    => s($user->firstname ?? ''),
            'lastname'     => s($user->lastname ?? ''),
            'fullname'     => s(fullname($user)),
            'username'     => s($user->username ?? ''),
            'email'        => s($user->email ?? ''),
            'sitename'     => format_string($site->fullname),
            'siteurl'      => s($CFG->wwwroot),
            'loginurl'     => s($CFG->wwwroot . '/login/index.php'),
            'dashboardurl' => s($CFG->wwwroot . '/my/'),
            'date'         => userdate(time(), get_string('strftimedaydate')),
            'supportemail' => s($CFG->supportemail ?: ($admin ? $admin->email : '')),
        ];
    }

    /**
     * Values for the "bought a course" email — the course file summary.
     *
     * @param \stdClass $user recipient
     * @param \stdClass $course course record
     * @param \stdClass|null $order optional order info (amount, currency, order_id)
     * @return array<string, string>
     */
    public static function course(\stdClass $user, \stdClass $course, ?\stdClass $order = null): array {
        global $CFG;

        $context = \context_course::instance($course->id);
        $cf = self::course_customfields($course->id);

        $values = self::common($user) + [
            'coursename'      => format_string($course->fullname, true, ['context' => $context]),
            'courseurl'       => s($CFG->wwwroot . '/course/view.php?id=' . $course->id),
            'coursesummary'   => self::text_or_empty(
                strip_tags(format_text($course->summary ?? '', $course->summaryformat ?? FORMAT_HTML,
                    ['context' => $context, 'noclean' => false]))),
            'coursestartdate' => $course->startdate
                ? userdate($course->startdate, get_string('strftimedaydate'))
                : self::EMPTY_VALUE,
            'totalhours'      => self::hours_value($cf),
            'instructors'     => self::instructors($context),
            'targetaudience'  => self::list_field($cf, ['target_audience']),
            'prerequisites'   => self::list_field($cf, ['prerequisites']),
            'ilos'            => self::list_field($cf, ['ilos', 'by_the_end_of_training']),
            'coursecontent'   => self::course_structure($course),
        ];

        return $values + self::order($order);
    }

    /**
     * Values for the "subscribed to a plan" email.
     *
     * @param \stdClass $user recipient
     * @param \stdClass $subscription nit_subscription record
     * @param \stdClass $purchase nit_sub_purchase record
     * @return array<string, string>
     */
    public static function subscription(\stdClass $user, \stdClass $subscription, \stdClass $purchase): array {
        global $CFG;

        $days = (int) ($purchase->duration_days ?: $subscription->duration_days);
        $seats = (int) ($purchase->seats ?? 0);
        $isb2b = (($purchase->type ?? 'normal') === 'b2b');

        // The subscription's currency is per-country (resolved from the buyer's profile country),
        // and nit_sub_purchase does not store it — resolve it the same way checkout did, so the
        // email shows the currency the student was actually charged in (not the site default).
        $currency = get_config('local_payments', 'default_currency') ?: 'EGP';
        if (class_exists('\local_nit_subscriptions\subscription_manager')) {
            try {
                $resolved = \local_nit_subscriptions\subscription_manager::resolve_price(
                    (int) $subscription->id, (int) $purchase->userid);
                if (!empty($resolved->currency)) {
                    $currency = $resolved->currency;
                }
            } catch (\Throwable $e) {
                // Fall back to the default currency on any resolver error.
            }
        }
        $order = (object) [
            'amount'   => $purchase->price_paid ?? 0,
            'currency' => $currency,
            'order_id' => $purchase->reference ?? '',
        ];

        return self::common($user) + [
            'subscriptionname'        => self::multilang($subscription->name ?? ''),
            'subscriptiondescription' => self::text_or_empty(
                strip_tags(self::multilang($subscription->description ?? ''))),
            'subscriptiontype'        => get_string($isb2b ? 'subtype_b2b' : 'subtype_normal', 'local_nit_emails'),
            'durationdays'            => $days > 0
                ? self::count_string($days, 'nday', 'ndays')
                : self::EMPTY_VALUE,
            'startdate'               => userdate((int) ($purchase->timeactivated ?: time()),
                get_string('strftimedaydate')),
            'expirydate'              => !empty($purchase->expires_at)
                ? userdate((int) $purchase->expires_at, get_string('strftimedaydate'))
                : self::EMPTY_VALUE,
            'seats'                   => $seats > 0 ? (string) $seats : self::EMPTY_VALUE,
            'coursesurl'              => s($CFG->wwwroot . '/course/index.php'),
            'mysubscriptionsurl'      => s($CFG->wwwroot . '/local/payments/history.php'),
        ] + self::order($order);
    }

    /**
     * Values for the "registration succeeded" email.
     *
     * @param \stdClass $user recipient
     * @return array<string, string>
     */
    public static function registration(\stdClass $user): array {
        global $CFG;

        return self::common($user) + [
            'profileurl'       => s($CFG->wwwroot . '/user/edit.php'),
            'browsecoursesurl' => s($CFG->wwwroot . '/course/index.php'),
        ];
    }

    /**
     * Sample values so an admin can preview / test a template without a real
     * purchase behind it.
     *
     * @param string $event
     * @param \stdClass $user the admin previewing
     * @return array<string, string>
     */
    public static function sample(string $event, \stdClass $user): array {
        $values = self::common($user);

        if ($event === templates::EVENT_COURSE) {
            $values += [
                'coursename'      => get_string('sample_coursename', 'local_nit_emails'),
                'courseurl'       => $values['siteurl'] . '/course/view.php?id=2',
                'coursesummary'   => get_string('sample_coursesummary', 'local_nit_emails'),
                'coursestartdate' => userdate(time(), get_string('strftimedaydate')),
                'totalhours'      => get_string('nhours', 'local_nit_emails', 24),
                'instructors'     => get_string('sample_instructor', 'local_nit_emails'),
                'targetaudience'  => self::bullets([
                    get_string('sample_audience1', 'local_nit_emails'),
                    get_string('sample_audience2', 'local_nit_emails'),
                ]),
                'prerequisites'   => self::bullets([get_string('sample_prereq', 'local_nit_emails')]),
                'ilos'            => self::bullets([
                    get_string('sample_ilo1', 'local_nit_emails'),
                    get_string('sample_ilo2', 'local_nit_emails'),
                ]),
                'coursecontent'   => self::bullets([
                    get_string('sample_module1', 'local_nit_emails'),
                    get_string('sample_module2', 'local_nit_emails'),
                ]),
            ] + self::order((object) ['amount' => 1500, 'currency' => 'EGP', 'order_id' => 'PAY-2026-00012345']);
        } else if ($event === templates::EVENT_SUBSCRIPTION) {
            $values += [
                'subscriptionname'        => get_string('sample_planname', 'local_nit_emails'),
                'subscriptiondescription' => get_string('sample_plandesc', 'local_nit_emails'),
                'subscriptiontype'        => get_string('subtype_normal', 'local_nit_emails'),
                'durationdays'            => get_string('ndays', 'local_nit_emails', 365),
                'startdate'               => userdate(time(), get_string('strftimedaydate')),
                'expirydate'              => userdate(time() + (365 * DAYSECS), get_string('strftimedaydate')),
                'seats'                   => self::EMPTY_VALUE,
                'coursesurl'              => $values['siteurl'] . '/course/index.php',
                'mysubscriptionsurl'      => $values['siteurl'] . '/local/payments/history.php',
            ] + self::order((object) ['amount' => 4900, 'currency' => 'EGP', 'order_id' => 'PAY-2026-00067890']);
        } else {
            $values += self::registration($user);
        }

        return $values;
    }

    // =========================================================================
    // Pieces
    // =========================================================================

    /**
     * Order placeholders, blanked out when the caller has no order to show.
     *
     * @param \stdClass|null $order
     * @return array<string, string>
     */
    private static function order(?\stdClass $order): array {
        if (!$order) {
            return ['amount' => self::EMPTY_VALUE, 'currency' => '', 'orderid' => self::EMPTY_VALUE];
        }
        return [
            'amount'   => s(format_float((float) ($order->amount ?? 0), 2)),
            'currency' => s(strtoupper((string) ($order->currency ?? ''))),
            'orderid'  => self::text_or_empty((string) ($order->order_id ?? '')),
        ];
    }

    /**
     * Course custom fields keyed by shortname, values raw (pre-multilang).
     *
     * @param int $courseid
     * @return array<string, string>
     */
    private static function course_customfields(int $courseid): array {
        $out = [];
        try {
            $handler = \core_course\customfield\course_handler::create();
            foreach ($handler->get_instance_data($courseid, true) as $data) {
                $out[$data->get_field()->get('shortname')] = (string) $data->get_value();
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    /**
     * "Total number of hours" as a display string.
     *
     * @param array<string, string> $cf
     * @return string
     */
    private static function hours_value(array $cf): string {
        $raw = trim((string) ($cf['total_number_of_hours'] ?? ''));
        if ($raw === '' || (is_numeric($raw) && (float) $raw == 0.0)) {
            return self::EMPTY_VALUE;
        }
        if (is_numeric($raw)) {
            $f = (float) $raw;
            $raw = ($f == (int) $f) ? (string) (int) $f : rtrim(rtrim((string) $f, '0'), '.');
            return self::count_string($raw, 'nhour', 'nhours');
        }
        return s(self::multilang($raw));
    }

    /**
     * The first of $shortnames that holds anything, rendered as a bullet list.
     *
     * @param array<string, string> $cf
     * @param string[] $shortnames candidate custom-field shortnames
     * @return string
     */
    private static function list_field(array $cf, array $shortnames): string {
        foreach ($shortnames as $name) {
            $text = self::multilang((string) ($cf[$name] ?? ''));
            $text = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $text)));
            if ($text === '') {
                continue;
            }
            $items = preg_split('/[|\n•]+/u', $text);
            $items = array_values(array_filter(array_map('trim', $items), function ($item) {
                return $item !== '';
            }));
            if ($items) {
                return count($items) === 1 ? s($items[0]) : self::bullets($items);
            }
        }
        return self::EMPTY_VALUE;
    }

    /**
     * Enrolled teachers as a comma-separated list of names.
     *
     * @param \context_course $context
     * @return string
     */
    private static function instructors(\context_course $context): string {
        $roles = get_archetype_roles('editingteacher') + get_archetype_roles('teacher');
        if (empty($roles)) {
            return self::EMPTY_VALUE;
        }
        $fields = 'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                   u.middlename, u.alternatename';
        $names = [];
        foreach (get_role_users(array_keys($roles), $context, false, $fields) as $teacher) {
            $names[$teacher->id] = fullname($teacher);
        }
        return $names ? s(implode(get_string('listsep', 'langconfig') . ' ', $names)) : self::EMPTY_VALUE;
    }

    /**
     * "Course content & program structure": the visible sections, each with the
     * number of activities it holds.
     *
     * @param \stdClass $course
     * @return string
     */
    private static function course_structure(\stdClass $course): string {
        try {
            $modinfo = get_fast_modinfo($course);
            $format = course_get_format($course);
        } catch (\Throwable $e) {
            return self::EMPTY_VALUE;
        }

        $items = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->uservisible && !$section->visible) {
                continue;
            }
            $count = 0;
            foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
                $cm = $modinfo->cms[$cmid] ?? null;
                if ($cm && $cm->visible && $cm->has_view()) {
                    $count++;
                }
            }
            if ($count === 0) {
                continue;
            }
            $name = trim(strip_tags($format->get_section_name($section)));
            if ($name === '') {
                continue;
            }
            $items[] = s($name) . ' <span style="color:#5b6b7f;">('
                . s(self::count_string($count, 'nactivity', 'nactivities')) . ')</span>';
            if (count($items) >= 25) {
                break;
            }
        }

        return $items ? self::bullets($items, false) : self::EMPTY_VALUE;
    }

    // =========================================================================
    // Formatting helpers
    // =========================================================================

    /**
     * A count rendered with the right singular / plural wording.
     *
     * @param int|string $count
     * @param string $onekey singular string key
     * @param string $manykey plural string key
     * @return string
     */
    private static function count_string($count, string $onekey, string $manykey): string {
        return get_string(((float) $count === 1.0) ? $onekey : $manykey, 'local_nit_emails', $count);
    }

    /**
     * A <ul> of items.
     *
     * @param string[] $items
     * @param bool $escape escape each item (false when it is already markup)
     * @return string
     */
    public static function bullets(array $items, bool $escape = true): string {
        $lis = '';
        foreach ($items as $item) {
            $lis .= '<li style="margin:0 0 6px;">' . ($escape ? s($item) : $item) . '</li>';
        }
        return '<ul style="margin:0 0 20px;padding-inline-start:22px;">' . $lis . '</ul>';
    }

    /**
     * Escape a value, or fall back to the em dash when it is empty.
     *
     * @param string $text
     * @return string
     */
    private static function text_or_empty(string $text): string {
        $text = trim($text);
        return $text === '' ? self::EMPTY_VALUE : s($text);
    }

    /**
     * Resolve a bilingual "{mlang xx}…{mlang}" value for the current language.
     *
     * The mailer forces the recipient's language around rendering, so "current"
     * here is already the language the email is being written in. Mirrors
     * filter_multilang2 selection: the current language wins, then "other",
     * then the first block, so a value is never lost.
     *
     * @param string $raw
     * @return string
     */
    public static function multilang(string $raw): string {
        $raw = trim($raw);
        if ($raw === '' || stripos($raw, '{mlang') === false) {
            return $raw;
        }

        $pattern = '/\{mlang\s+([^}]+)\}(.*?)\{mlang\}/is';
        if (!preg_match_all($pattern, $raw, $matches, PREG_SET_ORDER)) {
            return $raw;
        }

        $current = strtolower(current_language());
        $matched = '';
        $other = '';
        $first = null;

        foreach ($matches as $block) {
            $langs = array_map('trim', explode(',', strtolower($block[1])));
            $content = $block[2];
            if ($first === null) {
                $first = $content;
            }
            if ($matched === '' && (in_array($current, $langs, true)
                    || in_array(substr($current, 0, 2), $langs, true))) {
                $matched = $content;
            }
            if ($other === '' && in_array('other', $langs, true)) {
                $other = $content;
            }
        }

        if ($matched !== '') {
            return trim($matched);
        }
        if ($other !== '') {
            return trim($other);
        }
        return trim((string) $first);
    }
}
