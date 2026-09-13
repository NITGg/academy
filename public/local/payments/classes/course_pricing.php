<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * A course's prices as ONE editable set, plus its refund terms.
 *
 * Pricing lives on the course settings form (course/edit.php, the "Course
 * pricing" section added by {@see \local_payments\local\hooks\course_form})
 * rather than on a screen of its own. This class is the part that does not
 * depend on the form: what the section should show for a course, whether what
 * was typed is a legal set of prices, and how to write it back.
 *
 * The set is:
 *
 *   1. the home country's price (Egypt, unless the admin set another default
 *      country) — in whatever currency the admin picks;
 *   2. the Default price (country "*", is_default = 1) — the row every buyer the
 *      site cannot place in a country lands on, in an international currency;
 *   3. the course's refund window and fee, overriding the site policy.
 *
 * Nothing per country beyond those two: the table can hold a row for any
 * country (the old screen offered that), but the form no longer does — two
 * prices is the whole model for now.
 *
 * The two-price rule ({@see price_resolver::pricing_gaps()}) is enforced here as
 * plain validation: a course that sells must have BOTH 1 and 2, and 2 may not be
 * in the home currency. Because the whole set is saved at once there is no longer
 * a moment where a course is half-priced, so the old "may not break a complete
 * course" direction test is not needed — the form simply refuses an incomplete set.
 * Clearing every price makes the course free.
 *
 * "What the form shows is what the course has": on save, each of the two rows
 * becomes exactly one active record, and every other record the course had — a
 * per-country row from the old screen, a duplicate, an inactive row nobody
 * could see — is removed. A price the admin cannot see on the form is a price
 * they cannot be expected to know about.
 */
class course_pricing {

    /** @var string The country code of the Default price row. */
    const DEFAULT_ROW = '*';

    /**
     * The currencies a price may be set in.
     *
     * @return array<string, string> ISO 4217 => label
     */
    public static function currencies(): array {
        return [
            'USD' => 'USD - US Dollar',
            'EGP' => 'EGP - Egyptian Pound',
            'EUR' => 'EUR - Euro',
            'GBP' => 'GBP - British Pound',
            'SAR' => 'SAR - Saudi Riyal',
            'AED' => 'AED - UAE Dirham',
            'KWD' => 'KWD - Kuwaiti Dinar',
            'BHD' => 'BHD - Bahraini Dinar',
            'QAR' => 'QAR - Qatari Rial',
            'OMR' => 'OMR - Omani Rial',
        ];
    }

    /**
     * The currency the site's own country pays in — see country_detector::home_currency().
     *
     * Only a SUGGESTION now: it pre-selects the home row's currency picker on a
     * course with no prices yet, and it is the one currency the Default row may
     * not use. The admin may price the home country in anything.
     *
     * @return string ISO 4217
     */
    public static function home_currency(): string {
        $currency = country_detector::home_currency();

        return isset(self::currencies()[$currency]) ? $currency : 'EGP';
    }

    /**
     * Where a course's prices are edited: its settings form, pricing section open.
     *
     * The `pricing` flag asks the section to render expanded; the anchor is the
     * header's fieldset id (`id_` + element name).
     *
     * @param int $courseid
     * @return \moodle_url
     */
    public static function settings_url(int $courseid): \moodle_url {
        return new \moodle_url('/course/edit.php', ['id' => $courseid, 'pricing' => 1], 'id_lpp_pricing');
    }

    /**
     * What the section should show for a course.
     *
     * Only ACTIVE rows: inactive ones have no effect on any buyer, so they are not
     * a price the course has. Rows are keyed by what they are to the form, not by
     * id — the form has no ids.
     *
     * @param int $courseid 0 for a course being created
     * @return object {home: ?object{currency,price}, default: ?object{currency,price},
     *                 selling: bool, refund: object{hours: ?int, feepercent: ?float}}
     */
    public static function load(int $courseid): object {
        global $DB;

        $out = (object) [
            'home' => null,
            'default' => null,
            'selling' => false,
            'refund' => (object) ['hours' => null, 'feepercent' => null],
        ];
        if (!$courseid) {
            return $out;
        }

        $home = country_detector::fallback_country();
        $rows = $DB->get_records('local_payments_course_prices',
            ['courseid' => $courseid, 'is_active' => 1], 'is_default DESC, id ASC');

        foreach ($rows as $row) {
            $entry = (object) [
                'country' => (string) $row->country,
                'currency' => strtoupper((string) $row->currency),
                'price' => (float) $row->price,
            ];
            if ($entry->country === self::DEFAULT_ROW) {
                $out->default = $out->default ?? $entry;
            } else if ($entry->country === $home) {
                $out->home = $out->home ?? $entry;
            }
            // Any other country's row is not shown: the form has no place for it.
        }

        // A legacy Default row may carry the flag under a real country code. It
        // is still the row the resolver falls back to, so show it as the Default.
        if (!$out->default) {
            foreach ($rows as $row) {
                if ((int) $row->is_default === 1) {
                    $out->default = (object) [
                        'country' => self::DEFAULT_ROW,
                        'currency' => strtoupper((string) $row->currency),
                        'price' => (float) $row->price,
                    ];
                    break;
                }
            }
        }

        $out->selling = !empty($rows);

        $terms = $DB->get_record('local_payments_refund_terms', ['itemtype' => 'course', 'itemid' => $courseid]);
        if ($terms) {
            $out->refund->hours = $terms->hours === null ? null : (int) $terms->hours;
            $out->refund->feepercent = $terms->feepercent === null ? null : (float) $terms->feepercent;
        }

        return $out;
    }

    /**
     * Validate the pricing section of a submitted course form.
     *
     * Errors are keyed by the element that shows them — the GROUP names for the
     * price rows, because a moodleform reports errors on top-level elements only.
     *
     * @param array $data the whole course form's submitted data
     * @return array element name => message
     */
    public static function validate(array $data): array {
        $errors = [];
        $currencies = self::currencies();
        $home = country_detector::fallback_country();

        // ── The two required rows ────────────────────────────────────────────
        $homeprice = self::parse_price($data['lpp_homeprice'] ?? '');
        $defaultprice = self::parse_price($data['lpp_defaultprice'] ?? '');
        if ($homeprice === false) {
            $errors['lpp_homegrp'] = get_string('error_price_positive', 'local_payments');
        }
        if ($defaultprice === false) {
            $errors['lpp_defaultgrp'] = get_string('error_price_positive', 'local_payments');
        }

        $homecurrency = strtoupper((string) ($data['lpp_homecurrency'] ?? ''));
        $defaultcurrency = strtoupper((string) ($data['lpp_defaultcurrency'] ?? ''));
        if ($homeprice !== null && !isset($currencies[$homecurrency])) {
            $errors['lpp_homegrp'] = get_string('error_currency_unknown', 'local_payments');
        }
        if ($defaultprice !== null && !isset($currencies[$defaultcurrency])) {
            $errors['lpp_defaultgrp'] = get_string('error_currency_unknown', 'local_payments');
        }

        // ── The two-price rule ───────────────────────────────────────────────
        // A course that sells at all needs the home price AND the Default price:
        // with only one of them, half the audience is quoted the other half's
        // currency — or, with no Default row, nothing at all.
        $selling = $homeprice !== null || $defaultprice !== null;
        if ($selling) {
            $countries = get_string_manager()->get_list_of_countries();
            $homename = $countries[$home] ?? $home;
            if ($homeprice === null && !isset($errors['lpp_homegrp'])) {
                $errors['lpp_homegrp'] = get_string('error_home_required', 'local_payments', $homename);
            }
            if ($defaultprice === null && !isset($errors['lpp_defaultgrp'])) {
                $errors['lpp_defaultgrp'] = get_string('error_default_required', 'local_payments');
            }
        }

        // The Default row quotes everybody OUTSIDE the home country — and any
        // visitor the site cannot place — so in the home currency it is quoting
        // local money to the whole world. The picker does not offer it; this is
        // for a forged post.
        if ($defaultprice !== null && $defaultcurrency === self::home_currency()
                && !isset($errors['lpp_defaultgrp'])) {
            $errors['lpp_defaultgrp'] = get_string('error_same_currency', 'local_payments');
        }

        // ── Refund terms ─────────────────────────────────────────────────────
        $hours = trim((string) ($data['lpp_refund_hours'] ?? ''));
        if ($hours !== '' && (!is_numeric($hours) || (int) $hours < 0 || (float) $hours != (int) $hours)) {
            $errors['lpp_refund_hours'] = get_string('error_refund_hours', 'local_payments');
        }
        $fee = trim((string) ($data['lpp_refund_fee'] ?? ''));
        if ($fee !== '') {
            $feevalue = unformat_float($fee, true);
            if ($feevalue === false || $feevalue < 0 || $feevalue > 100) {
                $errors['lpp_refund_fee'] = get_string('error_refund_fee', 'local_payments');
            }
        }

        return $errors;
    }

    /**
     * Write the section back: price rows and refund terms, in one transaction.
     *
     * Assumes {@see self::validate()} passed. Runs from the course form's
     * after_form_submission hook, so $data is the whole course form's data.
     *
     * @param int $courseid
     * @param \stdClass $data
     * @param int $userid who is saving (stamped on new rows)
     * @return void
     */
    public static function save(int $courseid, \stdClass $data, int $userid): void {
        global $DB;

        $home = country_detector::fallback_country();
        $data = (array) $data;

        // The set the course should have after this save, keyed by country.
        $target = [];
        $homeprice = self::parse_price($data['lpp_homeprice'] ?? '');
        if ($homeprice) {
            $target[$home] = (object) [
                'currency' => strtoupper((string) $data['lpp_homecurrency']),
                'price' => $homeprice,
                'is_default' => 0,
            ];
        }
        $defaultprice = self::parse_price($data['lpp_defaultprice'] ?? '');
        if ($defaultprice) {
            $target[self::DEFAULT_ROW] = (object) [
                'currency' => strtoupper((string) $data['lpp_defaultcurrency']),
                'price' => $defaultprice,
                'is_default' => 1,
            ];
        }
        $now = time();
        $transaction = $DB->start_delegated_transaction();

        // Every row the course has, active or not, oldest first so that the row
        // kept for a country is the one that has been there longest.
        $existing = $DB->get_records('local_payments_course_prices', ['courseid' => $courseid], 'id ASC');

        foreach ($target as $country => $row) {
            $record = (object) [
                'country' => $country,
                'currency' => $row->currency,
                'price' => $row->price,
                'is_default' => $row->is_default,
                'is_active' => 1,
                'timemodified' => $now,
            ];

            $match = null;
            foreach ($existing as $id => $e) {
                if ((string) $e->country === (string) $country) {
                    $match = $e;
                    unset($existing[$id]);
                    break;
                }
            }

            if ($match) {
                $record->id = $match->id;
                $DB->update_record('local_payments_course_prices', $record);
            } else {
                $record->courseid = $courseid;
                $record->created_by = $userid;
                $record->timecreated = $now;
                $DB->insert_record('local_payments_course_prices', $record);
            }
        }

        // Whatever is left was not on the form: a price the admin cleared, a row
        // for some other country from the old screen, or a duplicate/inactive row
        // nobody could see.
        if (!empty($existing)) {
            $DB->delete_records_list('local_payments_course_prices', 'id', array_keys($existing));
        }

        // A Default row must carry the flag alone — the resolver reads the FIRST
        // is_default row it finds, and a legacy flag on a country row could win.
        $DB->execute('UPDATE {local_payments_course_prices} SET is_default = 0
                       WHERE courseid = :courseid AND country <> :star AND is_default = 1',
            ['courseid' => $courseid, 'star' => self::DEFAULT_ROW]);

        self::save_refund_terms($courseid, $data);

        $transaction->allow_commit();
    }

    /**
     * The course's refund override. Both blank = no override row at all.
     *
     * @param int $courseid
     * @param array $data
     * @return void
     */
    private static function save_refund_terms(int $courseid, array $data): void {
        global $DB;

        $rawhours = trim((string) ($data['lpp_refund_hours'] ?? ''));
        $rawfee = trim((string) ($data['lpp_refund_fee'] ?? ''));

        $terms = (object) [
            'itemtype' => 'course',
            'itemid' => $courseid,
            // Blank means "follow the site policy"; 0 is a deliberate choice.
            'hours' => $rawhours === '' ? null : max(0, (int) $rawhours),
            'feepercent' => $rawfee === '' ? null : max(0, min(100, (float) unformat_float($rawfee))),
            'timemodified' => time(),
        ];

        $existing = $DB->get_record('local_payments_refund_terms', ['itemtype' => 'course', 'itemid' => $courseid]);

        if ($terms->hours === null && $terms->feepercent === null) {
            if ($existing) {
                $DB->delete_records('local_payments_refund_terms', ['id' => $existing->id]);
            }
        } else if ($existing) {
            $terms->id = $existing->id;
            $DB->update_record('local_payments_refund_terms', $terms);
        } else {
            $DB->insert_record('local_payments_refund_terms', $terms);
        }
    }

    /**
     * A typed price: null when blank, false when not a positive number.
     *
     * @param mixed $raw
     * @return float|false|null
     */
    public static function parse_price($raw) {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        $value = unformat_float($raw, true);
        if ($value === false || $value === null || $value <= 0) {
            return false;
        }
        return round($value, 2);
    }

    /**
     * A stored price as the form should show it: no trailing zeros, local decimal separator.
     *
     * @param float|null $price
     * @return string
     */
    public static function display_price(?float $price): string {
        if ($price === null) {
            return '';
        }
        return format_float($price, 2, true, true);
    }
}
