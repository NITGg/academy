<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD + rules for automatic offers (US-AD-8-1 create, US-AD-8-2 update, US-AD-8-3 deactivate/delete)
 * and the student-facing reads (US-US-OF-1-1 available, US-US-OF-1-3 usage history).
 *
 * Offers carry no code and no max cap — they apply automatically at checkout. Discount maths +
 * resolution live in {@see discount_manager}. See docs/specs/admin/US-AD-8-* and
 * docs/specs/student/US-US-OF-*.
 */
class offer_manager {

    const STATUS_ACTIVE   = 'active';
    const STATUS_INACTIVE = 'inactive';

    // ──────────────────────────────────────────────────────────────────────────
    // CRUD
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create an offer (US-AD-8-1).
     *
     * @param array $data name, discount_type, discount_value, startdate, enddate, active, items[]
     * @param int $userid admin
     * @return int new offer id
     */
    public static function create_offer(array $data, $userid) {
        global $DB;
        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw new \moodle_exception('err_offernamerequired', 'local_academy');
        }
        $now = time();
        $record = new \stdClass();
        $record->name           = $name;
        $record->discount_type  = discount_manager::normalize_discount_type($data['discount_type'] ?? 'percent');
        $record->discount_value = self::validate_value($record->discount_type, $data['discount_value'] ?? 0);
        list($record->startdate, $record->enddate) = self::validate_dates($data['startdate'] ?? 0, $data['enddate'] ?? 0);
        $record->status         = !empty($data['active']) ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
        $record->timecreated    = $now;
        $record->timemodified   = $now;
        $record->usermodified   = $userid;

        $id = $DB->insert_record('academy_offers', $record);
        self::save_items($id, (array)($data['items'] ?? array()));
        return $id;
    }

    /**
     * Update an offer (US-AD-8-2). Only provided fields change; applies to future purchases.
     *
     * @param int $id
     * @param array $data subset of create fields (+ status). If 'items' present it replaces scope.
     * @param int $userid admin
     */
    public static function update_offer($id, array $data, $userid) {
        global $DB;
        $offer = self::get_record($id);

        $update = new \stdClass();
        $update->id = $offer->id;

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if ($name === '') {
                throw new \moodle_exception('err_offernamerequired', 'local_academy');
            }
            $update->name = $name;
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
        $DB->update_record('academy_offers', $update);

        if (array_key_exists('items', $data)) {
            self::save_items($offer->id, (array)$data['items']);
        }
    }

    /** Deactivate an offer (US-AD-8-3). */
    public static function deactivate_offer($id, $userid) {
        self::set_status($id, self::STATUS_INACTIVE, $userid);
    }

    /** Reactivate an offer. */
    public static function activate_offer($id, $userid) {
        self::set_status($id, self::STATUS_ACTIVE, $userid);
    }

    /** Delete an offer (US-AD-8-3). Allowed only if it was never used. */
    public static function delete_offer($id) {
        global $DB;
        self::get_record($id);
        if (self::has_usages($id)) {
            throw new \moodle_exception('err_offerhasusages', 'local_academy');
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('academy_offer_items', array('offerid' => $id));
        $DB->delete_records('academy_offers', array('id' => $id));
        $transaction->allow_commit();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Reads
    // ──────────────────────────────────────────────────────────────────────────

    /** Fetch one offer (with scope + usage count) or throw. */
    public static function get_offer($id) {
        return self::format(self::get_record($id));
    }

    /**
     * List offers for the admin UI (US-AD-8-*), newest first.
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
        $rows = array_values($DB->get_records('academy_offers', $conditions, 'timecreated DESC'));
        return array_map(array(self::class, 'format'), $rows);
    }

    /**
     * Active, in-window offers (US-US-OF-1-1). Each row includes its scope labels.
     *
     * @return array
     */
    public static function get_available_offers() {
        global $DB;
        $now = time();
        $rows = array_values($DB->get_records('academy_offers', array('status' => self::STATUS_ACTIVE), 'timecreated DESC'));
        $out = array();
        foreach ($rows as $r) {
            if ($r->startdate > 0 && $now < $r->startdate) { continue; }
            if ($r->enddate > 0 && $now > $r->enddate) { continue; }
            $out[] = self::format($r);
        }
        return $out;
    }

    /**
     * A user's offer applications, newest first (US-US-OF-1-3).
     *
     * @param int $userid
     * @return array
     */
    public static function get_my_usages($userid) {
        global $DB;
        $sql = "SELECT u.id, u.offerid, o.name, u.item_type, u.item_id,
                       u.original_amount, u.discount_amount, u.final_amount, u.timecreated
                  FROM {academy_offer_usages} u
                  JOIN {academy_offers} o ON o.id = u.offerid
                 WHERE u.userid = :userid
              ORDER BY u.timecreated DESC";
        $rows = array_values($DB->get_records_sql($sql, array('userid' => $userid)));
        foreach ($rows as $r) {
            $r->id              = (int)$r->id;
            $r->offerid         = (int)$r->offerid;
            $r->name            = format_string($r->name);
            $r->item_label      = discount_manager::item_label($r->item_type, $r->item_id);
            $r->original_amount = (float)$r->original_amount;
            $r->discount_amount = (float)$r->discount_amount;
            $r->final_amount    = (float)$r->final_amount;
        }
        return $rows;
    }

    /** Whether the offer has any application records. */
    public static function has_usages($id) {
        global $DB;
        return $DB->record_exists('academy_offer_usages', array('offerid' => $id));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /** Fetch an offer record or throw. */
    private static function get_record($id) {
        global $DB;
        $offer = $DB->get_record('academy_offers', array('id' => $id));
        if (!$offer) {
            throw new \moodle_exception('err_offernotfound', 'local_academy');
        }
        return $offer;
    }

    /** Shape a record for the API: cast types + attach scope items and usage count. */
    private static function format($record) {
        global $DB;
        $items = array_values($DB->get_records('academy_offer_items', array('offerid' => $record->id)));
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
            // 'name' is localised (the {mlang} filter resolves to the current language) for display;
            // 'name_raw' keeps the stored {mlang en}…{mlang}{mlang ar}…{mlang} so the admin edit form
            // can populate both language boxes (US-AD-8-1 localised offer name).
            'name'           => format_string($record->name),
            'name_raw'       => $record->name,
            'discount_type'  => $record->discount_type,
            'discount_value' => (float)$record->discount_value,
            'startdate'      => (int)$record->startdate,
            'enddate'        => (int)$record->enddate,
            'status'         => $record->status,
            'usage_count'    => $DB->count_records('academy_offer_usages', array('offerid' => $record->id)),
            'applies_to'     => $applies,
        );
    }

    /**
     * Replace the offer's applicable-types + scope rows (see coupon_manager::save_items).
     *
     * @param int $offerid
     * @param array $items
     */
    public static function save_items($offerid, array $items) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('academy_offer_items', array('offerid' => $offerid));
        $seen = array();
        foreach ($items as $it) {
            $type = discount_manager::normalize_item_type($it['item_type'] ?? '');
            $iid  = max(0, (int)($it['item_id'] ?? 0));
            $key  = $type . ':' . $iid;
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $DB->insert_record('academy_offer_items', (object) array(
                'offerid'   => $offerid,
                'item_type' => $type,
                'item_id'   => $iid,
            ));
        }
        $transaction->allow_commit();
    }

    /** Validate a discount value (percent 0..100; fixed >= 0). */
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

    /** Validate a start/end window. */
    private static function validate_dates($start, $end) {
        $start = max(0, (int)$start);
        $end = max(0, (int)$end);
        if ($start > 0 && $end > 0 && $end < $start) {
            throw new \moodle_exception('err_daterange', 'local_academy');
        }
        return array($start, $end);
    }

    /** Validate a status. */
    private static function normalize_status($status) {
        $status = strtolower(trim((string)$status));
        if (!in_array($status, array(self::STATUS_ACTIVE, self::STATUS_INACTIVE), true)) {
            throw new \moodle_exception('err_status', 'local_academy');
        }
        return $status;
    }

    /** Set an offer status. */
    private static function set_status($id, $status, $userid) {
        global $DB;
        self::get_record($id);
        $DB->update_record('academy_offers', (object) array(
            'id'           => $id,
            'status'       => $status,
            'timemodified' => time(),
            'usermodified' => $userid,
        ));
    }
}
