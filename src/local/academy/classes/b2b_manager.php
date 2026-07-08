<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * B2B subscription administration: invitation links, memberships (join/approve/reject/remove) and
 * seat-capacity accounting.
 *
 * Covers user stories US-B2B-1-2 .. US-B2B-1-8 (see docs/specs/B2B Administrator/).
 *
 * A "parent B2B subscription" is an {academy_sub_purchases} row of type 'b2b' owned by a B2B
 * administrator (b2b_admin_id === purchase.userid). Seats are consumed only by approved memberships;
 * each membership stores its own `consumes_seat` so historical capacity never depends on the current
 * global seat-return setting (US-B2B-1-8).
 */
class b2b_manager {

    const M_PENDING  = 'pending';
    const M_APPROVED = 'approved';
    const M_REJECTED = 'rejected';
    const M_REMOVED  = 'removed';
    const M_EXPIRED  = 'expired';

    const I_ACTIVE   = 'active';
    const I_EXPIRED  = 'expired';
    const I_DISABLED = 'disabled';
    const I_REVOKED  = 'revoked';

    // ──────────────────────────────────────────────────────────────────────────
    // Parent-subscription helpers
    // ──────────────────────────────────────────────────────────────────────────

    /** Fetch a B2B purchase this admin owns, or throw. Optionally require it to be active + unexpired. */
    public static function require_owned_purchase($purchaseid, $adminid, $requireactive = false) {
        global $DB;
        $purchase = $DB->get_record('academy_sub_purchases', array('id' => $purchaseid));
        if (!$purchase || $purchase->type !== 'b2b') {
            throw new \moodle_exception('err_subnotfound', 'local_academy');
        }
        if ((int)$purchase->userid !== (int)$adminid) {
            throw new \moodle_exception('err_b2bnotowner', 'local_academy');
        }
        if ($requireactive) {
            self::assert_parent_usable($purchase);
        }
        return $purchase;
    }

    /** Throw unless the parent B2B subscription is active and not expired. */
    protected static function assert_parent_usable($purchase) {
        if ($purchase->status !== 'active') {
            throw new \moodle_exception('err_b2bnotactive', 'local_academy');
        }
        if ((int)$purchase->expires_at > 0 && time() > (int)$purchase->expires_at) {
            throw new \moodle_exception('err_b2bexpired', 'local_academy');
        }
    }

    /** Seats currently consumed (approved, or removed-without-return) for a parent subscription. */
    public static function consumed_seats($purchaseid) {
        global $DB;
        return (int)$DB->count_records('academy_b2b_memberships',
            array('purchaseid' => $purchaseid, 'consumes_seat' => 1));
    }

    /** Whether at least one seat is free. */
    public static function has_free_seat($purchase) {
        return self::consumed_seats($purchase->id) < (int)$purchase->seats;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Invitations (US-B2B-1-2)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Generate an invitation link for a B2B subscription.
     *
     * @param int $purchaseid parent B2B subscription
     * @param int $adminid    the B2B administrator (owner)
     * @param int $expiresat  unix expiry, 0 = never
     * @return array ['id'=>, 'url'=>, 'expires_at'=>]
     */
    public static function generate_invitation($purchaseid, $adminid, $expiresat = 0) {
        global $DB, $CFG;
        $purchase = self::require_owned_purchase($purchaseid, $adminid, true);

        $token = random_string(40);
        $now = time();
        $rec = new \stdClass();
        $rec->purchaseid   = $purchase->id;
        $rec->b2b_admin_id = $adminid;
        $rec->token_hash   = hash('sha256', $token);
        $rec->token        = $token; // owner-only, so an active link can be re-displayed + copied
        $rec->status       = self::I_ACTIVE;
        $rec->expires_at   = (int)$expiresat;
        $rec->timecreated  = $now;
        $rec->timemodified = $now;
        $rec->id = $DB->insert_record('academy_b2b_invitations', $rec);

        $url = new \moodle_url('/local/academy/b2b_join.php', array('t' => $token));
        return array(
            'id'         => (int)$rec->id,
            'url'        => $url->out(false),
            'expires_at' => (int)$expiresat,
            'status'     => self::I_ACTIVE,
        );
    }

    /**
     * List a subscription's invitations. The shareable URL is returned ONLY for a still-active link
     * (from the owner-stored raw token) so the owner can view/copy it; other links expose metadata only.
     */
    public static function list_invitations($purchaseid, $adminid) {
        global $DB;
        self::require_owned_purchase($purchaseid, $adminid);
        $rows = array_values($DB->get_records('academy_b2b_invitations',
            array('purchaseid' => $purchaseid), 'timecreated DESC'));
        $out = array();
        foreach ($rows as $r) {
            $status = self::effective_invite_status($r);
            $entry = array(
                'id'          => (int)$r->id,
                'status'      => $status,
                'expires_at'  => (int)$r->expires_at,
                'timecreated' => (int)$r->timecreated,
            );
            if ($status === self::I_ACTIVE && !empty($r->token)) {
                $entry['url'] = (new \moodle_url('/local/academy/b2b_join.php',
                    array('t' => $r->token)))->out(false);
            }
            $out[] = $entry;
        }
        return $out;
    }

    /** Revoke an invitation (US-B2B-1-2 note: admin may revoke an existing link). */
    public static function revoke_invitation($invitationid, $adminid) {
        global $DB;
        $inv = $DB->get_record('academy_b2b_invitations', array('id' => $invitationid));
        if (!$inv) {
            throw new \moodle_exception('err_invalidinvite', 'local_academy');
        }
        self::require_owned_purchase($inv->purchaseid, $adminid);
        $DB->update_record('academy_b2b_invitations', (object) array(
            'id'           => $inv->id,
            'status'       => self::I_REVOKED,
            'timemodified' => time(),
        ));
    }

    /** The effective status of an invitation (auto-expires by date). */
    protected static function effective_invite_status($inv) {
        if ($inv->status === self::I_ACTIVE && (int)$inv->expires_at > 0 && time() > (int)$inv->expires_at) {
            return self::I_EXPIRED;
        }
        return $inv->status;
    }

    /**
     * Validate a raw invitation token and return its (invitation, purchase) or throw (US-B2B-1-3).
     *
     * @param string $token raw token from the link
     * @return array [invitation record, purchase record]
     */
    public static function validate_invitation($token) {
        global $DB;
        $token = trim((string)$token);
        if ($token === '') {
            throw new \moodle_exception('err_invalidinvite', 'local_academy');
        }
        $inv = $DB->get_record('academy_b2b_invitations', array('token_hash' => hash('sha256', $token)));
        if (!$inv || self::effective_invite_status($inv) !== self::I_ACTIVE) {
            throw new \moodle_exception('err_invalidinvite', 'local_academy');
        }
        $purchase = $DB->get_record('academy_sub_purchases', array('id' => $inv->purchaseid));
        if (!$purchase || $purchase->type !== 'b2b') {
            throw new \moodle_exception('err_invalidinvite', 'local_academy');
        }
        return array($inv, $purchase);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Membership lifecycle (US-B2B-1-3 .. US-B2B-1-7)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Join through an invitation link (US-B2B-1-3). Idempotent: reopening the same link returns the
     * existing membership instead of creating a duplicate. Auto-approves when the platform setting is
     * on and a seat is free (US-B2B-1-4).
     *
     * @param string $token raw invitation token
     * @param int $userid the joining user
     * @return array ['membershipid'=>, 'status'=>]
     */
    public static function join($token, $userid) {
        global $DB;
        list($inv, $purchase) = self::validate_invitation($token);

        // The B2B administrator cannot be a member of their own subscription.
        if ((int)$userid === (int)$purchase->userid) {
            throw new \moodle_exception('err_b2bnotowner', 'local_academy');
        }

        $existing = $DB->get_record('academy_b2b_memberships',
            array('purchaseid' => $purchase->id, 'userid' => $userid));
        if ($existing) {
            return array('membershipid' => (int)$existing->id, 'status' => $existing->status);
        }

        $now = time();
        $m = new \stdClass();
        $m->purchaseid     = $purchase->id;
        $m->subscriptionid = $purchase->subscriptionid;
        $m->b2b_admin_id   = $purchase->userid;
        $m->userid         = $userid;
        $m->invitationid   = (int)$inv->id;
        $m->status         = self::M_PENDING;
        $m->consumes_seat  = 0;
        $m->approved_by    = 0;
        $m->approved_at    = 0;
        $m->removed_by     = 0;
        $m->removed_at     = 0;
        $m->timecreated    = $now;
        $m->timemodified   = $now;
        $m->id = $DB->insert_record('academy_b2b_memberships', $m);

        // Auto-approval (US-B2B-1-4): only when enabled, the parent is usable, and a seat is free.
        $autoapprove = (int)settings_manager::get('b2b_auto_approve_invited_users') === 1;
        if ($autoapprove && $purchase->status === 'active'
                && !((int)$purchase->expires_at > 0 && $now > (int)$purchase->expires_at)
                && self::has_free_seat($purchase)) {
            self::do_approve($m, $purchase, $purchase->userid);
            return array('membershipid' => (int)$m->id, 'status' => self::M_APPROVED);
        }

        // Otherwise notify the B2B admin that someone is waiting.
        notification_manager::b2b_membership_pending($m);
        return array('membershipid' => (int)$m->id, 'status' => self::M_PENDING);
    }

    /** Approve a pending membership (US-B2B-1-5). */
    public static function approve_membership($membershipid, $adminid) {
        global $DB;
        $m = self::get_membership($membershipid);
        $purchase = self::require_owned_purchase($m->purchaseid, $adminid, true);

        if ($m->status === self::M_APPROVED) {
            return; // idempotent — no double seat
        }
        if ($m->status !== self::M_PENDING) {
            throw new \moodle_exception('err_notpending', 'local_academy');
        }
        if (!self::has_free_seat($purchase)) {
            throw new \moodle_exception('err_nofreeseats', 'local_academy');
        }
        self::do_approve($m, $purchase, $adminid);
    }

    /** Shared approval body (used by manual approve + auto-approve). Assumes a free seat exists. */
    protected static function do_approve($m, $purchase, $approverid) {
        global $DB;
        $now = time();
        $DB->update_record('academy_b2b_memberships', (object) array(
            'id'            => $m->id,
            'status'        => self::M_APPROVED,
            'consumes_seat' => 1,
            'approved_by'   => (int)$approverid,
            'approved_at'   => $now,
            'timemodified'  => $now,
        ));
        // Grant the same course access a normal subscriber would get, ending at the parent expiry.
        subscription_purchase_manager::grant_course_access($m->userid, $purchase->subscriptionid,
            (int)$purchase->expires_at);

        $m->status = self::M_APPROVED;
        $m->approved_by = (int)$approverid;
        notification_manager::b2b_membership_approved($m);
    }

    /** Reject a pending membership (US-B2B-1-6). */
    public static function reject_membership($membershipid, $adminid, $reason = '') {
        global $DB;
        $m = self::get_membership($membershipid);
        self::require_owned_purchase($m->purchaseid, $adminid);
        if ($m->status !== self::M_PENDING) {
            throw new \moodle_exception('err_notpending', 'local_academy');
        }
        $DB->update_record('academy_b2b_memberships', (object) array(
            'id'            => $m->id,
            'status'        => self::M_REJECTED,
            'reject_reason' => (string)$reason,
            'timemodified'  => time(),
        ));
        $m->status = self::M_REJECTED;
        $m->reject_reason = (string)$reason;
        notification_manager::b2b_membership_rejected($m);
    }

    /**
     * Remove an approved member (US-B2B-1-7). Revokes course access; the seat is freed or kept per the
     * `b2b_return_seat_after_user_removal` setting, and the result is stored on the record so future
     * capacity math is stable regardless of later setting changes.
     */
    public static function remove_member($membershipid, $adminid) {
        global $DB;
        $m = self::get_membership($membershipid);
        self::require_owned_purchase($m->purchaseid, $adminid);
        if ($m->status !== self::M_APPROVED) {
            throw new \moodle_exception('err_notapproved', 'local_academy');
        }

        $returnseat = (int)settings_manager::get('b2b_return_seat_after_user_removal') === 1;
        $now = time();
        $DB->update_record('academy_b2b_memberships', (object) array(
            'id'            => $m->id,
            'status'        => self::M_REMOVED,
            'consumes_seat' => $returnseat ? 0 : 1,
            'removed_by'    => (int)$adminid,
            'removed_at'    => $now,
            'timemodified'  => $now,
        ));

        // Revoke this subscription's course access (unless another active sub of theirs still grants it).
        subscription_purchase_manager::revoke_course_access($m->userid, $m->subscriptionid);

        $m->status = self::M_REMOVED;
        $m->removed_by = (int)$adminid;
        notification_manager::b2b_member_removed($m);
    }

    /** Fetch a membership or throw. */
    public static function get_membership($membershipid) {
        global $DB;
        $m = $DB->get_record('academy_b2b_memberships', array('id' => $membershipid));
        if (!$m) {
            throw new \moodle_exception('err_membershipnotfound', 'local_academy');
        }
        return $m;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Dashboard reads (US-B2B-1-8)
    // ──────────────────────────────────────────────────────────────────────────

    /** Capacity + per-status counts for a parent B2B subscription. */
    public static function capacity_stats($purchaseid, $adminid) {
        global $DB;
        $purchase = self::require_owned_purchase($purchaseid, $adminid);

        $counts = array(
            self::M_PENDING => 0, self::M_APPROVED => 0, self::M_REJECTED => 0,
            self::M_REMOVED => 0, self::M_EXPIRED => 0,
        );
        $removedreturned = 0;
        $removedkept = 0;
        $members = $DB->get_records('academy_b2b_memberships', array('purchaseid' => $purchaseid));
        foreach ($members as $m) {
            if (isset($counts[$m->status])) {
                $counts[$m->status]++;
            }
            if ($m->status === self::M_REMOVED) {
                if ((int)$m->consumes_seat === 1) { $removedkept++; } else { $removedreturned++; }
            }
        }
        $consumed = self::consumed_seats($purchaseid);
        return array(
            'purchaseid'        => (int)$purchaseid,
            'capacity'          => (int)$purchase->seats,
            'consumed'          => $consumed,
            'available'         => max(0, (int)$purchase->seats - $consumed),
            'pending'           => $counts[self::M_PENDING],
            'approved'          => $counts[self::M_APPROVED],
            'rejected'          => $counts[self::M_REJECTED],
            'removed'           => $counts[self::M_REMOVED],
            'removed_returned'  => $removedreturned,
            'removed_kept'      => $removedkept,
            'expires_at'        => (int)$purchase->expires_at,
            'status'            => subscription_purchase_manager::effective_status($purchase),
        );
    }

    /** List members of a parent B2B subscription, optionally filtered by status. */
    public static function list_members($purchaseid, $adminid, $status = '') {
        global $DB;
        self::require_owned_purchase($purchaseid, $adminid);
        $conditions = array('purchaseid' => $purchaseid);
        if ($status !== '') {
            $conditions['status'] = $status;
        }
        $sql = "SELECT m.*, u.firstname, u.lastname, u.email
                  FROM {academy_b2b_memberships} m
                  JOIN {user} u ON u.id = m.userid
                 WHERE m.purchaseid = :pid" . ($status !== '' ? " AND m.status = :status" : "") . "
              ORDER BY m.timecreated DESC";
        $params = array('pid' => $purchaseid);
        if ($status !== '') { $params['status'] = $status; }
        $rows = $DB->get_records_sql($sql, $params);
        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'id'            => (int)$r->id,
                'userid'        => (int)$r->userid,
                'user_fullname' => fullname($r),
                'user_email'    => $r->email,
                'status'        => $r->status,
                'consumes_seat' => (int)$r->consumes_seat,
                'approved_at'   => (int)$r->approved_at,
                'removed_at'    => (int)$r->removed_at,
                'reject_reason' => (string)$r->reject_reason,
                'timecreated'   => (int)$r->timecreated,
            );
        }
        return $out;
    }

    /** The B2B subscriptions this user administers (active first), each with capacity stats. */
    public static function get_my_b2b_subscriptions($adminid) {
        global $DB;
        $sql = "SELECT sp.*, s.name AS subscription_name
                  FROM {academy_sub_purchases} sp
                  JOIN {academy_subscriptions} s ON s.id = sp.subscriptionid
                 WHERE sp.userid = :uid AND sp.type = 'b2b'
              ORDER BY sp.timecreated DESC";
        $rows = $DB->get_records_sql($sql, array('uid' => $adminid));
        $out = array();
        foreach ($rows as $r) {
            $consumed = self::consumed_seats($r->id);
            $out[] = array(
                'purchaseid'    => (int)$r->id,
                'subscriptionid' => (int)$r->subscriptionid,
                'name'          => format_string($r->subscription_name),
                'seats'         => (int)$r->seats,
                'consumed'      => $consumed,
                'available'     => max(0, (int)$r->seats - $consumed),
                'price_paid'    => $r->price_paid,
                'status'        => subscription_purchase_manager::effective_status($r),
                'expires_at'    => (int)$r->expires_at,
            );
        }
        return $out;
    }
}
