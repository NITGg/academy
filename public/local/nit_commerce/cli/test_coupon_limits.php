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
 * Exercises the coupon usage cap (AC-4.12.9) and the redemption record (AC-4.12.8).
 *
 *   php local/nit_commerce/cli/test_coupon_limits.php
 *
 * Unlike {@see test_discount_rules.php} this one cannot run inside a rolled-back transaction: the
 * cap is enforced with {@see \core\lock} named locks and by counting committed rows, so nothing
 * would be visible to a second connection if the writes were never committed. It therefore commits
 * its fixtures and deletes them again in a finally block — every row it creates is tagged
 * ZZTESTLIMIT so a crashed run can be cleaned up by hand.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_nit_commerce\discount_manager;

const TAG = 'ZZTESTLIMIT';

// ── Racer mode ──
// The parent re-invokes this same file twice with --race to get two genuinely separate processes
// contending for one seat. Each child does exactly one reservation and prints OK or the error, so
// the parent can count winners. It exits here and never runs the suite below.
list($opts) = cli_get_params([
    'race' => false, 'userid' => 0, 'txid' => 0, 'couponid' => 0,
]);
if (!empty($opts['race'])) {
    $courseid = (int) $DB->get_field_sql('SELECT MIN(id) FROM {course} WHERE id > 1');
    $couponid = (int) $opts['couponid'];
    // Same hand-built resolution as the parent below, and for the same reason: the race is about
    // the lock around the cap, not about which discount wins.
    $resolved = [
        'original' => 1000.00, 'offers' => [], 'offer_id' => 0, 'offer_discount' => 0.0,
        'coupon_id' => $couponid,
        'coupon_code' => (string) $DB->get_field('nit_coupon', 'code', ['id' => $couponid]),
        'coupon_discount' => 100.00, 'applied' => 'coupon', 'discount' => 100.00, 'final' => 900.00,
    ];
    try {
        discount_manager::reserve_usage($resolved, (int) $opts['userid'], (int) $opts['txid'],
            'course', $courseid);
        echo "OK took the seat\n";
    } catch (\moodle_exception $e) {
        echo 'REFUSED ' . ($e->errorcode ?: $e->getMessage()) . "\n";
    }
    exit(0);
}

$pass = 0;
$fail = 0;

/**
 * Record a check result.
 *
 * @param string $label
 * @param bool $ok
 * @param string $detail
 * @return void
 */
function check(string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    if ($ok) {
        $pass++;
        cli_writeln("  PASS  {$label}");
    } else {
        $fail++;
        cli_writeln("  FAIL  {$label}" . ($detail !== '' ? " — {$detail}" : ''));
    }
}

/**
 * Delete every row this script creates.
 *
 * @return void
 */
function cleanup(): void {
    global $DB;
    $ids = $DB->get_fieldset_select('nit_coupon', 'id', 'name = ?', [TAG]);
    foreach ($ids as $id) {
        $DB->delete_records('nit_coupon_usage', ['couponid' => $id]);
        $DB->delete_records('nit_coupon_item', ['couponid' => $id]);
    }
    $DB->delete_records('nit_coupon', ['name' => TAG]);
    $DB->delete_records_select('local_payments_transactions', 'order_id LIKE ?', [TAG . '%']);
}

$courseid = (int) $DB->get_field_sql('SELECT MIN(id) FROM {course} WHERE id > 1');
$users = $DB->get_fieldset_sql('SELECT id FROM {user} WHERE deleted = 0 AND id > 1 ORDER BY id', []);
if (count($users) < 3) {
    cli_error('Need at least 3 users to test the per-user rule.');
}
$prov = (int) $DB->get_field_sql('SELECT MIN(id) FROM {local_payments_providers}');

cleanup();

try {
    // ── Fixture: a coupon worth 10% that may be redeemed twice in total ──
    $now = time();
    $couponid = $DB->insert_record('nit_coupon', (object) [
        'code' => TAG . '2USES', 'name' => TAG, 'description' => '', 'discount_type' => 'percent',
        'discount_value' => 10, 'max_discount' => null, 'usage_type' => 'multiple',
        'usage_limit' => 2, 'startdate' => 0, 'enddate' => 0, 'status' => 'active',
        'timecreated' => $now, 'timemodified' => $now, 'usermodified' => 2,
    ]);
    $DB->insert_record('nit_coupon_item',
        (object) ['couponid' => $couponid, 'item_type' => 'course', 'item_id' => 0]);

    /**
     * Make a pending order row for a buyer.
     *
     * @param int $userid
     * @param int $n
     * @return int transaction id
     */
    $order = function (int $userid, int $n) use ($DB, $courseid, $prov, $now): int {
        return $DB->insert_record('local_payments_transactions', (object) [
            'userid' => $userid, 'courseid' => $courseid, 'provider_id' => $prov,
            'order_id' => TAG . '-' . $n, 'idempotency_key' => TAG . '-k' . $n,
            'amount' => 900, 'original_amount' => 1000, 'currency' => 'EGP',
            'status' => 'pending', 'timecreated' => $now, 'timemodified' => $now,
        ]);
    };

    /**
     * Try to reserve the coupon for a buyer; returns '' on success or the error code.
     *
     * The resolved array is built here rather than taken from resolve(), deliberately. This suite
     * is about the usage cap, and resolve() answers a different question: whether the coupon beat
     * the site's current offers (AC-4.12.6, covered by test_discount_rules.php). Calling it would
     * make these results depend on whatever promotion marketing has running — a live site-wide
     * offer bigger than the coupon returns a zero coupon discount, nothing is reserved, and the
     * cap looks broken when it is not.
     *
     * @param int $userid
     * @param int $txid
     * @return string
     */
    $reserve = function (int $userid, int $txid) use ($courseid, $couponid): string {
        $resolved = [
            'original'        => 1000.00,
            'offers'          => [],
            'offer_id'        => 0,
            'offer_discount'  => 0.0,
            'coupon_id'       => $couponid,
            'coupon_code'     => TAG . '2USES',
            'coupon_discount' => 100.00,
            'applied'         => 'coupon',
            'discount'        => 100.00,
            'final'           => 900.00,
        ];
        try {
            discount_manager::reserve_usage($resolved, $userid, $txid, 'course', $courseid);
            return '';
        } catch (\moodle_exception $e) {
            return $e->errorcode ?: $e->getMessage();
        }
    };

    /**
     * How many redemptions the coupon has recorded.
     *
     * @return int
     */
    $count = function () use ($DB, $couponid): int {
        return $DB->count_records('nit_coupon_usage', ['couponid' => $couponid]);
    };

    cli_writeln('AC-4.12.9  a coupon capped at 2 uses');
    $e1 = $reserve((int) $users[0], $order((int) $users[0], 1));
    check('first redemption succeeds', $e1 === '', $e1);
    check('counter is 1', $count() === 1, 'got ' . $count());

    $e2 = $reserve((int) $users[1], $order((int) $users[1], 2));
    check('second redemption succeeds', $e2 === '', $e2);
    check('counter is 2', $count() === 2, 'got ' . $count());

    $e3 = $reserve((int) $users[2], $order((int) $users[2], 3));
    check('third redemption is refused at the cap', $e3 === 'err_couponusedup', 'got "' . $e3 . '"');
    check('counter is still 2 — the cap held', $count() === 2, 'got ' . $count());

    // ── The same buyer twice ──
    cli_writeln('');
    cli_writeln('One redemption per learner');
    $DB->delete_records('nit_coupon_usage', ['couponid' => $couponid]);
    $e4 = $reserve((int) $users[0], $order((int) $users[0], 4));
    check('first use by this learner succeeds', $e4 === '', $e4);
    $e5 = $reserve((int) $users[0], $order((int) $users[0], 5));
    check('same learner refused a second time', $e5 === 'err_couponalreadyusedbyuser', 'got "' . $e5 . '"');

    // ── AC-4.12.9 under genuine concurrency ──
    // Two OS processes, started together, both racing to take the last remaining seat. Only one
    // may get it. A check-then-insert without the lock loses this race roughly half the time.
    cli_writeln('');
    cli_writeln('AC-4.12.9  two processes race for the last seat');
    $DB->delete_records('nit_coupon_usage', ['couponid' => $couponid]);
    $DB->set_field('nit_coupon', 'usage_limit', 1, ['id' => $couponid]);

    $racer = __DIR__ . '/../../../local/nit_commerce/cli/test_coupon_limits.php';
    $ua = (int) $users[0];
    $ub = (int) $users[1];
    $txa = $order($ua, 10);
    $txb = $order($ub, 11);

    $cmd = function (int $u, int $t) use ($racer, $couponid) {
        return escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($racer)
            . " --race --userid={$u} --txid={$t} --couponid={$couponid} 2>&1";
    };
    // Launched back to back so both are inside reserve_usage at the same time.
    $pa = popen($cmd($ua, $txa), 'r');
    $pb = popen($cmd($ub, $txb), 'r');
    $ra = trim((string) stream_get_contents($pa)); pclose($pa);
    $rb = trim((string) stream_get_contents($pb)); pclose($pb);

    cli_writeln("    process A: {$ra}");
    cli_writeln("    process B: {$rb}");
    $wins = (int) (strpos($ra, 'OK') === 0) + (int) (strpos($rb, 'OK') === 0);
    check('exactly one process won the seat', $wins === 1, "{$wins} processes succeeded");
    check('exactly one usage row exists', $count() === 1, 'got ' . $count());

    // ── AC-4.12.8: what the surviving row actually records ──
    cli_writeln('');
    cli_writeln('AC-4.12.8  the redemption record');
    $row = $DB->get_record_sql('SELECT * FROM {nit_coupon_usage} WHERE couponid = ?', [$couponid]);
    check('records the learner', !empty($row->userid), 'userid=' . ($row->userid ?? 'null'));
    check('records the order', !empty($row->transactionid), 'transactionid=' . ($row->transactionid ?? 'null'));
    check('records the date', !empty($row->timecreated), 'timecreated=' . ($row->timecreated ?? 'null'));
    check('records the amount discounted', abs((float) $row->discount_amount - 100.00) < 0.005,
        'discount_amount=' . ($row->discount_amount ?? 'null'));
    check('records what was charged', abs((float) $row->final_amount - 900.00) < 0.005,
        'final_amount=' . ($row->final_amount ?? 'null'));
    check('records the item', $row->item_type === 'course' && (int) $row->item_id === $courseid);

    // ── Releasing a reservation frees the seat again ──
    cli_writeln('');
    cli_writeln('An abandoned checkout frees its seat');
    discount_manager::release_usage((int) $row->transactionid);
    check('the reservation is gone', $count() === 0, 'got ' . $count());
    $e6 = $reserve((int) $users[2], $order((int) $users[2], 12));
    check('the freed seat can be taken by someone else', $e6 === '', $e6);

} finally {
    cleanup();
    cli_writeln('');
    cli_writeln('Fixtures removed.');
}

cli_writeln(str_repeat('─', 60));
cli_writeln("  {$pass} passed, {$fail} failed");
exit($fail > 0 ? 1 : 0);
