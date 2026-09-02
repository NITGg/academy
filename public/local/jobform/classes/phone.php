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

namespace local_jobform;

defined('MOODLE_INTERNAL') || die();

/**
 * The country dialling code half of a "phone" answer.
 *
 * AC-4.20.4: a phone field is two controls, not one - a country dialling code and
 * the national number - and the country control is generic, offering every country
 * rather than the handful an example happened to name. The list itself, the codes
 * and the per-country length rules already exist in the `profilefield_phone` field
 * (that is the sign-up phone box), so this class is a thin adapter: it borrows
 * `profilefield_phone\dialcodes` and adds the part that is specific to a job form,
 * which is how the two halves survive as one string in `jobform_submission_data.value`.
 *
 * Stored format
 * -------------
 * `ISO:nationalnumber`, e.g. `EG:1012345678` - the same shape the profile field
 * uses. The ISO code rather than the "+20" because a dialling code is not unique:
 * two dozen countries dial +1, and four French departments split +262/+590/+594/
 * +596. Storing "+1 4165551234" would lose whether the applicant said Canada or
 * the United States, and a submission is a record of what somebody actually chose.
 *
 * Answers written before this existed are plain numbers, so {@see self::split()}
 * reads those too and everything that displays a value goes through
 * {@see field_types::format_value()}, which renders both shapes as "+20 1012345678".
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class phone {

    /** @var string Separates the ISO code from the national number in a stored value. */
    const SEP = ':';

    /**
     * Is the dialling-code source installed?
     *
     * `profilefield_phone` is a sibling plugin, not a declared dependency: a site
     * can run the job form without the academy's sign-up field. When it is absent
     * the phone field degrades to the plain text box it used to be rather than
     * fataling, so this is checked before every use.
     *
     * @return bool
     */
    public static function available(): bool {
        return class_exists('\profilefield_phone\dialcodes');
    }

    /**
     * The dialling code for a country, without the leading "+".
     *
     * @param string $iso ISO 3166-1 alpha-2 country code
     * @return string '' when the country is unknown
     */
    public static function code(string $iso): string {
        if (!self::available()) {
            return '';
        }
        return \profilefield_phone\dialcodes::code($iso);
    }

    /**
     * Every country, for the dialling code select: ISO => "Egypt +20 🇪🇬".
     *
     * Sorted by the localised country name, so the order and the labels follow the
     * language the applicant is reading the form in.
     *
     * @return array<string,string>
     */
    public static function menu(): array {
        if (!self::available()) {
            return [];
        }
        return \profilefield_phone\dialcodes::menu();
    }

    /**
     * The same list as {@see self::menu()}, broken into parts for the mobile app.
     *
     * The app draws its own control, so it needs the pieces rather than a label:
     * the ISO code to send back, the "+NN" to show beside the number box, and the
     * localised country name to list and search on.
     *
     * @return array[] [['iso' => .., 'code' => .., 'name' => .., 'label' => ..], ..]
     */
    public static function country_list(): array {
        if (!self::available()) {
            return [];
        }
        $names = get_string_manager()->get_list_of_countries(true);
        $list = [];
        foreach (self::menu() as $iso => $label) {
            $list[] = [
                'iso'   => $iso,
                'code'  => \profilefield_phone\dialcodes::code($iso),
                'name'  => (string) ($names[$iso] ?? $iso),
                'label' => $label,
            ];
        }
        return $list;
    }

    /**
     * The country to preselect when the applicant has not answered yet.
     *
     * Their own location when the site can resolve it, otherwise the site default.
     *
     * @return string ISO alpha-2, or '' when there is no dialling code source
     */
    public static function default_country(): string {
        if (!self::available()) {
            return '';
        }
        return \profilefield_phone\dialcodes::default_country();
    }

    /**
     * Reduce a number as typed to its national digits.
     *
     * Drops the spaces, dashes and brackets people write, a dialling code they may
     * have typed into the number box themselves, and the trunk "0" that is only
     * dialled domestically.
     *
     * @param string $number the number as typed
     * @param string $iso the selected country
     * @return string digits only
     */
    public static function normalise_number(string $number, string $iso): string {
        $digits = preg_replace('/\D/', '', $number);
        if ($digits === '') {
            return '';
        }

        $dial = self::code($iso);
        if ($dial !== '' && strpos($digits, $dial) === 0 && strlen($digits) > strlen($dial)) {
            $digits = substr($digits, strlen($dial));
        }

        return ltrim($digits, '0');
    }

    /**
     * Break a stored answer into its country and number halves.
     *
     * @param string|null $value a stored `jobform_submission_data.value`
     * @return array{0:string,1:string} [ISO alpha-2 or '', national number or '']
     */
    public static function split(?string $value): array {
        $value = trim((string) $value);
        if ($value === '') {
            return ['', ''];
        }

        if (preg_match('/^([A-Za-z]{2})' . preg_quote(self::SEP, '/') . '(.*)$/', $value, $m)) {
            $iso = \core_text::strtoupper($m[1]);
            if (self::code($iso) !== '') {
                return [$iso, preg_replace('/\D/', '', $m[2])];
            }
        }

        return self::split_legacy($value);
    }

    /**
     * Read an answer written before the country control existed.
     *
     * Those are plain text: usually a local number, sometimes one the applicant
     * prefixed with their own "+NN". Only the second shape names a country, and
     * only when a single country claims that code - the North American "+1" is
     * shared by two dozen, and picking one of them would put a country on the
     * submission that the applicant never chose. When nothing can be said, the
     * digits are returned whole and the form preselects the usual default, leaving
     * the applicant looking at their own number with a country they can correct.
     *
     * @param string $value the raw stored value
     * @return array{0:string,1:string}
     */
    protected static function split_legacy(string $value): array {
        $digits = preg_replace('/\D/', '', $value);
        if ($digits === '' || !self::available() || strpos(ltrim($value), '+') !== 0) {
            return ['', $digits];
        }

        // Longest code first, or a short one swallows a long one that starts with
        // it: +35 is nobody's code, but trying one digit at a time would match +3
        // (nobody's either) before ever reaching Finland's +358.
        for ($len = 3; $len >= 1; $len--) {
            $owners = array_keys(\profilefield_phone\dialcodes::CODES, substr($digits, 0, $len), true);
            if (count($owners) === 1) {
                return [$owners[0], substr($digits, $len)];
            }
        }

        return ['', $digits];
    }

    /**
     * Join the two controls back into the single stored string.
     *
     * @param string $iso ISO alpha-2 from the country select
     * @param string $number the number as typed
     * @return string `ISO:number`, the bare number when no country is known, or ''
     */
    public static function compose(string $iso, string $number): string {
        $iso = \core_text::strtoupper(trim($iso));
        $number = self::normalise_number($number, $iso);
        if ($number === '') {
            return '';
        }

        return self::code($iso) !== '' ? $iso . self::SEP . $number : $number;
    }

    /**
     * A stored answer as a person reads it: "+20 1012345678".
     *
     * @param string|null $value a stored `jobform_submission_data.value`
     * @return string
     */
    public static function format(?string $value): string {
        [$iso, $number] = self::split($value);
        $code = self::code($iso);
        if ($number === '' || $code === '') {
            // Nothing to improve on: an older answer that named no country is shown
            // exactly as the applicant wrote it, "+" and spacing included, rather
            // than reduced to bare digits.
            return trim((string) $value);
        }

        return '+' . $code . ' ' . $number;
    }

    /**
     * Check one phone answer, in the applicant's language.
     *
     * @param string $iso the chosen country
     * @param string $raw the number exactly as typed
     * @param bool $required whether an empty answer is an error
     * @return string|null the message to show, or null when the answer is fine
     */
    public static function validate(string $iso, string $raw, bool $required): ?string {
        $iso = \core_text::strtoupper(trim($iso));
        $raw = trim($raw);
        $number = self::normalise_number($raw, $iso);

        if ($number === '') {
            // An empty box is either allowed or the one thing missing; a box holding
            // only punctuation ("--") is a number that isn't one.
            if ($raw !== '') {
                return get_string('errphonedigits', 'local_jobform');
            }
            return $required ? get_string('required') : null;
        }

        // What people legitimately type: digits, spaces, dashes, brackets, a "+".
        if (!preg_match('/^\+?[0-9\s().-]+$/', $raw)) {
            return get_string('errphonedigits', 'local_jobform');
        }

        if ($iso === '' || self::code($iso) === '') {
            return get_string('errphonecountry', 'local_jobform');
        }

        // Length, per country where it is known - a number of the wrong length is
        // nobody's number, and a wrong one is only discovered when the call to the
        // applicant fails to connect.
        if (!\profilefield_phone\dialcodes::length_ok($iso, $number)) {
            [$min, $max] = \profilefield_phone\dialcodes::length($iso);

            return $min === $max
                ? get_string('errphonelength', 'local_jobform', $min)
                : get_string('errphonelengthrange', 'local_jobform',
                    (object) ['min' => $min, 'max' => $max]);
        }

        return null;
    }
}
