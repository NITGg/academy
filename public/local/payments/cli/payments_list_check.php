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
 * Diagnostic: run the All payments / Course payments query and show what breaks.
 *
 * The web page can only say "Error reading from database" — Moodle hides the
 * driver's message from users on purpose. This runs the same query the page
 * builds and prints the real one, plus the SQL that produced it.
 *
 * Usage (from the Moodle root, or via docker):
 *   php public/local/payments/cli/payments_list_check.php
 *   php public/local/payments/cli/payments_list_check.php --courseid=9
 *   php public/local/payments/cli/payments_list_check.php --reset-prefs
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['help' => false, 'courseid' => 0, 'sort' => '', 'reset-prefs' => false],
    ['h' => 'help']
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognised)));
}

if ($options['help']) {
    cli_writeln("Run the payments list query and report what the database says.

Options:
  --courseid=N    Scope to one course, as the course view does.
  --sort=COLUMN   Try a specific ORDER BY column (default paidon).
  --reset-prefs   Clear every saved sort/paging preference for this table. A
                  preference saved under an older column name outlives the code
                  change that renamed it, and produces an ORDER BY on a column
                  that no longer exists.
  -h, --help      Print this help.
");
    exit(0);
}

if ($options['reset-prefs']) {
    // flexible_table stores prefs per user under this name.
    $count = $DB->count_records_select('user_preferences', $DB->sql_like('name', ':n'),
        ['n' => 'flextable_local_payments%']);
    $DB->delete_records_select('user_preferences', $DB->sql_like('name', ':n'),
        ['n' => 'flextable_local_payments%']);
    cli_writeln("Cleared {$count} saved table preference(s).");
    cli_writeln('');
}

$courseid = (int) $options['courseid'];
$sort = $options['sort'] ?: 'paidon';

cli_writeln('Payments list query check');
cli_writeln('  scope : ' . ($courseid ? "course {$courseid}" : 'all courses'));
cli_writeln('  sort  : ' . $sort);
cli_writeln('');

// Tables the page depends on. A missing one is reported as a database read
// error too, and that is a very different fix from a broken query.
foreach (['local_payments_transactions', 'local_payments_providers',
        'local_payments_refund_reqs', 'local_payments_refunds'] as $table) {
    $exists = $DB->get_manager()->table_exists($table);
    cli_writeln(sprintf('  %-32s %s', $table, $exists ? 'present' : 'MISSING — run admin/cli/upgrade.php'));
}
cli_writeln('');

[$fields, $from, $where, $params] = \local_payments\output\transactions_table::build_query($courseid);

$countsql = "SELECT COUNT(1) FROM {$from} WHERE {$where}";
$listsql = "SELECT {$fields} FROM {$from} WHERE {$where} ORDER BY {$sort} DESC";

$run = static function (string $label, callable $fn, string $sql) {
    cli_writeln($label);
    try {
        $result = $fn();
        cli_writeln('  OK — ' . $result);
    } catch (\Throwable $e) {
        cli_writeln('  FAILED');
        cli_writeln('  message : ' . $e->getMessage());
        if (!empty($e->debuginfo)) {
            cli_writeln('  driver  : ' . $e->debuginfo);
        }
        cli_writeln('  sql     : ' . preg_replace('/\s+/', ' ', $sql));
    }
    cli_writeln('');
};

$run('Count query', static function () use ($DB, $countsql, $params) {
    return $DB->count_records_sql($countsql, $params) . ' rows';
}, $countsql);

$run('List query (first 5)', static function () use ($DB, $listsql, $params) {
    $rows = $DB->get_records_sql($listsql, $params, 0, 5);
    return count($rows) . ' rows read';
}, $listsql);

exit(0);
