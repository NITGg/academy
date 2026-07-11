<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Admin "Manage Courses": list who bought which single course, and "unbuy" (revoke) a purchase.
 *
 * A single-course purchase is a completed local_payments transaction whose metadata item_type is
 * "course" (package / subscription checkouts do not target a course). Revoking unenrols the buyer
 * from the course and marks the transaction cancelled (or refunded), mirroring the admin
 * "unsubscribe" flow on manage_subscriptions.php.
 *
 * No schema change: reads local_payments_transactions directly and reuses
 * {@see \local_payments\enrollment_handler} to unenrol.
 */
class course_purchase_manager {

    /**
     * Every paid single-course purchase, newest first, with the buyer + course + amount and whether
     * the buyer is still enrolled.
     *
     * @return array list of purchase rows for the admin table
     */
    public static function get_all_course_purchases() {
        global $DB;

        // Completed transactions that carry a course id. We still confirm item_type=course from the
        // JSON metadata below, because a completed row is the only thing that ever granted access.
        $sql = "SELECT t.id, t.userid, t.courseid, t.amount, t.original_amount, t.currency,
                       t.status, t.metadata, t.timemodified, t.timecreated,
                       u.firstname, u.lastname, u.email,
                       c.fullname AS course_fullname
                  FROM {local_payments_transactions} t
                  JOIN {user} u ON u.id = t.userid
             LEFT JOIN {course} c ON c.id = t.courseid
                 WHERE t.status = :status AND t.courseid IS NOT NULL AND t.courseid > 0
              ORDER BY t.timecreated DESC";
        $rows = $DB->get_records_sql($sql, array('status' => \local_payments\status_machine::COMPLETED));

        $out = array();
        foreach ($rows as $r) {
            $meta = json_decode((string)$r->metadata);
            $itemtype = isset($meta->item_type) ? $meta->item_type : 'course';
            if ($itemtype !== 'course') {
                continue; // package/subscription transaction that happens to carry a course id.
            }
            $coursename = $r->course_fullname !== null
                ? format_string($r->course_fullname)
                : get_string('mc_course_deleted', 'local_academy');
            $enrolled = false;
            if ($r->course_fullname !== null) {
                $enrolled = \local_payments\enrollment_handler::is_enrolled((int)$r->userid, (int)$r->courseid);
            }
            $out[] = array(
                'id'              => (int)$r->id,
                'userid'          => (int)$r->userid,
                'user_fullname'   => fullname($r),
                'user_email'      => $r->email,
                'courseid'        => (int)$r->courseid,
                'course_fullname' => $coursename,
                'amount'          => (float)$r->amount,
                'original_amount' => $r->original_amount !== null ? (float)$r->original_amount : (float)$r->amount,
                'currency'        => $r->currency,
                'enrolled'        => $enrolled,
                'timecreated'     => (int)$r->timecreated,
            );
        }
        return $out;
    }

    /**
     * "Unbuy" a course: unenrol the buyer and mark the transaction cancelled (refunded when asked).
     *
     * @param int $transactionid local_payments_transactions.id
     * @param bool $refund mark the transaction refunded instead of cancelled
     * @param int $adminid acting admin (for the audit note)
     */
    public static function revoke_course_purchase($transactionid, $refund, $adminid) {
        global $DB;

        $transaction = $DB->get_record('local_payments_transactions', array('id' => $transactionid));
        if (!$transaction) {
            throw new \moodle_exception('mc_txn_notfound', 'local_academy');
        }
        if ($transaction->status !== \local_payments\status_machine::COMPLETED) {
            throw new \moodle_exception('mc_not_active', 'local_academy');
        }

        $courseid = (int)$transaction->courseid;
        $userid   = (int)$transaction->userid;

        $dbtx = $DB->start_delegated_transaction();

        // Revoke access. unenrol_user is a no-op if the course is gone or the user is not enrolled.
        if ($courseid > 0 && $DB->record_exists('course', array('id' => $courseid))) {
            \local_payments\enrollment_handler::unenrol_user($userid, $courseid);
        }

        $update = new \stdClass();
        $update->id           = $transaction->id;
        $update->status       = $refund
            ? \local_payments\status_machine::REFUNDED
            : \local_payments\status_machine::CANCELLED;
        $update->timemodified = time();
        $DB->update_record('local_payments_transactions', $update);

        $dbtx->allow_commit();
    }
}
