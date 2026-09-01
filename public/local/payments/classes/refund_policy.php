<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * How long a buyer has to change their mind, and what it costs them.
 *
 * Two numbers make up a policy:
 *
 *  - the window, in hours from the moment the payment completed. Hours rather
 *    than days because the sensible answer for a subscription is often "the
 *    first 24" and for a course "the first two weeks", and one unit covers both.
 *    Zero means there is no self-service window at all.
 *  - the fee, which the platform keeps. Zero means a full refund.
 *
 * The fee is always a **percentage of the amount paid**, and that one decision
 * is what lets both numbers live on the course or the plan rather than on every
 * price row. A percentage needs no currency, so a course sold at 36 EGP and 450
 * USD needs one number instead of two; and it follows what was actually charged,
 * so a coupon that halves the price halves the fee, where a flat amount would
 * quietly become a third of the sale.
 *
 * The site settings are the fallback for an item that sets nothing of its own.
 *
 * Outside the window — or when the window is zero — nothing is refused
 * outright: the buyer asks, and a human decides. {@see refund_manager}.
 */
class refund_policy {

    /** @var string[] Item types with settings of their own. */
    const TYPES = ['course', 'subscription'];

    /**
     * What was bought, as the settings name it.
     *
     * @param \stdClass $transaction
     * @return string 'course', 'subscription', or the raw type for anything else.
     */
    public static function item_type(\stdClass $transaction): string {
        $meta = json_decode($transaction->metadata ?? '{}', true) ?: [];
        $type = (string) ($meta['item_type'] ?? '');
        if ($type === '') {
            // A row from before item_type was recorded is a course purchase:
            // nothing else existed then.
            $type = 'course';
        }
        return $type;
    }

    /**
     * Which course or plan this payment was for.
     *
     * @param \stdClass $transaction
     * @return int 0 when the purchase is not tied to a specific item.
     */
    public static function item_id(\stdClass $transaction): int {
        $meta = json_decode($transaction->metadata ?? '{}', true) ?: [];
        $itemid = (int) ($meta['item_id'] ?? 0);

        // Rows from before item_id was recorded are course purchases.
        return $itemid ?: (int) ($transaction->courseid ?? 0);
    }

    /**
     * The site-wide fallback for an item type.
     *
     * @param string $itemtype
     * @return object {hours:int, feepercent:float}
     */
    public static function site_policy(string $itemtype): object {
        // An item type with no settings of its own uses the default block, which
        // is what makes "any other thing we sell" work without a code change.
        $suffix = in_array($itemtype, self::TYPES, true) ? $itemtype : 'default';

        return (object) [
            'hours' => max(0, (int) get_config('local_payments', 'refund_hours_' . $suffix)),
            'feepercent' => max(0, (float) get_config('local_payments', 'refund_fee_' . $suffix)),
        ];
    }

    /** Are refunds offered on this site at all? */
    public static function enabled(): bool {
        return (bool) get_config('local_payments', 'refund_enabled');
    }

    /**
     * The terms set on this specific course or plan, if any.
     *
     * @param string $itemtype
     * @param int $itemid
     * @return \stdClass|null Carrying hours and feepercent, either of which may be null.
     */
    public static function item_terms(string $itemtype, int $itemid): ?\stdClass {
        global $DB;

        if ($itemid <= 0) {
            return null;
        }

        // Subscriptions keep their terms on the plan, beside the plan's other
        // terms, because that is the screen a plan is edited on. Everything else
        // uses our own table, since we do not own the course record.
        if ($itemtype === 'subscription') {
            $plan = $DB->get_record('nit_subscription', ['id' => $itemid],
                'id, refund_hours, refund_fee');
            if (!$plan) {
                return null;
            }
            return (object) [
                'hours' => $plan->refund_hours,
                'feepercent' => $plan->refund_fee,
            ];
        }

        $row = $DB->get_record('local_payments_refund_terms',
            ['itemtype' => $itemtype, 'itemid' => $itemid], 'hours, feepercent');

        return $row ?: null;
    }

    /**
     * The policy in force for one transaction.
     *
     * The fee is always a percentage of what was actually paid. That is not a
     * limitation, it is what makes one number correct everywhere: it needs no
     * currency, so a course priced in EGP and USD needs only one; and it follows
     * the amount charged, so a coupon that halves the price halves the fee
     * instead of turning a flat 10 into a third of the sale.
     *
     * @param \stdClass $transaction
     * @return object {hours:int, fee:float, fromitem:bool, itemtype:string}
     */
    public static function for_transaction(\stdClass $transaction): object {
        $itemtype = self::item_type($transaction);
        $site = self::site_policy($itemtype);
        $paid = round((float) $transaction->amount, 2);

        $terms = self::item_terms($itemtype, self::item_id($transaction));

        // Null means "nothing set here", which is different from zero: zero is a
        // deliberate no-window or full refund.
        $hours = ($terms && $terms->hours !== null)
            ? max(0, (int) $terms->hours)
            : $site->hours;

        $percent = ($terms && $terms->feepercent !== null)
            ? max(0, (float) $terms->feepercent)
            : $site->feepercent;

        $fee = $percent > 0 ? round($paid * $percent / 100, 2) : 0.0;

        return (object) [
            'itemtype' => $itemtype,
            'hours' => $hours,
            'feepercent' => $percent,
            // A fee larger than the payment would mean billing somebody for asking.
            'fee' => min($fee, $paid),
            'fromitem' => (bool) $terms,
        ];
    }

    /**
     * What this transaction is worth back, under the policy in force now.
     *
     * The money is always quoted, even when the window has closed: the buyer
     * asking for a refund is entitled to know what they would get, and staff
     * deciding on the request need the same number in front of them.
     *
     * @param \stdClass $transaction
     * @return object {
     *     paid:float, fee:float, net:float, currency:string, hours:int,
     *     feepercent:float, deadline:int, withinwindow:bool, fromitem:bool,
     *     itemtype:string
     * }
     */
    public static function quote(\stdClass $transaction): object {
        $policy = self::for_transaction($transaction);
        $paid = round((float) $transaction->amount, 2);

        // The clock starts when the payment completed, which is timemodified on
        // a transaction that reached COMPLETED — timecreated is when checkout
        // opened, and an offline method can settle hours later.
        $paidat = (int) ($transaction->timemodified ?: $transaction->timecreated);
        $deadline = $policy->hours > 0 ? $paidat + ($policy->hours * HOURSECS) : 0;

        return (object) [
            'itemtype' => $policy->itemtype,
            'paid' => $paid,
            'fee' => $policy->fee,
            'net' => round(max(0, $paid - $policy->fee), 2),
            'currency' => $transaction->currency,
            'hours' => $policy->hours,
            'feepercent' => $policy->feepercent,
            'fromitem' => $policy->fromitem,
            'deadline' => $deadline,
            'withinwindow' => $deadline > 0 && time() <= $deadline,
        ];
    }

    /**
     * The policy as a sentence, for a buyer about to pay or about to ask.
     *
     * @param object $quote from {@see self::quote()}
     * @return string
     */
    public static function describe(object $quote): string {
        if ($quote->hours <= 0) {
            return get_string('refund_policy_norwindow', 'local_payments');
        }

        $a = (object) [
            'hours' => $quote->hours,
            // Both, because the percentage is the policy and the amount is what
            // this particular buyer would actually get back.
            'fee' => format_float($quote->feepercent, 2, true, true) . '% ('
                . format_float($quote->fee, 2, true, true) . ' ' . $quote->currency . ')',
        ];

        return $quote->fee > 0
            ? get_string('refund_policy_windowfee', 'local_payments', $a)
            : get_string('refund_policy_windowfree', 'local_payments', $a);
    }
}
