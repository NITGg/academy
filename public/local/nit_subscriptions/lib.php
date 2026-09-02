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
 * A signed-in caller with no profile country gets every plan back priceless and flagged
 * `country_required` — see {@see \local_payments\country_detector::pricing_blocked()}.
 *
 * @param string|null $app_country optional ISO 3166-1 alpha-2 country to price for (e.g. from the
 *        mobile app). When null, the caller's profile country (or IP for guests) is used.
 * @return array
 */
function nit_subscriptions_available(?string $app_country = null): array {
    $subs = \local_nit_subscriptions\subscription_manager::get_subscriptions(
        \local_nit_subscriptions\subscription_manager::STATUS_ACTIVE);
    $hasoffers = class_exists('\local_nit_commerce\discount_manager');

    // Signed in with no profile country: the plans still list (their name, duration and course
    // list are not secret) but every one of them comes back with no price, no offer and no seat
    // tiers, plus the flag and message the block and the app show in place of the amount.
    // Subscribing is refused server-side too — see local_payments\manager::create_subscription_checkout().
    $blocked = \local_nit_subscriptions\subscription_manager::pricing_blocked();
    $notice = [];
    if ($blocked) {
        $notice = class_exists('\local_payments\country_detector')
            ? \local_payments\country_detector::country_required_notice()
            : [
                'message' => get_string('countryrequired_desc', 'local_nit_subscriptions'),
                'short' => get_string('countryrequired', 'local_nit_subscriptions'),
                'action' => get_string('countryrequired_action', 'local_nit_subscriptions'),
                'url' => (new moodle_url('/user/edit.php'))->out(false),
            ];
    }

    $out = [];
    foreach ($subs as $s) {
        // Country-based price/currency for the caller (an explicit country wins; otherwise the
        // profile country, falling back to the plan's default price/currency).
        $price = 0.0;
        $currency = '';
        $country = '';
        if (!$blocked) {
            $resolved = \local_nit_subscriptions\subscription_manager::resolve_price((int) $s->id, null, $app_country);
            $price = (float) $resolved->price;
            $currency = (string) $resolved->currency;
            $country = (string) $resolved->country;
        }

        // The best auto-offer on this plan. Exposed two ways: flat offer_label/offer_final (the web
        // block reads these) and a nested `offer` object (the mobile app reads this) — present only
        // when there IS an active offer.
        $offerlabel = '';
        $offerfinal = 0.0;
        $offer = null;
        if ($hasoffers && !$blocked) {
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
            // Seat tiers priced off the resolved (country) base price. Nothing to price when
            // the caller is blocked, so the tiers are withheld along with the base price.
            'seat_options'  => $blocked
                ? [] : \local_nit_subscriptions\subscription_manager::get_seat_options((int) $s->id, $price),
            'offer_label'   => $offerlabel,
            'offer_final'   => $offerfinal,
            'country_required' => $blocked,
            'country_message'  => $blocked ? (string) $notice['message'] : '',
            'country_short'    => $blocked ? (string) $notice['short'] : '',
            'country_action'   => $blocked ? (string) $notice['action'] : '',
            'country_url'      => $blocked ? (string) $notice['url'] : '',
        ];
        if ($offer !== null) {
            $item['offer'] = $offer;
        }
        $out[] = $item;
    }
    return $out;
}

/**
 * The subscription that governs a user's access right now, shaped for a plan card.
 *
 * Answers the one question a pricing card cannot answer for itself: of everything this user has
 * bought, which plan is live, how long is left on it, and is it close enough to the end that
 * renewing should be offered. Shared by api.php (?function=get_my_active_subscription, read by
 * theme/nit/blocks/home_subscriptions.js) and the get_my_active_subscription external function so
 * the web card and the app card agree — the rules below are deliberately NOT client-side.
 *
 * When nothing is live every field is present and zeroed, so a caller can read the shape without
 * branching on has_active first.
 *
 * @param int $userid
 * @return array
 */
function nit_subscriptions_active_summary(int $userid): array {
    global $DB;

    $data = [
        'has_active' => false, 'subscriptionid' => 0, 'expires_at' => 0, 'expires_text' => '',
        'name' => '', 'days_left' => 0, 'price_paid' => 0.0,
        'renew_due' => false, 'renew_window_days' => 0, 'renewed_expires_at' => 0,
        'periods' => 0, 'renewed' => false, 'current_ends_at' => 0, 'current_days_left' => 0,
    ];

    $active = \local_nit_subscriptions\subscription_purchase_manager::get_active_subscription($userid);
    if (!$active) {
        return $data;
    }

    // Which of the user's live purchases on this plan governs their access is the one that runs
    // LONGEST, not the one activated most recently. Those are usually the same row, but a renewal
    // activated in the same second as another purchase ties on timeactivated — and
    // get_active_subscription() breaks that tie arbitrarily, which would have this screen quote the
    // shorter of the two and under-report the days the user has actually paid for.
    $active = \local_nit_subscriptions\subscription_purchase_manager::longest_active(
        $userid, (int) $active->subscriptionid) ?: $active;

    $name = $DB->get_field('nit_subscription', 'name', ['id' => $active->subscriptionid]);
    $daysleft = ((int) $active->expires_at > 0)
        ? max(0, (int) ceil(((int) $active->expires_at - time()) / DAYSECS)) : 0;

    // Renewing is offered on exactly the window that triggers the expiry reminder, so the button
    // and the notification can never disagree. The date it quotes is the one fulfilment will
    // actually set: the current deadline plus another full period, never "today plus a period".
    $reminders = \local_nit_subscriptions\reminder_manager::get_settings();
    $renewdue = \local_nit_subscriptions\reminder_manager::renew_due($active);
    $duration = (int) $active->duration_days;

    // "2 days left" on a renewed plan is a true number that reads like a wrong one: it is what
    // remains of the period running now PLUS the period stacked behind it. A user who just renewed
    // a 1-day plan sees 2 and cannot tell where it came from. So the caller is given the parts as
    // well as the total — how many periods are stacked, when the one running now ends, and the
    // final date — and can say so.
    $stacked = [];
    foreach (\local_nit_subscriptions\subscription_purchase_manager::get_active_subscriptions($userid) as $p) {
        if ((int) $p->subscriptionid === (int) $active->subscriptionid) {
            $stacked[] = (int) $p->expires_at;
        }
    }
    sort($stacked);
    $currentends = $stacked ? $stacked[0] : (int) $active->expires_at;

    return [
        'has_active'     => true,
        'subscriptionid' => (int) $active->subscriptionid,
        'expires_at'     => (int) $active->expires_at,
        'expires_text'   => ((int) $active->expires_at > 0)
            ? userdate((int) $active->expires_at, get_string('strftimedaydate')) : '',
        'name'           => $name !== false
            ? format_string(\local_nit_subscriptions\subscription_manager::resolve_mlang($name)) : '',
        'days_left'      => $daysleft,
        'price_paid'     => (float) $active->price_paid,
        'renew_due'      => $renewdue,
        'renew_window_days' => $reminders['days'] ? max($reminders['days']) : 0,
        'renewed_expires_at' => ((int) $active->expires_at > 0 && $duration > 0)
            ? (int) $active->expires_at + ($duration * DAYSECS) : 0,
        // More than one live purchase on this plan means a renewal is already queued behind the
        // period running now.
        'periods'         => count($stacked),
        'renewed'         => count($stacked) > 1,
        'current_ends_at' => $currentends,
        'current_days_left' => ($currentends > 0)
            ? max(0, (int) ceil(($currentends - time()) / DAYSECS)) : 0,
    ];
}
