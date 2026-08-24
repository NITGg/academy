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

defined('MOODLE_INTERNAL') || die();

/**
 * Creates the academy's instructor profile fields in one action.
 *
 * The sibling of {@see provision}, for the second requested set: the fields an
 * instructor profile needs on top of the personal details everybody has. Same
 * contract - writes straight to `user_info_field`, fires the same events, and is
 * idempotent, so a shortname that already exists anywhere is left exactly as it is.
 *
 * Fourteen of the thirty fields originally requested are deliberately absent:
 * they are core user fields or already live in "Additional details". They are
 * listed by {@see not_created()} so a run can be audited against the request
 * rather than appearing to have silently dropped them.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class instructor_fields {

    /**
     * The category these fields live in.
     *
     * @return string localised category name
     */
    public static function category_name(): string {
        return get_string('instructorcategory', 'local_profilefields');
    }

    /**
     * Whether the file profile field type is installed.
     *
     * Moodle core ships no file-upload profile field, so "Cover image" and
     * "Resume" need profilefield_file. Without it they fall back to a URL text
     * field rather than not being created at all.
     *
     * @return bool
     */
    public static function file_available(): bool {
        return \core_component::get_component_directory('profilefield_file') !== null;
    }

    /**
     * The instructor fields, in display order.
     *
     * Every one of them is optional and non-unique - that is what the request
     * asked for, and it is also the only sane default for a profile that is filled
     * in gradually.
     *
     * @return array[] field specs keyed by shortname
     */
    public static function fields(): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $hasfile = self::file_available();

        // Cover image and resume are real uploads when profilefield_file is
        // present, and a pasted URL when it is not.
        $coverimage = $hasfile
            ? ['datatype' => 'file', 'param1' => 'web_image', 'param2' => 0, 'param3' => 'image']
            : ['datatype' => 'text', 'param1' => 60, 'param2' => 1333, 'param3' => 0];
        $resume = $hasfile
            ? ['datatype' => 'file', 'param1' => '.pdf,.doc,.docx', 'param2' => 0, 'param3' => 'link']
            : ['datatype' => 'text', 'param1' => 60, 'param2' => 1333, 'param3' => 0];

        return [
            'coverimage' => $coverimage + [
                'name' => 'Cover image', 'namestr' => 'fieldcoverimage',
            ],
            'biography' => [
                'name' => 'Biography', 'namestr' => 'fieldbiography',
                'datatype' => 'textarea',
            ],
            'qualifications' => [
                'name' => 'Qualifications', 'namestr' => 'fieldqualifications',
                'datatype' => 'textarea',
            ],
            'certificates' => [
                'name' => 'Certificates', 'namestr' => 'fieldcertificates',
                'datatype' => 'textarea',
            ],
            'experience' => [
                'name' => 'Experience', 'namestr' => 'fieldexperience',
                'datatype' => 'textarea',
            ],
            'specialization' => [
                'name' => 'Specialization', 'namestr' => 'fieldspecialization',
                'datatype' => 'text', 'param1' => 40, 'param2' => 255,
            ],
            'languages' => [
                'name' => 'Languages', 'namestr' => 'fieldlanguages',
                'datatype' => 'text', 'param1' => 40, 'param2' => 255,
            ],
            'linkedin' => [
                'name' => 'LinkedIn', 'namestr' => 'fieldlinkedin',
                'datatype' => 'text', 'param1' => 50, 'param2' => 255,
            ],
            'website' => [
                'name' => 'Website', 'namestr' => 'fieldwebsite',
                'datatype' => 'text', 'param1' => 50, 'param2' => 255,
            ],
            'facebook' => [
                'name' => 'Facebook', 'namestr' => 'fieldfacebook',
                'datatype' => 'text', 'param1' => 50, 'param2' => 255,
            ],
            'instagram' => [
                'name' => 'Instagram', 'namestr' => 'fieldinstagram',
                'datatype' => 'text', 'param1' => 50, 'param2' => 255,
            ],
            'twitter' => [
                'name' => 'Twitter', 'namestr' => 'fieldtwitter',
                'datatype' => 'text', 'param1' => 50, 'param2' => 255,
            ],
            'youtube' => [
                'name' => 'YouTube', 'namestr' => 'fieldyoutube',
                'datatype' => 'text', 'param1' => 50, 'param2' => 255,
            ],
            'awards' => [
                'name' => 'Awards', 'namestr' => 'fieldawards',
                'datatype' => 'textarea',
            ],
            'yearsofexperience' => [
                'name' => 'Years of experience', 'namestr' => 'fieldyearsofexperience',
                'datatype' => 'text', 'param1' => 6, 'param2' => 3,
            ],
            'resume' => $resume + [
                'name' => 'Resume', 'namestr' => 'fieldresume',
            ],
        ];
    }

    /**
     * Requested fields that are intentionally never created, and why.
     *
     * @return string[] label => reason (already localised)
     */
    public static function not_created(): array {
        return [
            'Full Name'     => get_string('skipcorename', 'local_profilefields'),
            'E-mail'        => get_string('skipcoreemail', 'local_profilefields'),
            'Country'       => get_string('skipcorecountry', 'local_profilefields'),
            'Photo'         => get_string('skipcorepicture', 'local_profilefields'),
            'Phone'         => get_string('skipexisting', 'local_profilefields', 'phone'),
            'Nationality'   => get_string('skipexisting', 'local_profilefields', 'nationality'),
            'Gender'        => get_string('skipexisting', 'local_profilefields', 'gender'),
            'Date of Birth' => get_string('skipexisting', 'local_profilefields', 'dateofbirth'),
            'Job Title'     => get_string('skipexisting', 'local_profilefields', 'jobtitle'),
            'Company'       => get_string('skipexisting', 'local_profilefields', 'company'),
            'Industry'      => get_string('skipexisting', 'local_profilefields', 'industry'),
            'Education'     => get_string('skipexisting', 'local_profilefields', 'education'),
            'National ID'   => get_string('skipexisting', 'local_profilefields', 'nationalid'),
            'Passport'      => get_string('skipexisting', 'local_profilefields', 'passport'),
        ];
    }

    /**
     * Which instructor fields do not yet exist.
     *
     * @return string[] shortnames still missing
     */
    public static function missing(): array {
        global $DB;

        $existing = array_flip($DB->get_fieldset_select('user_info_field', 'shortname', ''));

        $missing = [];
        foreach (self::fields() as $shortname => $unused) {
            if (!isset($existing[$shortname])) {
                $missing[] = $shortname;
            }
        }
        return $missing;
    }

    /**
     * Instructor fields that already exist, and the category each one is in.
     *
     * @return string[] shortname => category name
     */
    public static function existing(): array {
        global $DB;

        $rows = $DB->get_records_sql('
                SELECT f.shortname, c.name AS categoryname
                  FROM {user_info_field} f
             LEFT JOIN {user_info_category} c ON c.id = f.categoryid');

        $found = [];
        foreach (self::fields() as $shortname => $unused) {
            if (isset($rows[$shortname])) {
                $found[$shortname] = $rows[$shortname]->categoryname ?? '';
            }
        }
        return $found;
    }

    /**
     * Create every instructor field that is missing.
     *
     * @param int $signup 1 to also show them on the sign-up form
     * @return int the number of fields created
     */
    public static function run(int $signup = 0): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/user/profile/definelib.php');

        $categoryid = self::ensure_category();
        $sortorder = (int) $DB->get_field_sql(
            'SELECT MAX(sortorder) FROM {user_info_field} WHERE categoryid = ?', [$categoryid]);

        $created = 0;
        foreach (self::fields() as $shortname => $spec) {
            if ($DB->record_exists('user_info_field', ['shortname' => $shortname])) {
                continue;
            }
            $sortorder++;
            self::create_field($shortname, $spec, $categoryid, $sortorder, $signup);
            $created++;
        }

        if ($created > 0) {
            profile_purge_user_fields_cache();
        }

        return $created;
    }

    /**
     * The category id for the instructor fields, creating it if needed.
     *
     * @return int
     */
    protected static function ensure_category(): int {
        global $DB;

        $name = self::category_name();
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
     * @param int $sortorder
     * @param int $signup
     * @return void
     */
    protected static function create_field(string $shortname, array $spec, int $categoryid,
            int $sortorder, int $signup): void {
        global $DB;

        // A file field cannot appear on sign-up: uploading needs a user context,
        // which does not exist until the account does.
        if ($spec['datatype'] === 'file') {
            $signup = 0;
        }

        $record = (object) [
            'shortname'         => $shortname,
            'name'              => provision::field_name($spec),
            'datatype'          => $spec['datatype'],
            'description'       => '',
            'descriptionformat' => FORMAT_HTML,
            'categoryid'        => $categoryid,
            'sortorder'         => $sortorder,
            'required'          => 0,
            'locked'            => 0,
            'visible'           => PROFILE_VISIBLE_ALL,
            'forceunique'       => 0,
            'signup'            => $signup,
            'defaultdata'       => '',
            'defaultdataformat' => FORMAT_HTML,
            'param1'            => isset($spec['param1']) ? (string) $spec['param1'] : null,
            'param2'            => isset($spec['param2']) ? (string) $spec['param2'] : null,
            'param3'            => isset($spec['param3']) ? (string) $spec['param3'] : null,
            'param4'            => null,
            'param5'            => null,
        ];
        $record->id = $DB->insert_record('user_info_field', $record);

        $field = $DB->get_record('user_info_field', ['id' => $record->id]);
        \core\event\user_info_field_created::create_from_field($field)->trigger();
    }
}
