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

/**
 * Central definition of the Job Form field types.
 *
 * This is the single source of truth shared by the admin template manager
 * (local_jobform) and the activity module (mod_jobform): the list of types,
 * how each one is rendered on the student form, and how a submitted value is
 * formatted for display.
 *
 * @package    local_jobform
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class field_types {

    /** @var string Free single-line text. */
    const TYPE_TEXT = 'text';
    /** @var string Numeric input. */
    const TYPE_NUMBER = 'number';
    /** @var string Email address. */
    const TYPE_EMAIL = 'email';
    /** @var string Phone number. */
    const TYPE_PHONE = 'phone';
    /** @var string Date. */
    const TYPE_DATE = 'date';
    /** @var string Single checkbox (yes/no). */
    const TYPE_CHECKBOX = 'checkbox';
    /** @var string URL / link. */
    const TYPE_URL = 'url';
    /** @var string Dropdown with admin-defined options (single or multiple select). */
    const TYPE_SELECT = 'select';
    /** @var string Fixed value set by the admin; read-only for the student. */
    const TYPE_FIXED = 'fixed';

    /** @var string Pre-fill source not chosen: work it out from the label and type. */
    const AUTOFILL_AUTO = '';
    /** @var string Never pre-fill: the applicant types the value themselves. */
    const AUTOFILL_NONE = 'none';
    /** @var string Pre-fill with the full name on the applicant's account. */
    const AUTOFILL_FULLNAME = 'fullname';
    /** @var string Pre-fill with the email address on the applicant's account. */
    const AUTOFILL_EMAIL = 'email';

    /**
     * All supported types mapped to their language string key.
     *
     * @return string[] type => lang string identifier (in local_jobform)
     */
    public static function all(): array {
        return [
            self::TYPE_TEXT     => 'fieldtype_text',
            self::TYPE_NUMBER   => 'fieldtype_number',
            self::TYPE_EMAIL    => 'fieldtype_email',
            self::TYPE_PHONE    => 'fieldtype_phone',
            self::TYPE_DATE     => 'fieldtype_date',
            self::TYPE_CHECKBOX => 'fieldtype_checkbox',
            self::TYPE_URL      => 'fieldtype_url',
            self::TYPE_SELECT   => 'fieldtype_select',
            self::TYPE_FIXED    => 'fieldtype_fixed',
        ];
    }

    /**
     * A menu of type => localised label, for select boxes.
     *
     * @return string[]
     */
    public static function menu(): array {
        $menu = [];
        foreach (self::all() as $type => $stringkey) {
            $menu[$type] = get_string($stringkey, 'local_jobform');
        }
        return $menu;
    }

    /**
     * Whether the given type is one we know about.
     *
     * @param string $type
     * @return bool
     */
    public static function is_valid(string $type): bool {
        return array_key_exists($type, self::all());
    }

    /**
     * Whether the type carries a list of options (dropdown).
     *
     * @param string $type
     * @return bool
     */
    public static function has_options(string $type): bool {
        return $type === self::TYPE_SELECT;
    }

    /**
     * Whether the type stores an admin-defined fixed value the student cannot change.
     *
     * @param string $type
     * @return bool
     */
    public static function is_fixed(string $type): bool {
        return $type === self::TYPE_FIXED;
    }

    /**
     * Whether a field of this type can carry a value taken from the user's account.
     *
     * Only the free-text-ish inputs can: a dropdown, date, checkbox or fixed value
     * has nothing sensible to copy a name or an email into.
     *
     * @param string $type
     * @return bool
     */
    public static function supports_autofill(string $type): bool {
        return in_array($type, [self::TYPE_TEXT, self::TYPE_EMAIL], true);
    }

    /**
     * The pre-fill choices offered in the field editor.
     *
     * @return string[] value => localised label
     */
    public static function autofill_menu(): array {
        return [
            self::AUTOFILL_AUTO     => get_string('fieldautofill_auto', 'local_jobform'),
            self::AUTOFILL_NONE     => get_string('fieldautofill_none', 'local_jobform'),
            self::AUTOFILL_FULLNAME => get_string('fieldautofill_fullname', 'local_jobform'),
            self::AUTOFILL_EMAIL    => get_string('fieldautofill_email', 'local_jobform'),
        ];
    }

    /**
     * The account property a field should be pre-filled from (AC-4.20.2).
     *
     * An explicit choice in the field editor always wins. When none was made
     * (every field created before this setting existed) the label and the type
     * are used to spot the "full name" and "email" fields automatically, so the
     * existing forms pre-fill without having to be re-edited one by one.
     *
     * @param object $field a field record (needs ->name, ->type, ->configdata)
     * @return string one of AUTOFILL_FULLNAME / AUTOFILL_EMAIL, or '' for no pre-fill
     */
    public static function autofill_source(object $field): string {
        if (!self::supports_autofill($field->type ?? '')) {
            return '';
        }
        $config = self::decode_config($field->configdata ?? null);
        if ($config['autofill'] === self::AUTOFILL_NONE) {
            return '';
        }
        if ($config['autofill'] !== self::AUTOFILL_AUTO) {
            return $config['autofill'];
        }
        return self::detect_autofill($field);
    }

    /**
     * Guess the pre-fill source of a field that has no explicit setting.
     *
     * Deliberately narrow: it must not claim unrelated labels that merely contain
     * the word "name" (e.g. "Job name"), so only whole "full name" style labels
     * and the Arabic labels that open with the definite article match.
     *
     * @param object $field
     * @return string AUTOFILL_FULLNAME / AUTOFILL_EMAIL, or ''
     */
    protected static function detect_autofill(object $field): string {
        $parts = mlang::parse($field->name ?? '');
        $labels = [];
        foreach ($parts as $label) {
            $label = trim(preg_replace('/\s+/u', ' ', (string) $label), " \t\n\r\0\x0B*:.");
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        if (($field->type ?? '') === self::TYPE_EMAIL) {
            return self::AUTOFILL_EMAIL;
        }
        foreach ($labels as $label) {
            $lower = \core_text::strtolower($label);
            if (preg_match('/^e[\s-]?mail\b/u', $lower) || \core_text::strpos($lower, 'بريد') !== false) {
                return self::AUTOFILL_EMAIL;
            }
        }
        foreach ($labels as $label) {
            $lower = \core_text::strtolower($label);
            if (preg_match('/\b(full|complete)\s+name\b/u', $lower)
                    || preg_match('/^(your |applicant |student |candidate )?name$/u', $lower)
                    || preg_match('/^الاسم(\s|$)/u', $label)) {
                return self::AUTOFILL_FULLNAME;
            }
        }
        return '';
    }

    /**
     * The value to pre-fill for a given source, read from the account of record.
     *
     * @param string $source AUTOFILL_FULLNAME / AUTOFILL_EMAIL
     * @param object $user the user record (needs the name fields and ->email)
     * @return string '' when there is nothing to pre-fill with
     */
    public static function autofill_value(string $source, object $user): string {
        switch ($source) {
            case self::AUTOFILL_FULLNAME:
                return trim(fullname($user));
            case self::AUTOFILL_EMAIL:
                return trim((string) ($user->email ?? ''));
            default:
                return '';
        }
    }

    /**
     * Decode a field's configdata JSON into a predictable array.
     *
     * @param string|null $configdata raw JSON from the DB
     * @return array{options: string[], multiple: bool, fixedvalue: string, autofill: string}
     */
    public static function decode_config(?string $configdata): array {
        $decoded = json_decode((string) $configdata, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $options = [];
        if (!empty($decoded['options']) && is_array($decoded['options'])) {
            foreach ($decoded['options'] as $opt) {
                $opt = trim((string) $opt);
                if ($opt !== '') {
                    $options[] = $opt;
                }
            }
        }
        $autofill = isset($decoded['autofill']) ? (string) $decoded['autofill'] : self::AUTOFILL_AUTO;
        if (!in_array($autofill,
                [self::AUTOFILL_NONE, self::AUTOFILL_FULLNAME, self::AUTOFILL_EMAIL], true)) {
            $autofill = self::AUTOFILL_AUTO;
        }
        return [
            'options'    => $options,
            'multiple'   => !empty($decoded['multiple']),
            'fixedvalue' => isset($decoded['fixedvalue']) ? (string) $decoded['fixedvalue'] : '',
            'autofill'   => $autofill,
        ];
    }

    /**
     * Build the configdata JSON string from raw inputs, keeping only what the type needs.
     *
     * @param string $type
     * @param string $optionstext newline-separated option list (dropdown only)
     * @param bool $multiple allow multi-select (dropdown only)
     * @param string $fixedvalue the fixed value (fixed type only)
     * @param string $autofill which account property pre-fills this field (text / email types)
     * @return string JSON
     */
    public static function encode_config(string $type, string $optionstext, bool $multiple,
            string $fixedvalue, string $autofill = self::AUTOFILL_AUTO): string {
        $config = [];
        if (self::has_options($type)) {
            $options = [];
            foreach (preg_split('/\r\n|\r|\n/', $optionstext) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $options[] = $line;
                }
            }
            $config['options'] = array_values(array_unique($options));
            $config['multiple'] = $multiple ? 1 : 0;
        } else if (self::is_fixed($type)) {
            $config['fixedvalue'] = $fixedvalue;
        }
        // Only stored when actually chosen; an unset value keeps the automatic detection.
        if (self::supports_autofill($type) && $autofill !== self::AUTOFILL_AUTO
                && in_array($autofill,
                    [self::AUTOFILL_NONE, self::AUTOFILL_FULLNAME, self::AUTOFILL_EMAIL], true)) {
            $config['autofill'] = $autofill;
        }
        return json_encode($config);
    }

    /**
     * Format a stored submission value for read-only display (admin view / confirmation).
     *
     * @param object $field a field record (needs ->type, ->configdata)
     * @param string|null $value the stored value
     * @return string plain text, safe to pass through format_string() by the caller
     */
    public static function format_value(object $field, ?string $value): string {
        $value = (string) $value;
        switch ($field->type) {
            case self::TYPE_CHECKBOX:
                return $value ? get_string('yes') : get_string('no');
            case self::TYPE_DATE:
                return $value !== '' ? userdate((int) $value, get_string('strftimedate', 'langconfig')) : '';
            case self::TYPE_PHONE:
                // Stored as "EG:1012345678" (AC-4.20.4), read as "+20 1012345678".
                // Answers written before the country control existed are plain
                // numbers and come back out unchanged.
                return phone::format($value);
            case self::TYPE_SELECT:
                // Stored as a JSON array for multi-select, plain string otherwise.
                // Each stored value may be a {mlang} option, so resolve for display.
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    return implode(', ', array_map([mlang::class, 'resolve'], $decoded));
                }
                return mlang::resolve($value);
            case self::TYPE_FIXED:
                $config = self::decode_config($field->configdata);
                return mlang::resolve($config['fixedvalue']);
            default:
                return $value;
        }
    }
}
