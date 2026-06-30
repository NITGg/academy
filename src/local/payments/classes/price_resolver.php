<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

class price_resolver {

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
}
