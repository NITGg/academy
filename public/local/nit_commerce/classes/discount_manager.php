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
 * Shared discount engine for coupons and offers. Ported from local_academy\discount_manager.
 *
 * Item labels/prices resolve against the new platform's tables (nit_package, nit_subscription, course)
 * with guards so a missing plugin degrades gracefully rather than erroring. The checkout resolution
 * methods are retained for when the purchase flow is wired; the admin pages only use the scope +
 * label helpers.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_commerce;

defined('MOODLE_INTERNAL') || die();

/**
 * Discount maths, scope matching and checkout resolution shared by coupons + offers.
 */
class discount_manager {

    /** @var string Course item type. */
    const TYPE_COURSE       = 'course';
    /** @var string Package item type. */
    const TYPE_PACKAGE      = 'package';
    /** @var string Subscription item type. */
    const TYPE_SUBSCRIPTION = 'subscription';
    /** @var string Program item type. */
    const TYPE_PROGRAM      = 'program';
    /**
     * @var string Course-category item type.
     *
     * Scope only: nothing is ever BOUGHT as a category, so this never reaches
     * {@see self::resolve()} as the thing being purchased. It appears solely in
     * {nit_coupon_item} / {nit_offer_item} rows, where it means "every course in this
     * category (and the categories beneath it), and every subscription plan attached to it".
     */
    const TYPE_CATEGORY     = 'category';

    /** @var string Percentage discount kind. */
    const DISCOUNT_PERCENT = 'percent';
    /** @var string Fixed-amount discount kind. */
    const DISCOUNT_FIXED   = 'fixed';

    /**
     * The valid item types.
     *
     * @return string[]
     */
    public static function item_types() {
        return array(self::TYPE_COURSE, self::TYPE_PACKAGE, self::TYPE_SUBSCRIPTION, self::TYPE_PROGRAM,
            self::TYPE_CATEGORY);
    }

    /**
     * Validate an item type or throw.
     *
     * @param string $type
     * @return string
     */
    public static function normalize_item_type($type) {
        $type = strtolower(trim((string)$type));
        if (!in_array($type, self::item_types(), true)) {
            throw new \moodle_exception('err_itemtype', 'local_nit_commerce');
        }
        return $type;
    }

    /**
     * Validate a discount type or throw.
     *
     * @param string $type
     * @return string
     */
    public static function normalize_discount_type($type) {
        $type = strtolower(trim((string)$type));
        if (!in_array($type, array(self::DISCOUNT_PERCENT, self::DISCOUNT_FIXED), true)) {
            throw new \moodle_exception('err_discounttype', 'local_nit_commerce');
        }
        return $type;
    }

    // ── Pricing ──

    /**
     * The current base price of a sellable item, before any coupon/offer. Best-effort; 0 if unknown.
     *
     * @param string $itemtype course | package | subscription | program
     * @param int $itemid
     * @return float
     */
    public static function price_of($itemtype, $itemid) {
        global $DB;
        $itemtype = self::normalize_item_type($itemtype);
        $itemid = (int)$itemid;
        if ($itemtype === self::TYPE_PACKAGE && $DB->get_manager()->table_exists('nit_package')) {
            $minor = (int)$DB->get_field('nit_package', 'price_minor', array('id' => $itemid));
            return $minor / 100;
        }
        if ($itemtype === self::TYPE_SUBSCRIPTION && $DB->get_manager()->table_exists('nit_subscription')) {
            if (!$DB->record_exists('nit_subscription', array('id' => $itemid))) {
                return 0.0;
            }
            // Use the buyer's country-resolved price so a coupon/offer preview matches the
            // price shown on the plan card (which is already country-resolved).
            if (class_exists('\local_nit_subscriptions\subscription_manager')) {
                try {
                    return (float) \local_nit_subscriptions\subscription_manager::resolve_price($itemid)->price;
                } catch (\Throwable $e) {
                    // No price for this viewer (signed in with no profile country) — a coupon
                    // preview has nothing to discount, and the purchase is refused anyway.
                    return 0.0;
                }
            }
            return (float)$DB->get_field('nit_subscription', 'price', array('id' => $itemid));
        }
        return 0.0;
    }

    /**
     * The raw discount amount for a (type, value) pair against a base, capped by an optional max and by
     * the base itself.
     *
     * @param string $discounttype percent | fixed
     * @param float $value
     * @param float|null $max cap on the applied discount (null = no cap)
     * @param float $base price the discount is applied to
     * @return float
     */
    public static function discount_amount($discounttype, $value, $max, $base) {
        $base = max(0.0, (float)$base);
        $value = max(0.0, (float)$value);
        if ($discounttype === self::DISCOUNT_PERCENT) {
            $amount = $base * $value / 100.0;
        } else {
            $amount = $value;
        }
        if ($max !== null && $max !== '' && (float)$max > 0) {
            $amount = min($amount, (float)$max);
        }
        $amount = min($amount, $base);
        return round($amount, 2);
    }

    // ── Scope matching ──

    /**
     * Whether a set of scope rows targets a given item. A row with item_id 0 means "all of that type".
     *
     * @param array $items rows each with ->item_type and ->item_id
     * @param string $itemtype
     * @param int $itemid
     * @return bool
     */
    public static function scope_matches(array $items, $itemtype, $itemid) {
        $itemid = (int)$itemid;

        // Category rows are a GATE, not another alternative. Everything else in the list is an
        // alternative — "all courses" OR "this plan" — but a category names a part of the
        // catalogue, and an admin who has said "Programming" has said where this promotion
        // lives. So when any category row is present the item must sit inside one of those
        // branches, and the rows below then choose within it: "Programming" + "all courses"
        // reads as every course under Programming, which is what it looks like it says.
        //
        // Two consequences worth being explicit about. Category rows alone grant the whole
        // branch (there is nothing else to choose with, so the gate IS the scope). And this is
        // the same test {@see self::matches_category()} uses to decide which category page
        // advertises the coupon — a code can never be shown somewhere the checkout would then
        // refuse it, which is exactly what an OR would have allowed.
        $categoryrows = array();
        $otherrows = array();
        foreach ($items as $row) {
            if ($row->item_type === self::TYPE_CATEGORY) {
                $categoryrows[] = (int) $row->item_id;
            } else {
                $otherrows[] = $row;
            }
        }

        if ($categoryrows) {
            $inbranch = false;
            foreach ($categoryrows as $rowid) {
                if (self::category_row_covers($rowid, $itemtype, $itemid)) {
                    $inbranch = true;
                    break;
                }
            }
            if (!$inbranch) {
                return false;
            }
            if (!$otherrows) {
                return true;
            }
        }

        foreach ($otherrows as $row) {
            if ($row->item_type === $itemtype && ((int)$row->item_id === 0 || (int)$row->item_id === $itemid)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Does a `category` scope row cover this item?
     *
     * item_id 0 means every category, i.e. the whole catalogue. Otherwise the row covers a course
     * that sits in that category or in any category BENEATH it (scoping "Programming" has to
     * reach the courses filed under "Programming / Web", or the setting would be useless on any
     * site with a real tree), and a subscription plan attached to the same branch.
     *
     * @param int $rowcategoryid the scope row's item_id
     * @param string $itemtype the item being priced
     * @param int $itemid
     * @return bool
     */
    protected static function category_row_covers($rowcategoryid, $itemtype, $itemid) {
        $itemid = (int) $itemid;
        if ($itemtype === self::TYPE_COURSE) {
            $coursecat = \local_nit_core\helper\category::of_course($itemid);
            if ($coursecat <= 0) {
                return false;
            }
            return $rowcategoryid === 0
                || \local_nit_core\helper\category::is_within($coursecat, $rowcategoryid);
        }
        if ($itemtype === self::TYPE_SUBSCRIPTION) {
            if (!class_exists('\local_nit_subscriptions\subscription_manager')) {
                return false;
            }
            // A plan with no categories of its own belongs to the whole catalogue, so an
            // "all categories" row covers it; a row naming one category does not.
            return \local_nit_subscriptions\subscription_manager::matches_category($itemid, $rowcategoryid);
        }
        // Packages and programs carry no category of their own; only an "all categories" row
        // can reach them, and even that is a stretch, so it does not.
        return false;
    }

    /**
     * Should a coupon or offer with this scope be advertised on a category's landing page?
     *
     * This is a DISPLAY question, not the applicability question {@see self::scope_matches()}
     * answers, and the two are deliberately separate: a coupon for one specific course is
     * perfectly valid, but printing it on the page of a category that course is not in would be
     * advertising a code the checkout would then refuse.
     *
     * The rule, in order:
     *   - category rows win outright: a scope that names categories is shown on those branches
     *     and nowhere else (item_id 0 = every category, so everywhere);
     *   - otherwise a scope covering ALL courses (or all subscriptions) is site-wide, so it is
     *     shown on every category page;
     *   - otherwise it is shown where the things it names actually live — a named course in
     *     this branch, or a named plan attached to it;
     *   - anything else belongs to some other part of the catalogue and is left out.
     *
     * A category id of 0 means "no category in particular" (the home page), which shows the lot.
     *
     * @param array $items rows each with ->item_type and ->item_id
     * @param int $categoryid the category page being rendered, 0 for none
     * @return bool
     */
    public static function matches_category(array $items, $categoryid) {
        $categoryid = (int) $categoryid;
        if ($categoryid <= 0) {
            return true;
        }

        $categoryrows = array();
        foreach ($items as $row) {
            if ($row->item_type === self::TYPE_CATEGORY) {
                $categoryrows[] = (int) $row->item_id;
            }
        }
        if ($categoryrows) {
            foreach ($categoryrows as $rowid) {
                if ($rowid === 0 || in_array($rowid, \local_nit_core\helper\category::branch($categoryid), true)) {
                    return true;
                }
            }
            return false;
        }

        $hassubscriptions = class_exists('\local_nit_subscriptions\subscription_manager');
        foreach ($items as $row) {
            $rowid = (int) $row->item_id;
            if ($rowid === 0) {
                // "All courses" / "all subscriptions" / "all packages": site-wide by definition.
                return true;
            }
            if ($row->item_type === self::TYPE_COURSE) {
                $coursecat = \local_nit_core\helper\category::of_course($rowid);
                if ($coursecat > 0 && \local_nit_core\helper\category::is_within($coursecat, $categoryid)) {
                    return true;
                }
            } else if ($row->item_type === self::TYPE_SUBSCRIPTION && $hassubscriptions) {
                if (\local_nit_subscriptions\subscription_manager::matches_category($rowid, $categoryid)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Resolve a stored {mlang en}…{mlang}{mlang ar}…{mlang} value to the current language.
     *
     * The {mlang} (filter_multilang2) filter is not installed here, so we resolve it ourselves for
     * server-rendered / API output. Values without {mlang} markup are returned unchanged.
     *
     * @param string $text
     * @return string
     */
    public static function resolve_mlang($text) {
        $text = (string)$text;
        if (strpos($text, '{mlang') === false) {
            return $text;
        }
        $blocks = array();
        if (preg_match_all('/\{\s*mlang\s+([a-zA-Z0-9_-]+)\s*\}(.*?)\{\s*mlang\s*\}/s', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $blocks[strtolower($m[1])] = trim($m[2]);
            }
        }
        if (!$blocks) {
            return $text;
        }
        $lang = strtolower(current_language());
        foreach ($blocks as $code => $val) {
            if ($code === $lang || strpos($code, $lang) === 0 || strpos($lang, $code) === 0) {
                return $val;
            }
        }
        if (isset($blocks['other'])) { return $blocks['other']; }
        if (isset($blocks['en'])) { return $blocks['en']; }
        return reset($blocks);
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
            return get_string('scope_all_' . $itemtype, 'local_nit_commerce');
        }
        if ($itemtype === self::TYPE_COURSE) {
            $name = $DB->get_field('course', 'fullname', array('id' => $itemid));
            return $name !== false ? format_string(self::resolve_mlang($name)) : ('#' . $itemid);
        }
        if ($itemtype === self::TYPE_CATEGORY) {
            $name = \local_nit_core\helper\category::name($itemid);
            return $name !== '' ? $name : ('#' . $itemid);
        }
        if ($itemtype === self::TYPE_PACKAGE) {
            $name = $DB->get_manager()->table_exists('nit_package')
                ? $DB->get_field('nit_package', 'name', array('id' => $itemid)) : false;
        } else if ($itemtype === self::TYPE_SUBSCRIPTION) {
            $name = $DB->get_manager()->table_exists('nit_subscription')
                ? $DB->get_field('nit_subscription', 'name', array('id' => $itemid)) : false;
        } else {
            // Programs are not yet available in the new platform.
            $name = false;
        }
        return $name !== false ? format_string(self::resolve_mlang($name)) : ('#' . $itemid);
    }

    // ── Resolution (used by checkout — retained for when the purchase flow is wired) ──

    /**
     * Every live, in-scope automatic offer for an item, ranked best-for-the-learner first.
     *
     * "Live" means active and inside its own start/end window at $now; "in scope" means one of its
     * {@see nit_offer_item} rows targets this item (or all items of its type). Several offers can
     * legitimately cover the same item at once — a site-wide "all courses" promotion, a campaign on
     * this one course, a launch offer on the package it belongs to — and they never stack.
     *
     * @param string $itemtype
     * @param int $itemid
     * @param float $base base price the offers are measured against
     * @param int|null $now timestamp (defaults to time())
     * @return array of objects {id, name, discount_type, discount_value, discount, final, enddate}
     */
    public static function matching_offers($itemtype, $itemid, $base, $now = null) {
        global $DB;
        $now = $now ?? time();
        $base = round(max(0.0, (float)$base), 2);
        $offers = $DB->get_records('nit_offer', array('status' => 'active'));
        $out = array();
        foreach ($offers as $offer) {
            if ($offer->startdate > 0 && $now < $offer->startdate) { continue; }
            if ($offer->enddate > 0 && $now > $offer->enddate) { continue; }
            $items = $DB->get_records('nit_offer_item', array('offerid' => $offer->id));
            if (!self::scope_matches($items, $itemtype, $itemid)) { continue; }
            $discount = self::discount_amount($offer->discount_type, $offer->discount_value, null, $base);
            $out[] = (object) array(
                'id'             => (int)$offer->id,
                // Resolve {mlang} to the current language for display (the {mlang} filter is not
                // installed here), so the offer name shows translated in the checkout modal.
                'name'           => format_string(self::resolve_mlang($offer->name)),
                'discount_type'  => $offer->discount_type,
                'discount_value' => (float)$offer->discount_value,
                'discount'       => $discount,
                'final'          => round(max(0.0, $base - $discount), 2),
                'enddate'        => (int)$offer->enddate,
            );
        }
        usort($out, array(self::class, 'compare_offers'));
        return $out;
    }

    /**
     * Rank two competing offers: the one leaving the learner paying least comes first (AC-4.13.4).
     *
     * Both sides were measured against the same base price, so a percentage and a fixed amount are
     * compared as the MONEY each takes off this item — not rate against rate. 20% of 300 (60) beats
     * a flat 50 and loses to a flat 100; on a 200 item those same two offers swap places, which is
     * exactly why the comparison is on final prices rather than on headline numbers.
     *
     * The remaining two steps only ever run on an exact tie in price, and exist so that the same
     * item always resolves to the SAME offer: the price the buyer was shown, the offer named on the
     * checkout screen and the row written to {@see nit_offer_usage} have to agree, and a tie broken
     * by whatever order the database happened to return would make the report impossible to
     * reconcile. Of two offers worth exactly the same, the one ending sooner is spent first (an
     * open-ended offer, 0, is treated as ending last — it will still be there tomorrow); a still
     * exact tie falls to the lower id, i.e. the offer created first.
     *
     * @param object $a
     * @param object $b
     * @return int
     */
    private static function compare_offers($a, $b) {
        $cmp = $a->final <=> $b->final;
        if ($cmp !== 0) {
            return $cmp;
        }
        $aend = $a->enddate > 0 ? $a->enddate : PHP_INT_MAX;
        $bend = $b->enddate > 0 ? $b->enddate : PHP_INT_MAX;
        if ($aend !== $bend) {
            return $aend <=> $bend;
        }
        return $a->id <=> $b->id;
    }

    /**
     * The automatic offer that leaves the learner with the lowest price for an item, or null when
     * none is live and in scope (AC-4.13.4).
     *
     * @param string $itemtype
     * @param int $itemid
     * @param float $base base price
     * @param int|null $now timestamp (defaults to time())
     * @return object|null the winner, with `candidates` = how many offers competed for this item
     */
    public static function best_offer($itemtype, $itemid, $base, $now = null) {
        $matches = self::matching_offers($itemtype, $itemid, $base, $now);
        if (!$matches) {
            return null;
        }
        $best = $matches[0];
        $best->candidates = count($matches);
        return $best;
    }

    /**
     * The best automatic-offer discount for an item. Offers do NOT stack: where several cover the
     * same item, only the one giving the lowest price to the learner is applied (AC-4.13.4).
     *
     * @param string $itemtype
     * @param int $itemid
     * @param float $base
     * @param int|null $now
     * @return array {offers: array of {id, name, discount}, total: float, candidates: int}
     */
    public static function offers_discount($itemtype, $itemid, $base, $now = null) {
        $base = round(max(0.0, (float)$base), 2);
        $matches = self::matching_offers($itemtype, $itemid, $base, $now);
        $best = $matches ? $matches[0] : null;
        if (!$best || $best->discount <= 0) {
            // Offers that matched but take nothing off (a 0% campaign, or a fixed amount that
            // rounds away) still count as candidates: the number reported is "how many covered
            // this item", which is the question asked, not "how many were worth applying".
            return array('offers' => array(), 'total' => 0.0, 'candidates' => count($matches));
        }
        $total = min(round((float)$best->discount, 2), $base);
        return array(
            'offers'     => array(array('id' => (int)$best->id, 'name' => $best->name, 'discount' => $total)),
            'total'      => $total,
            'candidates' => count($matches),
        );
    }

    /**
     * A compact, display-ready summary of the best automatic offer on an item, or null.
     *
     * @param string $itemtype course | package | subscription | program
     * @param int $itemid
     * @param float|null $base base price (resolved if null)
     * @param int|null $now
     * @return array|null
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
        $pct = $base > 0 ? round($total / $base * 100) : 0;
        return array(
            'id'             => (int) $od['offers'][0]['id'],
            'name'           => format_string($od['offers'][0]['name']),
            'discount_type'  => self::DISCOUNT_PERCENT,
            'discount_value' => $pct,
            'discount'       => round($total, 2),
            'original'       => $base,
            'final'          => round(max(0.0, $base - $total), 2),
            'label'          => '-' . rtrim(rtrim(number_format($pct, 2), '0'), '.') . '%',
            // How many live offers covered this item; the winner is the one named above.
            'candidates'     => (int) ($od['candidates'] ?? 1),
        );
    }

    /** @var string[] Transaction states under which a usage row no longer holds a slot. */
    const DEAD_STATUSES = array('failed', 'cancelled', 'expired', 'timed_out');

    /**
     * SQL fragment (plus params) that keeps only the {nit_coupon_usage} rows still holding a
     * slot against a coupon's cap, given the row is joined to its transaction as `t`.
     *
     * A usage row is written twice over: as a *reservation* when a checkout is created, and
     * confirmed as-is when the payment lands. So the table alone cannot say whether a coupon
     * was spent — only the owning transaction can. A row whose transaction failed, was
     * cancelled, expired, or is pending past its own expiry is dead weight the cleanup task
     * has not swept yet, and must not read as a redemption. A row with no transaction at all
     * (a manual or legacy entry) is taken at face value.
     *
     * @param int $now
     * @param string $prefix param-name prefix, so two fragments can share one query
     * @return array [sql, params]
     */
    private static function live_usage_sql($now, $prefix = 'lu') {
        $dead = array();
        $params = array($prefix . 'now' => (int) $now);
        foreach (self::DEAD_STATUSES as $i => $status) {
            $dead[] = ':' . $prefix . 'dead' . $i;
            $params[$prefix . 'dead' . $i] = $status;
        }
        // expires_at is nullable; COALESCE keeps a NULL from turning the whole predicate
        // NULL (which would drop a live pending row from the count rather than keep it).
        $sql = "(t.id IS NULL OR (t.status NOT IN (" . implode(',', $dead) . ")
                    AND NOT (t.status = 'pending' AND COALESCE(t.expires_at, 0) > 0
                             AND t.expires_at < :{$prefix}now)))";
        return array($sql, $params);
    }

    /**
     * Whether the transactions table is there to join against. nit_commerce can be installed
     * ahead of local_payments; without it every usage row is taken at face value.
     *
     * @return bool
     */
    private static function has_transactions_table() {
        global $DB;
        static $exists = null;
        if ($exists === null) {
            $exists = $DB->get_manager()->table_exists('local_payments_transactions');
        }
        return $exists;
    }

    /**
     * How many slots of a coupon's cap are taken: confirmed redemptions plus reservations
     * held by checkouts that are still live.
     *
     * A buyer who opened the gateway page with this coupon and came back without paying has a
     * reservation of their own still standing. When THAT buyer checks out again the reservation
     * is either reused (same order) or retired and re-made, so counting it against them would
     * refuse a coupon nobody has spent — pass their id in $ignorependingof and their own pending
     * reservations are left out of the count. Other buyers' pending reservations still count:
     * they hold the slot until they pay or their order dies.
     *
     * @param int $couponid
     * @param int $ignorependingof user id whose own pending reservations are not counted, 0 for none
     * @param int|null $now
     * @return int
     */
    public static function live_usage_count($couponid, $ignorependingof = 0, $now = null) {
        global $DB;
        $couponid = (int) $couponid;
        if (!self::has_transactions_table()) {
            return $DB->count_records('nit_coupon_usage', array('couponid' => $couponid));
        }
        list($livesql, $params) = self::live_usage_sql($now ?? time());
        $params['couponid'] = $couponid;
        $own = '';
        if ((int) $ignorependingof > 0) {
            $own = " AND NOT (t.status = 'pending' AND cu.userid = :ownuserid)";
            $params['ownuserid'] = (int) $ignorependingof;
        }
        return (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {nit_coupon_usage} cu
          LEFT JOIN {local_payments_transactions} t ON t.id = cu.transactionid
              WHERE cu.couponid = :couponid AND {$livesql}{$own}", $params);
    }

    /**
     * Whether a user has actually redeemed a coupon: a usage row whose transaction is neither
     * dead nor still pending. Their own open checkout is a reservation, not a redemption.
     *
     * @param int $couponid
     * @param int $userid
     * @param int|null $now
     * @return bool
     */
    public static function user_has_redeemed($couponid, $userid, $now = null) {
        global $DB;
        $couponid = (int) $couponid;
        $userid = (int) $userid;
        if ($userid <= 0) {
            return false;
        }
        if (!self::has_transactions_table()) {
            return $DB->record_exists('nit_coupon_usage', array('couponid' => $couponid, 'userid' => $userid));
        }
        list($livesql, $params) = self::live_usage_sql($now ?? time());
        $params['couponid'] = $couponid;
        $params['userid'] = $userid;
        return $DB->record_exists_sql(
            "SELECT 1
               FROM {nit_coupon_usage} cu
          LEFT JOIN {local_payments_transactions} t ON t.id = cu.transactionid
              WHERE cu.couponid = :couponid AND cu.userid = :userid
                    AND {$livesql} AND (t.id IS NULL OR t.status <> 'pending')", $params);
    }

    /**
     * Every coupon a user has actually redeemed (see {@see self::user_has_redeemed()}), in one
     * query, for list screens.
     *
     * @param int $userid
     * @param int|null $now
     * @return int[] coupon ids
     */
    public static function coupons_redeemed_by($userid, $now = null) {
        global $DB;
        $userid = (int) $userid;
        if ($userid <= 0) {
            return array();
        }
        if (!self::has_transactions_table()) {
            return $DB->get_fieldset_select('nit_coupon_usage', 'DISTINCT couponid',
                'userid = :userid', array('userid' => $userid));
        }
        list($livesql, $params) = self::live_usage_sql($now ?? time());
        $params['userid'] = $userid;
        return $DB->get_fieldset_sql(
            "SELECT DISTINCT cu.couponid
               FROM {nit_coupon_usage} cu
          LEFT JOIN {local_payments_transactions} t ON t.id = cu.transactionid
              WHERE cu.userid = :userid AND {$livesql} AND (t.id IS NULL OR t.status <> 'pending')", $params);
    }

    /**
     * Validate a coupon code for an item + user, or throw a moodle_exception describing why.
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
            throw new \moodle_exception('err_couponcoderequired', 'local_nit_commerce');
        }
        $coupon = $DB->get_record_select('nit_coupon', $DB->sql_equal('code', ':code', false),
            array('code' => $code));
        if (!$coupon) {
            throw new \moodle_exception('err_couponnotfound', 'local_nit_commerce');
        }
        if ($coupon->status !== 'active') {
            throw new \moodle_exception('err_couponinactive', 'local_nit_commerce');
        }
        if ($coupon->startdate > 0 && $now < $coupon->startdate) {
            throw new \moodle_exception('err_couponnotstarted', 'local_nit_commerce');
        }
        if ($coupon->enddate > 0 && $now > $coupon->enddate) {
            throw new \moodle_exception('err_couponexpired', 'local_nit_commerce');
        }
        $items = $DB->get_records('nit_coupon_item', array('couponid' => $coupon->id));
        if (!self::scope_matches($items, $itemtype, $itemid)) {
            throw new \moodle_exception('err_couponnotapplicable', 'local_nit_commerce');
        }
        // The user's own still-pending reservation (a gateway page they opened and left) is
        // not a redemption: their next checkout reuses or retires it, so it is not counted.
        $used = self::live_usage_count($coupon->id, (int) $userid, $now);
        if ($coupon->usage_type === 'once') {
            if ($used >= 1) {
                throw new \moodle_exception('err_couponusedup', 'local_nit_commerce');
            }
        } else if ((int)$coupon->usage_limit > 0 && $used >= (int)$coupon->usage_limit) {
            throw new \moodle_exception('err_couponusedup', 'local_nit_commerce');
        }
        // One redemption per user, on top of any global cap: a coupon this user
        // has already redeemed cannot be applied by them again.
        if (!empty($userid) && self::user_has_redeemed($coupon->id, (int) $userid, $now)) {
            throw new \moodle_exception('err_couponalreadyusedbyuser', 'local_nit_commerce');
        }
        return $coupon;
    }

    /**
     * Compute the charged price for an item: the best automatic offer and the entered coupon
     * compete, and the larger of the two wins outright (AC-4.12.6).
     *
     * The two are deliberately NOT combined and NOT applied one after the other. Both are measured
     * against the same undiscounted base, so "larger" is a straight comparison of two amounts in
     * the same currency — a 20% offer and a "50 off" coupon are compared as the money each
     * actually takes off this item, not as a rate against a rate. A tie goes to the offer, so a
     * coupon is spent only when it genuinely beats what the buyer would have got for free. The
     * loser is still reported (so the checkout can explain itself) but carries a zero amount,
     * which is what keeps {@see self::reserve_usage()} and {@see self::do_record_usage()} from
     * recording a redemption against it.
     *
     * The order can never fall below zero (AC-4.12.7): {@see self::discount_amount()} caps each
     * candidate at the base price, and the final subtraction is clamped again.
     *
     * @param string $itemtype course | package | subscription
     * @param int $itemid
     * @param int $userid buyer
     * @param string $couponcode optional code entered at checkout
     * @param float|null $baseprice override base price; else resolved
     * @param int|null $now
     * @return array
     */
    public static function resolve($itemtype, $itemid, $userid, $couponcode = '', $baseprice = null, $now = null) {
        $itemtype = self::normalize_item_type($itemtype);
        $now = $now ?? time();
        $base = $baseprice !== null ? (float)$baseprice : self::price_of($itemtype, $itemid);
        $base = round(max(0.0, $base), 2);

        $result = array(
            'original'          => $base,
            'offers'            => array(),
            'offer_id'          => 0,
            'offer_name'        => '',
            'offer_discount'    => 0.0,
            'coupon_id'         => 0,
            'coupon_code'       => '',
            'coupon_discount'   => 0.0,
            // Which of the two won, and what each would have taken off on its own. The checkout
            // reads these to explain a coupon that was perfectly valid but simply beaten.
            'applied'           => 'none',
            'offer_candidate'   => 0.0,
            'coupon_candidate'  => 0.0,
            'coupon_superseded' => false,
            // How many live offers covered this item. More than one means they competed and the
            // cheapest won (AC-4.13.4); the checkout says so rather than leaving the buyer to
            // wonder why a promotion they read about is not the one on screen.
            'offer_candidates'  => 0,
            // The entered coupon's usage cap (the admin's "Usage limit (optional)"), so the
            // checkout can tell the buyer how many redemptions the code has left rather than
            // only whether it worked. Filled only for a coupon that validated; `uses_left` is
            // null when the coupon is uncapped.
            'coupon_usage_type'  => '',
            'coupon_usage_limit' => 0,
            'coupon_usage_count' => 0,
            'coupon_uses_left'   => null,
            // The coupon's "Max discount amount (optional)" and whether it bit: a 30% code on
            // a 500 order that takes off 50 looks broken unless the buyer is told the cap did it.
            'coupon_max_discount' => null,
            'coupon_max_hit'      => false,
            'discount'          => 0.0,
            'final'             => $base,
        );

        // Candidate 1: the best automatic offer, measured against the undiscounted base. Where
        // several offers cover this item, offers_discount() has already picked the one that
        // leaves the learner paying least (AC-4.13.4) — they never stack.
        $od = self::offers_discount($itemtype, $itemid, $base, $now);
        $offeramount = round((float) $od['total'], 2);
        $result['offer_candidates'] = (int) ($od['candidates'] ?? 0);

        // Candidate 2: the entered coupon, measured against the SAME undiscounted base — never
        // against an offer-reduced running total, because the two never compound.
        $coupon = null;
        $couponamount = 0.0;
        $couponcode = trim((string)$couponcode);
        if ($couponcode !== '') {
            $coupon = self::validate_coupon($couponcode, $itemtype, $itemid, $userid, $now);
            $couponamount = self::discount_amount($coupon->discount_type, $coupon->discount_value,
                $coupon->max_discount, $base);
            // Same count validate_coupon() just measured the cap against, so "left" can never
            // disagree with the accept/refuse decision above.
            $used = self::live_usage_count($coupon->id, (int) $userid, $now);
            $limit = (int) $coupon->usage_limit;
            $result['coupon_usage_type']  = (string) $coupon->usage_type;
            $result['coupon_usage_limit'] = $limit;
            $result['coupon_usage_count'] = $used;
            $result['coupon_uses_left']   = $limit > 0 ? max(0, $limit - $used) : null;
            $max = ($coupon->max_discount !== null && (float) $coupon->max_discount > 0)
                ? round((float) $coupon->max_discount, 2) : null;
            $result['coupon_max_discount'] = $max;
            // Measured against the uncapped amount, so "hit" means the cap — not the order
            // total — is what held the discount down.
            $result['coupon_max_hit'] = $max !== null
                && self::discount_amount($coupon->discount_type, $coupon->discount_value, null, $base) > $max;
        }

        $result['offer_candidate']  = $offeramount;
        $result['coupon_candidate'] = $couponamount;

        // Strictly greater: an equal-value coupon loses, so the offer is applied (AC-4.12.6).
        $couponwins = ($coupon !== null) && ($couponamount > $offeramount) && ($couponamount > 0);

        if ($couponwins) {
            $result['coupon_id']       = (int)$coupon->id;
            $result['coupon_code']     = $coupon->code;
            $result['coupon_discount'] = $couponamount;
            $result['applied']         = 'coupon';
            // The beaten offer is named for display only: no amount and no 'offers' row, so no
            // offer usage is recorded for a purchase the offer did not discount.
            if ($offeramount > 0) {
                $result['offer_id']   = (int) $od['offers'][0]['id'];
                $result['offer_name'] = format_string($od['offers'][0]['name']);
            }
            $applied = $couponamount;

        } else if ($offeramount > 0) {
            $result['offers']         = $od['offers'];
            $result['offer_discount'] = $offeramount;
            $result['offer_id']       = (int) $od['offers'][0]['id'];
            $result['offer_name']     = format_string($od['offers'][0]['name']);
            $result['applied']        = 'offer';
            if ($coupon !== null) {
                // Valid and in scope, just beaten. Named with a zero amount so the checkout can
                // say why it did nothing, and so no redemption is recorded against it.
                $result['coupon_id']         = (int)$coupon->id;
                $result['coupon_code']       = $coupon->code;
                $result['coupon_superseded'] = true;
            }
            $applied = $offeramount;

        } else if ($coupon !== null && $couponamount > 0) {
            // No offer on this item — the coupon stands alone.
            $result['coupon_id']       = (int)$coupon->id;
            $result['coupon_code']     = $coupon->code;
            $result['coupon_discount'] = $couponamount;
            $result['applied']         = 'coupon';
            $applied = $couponamount;

        } else {
            $applied = 0.0;
        }

        // AC-4.12.7: a discount larger than the order value produces a zero-value order, never a
        // negative one. discount_amount() already capped each candidate at $base; this is the belt
        // to that braces, and it absorbs any rounding drift as well.
        $result['final']    = round(max(0.0, $base - $applied), 2);
        $result['discount'] = round($base - $result['final'], 2);
        return $result;
    }

    /**
     * Reserve coupon/offer usage for a still-pending checkout, so concurrent
     * checkouts cannot over-redeem a capped coupon. The reservation IS the usage
     * row tied to the pending transaction: it is confirmed as-is at fulfilment
     * (record_usage is idempotent) and removed by {@see self::release_usage()} or
     * the cleanup task if the payment fails or is abandoned.
     *
     * Under a per-coupon lock it re-checks the coupon's global + per-user limits,
     * so two simultaneous reservations can't both pass.
     *
     * @param array $resolved discount metadata (see resolve() / apply_nit_discount)
     * @param int $userid
     * @param int $transactionid local_payments_transactions.id
     * @param string $itemtype
     * @param int $itemid
     * @return void
     * @throws \moodle_exception if the coupon's limit is already reached
     */
    public static function reserve_usage(array $resolved, $userid, $transactionid, $itemtype, $itemid) {
        global $DB;
        $couponid = (int) ($resolved['coupon_id'] ?? 0);
        $hascoupon = $couponid > 0 && ($resolved['coupon_discount'] ?? 0) > 0;

        // No coupon → only automatic (uncapped) offers to record; no lock needed.
        if (!$hascoupon) {
            self::do_record_usage($DB, $resolved, $userid, (int) $transactionid, $itemtype, $itemid, time());
            return;
        }

        // Serialise reservations of the same coupon so the limit check + insert
        // are atomic across concurrent checkouts.
        $lock = \core\lock\lock_config::get_lock_factory('local_nit_commerce')
            ->get_lock('coupon_reserve_' . $couponid, 10);
        if (!$lock) {
            throw new \moodle_exception('err_couponbusy', 'local_nit_commerce');
        }
        try {
            $coupon = $DB->get_record('nit_coupon', array('id' => $couponid));
            if ($coupon) {
                // Same rule as validate_coupon(): this buyer's own pending reservation — an
                // earlier checkout they walked away from — is superseded by this one, so it
                // neither takes a slot nor counts as their redemption.
                $used = self::live_usage_count($couponid, (int) $userid);
                if ($coupon->usage_type === 'once' && $used >= 1) {
                    throw new \moodle_exception('err_couponusedup', 'local_nit_commerce');
                }
                if ((int) $coupon->usage_limit > 0 && $used >= (int) $coupon->usage_limit) {
                    throw new \moodle_exception('err_couponusedup', 'local_nit_commerce');
                }
                if (!empty($userid) && self::user_has_redeemed($couponid, (int) $userid)) {
                    throw new \moodle_exception('err_couponalreadyusedbyuser', 'local_nit_commerce');
                }
            }
            self::do_record_usage($DB, $resolved, $userid, (int) $transactionid, $itemtype, $itemid, time());
        } finally {
            $lock->release();
        }
    }

    /**
     * Release (delete) any coupon/offer usage rows reserved for a transaction,
     * when its payment failed or was abandoned — freeing the coupon for others.
     * A no-op for a fulfilled (paid) transaction should never be called.
     *
     * @param int $transactionid
     * @return void
     */
    public static function release_usage($transactionid) {
        global $DB;
        $transactionid = (int) $transactionid;
        if ($transactionid <= 0) {
            return;
        }
        $DB->delete_records('nit_coupon_usage', array('transactionid' => $transactionid));
        $DB->delete_records('nit_offer_usage', array('transactionid' => $transactionid));
    }

    /**
     * Record coupon + offer usage after a successful payment. Idempotent per transaction.
     *
     * @param array $resolved output of {@see self::resolve()} (or the stored discount metadata)
     * @param int $userid
     * @param int $transactionid local_payments_transactions.id
     * @param string $itemtype
     * @param int $itemid
     * @return void
     */
    public static function record_usage(array $resolved, $userid, $transactionid, $itemtype, $itemid) {
        global $DB;
        $now = time();
        $transactionid = (int)$transactionid;
        $couponid = (int) ($resolved['coupon_id'] ?? 0);
        $hascoupon = $couponid > 0 && ($resolved['coupon_discount'] ?? 0) > 0;

        // Idempotency lock: a single transaction can be fulfilled twice when the
        // gateway webhook and the browser-redirect callback race. Serialise on the
        // transaction id so the "does a usage row for this transaction exist?"
        // check-then-insert below cannot double-record offer/coupon usage.
        $lock = null;
        if ($transactionid > 0) {
            $lockfactory = \core\lock\lock_config::get_lock_factory('local_nit_commerce');
            $lock = $lockfactory->get_lock('record_usage_' . $transactionid, 10);
        }

        // AC-4.12.9: the counter is COUNT(nit_coupon_usage), so "incrementing it" IS this insert.
        // Normally the row already exists — reserve_usage() wrote it under the same coupon lock at
        // checkout — and the existence check below simply confirms it. But a fulfilment that
        // arrives with no reservation (a flow that skipped reserve_usage, or a reservation the
        // cleanup task released while the gateway was still deciding) would otherwise insert
        // outside any lock, and two of those could both land past a cap. Take the same per-coupon
        // lock reserve_usage() uses so every path that can add to the count is serialised on it.
        // A lock we cannot get is not a reason to lose a fulfilment the buyer has already paid
        // for: record it anyway. Refusing here would drop the redemption from the report while
        // the money stands, which is a worse failure than a cap overshot by one.
        $couponlock = null;
        if ($hascoupon) {
            $couponlock = \core\lock\lock_config::get_lock_factory('local_nit_commerce')
                ->get_lock('coupon_reserve_' . $couponid, 10);
        }

        try {
            self::do_record_usage($DB, $resolved, $userid, $transactionid, $itemtype, $itemid, $now);
        } finally {
            if ($couponlock) {
                $couponlock->release();
            }
            if ($lock) {
                $lock->release();
            }
        }
    }

    /**
     * Insert the offer/coupon usage rows for a transaction (idempotent per
     * transaction). Runs inside the transaction lock held by record_usage().
     *
     * @param \moodle_database $DB
     * @param array $resolved
     * @param int $userid
     * @param int $transactionid
     * @param string $itemtype
     * @param int $itemid
     * @param int $now
     * @return void
     */
    private static function do_record_usage($DB, array $resolved, $userid, $transactionid, $itemtype, $itemid, $now) {
        $offers = (isset($resolved['offers']) && is_array($resolved['offers'])) ? $resolved['offers'] : array();
        if (empty($offers) && !empty($resolved['offer_id']) && ($resolved['offer_discount'] ?? 0) > 0) {
            $offers = array(array('id' => $resolved['offer_id'], 'discount' => $resolved['offer_discount']));
        }
        foreach ($offers as $off) {
            $off = (array)$off;
            $oid = (int)($off['id'] ?? 0);
            $odisc = round((float)($off['discount'] ?? 0), 2);
            if ($oid <= 0 || $odisc <= 0) { continue; }
            $exists = $transactionid > 0 && $DB->record_exists('nit_offer_usage',
                array('offerid' => $oid, 'transactionid' => $transactionid));
            if ($exists) { continue; }
            $DB->insert_record('nit_offer_usage', (object) array(
                'offerid'         => $oid,
                'userid'          => $userid,
                'transactionid'   => $transactionid,
                'item_type'       => $itemtype,
                'item_id'         => (int)$itemid,
                'original_amount' => $resolved['original'] ?? 0,
                'discount_amount' => $odisc,
                'final_amount'    => round((float)($resolved['original'] ?? 0) - $odisc, 2),
                'timecreated'     => $now,
            ));
        }

        if (!empty($resolved['coupon_id']) && ($resolved['coupon_discount'] ?? 0) > 0) {
            $exists = $transactionid > 0 && $DB->record_exists('nit_coupon_usage',
                array('couponid' => $resolved['coupon_id'], 'transactionid' => $transactionid));
            if (!$exists) {
                $DB->insert_record('nit_coupon_usage', (object) array(
                    'couponid'        => $resolved['coupon_id'],
                    'userid'          => $userid,
                    'transactionid'   => $transactionid,
                    'item_type'       => $itemtype,
                    'item_id'         => (int)$itemid,
                    'original_amount' => $resolved['original'] ?? 0,
                    'discount_amount' => $resolved['coupon_discount'],
                    'final_amount'    => $resolved['final'] ?? 0,
                    'timecreated'     => $now,
                ));
            }
        }
    }
}
