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
 * Bulk-create the "Instructor Fields" custom profile fields.
 *
 * This is data entry, not a feature: it creates on /user/profile/index.php exactly
 * what an admin would create by hand, writing to the same table and firing the
 * same events.
 *
 * It is idempotent and never touches anything that already exists. A shortname
 * already present anywhere on the site - a field in "Additional details", or one
 * added by hand - is reported as SKIP and left completely alone.
 *
 * Usage (from the Moodle code root, inside the container):
 *   php public/local/profilefields/cli/create_instructor_fields.php --dry-run
 *   php public/local/profilefields/cli/create_instructor_fields.php --run
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/user/profile/definelib.php');

/**
 * Print the requested fields that were intentionally not created.
 *
 * @param array $notcreated label => reason
 * @return void
 */
function local_profilefields_report_skipped(array $notcreated): void {
    cli_writeln("\nRequested but NOT created (already covered elsewhere):");
    foreach ($notcreated as $label => $reason) {
        cli_writeln(sprintf('  %-16s %s', $label, $reason));
    }
}

list($options, $unrecognized) = cli_get_params(
    [
        'run'      => false,
        'dry-run'  => false,
        'category' => 'Instructor Fields',
        'signup'   => false,
        'help'     => false,
    ],
    ['h' => 'help', 'n' => 'dry-run']
);

if ($unrecognized) {
    cli_error('Unrecognised option: ' . implode(', ', $unrecognized));
}

if ($options['help'] || (!$options['run'] && !$options['dry-run'])) {
    cli_writeln("Create the Instructor Fields custom profile fields.\n");
    cli_writeln("  --dry-run, -n       show what would be created, change nothing");
    cli_writeln("  --run               actually create the missing fields");
    cli_writeln("  --category=NAME     category to create them in (default 'Instructor Fields')");
    cli_writeln("  --signup            also show the fields on the sign-up form (default: off)");
    cli_writeln("  --help, -h          this help\n");
    exit(0);
}

$dryrun = !$options['run'];
$categoryname = trim((string) $options['category']);
$signup = $options['signup'] ? 1 : 0;

if ($categoryname === '') {
    cli_error('--category cannot be empty.');
}

/*
 * The fields to create, in display order.
 *
 * 'why' documents a deliberate decision so the output explains itself.
 *
 * Datatype notes:
 *  - Moodle core ships no file-upload profile field, so "Cover image" and "Resume"
 *    are URL text fields: upload the file (private files / a course file area) and
 *    paste its link. Installing a profilefield_file plugin would be a separate job.
 *  - Long-form entries (biography, qualifications, certificates, experience,
 *    awards) are 'textarea' so instructors get an editor and more than one line;
 *    short entries stay 'text'.
 *  - Link fields expect a full URL pasted in. param4 (the auto-link template) is
 *    deliberately left empty: it urlencodes the stored value, which mangles a URL
 *    that is already complete.
 */
$fields = [
    'coverimage' => [
        'name' => 'Cover image', 'datatype' => 'text',
        'param1' => 60, 'param2' => 1333,
        'why' => 'URL - core has no file-upload profile field',
    ],
    'biography' => [
        'name' => 'Biography', 'datatype' => 'textarea',
        'why' => 'long form; separate from the core Description field',
    ],
    'qualifications' => [
        'name' => 'Qualifications', 'datatype' => 'textarea',
        'why' => 'long form',
    ],
    'certificates' => [
        'name' => 'Certificates', 'datatype' => 'textarea',
        'why' => 'long form',
    ],
    'experience' => [
        'name' => 'Experience', 'datatype' => 'textarea',
        'why' => 'long form',
    ],
    'specialization' => [
        'name' => 'Specialization', 'datatype' => 'text',
        'param1' => 40, 'param2' => 255,
    ],
    'languages' => [
        'name' => 'Languages', 'datatype' => 'text',
        'param1' => 40, 'param2' => 255,
    ],
    'linkedin' => [
        'name' => 'LinkedIn', 'datatype' => 'text',
        'param1' => 50, 'param2' => 255, 'why' => 'full profile URL',
    ],
    'website' => [
        'name' => 'Website', 'datatype' => 'text',
        'param1' => 50, 'param2' => 255, 'why' => 'full URL',
    ],
    'facebook' => [
        'name' => 'Facebook', 'datatype' => 'text',
        'param1' => 50, 'param2' => 255, 'why' => 'full profile URL',
    ],
    'instagram' => [
        'name' => 'Instagram', 'datatype' => 'text',
        'param1' => 50, 'param2' => 255, 'why' => 'full profile URL',
    ],
    'twitter' => [
        'name' => 'Twitter', 'datatype' => 'text',
        'param1' => 50, 'param2' => 255, 'why' => 'full profile URL',
    ],
    'youtube' => [
        'name' => 'YouTube', 'datatype' => 'text',
        'param1' => 50, 'param2' => 255, 'why' => 'full channel URL',
    ],
    'awards' => [
        'name' => 'Awards', 'datatype' => 'textarea',
        'why' => 'long form',
    ],
    'yearsofexperience' => [
        'name' => 'Years of experience', 'datatype' => 'text',
        'param1' => 6, 'param2' => 3, 'why' => 'numeric - core text field, max 3 chars',
    ],
    'resume' => [
        'name' => 'Resume', 'datatype' => 'text',
        'param1' => 60, 'param2' => 1333,
        'why' => 'URL - core has no file-upload profile field',
    ],
];

/*
 * Requested fields that are NOT created, with the reason. Reported so a run is
 * auditable against the original list rather than silently dropping fourteen rows.
 */
$notcreated = [
    'Full Name'     => 'core field - firstname + lastname',
    'E-mail'        => 'core field - email (already unique site-wide)',
    'Phone'         => "custom field 'phone' already exists",
    'Country'       => 'core field - country',
    'Nationality'   => "custom field 'nationality' already exists",
    'Photo'         => 'core user picture',
    'Gender'        => "custom field 'gender' already exists",
    'Date of Birth' => "custom field 'dateofbirth' already exists",
    'Job Title'     => "custom field 'jobtitle' already exists",
    'Company'       => "custom field 'company' already exists",
    'Industry'      => "custom field 'industry' already exists",
    'Education'     => "custom field 'education' already exists",
    'National ID'   => "custom field 'nationalid' already exists",
    'Passport'      => "custom field 'passport' already exists",
];

cli_heading('Instructor Fields provisioning' . ($dryrun ? ' (DRY RUN - nothing will change)' : ''));

// Existing shortnames anywhere on the site, plus the category each one sits in.
$existing = $DB->get_records_sql('
        SELECT f.shortname, c.name AS categoryname
          FROM {user_info_field} f
     LEFT JOIN {user_info_category} c ON c.id = f.categoryid');

$tocreate = [];
foreach ($fields as $shortname => $spec) {
    if (isset($existing[$shortname])) {
        $where = $existing[$shortname]->categoryname ?? 'uncategorised';
        cli_writeln(sprintf('  SKIP    %-20s already exists in "%s"', $shortname, $where));
        continue;
    }
    $tocreate[$shortname] = $spec;
}

if (!$tocreate) {
    cli_writeln("\nNothing to do - every Instructor Field already exists.");
    local_profilefields_report_skipped($notcreated);
    exit(0);
}

cli_writeln('');
foreach ($tocreate as $shortname => $spec) {
    $note = isset($spec['why']) ? '  (' . $spec['why'] . ')' : '';
    cli_writeln(sprintf('  %-7s %-20s %-9s %s%s',
        $dryrun ? 'WOULD' : 'CREATE', $shortname, $spec['datatype'], $spec['name'], $note));
}

if ($dryrun) {
    cli_writeln("\n" . count($tocreate) . ' field(s) would be created in "' . $categoryname . '".');
    cli_writeln('Re-run with --run to apply.');
    local_profilefields_report_skipped($notcreated);
    exit(0);
}

// Category first, reusing one with the same name if the admin already made it.
$categoryid = (int) $DB->get_field('user_info_category', 'id', ['name' => $categoryname]);
if ($categoryid) {
    cli_writeln("\nUsing existing category \"{$categoryname}\" (id {$categoryid}).");
} else {
    $sortorder = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {user_info_category}') + 1;
    $category = (object) ['name' => $categoryname, 'sortorder' => $sortorder];
    $category->id = $DB->insert_record('user_info_category', $category);
    \core\event\user_info_category_created::create_from_category($category)->trigger();
    $categoryid = (int) $category->id;
    cli_writeln("\nCreated category \"{$categoryname}\" (id {$categoryid}).");
}

$sortorder = (int) $DB->get_field_sql(
    'SELECT MAX(sortorder) FROM {user_info_field} WHERE categoryid = ?', [$categoryid]);

$created = 0;
foreach ($tocreate as $shortname => $spec) {
    // Re-check: the field could have appeared between the scan and now.
    if ($DB->record_exists('user_info_field', ['shortname' => $shortname])) {
        cli_writeln(sprintf('  SKIP    %-20s appeared since the scan', $shortname));
        continue;
    }

    $sortorder++;
    $record = (object) [
        'shortname'         => $shortname,
        'name'              => $spec['name'],
        'datatype'          => $spec['datatype'],
        'description'       => '',
        'descriptionformat' => FORMAT_HTML,
        'categoryid'        => $categoryid,
        'sortorder'         => $sortorder,
        // Every field on this list is optional and non-unique.
        'required'          => 0,
        'locked'            => 0,
        'visible'           => PROFILE_VISIBLE_ALL,
        'forceunique'       => 0,
        'signup'            => $signup,
        'defaultdata'       => '',
        'defaultdataformat' => FORMAT_HTML,
        'param1'            => isset($spec['param1']) ? (string) $spec['param1'] : null,
        'param2'            => isset($spec['param2']) ? (string) $spec['param2'] : null,
        // Text fields: param3 = "is password", off.
        'param3'            => $spec['datatype'] === 'text' ? '0' : null,
        'param4'            => null,
        'param5'            => null,
    ];
    $record->id = $DB->insert_record('user_info_field', $record);

    $field = $DB->get_record('user_info_field', ['id' => $record->id]);
    \core\event\user_info_field_created::create_from_field($field)->trigger();

    cli_writeln(sprintf('  OK      %-20s created (id %d)', $shortname, $record->id));
    $created++;
}

if ($created > 0) {
    profile_purge_user_fields_cache();
}

cli_writeln("\nDone. {$created} field(s) created in \"{$categoryname}\".");
local_profilefields_report_skipped($notcreated);
