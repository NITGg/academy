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
 * Exercises the coupon/offer resolution rules against a real database, then throws the data away.
 *
 *   php local/nit_commerce/cli/test_discount_rules.php
 *
 * Covers AC-4.12.6 (only the larger of coupon and offer applies; a tie goes to the offer; the two
 * are never combined or chained) and AC-4.12.7 (a discount above the order value yields zero, not
 * a negative). Every fixture is created inside a transaction that is deliberately rolled back, so
 * running this leaves the database exactly as it found it — safe on a staging site.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_nit_commerce\discount_manager;

/**
 * The reason handed to rollback(), which always rethrows it. Its own class, so the catch below can
 * tell "the script finished and threw its fixtures away" apart from a genuine failure.
 */
class rollback_sentinel extends \Exception {
}

$pass = 0;
$fail = 0;

/**
 * Assert two floats match to the cent.
 *
 * @param string $label
 * @param float $expected
 * @param float $actual
 * @return void
 */
function check(string $label, $expected, $actual): void {
    global $pass, $fail;
    $ok = is_float($expected) || is_int($expected)
        ? (abs((float) $expected - (float) $actual) < 0.005)
        : ($expected === $actual);
    if ($ok) {
        $pass++;
        cli_writeln("  PASS  {$label}");
    } else {
        $fail++;
        cli_writeln("  FAIL  {$label} — expected " . var_export($expected, true)
            . ', got ' . var_export($actual, true));
    }
}

/**
 * Insert an active offer covering every course, worth the given discount.
 *
 * @param string $type percent|fixed
 * @param float $value
 * @return int offer id
 */
function make_offer(string $type, float $value): int {
    global $DB, $USER;
    $now = time();
    $id = $DB->insert_record('nit_offer', (object) [
        'name' => 'TEST offer', 'description' => '', 'discount_type' => $type,
        'discount_value' => $value, 'startdate' => 0, 'enddate' => 0, 'status' => 'active',
        'timecreated' => $now, 'timemodified' => $now, 'usermodified' => $USER->id,
    ]);
    $DB->insert_record('nit_offer_item', (object) ['offerid' => $id, 'item_type' => 'course', 'item_id' => 0]);
    return $id;
}

/**
 * Insert an active coupon covering every course, worth the given discount.
 *
 * @param string $code
 * @param string $type percent|fixed
 * @param float $value
 * @param float|null $max optional cap
 * @return int coupon id
 */
function make_coupon(string $code, string $type, float $value, $max = null): int {
    global $DB, $USER;
    $now = time();
    $id = $DB->insert_record('nit_coupon', (object) [
        'code' => $code, 'name' => 'TEST coupon', 'description' => '', 'discount_type' => $type,
        'discount_value' => $value, 'max_discount' => $max, 'usage_type' => 'multiple',
        'usage_limit' => 0, 'startdate' => 0, 'enddate' => 0, 'status' => 'active',
        'timecreated' => $now, 'timemodified' => $now, 'usermodified' => $USER->id,
    ]);
    $DB->insert_record('nit_coupon_item', (object) ['couponid' => $id, 'item_type' => 'course', 'item_id' => 0]);
    return $id;
}

/**
 * Wipe the fixtures created for one scenario, so scenarios cannot leak into each other.
 *
 * @return void
 */
function clear_fixtures(): void {
    global $DB;
    $DB->delete_records_select('nit_offer_item', "offerid IN (SELECT id FROM {nit_offer} WHERE name = 'TEST offer')");
    $DB->delete_records('nit_offer', ['name' => 'TEST offer']);
    $DB->delete_records_select('nit_coupon_item', "couponid IN (SELECT id FROM {nit_coupon} WHERE name = 'TEST coupon')");
    $DB->delete_records('nit_coupon', ['name' => 'TEST coupon']);
}

// Any course id will do: the base price is passed in explicitly, so no pricing rows are consulted.
$courseid = (int) $DB->get_field_sql('SELECT MIN(id) FROM {course} WHERE id > 1');
if (!$courseid) {
    cli_error('No course to test against.');
}
$userid = (int) $DB->get_field_sql('SELECT MIN(id) FROM {user} WHERE deleted = 0 AND id > 1');

cli_writeln('Testing against course ' . $courseid . ', base price 1000.00');
cli_writeln('');

$tx = $DB->start_delegated_transaction();
try {
    $base = 1000.00;

    // Park any real site offer for the duration. best_offer() picks the largest offer that covers
    // the item, so a live site-wide promotion would silently stand in for the fixtures below and
    // the expected numbers would depend on whatever marketing is running today. Rolled back with
    // everything else, so the site's own offers are untouched.
    $DB->set_field('nit_offer', 'status', 'inactive', ['status' => 'active']);
    cli_writeln('Site offers parked for the run (rolled back afterwards).');
    cli_writeln('');

    // ── AC-4.12.6: the coupon is worth more, so the coupon wins outright ──
    cli_writeln('AC-4.12.6  coupon (400) beats offer (10% = 100)');
    clear_fixtures();
    make_offer('percent', 10);
    make_coupon('TESTBIG', 'fixed', 400);
    $r = discount_manager::resolve('course', $courseid, $userid, 'TESTBIG', $base);
    check('coupon discount is the full 400', 400.00, $r['coupon_discount']);
    check('offer contributes nothing', 0.00, $r['offer_discount']);
    check('offers list is empty (no offer usage recorded)', 0, count($r['offers']));
    check('total discount is the winner alone, not 500', 400.00, $r['discount']);
    check('final = 1000 - 400', 600.00, $r['final']);
    check('applied flag', 'coupon', $r['applied']);

    // ── AC-4.12.6: the offer is worth more, so the coupon is set aside ──
    cli_writeln('');
    cli_writeln('AC-4.12.6  offer (50% = 500) beats coupon (100)');
    clear_fixtures();
    make_offer('percent', 50);
    make_coupon('TESTSMALL', 'fixed', 100);
    $r = discount_manager::resolve('course', $courseid, $userid, 'TESTSMALL', $base);
    check('offer discount applied', 500.00, $r['offer_discount']);
    check('coupon contributes nothing', 0.00, $r['coupon_discount']);
    check('coupon is flagged as superseded, not rejected', true, $r['coupon_superseded']);
    check('total discount is 500, not 600', 500.00, $r['discount']);
    check('final = 1000 - 500', 500.00, $r['final']);
    check('applied flag', 'offer', $r['applied']);

    // ── AC-4.12.6: equal amounts — the offer takes it ──
    cli_writeln('');
    cli_writeln('AC-4.12.6  tie (offer 20% = 200, coupon 200) goes to the offer');
    clear_fixtures();
    make_offer('percent', 20);
    make_coupon('TESTTIE', 'fixed', 200);
    $r = discount_manager::resolve('course', $courseid, $userid, 'TESTTIE', $base);
    check('offer applied on a tie', 200.00, $r['offer_discount']);
    check('coupon not spent on a tie', 0.00, $r['coupon_discount']);
    check('applied flag', 'offer', $r['applied']);
    check('final', 800.00, $r['final']);

    // ── AC-4.12.6: a coupon measured against the base, never against the offer-reduced total ──
    cli_writeln('');
    cli_writeln('AC-4.12.6  coupon 30% is measured on 1000, not on the offer-reduced 900');
    clear_fixtures();
    make_offer('percent', 10);            // 100 off.
    make_coupon('TESTPCT', 'percent', 30); // 300 off the base; 270 if chained after the offer.
    $r = discount_manager::resolve('course', $courseid, $userid, 'TESTPCT', $base);
    check('coupon computed on the full base', 300.00, $r['coupon_discount']);
    check('final = 1000 - 300 (not 1000 - 100 - 270)', 700.00, $r['final']);

    // ── AC-4.12.7: a discount bigger than the order ──
    cli_writeln('');
    cli_writeln('AC-4.12.7  a 5000 coupon on a 1000 order');
    clear_fixtures();
    make_coupon('TESTHUGE', 'fixed', 5000);
    $r = discount_manager::resolve('course', $courseid, $userid, 'TESTHUGE', $base);
    check('discount capped at the order value', 1000.00, $r['discount']);
    check('final is exactly zero, never negative', 0.00, $r['final']);
    check('final is not negative', true, $r['final'] >= 0);

    cli_writeln('');
    cli_writeln('AC-4.12.7  a 200% offer on a 1000 order');
    clear_fixtures();
    make_offer('percent', 100);
    $r = discount_manager::resolve('course', $courseid, $userid, '', $base);
    check('final is zero', 0.00, $r['final']);
    check('final is not negative', true, $r['final'] >= 0);

    // ── A coupon on an item with no offer still works normally ──
    cli_writeln('');
    cli_writeln('Baseline  coupon alone, no offer in play');
    clear_fixtures();
    make_coupon('TESTALONE', 'percent', 25);
    $r = discount_manager::resolve('course', $courseid, $userid, 'TESTALONE', $base);
    check('coupon applied', 250.00, $r['coupon_discount']);
    check('final', 750.00, $r['final']);
    check('applied flag', 'coupon', $r['applied']);

    clear_fixtures();
    // Deliberately never committed: the fixtures above must not survive this script. rollback()
    // always rethrows the reason it is given, so the sentinel below is the expected exit path —
    // it is recognised by class, because moodle_exception's getMessage() is the localised
    // "Error occurred", not the debug text passed in.
    $tx->rollback(new rollback_sentinel('done'));
} catch (rollback_sentinel $e) {
    cli_writeln('');
    cli_writeln('Fixtures rolled back — the database is as it was found.');
} catch (\Throwable $e) {
    cli_writeln('');
    cli_writeln('UNEXPECTED: ' . get_class($e) . ': ' . $e->getMessage());
    $fail++;
}

cli_writeln('');
cli_writeln(str_repeat('─', 60));
cli_writeln("  {$pass} passed, {$fail} failed");
exit($fail > 0 ? 1 : 0);
