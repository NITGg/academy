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
 * @return array
 */
function nit_subscriptions_available(): array {
    $subs = \local_nit_subscriptions\subscription_manager::get_subscriptions(
        \local_nit_subscriptions\subscription_manager::STATUS_ACTIVE);
    $hasoffers = class_exists('\local_nit_commerce\discount_manager');
    $out = [];
    foreach ($subs as $s) {
        // The best auto-offer on this plan (for a card badge). Fixed offers still yield a % label.
        $offerlabel = '';
        $offerfinal = 0.0;
        if ($hasoffers) {
            $summary = \local_nit_commerce\discount_manager::offer_summary('subscription', (int) $s->id, (float) $s->price);
            if ($summary) {
                $offerlabel = $summary['label'];      // e.g. "-10%"
                $offerfinal = (float) $summary['final'];
            }
        }
        $out[] = [
            'id'            => (int) $s->id,
            'name'          => format_string(\local_nit_subscriptions\subscription_manager::resolve_mlang($s->name)),
            'description'   => $s->description !== null
                ? format_text(\local_nit_subscriptions\subscription_manager::resolve_mlang($s->description), FORMAT_HTML) : '',
            'price'         => (float) $s->price,
            'duration_days' => (int) $s->duration_days,
            'b2b_enabled'   => (int) $s->b2b_enabled,
            'courses_count' => count($s->courses),
            'courses'       => array_map(static fn($c) => $c['fullname'], $s->courses),
            'seat_options'  => $s->seat_options,
            'offer_label'   => $offerlabel,
            'offer_final'   => $offerfinal,
        ];
    }
    return $out;
}
