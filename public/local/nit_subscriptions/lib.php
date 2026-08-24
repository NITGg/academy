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
 * Library functions for local_nit_subscriptions.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Build an associative map of localized strings for the given keys, for shipping to JS.
 *
 * @param array $keys string keys in local_nit_subscriptions
 * @return array key => localized string
 */
function local_nit_subscriptions_string_map(array $keys): array {
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = get_string($key, 'local_nit_subscriptions');
    }
    return $out;
}

/**
 * Active subscription plans shaped for public listing (front-page block + mobile web service).
 *
 * Each plan carries its price, unlocked courses, B2B seat tiers and the best current auto-offer.
 * Shared by api.php (?function=get_available_subscriptions) and the get_available_subscriptions
 * external function so both return the identical shape.
 *
 * @param string|null $app_country optional ISO 3166-1 alpha-2 country to price for (e.g. from the
 *        mobile app). When null, the caller's profile country (or IP for guests) is used.
 * @return array
 */
function nit_subscriptions_available(?string $app_country = null): array {
    $subs = \local_nit_subscriptions\subscription_manager::get_subscriptions(
        \local_nit_subscriptions\subscription_manager::STATUS_ACTIVE);
    $hasoffers = class_exists('\local_nit_commerce\discount_manager');
    $out = [];
    foreach ($subs as $s) {
        // Country-based price/currency for the caller (an explicit country wins; otherwise the
        // profile country, falling back to the plan's default price/currency).
        $resolved = \local_nit_subscriptions\subscription_manager::resolve_price((int) $s->id, null, $app_country);
        $price = (float) $resolved->price;
        $currency = (string) $resolved->currency;
        $country = (string) $resolved->country;

        // The best auto-offer on this plan. Exposed two ways: flat offer_label/offer_final (the web
        // block reads these) and a nested `offer` object (the mobile app reads this) — present only
        // when there IS an active offer.
        $offerlabel = '';
        $offerfinal = 0.0;
        $offer = null;
        if ($hasoffers) {
            $summary = \local_nit_commerce\discount_manager::offer_summary('subscription', (int) $s->id, $price);
            if ($summary) {
                $offerlabel = $summary['label'];      // e.g. "-10%"
                $offerfinal = (float) $summary['final'];
                $offer = [
                    'original' => (float) $summary['original'],
                    'final'    => (float) $summary['final'],
                    'label'    => (string) $summary['label'],
                    'name'     => (string) $summary['name'],
                ];
            }
        }
        $item = [
            'id'            => (int) $s->id,
            'name'          => format_string(\local_nit_subscriptions\subscription_manager::resolve_mlang($s->name)),
            'description'   => $s->description !== null
                ? format_text(\local_nit_subscriptions\subscription_manager::resolve_mlang($s->description), FORMAT_HTML) : '',
            'price'         => $price,
            'currency'      => $currency,
            'country'       => $country,
            'duration_days' => (int) $s->duration_days,
            'status'        => (string) $s->status,
            'b2b_enabled'   => (int) $s->b2b_enabled,
            'courses_count' => count($s->courses),
            // Full {id, fullname} objects — the mobile app needs the course id to map coverage.
            'courses'       => array_values($s->courses),
            // Seat tiers priced off the resolved (country) base price.
            'seat_options'  => \local_nit_subscriptions\subscription_manager::get_seat_options((int) $s->id, $price),
            'offer_label'   => $offerlabel,
            'offer_final'   => $offerfinal,
        ];
        if ($offer !== null) {
            $item['offer'] = $offer;
        }
        $out[] = $item;
    }
    return $out;
}
