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
 * Exercises the automatic-offer rules against a real database, then throws the data away.
 *
 *   php local/nit_commerce/cli/test_offer_rules.php
 *
 * Covers AC-4.13.4 (where several offers cover the same item, the one giving the learner the
 * lowest price is applied), AC-4.13.6 (a checkout is refused when the price no longer matches the
 * quote the buyer was shown) and AC-4.13.7 (usage counts and orders are reportable per offer).
 * Every fixture is created inside a transaction that is deliberately rolled back, so running this
 * leaves the database exactly as it found it — safe on a staging site.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_nit_commerce\discount_manager;
use local_nit_commerce\offer_manager;

/**
 * The reason handed to rollback(), which always rethrows it. Its own class, so the catch below can
 * tell "the script finished and threw its fixtures away" apart from a genuine failure.
 */
class offer_rollback_sentinel extends \Exception {
}

$pass = 0;
$fail = 0;

/**
 * Assert two values match — floats to the cent, everything else identically.
 *
 * @param string $label
 * @param mixed $expected
 * @param mixed $actual
 * @return void
 */
function check(string $label, $expected, $actual): void {
    global $pass, $fail;
    $ok = (is_float($expected) || is_int($expected)) && !is_bool($expected)
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
 * Insert an active offer covering every course.
 *
 * @param string $name so the fixtures can be told apart in the assertions
 * @param string $type percent|fixed
 * @param float $value
 * @param int $enddate 0 = open-ended
 * @return int offer id
 */
function make_offer(string $name, string $type, float $value, int $enddate = 0): int {
    global $DB, $USER;
    $now = time();
    $id = $DB->insert_record('nit_offer', (object) [
        'name' => $name, 'description' => '', 'discount_type' => $type,
        'discount_value' => $value, 'startdate' => 0, 'enddate' => $enddate, 'status' => 'active',
        'timecreated' => $now, 'timemodified' => $now, 'usermodified' => $USER->id,
    ]);
    $DB->insert_record('nit_offer_item', (object) ['offerid' => $id, 'item_type' => 'course', 'item_id' => 0]);
    return $id;
}

/**
 * Wipe the fixtures created for one scenario, so scenarios cannot leak into each other.
 *
 * @return void
 */
function clear_fixtures(): void {
    global $DB;
    $DB->delete_records_select('nit_offer_usage',
        "offerid IN (SELECT id FROM {nit_offer} WHERE " . $DB->sql_like('name', "'TESTOFR%'") . ")");
    $DB->delete_records_select('nit_offer_item',
        "offerid IN (SELECT id FROM {nit_offer} WHERE " . $DB->sql_like('name', "'TESTOFR%'") . ")");
    $DB->delete_records_select('nit_offer', $DB->sql_like('name', "'TESTOFR%'"));
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

    // Park any real site offer for the duration, so a live promotion cannot stand in for the
    // fixtures below. Rolled back with everything else.
    $DB->set_field('nit_offer', 'status', 'inactive', ['status' => 'active']);
    cli_writeln('Site offers parked for the run (rolled back afterwards).');
    cli_writeln('');

    // ── AC-4.13.4: three offers cover the same course; the cheapest for the learner wins ──
    cli_writeln('AC-4.13.4  three offers on one course — 10%, 40%, and a flat 250');
    clear_fixtures();
    make_offer('TESTOFR small', 'percent', 10);        // 100 off -> 900.
    $bigid = make_offer('TESTOFR big', 'percent', 40); // 400 off -> 600. Cheapest.
    make_offer('TESTOFR flat', 'fixed', 250);          // 250 off -> 750.
    $matches = discount_manager::matching_offers('course', $courseid, $base);
    check('all three offers matched the item', 3, count($matches));
    check('the winner is the 40% one', $bigid, $matches[0]->id);
    check('the winner leaves the lowest price', 600.00, $matches[0]->final);
    check('ranked cheapest-first', true, $matches[0]->final <= $matches[1]->final);
    $best = discount_manager::best_offer('course', $courseid, $base);
    check('best_offer agrees', $bigid, (int) $best->id);
    check('best_offer reports how many competed', 3, (int) $best->candidates);
    $r = discount_manager::resolve('course', $courseid, $userid, '', $base);
    check('resolve applies the winner only', 400.00, $r['offer_discount']);
    check('offers never stack (not 100+400+250)', 400.00, $r['discount']);
    check('final', 600.00, $r['final']);
    check('candidate count is carried to the checkout', 3, (int) $r['offer_candidates']);

    // ── AC-4.13.4: percent vs fixed are compared as money, and the answer depends on the base ──
    cli_writeln('');
    cli_writeln('AC-4.13.4  20% vs a flat 150 — on 1000 the percentage wins');
    clear_fixtures();
    $pctid = make_offer('TESTOFR pct', 'percent', 20);   // 200 off 1000.
    $flatid = make_offer('TESTOFR flat', 'fixed', 150);  // 150 off.
    $best = discount_manager::best_offer('course', $courseid, 1000.00);
    check('percentage wins on the larger base', $pctid, (int) $best->id);
    check('and takes 200 off', 200.00, $best->discount);

    cli_writeln('');
    cli_writeln('AC-4.13.4  the same two offers on a 500 course — the flat one now wins');
    $best = discount_manager::best_offer('course', $courseid, 500.00);
    check('flat wins on the smaller base (100 vs 150)', $flatid, (int) $best->id);
    check('and takes 150 off', 150.00, $best->discount);

    // ── AC-4.13.4: an exact tie resolves the same way every time ──
    cli_writeln('');
    cli_writeln('AC-4.13.4  a tie (20% = 200 vs a flat 200) — the one ending sooner is spent first');
    clear_fixtures();
    make_offer('TESTOFR openended', 'percent', 20, 0);
    $endingid = make_offer('TESTOFR ending', 'fixed', 200, time() + DAYSECS);
    $first = discount_manager::best_offer('course', $courseid, $base);
    $second = discount_manager::best_offer('course', $courseid, $base);
    check('the dated offer wins the tie', $endingid, (int) $first->id);
    check('and the same offer wins every time it is asked', (int) $first->id, (int) $second->id);

    // ── An expired or not-yet-started offer is not a candidate at all ──
    cli_writeln('');
    cli_writeln('AC-4.13.4  an offer past its end date does not compete');
    clear_fixtures();
    $liveid = make_offer('TESTOFR live', 'percent', 10);
    make_offer('TESTOFR expired', 'percent', 90, time() - HOURSECS);
    $matches = discount_manager::matching_offers('course', $courseid, $base);
    check('only the live offer matched', 1, count($matches));
    check('the expired 90% one is not applied', $liveid, (int) $matches[0]->id);
    check('so the learner pays the 10% price', 900.00, $matches[0]->final);

    // ── AC-4.13.6: the same expiry, seen from the checkout ──
    cli_writeln('');
    cli_writeln('AC-4.13.6  the quote a buyer was shown no longer matches the price');
    clear_fixtures();
    make_offer('TESTOFR lapsing', 'percent', 40);
    $atquote = discount_manager::resolve('course', $courseid, $userid, '', $base);
    check('the buyer was quoted 600', 600.00, $atquote['final']);
    // Now the offer ends while they are on the confirmation screen.
    $DB->set_field_select('nit_offer', 'enddate', time() - 60, $DB->sql_like('name', "'TESTOFR%'"));
    $now = discount_manager::resolve('course', $courseid, $userid, '', $base);
    check('the price is now the undiscounted 1000', 1000.00, $now['final']);
    check('so the quote and the price disagree', true, abs($now['final'] - $atquote['final']) >= 0.005);
    if (class_exists('\local_payments\price_changed_exception')) {
        $e = new \local_payments\price_changed_exception($atquote['final'], $now['final'], 'EGP');
        $payload = $e->to_array();
        check('the checkout can tell the buyer what it was', 600.00, $payload['quoted']);
        check('…and what it is now', 1000.00, $payload['amount']);
        check('…and that it went up', true, $payload['increase']);
        check('tagged so a JSON caller can act on it', 'price_changed', $payload['code']);
    } else {
        cli_writeln('  SKIP  local_payments not installed — no checkout to refuse');
    }

    // ── AC-4.13.7: usage and orders are reportable per offer ──
    cli_writeln('');
    cli_writeln('AC-4.13.7  usage counts and orders per offer');
    clear_fixtures();
    $repid = make_offer('TESTOFR reported', 'percent', 25);
    $unusedid = make_offer('TESTOFR unused', 'percent', 5);
    $now = time();
    foreach ([1, 2] as $n) {
        $DB->insert_record('nit_offer_usage', (object) [
            'offerid' => $repid, 'userid' => $userid, 'transactionid' => 0,
            'item_type' => 'course', 'item_id' => $courseid,
            'original_amount' => 1000, 'discount_amount' => 250, 'final_amount' => 750,
            'timecreated' => $now - $n,
        ]);
    }
    $data = offer_manager::get_usages(['offerid' => $repid, 'state' => 'all']);
    check('both orders are listed', 2, $data['total']);
    check('with the item they were bought against', 'course', $data['rows'][0]['item_type']);
    check('and the money they took off', 250.00, $data['rows'][0]['discount_amount']);
    check('totals add the discount up', 500.00, $data['totals']['discounted']);
    check('and what was actually collected', 1500.00, $data['totals']['net']);
    check('one learner behind them', 1, $data['totals']['learners']);

    $summary = offer_manager::get_usage_summary(['state' => 'all']);
    $byid = [];
    foreach ($summary as $row) {
        $byid[$row['offerid']] = $row;
    }
    check('the used offer reports its usage count', 2, $byid[$repid]['usages'] ?? -1);
    check('an offer nobody bought under is still reported', 0, $byid[$unusedid]['usages'] ?? -1);
    check('…rather than being missing from the report', true, isset($byid[$unusedid]));

    // A usage row tied to an unpaid order is a reservation, not a sale.
    if ($DB->get_manager()->table_exists('local_payments_transactions')) {
        cli_writeln('');
        cli_writeln('AC-4.13.7  a row held by an unpaid checkout is not counted as a use');
        $txid = $DB->insert_record('local_payments_transactions', (object) [
            'userid' => $userid, 'courseid' => $courseid, 'provider_id' => 0,
            'order_id' => 'TESTOFR-' . $now, 'idempotency_key' => 'TESTOFR-' . $now,
            'amount' => 750, 'original_amount' => 1000, 'currency' => 'EGP',
            'status' => 'pending', 'timecreated' => $now, 'timemodified' => $now,
        ]);
        $DB->insert_record('nit_offer_usage', (object) [
            'offerid' => $repid, 'userid' => $userid, 'transactionid' => $txid,
            'item_type' => 'course', 'item_id' => $courseid,
            'original_amount' => 1000, 'discount_amount' => 250, 'final_amount' => 750,
            'timecreated' => $now,
        ]);
        $confirmed = offer_manager::get_usages(['offerid' => $repid, 'state' => 'confirmed']);
        $held = offer_manager::get_usages(['offerid' => $repid, 'state' => 'pending']);
        $all = offer_manager::get_usages(['offerid' => $repid, 'state' => 'all']);
        check('the unpaid one is excluded by default', 2, $confirmed['total']);
        check('but can be asked for', 1, $held['total']);
        check('and both together are every row', 3, $all['total']);
        check('the held row is flagged, not hidden', false, $held['rows'][0]['confirmed']);
        $offer = offer_manager::get_offer($repid);
        check('the offers table counts uses without the held one', 2, $offer['usage_paid']);
        check('and shows the held one separately', 1, $offer['usage_held']);
    }

    clear_fixtures();
    // Deliberately never committed: the fixtures above must not survive this script. rollback()
    // always rethrows the reason it is given, so the sentinel below is the expected exit path.
    $tx->rollback(new offer_rollback_sentinel('done'));
} catch (offer_rollback_sentinel $e) {
    cli_writeln('');
    cli_writeln('Fixtures rolled back — the database is as it was found.');
} catch (\Throwable $e) {
    cli_writeln('');
    cli_writeln('UNEXPECTED: ' . get_class($e) . ': ' . $e->getMessage());
    cli_writeln($e->getTraceAsString());
    $fail++;
}

cli_writeln('');
cli_writeln(str_repeat('─', 60));
cli_writeln("  {$pass} passed, {$fail} failed");
exit($fail > 0 ? 1 : 0);
