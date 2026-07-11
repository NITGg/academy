<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

class price_resolver {

    /**
     * Whether a course has any active pricing rule configured at all.
     * Used to decide whether to apply the payment gate — a course with
     * no active pricing is treated as free/open.
     */
    public static function has_pricing(int $courseid): bool {
        global $DB;
        return $DB->record_exists('local_payments_course_prices', [
            'courseid' => $courseid,
            'is_active' => 1,
        ]);
    }

    /**
     * Resolve the price for a course based on user's detected country.
     *
     * @param int $courseid
     * @param int|null $userid
     * @param string|null $app_country Country from Flutter app.
     * @return object {price_id, price, sale_price, original_price, currency, country, discount_pct, is_sale_active}
     * @throws \moodle_exception if no pricing found.
     */
    public static function resolve(int $courseid, ?int $userid = null, ?string $app_country = null): object {
        global $DB, $USER;

        $userid = $userid ?? $USER->id;
        $country = country_detector::detect($userid, $app_country);
        $now = time();

        // Try country-specific active price.
        $prices = $DB->get_records_select(
            'local_payments_course_prices',
            'courseid = :courseid AND country = :country AND is_active = 1',
            ['courseid' => $courseid, 'country' => $country],
            'priority DESC, id ASC'
        );

        $price_record = null;
        foreach ($prices as $p) {
            // Check date range if set.
            if (!empty($p->start_date) && $now < $p->start_date) {
                continue;
            }
            if (!empty($p->end_date) && $now > $p->end_date) {
                continue;
            }
            $price_record = $p;
            break;
        }

        // Fallback to default price.
        if (!$price_record) {
            $price_record = $DB->get_record_select(
                'local_payments_course_prices',
                'courseid = :courseid AND is_default = 1 AND is_active = 1',
                ['courseid' => $courseid],
                '*',
                IGNORE_MULTIPLE
            );
        }

        if (!$price_record) {
            throw new \moodle_exception('nopricefound', 'local_payments', '', null,
                "No pricing rule found for course {$courseid}, country {$country}");
        }

        // Determine effective price.
        $original_price = (float) $price_record->price;
        $sale_price = null;
        $is_sale_active = false;

        if (!empty($price_record->sale_price) && $price_record->sale_price > 0) {
            $sale_in_range = true;
            if (!empty($price_record->start_date) && $now < $price_record->start_date) {
                $sale_in_range = false;
            }
            if (!empty($price_record->end_date) && $now > $price_record->end_date) {
                $sale_in_range = false;
            }
            if ($sale_in_range) {
                $sale_price = (float) $price_record->sale_price;
                $is_sale_active = true;
            }
        }

        $effective_price = $is_sale_active ? $sale_price : $original_price;
        $discount_pct = ($is_sale_active && $original_price > 0)
            ? round((($original_price - $sale_price) / $original_price) * 100)
            : 0;

        return (object) [
            'price_id' => (int) $price_record->id,
            'price' => $effective_price,
            'sale_price' => $sale_price,
            'original_price' => $original_price,
            'currency' => $price_record->currency,
            'country' => $country,
            'discount_pct' => $discount_pct,
            'is_sale_active' => $is_sale_active,
            'sale_ends_at' => $price_record->end_date,
        ];
    }

    /**
     * Check if a user has already purchased a course.
     */
    public static function is_purchased(int $courseid, int $userid): bool {
        global $DB;
        return $DB->record_exists('local_payments_transactions', [
            'courseid' => $courseid,
            'userid' => $userid,
            'status' => status_machine::COMPLETED,
        ]);
    }

    /**
     * Whether an active subscription of the user's covers this course (grants access but
     * hasn't created real Moodle enrolment yet — see buy.php's action=enroll handler).
     */
    public static function is_covered_by_active_subscription(int $courseid, int $userid): bool {
        if ($userid <= 0 || !class_exists('\local_academy\subscription_purchase_manager')) {
            return false;
        }
        // Covers a normal subscriber, a B2B administrator and an approved B2B member alike — access is
        // resolved live, so a course newly added to any of their plans is picked up immediately.
        return \local_academy\subscription_purchase_manager::subscription_access_for_course($userid, $courseid) !== null;
    }

    /**
     * Build the context array for rendering the local_payments/course_card_price
     * template for a given course, from any course-listing page (catalog grids,
     * category pages, etc).
     */
    public static function card_context(int $courseid, ?int $userid = null): array {
        global $USER;

        $userid = $userid ?? (int) ($USER->id ?? 0);
        $is_enrolled = $userid > 0 ? enrollment_handler::is_enrolled($userid, $courseid) : false;
        $course_url = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);

        // Subscription coverage grants access but doesn't create real Moodle enrolment until
        // the student explicitly clicks "Enroll" (see buy.php's action=enroll handler) — so it
        // must not be treated as already enrolled here.
        $can_enroll_via_sub = !$is_enrolled && self::is_covered_by_active_subscription($courseid, $userid);

        if ($can_enroll_via_sub) {
            return [
                'is_enrolled' => false,
                'is_free' => false,
                'is_purchased' => false,
                'can_enroll_via_sub' => true,
                'course_url' => $course_url,
                'enroll_url' => (new \moodle_url('/local/payments/buy.php',
                    ['courseid' => $courseid, 'action' => 'enroll', 'sesskey' => sesskey()]))->out(false),
            ];
        }

        // The course keeps showing in "My courses" after a subscription/package lapses (the
        // enrolment record survives but is expired). Flag that so the card can invite the
        // student to renew instead of silently looking like a fresh, never-enrolled course.
        $can_renew = !$is_enrolled && $userid > 0
            && enrollment_handler::has_expired_enrolment($userid, $courseid);

        if ($is_enrolled || !self::has_pricing($courseid)) {
            return [
                'is_enrolled' => $is_enrolled,
                'is_free' => !self::has_pricing($courseid),
                'is_purchased' => false,
                'can_renew' => $can_renew,
                'course_url' => $course_url,
            ];
        }

        $buy_url = (new \moodle_url('/local/payments/buy.php', ['courseid' => $courseid]))->out(false);
        $is_purchased = $userid > 0 ? self::is_purchased($courseid, $userid) : false;

        if ($is_purchased) {
            return [
                'is_enrolled' => false,
                'is_free' => false,
                'is_purchased' => true,
                'can_renew' => $can_renew,
                'course_url' => $course_url,
                'buy_url' => $buy_url,
            ];
        }

        try {
            $pricing = self::resolve($courseid, $userid > 0 ? $userid : null);
        } catch (\moodle_exception $e) {
            return [
                'is_enrolled' => false,
                'is_free' => true,
                'is_purchased' => false,
                'can_renew' => $can_renew,
                'course_url' => $course_url,
            ];
        }

        return array_merge([
            'is_enrolled' => false,
            'is_free' => false,
            'is_purchased' => false,
            'can_renew' => $can_renew,
            'buy_url' => $buy_url,
            'course_url' => $course_url,
        ], self::display_fields($pricing, $courseid));
    }

    /**
     * Build the price display fields (price / sale_price / original_price / currency /
     * is_sale_active / discount_pct) for the course card + course page templates, folding in any
     * active local_academy automatic offer (US-US-OF-1-2) so the discounted price the student will
     * actually be charged is shown — not the pre-offer price.
     *
     * The offer applies to $pricing->price, the same base local_payments\manager charges at
     * checkout, so the displayed "was → now" matches the real charge. When a local_payments sale is
     * already active, the offer stacks on the sale price and the struck original stays the true
     * pre-everything price.
     *
     * @param object $pricing result of {@see self::resolve()}
     * @param int $courseid
     * @return array template fields (adds a non-empty offer_name when an offer applies)
     */
    public static function display_fields(object $pricing, int $courseid): array {
        $fields = [
            'price' => number_format((float) $pricing->price, 2),
            'sale_price' => $pricing->sale_price !== null ? number_format((float) $pricing->sale_price, 2) : '',
            'original_price' => number_format((float) $pricing->original_price, 2),
            'currency' => $pricing->currency,
            'is_sale_active' => (bool) $pricing->is_sale_active,
            'discount_pct' => (int) $pricing->discount_pct,
            'offer_name' => '',
        ];

        if (class_exists('\local_academy\discount_manager')) {
            $offer = \local_academy\discount_manager::offer_summary('course', $courseid, (float) $pricing->price);
            if ($offer) {
                $original = (float) $pricing->original_price;
                $final = (float) $offer['final'];
                $fields['is_sale_active'] = true;
                $fields['original_price'] = number_format($original, 2);
                $fields['sale_price'] = number_format($final, 2);
                $fields['discount_pct'] = $original > 0 ? (int) round((($original - $final) / $original) * 100) : 0;
                $fields['offer_name'] = $offer['name'];
            }
        }

        return $fields;
    }
}
