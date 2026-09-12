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
 * The single endpoint behind every financial-report panel.
 *
 * One endpoint for all five pages, because they render one report. `format=csv` answers the
 * same question as a download: the same query under the same filters, without the paging, since
 * "for reporting" means the whole list has to be gettable and not just the first screenful.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_nit_finance\report\revenue;

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/nit_finance:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/nit_finance/report_ajax.php'));

$format = optional_param('format', 'json', PARAM_ALPHA);

// A date input gives a day, not an instant. "From" means the start of that day and "to" means
// the end of it, or a report run on one day would show nothing bought that same day.
$from = optional_param('from', '', PARAM_RAW_TRIMMED);
$to = optional_param('to', '', PARAM_RAW_TRIMMED);

$filters = [
    'scope'    => optional_param('scope', revenue::SCOPE_ALL, PARAM_ALPHA),
    'state'    => optional_param('state', revenue::STATE_PAID, PARAM_ALPHA),
    'from'     => local_nit_finance_day_start($from),
    'to'       => local_nit_finance_day_end($to),
    'q'        => optional_param('q', '', PARAM_TEXT),
    'currency' => optional_param('currency', '', PARAM_ALPHA),
    'itemid'   => optional_param('itemid', 0, PARAM_INT),
    'source'   => optional_param('source', '', PARAM_TEXT),
    'page'     => optional_param('page', 0, PARAM_INT),
    'perpage'  => optional_param('perpage', revenue::PER_PAGE, PARAM_INT),
];

if ($format === 'csv') {
    require_once($CFG->libdir . '/csvlib.class.php');

    $export = revenue::all_rows($filters);

    $csv = new csv_export_writer();
    $csv->set_filename('financial-report-' . $filters['scope'] . '-' . userdate(time(), '%Y-%m-%d'));
    $csv->add_data([
        get_string('rep_col_date', 'local_nit_finance'),
        get_string('rep_col_order', 'local_nit_finance'),
        get_string('rep_col_user', 'local_nit_finance'),
        get_string('email'),
        get_string('rep_col_item', 'local_nit_finance'),
        get_string('rep_col_source', 'local_nit_finance'),
        get_string('rep_col_detail', 'local_nit_finance'),
        get_string('rep_col_gross', 'local_nit_finance'),
        get_string('rep_col_discount', 'local_nit_finance'),
        get_string('rep_col_net', 'local_nit_finance'),
        get_string('rep_col_lost', 'local_nit_finance'),
        get_string('rep_col_refunded', 'local_nit_finance'),
        get_string('rep_col_earned', 'local_nit_finance'),
        get_string('currency'),
        get_string('rep_col_status', 'local_nit_finance'),
    ]);
    foreach ($export['rows'] as $row) {
        $csv->add_data([
            userdate($row['time']),
            $row['order'],
            $row['user'],
            $row['email'],
            $row['item'],
            $row['sourcelabel'],
            $row['detail'],
            number_format($row['gross'], 2, '.', ''),
            number_format($row['discount'], 2, '.', ''),
            number_format($row['net'], 2, '.', ''),
            number_format($row['lost'], 2, '.', ''),
            number_format($row['refunded'], 2, '.', ''),
            number_format($row['earned'], 2, '.', ''),
            $row['currency'],
            $row['statuslabel'],
        ]);
    }
    $csv->download_file();
    exit;
}

$report = revenue::run($filters);
$report['status'] = 'success';

// Money is formatted here, once, so every screen that shows it agrees on the separators.
$moneykeys = ['gross', 'discount', 'net', 'lost', 'refunded', 'earned'];
foreach (array_merge($moneykeys, ['average']) as $key) {
    $report['kpis'][$key . 'f'] = revenue::money((float) $report['kpis'][$key]);
}
foreach ($report['breakdown'] as &$group) {
    foreach ($moneykeys as $key) {
        $group[$key . 'f'] = revenue::money((float) $group[$key]);
    }
}
unset($group);
foreach ($report['rows'] as &$row) {
    foreach ($moneykeys as $key) {
        $row[$key . 'f'] = revenue::money((float) $row[$key]);
    }
}
unset($row);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($report);
die();

/**
 * Midnight at the start of a yyyy-mm-dd day, in the site's timezone.
 *
 * @param string $day
 * @return int 0 when no day was given
 */
function local_nit_finance_day_start(string $day): int {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        return 0;
    }
    return (int) (new DateTimeImmutable($day . ' 00:00:00', core_date::get_user_timezone_object()))->getTimestamp();
}

/**
 * The last second of a yyyy-mm-dd day, in the site's timezone.
 *
 * @param string $day
 * @return int 0 when no day was given
 */
function local_nit_finance_day_end(string $day): int {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        return 0;
    }
    return (int) (new DateTimeImmutable($day . ' 23:59:59', core_date::get_user_timezone_object()))->getTimestamp();
}
