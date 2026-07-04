<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/enrollib.php');

/**
 * Student-facing subscription logic: browse, purchase, my subscriptions, payment history.
 *
 * Covers user stories US-SB-1-1, US-SB-1-2, US-SB-2-1 (see docs/specs/student/).
 * Payment gateway is intentionally skipped — purchase assumes payment already succeeded
 * (same convention as {@see purchase_manager} for packages).
 *
 * On a successful purchase the student is enrolled (Moodle manual enrolment) into every course
 * the plan unlocks, with the enrolment ending at the subscription expiry. The daily
 * {@see \local_academy\task\subscription_expiry_task} flips the purchase to expired and unenrols
 * from any course no other active subscription still grants.
 *
 * The full subscription price is platform revenue (US-SB-1-2) — it is recorded as a successful
 * payment and is NOT split with any teacher.
 */
class subscription_purchase_manager {

    /** US-SB-1-1: active plans a student can buy, each with its unlocked course list. */
    public static function get_available_subscriptions() {
        $subs = subscription_manager::get_subscriptions(subscription_manager::STATUS_ACTIVE);
        $out = array();
        foreach ($subs as $s) {
            $out[] = self::format_plan($s);
        }
        return $out;
    }

    /**
     * US-SB-1-2: purchase a subscription (payment assumed successful).
     *
     * @param int $userid
     * @param int $subscriptionid
     * @param string $method payment method label (e.g. online, card)
     * @param string $reference optional external payment reference
     * @return array purchase + payment summary
     */
    public static function purchase_subscription($userid, $subscriptionid, $method = 'online', $reference = '') {
        global $DB;

        $sub = $DB->get_record('academy_subscriptions', array('id' => $subscriptionid));
        if (!$sub) {
            throw new \moodle_exception('err_subnotfound', 'local_academy');
        }
        if ($sub->status !== subscription_manager::STATUS_ACTIVE) {
            throw new \moodle_exception('err_subnotavailable', 'local_academy');
        }
        // Rule: a student may hold only one active subscription at a time.
        if (self::get_active_subscription($userid)) {
            throw new \moodle_exception('err_alreadyhassubscription', 'local_academy');
        }

        $now = time();
        $expiresat = $now + ((int)$sub->duration_days * DAYSECS);
        $transaction = $DB->start_delegated_transaction();

        // 1. Create the purchase (snapshot of plan terms).
        $purchase = new \stdClass();
        $purchase->subscriptionid = $sub->id;
        $purchase->userid         = $userid;
        $purchase->price_paid     = $sub->price;
        $purchase->duration_days  = (int)$sub->duration_days;
        $purchase->status         = 'active';
        $purchase->source         = ($method === 'admin_assigned') ? 'admin_assigned' : 'online';
        $purchase->timeactivated  = $now;
        $purchase->expires_at     = $expiresat;
        $purchase->timecreated    = $now;
        $purchase->id = $DB->insert_record('academy_sub_purchases', $purchase);

        // 2. Record the payment (assumed successful — no gateway). Full price = platform revenue.
        $payment = new \stdClass();
        $payment->userid         = $userid;
        $payment->purchaseid     = $purchase->id;
        $payment->subscriptionid = $sub->id;
        $payment->amount         = $sub->price;
        $payment->method         = $method;
        $payment->reference      = $reference;
        $payment->transaction_no = self::generate_txn();
        $payment->status         = 'success';
        $payment->timecreated    = $now;
        $payment->id = $DB->insert_record('academy_sub_payments', $payment);

        // 3. (Removed) Auto-enrolment is now done on-demand per course when the student clicks "Enroll".

        $transaction->allow_commit();

        return array(
            'purchaseid'     => (int)$purchase->id,
            'paymentid'      => (int)$payment->id,
            'transaction_no' => $payment->transaction_no,
            'status'         => 'active',
            'timeactivated'  => (int)$now,
            'expires_at'     => (int)$expiresat,
            'courses'        => self::course_list($sub->id),
        );
    }

    /** US-SB-2-1: the student's subscriptions, active first. */
    public static function get_my_subscriptions($userid) {
        global $DB;
        $sql = "SELECT sp.*, s.name AS subscription_name
                  FROM {academy_sub_purchases} sp
                  JOIN {academy_subscriptions} s ON s.id = sp.subscriptionid
                 WHERE sp.userid = :uid
              ORDER BY sp.timecreated DESC";
        $rows = $DB->get_records_sql($sql, array('uid' => $userid));

        $now = time();
        $out = array();
        foreach ($rows as $r) {
            $status = self::effective_status($r);
            $daysleft = ($status === 'active' && (int)$r->expires_at > 0)
                ? max(0, (int) ceil(((int)$r->expires_at - $now) / DAYSECS)) : 0;
            $out[] = array(
                'id'             => (int)$r->id,
                'subscriptionid' => (int)$r->subscriptionid,
                'name'           => $r->subscription_name,
                'price_paid'     => $r->price_paid,
                'status'         => $status,
                'timeactivated'  => (int)$r->timeactivated,
                'expires_at'     => (int)$r->expires_at,
                'remaining_days' => $daysleft,
                'duration_days'  => (int)$r->duration_days,
                'courses'        => self::course_list($r->subscriptionid),
            );
        }
        // Active subscriptions first, then most recent.
        usort($out, function ($a, $b) {
            if (($a['status'] === 'active') !== ($b['status'] === 'active')) {
                return $a['status'] === 'active' ? -1 : 1;
            }
            return $b['timeactivated'] - $a['timeactivated'];
        });
        return $out;
    }

    /** US-SB-2-1: the student's subscription payment history. */
    public static function get_payment_history($userid) {
        global $DB;
        $sql = "SELECT pay.*, s.name AS subscription_name
                  FROM {academy_sub_payments} pay
                  JOIN {academy_subscriptions} s ON s.id = pay.subscriptionid
                 WHERE pay.userid = :uid
              ORDER BY pay.timecreated DESC";
        $rows = $DB->get_records_sql($sql, array('uid' => $userid));

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'id'             => (int)$r->id,
                'subscriptionid' => (int)$r->subscriptionid,
                'name'           => $r->subscription_name,
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

    /** Admin: Unsubscribe a specific user from a subscription. (US-AD-5-*) */
    public static function unsubscribe_user($purchaseid, $refund, $adminid) {
        global $DB;
        $purchase = $DB->get_record('academy_sub_purchases', array('id' => $purchaseid));
        if (!$purchase) {
            throw new \moodle_exception('err_subnotfound', 'local_academy');
        }

        $now = time();
        $transaction = $DB->start_delegated_transaction();

        // Mark purchase as cancelled
        $update = new \stdClass();
        $update->id = $purchase->id;
        $update->status = 'cancelled';
        $update->expires_at = $now;
        $DB->update_record('academy_sub_purchases', $update);

        // Revoke access
        self::revoke_course_access($purchase->userid, $purchase->subscriptionid);

        // Refund if requested
        if ($refund) {
            $payment = new \stdClass();
            $payment->userid         = $purchase->userid;
            $payment->purchaseid     = $purchase->id;
            $payment->subscriptionid = $purchase->subscriptionid;
            $payment->amount         = -1 * abs($purchase->price_paid);
            $payment->method         = 'refund';
            $payment->reference      = 'Unsubscribed by admin: ' . $adminid;
            $payment->transaction_no = self::generate_txn();
            $payment->status         = 'success';
            $payment->timecreated    = $now;
            $DB->insert_record('academy_sub_payments', $payment);
        }

        $transaction->allow_commit();
    }

    /** Admin: Get all user subscriptions. */
    public static function get_all_user_subscriptions() {
        global $DB;
        $sql = "SELECT sp.*, s.name AS subscription_name, u.firstname, u.lastname, u.email
                  FROM {academy_sub_purchases} sp
                  JOIN {academy_subscriptions} s ON s.id = sp.subscriptionid
                  JOIN {user} u ON u.id = sp.userid
              ORDER BY sp.timecreated DESC";
        $rows = $DB->get_records_sql($sql);

        $now = time();
        $out = array();
        foreach ($rows as $r) {
            $status = self::effective_status($r);
            $daysleft = ($status === 'active' && (int)$r->expires_at > 0)
                ? max(0, (int) ceil(((int)$r->expires_at - $now) / DAYSECS)) : 0;
            $out[] = array(
                'id'             => (int)$r->id,
                'userid'         => (int)$r->userid,
                'user_fullname'  => fullname($r),
                'user_email'     => $r->email,
                'subscriptionid' => (int)$r->subscriptionid,
                'name'           => $r->subscription_name,
                'price_paid'     => $r->price_paid,
                'status'         => $status,
                'timeactivated'  => (int)$r->timeactivated,
                'expires_at'     => (int)$r->expires_at,
                'remaining_days' => $daysleft,
                'duration_days'  => (int)$r->duration_days,
            );
        }
        return $out;
    }

    /** The student's current active, non-expired subscription (or null). */
    public static function get_active_subscription($userid) {
        global $DB;
        $purchases = $DB->get_records('academy_sub_purchases',
            array('userid' => $userid, 'status' => 'active'), 'timecreated DESC');
        foreach ($purchases as $p) {
            if (self::effective_status($p) === 'active') {
                return $p;
            }
        }
        return null;
    }

    /** Compute the real status of a purchase at read time (expired if past expiry). */
    public static function effective_status($purchase) {
        if ($purchase->status === 'active' && (int)$purchase->expires_at > 0 && time() > (int)$purchase->expires_at) {
            return 'expired';
        }
        return $purchase->status;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Course access via Moodle enrolment
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Enrol the student into every course the plan unlocks (manual enrolment, student role),
     * ending at $timeend.
     */
    public static function grant_course_access($userid, $subscriptionid, $timeend) {
        $roleid = self::student_roleid();
        foreach (subscription_manager::courses_for_subscription($subscriptionid) as $courseid) {
            self::enrol($courseid, $userid, $roleid, $timeend);
        }
    }

    /**
     * Enrol the student into a single course (manual enrolment, student role),
     * ending at $timeend.
     */
    public static function grant_single_course_access($courseid, $userid, $timeend) {
        $roleid = self::student_roleid();
        self::enrol($courseid, $userid, $roleid, $timeend);
    }

    /**
     * Unenrol the student from courses this plan granted, unless another still-active subscription
     * of theirs also grants the course.
     */
    public static function revoke_course_access($userid, $subscriptionid) {
        global $DB;

        $courseids = subscription_manager::courses_for_subscription($subscriptionid);
        if (empty($courseids)) {
            return;
        }

        // Courses still granted by the student's OTHER active, non-expired subscriptions.
        $stillgranted = array();
        $others = $DB->get_records('academy_sub_purchases',
            array('userid' => $userid, 'status' => 'active'));
        $now = time();
        foreach ($others as $o) {
            if ((int)$o->subscriptionid === (int)$subscriptionid) {
                continue;
            }
            if ((int)$o->expires_at > 0 && $now > (int)$o->expires_at) {
                continue; // effectively expired
            }
            foreach (subscription_manager::courses_for_subscription($o->subscriptionid) as $cid) {
                $stillgranted[$cid] = true;
            }
        }

        foreach ($courseids as $courseid) {
            if (!isset($stillgranted[$courseid])) {
                self::unenrol($courseid, $userid);
            }
        }
    }

    // ── enrolment plumbing ──

    /** The student archetype role id (falls back to the 'student' shortname). */
    private static function student_roleid() {
        global $DB;
        $roles = get_archetype_roles('student');
        if (!empty($roles)) {
            $role = reset($roles);
            return (int)$role->id;
        }
        return (int)$DB->get_field('role', 'id', array('shortname' => 'student'));
    }

    /** Get (or create) the manual enrolment instance for a course. */
    private static function manual_instance($courseid) {
        global $DB;
        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            return array(null, null);
        }
        $instance = $DB->get_record('enrol', array('courseid' => $courseid, 'enrol' => 'manual'), '*', IGNORE_MULTIPLE);
        if (!$instance) {
            $course = $DB->get_record('course', array('id' => $courseid));
            if (!$course) {
                return array(null, null);
            }
            $instanceid = $plugin->add_default_instance($course);
            if ($instanceid === null) {
                $instanceid = $plugin->add_instance($course);
            }
            $instance = $DB->get_record('enrol', array('id' => $instanceid));
        }
        return array($plugin, $instance);
    }

    private static function enrol($courseid, $userid, $roleid, $timeend) {
        list($plugin, $instance) = self::manual_instance($courseid);
        if ($plugin && $instance) {
            // timeend 0 = no end; we always pass a real expiry here.
            $plugin->enrol_user($instance, $userid, $roleid, time(), (int)$timeend);
        }
    }

    private static function unenrol($courseid, $userid) {
        list($plugin, $instance) = self::manual_instance($courseid);
        if ($plugin && $instance) {
            $plugin->unenrol_user($instance, $userid);
        }
    }

    // ── formatters ──

    /** Shape a plan for the student browse screen (US-SB-1-1 display fields). */
    private static function format_plan($s) {
        return array(
            'id'            => (int)$s->id,
            'name'          => $s->name,
            'description'   => $s->description,
            'price'         => $s->price,
            'duration_days' => (int)$s->duration_days,
            'status'        => $s->status,
            'courses'       => self::course_list($s->id),
        );
    }

    /** The unlocked courses for a plan as [{id, fullname}] for display. */
    private static function course_list($subscriptionid) {
        global $DB;
        $ids = subscription_manager::courses_for_subscription($subscriptionid);
        if (empty($ids)) {
            return array();
        }
        list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select('course', "id $insql", $params, 'fullname ASC', 'id, fullname');
        $out = array();
        foreach ($rows as $c) {
            $out[] = array('id' => (int)$c->id, 'fullname' => format_string($c->fullname));
        }
        return $out;
    }

    /** Generate a unique-ish transaction number. */
    private static function generate_txn() {
        return 'SUB' . strtoupper(substr(md5(uniqid('', true)), 0, 14));
    }
}
