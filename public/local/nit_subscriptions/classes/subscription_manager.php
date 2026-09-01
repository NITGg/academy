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
 * CRUD + rules for course-access subscription plans, and the course↔subscription access map.
 *
 * Ported from local_academy\subscription_manager. The subscription-purchase subsystem is not built
 * yet in the new platform, so the purchase-driven side effects (unenrolling holders when a course is
 * removed from a plan) are omitted; has_purchases() always returns false until that subsystem lands.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_subscriptions;

defined('MOODLE_INTERNAL') || die();

/**
 * Subscription plan and course-access manager.
 */
class subscription_manager {

    /** @var string Active plan status. */
    const STATUS_ACTIVE   = 'active';
    /** @var string Inactive plan status. */
    const STATUS_INACTIVE = 'inactive';

    /** @var int Sentinel subscriptionid in nit_course_access meaning "all subscriptions". */
    const ALL_SUBSCRIPTIONS = 0;

    // ──────────────────────────────────────────────────────────────────────────
    // Plan CRUD
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create a subscription plan.
     *
     * @param array $data name, description, price, duration_days, active, b2b_enabled, seat_options[]
     * @param int $userid admin performing the action
     * @return int new subscription id
     */
    public static function create_subscription(array $data, $userid) {
        global $DB;

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw new \moodle_exception('err_subnamerequired', 'local_nit_subscriptions');
        }
        // Every plan must carry a positive default price — it is the fallback used whenever a
        // buyer's country has no per-country override row, so a plan can never exist without a price.
        $price = (float)($data['price'] ?? 0);
        if ($price <= 0) {
            throw new \moodle_exception('err_pricepositive', 'local_nit_subscriptions');
        }
        $currency = self::normalize_currency($data['currency'] ?? 'EGP');
        $duration = (int)($data['duration_days'] ?? 0);
        if ($duration <= 0) {
            throw new \moodle_exception('err_durationpositive', 'local_nit_subscriptions');
        }
        $b2benabled = !empty($data['b2b_enabled']) ? 1 : 0;

        $now = time();
        $record = new \stdClass();
        $record->name          = $name;
        $record->description   = $data['description'] ?? '';
        $record->price         = $price;
        $record->currency      = $currency;
        $record->duration_days = $duration;
        // Null is meaningful here: it means the plan follows the site policy.
        $record->refund_hours = isset($data['refund_hours']) && $data['refund_hours'] !== null
            ? (int) $data['refund_hours'] : null;
        // A percentage of the amount paid, so it needs no currency of its own and
        // stays correct across every price row on the plan.
        $record->refund_fee = isset($data['refund_fee']) && $data['refund_fee'] !== null
            ? self::clamp_percent($data['refund_fee']) : null;
        $record->status        = !empty($data['active']) ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
        $record->b2b_enabled   = $b2benabled;
        $record->timecreated   = $now;
        $record->timemodified  = $now;
        $record->usermodified  = $userid;

        $id = $DB->insert_record('nit_subscription', $record);

        // Seat options only apply to a B2B-enabled plan.
        if ($b2benabled && array_key_exists('seat_options', $data)) {
            self::save_seat_options($id, (array)$data['seat_options']);
        }
        // Per-country price overrides (from the edit form).
        if (array_key_exists('prices', $data)) {
            self::replace_prices($id, (array) $data['prices'], $userid);
        }
        return $id;
    }

    /**
     * Update a subscription plan. Only provided fields change; changes apply to future purchases only.
     *
     * @param int $id
     * @param array $data subset of: name, description, price, duration_days, status, b2b_enabled, seat_options
     * @param int $userid admin performing the action
     * @return void
     */
    public static function update_subscription($id, array $data, $userid) {
        global $DB;

        $sub = self::get_subscription($id); // Throws if missing.

        $update = new \stdClass();
        $update->id = $sub->id;

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if ($name === '') {
                throw new \moodle_exception('err_subnameempty', 'local_nit_subscriptions');
            }
            $update->name = $name;
        }
        if (array_key_exists('description', $data)) {
            $update->description = $data['description'];
        }
        if (array_key_exists('price', $data)) {
            $price = (float)$data['price'];
            if ($price <= 0) {
                throw new \moodle_exception('err_pricepositive', 'local_nit_subscriptions');
            }
            $update->price = $price;
        }
        if (array_key_exists('currency', $data)) {
            $update->currency = self::normalize_currency($data['currency']);
        }
        foreach (['refund_hours', 'refund_fee'] as $refundfield) {
            if (array_key_exists($refundfield, $data)) {
                $update->$refundfield = $data[$refundfield] === null
                    ? null
                    : ($refundfield === 'refund_hours'
                        ? max(0, (int) $data[$refundfield])
                        : self::clamp_percent($data[$refundfield]));
            }
        }

        if (array_key_exists('duration_days', $data)) {
            $duration = (int)$data['duration_days'];
            if ($duration <= 0) {
                throw new \moodle_exception('err_durationpositive', 'local_nit_subscriptions');
            }
            $update->duration_days = $duration;
        }
        if (array_key_exists('status', $data)) {
            $update->status = self::normalize_status($data['status']);
        }
        $b2benabled = null;
        if (array_key_exists('b2b_enabled', $data)) {
            $b2benabled = !empty($data['b2b_enabled']) ? 1 : 0;
            $update->b2b_enabled = $b2benabled;
        }

        $update->timemodified = time();
        $update->usermodified = $userid;

        $DB->update_record('nit_subscription', $update);

        // Seat options: replace-on-save when provided. Disabling B2B clears them.
        $effectiveb2b = ($b2benabled === null) ? (int)$sub->b2b_enabled : $b2benabled;
        if ($effectiveb2b && array_key_exists('seat_options', $data)) {
            self::save_seat_options($sub->id, (array)$data['seat_options']);
        } else if ($b2benabled === 0) {
            $DB->delete_records('nit_sub_seat_option', array('subscriptionid' => $sub->id));
        }

        // Per-country price overrides (from the edit form).
        if (array_key_exists('prices', $data)) {
            self::replace_prices($sub->id, (array) $data['prices'], $userid);
        }
    }

    /**
     * Deactivate a plan. Existing student subscriptions are unaffected.
     *
     * @param int $id
     * @param int $userid
     * @return void
     */
    public static function deactivate_subscription($id, $userid) {
        self::set_status($id, self::STATUS_INACTIVE, $userid);
    }

    /**
     * Reactivate a plan.
     *
     * @param int $id
     * @param int $userid
     * @return void
     */
    public static function activate_subscription($id, $userid) {
        self::set_status($id, self::STATUS_ACTIVE, $userid);
    }

    /**
     * Delete a plan. Allowed only if it was never purchased.
     *
     * @param int $id
     * @return void
     */
    public static function delete_subscription($id) {
        global $DB;

        self::get_subscription($id); // Throws if missing.

        if (self::has_purchases($id)) {
            throw new \moodle_exception('err_subhaspurchases', 'local_nit_subscriptions');
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('nit_course_access', array('subscriptionid' => $id));
        $DB->delete_records('nit_sub_seat_option', array('subscriptionid' => $id));
        $DB->delete_records('nit_subscription', array('id' => $id));
        $transaction->allow_commit();
    }

    /**
     * Whether any student ever purchased this plan. Always false until the purchase subsystem lands.
     *
     * @param int $subscriptionid
     * @return bool
     */
    public static function has_purchases($subscriptionid) {
        global $DB;
        // A plan is "in use" if any purchase row references it (any status) —
        // deleting it would dangle those FKs and strip paying users' access.
        return $DB->record_exists('nit_sub_purchase', ['subscriptionid' => (int) $subscriptionid]);
    }

    /**
     * Fetch a single plan or throw.
     *
     * @param int $id
     * @return \stdClass
     */
    public static function get_subscription($id) {
        global $DB;
        $sub = $DB->get_record('nit_subscription', array('id' => $id));
        if (!$sub) {
            throw new \moodle_exception('err_subnotfound', 'local_nit_subscriptions');
        }
        return $sub;
    }

    /**
     * List plans, optionally filtered by status, each with its granted courses + seat options.
     *
     * @param string $status '' for all, or 'active' | 'inactive'
     * @return array
     */
    public static function get_subscriptions($status = '') {
        global $DB;
        $conditions = array();
        if ($status !== '') {
            $conditions['status'] = self::normalize_status($status);
        }
        $rows = array_values($DB->get_records('nit_subscription', $conditions, 'timecreated DESC'));
        foreach ($rows as $r) {
            $r->courses = self::courses_detail($r->id);
            $r->b2b_enabled = (int)$r->b2b_enabled;
            $r->seat_options = self::get_seat_options($r->id, (float)$r->price);
            // Per-country price overrides, for the in-form editor.
            $r->prices = array_map(function ($p) {
                return [
                    'id'        => (int) $p->id,
                    'country'   => (string) $p->country,
                    'currency'  => (string) $p->currency,
                    'price'     => (float) $p->price,
                    'is_active' => (int) $p->is_active,
                ];
            }, self::get_prices($r->id));
        }
        return $rows;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // B2B seat options + price
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The B2B price for a seat option.
     *
     * @param float $base normal price
     * @param int $seats capacity
     * @param float $pct discount percentage (0..100)
     * @return array ['original'=>, 'discount'=>, 'final'=>]
     */
    public static function b2b_price($base, $seats, $pct) {
        $original = round((float)$base * (int)$seats, 2);
        $discount = round($original * (float)$pct / 100, 2);
        return array(
            'original' => $original,
            'discount' => $discount,
            'final'    => round($original - $discount, 2),
        );
    }

    /**
     * Seat options for a plan, each with its computed B2B price.
     *
     * @param int $subscriptionid
     * @param float|null $baseprice plan price (fetched if null)
     * @return array
     */
    public static function get_seat_options($subscriptionid, $baseprice = null) {
        global $DB;
        if ($baseprice === null) {
            $baseprice = (float)$DB->get_field('nit_subscription', 'price', array('id' => $subscriptionid));
        }
        $rows = array_values($DB->get_records('nit_sub_seat_option',
            array('subscriptionid' => $subscriptionid), 'seats ASC'));
        $out = array();
        foreach ($rows as $r) {
            $price = self::b2b_price($baseprice, $r->seats, $r->discount_percent);
            $out[] = array(
                'id'               => (int)$r->id,
                'seats'            => (int)$r->seats,
                'discount_percent' => (float)$r->discount_percent,
                'original_price'   => $price['original'],
                'discount_amount'  => $price['discount'],
                'b2b_price'        => $price['final'],
            );
        }
        return $out;
    }

    /**
     * Replace a plan's seat options. Validates seats>0 and 0<=discount<=100, de-duplicates by seats.
     *
     * @param int $subscriptionid
     * @param array $options list of ['seats'=>, 'discount_percent'=>]
     * @return void
     */
    public static function save_seat_options($subscriptionid, array $options) {
        global $DB;
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('nit_sub_seat_option', array('subscriptionid' => $subscriptionid));

        $seen = array();
        foreach ($options as $opt) {
            $seats = (int)($opt['seats'] ?? 0);
            $pct = (float)($opt['discount_percent'] ?? 0);
            if ($seats <= 0) {
                throw new \moodle_exception('err_seatspositive', 'local_nit_subscriptions');
            }
            if ($pct < 0 || $pct > 100) {
                throw new \moodle_exception('err_discountrange', 'local_nit_subscriptions');
            }
            if (isset($seen[$seats])) {
                continue; // Ignore duplicate seat counts.
            }
            $seen[$seats] = true;
            $DB->insert_record('nit_sub_seat_option', (object) array(
                'subscriptionid'   => $subscriptionid,
                'seats'            => $seats,
                'discount_percent' => $pct,
                'timecreated'      => $now,
                'timemodified'     => $now,
            ));
        }
        $transaction->allow_commit();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Per-country pricing (mirror of local_payments course pricing)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Resolve the price a given user pays for a plan, based on their country.
     *
     * A country-specific active override row (nit_sub_price) wins; otherwise the plan's base
     * price/currency (nit_subscription) is the default. Mirrors local_payments price_resolver::resolve(),
     * including the rule that a signed-in account with no profile country has no price at all:
     * signed in → profile country, guest → IP geolocation → app hint → plan default price.
     *
     * @param int $subscriptionid
     * @param int|null $userid defaults to current $USER
     * @param string|null $app_country optional country hint from the mobile app
     * @return \stdClass {price, currency, price_id, country}
     * @throws \local_payments\country_required_exception if signed in with no profile country
     */
    public static function resolve_price($subscriptionid, $userid = null, $app_country = null) {
        global $DB, $USER;

        $userid = $userid ?? (int) $USER->id;
        $sub = self::get_subscription($subscriptionid); // Throws if missing.

        // A signed-in account with no profile country is quoted nothing — not the plan's base
        // price, not an IP guess. Same rule and same exception as a course price, so every
        // caller handles one case, not two.
        if (self::pricing_blocked($userid)) {
            $debug = "Subscription {$subscriptionid}: user {$userid} has no profile country";
            if (class_exists('\local_payments\country_required_exception')) {
                throw new \local_payments\country_required_exception($debug);
            }
            throw new \moodle_exception('countryrequired_desc', 'local_nit_subscriptions', '', null, $debug);
        }

        // May be '' when the country genuinely cannot be determined (a guest with no usable IP
        // and no app hint). That must land on the plan's base price, never on the admin default
        // country's override row.
        $country = self::detect_country_for_pricing($userid, $app_country);
        // Consumers such as the payment-provider routing in local_payments\manager need a real
        // code, so an unknown country is reported as the admin default.
        $reported = $country !== '' ? $country : self::fallback_country();

        $row = null;
        if ($country !== '') {
            $row = $DB->get_record_select(
                'nit_sub_price',
                'subscriptionid = :sid AND country = :country AND is_active = 1',
                ['sid' => (int) $subscriptionid, 'country' => $country],
                '*',
                IGNORE_MULTIPLE
            );
        }

        if ($row) {
            return (object) [
                'price'    => (float) $row->price,
                'currency' => (string) $row->currency,
                'price_id' => (int) $row->id,
                'country'  => $reported,
            ];
        }

        // Default: the plan's base price/currency.
        return (object) [
            'price'    => (float) $sub->price,
            'currency' => (string) ($sub->currency ?? 'EGP'),
            'price_id' => 0,
            'country'  => $reported,
        ];
    }

    /**
     * Is this user barred from seeing any price because their profile carries no country?
     *
     * Delegates to local_payments so courses and subscriptions can never disagree about who is
     * blocked. Standalone (that plugin absent) the same test runs against the user record.
     *
     * @param int|null $userid defaults to current $USER
     * @return bool
     */
    public static function pricing_blocked($userid = null) {
        global $DB, $USER, $CFG;

        $userid = (int) ($userid ?? $USER->id);
        if (class_exists('\local_payments\country_detector')) {
            return \local_payments\country_detector::pricing_blocked($userid);
        }
        // Guests have no profile to fill in, so they are never blocked.
        if ($userid <= 0 || (!empty($CFG->siteguest) && $userid === (int) $CFG->siteguest)) {
            return false;
        }

        return !preg_match('/^[A-Za-z]{2}$/', (string) $DB->get_field('user', 'country', ['id' => $userid]));
    }

    /**
     * The country to key a plan's price on, or '' when it cannot be determined.
     *
     * Delegates to local_payments country_detector (profile → IP → app hint) when available.
     * Standalone, without that plugin, there is no geolocation, so it is profile → app hint.
     *
     * @param int $userid
     * @param string|null $app_country
     * @return string ISO 3166-1 alpha-2 (uppercase), or '' when unknown
     */
    public static function detect_country_for_pricing($userid, $app_country = null) {
        global $DB;
        if (class_exists('\local_payments\country_detector')) {
            return \local_payments\country_detector::detect_for_pricing($userid, $app_country);
        }
        $country = (string) $DB->get_field('user', 'country', ['id' => $userid]);
        if (preg_match('/^[A-Za-z]{2}$/', $country)) {
            return strtoupper($country);
        }
        if (!empty($app_country) && preg_match('/^[A-Za-z]{2}$/', $app_country)) {
            return strtoupper($app_country);
        }
        return '';
    }

    /**
     * Detect the buyer's country, always returning a usable code. Use where a country is
     * structurally required (provider routing, reporting) — not to choose which price to show,
     * which is {@see self::detect_country_for_pricing()}.
     *
     * @param int $userid
     * @param string|null $app_country
     * @return string ISO 3166-1 alpha-2 (uppercase), never empty
     */
    public static function detect_country($userid, $app_country = null) {
        $country = self::detect_country_for_pricing($userid, $app_country);
        return $country !== '' ? $country : self::fallback_country();
    }

    /**
     * The site's fallback country code, used only where a code is mandatory.
     *
     * @return string ISO 3166-1 alpha-2 (uppercase), never empty
     */
    public static function fallback_country() {
        if (class_exists('\local_payments\country_detector')) {
            return \local_payments\country_detector::fallback_country();
        }
        return 'EG';
    }

    /**
     * All per-country override rows for a plan, country ASC.
     *
     * @param int $subscriptionid
     * @return array
     */
    public static function get_prices($subscriptionid) {
        global $DB;
        return array_values($DB->get_records('nit_sub_price',
            ['subscriptionid' => (int) $subscriptionid], 'country ASC'));
    }

    /**
     * Create or update a per-country price override row. One active row per (plan, country).
     *
     * @param array $data subscriptionid, country, currency, price, is_active, priceid (0 = new)
     * @param int $userid admin performing the action
     * @return int the row id
     */
    public static function save_price(array $data, $userid) {
        global $DB;

        $subscriptionid = (int) ($data['subscriptionid'] ?? 0);
        self::get_subscription($subscriptionid); // Validate plan exists.

        $country = strtoupper(trim((string) ($data['country'] ?? '')));
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            throw new \moodle_exception('err_pricecountry', 'local_nit_subscriptions');
        }
        $currency = self::normalize_currency($data['currency'] ?? 'EGP');
        $price = (float) ($data['price'] ?? 0);
        if ($price <= 0) {
            throw new \moodle_exception('err_pricepositive', 'local_nit_subscriptions');
        }
        $isactive = !empty($data['is_active']) ? 1 : 0;
        $priceid = (int) ($data['priceid'] ?? 0);

        // Enforce one row per (plan, country) — the unique index guards this too.
        $existing = $DB->get_record('nit_sub_price',
            ['subscriptionid' => $subscriptionid, 'country' => $country]);
        if ($existing && $existing->id != $priceid) {
            throw new \moodle_exception('err_priceonepercountry', 'local_nit_subscriptions');
        }

        $now = time();
        $record = (object) [
            'subscriptionid' => $subscriptionid,
            'country'        => $country,
            'currency'       => $currency,
            'price'          => $price,
            'is_active'      => $isactive,
            'timemodified'   => $now,
        ];

        if ($priceid) {
            $record->id = $priceid;
            $DB->update_record('nit_sub_price', $record);
            return $priceid;
        }
        $record->created_by  = (int) $userid;
        $record->timecreated = $now;
        return (int) $DB->insert_record('nit_sub_price', $record);
    }

    /**
     * Delete a per-country price override row.
     *
     * @param int $priceid
     * @param int $subscriptionid guard so a row can only be deleted from its own plan
     * @return void
     */
    public static function delete_price($priceid, $subscriptionid) {
        global $DB;
        $DB->delete_records('nit_sub_price',
            ['id' => (int) $priceid, 'subscriptionid' => (int) $subscriptionid]);
    }

    /**
     * Replace all per-country price overrides for a plan with the provided set (edit-form save).
     * Validates each row and rejects duplicate countries. Blank rows are ignored.
     *
     * @param int $subscriptionid
     * @param array $rows list of ['country','currency','price','is_active']
     * @param int $userid
     * @return void
     */
    public static function replace_prices($subscriptionid, array $rows, $userid) {
        global $DB;

        $subscriptionid = (int) $subscriptionid;
        self::get_subscription($subscriptionid); // Validate plan exists.

        // Validate + normalise every row before touching the DB, so a bad row aborts the whole save.
        $now = time();
        $clean = [];
        $seen = [];
        foreach ($rows as $row) {
            $country = strtoupper(trim((string) ($row['country'] ?? '')));
            if ($country === '') {
                continue; // Blank row — skip.
            }
            if (!preg_match('/^[A-Z]{2}$/', $country)) {
                throw new \moodle_exception('err_pricecountry', 'local_nit_subscriptions');
            }
            if (isset($seen[$country])) {
                throw new \moodle_exception('err_priceonepercountry', 'local_nit_subscriptions');
            }
            $seen[$country] = true;
            $price = (float) ($row['price'] ?? 0);
            if ($price <= 0) {
                throw new \moodle_exception('err_pricepositive', 'local_nit_subscriptions');
            }
            $clean[] = (object) [
                'subscriptionid' => $subscriptionid,
                'country'        => $country,
                'currency'       => self::normalize_currency($row['currency'] ?? 'EGP'),
                'price'          => $price,
                'is_active'      => !empty($row['is_active']) ? 1 : 0,
                'created_by'     => (int) $userid,
                'timecreated'    => $now,
                'timemodified'   => $now,
            ];
        }

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('nit_sub_price', ['subscriptionid' => $subscriptionid]);
        foreach ($clean as $record) {
            $DB->insert_record('nit_sub_price', $record);
        }
        $transaction->allow_commit();
    }

    /**
     * A refund fee as a sane percentage.
     *
     * @param mixed $value
     * @return float Between 0 and 100.
     */
    private static function clamp_percent($value): float {
        return max(0.0, min(100.0, (float) $value));
    }

    /**
     * Validate/normalize an ISO 4217 currency code.
     *
     * @param string $currency
     * @return string uppercase 3-letter code
     */
    public static function normalize_currency($currency) {
        $currency = strtoupper(trim((string) $currency));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new \moodle_exception('err_currency', 'local_nit_subscriptions');
        }
        return $currency;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Course ↔ subscription access map
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Set the courses a specific subscription unlocks. Replaces any existing rules for the plan.
     *
     * @param int $subscriptionid
     * @param int[] $courseids
     * @param int $userid
     * @return array the new courses for the subscription
     */
    public static function set_subscription_courses($subscriptionid, array $courseids, $userid) {
        global $DB;
        self::get_subscription($subscriptionid); // Validate.

        $now = time();
        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records('nit_course_access', array('subscriptionid' => $subscriptionid));

        $seen = array();
        foreach ($courseids as $cid) {
            $cid = (int)$cid;
            if ($cid <= 0 || isset($seen[$cid])) {
                continue;
            }
            if (!$DB->record_exists('course', array('id' => $cid))) {
                continue;
            }
            $seen[$cid] = true;
            $DB->insert_record('nit_course_access', (object) array(
                'courseid'       => $cid,
                'subscriptionid' => $subscriptionid,
                'timecreated'    => $now,
                'usermodified'   => $userid,
            ));
        }

        $transaction->allow_commit();

        return self::courses_detail($subscriptionid);
    }

    /**
     * Get categories and their courses for the admin UI.
     *
     * @return array
     */
    public static function get_categories_with_courses() {
        global $DB;
        $cats = $DB->get_records('course_categories', array(), 'sortorder ASC', 'id, name');
        $courses = $DB->get_records('course', array(), 'sortorder ASC', 'id, fullname, category');
        $tree = array();
        foreach ($cats as $cat) {
            $tree[$cat->id] = array(
                'id' => (int)$cat->id,
                'name' => format_string($cat->name),
                'courses' => array(),
            );
        }
        foreach ($courses as $c) {
            if ($c->category > 0 && isset($tree[$c->category])) {
                $tree[$c->category]['courses'][] = array(
                    'id' => (int)$c->id,
                    'fullname' => format_string($c->fullname),
                );
            }
        }
        // Filter out empty categories.
        $result = array();
        foreach ($tree as $node) {
            if (!empty($node['courses'])) {
                $result[] = $node;
            }
        }
        return $result;
    }

    /**
     * The course ids a given subscription unlocks: courses granted to it specifically, plus any course
     * open to "all subscriptions".
     *
     * @param int $subscriptionid
     * @return int[] course ids
     */
    public static function courses_for_subscription($subscriptionid) {
        global $DB;
        $rows = $DB->get_records_select('nit_course_access',
            'subscriptionid = :sid OR subscriptionid = :all',
            array('sid' => $subscriptionid, 'all' => self::ALL_SUBSCRIPTIONS),
            '', 'DISTINCT courseid');
        return array_map('intval', array_keys($rows));
    }

    /**
     * The courses a subscription unlocks, as [{id, fullname}] for display.
     *
     * @param int $subscriptionid
     * @return array
     */
    public static function courses_detail($subscriptionid) {
        global $DB;
        $ids = self::courses_for_subscription($subscriptionid);
        if (empty($ids)) {
            return array();
        }
        list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select('course', "id $insql", $params, 'fullname ASC', 'id, fullname');
        $out = array();
        foreach ($rows as $c) {
            $out[] = array('id' => (int)$c->id, 'fullname' => format_string($c->fullname));
        }
        return $out;
    }

    // ── Helpers ──

    /**
     * Resolve a stored {mlang en}…{mlang}{mlang ar}…{mlang} value to the current language.
     *
     * The platform stores bilingual names in one field, but the {mlang} (filter_multilang2) filter is
     * not installed here, so we resolve it ourselves for server-rendered / API output. Values without
     * {mlang} markup are returned unchanged.
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
     * Set a plan status (shared by activate/deactivate).
     *
     * @param int $id
     * @param string $status
     * @param int $userid
     * @return void
     */
    private static function set_status($id, $status, $userid) {
        global $DB;
        self::get_subscription($id); // Throws if missing.
        $update = new \stdClass();
        $update->id           = $id;
        $update->status       = $status;
        $update->timemodified = time();
        $update->usermodified = $userid;
        $DB->update_record('nit_subscription', $update);
    }

    /**
     * Validate/normalize a status value.
     *
     * @param string $status
     * @return string
     */
    private static function normalize_status($status) {
        $status = strtolower(trim((string)$status));
        if (!in_array($status, array(self::STATUS_ACTIVE, self::STATUS_INACTIVE), true)) {
            throw new \moodle_exception('err_status', 'local_nit_subscriptions');
        }
        return $status;
    }
}
