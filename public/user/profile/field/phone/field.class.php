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
 * Phone profile field.
 *
 * @package    profilefield_phone
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use profilefield_phone\dialcodes;

defined('MOODLE_INTERNAL') || die();

/**
 * Phone number with country dialling code.
 *
 * The field renders as two controls - a country select (flag + name + "+NN") and a
 * number box - but a profile field owns a single `user_info_data` row, so the two
 * are combined into one stored value. The format is `ISO:nationalnumber`, e.g.
 * `EG:1012345678`. Storing the ISO rather than the raw "+20" keeps the value
 * unambiguous (many countries share a dialling code) and lets uniqueness work on a
 * single normalised string.
 *
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_phone extends profile_field_base {

    /** @var string ISO alpha-2 country part of the current value. */
    protected $country = '';

    /** @var string National-number part of the current value (digits only). */
    protected $number = '';

    /**
     * Constructor.
     *
     * @param int $fieldid
     * @param int $userid
     * @param object|null $fielddata
     */
    public function __construct($fieldid = 0, $userid = 0, $fielddata = null) {
        parent::__construct($fieldid, $userid, $fielddata);

        [$this->country, $this->number] = self::split((string) $this->data);
    }

    /**
     * Break a stored value into its ISO and number parts.
     *
     * @param string $value stored as `ISO:number`
     * @return array{0:string,1:string} [iso, number]
     */
    protected static function split(string $value): array {
        if ($value === '' || strpos($value, ':') === false) {
            return ['', ''];
        }
        [$iso, $number] = explode(':', $value, 2);
        return [strtoupper(trim($iso)), preg_replace('/\D/', '', $number)];
    }

    /**
     * Reduce a number entered by a user to its national digits.
     *
     * Strips spaces, dashes and brackets, a single leading zero (trunk prefix), and
     * a leading dialling code the user may have typed in themselves.
     *
     * @param string $number the raw number
     * @param string $iso the selected country
     * @return string digits only, without trunk prefix or dialling code
     */
    protected static function normalise_number(string $number, string $iso): string {
        $digits = preg_replace('/\D/', '', $number);
        if ($digits === '') {
            return '';
        }

        $dial = dialcodes::code($iso);
        if ($dial !== '' && strpos($digits, $dial) === 0 && strlen($digits) > strlen($dial)) {
            $digits = substr($digits, strlen($dial));
        }

        return ltrim($digits, '0');
    }

    /**
     * Add the country select and number box, grouped under the field name.
     *
     * @param MoodleQuickForm $mform
     */
    public function edit_field_add($mform) {
        $countries = dialcodes::menu();
        if (!$this->is_required()) {
            $countries = ['' => get_string('choosedots')] + $countries;
        }

        $group = [
            $mform->createElement('select', 'country', get_string('country'), $countries,
                ['class' => 'profilefield-phone-country']),
            // The number is forced left-to-right with a dir attribute rather than
            // setForceLtr(): the latter looks the element up by name, and a grouped
            // sub-element ("name[number]") is not a top-level element, so the lookup
            // raises a PEAR error that is fatal on PHP 8.
            $mform->createElement('text', 'number', get_string('phone'),
                ['size' => 20, 'dir' => 'ltr', 'class' => 'profilefield-phone-number']),
        ];
        $mform->addGroup($group, $this->inputname, format_string($this->field->name), ' ', true);
        $mform->setType($this->inputname . '[number]', PARAM_TEXT);

        // "Required" is enforced server-side in edit_validate_field(), not with a
        // group rule. A client-side group rule records the group name against the
        // rule; when this plugin later reorders the field on the sign-up form, the
        // form's validation-script builder resolves that name to the inner select
        // instead of the group and calls a group-only method on it - fatal on the
        // whole page. Skipping the rule keeps the field required without that risk.
    }

    /**
     * Preselect the country - the saved one, or the field default, or the IP guess.
     *
     * @param MoodleQuickForm $mform
     */
    public function edit_field_set_default($mform) {
        $iso = $this->country;
        if ($iso === '') {
            $iso = strtoupper((string) $this->field->defaultdata);
        }
        if ($iso === '' || dialcodes::code($iso) === '') {
            $iso = dialcodes::default_country();
        }

        $mform->setDefault($this->inputname, ['country' => $iso, 'number' => $this->number]);
    }

    /**
     * Combine the two controls into the single stored string.
     *
     * @param mixed $data the group value: ['country' => .., 'number' => ..]
     * @param stdClass $datarecord unused
     * @return string|null `ISO:number`, or null when empty
     */
    public function edit_save_data_preprocess($data, $datarecord) {
        [$iso, $number] = self::values($data);
        if ($number === '') {
            return null;
        }
        return $iso . ':' . $number;
    }

    /**
     * Fill the group when the edit form opens.
     *
     * @param stdClass $user
     */
    public function edit_load_user_data($user) {
        $iso = $this->country !== '' ? $this->country : dialcodes::default_country();
        $user->{$this->inputname} = ['country' => $iso, 'number' => $this->number];
    }

    /**
     * Validate the number and, when required, enforce uniqueness on the full value.
     *
     * @param stdClass $usernew
     * @return array element name => error
     */
    public function edit_validate_field($usernew) {
        global $DB;

        $errors = [];
        $raw = $usernew->{$this->inputname} ?? null;
        [$iso, $number] = self::values($raw);

        if ($number === '') {
            // Required-ness is enforced here (there is no client-side group rule).
            // On the sign-up page and when editing one's own profile a missing value
            // is an error; an admin editing someone else may legitimately leave it.
            if ($this->is_required() && ($this->userid == 0 || isguestuser()
                    || $this->userid == ($GLOBALS['USER']->id ?? 0))) {
                $errors[$this->inputname] = get_string('required');
            }
            return $errors;
        }

        if ($iso === '' || dialcodes::code($iso) === '') {
            $errors[$this->inputname] = get_string('selectacountry');
            return $errors;
        }
        if (strlen($number) < 4 || strlen($number) > 15) {
            $errors[$this->inputname] = get_string('invalidphone', 'profilefield_phone');
            return $errors;
        }

        if ($this->is_unique()) {
            $value = $iso . ':' . $number;
            $rows = $DB->get_records_sql('
                    SELECT id, userid
                      FROM {user_info_data}
                     WHERE fieldid = ?
                       AND ' . $DB->sql_compare_text('data', 255) . ' = ' . $DB->sql_compare_text('?', 255),
                    [$this->field->id, $value]);
            foreach ($rows as $row) {
                if ($row->userid != $usernew->id) {
                    $errors[$this->inputname] = get_string('valuealreadyused');
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * Pull normalised (iso, number) out of a submitted group value.
     *
     * @param mixed $data the group value
     * @return array{0:string,1:string}
     */
    protected static function values($data): array {
        if (!is_array($data)) {
            return ['', ''];
        }
        $iso = strtoupper(trim((string) ($data['country'] ?? '')));
        $number = self::normalise_number((string) ($data['number'] ?? ''), $iso);
        return [$iso, $number];
    }

    /**
     * Freeze both controls when the field is locked for this user.
     *
     * @param MoodleQuickForm $mform
     */
    public function edit_field_set_locked($mform) {
        if (!$mform->elementExists($this->inputname)) {
            return;
        }
        if ($this->is_locked() && !has_capability('moodle/user:update', context_system::instance())) {
            $mform->hardFreeze($this->inputname);
        }
    }

    /**
     * Human-readable value for profile pages: "+20 1012345678".
     *
     * @return string
     */
    public function display_data() {
        if ($this->number === '') {
            return '';
        }
        $dial = dialcodes::code($this->country);
        $prefix = $dial !== '' ? '+' . $dial . ' ' : '';
        return s($prefix . $this->number);
    }

    /**
     * Data type and nullability for the signup/web-service validators.
     *
     * @return array
     */
    public function get_field_properties() {
        return [PARAM_RAW, NULL_NOT_ALLOWED];
    }
}
