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
        if ($reference !== '') {
            $existing = $DB->get_record('nit_sub_purchase', ['reference' => $reference]);
            if ($existing) {
                return self::summary($existing);
            }
        }

        $sub = $DB->get_record('nit_subscription', ['id' => $subscriptionid], '*', MUST_EXIST);
        $isb2b = ($type === 'b2b');
        $basePrice = (float) $sub->price;
        $discountPct = 0;
        if ($isb2b && $seats > 0) {
            $option = $DB->get_record('nit_sub_seat_option', ['subscriptionid' => $sub->id, 'seats' => (int) $seats]);
            if ($option) {
                $discountPct = (float) $option->discount_percent;
            }
        }

        $now = time();
        $expiresat = $now + ((int) $sub->duration_days * DAYSECS);

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

        return self::summary($purchase);
    }

    /**
     * The user's current active (non-expired) subscription purchase, or null.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    public static function get_active_subscription($userid) {
        global $DB;
        $now = time();
        $rows = $DB->get_records('nit_sub_purchase',
            ['userid' => $userid, 'status' => self::STATUS_ACTIVE], 'timeactivated DESC');
        foreach ($rows as $r) {
            if ((int) $r->expires_at === 0 || $now <= (int) $r->expires_at) {
                return $r;
            }
        }
        return null;
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
