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
 * Admin "Manage courses": list who bought which single course, and "unbuy" (revoke) a purchase.
 *
 * A single-course purchase is a completed local_payments transaction whose metadata item_type is
 * "course" (package / subscription checkouts do not target a course). Revoking unenrols the buyer
 * from the course and marks the transaction cancelled (or refunded), mirroring the admin
 * "unsubscribe" flow on manage_subscriptions.php.
 *
 * No schema change: reads local_payments_transactions directly and reuses
 * {@see \local_payments\enrollment_handler} to unenrol.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_subscriptions;

defined('MOODLE_INTERNAL') || die();

/**
 * Single-course purchase manager (admin list + revoke).
 */
class course_purchase_manager {

    /**
     * Every paid single-course purchase, newest first, with the buyer + course + amount, whether the
     * purchase is still live and whether the buyer is still enrolled.
     *
     * Revoked purchases (cancelled / refunded) stay in the list on purpose: revoking — either from
     * the Unbuy button here or by unenrolling the user anywhere in Moodle (see
     * {@see revoke_on_unenrolment()}) — must not make the row vanish, or the admin loses every trace
     * of who bought what.
     *
     * @return array list of purchase rows for the admin table
     */
    public static function get_all_course_purchases(): array {
        global $DB;

        // Transactions that carry a course id and either granted access (completed) or did and had
        // it revoked. We still confirm item_type=course from the JSON metadata below, because a
        // completed row is the only thing that ever granted access.
        [$insql, $inparams] = $DB->get_in_or_equal([
            \local_payments\status_machine::COMPLETED,
            \local_payments\status_machine::CANCELLED,
            \local_payments\status_machine::REFUNDED,
        ], SQL_PARAMS_NAMED, 'st');
        $sql = "SELECT t.id, t.userid, t.courseid, t.amount, t.original_amount, t.currency,
                       t.status, t.metadata, t.timemodified, t.timecreated,
                       u.firstname, u.lastname, u.email,
                       c.fullname AS course_fullname
                  FROM {local_payments_transactions} t
                  JOIN {user} u ON u.id = t.userid
             LEFT JOIN {course} c ON c.id = t.courseid
                 WHERE t.status $insql AND t.courseid IS NOT NULL AND t.courseid > 0
              ORDER BY t.timecreated DESC";
        $rows = $DB->get_records_sql($sql, $inparams);

        $out = [];
        foreach ($rows as $r) {
            $meta = json_decode((string) $r->metadata);
            $itemtype = isset($meta->item_type) ? $meta->item_type : 'course';
            if ($itemtype !== 'course') {
                continue; // Package/subscription transaction that happens to carry a course id.
            }
            $coursename = $r->course_fullname !== null
                ? format_string($r->course_fullname)
                : get_string('mc_course_deleted', 'local_nit_subscriptions');
            $enrolled = false;
            if ($r->course_fullname !== null) {
                $enrolled = \local_payments\enrollment_handler::is_enrolled((int) $r->userid, (int) $r->courseid);
            }
            $out[] = [
                'id'              => (int) $r->id,
                'userid'          => (int) $r->userid,
                'user_fullname'   => fullname($r),
                'user_email'      => $r->email,
                'courseid'        => (int) $r->courseid,
                'course_fullname' => $coursename,
                'amount'          => (float) $r->amount,
                'original_amount' => $r->original_amount !== null ? (float) $r->original_amount : (float) $r->amount,
                'currency'        => $r->currency,
                'enrolled'        => $enrolled,
                'status'          => (string) $r->status,
                // A live purchase is one that still counts as "bought" everywhere else (course
                // cards, get_purchased_courses). Only those can be unbought — and they can be
                // unbought even with the buyer already unenrolled, because the purchase itself is
                // what the Unbuy button revokes.
                'active'          => $r->status === \local_payments\status_machine::COMPLETED,
                'timecreated'     => (int) $r->timecreated,
            ];
        }
        return $out;
    }

    /**
     * "Unbuy" a course: unenrol the buyer and mark the transaction cancelled (refunded when asked).
     *
     * @param int $transactionid local_payments_transactions.id
     * @param bool $refund mark the transaction refunded instead of cancelled
     * @return void
     */
    public static function revoke_course_purchase(int $transactionid, bool $refund): void {
        global $DB;

        $transaction = $DB->get_record('local_payments_transactions', ['id' => $transactionid]);
        if (!$transaction) {
            throw new \moodle_exception('mc_txn_notfound', 'local_nit_subscriptions');
        }
        if ($transaction->status !== \local_payments\status_machine::COMPLETED) {
            throw new \moodle_exception('mc_not_active', 'local_nit_subscriptions');
        }

        $courseid = (int) $transaction->courseid;
        $userid   = (int) $transaction->userid;

        $dbtx = $DB->start_delegated_transaction();

        // Revoke access. unenrol_user is a no-op if the course is gone or the user is not enrolled.
        if ($courseid > 0 && $DB->record_exists('course', ['id' => $courseid])) {
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

    /**
     * Revoke the course purchase(s) behind an enrolment that has just been deleted.
     *
     * Access to a paid course can be taken away from two places: the Unbuy button here, and Moodle's
     * own "Unenrol" on any participants page. Only the first used to touch the transaction, so a
     * core unenrolment left a COMPLETED purchase behind: price_resolver::is_purchased() kept
     * returning true, the catalogue card kept saying "Purchased" instead of offering "Buy now", and
     * the buyer could never get back in. Cancelling the purchase here makes both routes end in the
     * same state.
     *
     * Called by {@see \local_nit_subscriptions\observer::user_enrolment_deleted()}. Enrolment
     * deletions run in bulk (course deletion, enrol instance removal), so this stays a cheap
     * targeted UPDATE and does nothing when there is no live purchase to revoke.
     *
     * @param int $userid the user whose enrolment was deleted
     * @param int $courseid the course they were unenrolled from
     * @return int number of purchases revoked
     */
    public static function revoke_on_unenrolment(int $userid, int $courseid): int {
        global $DB;

        if ($userid <= 0 || $courseid <= 0) {
            return 0;
        }

        // The course is already gone (its context with it, so is_enrolled() below would fatal):
        // nothing to re-offer for sale, so leave the purchase record as it stands.
        if (!$DB->record_exists('course', ['id' => $courseid])) {
            return 0;
        }

        // Another enrolment method still grants access (e.g. a subscription enrolment alongside the
        // manual one): the user has not actually lost the course, so the purchase stands.
        if (\local_payments\enrollment_handler::is_enrolled($userid, $courseid)) {
            return 0;
        }

        $purchases = $DB->get_records('local_payments_transactions', [
            'userid'   => $userid,
            'courseid' => $courseid,
            'status'   => \local_payments\status_machine::COMPLETED,
        ], '', 'id, metadata');

        $revoked = 0;
        foreach ($purchases as $purchase) {
            // Subscription/package checkouts can carry a course id too — those are governed by the
            // subscription, not by this enrolment, so leave them alone.
            $meta = json_decode((string) $purchase->metadata);
            if (isset($meta->item_type) && $meta->item_type !== 'course') {
                continue;
            }
            $DB->update_record('local_payments_transactions', (object) [
                'id'           => $purchase->id,
                'status'       => \local_payments\status_machine::CANCELLED,
                'timemodified' => time(),
            ]);
            $revoked++;
        }
        return $revoked;
    }
}
