<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared discount engine for coupons (US-AD-7-*, US-US-CP-*) and offers (US-AD-8-*, US-US-OF-*).
 *
 * The three checkout paths in local_payments (course / package / subscription) call
 * {@see self::resolve()} to compute the charged price, and {@see self::record_usage()} on payment
 * success to log the redemption. Interaction rule (specs are silent, documented here + in the spec
 * Notes): automatic OFFERS STACK — every matching offer's discount is summed on the base price; then
 * a COUPON code, if supplied and valid, stacks on the offer-adjusted price. The final price is
 * clamped to >= 0.
 *
 * See docs/specs/admin/US-AD-7-*, US-AD-8-* and docs/specs/student/US-US-CP-*, US-US-OF-*.
 */
class discount_manager {

    /** Item types a coupon/offer can target. */
    const TYPE_COURSE       = 'course';
    const TYPE_PACKAGE      = 'package';
    const TYPE_SUBSCRIPTION = 'subscription';
    const TYPE_PROGRAM      = 'program';

    /** Discount value kinds. */
    const DISCOUNT_PERCENT = 'percent';
    const DISCOUNT_FIXED   = 'fixed';

    /** @return string[] the valid item types. */
    public static function item_types() {
        return array(self::TYPE_COURSE, self::TYPE_PACKAGE, self::TYPE_SUBSCRIPTION, self::TYPE_PROGRAM);
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
        if ($itemtype === self::TYPE_PROGRAM) {
            // Program prices live in local_academy, not in the third-party enrol_programs plugin.
            $price = program_purchase_manager::get_price($itemid);
            return $price ? (float)$price->price : 0.0;
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
        // A max of 0 (or unset) means "no cap" — same convention as usage_limit. Only a positive max
        // actually limits the discount; treating 0 as a 0-cap would silently zero out the coupon.
        if ($max !== null && $max !== '' && (float)$max > 0) {
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
        } else if ($itemtype === self::TYPE_PROGRAM) {
            $name = $DB->get_field('enrol_programs_programs', 'fullname', array('id' => $itemid));
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
     * All active, in-window offers that apply to an item, each with its discount computed on the base
     * price. Offers STACK — the caller sums these discounts (an admin can layer several offers on the
     * same item and they combine).
     *
     * @param string $itemtype
     * @param int $itemid
     * @param float $base base price
     * @param int|null $now
     * @return object[] each {id, name, discount_type, discount_value, discount}
     */
    public static function matching_offers($itemtype, $itemid, $base, $now = null) {
        global $DB;
        $now = $now ?? time();
        $offers = $DB->get_records('academy_offers', array('status' => 'active'));
        $out = array();
        foreach ($offers as $offer) {
            if ($offer->startdate > 0 && $now < $offer->startdate) { continue; }
            if ($offer->enddate > 0 && $now > $offer->enddate) { continue; }
            $items = $DB->get_records('academy_offer_items', array('offerid' => $offer->id));
            if (!self::scope_matches($items, $itemtype, $itemid)) { continue; }
            $discount = self::discount_amount($offer->discount_type, $offer->discount_value, null, $base);
            if ($discount <= 0) { continue; }
            $out[] = (object) array(
                'id'             => (int)$offer->id,
                'name'           => $offer->name,
                'discount_type'  => $offer->discount_type,
                'discount_value' => (float)$offer->discount_value,
                'discount'       => $discount,
            );
        }
        return $out;
    }

    /**
     * The combined (stacked) automatic-offer discount for an item: the sum of every matching offer's
     * discount, clamped so it never exceeds the base price (final stays >= 0). Each offer's discount is
     * computed independently on the base, then summed (additive), so two 30% offers give 60% off.
     *
     * @param string $itemtype
     * @param int $itemid
     * @param float $base
     * @param int|null $now
     * @return array {offers: array of {id, name, discount}, total: float}
     */
    public static function offers_discount($itemtype, $itemid, $base, $now = null) {
        $base = round(max(0.0, (float)$base), 2);
        $matching = self::matching_offers($itemtype, $itemid, $base, $now);
        $offers = array();
        $total = 0.0;
        foreach ($matching as $o) {
            $offers[] = array('id' => (int)$o->id, 'name' => $o->name, 'discount' => round((float)$o->discount, 2));
            $total += (float)$o->discount;
        }
        $total = min(round($total, 2), $base); // combined discount can't drive the price below zero
        return array('offers' => $offers, 'total' => $total);
    }

    /**
     * A compact, display-ready summary of the automatic offer(s) on an item (US-US-OF-1-1), for the
     * front-page cards, course boxes and buy page. Offers stack, so this reflects the combined
     * discount of every matching offer. Returns null when no active offer applies.
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
        $od = self::offers_discount($itemtype, $itemid, $base, $now);
        if ($od['total'] <= 0) {
            return null;
        }
        $total = $od['total'];
        // Combine the applied offer names, and show the combined effective discount as a "-NN%" badge.
        $names = array();
        foreach ($od['offers'] as $o) { $names[] = format_string($o['name']); }
        $pct = $base > 0 ? round($total / $base * 100) : 0;
        return array(
            'name'           => implode(' + ', $names),
            'discount_type'  => self::DISCOUNT_PERCENT,
            'discount_value' => $pct,
            'discount'       => round($total, 2),
            'original'       => $base,
            'final'          => round(max(0.0, $base - $total), 2),
            'label'          => '-' . rtrim(rtrim(number_format($pct, 2), '0'), '.') . '%',
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
            'offers'          => array(), // every applied offer [{id, name, discount}] (offers stack)
            'offer_id'        => 0,       // first applied offer id (kept for back-compat)
            'offer_name'      => '',
            'offer_discount'  => 0.0,     // combined offer discount
            'coupon_id'       => 0,
            'coupon_code'     => '',
            'coupon_discount' => 0.0,
            'discount'        => 0.0,
            'final'           => $base,
        );

        // Automatic offers — they STACK: sum every matching offer's discount (clamped >= 0).
        $od = self::offers_discount($itemtype, $itemid, $base, $now);
        $running = $base;
        if ($od['total'] > 0) {
            $result['offers']         = $od['offers'];
            $result['offer_discount'] = $od['total'];
            $result['offer_id']       = $od['offers'][0]['id'];
            $names = array();
            foreach ($od['offers'] as $o) { $names[] = format_string($o['name']); }
            $result['offer_name']     = implode(', ', $names);
            $running = round($running - $od['total'], 2);
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

        // Offers STACK: record a usage row for every applied offer. Back-compat: transaction metadata
        // created before stacking carried a single offer_id/offer_discount instead of an offers[] list.
        $offers = (isset($resolved['offers']) && is_array($resolved['offers'])) ? $resolved['offers'] : array();
        if (empty($offers) && !empty($resolved['offer_id']) && $resolved['offer_discount'] > 0) {
            $offers = array(array('id' => $resolved['offer_id'], 'discount' => $resolved['offer_discount']));
        }
        foreach ($offers as $off) {
            $off = (array)$off; // metadata decoded from JSON gives stdClass entries
            $oid = (int)($off['id'] ?? 0);
            $odisc = round((float)($off['discount'] ?? 0), 2);
            if ($oid <= 0 || $odisc <= 0) { continue; }
            $exists = $transactionid > 0 && $DB->record_exists('academy_offer_usages',
                array('offerid' => $oid, 'transactionid' => $transactionid));
            if ($exists) { continue; }
            $DB->insert_record('academy_offer_usages', (object) array(
                'offerid'         => $oid,
                'userid'          => $userid,
                'transactionid'   => $transactionid,
                'item_type'       => $itemtype,
                'item_id'         => (int)$itemid,
                'original_amount' => $resolved['original'],
                'discount_amount' => $odisc,
                'final_amount'    => round((float)$resolved['original'] - $odisc, 2),
                'timecreated'     => $now,
            ));
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
