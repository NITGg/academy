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

namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * "My payments" - the list, its filters and what each row may do.
 *
 * `/local/payments/history.php` and `local_payments_get_transactions` show the
 * same list to the same person; this class is the single place that decides what
 * is in it. Before it existed the page held the filter parsing and the SQL, and
 * the web service held a plain "everything, newest first" query, so an app could
 * not offer the filters the web page offers and the two disagreed about what a
 * row was worth saying.
 *
 * Nothing here renders. The page keeps its HTML and the web service keeps its
 * `external_*` description; what they share is the query, the filter vocabulary
 * and the per-row facts a client cannot work out for itself - whether an invoice
 * can be printed, whether a refund can still be taken, and whether one has
 * already been asked for.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class history_api {

    /** @var int Rows per page, when the caller does not say. */
    const PERPAGE = 20;

    /** @var int The most rows one call may ask for, however large its perpage. */
    const MAXPERPAGE = 100;

    /**
     * The filter set, normalised, with everything unusable dropped.
     *
     * Takes the raw values as they arrive from a query string or a web-service
     * parameter and returns the five keys the rest of this class understands.
     * A malformed date is not an error: it is simply not a filter, exactly as the
     * web page has always treated it.
     *
     * @param array $raw q, status, courseid, datefrom, dateto - any of them absent
     * @return array{q: string, status: string, courseid: int, from: int, to: int}
     */
    public static function filters(array $raw): array {
        $status = trim((string) ($raw['status'] ?? ''));

        // A status the site does not have would silently return nothing at all,
        // which reads as "you have no payments" rather than as a bad filter.
        if ($status !== '' && !in_array($status, status_machine::all_statuses(), true)) {
            $status = '';
        }

        return [
            'q' => trim((string) ($raw['q'] ?? '')),
            'status' => $status,
            'courseid' => (int) ($raw['courseid'] ?? 0),
            'from' => self::daybound((string) ($raw['datefrom'] ?? ''), false),
            'to' => self::daybound((string) ($raw['dateto'] ?? ''), true),
        ];
    }

    /**
     * A YYYY-MM-DD box turned into the timestamp that bounds the day it names.
     *
     * make_timestamp() rather than strtotime(): the boundary has to fall at
     * midnight in the reader's timezone, or a payment made in the evening drops
     * out of a range that plainly includes its date.
     *
     * @param string $value the date as typed, or ''
     * @param bool $endofday true for the closing bound of the range
     * @return int unix time, or 0 when the value is not a date
     */
    public static function daybound(string $value, bool $endofday): int {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($value), $m)) {
            return 0;
        }

        return $endofday
            ? (int) make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], 23, 59, 59)
            : (int) make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], 0, 0, 0);
    }

    /**
     * The WHERE clause and its parameters for one account's filtered list.
     *
     * @param int $userid whose payments
     * @param array $filters as returned by {@see self::filters()}
     * @return array{0: string, 1: array} sql, params
     */
    protected static function where(int $userid, array $filters): array {
        global $DB;

        $where = ['t.userid = :userid'];
        $params = ['userid' => $userid];

        if ($filters['status'] !== '') {
            $where[] = 't.status = :status';
            $params['status'] = $filters['status'];
        }

        if ($filters['courseid'] > 0) {
            $where[] = 't.courseid = :fcourseid';
            $params['fcourseid'] = $filters['courseid'];
        }

        if ($filters['from'] > 0) {
            $where[] = 't.timecreated >= :fromts';
            $params['fromts'] = $filters['from'];
        }

        if ($filters['to'] > 0) {
            $where[] = 't.timecreated <= :tots';
            $params['tots'] = $filters['to'];
        }

        if ($filters['q'] !== '') {
            // The two references a student actually holds: what the site called
            // the order and what the invoice was numbered. The invoice number is
            // on the PDF they are looking at, so it has to be searchable even
            // though it lives in its own table - reached with EXISTS rather than
            // a join, so that nothing can list a payment twice or inflate the
            // count.
            $params['searchorder'] = '%' . $DB->sql_like_escape($filters['q']) . '%';
            $params['searchinv'] = '%' . $DB->sql_like_escape($filters['q']) . '%';
            $where[] = '(' . $DB->sql_like('t.order_id', ':searchorder', false)
                . ' OR EXISTS (SELECT 1 FROM {local_payments_invoices} i
                                WHERE i.transaction_id = t.id
                                  AND ' . $DB->sql_like('i.invoice_number', ':searchinv', false) . '))';
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * One page of the account's payments, newest first.
     *
     * @param int $userid whose payments
     * @param array $filters as returned by {@see self::filters()}
     * @param int $page zero-based page number
     * @param int $perpage rows per page
     * @return \stdClass[] transaction records, keyed by id
     */
    public static function fetch(int $userid, array $filters, int $page = 0, int $perpage = self::PERPAGE): array {
        global $DB;

        [$wheresql, $params] = self::where($userid, $filters);

        return $DB->get_records_sql(
            "SELECT t.* FROM {local_payments_transactions} t WHERE {$wheresql} ORDER BY t.timecreated DESC",
            $params, max(0, $page) * $perpage, $perpage);
    }

    /**
     * How many payments the filters match in total.
     *
     * Counted separately from {@see self::fetch()} because a paged client needs
     * to know how far the list goes before it has walked to the end of it.
     *
     * @param int $userid whose payments
     * @param array $filters as returned by {@see self::filters()}
     * @return int
     */
    public static function count(int $userid, array $filters): int {
        global $DB;

        [$wheresql, $params] = self::where($userid, $filters);

        return $DB->count_records_sql(
            "SELECT COUNT(1) FROM {local_payments_transactions} t WHERE {$wheresql}", $params);
    }

    /**
     * The courses this account has actually paid for, for the course filter.
     *
     * A filter listing every course on the site would be mostly dead options.
     *
     * @param int $userid whose payments
     * @return \stdClass[] id, fullname - ordered by name
     */
    public static function courses(int $userid): array {
        global $DB;

        return $DB->get_records_sql(
            "SELECT DISTINCT c.id, c.fullname
               FROM {local_payments_transactions} t
               JOIN {course} c ON c.id = t.courseid
              WHERE t.userid = :userid
           ORDER BY c.fullname", ['userid' => $userid]);
    }

    /**
     * Every status a payment can be in, with the label to show for it.
     *
     * @return array<string,string> machine value => translated label
     */
    public static function statuses(): array {
        $out = [];

        foreach (status_machine::all_statuses() as $value) {
            $out[$value] = get_string('status_' . $value, 'local_payments');
        }

        return $out;
    }

    /**
     * Whether this payment has an invoice worth downloading.
     *
     * @param \stdClass $txn a transaction record
     * @return bool
     */
    public static function has_invoice(\stdClass $txn): bool {
        return in_array($txn->status, [
            status_machine::COMPLETED,
            status_machine::REFUNDED,
            status_machine::PARTIALLY_REFUNDED,
        ], true);
    }

    /**
     * What refund, if any, this payment is still open to.
     *
     * Offered only on a completed payment, and only when nothing is already in
     * flight for it. `instant` is the difference between a refund the buyer can
     * simply take and one they have to ask for, and it is decided here rather
     * than on the refund screen so that the promise on the button is honest
     * before it is pressed.
     *
     * @param \stdClass $txn a transaction record
     * @return array{allowed: bool, pending: bool, instant: bool}
     */
    public static function refund_state(\stdClass $txn): array {
        if (!refund_policy::enabled() || $txn->status !== status_machine::COMPLETED) {
            return ['allowed' => false, 'pending' => false, 'instant' => false];
        }

        $pending = (bool) refund_manager::open_request((int) $txn->id);

        return [
            'allowed' => !$pending,
            'pending' => $pending,
            'instant' => refund_policy::quote($txn)->withinwindow,
        ];
    }

    /**
     * The invoice numbers for a set of transactions, in one query.
     *
     * @param \stdClass[] $transactions
     * @return array<int,string> transaction id => invoice number
     */
    public static function invoice_numbers(array $transactions): array {
        global $DB;

        $ids = array_map('intval', array_column($transactions, 'id'));
        if (!$ids) {
            return [];
        }

        $rows = $DB->get_records_list('local_payments_invoices', 'transaction_id', $ids,
            '', 'transaction_id, invoice_number');

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->transaction_id] = (string) $row->invoice_number;
        }

        return $out;
    }

    /**
     * A page of transactions as a client should receive them.
     *
     * The row a mobile app draws and the row the web page draws carry the same
     * facts; only the shape differs. Everything a client could not work out on
     * its own is decided here - the translated status, what was actually bought,
     * whether an invoice exists, and what the refund button should say.
     *
     * @param \stdClass[] $transactions as returned by {@see self::fetch()}
     * @return array[] one array per transaction, in the order given
     */
    public static function rows(array $transactions): array {
        global $DB;

        if (!$transactions) {
            return [];
        }

        // Batch-loaded rather than a query per row (N+1).
        $itemnames = item_name::for_many($transactions);
        $invoices = self::invoice_numbers($transactions);
        $providers = $DB->get_records_menu('local_payments_providers', null, '', 'id, display_name');

        $rows = [];

        foreach ($transactions as $txn) {
            $refund = self::refund_state($txn);
            $downloadable = self::has_invoice($txn);

            $rows[] = [
                'transaction_id' => (int) $txn->id,
                'order_id' => (string) $txn->order_id,
                'courseid' => (int) $txn->courseid,
                'item_type' => refund_policy::item_type($txn),
                'item_name' => (string) ($itemnames[(int) $txn->id] ?? ''),
                'amount' => (float) $txn->amount,
                'original_amount' => (float) ($txn->original_amount ?? $txn->amount),
                'currency' => (string) $txn->currency,
                'status' => (string) $txn->status,
                // The badge said "completed" in the middle of an Arabic page: the
                // status is a machine value and needs a translated label like
                // everything else on the row. Branch on `status`, show
                // `status_label`.
                'status_label' => get_string('status_' . $txn->status, 'local_payments'),
                'provider' => (string) ($providers[$txn->provider_id] ?? ''),
                'payment_method' => (string) ($txn->payment_method_type ?? ''),
                'invoice_number' => (string) ($invoices[(int) $txn->id] ?? ''),
                'timecreated' => (int) $txn->timecreated,
                'can_download_invoice' => $downloadable,
                'can_refund' => $refund['allowed'],
                'refund_pending' => $refund['pending'],
                'refund_instant' => $refund['instant'],
                'refund_label' => $refund['allowed']
                    ? get_string($refund['instant'] ? 'refund_now_button' : 'refund_ask_button', 'local_payments')
                    : '',
            ];
        }

        return $rows;
    }
}
