<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared discount engine for coupons (US-AD-7-*, US-US-CP-*) and offers (US-AD-8-*, US-US-OF-*).
 *
 * The three checkout paths in local_payments (course / package / subscription) call
 * {@see self::resolve()} to compute the charged price, and {@see self::record_usage()} on payment
 * success to log the redemption. Interaction rule (specs are silent, documented here + in the spec
 * Notes): an automatic OFFER applies first to the base price; a COUPON code, if supplied and valid,
 * stacks on the offer-adjusted price. The final price is clamped to >= 0.
 *
 * See docs/specs/admin/US-AD-7-*, US-AD-8-* and docs/specs/student/US-US-CP-*, US-US-OF-*.
 */
class discount_manager {

    /** Item types a coupon/offer can target. */
    const TYPE_COURSE       = 'course';
    const TYPE_PACKAGE      = 'package';
    const TYPE_SUBSCRIPTION = 'subscription';

    /** Discount value kinds. */
    const DISCOUNT_PERCENT = 'percent';
    const DISCOUNT_FIXED   = 'fixed';

    /** @return string[] the valid item types. */
    public static function item_types() {
        return array(self::TYPE_COURSE, self::TYPE_PACKAGE, self::TYPE_SUBSCRIPTION);
    }

    /** Validate an item type or throw. */
    public static function normalize_item_type($type) {
        $type = strtolower(trim((string)$type));
        if (!in_array($type, self::item_types(), true)) {
            throw new \moodle_exception('err_itemtype', 'local_academy');
        }
        return $type;
    }

    /** Validate a discount type or throw. */
    public static function normalize_discount_type($type) {
        $type = strtolower(trim((string)$type));
        if (!in_array($type, array(self::DISCOUNT_PERCENT, self::DISCOUNT_FIXED), true)) {
            throw new \moodle_exception('err_discounttype', 'local_academy');
        }
        return $type;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Pricing
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The current base price of a sellable item, before any coupon/offer.
     *
     * @param string $itemtype course | package | subscription
     * @param int $itemid
     * @return float base price (0 if it cannot be resolved)
     */
    public static function price_of($itemtype, $itemid) {
        global $DB, $CFG;
        $itemtype = self::normalize_item_type($itemtype);
        $itemid = (int)$itemid;
        if ($itemtype === self::TYPE_PACKAGE) {
            return (float)$DB->get_field('academy_packages', 'price', array('id' => $itemid));
        }
        if ($itemtype === self::TYPE_SUBSCRIPTION) {
            return (float)$DB->get_field('academy_subscriptions', 'price', array('id' => $itemid));
        }
        // Course price comes from the payments plugin's per-course pricing rules.
        require_once($CFG->dirroot . '/local/payments/classes/price_resolver.php');
        try {
            return (float)\local_payments\price_resolver::resolve($itemid)->price;
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * The raw discount amount for a (type, value) pair against a base, capped by an optional max and
     * by the base itself (never returns more than the base).
     *
     * @param string $discounttype percent | fixed
     * @param float $value
     * @param float|null $max cap on the applied discount (null = no cap)
     * @param float $base price the discount is applied to
     * @return float discount amount (>= 0), rounded to 2 dp
     */
    public static function discount_amount($discounttype, $value, $max, $base) {
        $base = max(0.0, (float)$base);
        $value = max(0.0, (float)$value);
        if ($discounttype === self::DISCOUNT_PERCENT) {
            $amount = $base * $value / 100.0;
        } else {
            $amount = $value;
        }
        if ($max !== null && $max !== '' && (float)$max >= 0) {
            $amount = min($amount, (float)$max);
        }
        $amount = min($amount, $base); // final price can never go below zero
        return round($amount, 2);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Scope matching
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Whether a set of scope rows (from academy_coupon_items / academy_offer_items) targets a given
     * item. A row with item_id 0 means "all items of that type".
     *
     * @param array $items rows each with ->item_type and ->item_id
     * @param string $itemtype
     * @param int $itemid
     * @return bool
     */
    public static function scope_matches(array $items, $itemtype, $itemid) {
        $itemid = (int)$itemid;
        foreach ($items as $row) {
            if ($row->item_type === $itemtype && ((int)$row->item_id === 0 || (int)$row->item_id === $itemid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * A human-readable label for a scope row, for the admin + student "applicable items" displays.
     *
     * @param string $itemtype
     * @param int $itemid 0 = all of the type
     * @return string
     */
    public static function item_label($itemtype, $itemid) {
        global $DB;
        $itemid = (int)$itemid;
        if ($itemid === 0) {
            return get_string('scope_all_' . $itemtype, 'local_academy');
        }
        if ($itemtype === self::TYPE_COURSE) {
            $name = $DB->get_field('course', 'fullname', array('id' => $itemid));
            return $name !== false ? format_string($name) : ('#' . $itemid);
        }
        if ($itemtype === self::TYPE_PACKAGE) {
            $name = $DB->get_field('academy_packages', 'name', array('id' => $itemid));
        } else {
            $name = $DB->get_field('academy_subscriptions', 'name', array('id' => $itemid));
        }
        return $name !== false ? format_string($name) : ('#' . $itemid);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Resolution (used by checkout)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The single best automatic offer for an item, or null.
     *
     * "Only one active offer can apply per item" (US-AD-8-1): if several match, the one giving the
     * biggest discount on the base price wins.
     *
     * @param string $itemtype
     * @param int $itemid
     * @param float $base base price
     * @param int|null $now timestamp (defaults to time())
     * @return object|null {id, name, discount_type, discount_value, discount}
     */
    public static function best_offer($itemtype, $itemid, $base, $now = null) {
        global $DB;
        $now = $now ?? time();
        $offers = $DB->get_records('academy_offers', array('status' => 'active'));
        $best = null;
        foreach ($offers as $offer) {
            if ($offer->startdate > 0 && $now < $offer->startdate) { continue; }
            if ($offer->enddate > 0 && $now > $offer->enddate) { continue; }
            $items = $DB->get_records('academy_offer_items', array('offerid' => $offer->id));
            if (!self::scope_matches($items, $itemtype, $itemid)) { continue; }
            $discount = self::discount_amount($offer->discount_type, $offer->discount_value, null, $base);
            if ($best === null || $discount > $best->discount) {
                $best = (object) array(
                    'id'             => (int)$offer->id,
                    'name'           => $offer->name,
                    'discount_type'  => $offer->discount_type,
                    'discount_value' => (float)$offer->discount_value,
                    'discount'       => $discount,
                );
            }
        }
        return $best;
    }

    /**
     * A compact, display-ready summary of the automatic offer on an item (US-US-OF-1-1), for the
     * front-page cards, course boxes and buy page. Returns null when no active offer applies.
     *
     * @param string $itemtype course | package | subscription
     * @param int $itemid
     * @param float|null $base base price (resolved if null)
     * @param int|null $now
     * @return array|null {name, discount_type, discount_value, discount, original, final, label}
     */
    public static function offer_summary($itemtype, $itemid, $base = null, $now = null) {
        $itemtype = self::normalize_item_type($itemtype);
        $base = $base !== null ? (float)$base : self::price_of($itemtype, $itemid);
        $base = round(max(0.0, $base), 2);
        $offer = self::best_offer($itemtype, $itemid, $base, $now);
        if (!$offer || $offer->discount <= 0) {
            return null;
        }
        // Short badge label: "-25%" for a percentage, "-50 <cur>" handled by the caller for fixed.
        $label = $offer->discount_type === self::DISCOUNT_PERCENT
            ? '-' . rtrim(rtrim(number_format((float)$offer->discount_value, 2), '0'), '.') . '%'
            : '';
        return array(
            'name'           => format_string($offer->name),
            'discount_type'  => $offer->discount_type,
            'discount_value' => (float)$offer->discount_value,
            'discount'       => round($offer->discount, 2),
            'original'       => $base,
            'final'          => round(max(0.0, $base - $offer->discount), 2),
            'label'          => $label,
        );
    }

    /**
     * Validate a coupon code for an item + user, or throw a moodle_exception describing why it is not
     * usable (so the student sees a clear message when they apply it).
     *
     * @param string $code
     * @param string $itemtype
     * @param int $itemid
     * @param int $userid
     * @param int|null $now
     * @return object the coupon record
     */
    public static function validate_coupon($code, $itemtype, $itemid, $userid, $now = null) {
        global $DB;
        $now = $now ?? time();
        $code = trim((string)$code);
        if ($code === '') {
            throw new \moodle_exception('err_couponcoderequired', 'local_academy');
        }
        // Case-insensitive lookup so "SAVE10" and "save10" resolve to the same coupon.
        $coupon = $DB->get_record_select('academy_coupons', $DB->sql_equal('code', ':code', false),
            array('code' => $code));
        if (!$coupon) {
            throw new \moodle_exception('err_couponnotfound', 'local_academy');
        }
        if ($coupon->status !== 'active') {
            throw new \moodle_exception('err_couponinactive', 'local_academy');
        }
        if ($coupon->startdate > 0 && $now < $coupon->startdate) {
            throw new \moodle_exception('err_couponnotstarted', 'local_academy');
        }
        if ($coupon->enddate > 0 && $now > $coupon->enddate) {
            throw new \moodle_exception('err_couponexpired', 'local_academy');
        }
        $items = $DB->get_records('academy_coupon_items', array('couponid' => $coupon->id));
        if (!self::scope_matches($items, $itemtype, $itemid)) {
            throw new \moodle_exception('err_couponnotapplicable', 'local_academy');
        }
        // Usage limits. "once" = a single redemption across the platform; "multiple" with a limit caps
        // the total redemptions; a 0 limit means unlimited.
        $used = $DB->count_records('academy_coupon_usages', array('couponid' => $coupon->id));
        if ($coupon->usage_type === 'once') {
            if ($used >= 1) {
                throw new \moodle_exception('err_couponusedup', 'local_academy');
            }
        } else if ((int)$coupon->usage_limit > 0 && $used >= (int)$coupon->usage_limit) {
            throw new \moodle_exception('err_couponusedup', 'local_academy');
        }
        return $coupon;
    }

    /**
     * Compute the charged price for an item, applying the best offer automatically and an optional
     * coupon code on top.
     *
     * @param string $itemtype course | package | subscription
     * @param int $itemid
     * @param int $userid buyer
     * @param string $couponcode optional code entered at checkout
     * @param float|null $baseprice override base price (checkout already knows it, e.g. B2B); else resolved
     * @param int|null $now
     * @return array {original, offer_id, offer_name, offer_discount, coupon_id, coupon_code,
     *                coupon_discount, discount, final}
     */
    public static function resolve($itemtype, $itemid, $userid, $couponcode = '', $baseprice = null, $now = null) {
        $itemtype = self::normalize_item_type($itemtype);
        $now = $now ?? time();
        $base = $baseprice !== null ? (float)$baseprice : self::price_of($itemtype, $itemid);
        $base = round(max(0.0, $base), 2);

        $result = array(
            'original'        => $base,
            'offer_id'        => 0,
            'offer_name'      => '',
            'offer_discount'  => 0.0,
            'coupon_id'       => 0,
            'coupon_code'     => '',
            'coupon_discount' => 0.0,
            'discount'        => 0.0,
            'final'           => $base,
        );

        // Automatic offer.
        $offer = self::best_offer($itemtype, $itemid, $base, $now);
        $running = $base;
        if ($offer && $offer->discount > 0) {
            $result['offer_id']       = $offer->id;
            $result['offer_name']     = $offer->name;
            $result['offer_discount'] = $offer->discount;
            $running = round($running - $offer->discount, 2);
        }

        // Coupon on top (only when a code was supplied; invalid codes bubble up as an exception).
        $couponcode = trim((string)$couponcode);
        if ($couponcode !== '') {
            $coupon = self::validate_coupon($couponcode, $itemtype, $itemid, $userid, $now);
            $cdiscount = self::discount_amount($coupon->discount_type, $coupon->discount_value,
                $coupon->max_discount, $running);
            $result['coupon_id']       = (int)$coupon->id;
            $result['coupon_code']     = $coupon->code;
            $result['coupon_discount'] = $cdiscount;
            $running = round($running - $cdiscount, 2);
        }

        $result['final']    = round(max(0.0, $running), 2);
        $result['discount'] = round($base - $result['final'], 2);
        return $result;
    }

    /**
     * Record coupon + offer usage after a successful payment. Idempotent: skips a record that already
     * exists for the same transaction, so a re-delivered webhook does not double-count.
     *
     * @param array $resolved output of {@see self::resolve()}
     * @param int $userid
     * @param int $transactionid local_payments_transactions.id
     * @param string $itemtype
     * @param int $itemid
     */
    public static function record_usage(array $resolved, $userid, $transactionid, $itemtype, $itemid) {
        global $DB;
        $now = time();
        $transactionid = (int)$transactionid;

        if (!empty($resolved['offer_id']) && $resolved['offer_discount'] > 0) {
            $exists = $transactionid > 0 && $DB->record_exists('academy_offer_usages',
                array('offerid' => $resolved['offer_id'], 'transactionid' => $transactionid));
            if (!$exists) {
                $DB->insert_record('academy_offer_usages', (object) array(
                    'offerid'         => $resolved['offer_id'],
                    'userid'          => $userid,
                    'transactionid'   => $transactionid,
                    'item_type'       => $itemtype,
                    'item_id'         => (int)$itemid,
                    'original_amount' => $resolved['original'],
                    'discount_amount' => $resolved['offer_discount'],
                    'final_amount'    => round($resolved['original'] - $resolved['offer_discount'], 2),
                    'timecreated'     => $now,
                ));
            }
        }

        if (!empty($resolved['coupon_id']) && $resolved['coupon_discount'] > 0) {
            $exists = $transactionid > 0 && $DB->record_exists('academy_coupon_usages',
                array('couponid' => $resolved['coupon_id'], 'transactionid' => $transactionid));
            if (!$exists) {
                $DB->insert_record('academy_coupon_usages', (object) array(
                    'couponid'        => $resolved['coupon_id'],
                    'userid'          => $userid,
                    'transactionid'   => $transactionid,
                    'item_type'       => $itemtype,
                    'item_id'         => (int)$itemid,
                    'original_amount' => $resolved['original'],
                    'discount_amount' => $resolved['coupon_discount'],
                    'final_amount'    => $resolved['final'],
                    'timecreated'     => $now,
                ));
            }
        }
    }
}
