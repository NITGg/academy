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

namespace block_nit_offers;

use moodle_url;

/**
 * Turns the offers that local_nit_commerce is currently running into bar rows.
 *
 * The block never stores a copy of an offer: it asks the commerce plugin what is
 * live at render time, so an offer that starts, ends or is deactivated changes the
 * bar on the next page load with nobody editing HTML. If local_nit_commerce is not
 * installed the reader simply returns nothing and the block hides itself.
 *
 * @package    block_nit_offers
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bar {

    /** @var int Below this many days left, the deadline is written as a countdown instead of a date. */
    const COUNTDOWN_DAYS = 14;

    /**
     * Whether the commerce plugin that owns the offers is available.
     *
     * @return bool
     */
    public static function available(): bool {
        return class_exists('\local_nit_commerce\offer_manager');
    }

    /**
     * The live offers, newest first, shaped for the bar template.
     *
     * @param int $max how many to keep (0 or less = all)
     * @param moodle_url|null $fallbackurl where a row links when the offer is not one single course
     * @param string $linktext label for that link
     * @return array[] one row per offer
     */
    public static function rows(int $max, ?moodle_url $fallbackurl, string $linktext): array {
        if (!self::available()) {
            return [];
        }
        try {
            $offers = \local_nit_commerce\offer_manager::get_available_offers();
        } catch (\Throwable $e) {
            // Commerce tables missing or mid-upgrade: an announcement bar is never worth
            // breaking a page over, so treat it as "nothing is running".
            return [];
        }
        if ($max > 0) {
            $offers = array_slice($offers, 0, $max);
        }

        $rows = [];
        $index = 0;
        foreach ($offers as $offer) {
            $index++;
            $url = self::offer_url($offer) ?? $fallbackurl;
            $rows[] = [
                'index'    => $index,
                'first'    => $index === 1,
                'headline' => (string) $offer['name'],
                'badge'    => self::badge($offer),
                'meta'     => self::deadline($offer),
                'scope'    => self::scope($offer),
                'url'      => $url ? $url->out(false) : '',
                'linktext' => $linktext,
            ];
        }
        return $rows;
    }

    /**
     * A stable id for "the set of offers currently running".
     *
     * The dismiss button remembers this, not the block instance, so closing the bar
     * hides today's offers but a NEW offer brings the bar back instead of staying
     * invisible forever.
     *
     * @param array[] $rows rows from {@see self::rows()}
     * @return string
     */
    public static function fingerprint(array $rows): string {
        $parts = array_map(static fn(array $r) => $r['headline'] . '|' . $r['badge'] . '|' . $r['meta'], $rows);
        return substr(md5(implode('||', $parts)), 0, 12);
    }

    /**
     * The "-25%" pill. Percent offers print as a percentage; a fixed-amount offer has no
     * currency of its own (the amount is taken off whatever the buyer is quoted), so it
     * prints the bare number rather than inventing a currency it cannot know.
     *
     * @param array $offer
     * @return string '' when the offer has no meaningful value
     */
    private static function badge(array $offer): string {
        $value = (float) ($offer['discount_value'] ?? 0);
        if ($value <= 0) {
            return '';
        }
        $number = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        if (($offer['discount_type'] ?? 'percent') === 'percent') {
            return '-' . $number . '%';
        }
        // "50 off" rather than "-50": the amount is taken off whatever the buyer is quoted,
        // and the buyer's currency depends on their country, so the bar must not print one.
        return get_string('amountoff', 'block_nit_offers', $number);
    }

    /**
     * The deadline line: "Ends 30 September" far out, a countdown in the last fortnight,
     * and "Starts …" for an offer that is live but has not opened yet.
     *
     * @param array $offer
     * @return string '' when the offer is open-ended
     */
    private static function deadline(array $offer): string {
        $now = time();
        $start = (int) ($offer['startdate'] ?? 0);
        $end   = (int) ($offer['enddate'] ?? 0);

        if ($start > $now) {
            return get_string('starts', 'block_nit_offers', userdate($start, get_string('strftimedateshort')));
        }
        if ($end <= 0) {
            return '';
        }

        // Days left is counted between calendar days, not as a raw 24h division, so an
        // offer ending late tonight reads "Ends today" rather than "Ends in 0 days".
        $today = usergetmidnight($now);
        $last  = usergetmidnight($end);
        $days  = (int) round(($last - $today) / DAYSECS);

        if ($days <= 0) {
            return get_string('endstoday', 'block_nit_offers');
        }
        if ($days === 1) {
            return get_string('endstomorrow', 'block_nit_offers');
        }
        if ($days <= self::COUNTDOWN_DAYS) {
            return get_string('endsindays', 'block_nit_offers', $days);
        }
        return get_string('ends', 'block_nit_offers', userdate($end, get_string('strftimedateshort')));
    }

    /**
     * What the offer covers, e.g. "Applies to: All courses" — the same labels the admin
     * chose when scoping it. Kept to the first few so a broad offer cannot flood the bar.
     *
     * @param array $offer
     * @return string
     */
    private static function scope(array $offer): string {
        $labels = [];
        foreach (($offer['applies_to'] ?? []) as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            if ($label !== '') {
                $labels[] = $label;
            }
        }
        if (empty($labels)) {
            return '';
        }
        $labels = array_slice(array_unique($labels), 0, 3);
        return get_string('appliesto', 'block_nit_offers', implode(' · ', $labels));
    }

    /**
     * An offer that targets exactly one course sends the visitor to that course; anything
     * broader has no single destination, so the caller's fallback (the catalogue) is used.
     *
     * @param array $offer
     * @return moodle_url|null
     */
    private static function offer_url(array $offer): ?moodle_url {
        $items = $offer['applies_to'] ?? [];
        if (count($items) !== 1) {
            return null;
        }
        $item = reset($items);
        if (($item['item_type'] ?? '') !== 'course' || (int) ($item['item_id'] ?? 0) <= 0) {
            return null;
        }
        return new moodle_url('/course/view.php', ['id' => (int) $item['item_id']]);
    }
}
