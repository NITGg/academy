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

namespace local_nit_category;

use context_course;

/**
 * What a course card has to say about money and access, resolved once per course.
 *
 * This is the ONE place a card's price is resolved. It used to live as a closure inside
 * the category page; the catalogue needs the same answers, and two implementations would
 * eventually disagree — a card showing "Free" beside its own "Buy now" button is exactly
 * the bug that shape produces.
 *
 * Guest vs logged in: local_payments prices are country-aware and keyed on the viewer, so
 * the user id is passed through in BOTH states. A logged-in user is priced by their profile
 * country and nothing else — with that field empty the card shows no price and no Buy button
 * at all, because a price they were never quoted is worse than no price. A guest is priced by
 * IP geolocation, falling back to the course's default price.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pricing {

    /** @var array<int, array> Resolved course states, keyed by course id. */
    private static $cache = [];

    /** @var array|null The "set your country" notice, built at most once per request. */
    private static $notice = null;

    /**
     * Whether the commerce stack that prices courses is installed.
     *
     * @return bool
     */
    public static function available(): bool {
        global $CFG;
        return class_exists('\local_payments\price_resolver')
            && file_exists($CFG->dirroot . '/local/nit_commerce/lib.php')
            && class_exists('\local_nit_commerce\discount_manager');
    }

    /**
     * Enrolment, purchase, subscription coverage, price and offer for one course.
     *
     * @param int $courseid
     * @return array{enrolled:bool,purchased:bool,covered:bool,free:bool,haspricing:bool,
     *               price:float,currency:string,offerlabel:string,offerfinal:float,countryrequired:bool}
     */
    public static function info(int $courseid): array {
        global $USER, $DB, $CFG;

        if (isset(self::$cache[$courseid])) {
            return self::$cache[$courseid];
        }

        $out = ['enrolled' => false, 'purchased' => false, 'covered' => false, 'free' => true,
            'haspricing' => false, 'price' => 0.0, 'currency' => '', 'offerlabel' => '', 'offerfinal' => 0.0,
            'countryrequired' => false];

        $uid = (int) ($USER->id ?? 0);
        $out['enrolled'] = $uid > 0 && is_enrolled(context_course::instance($courseid), $uid, '', true);

        if (!self::available()) {
            return self::$cache[$courseid] = $out;
        }
        require_once($CFG->dirroot . '/local/nit_commerce/lib.php');

        // "Paid" means local_payments has an active rule — the same test enrol.php and buy.php
        // gate on, so a card can never offer a flow the server will refuse.
        $out['haspricing'] = (bool) \local_payments\price_resolver::has_pricing($courseid);
        $out['free'] = !$out['haspricing'];
        if (!$out['haspricing']) {
            return self::$cache[$courseid] = $out;
        }

        // An enrolled viewer skips the purchase/coverage probes — the "Enrolled" badge already
        // wins over both — but NOT the price resolution below: the card prints the course price
        // next to that badge, so a paid course always shows what it costs, enrolled or not.
        if (!$out['enrolled']) {
            $out['purchased'] = $uid > 0 && \local_payments\price_resolver::is_purchased($courseid, $uid);
            if (!$out['purchased'] && class_exists('\local_nit_subscriptions\subscription_purchase_manager')) {
                $out['covered'] = (bool) \local_payments\price_resolver::is_covered_by_active_subscription($courseid, $uid);
            }
        }

        try {
            $priced = \local_payments\price_resolver::resolve($courseid, $uid);
            $out['price'] = (float) $priced->price;
            $out['currency'] = (string) $priced->currency;
        } catch (\local_payments\country_required_exception $e) {
            // Signed in with no profile country — no price exists for this viewer. Caught before
            // the generic handler on purpose: that one reaches past the resolver for "any active
            // rule", which is exactly the borrowed price this rule forbids.
            $out['countryrequired'] = true;
            return self::$cache[$courseid] = $out;
        } catch (\Throwable $e) {
            // resolve() throws when nothing matches the viewer's country AND the course has no
            // default rule — a per-viewer miss, not a free course. Fall back to any active rule
            // so the card still shows a real price instead of claiming the course is free.
            $fallback = $DB->get_record_select(
                'local_payments_course_prices',
                'courseid = :courseid AND is_active = 1',
                ['courseid' => $courseid],
                'price, currency',
                IGNORE_MULTIPLE
            );
            if ($fallback) {
                $out['price'] = (float) $fallback->price;
                $out['currency'] = (string) $fallback->currency;
            }
        }

        if ($out['price'] > 0) {
            try {
                $summary = \local_nit_commerce\discount_manager::offer_summary('course', $courseid, $out['price']);
                if ($summary) {
                    $out['offerlabel'] = $summary['label'];   // e.g. "-40%".
                    $out['offerfinal'] = (float) $summary['final'];
                }
            } catch (\Throwable $e) {
                // No offer engine / bad offer data — just show the undiscounted price.
            }
        }

        return self::$cache[$courseid] = $out;
    }

    /**
     * What the viewer would actually pay, for sorting and range filtering: the discounted
     * amount when an offer applies, otherwise the plain price.
     *
     * @param array $info from {@see self::info()}
     * @return float
     */
    public static function effective_price(array $info): float {
        if ($info['offerlabel'] !== '' && $info['offerfinal'] > 0) {
            return (float) $info['offerfinal'];
        }
        return (float) $info['price'];
    }

    /**
     * One money formatter for every price a card prints — the base price, the struck-through
     * original and the discounted final all go through it, so they can never disagree on
     * decimals or currency. Digits stay unlocalised, matching the rest of the shop.
     *
     * @param float $amount
     * @param string $currency
     * @return string
     */
    public static function money(float $amount, string $currency): string {
        if ($currency === '') {
            $currency = get_string('defaultcurrency', 'local_nit_category');
        }
        return format_float($amount, 2, false) . ' ' . $currency;
    }

    /**
     * The "set your country" notice, or [] when this viewer is priced normally.
     *
     * @return array
     */
    public static function country_notice(): array {
        if (self::$notice !== null) {
            return self::$notice;
        }
        self::$notice = [];
        if (class_exists('\local_payments\country_detector') && \local_payments\country_detector::pricing_blocked()) {
            self::$notice = \local_payments\country_detector::country_required_notice();
        }
        return self::$notice;
    }
}
