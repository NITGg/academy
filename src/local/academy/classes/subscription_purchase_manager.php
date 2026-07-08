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
     * US-SB-1-2 / US-B2B-1-1: purchase a subscription (payment assumed successful).
     *
     * @param int $userid
     * @param int $subscriptionid
     * @param string $method payment method label (e.g. online, card)
     * @param string $reference optional external payment reference
     * @param string $type 'normal' | 'b2b'
     * @param int $seats purchased capacity (B2B only; must match a seat option)
     * @return array purchase + payment summary
     */
    public static function purchase_subscription($userid, $subscriptionid, $method = 'online', $reference = '',
            $type = 'normal', $seats = 0) {
        global $DB;

        $sub = $DB->get_record('academy_subscriptions', array('id' => $subscriptionid));
        if (!$sub) {
            throw new \moodle_exception('err_subnotfound', 'local_academy');
        }
        if ($sub->status !== subscription_manager::STATUS_ACTIVE) {
            throw new \moodle_exception('err_subnotavailable', 'local_academy');
        }

        $isb2b = ($type === 'b2b');
        $requestedseats = (int)$seats;

        // Defaults for a normal purchase.
        $basePrice = (float)$sub->price;
        $discountPct = 0;
        $pricePaid = (float)$sub->price;
        $seats = 0;

        if ($isb2b) {
            if (empty($sub->b2b_enabled)) {
                throw new \moodle_exception('err_b2bnotenabled', 'local_academy');
            }
            $seats = $requestedseats;
            $option = $DB->get_record('academy_sub_seat_options',
                array('subscriptionid' => $sub->id, 'seats' => $seats));
            if (!$option) {
                throw new \moodle_exception('err_seatoptioninvalid', 'local_academy');
            }
            $price = subscription_manager::b2b_price($basePrice, $seats, $option->discount_percent);
            $discountPct = (float)$option->discount_percent;
            $pricePaid = $price['final'];
        } else {
            // Rule: a user may hold only one active NORMAL subscription at a time (B2B is separate).
            $existing = self::get_active_subscription($userid);
            if ($existing && (!isset($existing->type) || $existing->type === 'normal')) {
                throw new \moodle_exception('err_alreadyhassubscription', 'local_academy');
            }
        }

        $now = time();
        $expiresat = $now + ((int)$sub->duration_days * DAYSECS);
        $transaction = $DB->start_delegated_transaction();

        // 1. Create the purchase (snapshot of plan terms; B2B stores capacity + price breakdown).
        $purchase = new \stdClass();
        $purchase->subscriptionid   = $sub->id;
        $purchase->userid           = $userid;
        $purchase->type             = $isb2b ? 'b2b' : 'normal';
        $purchase->seats            = $seats;
        $purchase->base_price       = $basePrice;
        $purchase->discount_percent = $discountPct;
        $purchase->price_paid       = $pricePaid;
        $purchase->duration_days    = (int)$sub->duration_days;
        $purchase->status           = 'active';
        $purchase->source           = ($method === 'admin_assigned') ? 'admin_assigned' : 'online';
        $purchase->timeactivated    = $now;
        $purchase->expires_at       = $expiresat;
        $purchase->timecreated      = $now;
        $purchase->id = $DB->insert_record('academy_sub_purchases', $purchase);

        // 2. Record the payment (assumed successful — no gateway). Full price = platform revenue.
        $payment = new \stdClass();
        $payment->userid         = $userid;
        $payment->purchaseid     = $purchase->id;
        $payment->subscriptionid = $sub->id;
        $payment->amount         = $pricePaid;
        $payment->method         = $method;
        $payment->reference      = $reference;
        $payment->transaction_no = self::generate_txn();
        $payment->status         = 'success';
        $payment->timecreated    = $now;
        $payment->id = $DB->insert_record('academy_sub_payments', $payment);

        // 3. Enrolment is on-demand for EVERY subscription type: the student (or a B2B admin/member)
        //    is enrolled into a course only when they click "Enroll" (see local_payments/buy.php).
        //    The B2B buyer still becomes B2B Administrator so they can manage seats/invitations
        //    (US-B2B-1-1); their own course access is then granted on demand like any subscriber
        //    (no seat consumed). Because coverage is resolved live, any course later added to the plan
        //    becomes available to existing subscribers automatically.
        if ($isb2b) {
            self::assign_b2b_admin_role($userid);
        }

        $transaction->allow_commit();

        if ($isb2b) {
            // Best-effort confirmation (never let a notification failure roll back a paid purchase).
            if (method_exists('\local_academy\notification_manager', 'b2b_purchase_confirmed')) {
                try {
                    notification_manager::b2b_purchase_confirmed($purchase);
                } catch (\Throwable $e) {
                    debugging('b2b_purchase_confirmed failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
        }

        return array(
            'purchaseid'     => (int)$purchase->id,
            'paymentid'      => (int)$payment->id,
            'transaction_no' => $payment->transaction_no,
            'type'           => $purchase->type,
            'seats'          => (int)$seats,
            'price_paid'     => $pricePaid,
            'status'         => 'active',
            'timeactivated'  => (int)$now,
            'expires_at'     => (int)$expiresat,
            'courses'        => self::course_list($sub->id),
        );
    }

    /**
     * Assign the site's existing B2B Administrator role at system context (US-B2B-1-1).
     * The role is created via the Moodle role UI (shortname b2b_administrator); if it is missing we
     * skip silently rather than block a paid purchase.
     */
    public static function assign_b2b_admin_role($userid) {
        global $DB;
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'b2b_administrator'));
        if ($roleid) {
            role_assign($roleid, $userid, \context_system::instance()->id);
        }
    }

    /**
     * Remove the B2B Administrator system role from a user — but only if they no longer hold ANY
     * active (non-expired) B2B subscription (a buyer may own several). Call this after a B2B purchase
     * has been flipped to expired/cancelled, so the just-ended purchase is not counted.
     * The implicit "Authenticated user" role is untouched (Moodle cannot remove it).
     */
    public static function unassign_b2b_admin_role_if_unused($userid) {
        global $DB;
        $now = time();
        $others = $DB->get_records('academy_sub_purchases',
            array('userid' => $userid, 'type' => 'b2b', 'status' => 'active'));
        foreach ($others as $o) {
            if ((int)$o->expires_at === 0 || $now <= (int)$o->expires_at) {
                return; // still administering an active B2B subscription — keep the role
            }
        }
        $roleid = $DB->get_field('role', 'id', array('shortname' => 'b2b_administrator'));
        if ($roleid) {
            role_unassign($roleid, $userid, \context_system::instance()->id);
        }
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
                'name'           => format_string($r->subscription_name),
                'type'           => $r->type ?? 'normal',
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
                'name'           => format_string($r->subscription_name),
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

        // A cancelled B2B parent also ends every approved member and drops the admin role — unless the
        // buyer still owns another active B2B subscription. (Status was set to cancelled above.)
        if (isset($purchase->type) && $purchase->type === 'b2b') {
            $members = $DB->get_records('academy_b2b_memberships',
                array('purchaseid' => $purchase->id, 'status' => 'approved'));
            foreach ($members as $m) {
                $DB->update_record('academy_b2b_memberships', (object) array(
                    'id'           => $m->id,
                    'status'       => 'expired',
                    'timemodified' => $now,
                ));
                self::revoke_course_access($m->userid, $m->subscriptionid);
            }
            self::unassign_b2b_admin_role_if_unused($purchase->userid);
        }

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

    /**
     * Every source through which a user currently has active subscription access: their own active,
     * unexpired purchases (normal or B2B) AND any approved, unexpired B2B membership. Enrolment is
     * on-demand, so these sources decide what a user is eligible to enrol into — resolved live, which
     * is why courses added to a plan after purchase become available to existing subscribers at once.
     *
     * @param int $userid
     * @return \stdClass[] list of {subscriptionid, expires_at}
     */
    public static function get_active_access_sources($userid) {
        global $DB;
        $now = time();
        $sources = array();

        // The user's own purchases (normal + B2B administrator).
        $purchases = $DB->get_records('academy_sub_purchases',
            array('userid' => $userid, 'status' => 'active'));
        foreach ($purchases as $p) {
            if ((int)$p->expires_at > 0 && $now > (int)$p->expires_at) {
                continue; // effectively expired
            }
            $sources[] = (object) array(
                'subscriptionid' => (int)$p->subscriptionid,
                'expires_at'     => (int)$p->expires_at,
            );
        }

        // Approved B2B memberships — access ends at the parent subscription's expiry.
        $sql = "SELECT m.id, m.subscriptionid, p.expires_at, p.status AS parentstatus
                  FROM {academy_b2b_memberships} m
                  JOIN {academy_sub_purchases} p ON p.id = m.purchaseid
                 WHERE m.userid = :uid AND m.status = :approved";
        $rows = $DB->get_records_sql($sql,
            array('uid' => $userid, 'approved' => b2b_manager::M_APPROVED));
        foreach ($rows as $r) {
            if ($r->parentstatus !== 'active') {
                continue; // parent cancelled/expired
            }
            if ((int)$r->expires_at > 0 && $now > (int)$r->expires_at) {
                continue;
            }
            $sources[] = (object) array(
                'subscriptionid' => (int)$r->subscriptionid,
                'expires_at'     => (int)$r->expires_at,
            );
        }
        return $sources;
    }

    /**
     * The subscription access (if any) that lets a user enrol into $courseid on demand. Returns the
     * covering source so the caller can enrol until that source's expiry, or null when no active
     * subscription of theirs unlocks the course.
     *
     * @param int $userid
     * @param int $courseid
     * @return \stdClass|null {subscriptionid, expires_at}
     */
    public static function subscription_access_for_course($userid, $courseid) {
        $courseid = (int)$courseid;
        foreach (self::get_active_access_sources($userid) as $src) {
            if (in_array($courseid, subscription_manager::courses_for_subscription($src->subscriptionid), true)) {
                return $src;
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
        $courseids = subscription_manager::courses_for_subscription($subscriptionid);
        if (empty($courseids)) {
            return;
        }

        // Courses still granted by the user's OTHER currently-active access — own active purchases plus
        // approved, unexpired B2B memberships. Callers mark the ending purchase/membership non-active
        // before calling us, so it is naturally excluded from these sources.
        $stillgranted = array();
        foreach (self::get_active_access_sources($userid) as $src) {
            foreach (subscription_manager::courses_for_subscription($src->subscriptionid) as $cid) {
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
            'name'          => format_string($s->name),
            'description'   => format_string($s->description),
            'price'         => $s->price,
            'duration_days' => (int)$s->duration_days,
            'status'        => $s->status,
            'b2b_enabled'   => (int)($s->b2b_enabled ?? 0),
            'seat_options'  => isset($s->seat_options) ? $s->seat_options
                                : subscription_manager::get_seat_options($s->id, (float)$s->price),
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
