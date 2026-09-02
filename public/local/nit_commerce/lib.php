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
 * Library functions for local_nit_commerce.
 *
 * @package    local_nit_commerce
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Build an associative map of localized strings for the given keys, for shipping to JS.
 *
 * @param array $keys string keys in local_nit_commerce
 * @return array key => localized string
 */
function local_nit_commerce_string_map(array $keys): array {
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = get_string($key, 'local_nit_commerce');
    }
    return $out;
}

/**
 * Read the coupon-report filters off the request, in one place, so the JSON reader and the CSV
 * export can never disagree about what the admin asked for.
 *
 * The date boxes send a plain YYYY-MM-DD; "to" is widened to the end of that day so a same-day
 * from/to returns that day rather than nothing.
 *
 * @return array filter array for {@see \local_nit_commerce\coupon_manager::get_redemptions()}
 */
function nit_commerce_redemption_filters(): array {
    $state = optional_param('state', 'confirmed', PARAM_ALPHA);
    if (!in_array($state, ['confirmed', 'pending', 'all'], true)) {
        $state = 'confirmed';
    }
    $itemtype = optional_param('item_type', '', PARAM_ALPHA);
    if (!in_array($itemtype, ['course', 'package', 'subscription', 'program'], true)) {
        $itemtype = '';
    }
    return [
        'couponid' => optional_param('couponid', 0, PARAM_INT),
        'userid'   => optional_param('userid', 0, PARAM_INT),
        'itemtype' => $itemtype,
        'state'    => $state,
        'q'        => trim(optional_param('q', '', PARAM_TEXT)),
        'from'     => nit_commerce_day_stamp(optional_param('from', '', PARAM_RAW_TRIMMED), false),
        'to'       => nit_commerce_day_stamp(optional_param('to', '', PARAM_RAW_TRIMMED), true),
    ];
}

/**
 * Read the offer-report filters off the request (AC-4.13.7), in one place, so the JSON reader and
 * the CSV export can never disagree about what the admin asked for.
 *
 * Same shape and same date handling as {@see nit_commerce_redemption_filters()} — the two reports
 * sit in the same kind of tab and are read by the same people, so they answer to the same boxes.
 * The only difference is which discount the id names.
 *
 * @return array filter array for {@see \local_nit_commerce\offer_manager::get_usages()}
 */
function nit_commerce_offer_filters(): array {
    $state = optional_param('state', 'confirmed', PARAM_ALPHA);
    if (!in_array($state, ['confirmed', 'pending', 'all'], true)) {
        $state = 'confirmed';
    }
    $itemtype = optional_param('item_type', '', PARAM_ALPHA);
    if (!in_array($itemtype, ['course', 'package', 'subscription', 'program'], true)) {
        $itemtype = '';
    }
    return [
        'offerid'  => optional_param('offerid', 0, PARAM_INT),
        'userid'   => optional_param('userid', 0, PARAM_INT),
        'itemtype' => $itemtype,
        'state'    => $state,
        'q'        => trim(optional_param('q', '', PARAM_TEXT)),
        'from'     => nit_commerce_day_stamp(optional_param('from', '', PARAM_RAW_TRIMMED), false),
        'to'       => nit_commerce_day_stamp(optional_param('to', '', PARAM_RAW_TRIMMED), true),
    ];
}

/**
 * Turn a YYYY-MM-DD box into a unix timestamp at the start (or end) of that day, in the user's
 * own timezone — an admin in Cairo filtering "today" means Cairo's today.
 *
 * @param string $value YYYY-MM-DD, or '' for no bound
 * @param bool $endofday true to return 23:59:59 rather than 00:00:00
 * @return int 0 when there is no bound
 */
function nit_commerce_day_stamp(string $value, bool $endofday): int {
    if ($value === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return 0;
    }
    $tz = core_date::get_user_timezone_object();
    $date = new DateTime('now', $tz);
    $date->setDate((int) $m[1], (int) $m[2], (int) $m[3]);
    $date->setTime($endofday ? 23 : 0, $endofday ? 59 : 0, $endofday ? 59 : 0);
    return (int) $date->getTimestamp();
}
