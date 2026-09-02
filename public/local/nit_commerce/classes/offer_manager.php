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
 * CRUD + rules for automatic offers. Ported from local_academy\offer_manager (tables nit_offer*).
 *
 * Offers carry no code and no max cap — they apply automatically at checkout.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_commerce;

defined('MOODLE_INTERNAL') || die();

/**
 * Automatic offer manager.
 */
class offer_manager {

    /** @var string Active status. */
    const STATUS_ACTIVE   = 'active';
    /** @var string Inactive status. */
    const STATUS_INACTIVE = 'inactive';

    // ── CRUD ──

    /**
     * Create an offer.
     *
     * @param array $data name, description, discount_type, discount_value, startdate, enddate, active, items[]
     * @param int $userid admin
     * @return int new offer id
     */
    public static function create_offer(array $data, $userid) {
        global $DB;
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw new \moodle_exception('err_offernamerequired', 'local_nit_commerce');
        }
        $now = time();
        $record = new \stdClass();
        $record->name           = $name;
        $record->description    = trim((string)($data['description'] ?? ''));
        $record->discount_type  = discount_manager::normalize_discount_type($data['discount_type'] ?? 'percent');
        $record->discount_value = self::validate_value($record->discount_type, $data['discount_value'] ?? 0);
        list($record->startdate, $record->enddate) = self::validate_dates($data['startdate'] ?? 0, $data['enddate'] ?? 0);
        $record->status         = !empty($data['active']) ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
        $record->timecreated    = $now;
        $record->timemodified   = $now;
        $record->usermodified   = $userid;

        $id = $DB->insert_record('nit_offer', $record);
        self::save_items($id, (array)($data['items'] ?? array()));
        return $id;
    }

    /**
     * Update an offer. Only provided fields change.
     *
     * @param int $id
     * @param array $data subset of create fields (+ status). If 'items' present it replaces scope.
     * @param int $userid admin
     * @return void
     */
    public static function update_offer($id, array $data, $userid) {
        global $DB;
        $offer = self::get_record($id);

        $update = new \stdClass();
        $update->id = $offer->id;

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if ($name === '') {
                throw new \moodle_exception('err_offernamerequired', 'local_nit_commerce');
            }
            $update->name = $name;
        }
        if (array_key_exists('description', $data)) {
            $update->description = trim((string)$data['description']);
        }
        if (array_key_exists('discount_type', $data)) {
            $update->discount_type = discount_manager::normalize_discount_type($data['discount_type']);
        }
        if (array_key_exists('discount_value', $data)) {
            $type = $update->discount_type ?? $offer->discount_type;
            $update->discount_value = self::validate_value($type, $data['discount_value']);
        }
        if (array_key_exists('startdate', $data) || array_key_exists('enddate', $data)) {
            $start = array_key_exists('startdate', $data) ? $data['startdate'] : $offer->startdate;
            $end   = array_key_exists('enddate', $data) ? $data['enddate'] : $offer->enddate;
            list($update->startdate, $update->enddate) = self::validate_dates($start, $end);
        }
        if (array_key_exists('status', $data)) {
            $update->status = self::normalize_status($data['status']);
        } else if (array_key_exists('active', $data)) {
            $update->status = !empty($data['active']) ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
        }

        $update->timemodified = time();
        $update->usermodified = $userid;
        $DB->update_record('nit_offer', $update);

        if (array_key_exists('items', $data)) {
            self::save_items($offer->id, (array)$data['items']);
        }
    }

    /**
     * Deactivate an offer.
     *
     * @param int $id
     * @param int $userid
     * @return void
     */
    public static function deactivate_offer($id, $userid) {
        self::set_status($id, self::STATUS_INACTIVE, $userid);
    }

    /**
     * Reactivate an offer.
     *
     * @param int $id
     * @param int $userid
     * @return void
     */
    public static function activate_offer($id, $userid) {
        self::set_status($id, self::STATUS_ACTIVE, $userid);
    }

    /**
     * Delete an offer. Allowed only if it was never used.
     *
     * @param int $id
     * @return void
     */
    public static function delete_offer($id) {
        global $DB;
        self::get_record($id);
        if (self::has_usages($id)) {
            throw new \moodle_exception('err_offerhasusages', 'local_nit_commerce');
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('nit_offer_item', array('offerid' => $id));
        $DB->delete_records('nit_offer', array('id' => $id));
        $transaction->allow_commit();
    }

    // ── Reads ──

    /**
     * Fetch one offer (with scope + usage count) or throw.
     *
     * @param int $id
     * @return array
     */
    public static function get_offer($id) {
        return self::format(self::get_record($id));
    }

    /**
     * List offers for the admin UI, newest first.
     *
     * @param string $status '' | active | inactive
     * @return array
     */
    public static function get_offers($status = '') {
        global $DB;
        $conditions = array();
        if ($status !== '') {
            $conditions['status'] = self::normalize_status($status);
        }
        $rows = array_values($DB->get_records('nit_offer', $conditions, 'timecreated DESC'));
        return array_map(array(self::class, 'format'), $rows);
    }

    /**
     * Active, in-window offers. Each row includes its scope labels.
     *
     * @return array
     */
    public static function get_available_offers() {
        global $DB;
        $now = time();
        $rows = array_values($DB->get_records('nit_offer', array('status' => self::STATUS_ACTIVE), 'timecreated DESC'));
        $out = array();
        foreach ($rows as $r) {
            if ($r->startdate > 0 && $now < $r->startdate) { continue; }
            if ($r->enddate > 0 && $now > $r->enddate) { continue; }
            $out[] = self::format($r);
        }
        return $out;
    }

    /**
     * Whether the offer has any application records.
     *
     * @param int $id
     * @return bool
     */
    public static function has_usages($id) {
        global $DB;
        return $DB->record_exists('nit_offer_usage', array('offerid' => $id));
    }

    // ── Reporting (AC-4.13.7) ──

    /**
     * The orders an offer was applied to, one row each, with the names a human needs to read them.
     *
     * Rows in {@see nit_offer_usage} are written at checkout, BEFORE the payment is known to have
     * succeeded — {@see discount_manager::reserve_usage()} writes them alongside the pending
     * transaction and {@see discount_manager::release_usage()} removes them if it is abandoned. So
     * a raw row count is not "how many times this offer was used": it also counts checkouts that
     * were opened and walked away from, until the cleanup task sweeps them. Each row is therefore
     * labelled with the state of the order that owns it, and the caller asks for confirmed
     * applications only (the default), unpaid ones, or both. A row with no transaction behind it
     * at all (a manual or zero-value grant) counts as confirmed: nothing is pending on it.
     *
     * @param array $filters offerid, userid, itemtype, from, to (unix), state
     *                       (confirmed|pending|all), q (offer name / learner / order search)
     * @param int $limitfrom
     * @param int $limitnum 0 = no limit
     * @return array {rows: array, total: int, totals: array}
     */
    public static function get_usages(array $filters = array(), $limitfrom = 0, $limitnum = 0) {
        global $DB;

        $hastx = $DB->get_manager()->table_exists('local_payments_transactions');
        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

        // The transaction is the "order": it carries the reference the buyer and finance both
        // quote, the currency, and the status that says whether the money actually arrived.
        $txjoin = $hastx ? 'LEFT JOIN {local_payments_transactions} t ON t.id = ou.transactionid' : '';
        $txcols = $hastx
            ? 't.order_id, t.status AS txstatus, t.currency, t.amount AS txamount, t.timecreated AS txtime'
            : "'' AS order_id, '' AS txstatus, '' AS currency, 0 AS txamount, 0 AS txtime";

        $where = array('1 = 1');
        $params = array();

        if (!empty($filters['offerid'])) {
            $where[] = 'ou.offerid = :offerid';
            $params['offerid'] = (int) $filters['offerid'];
        }
        if (!empty($filters['userid'])) {
            $where[] = 'ou.userid = :userid';
            $params['userid'] = (int) $filters['userid'];
        }
        if (!empty($filters['itemtype'])) {
            $where[] = 'ou.item_type = :itemtype';
            $params['itemtype'] = (string) $filters['itemtype'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'ou.timecreated >= :fromtime';
            $params['fromtime'] = (int) $filters['from'];
        }
        if (!empty($filters['to'])) {
            // Inclusive of the whole "to" day: the caller passes the day's end.
            $where[] = 'ou.timecreated <= :totime';
            $params['totime'] = (int) $filters['to'];
        }

        $state = (string) ($filters['state'] ?? 'confirmed');
        if ($hastx && $state !== 'all') {
            if ($state === 'pending') {
                $where[] = "(t.id IS NOT NULL AND t.status <> 'completed')";
            } else {
                $where[] = "(t.id IS NULL OR t.status = 'completed')";
            }
        }

        if (!empty($filters['q'])) {
            $q = '%' . $DB->sql_like_escape(trim((string) $filters['q'])) . '%';
            $like = array(
                $DB->sql_like('o.name', ':q1', false),
                $DB->sql_like('u.firstname', ':q2', false),
                $DB->sql_like('u.lastname', ':q3', false),
                $DB->sql_like('u.email', ':q4', false),
            );
            if ($hastx) {
                $like[] = $DB->sql_like('t.order_id', ':q5', false);
                $params['q5'] = $q;
            }
            $where[] = '(' . implode(' OR ', $like) . ')';
            $params['q1'] = $params['q2'] = $params['q3'] = $params['q4'] = $q;
        }

        $wheresql = implode(' AND ', $where);
        $from = "FROM {nit_offer_usage} ou
                 JOIN {nit_offer} o ON o.id = ou.offerid
            LEFT JOIN {user} u ON u.id = ou.userid
                 {$txjoin}
                WHERE {$wheresql}";

        $total = (int) $DB->count_records_sql("SELECT COUNT(1) {$from}", $params);

        // Site-wide figures for the filtered set, so the page can show what the offers cost
        // without paging through every row to add it up.
        $sums = $DB->get_record_sql(
            "SELECT COALESCE(SUM(ou.discount_amount), 0) AS discounted,
                    COALESCE(SUM(ou.original_amount), 0) AS gross,
                    COALESCE(SUM(ou.final_amount), 0) AS net,
                    COUNT(DISTINCT ou.userid) AS learners
               {$from}", $params);

        $sql = "SELECT ou.id, ou.offerid, ou.userid, ou.transactionid, ou.item_type, ou.item_id,
                       ou.original_amount, ou.discount_amount, ou.final_amount, ou.timecreated,
                       o.name AS offername, o.discount_type, o.discount_value, o.status AS offerstatus,
                       o.startdate, o.enddate,
                       u.email, {$userfields}, {$txcols}
                {$from}
             ORDER BY ou.timecreated DESC, ou.id DESC";

        $records = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);

        $rows = array();
        foreach ($records as $r) {
            $status = $hastx ? (string) $r->txstatus : '';
            $rows[] = array(
                'id'              => (int) $r->id,
                'offerid'         => (int) $r->offerid,
                'offer_name'      => format_string(discount_manager::resolve_mlang((string) $r->offername)),
                'discount_type'   => (string) $r->discount_type,
                'discount_value'  => (float) $r->discount_value,
                'userid'          => (int) $r->userid,
                'learner'         => $r->userid ? fullname($r) : '',
                'email'           => (string) ($r->email ?? ''),
                'transactionid'   => (int) $r->transactionid,
                'order_id'        => (string) ($r->order_id ?? ''),
                // '' when there is no order behind the row at all; the UI reads that as
                // "recorded, nothing pending".
                'order_status'    => $status,
                'confirmed'       => ($status === '' || $status === 'completed'),
                'currency'        => (string) ($r->currency ?? '') ?: coupon_manager::default_currency(),
                'item_type'       => (string) $r->item_type,
                'item_id'         => (int) $r->item_id,
                'item_label'      => discount_manager::item_label((string) $r->item_type, (int) $r->item_id),
                'original_amount' => round((float) $r->original_amount, 2),
                'discount_amount' => round((float) $r->discount_amount, 2),
                'final_amount'    => round((float) $r->final_amount, 2),
                'timecreated'     => (int) $r->timecreated,
                'date'            => userdate((int) $r->timecreated, get_string('strftimedatetimeshort')),
            );
        }

        return array(
            'rows'   => $rows,
            'total'  => $total,
            'totals' => array(
                'usages'     => $total,
                'learners'   => (int) ($sums->learners ?? 0),
                'gross'      => round((float) ($sums->gross ?? 0), 2),
                'discounted' => round((float) ($sums->discounted ?? 0), 2),
                'net'        => round((float) ($sums->net ?? 0), 2),
                'currency'   => coupon_manager::default_currency(),
            ),
        );
    }

    /**
     * Per-offer totals for the report's summary table: how many orders each offer was applied to,
     * for how many learners, and what it gave away (AC-4.13.7).
     *
     * Every live offer appears, including ones that have never been used — "this campaign sold
     * nothing" is the answer the admin most needs and the one a join over the usage log alone
     * would hide. An offer with no rows shows zeros, which is the truth about it.
     *
     * @param array $filters same shape as {@see self::get_usages()}
     * @return array
     */
    public static function get_usage_summary(array $filters = array()) {
        global $DB;

        // Reuse the row reader so the two views can never disagree about what "confirmed" means.
        $data = self::get_usages($filters, 0, 0);

        $byoffer = array();
        // Seed with the offers themselves so a zero-usage offer is a row rather than an absence.
        // Narrowed to one offer when the filter names one; not seeded at all when the filter is on
        // learner/item/date, where "every offer" would pad the table with rows the filter excluded.
        $seed = empty($filters['userid']) && empty($filters['itemtype'])
            && empty($filters['from']) && empty($filters['to']) && empty($filters['q']);
        if ($seed) {
            $conditions = !empty($filters['offerid']) ? array('id' => (int) $filters['offerid']) : array();
            foreach ($DB->get_records('nit_offer', $conditions, 'timecreated DESC') as $offer) {
                $byoffer[(int) $offer->id] = self::empty_summary_row($offer);
            }
        }

        foreach ($data['rows'] as $row) {
            $id = $row['offerid'];
            if (!isset($byoffer[$id])) {
                $offer = $DB->get_record('nit_offer', array('id' => $id));
                $byoffer[$id] = $offer ? self::empty_summary_row($offer) : self::empty_summary_row((object) array(
                    'id' => $id, 'name' => $row['offer_name'], 'discount_type' => $row['discount_type'],
                    'discount_value' => $row['discount_value'], 'status' => '', 'startdate' => 0, 'enddate' => 0,
                ));
            }
            $byoffer[$id]['usages']++;
            $byoffer[$id]['gross']      += $row['original_amount'];
            $byoffer[$id]['discounted'] += $row['discount_amount'];
            $byoffer[$id]['net']        += $row['final_amount'];
            $byoffer[$id]['learnerids'][$row['userid']] = true;
            $byoffer[$id]['last'] = max($byoffer[$id]['last'], $row['timecreated']);
        }

        $out = array();
        foreach ($byoffer as $entry) {
            $entry['learners']   = count($entry['learnerids']);
            unset($entry['learnerids']);
            $entry['gross']      = round($entry['gross'], 2);
            $entry['discounted'] = round($entry['discounted'], 2);
            $entry['net']        = round($entry['net'], 2);
            $entry['last_date']  = $entry['last'] ? userdate($entry['last'], get_string('strftimedatetimeshort')) : '';
            $out[] = $entry;
        }

        // Biggest give-away first, then by name so unused offers keep a stable order rather than
        // shuffling on every load.
        usort($out, function ($a, $b) {
            $cmp = $b['discounted'] <=> $a['discounted'];
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = $b['usages'] <=> $a['usages'];
            return $cmp !== 0 ? $cmp : strcasecmp($a['offer_name'], $b['offer_name']);
        });
        return $out;
    }

    /**
     * A summary row for an offer with nothing counted into it yet.
     *
     * @param \stdClass $offer
     * @return array
     */
    private static function empty_summary_row($offer) {
        return array(
            'offerid'        => (int) $offer->id,
            'offer_name'     => format_string(discount_manager::resolve_mlang((string) $offer->name)),
            'discount_type'  => (string) ($offer->discount_type ?? ''),
            'discount_value' => (float) ($offer->discount_value ?? 0),
            'status'         => (string) ($offer->status ?? ''),
            'startdate'      => (int) ($offer->startdate ?? 0),
            'enddate'        => (int) ($offer->enddate ?? 0),
            'usages'         => 0,
            'gross'          => 0.0,
            'discounted'     => 0.0,
            'net'            => 0.0,
            'learnerids'     => array(),
            'last'           => 0,
        );
    }

    // ── Helpers ──

    /**
     * Fetch an offer record or throw.
     *
     * @param int $id
     * @return \stdClass
     */
    private static function get_record($id) {
        global $DB;
        $offer = $DB->get_record('nit_offer', array('id' => $id));
        if (!$offer) {
            throw new \moodle_exception('err_offernotfound', 'local_nit_commerce');
        }
        return $offer;
    }

    /**
     * Shape a record for the API: cast types + attach scope items and usage count.
     *
     * @param \stdClass $record
     * @return array
     */
    private static function format($record) {
        global $DB;
        $items = array_values($DB->get_records('nit_offer_item', array('offerid' => $record->id)));
        $applies = array();
        foreach ($items as $it) {
            $applies[] = array(
                'item_type' => $it->item_type,
                'item_id'   => (int)$it->item_id,
                'label'     => discount_manager::item_label($it->item_type, $it->item_id),
            );
        }
        return array(
            'id'             => (int)$record->id,
            // 'name' is localised for display; 'name_raw' keeps the stored {mlang} markup for editing.
            'name'           => format_string(discount_manager::resolve_mlang($record->name)),
            'name_raw'       => $record->name,
            'description'    => format_string(discount_manager::resolve_mlang((string)$record->description)),
            'description_raw' => (string)$record->description,
            'discount_type'  => $record->discount_type,
            'discount_value' => (float)$record->discount_value,
            'startdate'      => (int)$record->startdate,
            'enddate'        => (int)$record->enddate,
            'status'         => $record->status,
            // AC-4.13.7: the number of times the offer was actually used. Rows tied to an order
            // that has not been paid are reservations, not uses, so they are counted separately
            // rather than folded in — see {@see self::get_usages()} for why they exist at all.
            // usage_count keeps its old meaning (every row) so nothing that already reads it,
            // delete_offer's guard included, changes behaviour.
            'usage_count'    => $DB->count_records('nit_offer_usage', array('offerid' => $record->id)),
            'usage_paid'     => self::count_usages($record->id, true),
            'usage_held'     => self::count_usages($record->id, false),
            'applies_to'     => $applies,
        );
    }

    /**
     * Count an offer's applications, split by whether the order behind them was paid.
     *
     * A usage row with no transaction at all counts as paid: there is no order left pending on it.
     * With no payments plugin installed there are no orders to check, so every row counts as paid
     * and the held count is zero.
     *
     * @param int $offerid
     * @param bool $paid true for confirmed applications, false for ones still held by an unpaid order
     * @return int
     */
    private static function count_usages($offerid, $paid) {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_payments_transactions')) {
            return $paid ? $DB->count_records('nit_offer_usage', array('offerid' => $offerid)) : 0;
        }
        $condition = $paid ? "(t.id IS NULL OR t.status = 'completed')" : "(t.id IS NOT NULL AND t.status <> 'completed')";
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {nit_offer_usage} ou
          LEFT JOIN {local_payments_transactions} t ON t.id = ou.transactionid
              WHERE ou.offerid = :offerid AND {$condition}",
            array('offerid' => (int) $offerid));
    }

    /**
     * Replace the offer's applicable-types + scope rows.
     *
     * @param int $offerid
     * @param array $items
     * @return void
     */
    public static function save_items($offerid, array $items) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('nit_offer_item', array('offerid' => $offerid));
        $seen = array();
        foreach ($items as $it) {
            $type = discount_manager::normalize_item_type($it['item_type'] ?? '');
            $iid  = max(0, (int)($it['item_id'] ?? 0));
            $key  = $type . ':' . $iid;
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $DB->insert_record('nit_offer_item', (object) array(
                'offerid'   => $offerid,
                'item_type' => $type,
                'item_id'   => $iid,
            ));
        }
        $transaction->allow_commit();
    }

    /**
     * Validate a discount value (percent 0..100; fixed >= 0).
     *
     * @param string $type
     * @param mixed $value
     * @return float
     */
    private static function validate_value($type, $value) {
        $value = (float)$value;
        if ($value < 0) {
            throw new \moodle_exception('err_discountvalue', 'local_nit_commerce');
        }
        if ($type === discount_manager::DISCOUNT_PERCENT && $value > 100) {
            throw new \moodle_exception('err_discountpercent', 'local_nit_commerce');
        }
        return $value;
    }

    /**
     * Validate a start/end window.
     *
     * @param mixed $start
     * @param mixed $end
     * @return array [start, end]
     */
    private static function validate_dates($start, $end) {
        $start = max(0, (int)$start);
        $end = max(0, (int)$end);
        if ($start > 0 && $end > 0 && $end < $start) {
            throw new \moodle_exception('err_daterange', 'local_nit_commerce');
        }
        return array($start, $end);
    }

    /**
     * Validate a status.
     *
     * @param string $status
     * @return string
     */
    private static function normalize_status($status) {
        $status = strtolower(trim((string)$status));
        if (!in_array($status, array(self::STATUS_ACTIVE, self::STATUS_INACTIVE), true)) {
            throw new \moodle_exception('err_status', 'local_nit_commerce');
        }
        return $status;
    }

    /**
     * Set an offer status.
     *
     * @param int $id
     * @param string $status
     * @param int $userid
     * @return void
     */
    private static function set_status($id, $status, $userid) {
        global $DB;
        self::get_record($id);
        $DB->update_record('nit_offer', (object) array(
            'id'           => $id,
            'status'       => $status,
            'timemodified' => time(),
            'usermodified' => $userid,
        ));
    }
}
