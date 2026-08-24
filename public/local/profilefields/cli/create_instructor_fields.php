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
 * The command-line twin of the button on the profile tab of
 * /local/profilefields/manage.php - both call local_profilefields\instructor_fields,
 * so there is one definition of what an instructor field set is.
 *
 * This is data entry, not a feature: it creates on /user/profile/index.php exactly
 * what an admin would create by hand, writing to the same table and firing the
 * same events. It is idempotent - a shortname that already exists anywhere is
 * reported as SKIP and left completely alone.
 *
 * Usage (inside the container, from the directory holding admin/ and local/):
 *   php local/profilefields/cli/create_instructor_fields.php --dry-run
 *   php local/profilefields/cli/create_instructor_fields.php --run
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_profilefields\instructor_fields;

list($options, $unrecognized) = cli_get_params(
    [
        'run'     => false,
        'dry-run' => false,
        'signup'  => false,
        'help'    => false,
    ],
    ['h' => 'help', 'n' => 'dry-run']
);

if ($unrecognized) {
    cli_error('Unrecognised option: ' . implode(', ', $unrecognized));
}

if ($options['help'] || (!$options['run'] && !$options['dry-run'])) {
    cli_writeln("Create the Instructor Fields custom profile fields.\n");
    cli_writeln('  --dry-run, -n       show what would be created, change nothing');
    cli_writeln('  --run               actually create the missing fields');
    cli_writeln('  --signup            also show them on the sign-up form (default: off)');
    cli_writeln("  --help, -h          this help\n");
    exit(0);
}

$dryrun = !$options['run'];
$signup = $options['signup'] ? 1 : 0;
$categoryname = instructor_fields::category_name();

cli_heading('Instructor Fields provisioning' . ($dryrun ? ' (DRY RUN - nothing will change)' : ''));

if (!instructor_fields::file_available()) {
    cli_problem('profilefield_file is not installed - "Cover image" and "Resume" '
        . 'will be created as URL text fields instead of real uploads.');
    cli_writeln('');
}

// Report what is already there before touching anything.
foreach (instructor_fields::existing() as $shortname => $category) {
    cli_writeln(sprintf('  SKIP    %-20s already exists in "%s"',
        $shortname, $category !== '' ? $category : 'uncategorised'));
}

$missing = instructor_fields::missing();

if (!$missing) {
    cli_writeln("\nNothing to do - every Instructor Field already exists.");
    local_profilefields_report_skipped();
    exit(0);
}

$specs = instructor_fields::fields();
cli_writeln('');
foreach ($missing as $shortname) {
    cli_writeln(sprintf('  %-7s %-20s %-9s %s',
        $dryrun ? 'WOULD' : 'CREATE', $shortname, $specs[$shortname]['datatype'], $specs[$shortname]['name']));
}

if ($dryrun) {
    cli_writeln("\n" . count($missing) . ' field(s) would be created in "' . $categoryname . '".');
    cli_writeln('Re-run with --run to apply.');
    local_profilefields_report_skipped();
    exit(0);
}

$created = instructor_fields::run($signup);

cli_writeln("\nDone. {$created} field(s) created in \"{$categoryname}\".");
local_profilefields_report_skipped();

/**
 * Print the requested fields that are intentionally never created.
 *
 * @return void
 */
function local_profilefields_report_skipped(): void {
    cli_writeln("\nRequested but NOT created (already covered elsewhere):");
    foreach (instructor_fields::not_created() as $label => $reason) {
        cli_writeln(sprintf('  %-16s %s', $label, $reason));
    }
}
