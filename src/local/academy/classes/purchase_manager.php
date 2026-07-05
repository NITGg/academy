<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Student-facing package logic: browse, purchase, my packages, payment history.
 *
 * Covers user stories US-PK-1-1, US-PK-1-2, US-PK-2-1 (see docs/specs/student/).
 * Payment gateway is intentionally skipped — purchase assumes payment already succeeded.
 */
class purchase_manager {

    /** US-PK-1-1: active packages a student can buy. */
    public static function get_available_packages() {
        return package_manager::get_packages(package_manager::STATUS_ACTIVE);
    }

    /**
     * The student's current active package (or null). A package is "active" when its status is
     * active, it still has flexes, and it hasn't expired.
     */
    public static function get_active_purchase($userid) {
        global $DB;
        $purchases = $DB->get_records('academy_package_purchases',
            array('userid' => $userid, 'status' => 'active'), 'timecreated DESC');
        foreach ($purchases as $p) {
            if (self::effective_status($p) === 'active') {
                return $p;
            }
        }
        return null;
    }

    /**
     * US-PK-1-2: purchase a package (payment assumed successful).
     *
     * @param int $userid
     * @param int $packageid
     * @param string $method payment method label (e.g. online, card)
     * @param string $reference optional external payment reference
     * @return array purchase + payment summary
     */
    public static function purchase_package($userid, $packageid, $method = 'online', $reference = '') {
        global $DB;

        $package = $DB->get_record('academy_packages', array('id' => $packageid));
        if (!$package) {
            throw new \moodle_exception('err_notfound', 'local_academy');
        }
        if ($package->status !== package_manager::STATUS_ACTIVE) {
            throw new \moodle_exception('err_packagenotavailable', 'local_academy');
        }
        // Rule: a student may hold only one active package at a time.
        if (self::get_active_purchase($userid)) {
            throw new \moodle_exception('err_alreadyhaspackage', 'local_academy');
        }

        $now = time();
        $transaction = $DB->start_delegated_transaction();

        // 1. Create the purchase (snapshot of package terms + opening balance).
        $purchase = new \stdClass();
        $purchase->packageid       = $package->id;
        $purchase->userid          = $userid;
        $purchase->price_paid      = $package->price;
        $purchase->flex_count      = $package->flex_count;
        $purchase->expiration_days = $package->expiration_days;
        $purchase->status          = 'active';
        $purchase->source          = ($method === 'admin_assigned') ? 'admin_assigned' : 'online';
        $purchase->remaining_flex  = $package->flex_count;
        $purchase->expires_at      = $package->expiration_days > 0
            ? $now + ($package->expiration_days * DAYSECS) : 0;
        $purchase->timeactivated   = $now;
        $purchase->timecreated     = $now;
        $purchase->id = $DB->insert_record('academy_package_purchases', $purchase);

        // 2. Record the payment (assumed successful — no gateway).
        $payment = new \stdClass();
        $payment->userid         = $userid;
        $payment->purchaseid     = $purchase->id;
        $payment->packageid      = $package->id;
        $payment->amount         = $package->price;
        $payment->method         = $method;
        $payment->reference      = $reference;
        $payment->transaction_no = self::generate_txn();
        $payment->status         = 'success';
        $payment->timecreated    = $now;
        $payment->id = $DB->insert_record('academy_payments', $payment);

        // Ledger entry so the student Flex history (US-AD-3-4) captures the opening balance.
        flex_manager::log_grant($userid, $purchase->id, $purchase->remaining_flex, $userid,
            flex_manager::TYPE_PURCHASE, 'Package purchased: ' . $package->name);

        $transaction->allow_commit();

        return array(
            'purchaseid'     => (int)$purchase->id,
            'paymentid'      => (int)$payment->id,
            'transaction_no' => $payment->transaction_no,
            'flex_balance'   => (int)$purchase->remaining_flex,
            'expires_at'     => (int)$purchase->expires_at,
            'status'         => 'active',
        );
    }

    /**
     * US-AD-4-1: an admin assigns a package to a student who paid outside the platform.
     *
     * @param int $adminid the assigning admin
     * @param int $studentid recipient
     * @param int $packageid active package to assign
     * @param float $amount amount paid externally (defaults to the package price if <= 0)
     * @param string $method external payment method
     * @param string $reference external payment reference
     * @param string $note optional note
     * @return array assignment summary
     */
    public static function assign_package($adminid, $studentid, $packageid, $amount, $method = 'offline',
            $reference = '', $note = '') {
        global $DB;

        $student = $DB->get_record('user', array('id' => $studentid, 'deleted' => 0));
        if (!$student) {
            throw new \moodle_exception('err_studentnotfound', 'local_academy');
        }
        $package = $DB->get_record('academy_packages', array('id' => $packageid));
        if (!$package) {
            throw new \moodle_exception('err_notfound', 'local_academy');
        }
        if ($package->status !== package_manager::STATUS_ACTIVE) {
            throw new \moodle_exception('err_packagenotavailable', 'local_academy');
        }
        if (self::get_active_purchase($studentid)) {
            throw new \moodle_exception('err_studenthaspackage', 'local_academy');
        }

        $price = ((float)$amount > 0) ? round((float)$amount, 2) : (float)$package->price;
        $now = time();
        $transaction = $DB->start_delegated_transaction();

        $purchase = new \stdClass();
        $purchase->packageid       = $package->id;
        $purchase->userid          = $studentid;
        $purchase->price_paid      = $price;
        $purchase->flex_count      = $package->flex_count;
        $purchase->expiration_days = $package->expiration_days;
        $purchase->status          = 'active';
        $purchase->source          = 'admin_assigned';
        $purchase->remaining_flex  = $package->flex_count;
        $purchase->expires_at      = $package->expiration_days > 0
            ? $now + ($package->expiration_days * DAYSECS) : 0;
        $purchase->timeactivated   = $now;
        $purchase->timecreated     = $now;
        $purchase->id = $DB->insert_record('academy_package_purchases', $purchase);

        $payment = new \stdClass();
        $payment->userid         = $studentid;
        $payment->purchaseid     = $purchase->id;
        $payment->packageid      = $package->id;
        $payment->amount         = $price;
        $payment->method         = $method !== '' ? $method : 'offline';
        $payment->reference      = trim($reference . ($note !== '' ? ' | ' . $note : ''));
        $payment->transaction_no = self::generate_txn();
        $payment->status         = 'success';
        $payment->timecreated    = $now;
        $payment->id = $DB->insert_record('academy_payments', $payment);

        flex_manager::log_grant($studentid, $purchase->id, $purchase->remaining_flex, $adminid,
            flex_manager::TYPE_ASSIGN, 'Package assigned by admin: ' . $package->name);

        $transaction->allow_commit();

        return array(
            'purchaseid'    => (int)$purchase->id,
            'paymentid'     => (int)$payment->id,
            'studentid'     => (int)$studentid,
            'student_name'  => trim($student->firstname . ' ' . $student->lastname),
            'package_name'  => $package->name,
            'flex_balance'  => (int)$purchase->remaining_flex,
            'price_paid'    => $price,
            'expires_at'    => (int)$purchase->expires_at,
            'source'        => 'admin_assigned',
        );
    }

    /** US-PK-2-1 (packages): the student's packages, active first. */
    public static function get_my_packages($userid) {
        global $DB;
        $sql = "SELECT pu.*, p.name AS package_name
                  FROM {academy_package_purchases} pu
                  JOIN {academy_packages} p ON p.id = pu.packageid
                 WHERE pu.userid = :uid
              ORDER BY pu.timecreated DESC";
        $rows = $DB->get_records_sql($sql, array('uid' => $userid));

        $out = array();
        foreach ($rows as $r) {
            $status = self::effective_status($r);
            $out[] = array(
                'id'              => (int)$r->id,
                'packageid'       => (int)$r->packageid,
                'name'            => $r->package_name,
                'total_flex'      => (int)$r->flex_count,
                'remaining_flex'  => (int)$r->remaining_flex,
                'used_flex'       => (int)$r->flex_count - (int)$r->remaining_flex,
                'price_paid'      => $r->price_paid,
                'status'          => $status,
                'timeactivated'   => (int)$r->timeactivated,
                'expires_at'      => (int)$r->expires_at,
                'expiration_days' => (int)$r->expiration_days,
            );
        }
        // Active package first, then most recent.
        usort($out, function ($a, $b) {
            if (($a['status'] === 'active') !== ($b['status'] === 'active')) {
                return $a['status'] === 'active' ? -1 : 1;
            }
            return $b['timeactivated'] - $a['timeactivated'];
        });
        return $out;
    }

    /** US-PK-2-1 (payments): the student's payment history. */
    public static function get_payment_history($userid) {
        global $DB;
        $sql = "SELECT pay.*, p.name AS package_name
                  FROM {academy_payments} pay
                  JOIN {academy_packages} p ON p.id = pay.packageid
                 WHERE pay.userid = :uid
              ORDER BY pay.timecreated DESC";
        $rows = $DB->get_records_sql($sql, array('uid' => $userid));

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'id'             => (int)$r->id,
                'packageid'      => (int)$r->packageid,
                'name'           => $r->package_name,
                'amount'         => $r->amount,
                'method'         => $r->method,
                'reference'      => $r->reference,
                'transaction_no' => $r->transaction_no,
                'status'         => $r->status,
                'timecreated'    => (int)$r->timecreated,
            );
        }
        return $out;
    }

    /** Admin: unassign (cancel) a specific user's package purchase. Mirrors
     * {@see subscription_purchase_manager::unsubscribe_user}. */
    public static function unassign_package($purchaseid, $refund, $adminid) {
        global $DB;
        $purchase = $DB->get_record('academy_package_purchases', array('id' => $purchaseid));
        if (!$purchase) {
            throw new \moodle_exception('err_notfound', 'local_academy');
        }

        $now = time();
        $transaction = $DB->start_delegated_transaction();

        // Zero out any remaining flex balance so it can no longer be spent, and log it.
        flex_manager::log_revoke($purchase->userid, $purchase->id, $adminid,
            'Unassigned by admin: ' . $adminid);

        $update = new \stdClass();
        $update->id = $purchase->id;
        $update->status = 'cancelled';
        $update->expires_at = $now;
        $DB->update_record('academy_package_purchases', $update);

        // Refund if requested.
        if ($refund) {
            $payment = new \stdClass();
            $payment->userid         = $purchase->userid;
            $payment->purchaseid     = $purchase->id;
            $payment->packageid      = $purchase->packageid;
            $payment->amount         = -1 * abs($purchase->price_paid);
            $payment->method         = 'refund';
            $payment->reference      = 'Unassigned by admin: ' . $adminid;
            $payment->transaction_no = self::generate_txn();
            $payment->status         = 'success';
            $payment->timecreated    = $now;
            $DB->insert_record('academy_payments', $payment);
        }

        $transaction->allow_commit();
    }

    /** Admin: get all user package purchases. */
    public static function get_all_user_packages() {
        global $DB;
        $sql = "SELECT pu.*, p.name AS package_name, u.firstname, u.lastname, u.email
                  FROM {academy_package_purchases} pu
                  JOIN {academy_packages} p ON p.id = pu.packageid
                  JOIN {user} u ON u.id = pu.userid
              ORDER BY pu.timecreated DESC";
        $rows = $DB->get_records_sql($sql);

        $out = array();
        foreach ($rows as $r) {
            $status = self::effective_status($r);
            $out[] = array(
                'id'             => (int)$r->id,
                'userid'         => (int)$r->userid,
                'user_fullname'  => fullname($r),
                'user_email'     => $r->email,
                'packageid'      => (int)$r->packageid,
                'name'           => $r->package_name,
                'total_flex'     => (int)$r->flex_count,
                'remaining_flex' => (int)$r->remaining_flex,
                'used_flex'      => (int)$r->flex_count - (int)$r->remaining_flex,
                'price_paid'     => $r->price_paid,
                'status'         => $status,
                'timeactivated'  => (int)$r->timeactivated,
                'expires_at'     => (int)$r->expires_at,
            );
        }
        return $out;
    }

    /** Compute the real status of a purchase at read time. */
    public static function effective_status($purchase) {
        if ($purchase->status === 'active') {
            if ((int)$purchase->remaining_flex <= 0) {
                return 'fully_used';
            }
            if ((int)$purchase->expires_at > 0 && time() > (int)$purchase->expires_at) {
                return 'expired';
            }
        }
        return $purchase->status;
    }

    /** Generate a unique-ish transaction number. */
    private static function generate_txn() {
        return 'TXN' . strtoupper(substr(md5(uniqid('', true)), 0, 14));
    }
}
