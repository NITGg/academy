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

namespace local_profilefields;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates the academy's recommended set of custom profile fields in one action.
 *
 * The requirement lists a fixed set of profile fields (phone, nationality, gender,
 * date of birth, job title, company, industry, education, national ID, passport).
 * Rather than ask the admin to hand-build ten fields on the core screen, this class
 * creates them - and their category - with the right type and flags. It is
 * idempotent: a field whose shortname already exists is left exactly as it is, so
 * running it twice, or after an admin has tweaked a field, changes nothing.
 *
 * It writes straight to `user_info_field` (the same table the core screen writes)
 * and fires the same events, so the fields are indistinguishable from ones created
 * by hand. This is provisioning, not a parallel store.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provision {

    /** @var string Shortname of the category the recommended fields live in. */
    const CATEGORY = 'academy_details';

    /**
     * The recommended fields, in display order.
     *
     * Flags map straight onto `user_info_field` columns. `signup` decides the
     * register form, `visible` the profile form, `required`/`forceunique`/`locked`
     * the usual per-field rules. `menu` fields set `param1` to their newline-joined
     * options; `countries` is expanded to the localised country list at run time.
     *
     * @return array[] field specs keyed by shortname
     */
    public static function fields(): array {
        global $CFG;
        // The specs reference PROFILE_VISIBLE_* (lib.php); provisioning purges the
        // field cache with profile_purge_user_fields_cache() (definelib.php). Neither
        // pulls in the other, and CLI upgrade loads neither, so require both here -
        // every provisioning path goes through fields().
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/user/profile/definelib.php');

        return [
            'phone' => [
                'name' => 'Phone', 'namestr' => 'fieldphone',
                'datatype' => 'phone',
                'required' => 1, 'forceunique' => 1, 'locked' => 0,
                'signup' => 1, 'visible' => PROFILE_VISIBLE_ALL,
            ],
            'nationality' => [
                'name' => 'Nationality', 'namestr' => 'fieldnationality',
                'datatype' => 'menu', 'options' => 'countries',
                'required' => 0, 'forceunique' => 0, 'locked' => 0,
                'signup' => 1, 'visible' => PROFILE_VISIBLE_ALL,
            ],
            'gender' => [
                'name' => 'Gender', 'namestr' => 'fieldgender',
                'datatype' => 'menu', 'options' => "Female\nMale\nPrefer not to say",
                'required' => 0, 'forceunique' => 0, 'locked' => 0,
                'signup' => 0, 'visible' => PROFILE_VISIBLE_ALL,
            ],
            'dateofbirth' => [
                'name' => 'Date of birth', 'namestr' => 'fielddateofbirth',
                'datatype' => 'datetime',
                'required' => 0, 'forceunique' => 0, 'locked' => 0,
                'signup' => 0, 'visible' => PROFILE_VISIBLE_ALL,
            ],
            'jobtitle' => [
                'name' => 'Job title', 'namestr' => 'fieldjobtitle',
                'datatype' => 'text',
                'required' => 0, 'forceunique' => 0, 'locked' => 0,
                'signup' => 0, 'visible' => PROFILE_VISIBLE_ALL,
            ],
            'company' => [
                'name' => 'Company', 'namestr' => 'fieldcompany',
                'datatype' => 'text',
                'required' => 0, 'forceunique' => 0, 'locked' => 0,
                'signup' => 0, 'visible' => PROFILE_VISIBLE_ALL,
            ],
            'industry' => [
                'name' => 'Industry', 'namestr' => 'fieldindustry',
                'datatype' => 'text',
                'required' => 0, 'forceunique' => 0, 'locked' => 0,
                'signup' => 0, 'visible' => PROFILE_VISIBLE_ALL,
            ],
            'education' => [
                'name' => 'Education', 'namestr' => 'fieldeducation',
                'datatype' => 'text',
                'required' => 0, 'forceunique' => 0, 'locked' => 0,
                'signup' => 0, 'visible' => PROFILE_VISIBLE_ALL,
            ],
            'nationalid' => [
                'name' => 'National ID', 'namestr' => 'fieldnationalid',
                'datatype' => 'text',
                'required' => 0, 'forceunique' => 0, 'locked' => 0,
                'signup' => 0, 'visible' => PROFILE_VISIBLE_ALL,
            ],
            'passport' => [
                'name' => 'Passport', 'namestr' => 'fieldpassport',
                'datatype' => 'text',
                'required' => 0, 'forceunique' => 0, 'locked' => 0,
                'signup' => 0, 'visible' => PROFILE_VISIBLE_ALL,
            ],
        ];
    }

    /**
     * Which of the recommended fields do not yet exist.
     *
     * @return string[] shortnames still missing
     */
    public static function missing(): array {
        global $DB;

        $existing = $DB->get_fieldset_select('user_info_field', 'shortname', '');
        $existing = array_flip($existing);

        $missing = [];
        foreach (self::fields() as $shortname => $spec) {
            if (!isset($existing[$shortname])) {
                $missing[] = $shortname;
            }
        }
        return $missing;
    }

    /**
     * Whether the phone field type plugin is installed.
     *
     * The recommended set includes a phone field; without profilefield_phone that
     * one field cannot be created, so the page can warn instead of failing silently.
     *
     * @return bool
     */
    public static function phone_available(): bool {
        return \core_component::get_component_directory('profilefield_phone') !== null;
    }

    /**
     * Create every recommended field that is missing.
     *
     * @return int the number of fields created
     */
    public static function run(): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $categoryid = self::ensure_category();
        $created = 0;

        foreach (self::fields() as $shortname => $spec) {
            if ($DB->record_exists('user_info_field', ['shortname' => $shortname])) {
                continue;
            }
            if ($spec['datatype'] === 'phone' && !self::phone_available()) {
                continue;
            }
            self::create_field($shortname, $spec, $categoryid);
            $created++;
        }

        if ($created > 0) {
            profile_purge_user_fields_cache();
        }

        self::ensure_signup_order();

        return $created;
    }

    /**
     * Set a sensible default sign-up field order, once.
     *
     * Only applied when the admin has not arranged the fields themselves, so their
     * choice is never overridden. Tokens for fields that do not exist are ignored
     * when the order is applied, so listing cf:phone before it is created is safe.
     *
     * @return void
     */
    public static function ensure_signup_order(): void {
        if (empty(manager::signup_order())) {
            manager::set_signup_order([
                'firstname', 'lastname', 'email', 'cf:phone', 'cf:nationality', 'password',
            ]);
        }
    }

    /**
     * The category id for the recommended fields, creating it if needed.
     *
     * @return int
     */
    protected static function ensure_category(): int {
        global $DB;

        $name = get_string('academycategory', 'local_profilefields');
        if ($id = $DB->get_field('user_info_category', 'id', ['name' => $name])) {
            return (int) $id;
        }

        $sortorder = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {user_info_category}') + 1;
        $category = (object) ['name' => $name, 'sortorder' => $sortorder];
        $category->id = $DB->insert_record('user_info_category', $category);

        \core\event\user_info_category_created::create_from_category($category)->trigger();

        return (int) $category->id;
    }

    /**
     * Insert one field record and fire the created event.
     *
     * @param string $shortname
     * @param array $spec one entry from self::fields()
     * @param int $categoryid
     * @return void
     */
    protected static function create_field(string $shortname, array $spec, int $categoryid): void {
        global $DB;

        $sortorder = (int) $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {user_info_field} WHERE categoryid = ?', [$categoryid]) + 1;

        $record = (object) [
            'shortname'         => $shortname,
            'name'              => self::field_name($spec),
            'datatype'          => $spec['datatype'],
            'description'       => '',
            'descriptionformat' => FORMAT_HTML,
            'categoryid'        => $categoryid,
            'sortorder'         => $sortorder,
            'required'          => $spec['required'],
            'locked'            => $spec['locked'],
            'visible'           => $spec['visible'],
            'forceunique'       => $spec['forceunique'],
            'signup'            => $spec['signup'],
            'defaultdata'       => self::field_defaultdata($spec),
            'defaultdataformat' => FORMAT_HTML,
            'param1'            => self::field_param1($spec),
            'param2'            => self::field_param2($spec),
            'param3'            => self::field_param3($spec),
            'param4'            => null,
            'param5'            => null,
        ];
        $record->id = $DB->insert_record('user_info_field', $record);

        $field = $DB->get_record('user_info_field', ['id' => $record->id]);
        \core\event\user_info_field_created::create_from_field($field)->trigger();
    }

    /**
     * Correct any recommended datetime field left with invalid parameters.
     *
     * A datetime profile field needs numeric start/end years and a numeric default;
     * created without them, `profilefield_datetime` passes an empty string to
     * `getdate()`, which is fatal on PHP 8 and takes down every profile edit page.
     * This repairs such a field in place so an already-provisioned site recovers.
     *
     * @return bool true if anything was repaired
     */
    public static function repair(): bool {
        global $DB;

        $repaired = self::repair_names();

        foreach (self::fields() as $shortname => $spec) {
            if ($spec['datatype'] !== 'datetime') {
                continue;
            }
            $field = $DB->get_record('user_info_field', ['shortname' => $shortname, 'datatype' => 'datetime']);
            if (!$field) {
                continue;
            }
            if (self::is_numeric_or_empty($field->param1) && self::is_numeric_or_empty($field->param2)
                    && ($field->defaultdata === '0' || (string) $field->defaultdata === '0' || $field->defaultdata === null)) {
                // param1/param2 already sane and default numeric - nothing to fix.
                if ($field->param1 !== null && $field->param1 !== '' && $field->param2 !== null && $field->param2 !== '') {
                    continue;
                }
            }
            $DB->update_record('user_info_field', (object) [
                'id'          => $field->id,
                'param1'      => (string) self::field_param1($spec),
                'param2'      => (string) self::field_param2($spec),
                'param3'      => (string) self::field_param3($spec),
                'defaultdata' => (string) self::field_defaultdata($spec),
            ]);
            $repaired = true;
        }

        if ($repaired) {
            profile_purge_user_fields_cache();
        }
        return $repaired;
    }

    /**
     * Strip leaked {mlang} tags from recommended field names when no filter renders them.
     *
     * If a multilang filter is enabled the tags are left in place (they render as
     * intended); only on a site without the filter are they collapsed to the
     * English part, so raw tags never show to users.
     *
     * @return bool true if any name was changed
     */
    protected static function repair_names(): bool {
        global $DB;

        if (self::multilang_active()) {
            return false;
        }

        $changed = false;
        foreach (self::fields() as $shortname => $spec) {
            $field = $DB->get_record('user_info_field', ['shortname' => $shortname]);
            if (!$field || strpos((string) $field->name, '{mlang') === false) {
                continue;
            }
            if (preg_match('/\{mlang\s+en\}(.*?)\{mlang\}/s', $field->name, $m)) {
                $plain = trim($m[1]);
            } else {
                $plain = $spec['name'];
            }
            $DB->set_field('user_info_field', 'name', $plain, ['id' => $field->id]);
            $changed = true;
        }
        return $changed;
    }

    /**
     * Whether a stored parameter is numeric or blank (never a stray string).
     *
     * @param mixed $value
     * @return bool
     */
    protected static function is_numeric_or_empty($value): bool {
        return $value === null || $value === '' || is_numeric($value);
    }

    /**
     * The field name, bilingual when the string is translated.
     *
     * Uses `{mlang}` so the one stored name renders in whichever language the
     * viewer is using - the same mechanism the rest of the site uses for
     * multilingual field labels.
     *
     * @param array $spec one entry from self::fields()
     * @return string
     */
    protected static function field_name(array $spec): string {
        $en = $spec['name'];

        // A bilingual {mlang} name only makes sense when a multilang filter will
        // actually render it; otherwise the raw tags would show to every user. On a
        // site without the filter, store the plain English name instead.
        if (!self::multilang_active()) {
            return $en;
        }

        $ar = get_string_manager()->string_exists($spec['namestr'], 'local_profilefields')
            ? get_string_manager()->get_string($spec['namestr'], 'local_profilefields', null, 'ar')
            : '';

        if ($ar === '' || $ar === $en) {
            return $en;
        }
        return '{mlang en}' . $en . '{mlang}{mlang ar}' . $ar . '{mlang}';
    }

    /**
     * Whether a multilang filter is globally enabled to render {mlang} tags.
     *
     * @return bool
     */
    protected static function multilang_active(): bool {
        global $CFG;
        require_once($CFG->dirroot . '/lib/filterlib.php');

        $enabled = filter_get_globally_enabled();
        return isset($enabled['multilang']) || isset($enabled['multilang2']);
    }

    /**
     * The param1 value for a field - its menu options, if any.
     *
     * @param array $spec one entry from self::fields()
     * @return string|null
     */
    protected static function field_param1(array $spec): ?string {
        $type = $spec['datatype'] ?? '';
        if ($type === 'datetime') {
            // Start year: old enough for a date of birth.
            return '1920';
        }
        if ($type !== 'menu') {
            return null;
        }
        if (($spec['options'] ?? '') === 'countries') {
            $countries = get_string_manager()->get_list_of_countries(true);
            return implode("\n", array_values($countries));
        }
        return $spec['options'] ?? '';
    }

    /**
     * The param2 value for a field - the datetime end year, otherwise none.
     *
     * @param array $spec one entry from self::fields()
     * @return string|null
     */
    protected static function field_param2(array $spec): ?string {
        if (($spec['datatype'] ?? '') === 'datetime') {
            return (string) (int) userdate(time(), '%Y');
        }
        return null;
    }

    /**
     * The param3 value for a field - datetime "include time", off by default.
     *
     * @param array $spec one entry from self::fields()
     * @return string|null
     */
    protected static function field_param3(array $spec): ?string {
        if (($spec['datatype'] ?? '') === 'datetime') {
            return '0';
        }
        return null;
    }

    /**
     * The default value for a field - a numeric zero for datetime, else blank.
     *
     * @param array $spec one entry from self::fields()
     * @return string
     */
    protected static function field_defaultdata(array $spec): string {
        return (($spec['datatype'] ?? '') === 'datetime') ? '0' : '';
    }
}
