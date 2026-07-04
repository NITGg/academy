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
     * Build the context array for rendering the local_payments/course_card_price
     * template for a given course, from any course-listing page (catalog grids,
     * category pages, etc).
     */
    public static function card_context(int $courseid, ?int $userid = null): array {
        global $USER;

        $userid = $userid ?? (int) ($USER->id ?? 0);
        $is_enrolled = $userid > 0 ? enrollment_handler::is_enrolled($userid, $courseid) : false;
        $course_url = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);

        // Auto-detect subscription coverage for card context
        if (!$is_enrolled && $userid > 0 && class_exists('\local_academy\subscription_purchase_manager') && class_exists('\local_academy\subscription_manager')) {
            $activesub = \local_academy\subscription_purchase_manager::get_active_subscription($userid);
            if ($activesub) {
                $covered_courses = \local_academy\subscription_manager::courses_for_subscription($activesub->subscriptionid);
                if (in_array($courseid, $covered_courses)) {
                    $is_enrolled = true;
                }
            }
        }

        if ($is_enrolled || !self::has_pricing($courseid)) {
            return [
                'is_enrolled' => $is_enrolled,
                'is_free' => !self::has_pricing($courseid),
                'is_purchased' => false,
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
                'course_url' => $course_url,
            ];
        }

        return [
            'is_enrolled' => false,
            'is_free' => false,
            'is_purchased' => false,
            'price' => number_format((float) $pricing->price, 2),
            'sale_price' => $pricing->sale_price !== null ? number_format((float) $pricing->sale_price, 2) : '',
            'original_price' => number_format((float) $pricing->original_price, 2),
            'currency' => $pricing->currency,
            'is_sale_active' => (bool) $pricing->is_sale_active,
            'discount_pct' => (int) $pricing->discount_pct,
            'buy_url' => $buy_url,
            'course_url' => $course_url,
        ];
    }
}
