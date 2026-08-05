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
 * JSON API for NIT Lessons admin actions: lesson/finance settings (US-AD-2-1) and the admin Flex
 * reversal (US-FN-1-5). Same protocol as nit_flex/api.php.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require(__DIR__ . '/../../config.php');

use local_nit_core\api\config as core_config;
use local_nit_finance\config as finance_config;
use local_nit_lessons\api\lessons;
use local_nit_lessons\exception\lesson_exception;
use local_nit_lessons\service\settings_service;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('local/nit_lessons:managesettings', $context);

header('Content-Type: application/json; charset=utf-8');

/**
 * Emit a JSON envelope and stop.
 *
 * @param array $payload
 * @return void
 */
function nit_lessons_respond(array $payload): void {
    echo json_encode($payload);
    exit;
}

$function = optional_param('function', '', PARAM_ALPHANUMEXT);
$ispost = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

try {
    if (in_array($function, ['update_settings', 'reverse_flex'], true)) {
        if (!$ispost) {
            throw new lesson_exception('err_postrequired');
        }
        require_sesskey();
    }

    switch ($function) {
        case 'get_settings':
            $service = new settings_service();
            $data = $service->get_all();
            $data['lesson_start_reminder_minutes'] = (string) core_config::for_plugin('local_nit_lessons')
                ->get_string('lesson_start_reminder_minutes', '15');
            $data['teacher_percent'] = finance_config::teacher_percent();
            $data['platform_percent'] = finance_config::platform_percent();
            nit_lessons_respond(['status' => 'success', 'data' => $data]);
            break;

        case 'update_settings':
            $teacherpercent = required_param('teacher_percent', PARAM_INT);
            $platformpercent = required_param('platform_percent', PARAM_INT);
            if ($teacherpercent + $platformpercent !== 100) {
                throw new lesson_exception('err_percenttotal');
            }
            $deadlines = [];
            foreach (array_keys(settings_service::DEFAULTS) as $key) {
                if (isset($_REQUEST[$key])) {
                    $deadlines[$key] = required_param($key, PARAM_INT);
                }
            }
            (new settings_service())->update($deadlines);
            core_config::for_plugin('local_nit_lessons')->set('lesson_start_reminder_minutes',
                optional_param('lesson_start_reminder_minutes', '', PARAM_TEXT));
            $financeconfig = core_config::for_plugin('local_nit_finance');
            $financeconfig->set('teacher_percent', $teacherpercent);
            $financeconfig->set('platform_percent', $platformpercent);
            nit_lessons_respond(['status' => 'success', 'data' => []]);
            break;

        case 'list_reversible_lessons':
            nit_lessons_respond(['status' => 'success',
                'data' => nit_lessons_reversible(optional_param('query', '', PARAM_TEXT))]);
            break;

        case 'reverse_flex':
            $result = lessons::reverse_flex(required_param('lessonid', PARAM_INT), $USER->id,
                required_param('reason', PARAM_TEXT));
            nit_lessons_respond(['status' => 'success', 'data' => $result]);
            break;

        default:
            throw new lesson_exception('err_unknownfunction');
    }
} catch (\Throwable $e) {
    nit_lessons_respond(['status' => 'error', 'error' => $e->getMessage()]);
}

/**
 * Lessons whose consumed Flex can still be reversed (they have an active earning).
 *
 * @param string $query optional free-text filter on subject / student / teacher name
 * @return array
 */
function nit_lessons_reversible(string $query): array {
    global $DB;
    $where = ['e.status = :active'];
    $params = ['active' => 'active'];
    $query = trim($query);
    if ($query !== '') {
        $like = '%' . $DB->sql_like_escape($query) . '%';
        $sname = $DB->sql_fullname('s.firstname', 's.lastname');
        $tname = $DB->sql_fullname('t.firstname', 't.lastname');
        $where[] = '(' . $DB->sql_like('l.subject', ':q1', false) .
            ' OR ' . $DB->sql_like($sname, ':q2', false) .
            ' OR ' . $DB->sql_like($tname, ':q3', false) . ')';
        $params['q1'] = $like;
        $params['q2'] = $like;
        $params['q3'] = $like;
    }
    $sql = "SELECT l.id, l.subject, l.confirmed_time, e.flex_value_minor,
                   s.firstname AS sfn, s.lastname AS sln, t.firstname AS tfn, t.lastname AS tln
              FROM {nit_earning} e
              JOIN {nit_lesson} l ON l.id = e.lessonid
         LEFT JOIN {user} s ON s.id = l.studentid
         LEFT JOIN {user} t ON t.id = l.teacherid
             WHERE " . implode(' AND ', $where) . "
          ORDER BY l.confirmed_time DESC, l.id DESC";
    $out = [];
    foreach ($DB->get_records_sql($sql, $params, 0, 50) as $r) {
        $out[] = [
            'id'           => (int) $r->id,
            'subject'      => $r->subject,
            'student_name' => trim($r->sfn . ' ' . $r->sln),
            'teacher_name' => trim($r->tfn . ' ' . $r->tln),
            'lesson_time'  => (int) $r->confirmed_time,
            'flex_value'   => ((int) $r->flex_value_minor) / 100,
        ];
    }
    return $out;
}
