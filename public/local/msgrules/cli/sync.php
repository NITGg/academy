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
 * Apply the messaging rules from the command line.
 *
 * The management screen queues a rebuild for cron, which is right for a web request but wrong
 * when you are standing at a terminal wanting to see the result. This runs it in the
 * foreground, and --check answers "would this pair be allowed" without writing anything -
 * the fastest way to tell a rule that is wrong from a rule that has not been applied yet.
 *
 * @package    local_msgrules
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_msgrules\rules;
use local_msgrules\sync;

[$options, $unrecognised] = cli_get_params(
    [
        'help'    => false,
        'rebuild' => false,
        'user'    => 0,
        'check'   => '',
        'status'  => false,
    ],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help'] || (!$options['rebuild'] && !$options['user'] && !$options['check'] && !$options['status'])) {
    echo <<<EOT
Apply or inspect the administrator-controlled messaging rules.

Options:
  --status           Show whether the rules are in force and how many conversations they close.
  --rebuild          Re-derive every block row from the matrix, in the foreground.
  --user=ID          Re-derive only the pairs the given account takes part in.
  --check=FROM,TO    Report whether account FROM may currently message account TO. Writes nothing.
  -h, --help         Print this help.

Example:
  php local/msgrules/cli/sync.php --rebuild
  php local/msgrules/cli/sync.php --check=42,7

EOT;
    exit(0);
}

if ($options['status']) {
    cli_writeln('Rules enforced : ' . (rules::is_enabled() ? 'yes' : 'no'));
    cli_writeln('Site messaging : ' . (empty($CFG->messaging) ? 'off - nothing can be sent at all' : 'on'));
    cli_writeln('All courses    : ' . rules::describe(rules::get_default_mode()));
    $overrides = rules::get_course_modes();
    cli_writeln('Course overrides: ' . count($overrides));
    foreach ($overrides as $courseid => $mode) {
        $name = $DB->get_field('course', 'shortname', ['id' => $courseid]) ?: "(course $courseid)";
        cli_writeln("  - $name: " . rules::describe($mode));
    }
    cli_writeln('Blocks owned   : ' . sync::count_managed());
    exit(0);
}

if ($options['check'] !== '') {
    $parts = array_map('trim', explode(',', $options['check']));
    if (count($parts) != 2 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
        cli_error('--check needs two numeric account ids, for example --check=42,7');
    }
    [$senderid, $recipientid] = array_map('intval', $parts);

    // Ask core, not our own tables: the whole point of the design is that core is the one
    // making the decision, so this reports what a real send would actually do.
    $allowed = \core_message\api::can_send_message($recipientid, $senderid);
    cli_writeln(sprintf(
        'User %d -> user %d : %s (rules alone would %sblock this pair)',
        $senderid,
        $recipientid,
        $allowed ? 'ALLOWED' : 'BLOCKED',
        sync::should_block($recipientid, $senderid) ? '' : 'not '
    ));
    exit(0);
}

if ($options['user']) {
    $result = sync::sync_user((int) $options['user']);
    cli_writeln(sprintf(
        'User %d: %d blocks added, %d removed, %d left to the user.',
        (int) $options['user'],
        $result['added'],
        $result['removed'],
        $result['skipped']
    ));
    exit(0);
}

$result = sync::rebuild(new text_progress_trace());
cli_writeln(sprintf(
    'Done: %d restricted students, %d blocks added, %d removed, %d left to the user.',
    $result['students'],
    $result['added'],
    $result['removed'],
    $result['skipped']
));
