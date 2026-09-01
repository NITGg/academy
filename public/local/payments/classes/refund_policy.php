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
 * Both live **with the price**, because that is where the currency already is.
 * A course sold at 36 EGP and 450 USD cannot share one flat fee, and a number
 * stated anywhere else has to carry its own currency and be skipped when it does
 * not match — a rule nobody should have to remember. On a price row it simply
 * cannot mismatch.
 *
 * The site settings are the fallback for anything without a price row, and their
 * fee is a percentage for the same reason: a percentage of the amount paid is
 * currency-safe by construction.
 *
 * Outside the window — or when the window is zero — nothing is refused
 * outright: the buyer asks, and a human decides. {@see refund_manager}.
 */
class refund_policy {

    /** @var string Fee is a flat amount in the price row's own currency. */
    const FEE_FIXED = 'fixed';

    /** @var string Fee is a percentage of the amount paid. */
    const FEE_PERCENT = 'percent';

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
     * The price row this payment was made under.
     *
     * For a course the transaction records the exact rule that priced it, so a
     * price edited after the sale cannot change what that sale is worth back.
     * A subscription has no such link, so it is matched on plan and currency,
     * falling back to the plan itself the way its pricing already does.
     *
     * @param \stdClass $transaction
     * @return \stdClass|null A row carrying refund_hours and refund_fee, if any.
     */
    private static function price_row(\stdClass $transaction): ?\stdClass {
        global $DB;

        $itemtype = self::item_type($transaction);

        if ($itemtype === 'course') {
            if (empty($transaction->price_id)) {
                return null;
            }
            $row = $DB->get_record('local_payments_course_prices', ['id' => $transaction->price_id],
                'id, refund_hours, refund_feetype, refund_fee');
            return $row ?: null;
        }

        if ($itemtype === 'subscription') {
            $planid = self::item_id($transaction);
            if (!$planid) {
                return null;
            }

            // The country row that priced it, matched on the currency actually
            // charged, then the plan's own terms.
            $row = $DB->get_record_select('nit_sub_price',
                'subscriptionid = :planid AND currency = :currency AND is_active = 1',
                ['planid' => $planid, 'currency' => $transaction->currency],
                'id, refund_hours, refund_feetype, refund_fee', IGNORE_MULTIPLE);
            if ($row && ($row->refund_hours !== null || $row->refund_fee !== null)) {
                return $row;
            }

            $plan = $DB->get_record('nit_subscription', ['id' => $planid],
                'id, refund_hours, refund_feetype, refund_fee');
            return $plan ?: null;
        }

        return null;
    }

    /**
     * The policy in force for one transaction.
     *
     * @param \stdClass $transaction
     * @return object {hours:int, fee:float, fromprice:bool, itemtype:string}
     */
    public static function for_transaction(\stdClass $transaction): object {
        $itemtype = self::item_type($transaction);
        $site = self::site_policy($itemtype);
        $paid = round((float) $transaction->amount, 2);

        $row = self::price_row($transaction);

        // Null on a price row means "nothing set here", which is different from
        // zero: zero is a deliberate no-window or full refund.
        $hours = ($row && $row->refund_hours !== null)
            ? max(0, (int) $row->refund_hours)
            : $site->hours;

        if ($row && $row->refund_fee !== null) {
            // A flat amount is stated in the row's own currency, which is the
            // currency charged — that is why it can live here at all. A
            // percentage needs no currency.
            $value = max(0, (float) $row->refund_fee);
            $fee = (($row->refund_feetype ?? self::FEE_FIXED) === self::FEE_PERCENT)
                ? round($paid * $value / 100, 2)
                : round($value, 2);
            $fromprice = true;
        } else {
            $fee = $site->feepercent > 0 ? round($paid * $site->feepercent / 100, 2) : 0.0;
            $fromprice = false;
        }

        return (object) [
            'itemtype' => $itemtype,
            'hours' => $hours,
            // A fee larger than the payment would mean billing somebody for asking.
            'fee' => min($fee, $paid),
            'fromprice' => $fromprice,
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
     *     deadline:int, withinwindow:bool, fromprice:bool, itemtype:string
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
            'fromprice' => $policy->fromprice,
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
            'fee' => format_float($quote->fee, 2, true, true) . ' ' . $quote->currency,
        ];

        return $quote->fee > 0
            ? get_string('refund_policy_windowfee', 'local_payments', $a)
            : get_string('refund_policy_windowfree', 'local_payments', $a);
    }
}
