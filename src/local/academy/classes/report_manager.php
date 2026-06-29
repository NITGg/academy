<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Admin reporting over the Flex platform's own data (Phase 4).
 *
 * Stories: US-AD-3-1 (lessons & attendance), US-AD-3-2 (platform earnings), US-AD-3-3 (package & flex),
 * US-AD-3-4 (student flex balance + history). Reports cover this plugin's data
 * (academy_lessons / earnings / flex_tx / purchases / payments); Moodle course/login-history reports
 * are out of scope for this plugin.
 *
 * All methods are admin-only (gated by local/academy:manageplatform at the API layer). Filters are an
 * assoc array; recognised keys per method are documented inline. CSV export reuses these same methods.
 */
class report_manager {

    // ──────────────────────────────────────────────────────────────────────────
    // US-AD-3-1: lessons & attendance
    // ──────────────────────────────────────────────────────────────────────────

    /** @param array $filters teacherid, studentid, status, from, to (unix, on effective time) */
    public static function lessons_report(array $filters = array()) {
        global $DB;
        list($where, $params) = self::lesson_where($filters);
        $sql = "SELECT l.*, s.firstname AS sfn, s.lastname AS sln, t.firstname AS tfn, t.lastname AS tln
                  FROM {academy_lessons} l
             LEFT JOIN {user} s ON s.id = l.studentid
             LEFT JOIN {user} t ON t.id = l.teacherid
                 $where
              ORDER BY l.timecreated DESC";
        $rows = $DB->get_records_sql($sql, $params);

        $out = array();
        $counts = array();
        foreach ($rows as $r) {
            $out[] = array(
                'id'             => (int)$r->id,
                'student_name'   => trim($r->sfn . ' ' . $r->sln),
                'teacher_name'   => trim($r->tfn . ' ' . $r->tln),
                'subject'        => $r->subject,
                'status'         => $r->status,
                'requested_time' => (int)$r->requested_time,
                'confirmed_time' => (int)$r->confirmed_time,
                'flex_state'     => $r->flex_state,
                'timecreated'    => (int)$r->timecreated,
            );
            $counts[$r->status] = ($counts[$r->status] ?? 0) + 1;
        }
        $completed = $counts['completed'] ?? 0;
        $sabs = $counts['student_absent'] ?? 0;
        $tabs = $counts['teacher_absent'] ?? 0;
        $held = $completed + $sabs + $tabs; // lessons that reached their scheduled time
        $summary = array(
            'total'           => count($out),
            'by_status'       => $counts,
            'completed'       => $completed,
            'student_absent'  => $sabs,
            'teacher_absent'  => $tabs,
            'attendance_rate' => $held > 0 ? round($completed * 100 / $held, 1) : 0,
        );
        return array('rows' => $out, 'summary' => $summary);
    }

    /**
     * US-AD-3-1: decrypted action timeline for one lesson (audit trail).
     *
     * Returns every student/teacher/system action recorded against the lesson with its time
     * decrypted for display. See {@see audit_manager} and docs/specs/00-overview.md.
     *
     * @param int $lessonid
     * @return array ['lesson' => [...], 'events' => [...]]
     */
    public static function lesson_events_report($lessonid) {
        global $DB;
        $lesson = $DB->get_record('academy_lessons', array('id' => $lessonid));
        if (!$lesson) {
            throw new \moodle_exception('err_lessonnotfound', 'local_academy');
        }
        $student = $DB->get_record('user', array('id' => $lesson->studentid), 'id, firstname, lastname');
        $teacher = $DB->get_record('user', array('id' => $lesson->teacherid), 'id, firstname, lastname');
        return array(
            'lesson' => array(
                'id'           => (int)$lesson->id,
                'student_name' => $student ? trim($student->firstname . ' ' . $student->lastname) : '',
                'teacher_name' => $teacher ? trim($teacher->firstname . ' ' . $teacher->lastname) : '',
                'subject'      => $lesson->subject,
                'status'       => $lesson->status,
            ),
            'events' => audit_manager::get_timeline($lessonid),
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-AD-3-2: platform earnings
    // ──────────────────────────────────────────────────────────────────────────

    /** @param array $filters teacherid, status (active|reversed), from, to (unix, on earning time) */
    public static function platform_earnings_report(array $filters = array()) {
        global $DB;
        $where = 'WHERE 1=1';
        $params = array();
        if (!empty($filters['teacherid'])) { $where .= ' AND e.teacherid = :tid'; $params['tid'] = $filters['teacherid']; }
        if (!empty($filters['status']))    { $where .= ' AND e.status = :st';     $params['st']  = $filters['status']; }
        if (!empty($filters['from']))      { $where .= ' AND e.timecreated >= :from'; $params['from'] = $filters['from']; }
        if (!empty($filters['to']))        { $where .= ' AND e.timecreated <= :to';   $params['to']   = $filters['to']; }

        $sql = "SELECT e.*, t.firstname AS tfn, t.lastname AS tln, s.firstname AS sfn, s.lastname AS sln,
                       l.confirmed_time
                  FROM {academy_earnings} e
             LEFT JOIN {user} t ON t.id = e.teacherid
             LEFT JOIN {user} s ON s.id = e.studentid
             LEFT JOIN {academy_lessons} l ON l.id = e.lessonid
                 $where
              ORDER BY e.timecreated DESC";
        $rows = $DB->get_records_sql($sql, $params);

        $out = array();
        $totplatform = 0; $totteacher = 0; $totflex = 0; $completed = 0;
        foreach ($rows as $r) {
            $out[] = array(
                'lessonid'        => (int)$r->lessonid,
                'teacher_name'    => trim($r->tfn . ' ' . $r->tln),
                'student_name'    => trim($r->sfn . ' ' . $r->sln),
                'lesson_time'     => (int)($r->confirmed_time ?? 0),
                'flex_value'      => (float)$r->flex_value,
                'platform_percent'=> (int)$r->platform_percent,
                'platform_amount' => (float)$r->platform_amount,
                'teacher_amount'  => (float)$r->teacher_amount,
                'status'          => $r->status,
                'timecreated'     => (int)$r->timecreated,
            );
            if ($r->status === finance_manager::EARNING_ACTIVE) {
                $totplatform += (float)$r->platform_amount;
                $totteacher  += (float)$r->teacher_amount;
                $totflex     += (float)$r->flex_value;
                $completed++;
            }
        }
        $summary = array(
            'total_platform_earnings' => round($totplatform, 2),
            'total_teacher_earnings'  => round($totteacher, 2),
            'total_consumed_value'    => round($totflex, 2),
            'completed_lessons'       => $completed,
        );
        return array('rows' => $out, 'summary' => $summary);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-AD-3-3: package & flex
    // ──────────────────────────────────────────────────────────────────────────

    /** @param array $filters studentid, source (online|admin_assigned), from, to (unix) */
    public static function package_flex_report(array $filters = array()) {
        global $DB;
        $where = 'WHERE 1=1';
        $params = array();
        if (!empty($filters['studentid'])) { $where .= ' AND pu.userid = :uid'; $params['uid'] = $filters['studentid']; }
        if (!empty($filters['source']))    { $where .= ' AND pu.source = :src'; $params['src'] = $filters['source']; }
        if (!empty($filters['from']))      { $where .= ' AND pu.timecreated >= :from'; $params['from'] = $filters['from']; }
        if (!empty($filters['to']))        { $where .= ' AND pu.timecreated <= :to';   $params['to']   = $filters['to']; }

        $sql = "SELECT pu.*, p.name AS package_name, u.firstname, u.lastname
                  FROM {academy_package_purchases} pu
             LEFT JOIN {academy_packages} p ON p.id = pu.packageid
             LEFT JOIN {user} u ON u.id = pu.userid
                 $where
              ORDER BY pu.timecreated DESC";
        $rows = $DB->get_records_sql($sql, $params);

        $out = array();
        $totsales = 0; $online = 0; $assigned = 0; $flexadded = 0; $flexconsumed = 0;
        foreach ($rows as $r) {
            $out[] = array(
                'id'             => (int)$r->id,
                'student_name'   => trim($r->firstname . ' ' . $r->lastname),
                'package_name'   => $r->package_name,
                'source'         => $r->source,
                'price_paid'     => (float)$r->price_paid,
                'flex_count'     => (int)$r->flex_count,
                'remaining_flex' => (int)$r->remaining_flex,
                'reserved_flex'  => (int)$r->reserved_flex,
                'consumed_flex'  => (int)$r->consumed_flex,
                'status'         => purchase_manager::effective_status($r),
                'timecreated'    => (int)$r->timecreated,
            );
            $totsales += (float)$r->price_paid;
            if ($r->source === 'admin_assigned') { $assigned++; } else { $online++; }
            $flexadded    += (int)$r->flex_count;
            $flexconsumed += (int)$r->consumed_flex;
        }

        // Flex movements (ledger) + reversals, honouring the same date window if given.
        $txwhere = '1=1'; $txparams = array();
        if (!empty($filters['from'])) { $txwhere .= ' AND timecreated >= :from'; $txparams['from'] = $filters['from']; }
        if (!empty($filters['to']))   { $txwhere .= ' AND timecreated <= :to';   $txparams['to']   = $filters['to']; }
        $returned = (int)$DB->count_records_select('academy_flex_tx', "type = 'return' AND $txwhere", $txparams);
        $reversed = (int)$DB->count_records_select('academy_earnings', "status = 'reversed'", array());

        $summary = array(
            'total_purchases'     => count($out),
            'total_sales_amount'  => round($totsales, 2),
            'online_count'        => $online,
            'assigned_count'      => $assigned,
            'total_flex_added'    => $flexadded,
            'total_flex_consumed' => $flexconsumed,
            'total_flex_returned' => $returned,
            'reversals'           => $reversed,
        );
        return array('rows' => $out, 'summary' => $summary);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-AD-3-4: student flex balance + history
    // ──────────────────────────────────────────────────────────────────────────

    public static function student_flex_report($studentid) {
        global $DB;
        $student = $DB->get_record('user', array('id' => $studentid), 'id, firstname, lastname, email');
        if (!$student) {
            throw new \moodle_exception('err_studentnotfound', 'local_academy');
        }
        $active = purchase_manager::get_active_purchase($studentid);
        $balance = array(
            'available_flex' => $active ? (int)$active->remaining_flex : 0,
            'reserved_flex'  => $active ? (int)$active->reserved_flex : 0,
            'consumed_flex'  => $active ? (int)$active->consumed_flex : 0,
            'active_package' => null,
            'expires_at'     => $active ? (int)$active->expires_at : 0,
        );
        if ($active) {
            $pkg = $DB->get_record('academy_packages', array('id' => $active->packageid), 'name');
            $balance['active_package'] = $pkg ? $pkg->name : null;
        }

        // Full ledger with package + lesson + performer context.
        $sql = "SELECT x.*, p.name AS package_name, pf.firstname AS pfn, pf.lastname AS pln
                  FROM {academy_flex_tx} x
             LEFT JOIN {academy_package_purchases} pu ON pu.id = x.purchaseid
             LEFT JOIN {academy_packages} p ON p.id = pu.packageid
             LEFT JOIN {user} pf ON pf.id = x.performedby
                 WHERE x.userid = :uid
              ORDER BY x.timecreated DESC, x.id DESC";
        $rows = $DB->get_records_sql($sql, array('uid' => $studentid));
        $history = array();
        foreach ($rows as $r) {
            $history[] = array(
                'id'             => (int)$r->id,
                'type'           => $r->type,
                'amount'         => (int)$r->amount,
                'balance_before' => (int)$r->balance_before,
                'balance_after'  => (int)$r->balance_after,
                'package'        => $r->package_name,
                'lessonid'       => (int)$r->lessonid,
                'performed_by'   => trim(($r->pfn ?? '') . ' ' . ($r->pln ?? '')),
                'reason'         => $r->reason,
                'timecreated'    => (int)$r->timecreated,
            );
        }

        return array(
            'student' => array(
                'id'    => (int)$student->id,
                'name'  => trim($student->firstname . ' ' . $student->lastname),
                'email' => $student->email,
            ),
            'balance' => $balance,
            'history' => $history,
        );
    }

    // ── helpers ──

    private static function lesson_where(array $filters) {
        $where = 'WHERE 1=1';
        $params = array();
        if (!empty($filters['teacherid'])) { $where .= ' AND l.teacherid = :tid'; $params['tid'] = $filters['teacherid']; }
        if (!empty($filters['studentid'])) { $where .= ' AND l.studentid = :sid'; $params['sid'] = $filters['studentid']; }
        if (!empty($filters['status']))    { $where .= ' AND l.status = :status'; $params['status'] = $filters['status']; }
        // Date window on the effective time (confirmed if set, else requested).
        if (!empty($filters['from'])) {
            $where .= ' AND (CASE WHEN l.confirmed_time > 0 THEN l.confirmed_time ELSE l.requested_time END) >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where .= ' AND (CASE WHEN l.confirmed_time > 0 THEN l.confirmed_time ELSE l.requested_time END) <= :to';
            $params['to'] = $filters['to'];
        }
        return array($where, $params);
    }
}
