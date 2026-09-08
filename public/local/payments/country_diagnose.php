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
 * Why did this visitor get THAT price?
 *
 * Every price on this site is chosen by country, and a country is worked out
 * from either a profile field or an IP address. When the answer is wrong the
 * symptom is always the same and always misleading: a buyer in Egypt is quoted
 * the Default row in dollars, and the course's Egyptian price — which exists, is
 * active, and is correct — looks like it is being ignored.
 *
 * It is not being ignored. One of four things happened, and only one of them is
 * about pricing at all:
 *
 *   1. No geolocation is configured, so no IP resolves to any country.
 *   2. Geolocation is configured, but the site is behind a reverse proxy and
 *      $CFG->getremoteaddrconf is unset — so getremoteaddr() returns the PROXY's
 *      private address for every visitor on earth and none of them can be placed.
 *      This is the one that looks least like itself: geolocation is "on", the
 *      database is present, and every lookup still fails.
 *   3. The address resolves to a country the course has no active row for, so
 *      the Default row is correctly used.
 *   4. The buyer is signed in, and a signed-in account is priced on its PROFILE
 *      country and nothing else — their IP is deliberately not consulted.
 *
 * This page separates those four, for the current request and for any address an
 * administrator types in. It is read-only: it looks things up and prints them,
 * and changes nothing.
 *
 * A CLI script cannot answer 1 and 2 — there is no request, so there are no
 * proxy headers and no remote address to read — which is why this is a web page
 * and not a cli/ script like the plugin's other diagnostics.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = optional_param('courseid', 0, PARAM_INT);
$testip = trim(optional_param('ip', '', PARAM_RAW_TRIMMED));

$url = new moodle_url('/local/payments/country_diagnose.php');
if ($courseid) {
    $url->param('courseid', $courseid);
}

// The pricing capability is defined at course level (db/access.php), so it is
// what gates the per-course view. Opened without a course this is a site-wide
// health check of the geolocation setup, and that is an administrator's screen.
if ($courseid) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    require_login($course);
    $context = context_course::instance($courseid);
    require_capability('local/payments:managecoursepricing', $context);
    $PAGE->set_heading($course->fullname);
} else {
    require_login();
    $context = context_system::instance();
    require_capability('moodle/site:config', $context);
    $PAGE->set_heading($SITE->fullname);
}

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout($courseid ? 'incourse' : 'admin');
$PAGE->set_title(get_string('geodiag_title', 'local_payments'));

$yes = get_string('yes');
$no = get_string('no');
$countries = get_string_manager()->get_list_of_countries();

/**
 * One label/value row of a diagnostic table.
 *
 * @param string $label
 * @param string $value already-escaped or plain text
 * @param string|null $verdict 'ok' | 'bad' | null (no marker)
 * @return array
 */
$row = function (string $label, string $value, ?string $verdict = null): array {
    $mark = '';
    if ($verdict === 'ok') {
        $mark = html_writer::tag('span', '&#10004;', ['class' => 'text-success fw-bold me-2']);
    } else if ($verdict === 'bad') {
        $mark = html_writer::tag('span', '&#10008;', ['class' => 'text-danger fw-bold me-2']);
    }
    return [$label, $mark . $value];
};

$table = function (array $rows) {
    $t = new html_table();
    $t->attributes['class'] = 'generaltable';
    $t->head = null;
    $t->data = $rows;
    return html_writer::table($t);
};

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('geodiag_title', 'local_payments'));
echo html_writer::tag('p', get_string('geodiag_intro', 'local_payments'), ['class' => 'text-muted']);

// ── 1. Is a lookup even possible? ───────────────────────────────────────────
$hasgeoip2 = !empty($CFG->geoip2file) && file_exists($CFG->geoip2file);
$hasgeoplugin = !empty($CFG->geopluginapikey);
$available = \local_payments\country_detector::geolocation_available();

$rows = [];
$rows[] = $row(get_string('geodiag_geoip2file', 'local_payments'),
    empty($CFG->geoip2file) ? get_string('geodiag_notset', 'local_payments') : s($CFG->geoip2file),
    empty($CFG->geoip2file) ? null : ($hasgeoip2 ? 'ok' : 'bad'));
if (!empty($CFG->geoip2file) && !$hasgeoip2) {
    $rows[] = $row(get_string('geodiag_geoip2missing', 'local_payments'),
        get_string('geodiag_geoip2missing_desc', 'local_payments'), 'bad');
}
$rows[] = $row(get_string('geodiag_geoip2lib', 'local_payments'),
    class_exists('\GeoIp2\Database\Reader') ? $yes : $no,
    class_exists('\GeoIp2\Database\Reader') ? 'ok' : null);
$rows[] = $row(get_string('geodiag_geoplugin', 'local_payments'),
    $hasgeoplugin ? $yes : $no, $hasgeoplugin ? 'ok' : null);

// The second rung. profilefield_phone carries a free, no-setup online lookup, and it
// is what the sign-up country check has always used — which is why registration could
// place a visitor on a site where the shop could not. The shop now uses the same
// ladder, so this row is part of the verdict rather than a footnote to it.
$hasonline = class_exists('\profilefield_phone\dialcodes');
$rows[] = $row(get_string('geodiag_online', 'local_payments'),
    $hasonline ? $yes : $no, $hasonline ? 'ok' : null);

$rows[] = $row(get_string('geodiag_available', 'local_payments'),
    $available ? $yes : $no, $available ? 'ok' : 'bad');

echo $OUTPUT->heading(get_string('geodiag_h_config', 'local_payments'), 3);
echo $table($rows);

if (!$available) {
    echo $OUTPUT->notification(get_string('geodiag_nogeo', 'local_payments'), 'error');
} else if (!\local_payments\country_detector::local_geolocation_available()) {
    // Working, but on the slow rung: every uncached address costs an external call.
    echo $OUTPUT->notification(get_string('geodiag_onlineonly', 'local_payments'), 'info');
}

// ── 2. Whose address does the server actually see? ──────────────────────────
// This is the half a CLI script cannot answer, and the half that most often
// turns out to be the real fault.
$seen = getremoteaddr();
$ispublic = \local_payments\country_detector::is_public_ip($seen);
$rawremote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
$clientip = (string) ($_SERVER['HTTP_CLIENT_IP'] ?? '');
$confset = isset($CFG->getremoteaddrconf);

$rows = [];
$rows[] = $row(get_string('geodiag_seenaddr', 'local_payments'), s($seen),
    $ispublic ? 'ok' : 'bad');
$rows[] = $row(get_string('geodiag_ispublic', 'local_payments'), $ispublic ? $yes : $no,
    $ispublic ? 'ok' : 'bad');
$rows[] = $row('REMOTE_ADDR', $rawremote === '' ? '&mdash;' : s($rawremote));
$rows[] = $row('X-Forwarded-For', $forwarded === '' ? '&mdash;' : s($forwarded));
$rows[] = $row('Client-IP', $clientip === '' ? '&mdash;' : s($clientip));
$rows[] = $row('$CFG->getremoteaddrconf',
    $confset ? (string) (int) $CFG->getremoteaddrconf : get_string('geodiag_notset', 'local_payments'));

echo $OUTPUT->heading(get_string('geodiag_h_address', 'local_payments'), 3);
echo $table($rows);

// The signature of the reverse-proxy trap: the server sees a private address,
// but the real one is sitting right there in a header it has been told to ignore.
if (!$ispublic && ($forwarded !== '' || $clientip !== '')) {
    echo $OUTPUT->notification(get_string('geodiag_proxytrap', 'local_payments'), 'error');
} else if (!$ispublic) {
    echo $OUTPUT->notification(get_string('geodiag_privateaddr', 'local_payments'), 'warning');
}

// ── 3. What country does that come out as? ──────────────────────────────────
$lookupip = $testip !== '' ? $testip : $seen;
$looked = \local_payments\country_detector::lookup_uncached($lookupip);

$rows = [];
$rows[] = $row(get_string('geodiag_lookupfor', 'local_payments'), s($lookupip));
$rows[] = $row(get_string('geodiag_lookupresult', 'local_payments'),
    $looked !== ''
        ? s($looked . ' — ' . ($countries[$looked] ?? $looked))
        : get_string('geodiag_unknowncountry', 'local_payments'),
    $looked !== '' ? 'ok' : 'bad');

// What THIS viewer would be priced on. An administrator is signed in, so this
// row will say "profile" — which is itself worth showing, because it is the
// rule people most often forget when they test their own site while logged in.
$isguest = !isloggedin() || isguestuser();
$rows[] = $row(get_string('geodiag_youare', 'local_payments'),
    $isguest ? get_string('geodiag_asguest', 'local_payments')
        : get_string('geodiag_assignedin', 'local_payments',
            !empty($USER->country) ? s($USER->country) : get_string('geodiag_nocountry', 'local_payments')));
$rows[] = $row(get_string('geodiag_yourcountry', 'local_payments'),
    ($c = \local_payments\country_detector::detect_for_pricing()) !== ''
        ? s($c . ' — ' . ($countries[$c] ?? $c))
        : get_string('geodiag_unknowncountry', 'local_payments'));

echo $OUTPUT->heading(get_string('geodiag_h_lookup', 'local_payments'), 3);
echo $table($rows);

// Type any address — the point being to test a real Egyptian address from an
// office abroad, or the other way round, without leaving the site.
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $url->out(false), 'class' => 'form-inline mb-4']);
if ($courseid) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
}
echo html_writer::tag('label', get_string('geodiag_testip', 'local_payments'),
    ['for' => 'lp-geodiag-ip', 'class' => 'form-label me-2']);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'id' => 'lp-geodiag-ip', 'name' => 'ip', 'value' => s($testip),
    'class' => 'form-control d-inline-block w-auto me-2', 'placeholder' => '197.0.0.1',
]);
echo html_writer::tag('button', get_string('geodiag_testgo', 'local_payments'),
    ['type' => 'submit', 'class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

// ── 4. Which row of THIS course would win? ──────────────────────────────────
if ($courseid) {
    $prices = $DB->get_records('local_payments_course_prices',
        ['courseid' => $courseid], 'is_default DESC, country ASC');

    // The country the lookup produced, priced the way price_resolver::resolve()
    // prices it: the country's own active row first, the Default row otherwise.
    $winner = null;
    if ($looked !== '') {
        foreach ($prices as $p) {
            if ((string) $p->country === $looked && (int) $p->is_active === 1) {
                $winner = $p;
                break;
            }
        }
    }
    $viadefault = false;
    if (!$winner) {
        foreach ($prices as $p) {
            if ((int) $p->is_default === 1 && (int) $p->is_active === 1) {
                $winner = $p;
                $viadefault = true;
                break;
            }
        }
    }

    $rows = [];
    if (!$prices) {
        $rows[] = $row(get_string('geodiag_courserows', 'local_payments'),
            get_string('noprices', 'local_payments'));
    } else {
        foreach ($prices as $p) {
            $name = ((string) $p->country === '*')
                ? get_string('defaultprice', 'local_payments')
                : ($countries[$p->country] ?? $p->country);
            $label = $name . ((int) $p->is_default === 1
                ? ' (' . get_string('is_default', 'local_payments') . ')' : '');
            $value = number_format((float) $p->price, 2) . ' ' . s($p->currency)
                . ((int) $p->is_active === 1 ? '' : ' — ' . get_string('is_active', 'local_payments') . ': ' . $no);
            $rows[] = $row($label, $value, (int) $p->is_active === 1 ? null : 'bad');
        }
        $rows[] = $row(get_string('geodiag_wouldwin', 'local_payments'),
            $winner
                ? (number_format((float) $winner->price, 2) . ' ' . s($winner->currency)
                    . ' — ' . ($viadefault
                        ? get_string('geodiag_viadefault', 'local_payments')
                        : get_string('geodiag_viacountry', 'local_payments', s($looked))))
                : get_string('geodiag_nowinner', 'local_payments'),
            $winner && !$viadefault ? 'ok' : 'bad');
    }

    echo $OUTPUT->heading(get_string('geodiag_h_course', 'local_payments'), 3);
    echo $table($rows);

    echo html_writer::link(
        new moodle_url('/local/payments/course_pricing.php', ['courseid' => $courseid]),
        get_string('coursepricing', 'local_payments'),
        ['class' => 'btn btn-secondary']);
}

echo $OUTPUT->footer();
