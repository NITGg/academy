<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD + rules for discount coupons (US-AD-7-1 create, US-AD-7-2 update, US-AD-7-3 deactivate/delete)
 * and the student-facing reads (US-US-CP-1-1 available, US-US-CP-1-3 usage history).
 *
 * Mirrors {@see subscription_manager}. Discount maths + checkout resolution live in
 * {@see discount_manager}. See docs/specs/admin/US-AD-7-* and docs/specs/student/US-US-CP-*.
 */
class coupon_manager {

    const STATUS_ACTIVE   = 'active';
    const STATUS_INACTIVE = 'inactive';

    const USAGE_ONCE     = 'once';
    const USAGE_MULTIPLE = 'multiple';

    // ──────────────────────────────────────────────────────────────────────────
    // CRUD
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create a coupon (US-AD-7-1).
     *
     * @param array $data code, discount_type, discount_value, max_discount, usage_type, usage_limit,
     *                     startdate, enddate, active, items[]
     * @param int $userid admin
     * @return int new coupon id
     */
    public static function create_coupon(array $data, $userid) {
        global $DB;

        $code = self::normalize_code($data['code'] ?? '');
        if (self::code_exists($code)) {
            throw new \moodle_exception('err_couponcodetaken', 'local_academy');
        }
        $now = time();
        $record = new \stdClass();
        $record->code           = $code;
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

        $id = $DB->insert_record('academy_coupons', $record);
        self::save_items($id, (array)($data['items'] ?? array()));
        return $id;
    }

    /**
     * Update a coupon (US-AD-7-2). Only provided fields change. Changes apply to future usage.
     *
     * @param int $id
     * @param array $data subset of the create fields (+ status). If 'items' is present it replaces scope.
     * @param int $userid admin
     */
    public static function update_coupon($id, array $data, $userid) {
        global $DB;
        $coupon = self::get_record($id);

        $update = new \stdClass();
        $update->id = $coupon->id;

        if (array_key_exists('code', $data)) {
            $code = self::normalize_code($data['code']);
            if (self::code_exists($code, $coupon->id)) {
                throw new \moodle_exception('err_couponcodetaken', 'local_academy');
            }
            $update->code = $code;
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
        $DB->update_record('academy_coupons', $update);

        if (array_key_exists('items', $data)) {
            self::save_items($coupon->id, (array)$data['items']);
        }
    }

    /** Deactivate a coupon (US-AD-7-3). */
    public static function deactivate_coupon($id, $userid) {
        self::set_status($id, self::STATUS_INACTIVE, $userid);
    }

    /** Reactivate a coupon. */
    public static function activate_coupon($id, $userid) {
        self::set_status($id, self::STATUS_ACTIVE, $userid);
    }

    /** Delete a coupon (US-AD-7-3). Allowed only if it was never used. */
    public static function delete_coupon($id) {
        global $DB;
        self::get_record($id); // throws if missing
        if (self::has_usages($id)) {
            throw new \moodle_exception('err_couponhasusages', 'local_academy');
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('academy_coupon_items', array('couponid' => $id));
        $DB->delete_records('academy_coupons', array('id' => $id));
        $transaction->allow_commit();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Reads
    // ──────────────────────────────────────────────────────────────────────────

    /** Fetch one coupon (with scope + usage count) or throw. */
    public static function get_coupon($id) {
        return self::format(self::get_record($id));
    }

    /**
     * List coupons for the admin UI (US-AD-7-*), newest first.
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
        $rows = array_values($DB->get_records('academy_coupons', $conditions, 'timecreated DESC'));
        return array_map(array(self::class, 'format'), $rows);
    }

    /**
     * Active, in-window coupons for students to browse (US-US-CP-1-1).
     *
     * @return array
     */
    public static function get_available_coupons() {
        global $DB;
        $now = time();
        $rows = array_values($DB->get_records('academy_coupons', array('status' => self::STATUS_ACTIVE), 'timecreated DESC'));
        $out = array();
        foreach ($rows as $r) {
            if ($r->startdate > 0 && $now < $r->startdate) { continue; }
            if ($r->enddate > 0 && $now > $r->enddate) { continue; }
            $out[] = self::format($r);
        }
        return $out;
    }

    /**
     * A user's coupon redemptions, newest first (US-US-CP-1-3).
     *
     * @param int $userid
     * @return array
     */
    public static function get_my_usages($userid) {
        global $DB;
        $sql = "SELECT u.id, u.couponid, c.code, u.item_type, u.item_id,
                       u.original_amount, u.discount_amount, u.final_amount, u.timecreated
                  FROM {academy_coupon_usages} u
                  JOIN {academy_coupons} c ON c.id = u.couponid
                 WHERE u.userid = :userid
              ORDER BY u.timecreated DESC";
        $rows = array_values($DB->get_records_sql($sql, array('userid' => $userid)));
        foreach ($rows as $r) {
            $r->id              = (int)$r->id;
            $r->couponid        = (int)$r->couponid;
            $r->item_label      = discount_manager::item_label($r->item_type, $r->item_id);
            $r->original_amount = (float)$r->original_amount;
            $r->discount_amount = (float)$r->discount_amount;
            $r->final_amount    = (float)$r->final_amount;
        }
        return $rows;
    }

    /** Whether the coupon has any redemption records. */
    public static function has_usages($id) {
        global $DB;
        return $DB->record_exists('academy_coupon_usages', array('couponid' => $id));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /** Fetch a coupon record or throw. */
    private static function get_record($id) {
        global $DB;
        $coupon = $DB->get_record('academy_coupons', array('id' => $id));
        if (!$coupon) {
            throw new \moodle_exception('err_couponnotfound', 'local_academy');
        }
        return $coupon;
    }

    /** Shape a record for the API: cast types + attach scope items and usage count. */
    private static function format($record) {
        global $DB;
        $items = array_values($DB->get_records('academy_coupon_items', array('couponid' => $record->id)));
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
            'discount_type'  => $record->discount_type,
            'discount_value' => (float)$record->discount_value,
            'max_discount'   => $record->max_discount === null ? null : (float)$record->max_discount,
            'usage_type'     => $record->usage_type,
            'usage_limit'    => (int)$record->usage_limit,
            'startdate'      => (int)$record->startdate,
            'enddate'        => (int)$record->enddate,
            'status'         => $record->status,
            'usage_count'    => $DB->count_records('academy_coupon_usages', array('couponid' => $record->id)),
            'applies_to'     => $applies,
        );
    }

    /**
     * Replace the coupon's applicable-types + scope rows. Each item is
     * ['item_type'=>course|package|subscription, 'item_id'=>int] (0 = all of that type).
     * De-duplicated; a "0" (all) row makes any specific rows of the same type redundant but harmless.
     *
     * @param int $couponid
     * @param array $items
     */
    public static function save_items($couponid, array $items) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('academy_coupon_items', array('couponid' => $couponid));
        $seen = array();
        foreach ($items as $it) {
            $type = discount_manager::normalize_item_type($it['item_type'] ?? '');
            $iid  = max(0, (int)($it['item_id'] ?? 0));
            $key  = $type . ':' . $iid;
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $DB->insert_record('academy_coupon_items', (object) array(
                'couponid'  => $couponid,
                'item_type' => $type,
                'item_id'   => $iid,
            ));
        }
        $transaction->allow_commit();
    }

    /** Normalize a code (trim). Empty is rejected. */
    private static function normalize_code($code) {
        $code = trim((string)$code);
        if ($code === '') {
            throw new \moodle_exception('err_couponcoderequired', 'local_academy');
        }
        return $code;
    }

    /** Whether a code already exists (case-insensitive), optionally excluding one id. */
    private static function code_exists($code, $excludeid = 0) {
        global $DB;
        $params = array('code' => $code);
        $where = $DB->sql_equal('code', ':code', false);
        if ($excludeid > 0) {
            $where .= ' AND id <> :xid';
            $params['xid'] = $excludeid;
        }
        return $DB->record_exists_select('academy_coupons', $where, $params);
    }

    /** Validate a discount value (percent must be 0..100; fixed must be >= 0). */
    private static function validate_value($type, $value) {
        $value = (float)$value;
        if ($value < 0) {
            throw new \moodle_exception('err_discountvalue', 'local_academy');
        }
        if ($type === discount_manager::DISCOUNT_PERCENT && $value > 100) {
            throw new \moodle_exception('err_discountpercent', 'local_academy');
        }
        return $value;
    }

    /** Validate an optional max-discount cap (>= 0 or null). */
    private static function validate_max($max) {
        if ($max === null || $max === '') {
            return null;
        }
        $max = (float)$max;
        if ($max < 0) {
            throw new \moodle_exception('err_maxdiscount', 'local_academy');
        }
        // 0 means "no maximum" (same convention as usage_limit); store null so the cap is skipped.
        return $max > 0 ? $max : null;
    }

    /** Validate a start/end window (end must not precede start when both set). */
    private static function validate_dates($start, $end) {
        $start = max(0, (int)$start);
        $end = max(0, (int)$end);
        if ($start > 0 && $end > 0 && $end < $start) {
            throw new \moodle_exception('err_daterange', 'local_academy');
        }
        return array($start, $end);
    }

    /** Validate a usage type. */
    private static function normalize_usage_type($type) {
        $type = strtolower(trim((string)$type));
        if (!in_array($type, array(self::USAGE_ONCE, self::USAGE_MULTIPLE), true)) {
            throw new \moodle_exception('err_usagetype', 'local_academy');
        }
        return $type;
    }

    /** Validate a status. */
    private static function normalize_status($status) {
        $status = strtolower(trim((string)$status));
        if (!in_array($status, array(self::STATUS_ACTIVE, self::STATUS_INACTIVE), true)) {
            throw new \moodle_exception('err_status', 'local_academy');
        }
        return $status;
    }

    /** Set a coupon status (shared by activate/deactivate). */
    private static function set_status($id, $status, $userid) {
        global $DB;
        self::get_record($id);
        $DB->update_record('academy_coupons', (object) array(
            'id'           => $id,
            'status'       => $status,
            'timemodified' => time(),
            'usermodified' => $userid,
        ));
    }
}
