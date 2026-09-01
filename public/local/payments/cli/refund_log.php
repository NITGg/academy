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
 * What happened to a refund.
 *
 * A refund touches four tables and the interesting part is usually in whichever
 * one you are not looking at: the money is in local_payments_refunds, the ask is
 * in local_payments_refund_reqs, the transaction says whether it ended up
 * refunded, and the reason it went wrong is in local_payments_logs. This prints
 * all four against one payment, in the order they happened.
 *
 * Usage (from the Moodle root, or via docker):
 *   php public/local/payments/cli/refund_log.php
 *   php public/local/payments/cli/refund_log.php --order=PAY-2026-62327662
 *   php public/local/payments/cli/refund_log.php --limit=20 --failed
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['help' => false, 'order' => '', 'limit' => 10, 'failed' => false, 'requests' => false],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}

if ($options['help']) {
    cli_writeln("Show what happened to a refund: the ask, the gateway call, the outcome
and any error, for one payment or for the most recent ones.

Options:
  --order=PAY-…   One payment, by order id. Prints everything known about it.
  --limit=N       How many refunds to list when no order is given (default 10).
  --failed        List only refunds that did not complete. Use this first when
                  somebody says a refund did not work.
  --requests      List refund requests waiting on a decision instead of refunds.
  -h, --help      Print this help.
");
    exit(0);
}

/**
 * Money, with its currency, or a dash.
 *
 * @param float|null $amount
 * @param string|null $currency
 * @return string
 */
function local_payments_refundlog_money($amount, $currency): string {
    if ($amount === null) {
        return '-';
    }
    return number_format((float) $amount, 2) . ' ' . ($currency ?: '');
}

/**
 * Everything recorded against one transaction, oldest first.
 *
 * @param \stdClass $transaction
 * @return void
 */
function local_payments_refundlog_detail(\stdClass $transaction): void {
    global $DB;

    cli_writeln(str_repeat('=', 78));
    cli_writeln('Order        ' . $transaction->order_id);
    cli_writeln('Status       ' . $transaction->status);
    cli_writeln('Paid         ' . local_payments_refundlog_money($transaction->amount, $transaction->currency)
        . '  on ' . userdate($transaction->timemodified ?: $transaction->timecreated));

    $user = $DB->get_record('user', ['id' => $transaction->userid]);
    if ($user) {
        cli_writeln('Buyer        ' . fullname($user) . ' <' . $user->email . '>  (id ' . $user->id . ')');
    }

    // What the policy says about it now. This is the number staff see, so when a
    // buyer disputes the amount, this is the line to read to them.
    try {
        $quote = \local_payments\refund_policy::quote($transaction);
        cli_writeln('Policy now   ' . $quote->hours . 'h window, fee ' . $quote->feepercent . '% ('
            . local_payments_refundlog_money($quote->fee, $quote->currency) . '), '
            . ($quote->fromitem ? 'set on the course or plan' : 'from the site policy'));
        cli_writeln('Would refund ' . local_payments_refundlog_money($quote->net, $quote->currency)
            . ($quote->deadline
                ? '  (window ' . ($quote->withinwindow ? 'open until ' : 'closed ') . userdate($quote->deadline) . ')'
                : '  (no self-service window)'));
    } catch (\Throwable $e) {
        cli_writeln('Policy now   could not be quoted: ' . $e->getMessage());
    }

    cli_writeln('');

    $requests = $DB->get_records('local_payments_refund_reqs',
        ['transaction_id' => $transaction->id], 'timecreated ASC');
    foreach ($requests as $request) {
        cli_writeln('--- request #' . $request->id . ' ---');
        cli_writeln('  asked    ' . userdate($request->timecreated));
        cli_writeln('  status   ' . $request->status);
        cli_writeln('  quoted   ' . local_payments_refundlog_money($request->quoted_amount, $request->currency)
            . ' less ' . local_payments_refundlog_money($request->quoted_fee, $request->currency));
        if ($request->reason !== null && trim($request->reason) !== '') {
            cli_writeln('  reason   ' . $request->reason);
        }
        if ($request->decided_by) {
            $staff = $DB->get_record('user', ['id' => $request->decided_by]);
            cli_writeln('  decided  ' . userdate($request->timemodified)
                . ' by ' . ($staff ? fullname($staff) : 'user ' . $request->decided_by));
        }
        if ($request->decision_note !== null && trim($request->decision_note) !== '') {
            cli_writeln('  note     ' . $request->decision_note);
        }
    }

    $refunds = $DB->get_records('local_payments_refunds',
        ['transaction_id' => $transaction->id], 'timecreated ASC');

    if (empty($refunds)) {
        cli_writeln('--- no refund was ever attempted ---');
    }

    foreach ($refunds as $refund) {
        cli_writeln('--- refund #' . $refund->id . ' (' . $refund->operation_type . ') ---');
        cli_writeln('  started  ' . userdate($refund->timecreated));
        cli_writeln('  status   ' . $refund->status);
        cli_writeln('  amount   ' . local_payments_refundlog_money($refund->amount, $refund->currency));
        cli_writeln('  sent as  ' . ($refund->provider_order_id ?: '-'));
        cli_writeln('  gateway  ' . ($refund->provider_refund_id ?: '-')
            . ($refund->gateway_code ? '  [' . $refund->gateway_code . ']' : ''));
        $staff = $DB->get_record('user', ['id' => $refund->initiated_by]);
        cli_writeln('  by       ' . ($staff ? fullname($staff) : 'user ' . $refund->initiated_by));
    }

    // The logs last, because they are where a failure explains itself — and a
    // refund that succeeded at the gateway but left access alive says so only
    // here.
    $logs = $DB->get_records('local_payments_logs',
        ['transaction_id' => $transaction->id], 'timecreated ASC');

    if ($logs) {
        cli_writeln('--- log ---');
        foreach ($logs as $log) {
            cli_writeln('  ' . userdate($log->timecreated) . '  [' . strtoupper($log->level) . ']  '
                . $log->message);
            if (!empty($log->context) && $log->context !== '[]') {
                cli_writeln('      ' . $log->context);
            }
        }
    }

    cli_writeln('');
}

if ($options['requests']) {
    $pending = $DB->get_records('local_payments_refund_reqs', ['status' => 'pending'],
        'timecreated ASC');

    if (empty($pending)) {
        cli_writeln('No refund requests are waiting on a decision.');
        exit(0);
    }

    cli_writeln(count($pending) . ' request(s) waiting. Decide them at /local/payments/refund_requests.php');
    foreach ($pending as $request) {
        $transaction = $DB->get_record('local_payments_transactions', ['id' => $request->transaction_id]);
        cli_writeln(str_repeat('-', 78));
        cli_writeln(userdate($request->timecreated) . '  ' . ($transaction->order_id ?? '?')
            . '  ' . local_payments_refundlog_money($request->quoted_amount, $request->currency)
            . ' less ' . local_payments_refundlog_money($request->quoted_fee, $request->currency));
        if ($request->reason !== null && trim($request->reason) !== '') {
            cli_writeln('  ' . $request->reason);
        }
    }
    exit(0);
}

if ($options['order'] !== '') {
    $transaction = $DB->get_record('local_payments_transactions',
        ['order_id' => trim($options['order'])]);

    if (!$transaction) {
        cli_error('No payment with order id ' . $options['order'] . '.');
    }

    local_payments_refundlog_detail($transaction);
    exit(0);
}

// No order given: the most recent refunds, so "a refund broke today" needs no
// order id to look into.
$limit = max(1, (int) $options['limit']);
$where = $options['failed'] ? "r.status <> 'completed'" : '1 = 1';

$rows = $DB->get_records_sql("
    SELECT r.id, r.status, r.amount, r.currency, r.gateway_code, r.timecreated,
           t.order_id, t.id AS transactionid
      FROM {local_payments_refunds} r
      JOIN {local_payments_transactions} t ON t.id = r.transaction_id
     WHERE {$where}
  ORDER BY r.timecreated DESC
", [], 0, $limit);

if (empty($rows)) {
    cli_writeln($options['failed'] ? 'No failed refunds.' : 'No refunds recorded yet.');
    cli_writeln('Waiting requests: --requests');
    exit(0);
}

cli_writeln(sprintf('%-20s  %-24s  %-10s  %-16s  %s',
    'WHEN', 'ORDER', 'STATUS', 'AMOUNT', 'GATEWAY'));
cli_writeln(str_repeat('-', 100));

foreach ($rows as $row) {
    cli_writeln(sprintf('%-20s  %-24s  %-10s  %-16s  %s',
        userdate($row->timecreated, '%Y-%m-%d %H:%M'),
        $row->order_id,
        $row->status,
        local_payments_refundlog_money($row->amount, $row->currency),
        $row->gateway_code ?: '-'));
}

cli_writeln('');
cli_writeln('Full history for one of them:  --order=<ORDER>');
