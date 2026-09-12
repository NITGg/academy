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
 * Empty every financial record on the site, so the reports can be tested from zero.
 *
 * This deletes MONEY AND ACTIVITY — orders, refunds, redemptions, invoices, payment logs,
 * subscription purchases. It deliberately does NOT touch SETUP: coupons, offers, plans,
 * course prices, payment providers and refund terms all survive, because the point of a clean
 * slate is to buy through the same catalogue again, not to rebuild it.
 *
 * It prints what it would remove and removes nothing unless --execute is given, because on a
 * live site these rows are the only record that money changed hands and there is no undo.
 * Take a database dump first; this script will not do that for you.
 *
 * Usage (from the Moodle code root, inside the container):
 *   php public/local/nit_finance/cli/reset_financial_data.php                 # dry run
 *   php public/local/nit_finance/cli/reset_financial_data.php --execute
 *   php public/local/nit_finance/cli/reset_financial_data.php --execute --revoke-access
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'execute'       => false,
        'revoke-access' => false,
        'help'          => false,
    ],
    ['h' => 'help', 'e' => 'execute']
);

if ($unrecognized) {
    cli_error('Unknown option: ' . implode(', ', $unrecognized));
}

if ($options['help']) {
    echo <<<'HELP'
Empty every financial record so the reports can be tested from zero.

  (no options)      Dry run: print what would be deleted, change nothing.
  --execute, -e     Actually delete it. There is no undo — take a DB dump first.
  --revoke-access   Also take away the course access those purchases granted, so a
                    test buyer can go through checkout again from nothing. Off by
                    default: it unenrols real students on a live site.
  --help, -h        This message.

KEPT, always: coupons, offers, subscription plans and their course lists, course
prices, payment providers, refund terms, free-preview settings.

HELP;
    exit(0);
}

/**
 * Every table holding a financial record, in delete order (children before parents).
 *
 * @return array table => one-line description
 */
function local_nit_finance_reset_tables(): array {
    return [
        // local_payments: the money-in ledger and everything hanging off it.
        'local_payments_refunds'      => 'refunds issued against an order',
        'local_payments_refund_reqs'  => 'refund requests from buyers',
        'local_payments_invoices'     => 'issued invoices',
        'local_payments_audit_logs'   => 'status-change audit trail',
        'local_payments_webhooks'     => 'gateway callbacks received',
        'local_payments_logs'         => 'gateway request/error log',
        'local_payments_transactions' => 'orders (the money-in ledger itself)',

        // local_nit_commerce: which coupon/offer was actually redeemed, and on what.
        'nit_coupon_usage'            => 'coupon redemptions',
        'nit_offer_usage'             => 'offer applications',

        // local_nit_subscriptions.
        'nit_sub_purchase'            => 'subscription plan purchases',
        'nit_enrol_source'            => 'recorded origin of each enrolment',

        // local_nit_finance's own Flex-model tables (unused on this site, emptied anyway).
        'nit_earning'                 => 'teacher/platform revenue splits',
        'nit_withdrawal'              => 'teacher withdrawal requests',
    ];
}

/**
 * Setup that must survive, listed so the operator can see it was considered.
 *
 * @return array table => one-line description
 */
function local_nit_finance_keep_tables(): array {
    return [
        'nit_coupon'                    => 'the coupons themselves',
        'nit_coupon_item'               => 'what each coupon applies to',
        'nit_offer'                     => 'the offers themselves',
        'nit_offer_item'                => 'what each offer applies to',
        'nit_subscription'              => 'subscription plans',
        'nit_sub_price'                 => 'per-country plan prices',
        'nit_sub_seat_option'           => 'B2B seat tiers',
        'nit_subscription_category'     => 'which category pages a plan appears on',
        'nit_course_access'             => 'which courses each plan unlocks',
        'nit_sub_reminder'              => 'renewal reminder settings',
        'local_payments_course_prices'  => 'per-country course prices',
        'local_payments_providers'      => 'payment gateways',
        'local_payments_refund_terms'   => 'refund policy',
        'local_payments_free_preview'   => 'free preview lessons',
    ];
}

global $DB;

$tables = local_nit_finance_reset_tables();
$dbman = $DB->get_manager();

// Count first, so the dry run and the real run describe the same thing.
$counts = [];
$total = 0;
foreach ($tables as $table => $description) {
    if (!$dbman->table_exists(new xmldb_table($table))) {
        $counts[$table] = null;
        continue;
    }
    $counts[$table] = $DB->count_records($table);
    $total += $counts[$table];
}

echo "\n";
echo $options['execute'] ? "DELETING financial data\n" : "DRY RUN — nothing will be changed\n";
echo str_repeat('=', 72), "\n";
echo "Site: {$CFG->wwwroot}\n";
echo "DB:   {$CFG->dbname} on {$CFG->dbhost}\n\n";

foreach ($counts as $table => $count) {
    if ($count === null) {
        printf("  %-30s  %8s  %s\n", $table, '-', '(table not installed)');
        continue;
    }
    printf("  %-30s  %8d  %s\n", $table, $count, $tables[$table]);
}
printf("  %-30s  %8d\n", strtoupper('total rows'), $total);

echo "\nKept (setup, not money):\n";
foreach (local_nit_finance_keep_tables() as $table => $description) {
    if (!$dbman->table_exists(new xmldb_table($table))) {
        continue;
    }
    printf("  %-30s  %8d  %s\n", $table, $DB->count_records($table), $description);
}

// Access granted by the purchases about to disappear. Listed even when --revoke-access was
// not asked for, because leaving it behind is a decision and the operator should make it
// knowingly: without it those learners keep the courses they paid for, for free, for ever.
$paidenrolments = $DB->get_records_sql(
    "SELECT DISTINCT t.userid, t.courseid
       FROM {local_payments_transactions} t
      WHERE t.courseid > 0 AND t.status = :status",
    ['status' => 'completed']
);
$subpurchases = $DB->count_records_select('nit_sub_purchase', 'status = :status', ['status' => 'active']);

echo "\nCourse access these records granted:\n";
printf("  %-30s  %8d  %s\n", 'paid course enrolments', count($paidenrolments),
    'from completed single-course orders');
printf("  %-30s  %8d  %s\n", 'active subscriptions', $subpurchases,
    'each unlocking every course in its plan');
echo $options['revoke-access']
    ? "  --revoke-access given: this access WILL be taken away.\n"
    : "  --revoke-access NOT given: this access stays. Learners keep the courses for free.\n";

if (!$options['execute']) {
    echo "\nNothing was changed. Re-run with --execute to do it.\n";
    echo "Take a database dump first — there is no undo.\n\n";
    exit(0);
}

if ($total === 0 && !$options['revoke-access']) {
    echo "\nAlready empty — nothing to do.\n\n";
    exit(0);
}

// Access is revoked before the records go, because revoking needs to read them.
$revoked = 0;
if ($options['revoke-access']) {
    echo "\nRevoking access…\n";

    foreach ($paidenrolments as $row) {
        if (!$DB->record_exists('course', ['id' => $row->courseid])) {
            continue;
        }
        \local_payments\enrollment_handler::unenrol_user((int) $row->userid, (int) $row->courseid);
        $revoked++;
    }

    // Plans go through the subscription code rather than a raw unenrol, so a learner who also
    // bought a course outright, or holds it through a second plan, keeps it.
    $purchases = $DB->get_records('nit_sub_purchase', ['status' => 'active']);
    foreach ($purchases as $purchase) {
        \local_nit_subscriptions\subscription_purchase_manager::unsubscribe((int) $purchase->id);
        $revoked++;
    }
    echo "  revoked access for {$revoked} purchase(s).\n";
}

// One transaction: a half-emptied ledger is worse than a full one, because the reports would
// then be reconciling orders against refunds that no longer have orders.
echo "\nDeleting…\n";
$tx = $DB->start_delegated_transaction();
$deleted = 0;
foreach ($tables as $table => $description) {
    if ($counts[$table] === null) {
        continue;
    }
    $DB->delete_records($table);
    $deleted += $counts[$table];
    printf("  %-30s  %8d deleted\n", $table, $counts[$table]);
}
$tx->allow_commit();

purge_all_caches();

echo "\nDone. {$deleted} row(s) deleted";
echo $options['revoke-access'] ? ", {$revoked} purchase(s) revoked" : '';
echo ".\n";
echo "Caches purged. The Financial Report now reads zero.\n\n";
