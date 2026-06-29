<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * The Flex engine: reserve / consume / return a student's lesson credits, with a full ledger.
 *
 * Covers US-FN-1-2 (reserve on confirm) and US-FN-1-3 (return on teacher cancel/absence or early
 * student cancel). Every movement is recorded in {academy_flex_tx} so US-AD-3-4 can show a student's
 * balance and history later.
 *
 * Balance model on {academy_package_purchases}:
 *   remaining_flex = available to reserve
 *   reserved_flex  = held for confirmed lessons (not yet earnings)
 *   consumed_flex  = permanently spent (completed / late-cancel / student-absent)
 *
 * reserve : remaining -1, reserved +1     (ledger amount -1 on remaining)
 * consume : reserved  -1, consumed +1     (ledger amount  0 — remaining already lowered at reserve)
 * return  : reserved  -1, remaining +1    (ledger amount +1 on remaining)
 */
class flex_manager {

    const TYPE_RESERVE  = 'reserve';
    const TYPE_CONSUME  = 'consume';
    const TYPE_RETURN   = 'return';
    const TYPE_EXPIRE   = 'expire';
    const TYPE_ADJUST   = 'adjust';
    const TYPE_PURCHASE = 'purchase';
    const TYPE_ASSIGN   = 'assign';

    /**
     * Record Flexes added to a student's balance when a package is purchased or admin-assigned, so the
     * ledger (US-AD-3-4) captures every balance change. Called within the purchase/assign transaction.
     */
    public static function log_grant($studentid, $purchaseid, $amount, $performedby, $type, $reason = '') {
        self::log($studentid, $purchaseid, 0, $type, (int)$amount, 0, (int)$amount, $performedby, $reason);
    }

    /**
     * Reserve one Flex from the student's active package when a lesson is confirmed (US-FN-1-2).
     *
     * @param int $studentid
     * @param int $lessonid
     * @param int $performedby user performing the action (teacher or student)
     * @param string $reason
     * @return int the purchase id the Flex was reserved from
     */
    public static function reserve($studentid, $lessonid, $performedby, $reason = '') {
        global $DB;
        $purchase = purchase_manager::get_active_purchase($studentid);
        if (!$purchase || (int)$purchase->remaining_flex < 1) {
            throw new \moodle_exception('err_noflex', 'local_academy');
        }
        $before = (int)$purchase->remaining_flex;
        $after  = $before - 1;
        $purchase->remaining_flex = $after;
        $purchase->reserved_flex  = (int)$purchase->reserved_flex + 1;
        $DB->update_record('academy_package_purchases', $purchase);

        self::log($studentid, $purchase->id, $lessonid, self::TYPE_RESERVE, -1, $before, $after, $performedby, $reason);
        return (int)$purchase->id;
    }

    /**
     * Permanently consume a previously reserved Flex (lesson completed / late student cancel /
     * student absent). The Flex moves from reserved to consumed; remaining is unchanged.
     */
    public static function consume($studentid, $purchaseid, $lessonid, $performedby, $reason = '') {
        global $DB;
        $purchase = $DB->get_record('academy_package_purchases', array('id' => $purchaseid));
        if (!$purchase) {
            throw new \moodle_exception('err_notfound', 'local_academy');
        }
        $remaining = (int)$purchase->remaining_flex;
        if ((int)$purchase->reserved_flex > 0) {
            $purchase->reserved_flex = (int)$purchase->reserved_flex - 1;
        }
        $purchase->consumed_flex = (int)$purchase->consumed_flex + 1;
        $DB->update_record('academy_package_purchases', $purchase);

        self::log($studentid, $purchaseid, $lessonid, self::TYPE_CONSUME, 0, $remaining, $remaining, $performedby, $reason);
    }

    /**
     * Return a reserved Flex to the student's balance (US-FN-1-3): teacher cancel/absence or early
     * student cancel. The Flex moves from reserved back to remaining.
     */
    public static function return_flex($studentid, $purchaseid, $lessonid, $performedby, $reason = '') {
        global $DB;
        $purchase = $DB->get_record('academy_package_purchases', array('id' => $purchaseid));
        if (!$purchase) {
            throw new \moodle_exception('err_notfound', 'local_academy');
        }
        $before = (int)$purchase->remaining_flex;
        $after  = $before + 1;
        $purchase->remaining_flex = $after;
        if ((int)$purchase->reserved_flex > 0) {
            $purchase->reserved_flex = (int)$purchase->reserved_flex - 1;
        }
        // Returning a Flex revives a purchase that had been fully used.
        if ($purchase->status === 'fully_used') {
            $purchase->status = 'active';
        }
        $DB->update_record('academy_package_purchases', $purchase);

        self::log($studentid, $purchaseid, $lessonid, self::TYPE_RETURN, 1, $before, $after, $performedby, $reason);
    }

    /**
     * Reverse a previously consumed Flex back to the student's balance (US-FN-1-5 admin reversal).
     * The Flex moves from consumed back to remaining (unlike {@see return_flex}, which releases a
     * reservation).
     */
    public static function reverse_consumed($studentid, $purchaseid, $lessonid, $performedby, $reason = '') {
        global $DB;
        $purchase = $DB->get_record('academy_package_purchases', array('id' => $purchaseid));
        if (!$purchase) {
            throw new \moodle_exception('err_notfound', 'local_academy');
        }
        $before = (int)$purchase->remaining_flex;
        $after  = $before + 1;
        $purchase->remaining_flex = $after;
        if ((int)$purchase->consumed_flex > 0) {
            $purchase->consumed_flex = (int)$purchase->consumed_flex - 1;
        }
        if ($purchase->status === 'fully_used') {
            $purchase->status = 'active';
        }
        $DB->update_record('academy_package_purchases', $purchase);

        self::log($studentid, $purchaseid, $lessonid, self::TYPE_RETURN, 1, $before, $after, $performedby, $reason);
    }

    /** A student's full Flex ledger (most recent first). */
    public static function get_history($userid) {
        global $DB;
        $rows = $DB->get_records('academy_flex_tx', array('userid' => $userid), 'timecreated DESC, id DESC');
        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'id'             => (int)$r->id,
                'purchaseid'     => (int)$r->purchaseid,
                'lessonid'       => (int)$r->lessonid,
                'type'           => $r->type,
                'amount'         => (int)$r->amount,
                'balance_before' => (int)$r->balance_before,
                'balance_after'  => (int)$r->balance_after,
                'reason'         => $r->reason,
                'timecreated'    => (int)$r->timecreated,
            );
        }
        return $out;
    }

    /** Write one ledger row. */
    private static function log($userid, $purchaseid, $lessonid, $type, $amount, $before, $after, $performedby, $reason) {
        global $DB;
        $DB->insert_record('academy_flex_tx', (object) array(
            'userid'         => $userid,
            'purchaseid'     => $purchaseid,
            'lessonid'       => $lessonid,
            'type'           => $type,
            'amount'         => $amount,
            'balance_before' => $before,
            'balance_after'  => $after,
            'performedby'    => $performedby,
            'reason'         => $reason,
            'timecreated'    => time(),
        ));
    }
}
