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
 * End-to-end walkthrough of the A1 money engine: buy a package, run a lesson to completion,
 * pay the teacher — printing balances at each step and asserting the wallet invariant.
 *
 * Run: docker exec moodle_app php /var/www/html/public/local/nit_flex/cli/walkthrough.php
 *
 * Idempotent: it recreates two test users and clears their prior Academy data each run.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');

use local_nit_finance\api\wallet;
use local_nit_flex\api\flex;
use local_nit_flex\api\packages;
use local_nit_flex\api\purchase;
use local_nit_lessons\api\lessons;
use local_nit_lessons\service\settings_service;

/**
 * Format minor units as an EGP string.
 *
 * @param int $minor
 * @return string
 */
function wt_money(int $minor): string {
    return number_format($minor / 100, 2) . ' EGP';
}

/**
 * Print a labelled line.
 *
 * @param string $msg
 * @return void
 */
function wt_say(string $msg): void {
    cli_writeln($msg);
}

/**
 * Get or create a test user by username.
 *
 * @param string $username
 * @param string $first
 * @return int userid
 */
function wt_user(string $username, string $first): int {
    global $DB, $CFG;
    $existing = $DB->get_record('user', ['username' => $username]);
    if ($existing) {
        return (int) $existing->id;
    }
    $user = new stdClass();
    $user->auth = 'manual';
    $user->confirmed = 1;
    $user->mnethostid = $CFG->mnet_localhost_id;
    $user->username = $username;
    $user->firstname = $first;
    $user->lastname = 'NITTest';
    $user->email = $username . '@example.invalid';
    return (int) user_create_user($user, false, false);
}

// Make every time gate pass instantly for the walkthrough.
foreach ([
    'min_booking_minutes' => 0, 'start_allowed_minutes' => 100000,
    'complete_allowed_minutes' => 0, 'cancel_deadline_minutes' => 0,
    'absence_report_minutes' => 0, 'update_deadline_minutes' => 100000,
] as $k => $v) {
    set_config($k, $v, 'local_nit_lessons');
}
set_config('teacher_percent', 40, 'local_nit_finance');
set_config('platform_percent', 60, 'local_nit_finance');

$studentid = wt_user('nit_test_student', 'Sara');
$teacherid = wt_user('nit_test_teacher', 'Tarek');

// Clear prior test data so re-runs are deterministic.
$DB->delete_records_select('nit_lesson', 'studentid = ? OR teacherid = ?', [$studentid, $teacherid]);
$DB->delete_records('nit_flex_tx', ['userid' => $studentid]);
$DB->delete_records('nit_payment', ['userid' => $studentid]);
$DB->delete_records('nit_package_purchase', ['userid' => $studentid]);
$DB->delete_records('nit_earning', ['teacherid' => $teacherid]);
$DB->delete_records('nit_withdrawal', ['teacherid' => $teacherid]);

cli_heading('NIT Academy A1 — money engine walkthrough');

// 1. Admin creates a Flex10 package (1000.00 EGP → 100000 minor).
$packageid = packages::create((object) [
    'name' => 'Flex10 (walkthrough)', 'description' => 'Test package',
    'flex_count' => 10, 'price_minor' => 100000, 'expiration_days' => 0,
]);
wt_say("1. Created package Flex10  (price " . wt_money(100000) . ", 10 Flex → value " .
    wt_money(10000) . "/Flex)");

// 2. Student buys it.
$p = purchase::fulfil($studentid, $packageid, 'online', 'WT-REF');
wt_say("2. Student bought package → balance {$p['remaining_flex']} Flex, status {$p['status']}");
$totals = flex::money_totals();
wt_say("   Platform: payments " . wt_money($totals['payments_minor']) .
    ", undistributed " . wt_money($totals['undistributed_minor']));

// 3. Student requests a lesson; teacher accepts (reserves 1 Flex).
$when = time() + 3600;
$lesson = lessons::request($studentid, $teacherid, 'Mathematics', $when, 'Please cover algebra.');
wt_say("3. Lesson requested (id {$lesson['id']}), status {$lesson['status']}");
$lesson = lessons::teacher_respond($teacherid, $lesson['id'], 'accept');
$active = purchase::active($studentid);
wt_say("   Teacher accepted → status {$lesson['status']}, flex_state {$lesson['flex_state']}; " .
    "remaining {$active['remaining_flex']}, reserved {$active['reserved_flex']}");

// 4. Teacher starts and completes the lesson (consume + distribute).
$lesson = lessons::start($teacherid, $lesson['id']);
wt_say("4. Lesson started → status {$lesson['status']}");
$lesson = lessons::complete($teacherid, $lesson['id'], 'Great session.');
$active = purchase::active($studentid);
$tw = wallet::teacher($teacherid);
wt_say("   Lesson completed → flex_state {$lesson['flex_state']}; remaining " .
    ($active['remaining_flex'] ?? 0) . ", consumed " . ($active['consumed_flex'] ?? 0));
wt_say("   Teacher wallet: earned " . wt_money($tw['total_earned_minor']) .
    ", available " . wt_money($tw['available_balance_minor']));

// 5. Teacher withdraws, admin pays.
$wd = wallet::request_withdrawal($teacherid, $tw['available_balance_minor'], 'bank', 'IBAN123');
wt_say("5. Teacher requested withdrawal " . wt_money($wd['amount_minor']) . " → {$wd['status']}");
$wd = wallet::process_withdrawal(2, $wd['id'], 'approve');
$wd = wallet::process_withdrawal(2, $wd['id'], 'pay', ['reference' => 'PAYOUT-1']);
wt_say("   Admin paid → status {$wd['status']}");

// 6. Platform wallet + invariant check.
$totals = flex::money_totals();
$pw = wallet::platform($totals['payments_minor'], $totals['undistributed_minor']);
cli_heading('Platform wallet');
wt_say("   Current money      : " . wt_money($pw['current_money_minor']));
wt_say("   Undistributed      : " . wt_money($pw['undistributed_money_minor']));
wt_say("   Teachers' money     : " . wt_money($pw['teachers_money_minor']));
wt_say("   Platform earnings   : " . wt_money($pw['platform_earnings_minor']));
wt_say("   Total paid out      : " . wt_money($pw['total_paid_out_minor']));

$sum = $pw['undistributed_money_minor'] + $pw['teachers_money_minor'] + $pw['platform_earnings_minor'];
$ok = $sum === $pw['current_money_minor'];
wt_say('');
wt_say(($ok ? '[OK] INVARIANT OK' : '[X] INVARIANT FAILED') .
    ": current (" . wt_money($pw['current_money_minor']) . ") = undistributed + teachers + earnings (" .
    wt_money($sum) . ")");

// Assert the exact expected numbers.
$expected = [
    'current_money_minor' => 96000, 'undistributed_money_minor' => 90000,
    'teachers_money_minor' => 0, 'platform_earnings_minor' => 6000, 'total_paid_out_minor' => 4000,
];
$mismatch = [];
foreach ($expected as $k => $v) {
    if ((int) $pw[$k] !== $v) {
        $mismatch[] = "$k expected " . wt_money($v) . " got " . wt_money((int) $pw[$k]);
    }
}
if ($mismatch) {
    cli_error("FAILED expectations:\n  " . implode("\n  ", $mismatch));
}
wt_say('✔ All expected balances matched. A1 money engine verified.');
exit(0);
