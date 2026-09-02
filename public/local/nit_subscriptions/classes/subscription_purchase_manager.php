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
 * Subscription purchases: fulfilment after a successful gateway payment, and the active-subscription
 * lookup used for on-demand course access.
 *
 * Course access is resolved LIVE (a purchase record grants coverage; real Moodle enrolment happens
 * on demand when the student opens a covered course — see local_payments price_resolver + buy.php).
 * So fulfilment only needs to create the purchase record.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_subscriptions;

defined('MOODLE_INTERNAL') || die();

/**
 * Subscription purchase + access manager.
 */
class subscription_purchase_manager {

    /** @var string Active purchase status. */
    const STATUS_ACTIVE    = 'active';
    /** @var string Expired purchase status. */
    const STATUS_EXPIRED   = 'expired';
    /** @var string Cancelled purchase status. */
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Create a subscription purchase after a successful payment. Idempotent by gateway reference so a
     * re-delivered webhook does not create a second purchase.
     *
     * @param int $userid
     * @param int $subscriptionid
     * @param float $pricepaid amount actually charged (after coupon/offer)
     * @param string $reference gateway order id (for idempotency)
     * @param string $type normal | b2b
     * @param int $seats B2B seat capacity (0 for normal)
     * @return array purchase summary
     */
    public static function fulfil_from_gateway($userid, $subscriptionid, $pricepaid, $reference = '',
            $type = 'normal', $seats = 0) {
        global $DB;

        $reference = trim((string) $reference);

        // Serialise concurrent fulfilments of the SAME gateway reference. The
        // server-to-server webhook and the browser-redirect callback commonly
        // fire near-simultaneously; without this, both pass the "does a purchase
        // with this reference exist yet?" check and each inserts a row, doubling
        // the paid subscription. The lock is keyed on the reference so different
        // orders never block each other. Empty reference (e.g. non-gateway paths)
        // skips the lock — it also skips the idempotency check below.
        $lock = null;
        if ($reference !== '') {
            $lockfactory = \core\lock\lock_config::get_lock_factory('local_nit_subscriptions');
            $lock = $lockfactory->get_lock('fulfil_' . sha1($reference), 10);
        }

        try {
            if ($reference !== '') {
                $existing = $DB->get_record('nit_sub_purchase', ['reference' => $reference]);
                if ($existing) {
                    return self::summary($existing);
                }
            }

            return self::insert_purchase($DB, $userid, $subscriptionid, $pricepaid, $reference, $type, $seats);
        } finally {
            if ($lock) {
                $lock->release();
            }
        }
    }

    /**
     * Build and insert the purchase row. Split out of fulfil_from_gateway so the
     * insert happens inside the reference lock held by the caller.
     *
     * @param \moodle_database $DB
     * @param int $userid
     * @param int $subscriptionid
     * @param float $pricepaid
     * @param string $reference
     * @param string $type
     * @param int $seats
     * @return array purchase summary
     */
    private static function insert_purchase($DB, $userid, $subscriptionid, $pricepaid, $reference, $type, $seats) {
        $sub = $DB->get_record('nit_subscription', ['id' => $subscriptionid], '*', MUST_EXIST);
        $isb2b = ($type === 'b2b');

        // Business rule: one active NORMAL subscription per user — with one exception, a
        // RENEWAL of the very plan they already hold. That case is not a duplicate payment,
        // it is the same plan bought for a second period, and it is handled below by starting
        // the new period where the old one ends rather than today (see $startsat).
        //
        // Anything else — a second payment for a DIFFERENT plan while one is still live — is
        // still refused: do NOT grant a second, return the existing subscription and log it so
        // an admin can refund the duplicate charge. (Catches the sequential case; the rare
        // truly-simultaneous cross-checkout case may still slip through, but no money is lost
        // — the buyer paid — only the one-subscription rule.)
        $renewalof = null;
        if (!$isb2b) {
            $active = self::get_active_subscription($userid);
            $activeisnormal = $active && (!isset($active->type) || $active->type === 'normal');

            if ($activeisnormal && (int) $active->subscriptionid === (int) $sub->id) {
                $renewalof = $active;
            } else if ($activeisnormal) {
                debugging("local_nit_subscriptions: duplicate active-normal subscription payment for user {$userid}"
                    . " (reference {$reference}) — no second subscription granted; refund required.", DEBUG_NORMAL);
                return self::summary($active);
            }
        }

        $basePrice = (float) $sub->price;
        $discountPct = 0;
        if ($isb2b && $seats > 0) {
            $option = $DB->get_record('nit_sub_seat_option', ['subscriptionid' => $sub->id, 'seats' => (int) $seats]);
            if ($option) {
                $discountPct = (float) $option->discount_percent;
            }
        }

        $now = time();

        // A renewal is bought for the period AFTER the one still running, not for the next N
        // days from today. Somebody who renews with 10 days left keeps those 10 days and the
        // new period is added on the end — which is the whole reason to renew early rather
        // than wait for the plan to lapse. A first purchase (or a renewal of something that
        // has already run out) simply starts now.
        $startsat = ($renewalof && (int) $renewalof->expires_at > $now) ? (int) $renewalof->expires_at : $now;
        $expiresat = $startsat + ((int) $sub->duration_days * DAYSECS);

        $purchase = new \stdClass();
        $purchase->subscriptionid   = $sub->id;
        $purchase->userid           = $userid;
        $purchase->type             = $isb2b ? 'b2b' : 'normal';
        $purchase->seats            = (int) $seats;
        $purchase->base_price       = $basePrice;
        $purchase->discount_percent = $discountPct;
        $purchase->price_paid       = (float) $pricepaid;
        $purchase->duration_days    = (int) $sub->duration_days;
        $purchase->status           = self::STATUS_ACTIVE;
        $purchase->source           = ($reference === 'admin_assigned') ? 'admin_assigned' : 'online';
        $purchase->reference        = $reference;
        $purchase->timeactivated    = $now;
        $purchase->expires_at       = $expiresat;
        $purchase->timecreated      = $now;
        $purchase->id = $DB->insert_record('nit_sub_purchase', $purchase);

        // On a renewal the student is already enrolled in the plan's courses, with an
        // enrolment that ends on the OLD date. Push those end dates out now, so access runs
        // straight through the changeover instead of lapsing on the old date and being
        // re-granted the next time they open a course.
        if ($renewalof) {
            self::extend_access_to((int) $sub->id, $userid, $expiresat);
        }

        // "Your subscription is active" — the admin-editable email under
        // Site administration › Plugins › Local plugins › Purchase &
        // registration emails. Sent from here rather than from the payment
        // gateway so an admin-assigned plan is announced too, and only on a
        // real insert, so a replayed webhook cannot send it twice. A failure
        // here must never cost the student the subscription they paid for.
        if (class_exists('\local_nit_emails\mailer')) {
            try {
                \local_nit_emails\mailer::send_subscription_purchase($purchase);
            } catch (\Throwable $e) {
                debugging('local_nit_subscriptions: subscription email failed: ' . $e->getMessage(), DEBUG_NORMAL);
            }
        }

        return self::summary($purchase);
    }

    /**
     * The user's current active (non-expired) subscription purchase, or null.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get_active_subscription($userid) {
        $rows = self::get_active_subscriptions($userid);
        return $rows ? reset($rows) : null;
    }

    /**
     * ALL of the user's active (non-expired) subscription purchases, newest activation first.
     *
     * A user normally holds at most one, but a B2B seat can sit alongside a personal plan, and
     * a renewal can overlap the plan it replaces. Expiry has to look at every one of them before
     * it takes a course away — see {@see revoke_course_access()}.
     *
     * @param int $userid
     * @param int $excludeid purchase id to leave out (the one being expired/cancelled)
     * @return \stdClass[] purchase records
     */
    public static function get_active_subscriptions($userid, $excludeid = 0) {
        global $DB;
        $now = time();
        $rows = $DB->get_records('nit_sub_purchase',
            ['userid' => (int) $userid, 'status' => self::STATUS_ACTIVE], 'timeactivated DESC');
        $out = [];
        foreach ($rows as $r) {
            if ((int) $r->id === (int) $excludeid) {
                continue;
            }
            if ((int) $r->expires_at === 0 || $now <= (int) $r->expires_at) {
                $out[$r->id] = $r;
            }
        }
        return $out;
    }

    /**
     * The live purchase that decides how long the user's access actually runs.
     *
     * {@see get_active_subscription()} answers "which subscription is theirs" by activation
     * order, which is the right question for most callers. It is the WRONG question for
     * anything that quotes a deadline: once a renewal is stacked on top of a running period
     * the user holds two live rows for the same plan, and the one activated last is not
     * necessarily the one that runs longest — a renewal activated in the same second as
     * another purchase ties on timeactivated and the tie breaks arbitrarily. Reporting that
     * row's date would understate the time the user has already paid for.
     *
     * @param int $userid
     * @param int $subscriptionid restrict to one plan; 0 = any
     * @return \stdClass|null the active purchase with the latest expiry, or null
     */
    public static function longest_active($userid, $subscriptionid = 0) {
        $best = null;
        foreach (self::get_active_subscriptions($userid) as $purchase) {
            if ($subscriptionid && (int) $purchase->subscriptionid !== (int) $subscriptionid) {
                continue;
            }
            // An open-ended purchase (expires_at 0) outruns every dated one.
            if ($best === null
                    || (int) $purchase->expires_at === 0
                    || ((int) $best->expires_at !== 0 && (int) $purchase->expires_at > (int) $best->expires_at)) {
                $best = $purchase;
            }
        }
        return $best;
    }

    /**
     * Whether the user already holds an active NORMAL subscription (only one allowed at a time).
     *
     * @param int $userid
     * @return bool
     */
    public static function has_active_normal($userid) {
        $active = self::get_active_subscription($userid);
        return $active && (!isset($active->type) || $active->type === 'normal');
    }

    /**
     * Did this user once reach this course through a subscription that has since run out?
     *
     * The question the course page needs answered before it decides what to say to somebody
     * standing outside a course: a first-time visitor is being SOLD something, while a lapsed
     * subscriber is being asked to come BACK — and they need telling that their progress
     * survived, which is the thing people actually worry about.
     *
     * Answered from the purchase history rather than from enrolments, because expiry unenrols:
     * by the time anyone asks, the enrolment that would have proved it is gone.
     *
     * @param int $courseid
     * @param int $userid
     * @return int the plan to renew (the most recently lapsed one covering this course), or 0
     */
    public static function lapsed_subscription_id($courseid, $userid) {
        global $DB;

        $courseid = (int) $courseid;
        $userid = (int) $userid;
        if ($courseid <= 0 || $userid <= 0) {
            return 0;
        }

        // Asked once per course per request: the course page calls this while rendering, and
        // the answer cannot change underneath it.
        static $cache = [];
        $key = $courseid . ':' . $userid;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $covers = function ($subscriptionid) use ($courseid) {
            return in_array($courseid,
                subscription_manager::courses_for_subscription((int) $subscriptionid), true);
        };

        // Still covered by something live — nothing has lapsed, whatever the history says.
        foreach (self::get_active_subscriptions($userid) as $live) {
            if ($covers($live->subscriptionid)) {
                return $cache[$key] = 0;
            }
        }

        $past = $DB->get_records('nit_sub_purchase',
            ['userid' => $userid, 'status' => self::STATUS_EXPIRED],
            'expires_at DESC', 'id, subscriptionid');

        foreach ($past as $purchase) {
            if ($covers($purchase->subscriptionid)) {
                return $cache[$key] = (int) $purchase->subscriptionid;
            }
        }

        return $cache[$key] = 0;
    }

    /**
     * Grant a user access to one course covered by their active subscription, as a
     * real Moodle enrolment that ends when the subscription expires.
     *
     * Idempotent, and safe to call again on renewal: an existing manual enrolment whose
     * end date is EARLIER than the new subscription's expiry is pushed out to the new
     * date. (Without that, renewing before the old plan lapsed left the enrolment pinned
     * to the old expiry, so access still died on the old date.) An open-ended enrolment
     * — a bought course, a free registration, a teacher's manual enrolment — is never
     * shortened or touched.
     *
     * SECURITY: this grants course access, so the CALLER must first verify the
     * user holds an active subscription that actually covers this course (see
     * local/payments/buy.php, which gates on courses_for_subscription + sesskey).
     *
     * @param int $courseid
     * @param int $userid
     * @param int $until unix time the access should end (subscription expiry); 0 = no end date
     * @return bool true on success
     */
    public static function grant_course_access($courseid, $userid, $until = 0) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');

        $courseid = (int) $courseid;
        $userid = (int) $userid;
        $context = \context_course::instance($courseid);

        // Already actively enrolled — extend the end date if this subscription runs longer,
        // otherwise leave the enrolment exactly as it is.
        if (is_enrolled($context, $userid, '', true)) {
            self::extend_enrolment_end($courseid, $userid, (int) $until);
            return true;
        }

        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            return false;
        }

        // The course's enabled manual enrolment instance (create one if absent).
        $instance = $DB->get_record('enrol',
            ['courseid' => $courseid, 'enrol' => 'manual', 'status' => ENROL_INSTANCE_ENABLED],
            '*', IGNORE_MULTIPLE);
        if (!$instance) {
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $instanceid = $plugin->add_default_instance($course);
            if (!$instanceid) {
                return false;
            }
            $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        }

        // Enrol as the instance's default role (student), ending when the
        // subscription expires so access lapses automatically.
        //
        // $recovergrades is passed explicitly rather than left to $CFG->recovergradesdefault
        // (which core ships OFF). It matters here in a way it does not for a normal
        // enrolment: when a subscription lapses we unenrol, and core's unenrol_user() clears
        // the student's grades out of the course as it goes. Without this flag a renewal
        // would return them to a course that has forgotten every mark they earned — while
        // still showing their completion ticks, which is worse than either extreme.
        $timeend = ((int) $until > time()) ? (int) $until : 0;
        $plugin->enrol_user($instance, $userid, $instance->roleid, time(), $timeend, null, true);

        return true;
    }

    /**
     * Push every enrolment a plan granted this user out to a later deadline (renewal).
     *
     * Only touches enrolments this plugin could have created — a dated manual one — and only
     * ever lengthens them, so a course the student bought outright or was enrolled in by a
     * teacher is never affected.
     *
     * @param int $subscriptionid the plan whose courses to extend
     * @param int $userid
     * @param int $until unix time the access should now end
     * @return void
     */
    public static function extend_access_to($subscriptionid, $userid, $until) {
        global $DB;

        foreach (subscription_manager::courses_for_subscription((int) $subscriptionid) as $courseid) {
            if (!$DB->record_exists('course', ['id' => (int) $courseid])) {
                continue;
            }
            try {
                self::extend_enrolment_end((int) $courseid, (int) $userid, (int) $until);
            } catch (\Throwable $e) {
                // One broken course must not cost the student the renewal they just paid for.
                debugging('local_nit_subscriptions: extending enrolment in course ' . (int) $courseid
                    . ' failed: ' . $e->getMessage(), DEBUG_NORMAL);
            }
        }
    }

    /**
     * The user's manual enrolment records in a course (there is normally one).
     *
     * Returns the user_enrolments row plus the id of its enrol instance, so callers can read
     * timeend (which is what marks an enrolment as subscription-granted, since only this
     * plugin gives a manual enrolment an end date) and still reach the instance needed to
     * unenrol. Disabled instances are included on purpose: a lingering enrolment on a
     * disabled instance still has to be cleaned up.
     *
     * @param int $courseid
     * @param int $userid
     * @return \stdClass[] rows of {ueid, enrolid, timeend, uestatus}
     */
    private static function manual_enrolments($courseid, $userid) {
        global $DB;
        $sql = "SELECT ue.id AS ueid, ue.enrolid, ue.timeend, ue.status AS uestatus
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = :courseid AND e.enrol = 'manual' AND ue.userid = :userid";
        return $DB->get_records_sql($sql, ['courseid' => (int) $courseid, 'userid' => (int) $userid]);
    }

    /**
     * Push an existing manual enrolment's end date out to $until (renewal).
     *
     * Only ever extends: an enrolment that already ends later, or has no end date at all,
     * is left alone so this can never shorten access someone paid for separately.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $until unix time; 0 (unlimited plan) clears the end date
     * @return void
     */
    private static function extend_enrolment_end($courseid, $userid, $until) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');

        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            return;
        }
        foreach (self::manual_enrolments($courseid, $userid) as $row) {
            $timeend = (int) $row->timeend;
            if ($timeend === 0) {
                continue; // Already open-ended — nothing to extend.
            }
            if ($until !== 0 && $until <= $timeend) {
                continue; // Current enrolment already runs at least as long.
            }
            $instance = $DB->get_record('enrol', ['id' => (int) $row->enrolid], '*', MUST_EXIST);
            $plugin->update_user_enrol($instance, (int) $userid, null, null, (int) $until);
        }
    }

    /**
     * Take back the course access one subscription purchase granted: unenrol the user from
     * every course that plan unlocked.
     *
     * Deliberately conservative — a course is SKIPPED when:
     *  - another still-active subscription of theirs also covers it (renewal, or a B2B seat
     *    alongside a personal plan);
     *  - they bought that single course separately (a completed local_payments transaction);
     *  - their enrolment is open-ended (timeend = 0) while the plan had an end date — that
     *    enrolment came from a free registration, a purchase or a teacher, not from here;
     *  - their enrolment ends LATER than this plan did — something else granted it.
     *
     * Only manual enrolments are touched; self/cohort/guest access is none of our business.
     *
     * @param \stdClass $purchase nit_sub_purchase record
     * @return int number of courses the user was unenrolled from
     */
    public static function revoke_course_access($purchase) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/enrollib.php');

        $userid = (int) $purchase->userid;
        $expiresat = (int) $purchase->expires_at;

        $courseids = subscription_manager::courses_for_subscription((int) $purchase->subscriptionid);
        if (empty($courseids)) {
            return 0;
        }

        $plugin = enrol_get_plugin('manual');
        if (!$plugin) {
            return 0;
        }

        // Courses still covered by another live subscription of this user's.
        $stillcovered = [];
        foreach (self::get_active_subscriptions($userid, (int) $purchase->id) as $other) {
            foreach (subscription_manager::courses_for_subscription((int) $other->subscriptionid) as $cid) {
                $stillcovered[$cid] = true;
            }
        }

        $haspayments = class_exists('\local_payments\price_resolver');
        $removed = 0;

        foreach ($courseids as $courseid) {
            $courseid = (int) $courseid;

            if (isset($stillcovered[$courseid])) {
                continue;
            }
            // The course is gone (its context with it) — nothing to unenrol from.
            if (!$DB->record_exists('course', ['id' => $courseid])) {
                continue;
            }
            // Bought on its own — that purchase, not the subscription, owns the access.
            if ($haspayments && \local_payments\price_resolver::is_purchased($courseid, $userid)) {
                continue;
            }

            foreach (self::manual_enrolments($courseid, $userid) as $row) {
                $timeend = (int) $row->timeend;

                if ($expiresat > 0) {
                    // A dated plan only ever produced a dated enrolment ending on its expiry.
                    if ($timeend === 0 || $timeend > $expiresat) {
                        continue;
                    }
                }

                $instance = $DB->get_record('enrol', ['id' => (int) $row->enrolid], '*', MUST_EXIST);
                $plugin->unenrol_user($instance, $userid);
                $removed++;
                break;
            }
        }

        return $removed;
    }

    /**
     * Close out every subscription purchase whose deadline has passed: flag the purchase
     * `expired` and unenrol the student from the courses it had unlocked.
     *
     * This is the piece that makes a subscription actually END. Until it runs, an expired
     * purchase keeps status `active` in the database and the student keeps a (lapsed but
     * present) enrolment in every covered course. Driven by the expire_subscriptions
     * scheduled task; safe to run repeatedly and safe to run on a backlog, since a purchase
     * is only ever processed once.
     *
     * @param int $now unix time to expire against (defaults to now; injectable for tests)
     * @return array{purchases:int, unenrolments:int}
     */
    public static function expire_due_purchases($now = 0) {
        global $DB;

        $now = $now > 0 ? (int) $now : time();

        $due = $DB->get_records_select('nit_sub_purchase',
            'status = :status AND expires_at > 0 AND expires_at <= :now',
            ['status' => self::STATUS_ACTIVE, 'now' => $now],
            'expires_at ASC');

        $unenrolments = 0;
        $count = 0;
        foreach ($due as $purchase) {
            // Flag it expired FIRST, so revoke_course_access no longer sees it as one of the
            // user's live subscriptions, and so a crash mid-revoke cannot leave it looking
            // active forever (the next run picks the leftovers up from the enrolment side).
            $DB->update_record('nit_sub_purchase', (object) [
                'id'     => $purchase->id,
                'status' => self::STATUS_EXPIRED,
            ]);
            $purchase->status = self::STATUS_EXPIRED;

            try {
                $unenrolments += self::revoke_course_access($purchase);
            } catch (\Throwable $e) {
                // One broken course must not stop the rest of the queue.
                debugging("local_nit_subscriptions: revoking access for purchase {$purchase->id} failed: "
                    . $e->getMessage(), DEBUG_NORMAL);
            }
            $count++;
        }

        return ['purchases' => $count, 'unenrolments' => $unenrolments];
    }

    /**
     * The effective status of a purchase record (expired if past its expiry).
     *
     * @param \stdClass $record
     * @return string
     */
    public static function effective_status($record) {
        if ($record->status !== self::STATUS_ACTIVE) {
            return $record->status;
        }
        if ((int) $record->expires_at > 0 && time() > (int) $record->expires_at) {
            return self::STATUS_EXPIRED;
        }
        return self::STATUS_ACTIVE;
    }

    /**
     * The user's subscription purchases, active first, for a "My subscriptions" view.
     *
     * @param int $userid
     * @return array
     */
    public static function get_my_subscriptions($userid) {
        global $DB;
        $sql = "SELECT sp.*, s.name AS subscription_name
                  FROM {nit_sub_purchase} sp
                  JOIN {nit_subscription} s ON s.id = sp.subscriptionid
                 WHERE sp.userid = :uid
              ORDER BY sp.timecreated DESC";
        $rows = $DB->get_records_sql($sql, ['uid' => $userid]);
        $now = time();
        $out = [];
        foreach ($rows as $r) {
            $status = self::effective_status($r);
            $daysleft = ($status === self::STATUS_ACTIVE && (int) $r->expires_at > 0)
                ? max(0, (int) ceil(((int) $r->expires_at - $now) / DAYSECS)) : 0;
            $out[] = [
                'id'             => (int) $r->id,
                'subscriptionid' => (int) $r->subscriptionid,
                'name'           => format_string(subscription_manager::resolve_mlang($r->subscription_name)),
                'type'           => $r->type ?? 'normal',
                'price_paid'     => (float) $r->price_paid,
                'status'         => $status,
                'timeactivated'  => (int) $r->timeactivated,
                'expires_at'     => (int) $r->expires_at,
                'remaining_days' => $daysleft,
                'duration_days'  => (int) $r->duration_days,
                // Courses the plan unlocks, as {id, fullname} objects — the catalog reads course.id
                // to show "included in your subscription" coverage.
                'courses'        => subscription_manager::courses_detail((int) $r->subscriptionid),
            ];
        }
        usort($out, function ($a, $b) {
            if (($a['status'] === self::STATUS_ACTIVE) !== ($b['status'] === self::STATUS_ACTIVE)) {
                return $a['status'] === self::STATUS_ACTIVE ? -1 : 1;
            }
            return $b['timeactivated'] - $a['timeactivated'];
        });
        return $out;
    }

    /**
     * The current user's subscription PAYMENTS, newest first, for a "Payment history" screen.
     *
     * Subscriptions are paid through the gateway (local_payments), so the money records live in
     * local_payments_transactions with metadata item_type=subscription — not in nit_sub_purchase
     * (which records fulfilment/access). This returns every subscription checkout the user started,
     * including failed/abandoned ones, so the history is complete. Degrades to [] if local_payments
     * is not installed.
     *
     * @param int $userid
     * @return array
     */
    public static function get_subscription_payment_history($userid) {
        global $DB;

        if (!$DB->get_manager()->table_exists('local_payments_transactions')) {
            return [];
        }

        $rows = $DB->get_records('local_payments_transactions',
            ['userid' => $userid, 'courseid' => 0], 'timecreated DESC');
        $out = [];
        foreach ($rows as $r) {
            $meta = json_decode($r->metadata ?? '{}');
            // courseid=0 is the subscription sentinel, but guard on item_type too so any
            // future non-course, non-subscription item can't leak into this history.
            if (($meta->item_type ?? '') !== 'subscription') {
                continue;
            }
            $name = $meta->subscription_name ?? '';
            if ($name === '' && !empty($meta->item_id)) {
                $name = (string) $DB->get_field('nit_subscription', 'name', ['id' => (int) $meta->item_id]);
            }
            $out[] = [
                'id'             => (int) $r->id,
                'subscriptionid' => (int) ($meta->item_id ?? 0),
                'name'           => format_string(subscription_manager::resolve_mlang($name)),
                'order_id'       => (string) $r->order_id,
                'amount'         => (float) $r->amount,
                'currency'       => (string) $r->currency,
                'status'         => (string) $r->status,
                'payment_method' => (string) ($r->payment_method_type ?? ''),
                'coupon_code'    => (string) ($meta->coupon_code ?? ''),
                'timecreated'    => (int) $r->timecreated,
            ];
        }
        return $out;
    }

    /**
     * All user subscription purchases for the admin table, newest first.
     *
     * @return array
     */
    public static function get_all_user_subscriptions() {
        global $DB;
        $sql = "SELECT sp.*, s.name AS subscription_name, u.firstname, u.lastname, u.email
                  FROM {nit_sub_purchase} sp
                  JOIN {nit_subscription} s ON s.id = sp.subscriptionid
                  JOIN {user} u ON u.id = sp.userid
              ORDER BY sp.timecreated DESC";
        $out = [];
        foreach ($DB->get_records_sql($sql) as $r) {
            $out[] = [
                'id'            => (int) $r->id,
                'userid'        => (int) $r->userid,
                'user_fullname' => fullname($r),
                'user_email'    => $r->email,
                'name'          => format_string(subscription_manager::resolve_mlang($r->subscription_name)),
                'type'          => $r->type,
                'price_paid'    => (float) $r->price_paid,
                'status'        => self::effective_status($r),
                'expires_at'    => (int) $r->expires_at,
            ];
        }
        return $out;
    }

    /**
     * Cancel a user's subscription purchase (admin unsubscribe).
     * Also unenrols the user from every course that was covered by this subscription plan.
     *
     * @param int $purchaseid
     * @return void
     */
    public static function unsubscribe($purchaseid) {
        global $DB;

        $purchase = $DB->get_record('nit_sub_purchase', ['id' => $purchaseid], '*', MUST_EXIST);

        // Cancel the purchase record.
        $DB->update_record('nit_sub_purchase', (object) [
            'id'     => $purchase->id,
            'status' => self::STATUS_CANCELLED,
        ]);
        $purchase->status = self::STATUS_CANCELLED;

        // Same revoke path natural expiry uses, so an admin cancellation and a lapsed deadline
        // leave the student in exactly the same state — and neither one takes away a course the
        // student bought separately or still holds through another subscription.
        self::revoke_course_access($purchase);
    }

    /**
     * Compact summary of a purchase record.
     *
     * @param \stdClass $p
     * @return array
     */
    private static function summary($p) {
        return [
            'purchaseid'    => (int) $p->id,
            'subscriptionid' => (int) $p->subscriptionid,
            'type'          => $p->type,
            'price_paid'    => (float) $p->price_paid,
            'status'        => self::effective_status($p),
            'timeactivated' => (int) $p->timeactivated,
            'expires_at'    => (int) $p->expires_at,
        ];
    }
}
