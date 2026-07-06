<?php
// CSV export for Flex platform reports (US-AD-3-1..3-4, US-TR-2-1).
// Usage: export.php?type=NAME&token=TOKEN[&filters]. Outputs text/csv as a download.
// Admin types (manageplatform): lessons, platform_earnings, packages, student_flex (needs studentid).
// Teacher types (own data): my_earnings, my_withdrawals.

require_once('../../config.php');
require_once($CFG->dirroot . '/webservice/lib.php');

use local_academy\report_manager;
use local_academy\finance_manager;

$type  = optional_param('type', '', PARAM_ALPHANUMEXT);
$token = optional_param('token', '', PARAM_TEXT);

// Optional ?alang=en|ar — resolve multilang content in the exported rows to the requested language.
// We read 'alang' (not 'lang') so this download can't reset the user's session language via core
// setup.php (which writes $SESSION->lang from any ?lang GET param). We also force it for THIS request
// only and restore it before finishing (academy_export_restore_lang()): a persisted
// $SESSION->forcelang would leak into the next page load and override the navbar language switch
// (highest priority in current_language()). Falls back to 'lang' for any legacy caller.
$lang = optional_param('alang', optional_param('lang', '', PARAM_SAFEDIR), PARAM_SAFEDIR);
$canforcelang = ($lang !== '' && get_string_manager()->translation_exists($lang));
$GLOBALS['academy_prev_forcelang'] = isset($SESSION->forcelang) ? $SESSION->forcelang : null;
if ($canforcelang) {
    $SESSION->forcelang = $lang;
}

/** Restore the session language override set above, so it doesn't leak to later page loads. */
function academy_export_restore_lang() {
    global $SESSION;
    if (array_key_exists('academy_prev_forcelang', $GLOBALS)) {
        if ($GLOBALS['academy_prev_forcelang'] === null) {
            unset($SESSION->forcelang);
        } else {
            $SESSION->forcelang = $GLOBALS['academy_prev_forcelang'];
        }
    }
}

// Authenticate via web-service token (same pattern as api.php).
$api = new webservice();
try {
    $authresult = json_decode(json_encode($api->authenticate_user($token)), true);
    $userid = $authresult['user']['id'] ?? 0;
} catch (Exception $e) {
    $userid = 0;
}
if (empty($userid)) {
    academy_export_restore_lang();
    header('HTTP/1.1 401 Unauthorized');
    echo 'Authentication required';
    exit;
}

$admintypes = array('lessons', 'platform_earnings', 'packages', 'student_flex');
if (in_array($type, $admintypes, true) && !has_capability('local/academy:manageplatform', context_system::instance())) {
    academy_export_restore_lang();
    header('HTTP/1.1 403 Forbidden');
    echo 'Permission denied';
    exit;
}

// Filters shared with the JSON reports.
$filters = array();
foreach (array('teacherid', 'studentid', 'from', 'to') as $k) {
    $v = optional_param($k, 0, PARAM_INT);
    if ($v) { $filters[$k] = $v; }
}
foreach (array('status', 'source') as $k) {
    $v = optional_param($k, '', PARAM_ALPHANUMEXT);
    if ($v !== '') { $filters[$k] = $v; }
}

/** Format a unix time for CSV (or empty). */
function academy_csv_date($ts) {
    return $ts ? userdate($ts, '%Y-%m-%d %H:%M') : '';
}

// Build [header[], rows[][]] per report type.
$header = array();
$data = array();

switch ($type) {
    case 'lessons':
        $r = report_manager::lessons_report($filters);
        $header = array('Lesson ID', 'Student', 'Teacher', 'Subject', 'Status', 'Requested', 'Confirmed', 'Flex state');
        foreach ($r['rows'] as $row) {
            $data[] = array($row['id'], $row['student_name'], $row['teacher_name'], $row['subject'],
                $row['status'], academy_csv_date($row['requested_time']), academy_csv_date($row['confirmed_time']),
                $row['flex_state']);
        }
        break;

    case 'platform_earnings':
        $r = report_manager::platform_earnings_report($filters);
        $header = array('Lesson ID', 'Teacher', 'Student', 'Lesson date', 'Flex value', 'Platform %',
            'Platform amount', 'Teacher amount', 'Status', 'Recorded');
        foreach ($r['rows'] as $row) {
            $data[] = array($row['lessonid'], $row['teacher_name'], $row['student_name'],
                academy_csv_date($row['lesson_time']), $row['flex_value'], $row['platform_percent'],
                $row['platform_amount'], $row['teacher_amount'], $row['status'], academy_csv_date($row['timecreated']));
        }
        break;

    case 'packages':
        $r = report_manager::package_flex_report($filters);
        $header = array('Purchase ID', 'Student', 'Package', 'Source', 'Price paid', 'Flex', 'Remaining',
            'Reserved', 'Consumed', 'Status', 'Date');
        foreach ($r['rows'] as $row) {
            $data[] = array($row['id'], $row['student_name'], $row['package_name'], $row['source'],
                $row['price_paid'], $row['flex_count'], $row['remaining_flex'], $row['reserved_flex'],
                $row['consumed_flex'], $row['status'], academy_csv_date($row['timecreated']));
        }
        break;

    case 'student_flex':
        $studentid = required_param('studentid', PARAM_INT);
        $r = report_manager::student_flex_report($studentid);
        $header = array('Date', 'Type', 'Amount', 'Balance before', 'Balance after', 'Package', 'Lesson',
            'Performed by', 'Reason');
        foreach ($r['history'] as $row) {
            $data[] = array(academy_csv_date($row['timecreated']), $row['type'], $row['amount'],
                $row['balance_before'], $row['balance_after'], $row['package'],
                $row['lessonid'] ?: '', $row['performed_by'], $row['reason']);
        }
        break;

    case 'my_earnings':
        $rows = finance_manager::list_earnings($userid);
        $header = array('Lesson ID', 'Student', 'Lesson date', 'Flex value', 'Teacher %', 'Earning', 'Status', 'Recorded');
        foreach ($rows as $row) {
            $data[] = array($row['lessonid'], $row['student_name'], academy_csv_date($row['lesson_time']),
                $row['flex_value'], $row['teacher_percent'], $row['teacher_amount'], $row['status'],
                academy_csv_date($row['timecreated']));
        }
        break;

    case 'my_withdrawals':
        $rows = finance_manager::get_my_withdrawals($userid);
        $header = array('Request date', 'Amount', 'Method', 'Status', 'Reference', 'Reason', 'Processed');
        foreach ($rows as $row) {
            $data[] = array(academy_csv_date($row['timecreated']), $row['amount'], $row['method'],
                $row['status'], $row['reference'], $row['reason'], academy_csv_date($row['timeprocessed']));
        }
        break;

    default:
        academy_export_restore_lang();
        header('HTTP/1.1 400 Bad Request');
        echo 'Unknown report type';
        exit;
}

// Report rows are built; restore the real session language before emitting/exiting.
academy_export_restore_lang();

// Emit CSV.
$filename = 'academy_' . $type . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Arabic names correctly
fputcsv($out, $header);
foreach ($data as $line) {
    fputcsv($out, $line);
}
fclose($out);
exit;
