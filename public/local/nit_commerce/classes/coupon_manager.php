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
 * CRUD + rules for discount coupons. Ported from local_academy\coupon_manager (tables nit_coupon*).
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_commerce;

defined('MOODLE_INTERNAL') || die();

/**
 * Discount coupon manager.
 */
class coupon_manager {

    /** @var string Active status. */
    const STATUS_ACTIVE   = 'active';
    /** @var string Inactive status. */
    const STATUS_INACTIVE = 'inactive';

    /** @var string One-time usage. */
    const USAGE_ONCE     = 'once';
    /** @var string Multiple usage. */
    const USAGE_MULTIPLE = 'multiple';

    // ── CRUD ──

    /**
     * Create a coupon.
     *
     * @param array $data code, name, description, discount_type, discount_value, max_discount, usage_type, usage_limit,
     *                     startdate, enddate, active, items[]
     * @param int $userid admin
     * @return int new coupon id
     */
    public static function create_coupon(array $data, $userid) {
        global $DB;

        $code = self::normalize_code($data['code'] ?? '');
        if (self::code_exists($code)) {
            throw new \moodle_exception('err_couponcodetaken', 'local_nit_commerce');
        }
        $now = time();
        $record = new \stdClass();
        $record->code           = $code;
        $record->name           = self::normalize_name($data['name'] ?? '');
        $record->description    = self::normalize_description($data['description'] ?? '');
        $record->discount_type  = discount_manager::normalize_discount_type($data['discount_type'] ?? 'percent');
        $record->discount_value = self::validate_value($record->discount_type, $data['discount_value'] ?? 0);
        $record->max_discount   = self::validate_max($data['max_discount'] ?? null);
        $record->usage_type     = self::normalize_usage_type($data['usage_type'] ?? self::USAGE_MULTIPLE);
        $record->usage_limit    = max(0, (int)($data['usage_limit'] ?? 0));
        list($record->startdate, $record->enddate) = self::validate_dates($data['startdate'] ?? 0, $data['enddate'] ?? 0);
        $record->status         = !empty($data['active']) ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
        $record->timecreated    = $now;
        $record->timemodified   = $now;
        $record->usermodified   = $userid;

        $id = $DB->insert_record('nit_coupon', $record);
        self::save_items($id, (array)($data['items'] ?? array()));
        return $id;
    }

    /**
     * Update a coupon. Only provided fields change.
     *
     * @param int $id
     * @param array $data subset of the create fields (+ status). If 'items' is present it replaces scope.
     * @param int $userid admin
     * @return void
     */
    public static function update_coupon($id, array $data, $userid) {
        global $DB;
        $coupon = self::get_record($id);

        $update = new \stdClass();
        $update->id = $coupon->id;

        if (array_key_exists('code', $data)) {
            $code = self::normalize_code($data['code']);
            if (self::code_exists($code, $coupon->id)) {
                throw new \moodle_exception('err_couponcodetaken', 'local_nit_commerce');
            }
            $update->code = $code;
        }
        if (array_key_exists('name', $data)) {
            $update->name = self::normalize_name($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $update->description = self::normalize_description($data['description']);
        }
        if (array_key_exists('discount_type', $data)) {
            $update->discount_type = discount_manager::normalize_discount_type($data['discount_type']);
        }
        if (array_key_exists('discount_value', $data)) {
            $type = $update->discount_type ?? $coupon->discount_type;
            $update->discount_value = self::validate_value($type, $data['discount_value']);
        }
        if (array_key_exists('max_discount', $data)) {
            $update->max_discount = self::validate_max($data['max_discount']);
        }
        if (array_key_exists('usage_type', $data)) {
            $update->usage_type = self::normalize_usage_type($data['usage_type']);
        }
        if (array_key_exists('usage_limit', $data)) {
            $update->usage_limit = max(0, (int)$data['usage_limit']);
        }
        if (array_key_exists('startdate', $data) || array_key_exists('enddate', $data)) {
            $start = array_key_exists('startdate', $data) ? $data['startdate'] : $coupon->startdate;
            $end   = array_key_exists('enddate', $data) ? $data['enddate'] : $coupon->enddate;
            list($update->startdate, $update->enddate) = self::validate_dates($start, $end);
        }
        if (array_key_exists('status', $data)) {
            $update->status = self::normalize_status($data['status']);
        } else if (array_key_exists('active', $data)) {
            $update->status = !empty($data['active']) ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
        }

        $update->timemodified = time();
        $update->usermodified = $userid;
        $DB->update_record('nit_coupon', $update);

        if (array_key_exists('items', $data)) {
            self::save_items($coupon->id, (array)$data['items']);
        }
    }

    /**
     * Deactivate a coupon.
     *
     * @param int $id
     * @param int $userid
     * @return void
     */
    public static function deactivate_coupon($id, $userid) {
        self::set_status($id, self::STATUS_INACTIVE, $userid);
    }

    /**
     * Reactivate a coupon.
     *
     * @param int $id
     * @param int $userid
     * @return void
     */
    public static function activate_coupon($id, $userid) {
        self::set_status($id, self::STATUS_ACTIVE, $userid);
    }

    /**
     * Delete a coupon. Allowed only if it was never used.
     *
     * @param int $id
     * @return void
     */
    public static function delete_coupon($id) {
        global $DB;
        self::get_record($id);
        if (self::has_usages($id)) {
            throw new \moodle_exception('err_couponhasusages', 'local_nit_commerce');
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('nit_coupon_item', array('couponid' => $id));
        $DB->delete_records('nit_coupon', array('id' => $id));
        $transaction->allow_commit();
    }

    // ── Reads ──

    /**
     * Fetch one coupon (with scope + usage count) or throw.
     *
     * @param int $id
     * @return array
     */
    public static function get_coupon($id) {
        return self::format(self::get_record($id));
    }

    /**
     * List coupons for the admin UI, newest first.
     *
     * @param string $status '' | active | inactive
     * @return array
     */
    public static function get_coupons($status = '') {
        global $DB;
        $conditions = array();
        if ($status !== '') {
            $conditions['status'] = self::normalize_status($status);
        }
        $rows = array_values($DB->get_records('nit_coupon', $conditions, 'timecreated DESC'));
        return array_map(array(self::class, 'format'), $rows);
    }

    /**
     * The coupons a visitor may actually use, newest first (AC-4.7.10).
     *
     * Every test here is one the checkout would apply anyway — this list exists so the
     * catalogue never advertises a code that {@see discount_manager::validate_coupon()}
     * would then refuse. A coupon that fails any of them is left out of the list rather
     * than returned greyed out: there is nothing a visitor can do with it, and a wall of
     * dead cards is worse than a shorter wall of live ones.
     *
     * The tests: active; inside its start/end window; still has redemptions left, globally
     * and for this user; and, when it takes a fixed amount off, denominated in the currency
     * this visitor is quoted in (AC-4.6) — 50 EGP off means nothing to a buyer paying USD.
     *
     * @param int|null $userid whose redemption history to respect; defaults to the current user
     * @return array
     */
    public static function get_available_coupons($userid = null) {
        global $DB, $USER;

        $userid = $userid === null ? (int) $USER->id : (int) $userid;
        if ($userid > 0 && isguestuser($userid)) {
            $userid = 0;
        }

        $now = time();
        $currency = self::visitor_currency();
        $sitecurrency = self::default_currency();

        // One query for this user's history, rather than one per coupon.
        $usedbyuser = array();
        if ($userid > 0) {
            $usedbyuser = array_flip($DB->get_fieldset_select('nit_coupon_usage', 'DISTINCT couponid',
                'userid = :userid', array('userid' => $userid)));
        }

        $rows = array_values($DB->get_records('nit_coupon', array('status' => self::STATUS_ACTIVE), 'timecreated DESC'));
        $out = array();
        foreach ($rows as $r) {
            if ($r->startdate > 0 && $now < $r->startdate) { continue; }
            if ($r->enddate > 0 && $now > $r->enddate) { continue; }

            // Fixed amounts are stated in the site's currency; a visitor priced in another
            // one cannot spend them. Percentages travel, so they are never filtered here.
            if ($r->discount_type !== 'percent'
                    && $currency !== '' && $sitecurrency !== '' && $currency !== $sitecurrency) {
                continue;
            }

            // Redemptions left: a one-time coupon is spent after the first, a capped one
            // after its cap, and either is spent for this user once they have used it.
            $used = $DB->count_records('nit_coupon_usage', array('couponid' => $r->id));
            if ($r->usage_type === self::USAGE_ONCE && $used >= 1) { continue; }
            if ((int)$r->usage_limit > 0 && $used >= (int)$r->usage_limit) { continue; }
            if (isset($usedbyuser[$r->id])) { continue; }

            $out[] = self::format($r, $used);
        }
        return $out;
    }

    /**
     * The currency a fixed-amount coupon is written in. Coupons carry none of their own, so
     * it is the currency the site prices in — {@see ocal_paymentsprice_resolver::default_currency()},
     * which prefers what the default price rows actually say over the untouched admin setting.
     *
     * @return string ISO 4217 (uppercase), or '' when the site names none
     */
    public static function default_currency() {
        $currency = '';
        if (class_exists('\local_payments\price_resolver')
                && method_exists('\local_payments\price_resolver', 'default_currency')) {
            $currency = \local_payments\price_resolver::default_currency();
        }
        if ($currency === '') {
            $currency = (string) get_config('local_payments', 'default_currency');
        }
        if ($currency === '' || $currency === '0') {
            $currency = (string) get_string('co_currency', 'local_nit_commerce');
        }
        return strtoupper(trim($currency));
    }

    /**
     * The currency this visitor is quoted in, or '' when nothing can say.
     *
     * Delegated to local_payments, which owns the country → price → currency rules; the
     * guard is there because nit_commerce is installable without it, and a site with no
     * payments plugin has no per-country currency to disagree with.
     *
     * @return string ISO 4217 (uppercase), or ''
     */
    private static function visitor_currency() {
        if (!class_exists('\local_payments\price_resolver')
                || !method_exists('\local_payments\price_resolver', 'visitor_currency')) {
            return '';
        }
        return \local_payments\price_resolver::visitor_currency();
    }


    /**
     * Whether the coupon has any redemption records.
     *
     * @param int $id
     * @return bool
     */
    public static function has_usages($id) {
        global $DB;
        return $DB->record_exists('nit_coupon_usage', array('couponid' => $id));
    }

    // ── Reporting (AC-4.12.8) ──

    /**
     * The redemption log: one row per coupon actually spent, carrying the learner, the order, the
     * date and the amount discounted — the four things AC-4.12.8 requires be recorded, joined to
     * the names a human needs to read them.
     *
     * Rows in {@see nit_coupon_usage} double as reservations for checkouts that are still pending,
     * which is what makes the cap atomic. That is right for counting but wrong for a report: an
     * abandoned checkout would read as a redemption until the cleanup task swept it. So each row
     * is labelled with the state of the order that owns it, and the caller can ask for confirmed
     * redemptions only (the default), pending ones, or both.
     *
     * @param array $filters couponid, userid, itemtype, from, to (unix), state
     *                       (confirmed|pending|all), q (code / learner search)
     * @param int $limitfrom
     * @param int $limitnum 0 = no limit
     * @return array {rows: array, total: int, totals: array}
     */
    public static function get_redemptions(array $filters = array(), $limitfrom = 0, $limitnum = 0) {
        global $DB;

        $hastx = $DB->get_manager()->table_exists('local_payments_transactions');
        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

        // The transaction is the "order": it carries the reference the buyer and finance both
        // quote, the currency, and the status that says whether the money actually arrived.
        $txjoin = $hastx ? 'LEFT JOIN {local_payments_transactions} t ON t.id = cu.transactionid' : '';
        $txcols = $hastx
            ? 't.order_id, t.status AS txstatus, t.currency, t.amount AS txamount, t.timecreated AS txtime'
            : "'' AS order_id, '' AS txstatus, '' AS currency, 0 AS txamount, 0 AS txtime";

        $where = array('1 = 1');
        $params = array();

        if (!empty($filters['couponid'])) {
            $where[] = 'cu.couponid = :couponid';
            $params['couponid'] = (int) $filters['couponid'];
        }
        if (!empty($filters['userid'])) {
            $where[] = 'cu.userid = :userid';
            $params['userid'] = (int) $filters['userid'];
        }
        if (!empty($filters['itemtype'])) {
            $where[] = 'cu.item_type = :itemtype';
            $params['itemtype'] = (string) $filters['itemtype'];
        }
        if (!empty($filters['from'])) {
            $where[] = 'cu.timecreated >= :fromtime';
            $params['fromtime'] = (int) $filters['from'];
        }
        if (!empty($filters['to'])) {
            // Inclusive of the whole "to" day: the caller passes the day's end.
            $where[] = 'cu.timecreated <= :totime';
            $params['totime'] = (int) $filters['to'];
        }

        // Confirmed = the owning order was paid. A row with no transaction at all (a manual or
        // zero-value grant) counts as confirmed too: nothing is pending on it.
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
                $DB->sql_like('c.code', ':q1', false),
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
        $from = "FROM {nit_coupon_usage} cu
                 JOIN {nit_coupon} c ON c.id = cu.couponid
            LEFT JOIN {user} u ON u.id = cu.userid
                 {$txjoin}
                WHERE {$wheresql}";

        $total = (int) $DB->count_records_sql("SELECT COUNT(1) {$from}", $params);

        // Site-wide figures for the filtered set, so the page can show what the coupons cost
        // without paging through every row to add it up.
        $sums = $DB->get_record_sql(
            "SELECT COALESCE(SUM(cu.discount_amount), 0) AS discounted,
                    COALESCE(SUM(cu.original_amount), 0) AS gross,
                    COALESCE(SUM(cu.final_amount), 0) AS net,
                    COUNT(DISTINCT cu.userid) AS learners
               {$from}", $params);

        $sql = "SELECT cu.id, cu.couponid, cu.userid, cu.transactionid, cu.item_type, cu.item_id,
                       cu.original_amount, cu.discount_amount, cu.final_amount, cu.timecreated,
                       c.code, c.name AS couponname, c.discount_type, c.discount_value,
                       u.email, {$userfields}, {$txcols}
                {$from}
             ORDER BY cu.timecreated DESC, cu.id DESC";

        $records = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);

        $rows = array();
        foreach ($records as $r) {
            $status = $hastx ? (string) $r->txstatus : '';
            $rows[] = array(
                'id'              => (int) $r->id,
                'couponid'        => (int) $r->couponid,
                'code'            => (string) $r->code,
                'coupon_name'     => format_string(discount_manager::resolve_mlang((string) $r->couponname)),
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
                'currency'        => (string) ($r->currency ?? '') ?: self::default_currency(),
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
                'redemptions' => $total,
                'learners'    => (int) ($sums->learners ?? 0),
                'gross'       => round((float) ($sums->gross ?? 0), 2),
                'discounted'  => round((float) ($sums->discounted ?? 0), 2),
                'net'         => round((float) ($sums->net ?? 0), 2),
                'currency'    => self::default_currency(),
            ),
        );
    }

    /**
     * Per-coupon totals for the report's summary table: how many times each coupon was spent,
     * by how many learners, and what it cost in discount.
     *
     * @param array $filters same shape as {@see self::get_redemptions()} (couponid/from/to/state)
     * @return array
     */
    public static function get_redemption_summary(array $filters = array()) {
        // Reuse the row reader so the two views can never disagree about what "confirmed" means.
        $data = self::get_redemptions($filters, 0, 0);
        $bycoupon = array();
        foreach ($data['rows'] as $row) {
            $id = $row['couponid'];
            if (!isset($bycoupon[$id])) {
                $bycoupon[$id] = array(
                    'couponid'    => $id,
                    'code'        => $row['code'],
                    'coupon_name' => $row['coupon_name'],
                    'redemptions' => 0,
                    'gross'       => 0.0,
                    'discounted'  => 0.0,
                    'net'         => 0.0,
                    'learners'    => array(),
                    'last'        => 0,
                );
            }
            $bycoupon[$id]['redemptions']++;
            $bycoupon[$id]['gross']      += $row['original_amount'];
            $bycoupon[$id]['discounted'] += $row['discount_amount'];
            $bycoupon[$id]['net']        += $row['final_amount'];
            $bycoupon[$id]['learners'][$row['userid']] = true;
            $bycoupon[$id]['last'] = max($bycoupon[$id]['last'], $row['timecreated']);
        }
        $out = array();
        foreach ($bycoupon as $entry) {
            $entry['learners']   = count($entry['learners']);
            $entry['gross']      = round($entry['gross'], 2);
            $entry['discounted'] = round($entry['discounted'], 2);
            $entry['net']        = round($entry['net'], 2);
            $entry['last_date']  = $entry['last'] ? userdate($entry['last'], get_string('strftimedatetimeshort')) : '';
            $out[] = $entry;
        }
        // Biggest spend first — the coupon that cost the most is the one worth looking at.
        usort($out, function ($a, $b) {
            return $b['discounted'] <=> $a['discounted'];
        });
        return $out;
    }

    // ── Helpers ──

    /**
     * Fetch a coupon record or throw.
     *
     * @param int $id
     * @return \stdClass
     */
    private static function get_record($id) {
        global $DB;
        $coupon = $DB->get_record('nit_coupon', array('id' => $id));
        if (!$coupon) {
            throw new \moodle_exception('err_couponnotfound', 'local_nit_commerce');
        }
        return $coupon;
    }

    /**
     * Shape a record for the API: cast types + attach scope items and usage count.
     *
     * @param \stdClass $record
     * @param int|null $used redemption count, when the caller has already counted it
     * @return array
     */
    private static function format($record, $used = null) {
        global $DB;
        $items = array_values($DB->get_records('nit_coupon_item', array('couponid' => $record->id)));
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
            'code'           => $record->code,
            // 'name' is localised for display; 'name_raw' keeps the stored {mlang} markup for editing.
            'name'           => format_string(discount_manager::resolve_mlang((string)$record->name)),
            'name_raw'       => (string)$record->name,
            'description'    => format_string(discount_manager::resolve_mlang((string)$record->description)),
            'description_raw' => (string)$record->description,
            'discount_type'  => $record->discount_type,
            'discount_value' => (float)$record->discount_value,
            'max_discount'   => $record->max_discount === null ? null : (float)$record->max_discount,
            'usage_type'     => $record->usage_type,
            'usage_limit'    => (int)$record->usage_limit,
            'startdate'      => (int)$record->startdate,
            'enddate'        => (int)$record->enddate,
            'status'         => $record->status,
            'usage_count'    => $used === null
                ? $DB->count_records('nit_coupon_usage', array('couponid' => $record->id))
                : (int)$used,
            // Coupons carry no currency of their own: a fixed amount is stated in the site's,
            // and a percentage is stated in none, so it reports none.
            'currency'       => $record->discount_type === 'percent' ? '' : self::default_currency(),
            'applies_to'     => $applies,
        );
    }

    /**
     * Replace the coupon's applicable-types + scope rows.
     *
     * @param int $couponid
     * @param array $items each ['item_type'=>, 'item_id'=>]
     * @return void
     */
    public static function save_items($couponid, array $items) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('nit_coupon_item', array('couponid' => $couponid));
        $seen = array();
        foreach ($items as $it) {
            $type = discount_manager::normalize_item_type($it['item_type'] ?? '');
            $iid  = max(0, (int)($it['item_id'] ?? 0));
            $key  = $type . ':' . $iid;
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $DB->insert_record('nit_coupon_item', (object) array(
                'couponid'  => $couponid,
                'item_type' => $type,
                'item_id'   => $iid,
            ));
        }
        $transaction->allow_commit();
    }

    /**
     * Normalize a display name (trim). Optional - an empty name is stored as an empty string.
     *
     * @param string $name may carry {mlang} markup
     * @return string
     */
    private static function normalize_name($name) {
        return \core_text::substr(trim((string)$name), 0, 255);
    }

    /**
     * Normalize a display description (trim). Optional; stored as plain text with {mlang} markup.
     *
     * @param string $description
     * @return string
     */
    private static function normalize_description($description) {
        return trim((string)$description);
    }

    /**
     * Normalize a code (trim). Empty is rejected.
     *
     * @param string $code
     * @return string
     */
    private static function normalize_code($code) {
        $code = trim((string)$code);
        if ($code === '') {
            throw new \moodle_exception('err_couponcoderequired', 'local_nit_commerce');
        }
        return $code;
    }

    /**
     * Whether a code already exists (case-insensitive), optionally excluding one id.
     *
     * @param string $code
     * @param int $excludeid
     * @return bool
     */
    private static function code_exists($code, $excludeid = 0) {
        global $DB;
        $params = array('code' => $code);
        $where = $DB->sql_equal('code', ':code', false);
        if ($excludeid > 0) {
            $where .= ' AND id <> :xid';
            $params['xid'] = $excludeid;
        }
        return $DB->record_exists_select('nit_coupon', $where, $params);
    }

    /**
     * Validate a discount value (percent must be 0..100; fixed must be >= 0).
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
     * Validate an optional max-discount cap (>= 0 or null).
     *
     * @param mixed $max
     * @return float|null
     */
    private static function validate_max($max) {
        if ($max === null || $max === '') {
            return null;
        }
        $max = (float)$max;
        if ($max < 0) {
            throw new \moodle_exception('err_maxdiscount', 'local_nit_commerce');
        }
        return $max > 0 ? $max : null;
    }

    /**
     * Validate a start/end window (end must not precede start when both set).
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
     * Validate a usage type.
     *
     * @param string $type
     * @return string
     */
    private static function normalize_usage_type($type) {
        $type = strtolower(trim((string)$type));
        if (!in_array($type, array(self::USAGE_ONCE, self::USAGE_MULTIPLE), true)) {
            throw new \moodle_exception('err_usagetype', 'local_nit_commerce');
        }
        return $type;
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
     * Set a coupon status (shared by activate/deactivate).
     *
     * @param int $id
     * @param string $status
     * @param int $userid
     * @return void
     */
    private static function set_status($id, $status, $userid) {
        global $DB;
        self::get_record($id);
        $DB->update_record('nit_coupon', (object) array(
            'id'           => $id,
            'status'       => $status,
            'timemodified' => time(),
            'usermodified' => $userid,
        ));
    }
}
