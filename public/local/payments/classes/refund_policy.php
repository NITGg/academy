<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * How long a buyer has to change their mind, and what it costs them.
 *
 * The policy is set per item type, because the things this platform sells are not
 * comparable: a course is bought once and consumed slowly, a subscription bills
 * for a period that starts immediately. Anything else that takes a payment —
 * packages today, whatever is added tomorrow — falls back to the default
 * settings rather than being refused a policy.
 *
 * Two numbers make up a policy:
 *
 *  - the window, in hours from the moment the payment completed. Hours rather
 *    than days because the sensible answer for a subscription is often "the
 *    first 24" and for a course "the first two weeks", and one unit covers both.
 *    Zero means there is no self-service window at all.
 *  - the fee, which the platform keeps. Zero means a full refund. It is either a
 *    flat amount or a percentage of what was paid; a flat amount is the honest
 *    shape for a bank charge, a percentage the honest shape for a restocking
 *    fee, and multi-currency pricing makes the percentage the safer default.
 *
 * Outside the window — or when the window is zero — nothing is refused outright:
 * the buyer asks, and a human decides. {@see refund_manager}.
 */
class refund_policy {

    /** @var string Fee is a flat amount in the transaction's currency. */
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
     * The policy in force for one transaction.
     *
     * @param \stdClass $transaction
     * @return object {hours:int, feetype:string, feevalue:float, itemtype:string}
     */
    public static function for_transaction(\stdClass $transaction): object {
        return self::for_item(self::item_type($transaction), self::item_id($transaction));
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
     * The policy for one specific course or plan.
     *
     * An override on the item wins over the settings for its type. That is the
     * point of it: a flagship course can refuse automatic refunds while
     * everything else allows them, without anyone touching site settings that
     * apply to hundreds of other courses.
     *
     * @param string $itemtype
     * @param int $itemid
     * @return object {hours:int, feetype:string, feevalue:float, itemtype:string, overridden:bool}
     */
    public static function for_item(string $itemtype, int $itemid): object {
        global $DB;

        $policy = self::for_item_type($itemtype);
        $policy->overridden = false;

        if ($itemid <= 0) {
            return $policy;
        }

        $rule = $DB->get_record('local_payments_refund_rules',
            ['itemtype' => $itemtype, 'itemid' => $itemid]);
        if (!$rule) {
            return $policy;
        }

        $policy->hours = max(0, (int) $rule->hours);
        $policy->feetype = $rule->feetype === self::FEE_FIXED ? self::FEE_FIXED : self::FEE_PERCENT;
        $policy->feevalue = max(0, (float) $rule->feevalue);
        $policy->feecurrency = self::normalise_currency((string) ($rule->feecurrency ?? ''));
        $policy->overridden = true;

        return $policy;
    }

    /**
     * The policy in force for an item type.
     *
     * @param string $itemtype
     * @return object {hours:int, feetype:string, feevalue:float, itemtype:string}
     */
    public static function for_item_type(string $itemtype): object {
        // An item type with no settings of its own uses the default block, which
        // is what makes "any other thing we sell" work without a code change.
        $suffix = in_array($itemtype, self::TYPES, true) ? $itemtype : 'default';

        $feetype = (string) get_config('local_payments', 'refund_feetype_' . $suffix);

        return (object) [
            'itemtype' => $itemtype,
            'hours' => max(0, (int) get_config('local_payments', 'refund_hours_' . $suffix)),
            'feetype' => $feetype === self::FEE_FIXED ? self::FEE_FIXED : self::FEE_PERCENT,
            'feevalue' => max(0, (float) get_config('local_payments', 'refund_fee_' . $suffix)),
            'feecurrency' => self::normalise_currency(
                (string) get_config('local_payments', 'refund_feecurrency_' . $suffix)),
        ];
    }

    /**
     * The refund fee set on the price rule this payment was made under.
     *
     * The transaction records which rule priced it, so this is the exact row the
     * buyer paid against rather than whichever rule matches today — a price
     * changed after the sale must not change what the sale is worth back.
     *
     * @param \stdClass $transaction
     * @return float|null Null when the rule sets no fee, so the policy applies.
     */
    private static function price_rule_fee(\stdClass $transaction): ?float {
        global $DB;

        if (empty($transaction->price_id)) {
            return null;
        }

        $fee = $DB->get_field('local_payments_course_prices', 'refund_fee',
            ['id' => $transaction->price_id]);

        // False means no such row; null means the row sets no fee. Both fall
        // back, and only a real number overrides.
        return ($fee === false || $fee === null) ? null : max(0, (float) $fee);
    }

    /**
     * A three-letter code, falling back to the site default when unset.
     */
    private static function normalise_currency(string $code): string {
        $code = strtoupper(trim($code));
        if (preg_match('/^[A-Z]{3}$/', $code)) {
            return $code;
        }
        $default = strtoupper(trim((string) get_config('local_payments', 'default_currency')));
        return preg_match('/^[A-Z]{3}$/', $default) ? $default : 'EGP';
    }

    /** Are refunds offered on this site at all? */
    public static function enabled(): bool {
        return (bool) get_config('local_payments', 'refund_enabled');
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
     *     deadline:int, withinwindow:bool, feetype:string, feevalue:float,
     *     itemtype:string
     * }
     */
    public static function quote(\stdClass $transaction): object {
        $policy = self::for_transaction($transaction);

        $paid = round((float) $transaction->amount, 2);

        // The fee, in order of preference.
        //
        // First the price rule the buyer actually paid under. That rule names
        // its own currency, so a fee set there is unambiguous by construction —
        // 10 on the Egypt/EGP row is ten pounds, and cannot be mistaken for ten
        // dollars. This is the right place for a flat fee on a course, and it
        // wins over anything set further out.
        //
        // Otherwise the item or site policy. A percentage there is currency-safe
        // — 10% of 36 EGP and 10% of 450 USD are both 10%. A flat amount there
        // is not, so it names a currency and is skipped against a payment in a
        // different one rather than being converted at a rate nobody agreed.
        $feecurrencymismatch = false;
        $feefromprice = self::price_rule_fee($transaction);

        if ($feefromprice !== null) {
            $fee = round($feefromprice, 2);
        } else if ($policy->feevalue <= 0) {
            $fee = 0.0;
        } else if ($policy->feetype === self::FEE_FIXED) {
            if (strcasecmp($policy->feecurrency, (string) $transaction->currency) === 0) {
                $fee = round($policy->feevalue, 2);
            } else {
                $fee = 0.0;
                $feecurrencymismatch = true;
            }
        } else {
            $fee = round($paid * $policy->feevalue / 100, 2);
        }

        // A fee larger than the payment would mean billing somebody for asking.
        $fee = min($fee, $paid);

        // The clock starts when the payment completed, which is timemodified on
        // a transaction that reached COMPLETED — timecreated is when checkout
        // opened, and an offline method can settle hours later.
        $paidat = (int) ($transaction->timemodified ?: $transaction->timecreated);
        $deadline = $policy->hours > 0 ? $paidat + ($policy->hours * HOURSECS) : 0;

        return (object) [
            'itemtype' => $policy->itemtype,
            'overridden' => !empty($policy->overridden),
            'feecurrency' => $policy->feecurrency,
            'feecurrencymismatch' => $feecurrencymismatch,
            'feefromprice' => $feefromprice !== null,
            'paid' => $paid,
            'fee' => $fee,
            'net' => round(max(0, $paid - $fee), 2),
            'currency' => $transaction->currency,
            'hours' => $policy->hours,
            'feetype' => $policy->feetype,
            'feevalue' => $policy->feevalue,
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
            'fee' => $quote->feetype === self::FEE_PERCENT
                ? format_float($quote->feevalue, 2, true, true) . '%'
                : format_float($quote->feevalue, 2, true, true) . ' ' . $quote->feecurrency,
        ];

        return $quote->fee > 0
            ? get_string('refund_policy_windowfee', 'local_payments', $a)
            : get_string('refund_policy_windowfree', 'local_payments', $a);
    }
}
