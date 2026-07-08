<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Financial layer (Phase 3): revenue distribution, reversal, teacher wallet, withdrawals.
 *
 * Stories: US-FN-1-4 (distribute on complete), US-FN-1-5 (admin reversal of a consumed Flex),
 * US-FN-2-1 (teacher withdrawal request), US-FN-2-2 (admin process withdrawal). Wallet model:
 * docs/specs/financial/00-wallet-model.md.
 *
 * Money model:
 *   flex_value      = purchase.price_paid / purchase.flex_count
 *   teacher_amount  = round(flex_value * teacher_percent / 100, 2)
 *   platform_amount = flex_value - teacher_amount   (so the two always sum to flex_value)
 *
 * Teacher available balance = sum(active earnings.teacher_amount)
 *                           − sum(withdrawals.amount where status in pending|approved|paid)
 * Rejected withdrawals release their reserved amount back to the balance.
 */
class finance_manager {

    const EARNING_ACTIVE   = 'active';
    const EARNING_REVERSED = 'reversed';

    const WD_PENDING  = 'pending';
    const WD_APPROVED = 'approved';
    const WD_REJECTED = 'rejected';
    const WD_PAID     = 'paid';

    // ──────────────────────────────────────────────────────────────────────────
    // US-FN-1-4: distribute revenue when a lesson is completed (called from lesson_manager)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Record the teacher/platform split for a just-completed lesson. Idempotent: if an active earning
     * already exists for the lesson, it is returned unchanged.
     *
     * @param \stdClass $lesson lesson record (must have purchaseid, teacherid, studentid)
     * @return array the earning summary
     */
    public static function distribute_for_lesson($lesson) {
        global $DB;

        $existing = $DB->get_record('academy_earnings',
            array('lessonid' => $lesson->id, 'status' => self::EARNING_ACTIVE));
        if ($existing) {
            return self::format_earning($existing);
        }

        $purchase = $DB->get_record('academy_package_purchases', array('id' => $lesson->purchaseid));
        if (!$purchase || (int)$purchase->flex_count <= 0) {
            throw new \moodle_exception('err_notdistributed', 'local_academy');
        }
        $tp = (int)settings_manager::get('teacher_percent');
        $pp = (int)settings_manager::get('platform_percent');

        $flexvalue = round((float)$purchase->price_paid / (int)$purchase->flex_count, 2);
        $teacher   = round($flexvalue * $tp / 100, 2);
        $platform  = round($flexvalue - $teacher, 2);

        $earning = (object) array(
            'lessonid'         => (int)$lesson->id,
            'teacherid'        => (int)$lesson->teacherid,
            'studentid'        => (int)$lesson->studentid,
            'purchaseid'       => (int)$lesson->purchaseid,
            'flex_value'       => $flexvalue,
            'teacher_amount'   => $teacher,
            'platform_amount'  => $platform,
            'teacher_percent'  => $tp,
            'platform_percent' => $pp,
            'status'           => self::EARNING_ACTIVE,
            'timecreated'      => time(),
        );
        $earning->id = $DB->insert_record('academy_earnings', $earning);
        return self::format_earning($earning);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-FN-1-5: admin reverses a consumed + distributed Flex
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Return a consumed Flex to the student and reverse the teacher/platform earning.
     *
     * @param int $lessonid
     * @param int $adminid
     * @param string $reason required
     * @return array result
     */
    public static function reverse_flex($lessonid, $adminid, $reason) {
        global $DB;
        $reason = trim($reason);
        if ($reason === '') {
            throw new \moodle_exception('err_reasonrequired', 'local_academy');
        }
        $lesson = $DB->get_record('academy_lessons', array('id' => $lessonid));
        if (!$lesson) {
            throw new \moodle_exception('err_lessonnotfound', 'local_academy');
        }
        $earning = $DB->get_record('academy_earnings',
            array('lessonid' => $lessonid, 'status' => self::EARNING_ACTIVE));
        if (!$earning) {
            // Either never distributed, or already reversed.
            if ($DB->record_exists('academy_earnings', array('lessonid' => $lessonid, 'status' => self::EARNING_REVERSED))) {
                throw new \moodle_exception('err_alreadyreversed', 'local_academy');
            }
            throw new \moodle_exception('err_earningnotfound', 'local_academy');
        }

        $transaction = $DB->start_delegated_transaction();

        // 1. Return one Flex to the student (consumed → remaining).
        flex_manager::reverse_consumed($lesson->studentid, $lesson->purchaseid, $lesson->id, $adminid,
            'Admin reversal: ' . $reason);

        // 2. Reverse the earning (teacher & platform shares no longer count).
        $earning->status        = self::EARNING_REVERSED;
        $earning->reverse_reason = $reason;
        $earning->reversedby    = $adminid;
        $earning->timereversed  = time();
        $DB->update_record('academy_earnings', $earning);

        // 3. Mark the lesson's Flex as returned for clarity.
        $lesson->flex_state   = 'returned';
        $lesson->timemodified = time();
        $DB->update_record('academy_lessons', $lesson);

        $transaction->allow_commit();

        return array(
            'lessonid'        => (int)$lessonid,
            'reversed'        => true,
            'teacher_amount'  => (float)$earning->teacher_amount,
            'platform_amount' => (float)$earning->platform_amount,
        );
    }

    /**
     * List lessons whose Flex can still be reversed — i.e. they have an ACTIVE (not-yet-reversed)
     * earning. Powers the admin "Return Flex" picker (replaces typing a raw lesson id).
     *
     * @param string $query optional free-text filter on subject / student name / teacher name
     * @param int    $limit max rows (clamped 1..100)
     * @return array<int, array{id:int, subject:string, student_name:string, teacher_name:string, lesson_time:int, flex_value:float}>
     */
    public static function list_reversible_lessons($query = '', $limit = 50) {
        global $DB;

        $limit = max(1, min(100, (int)$limit));
        $where  = array('e.status = :active');
        $params = array('active' => self::EARNING_ACTIVE);

        $query = trim((string)$query);
        if ($query !== '') {
            $like = '%' . $DB->sql_like_escape($query) . '%';
            $namestudent = $DB->sql_fullname('s.firstname', 's.lastname');
            $nameteacher = $DB->sql_fullname('t.firstname', 't.lastname');
            $where[] = '(' . $DB->sql_like('l.subject', ':q1', false) .
                       ' OR ' . $DB->sql_like($namestudent, ':q2', false) .
                       ' OR ' . $DB->sql_like($nameteacher, ':q3', false) . ')';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $sql = "SELECT l.id, l.subject, l.confirmed_time, e.flex_value,
                       s.firstname AS sfn, s.lastname AS sln, t.firstname AS tfn, t.lastname AS tln
                  FROM {academy_earnings} e
                  JOIN {academy_lessons} l ON l.id = e.lessonid
             LEFT JOIN {user} s ON s.id = l.studentid
             LEFT JOIN {user} t ON t.id = l.teacherid
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY l.confirmed_time DESC, l.id DESC";
        $rows = $DB->get_records_sql($sql, $params, 0, $limit);

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'id'           => (int)$r->id,
                'subject'      => $r->subject,
                'student_name' => trim($r->sfn . ' ' . $r->sln),
                'teacher_name' => trim($r->tfn . ' ' . $r->tln),
                'lesson_time'  => (int)$r->confirmed_time,
                'flex_value'   => (float)$r->flex_value,
            );
        }
        return $out;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Teacher wallet
    // ──────────────────────────────────────────────────────────────────────────

    /** Wallet summary + recent earnings + withdrawals for a teacher. */
    public static function get_teacher_wallet($teacherid) {
        global $DB;

        $totalearned = (float)$DB->get_field_sql(
            "SELECT COALESCE(SUM(teacher_amount),0) FROM {academy_earnings}
              WHERE teacherid = :tid AND status = :st",
            array('tid' => $teacherid, 'st' => self::EARNING_ACTIVE));

        // Pending + approved are reserved; paid is gone.
        $reserved = (float)$DB->get_field_sql(
            "SELECT COALESCE(SUM(amount),0) FROM {academy_withdrawals}
              WHERE teacherid = :tid AND status IN ('pending','approved')",
            array('tid' => $teacherid));
        $paid = (float)$DB->get_field_sql(
            "SELECT COALESCE(SUM(amount),0) FROM {academy_withdrawals}
              WHERE teacherid = :tid AND status = 'paid'",
            array('tid' => $teacherid));

        $available = round($totalearned - $reserved - $paid, 2);

        return array(
            'teacherid'           => (int)$teacherid,
            'total_earned'        => round($totalearned, 2),
            'available_balance'   => $available,
            'pending_withdrawals' => round($reserved, 2),
            'total_withdrawn'     => round($paid, 2),
            'earnings'            => self::list_earnings($teacherid),
            'withdrawals'         => self::get_my_withdrawals($teacherid),
        );
    }

    /** Just the available balance number (used for validation). */
    public static function available_balance($teacherid) {
        $w = self::get_teacher_wallet($teacherid);
        return (float)$w['available_balance'];
    }

    /** A teacher's earnings list with student name + lesson date (most recent first). */
    public static function list_earnings($teacherid) {
        global $DB;
        $sql = "SELECT e.*, u.firstname, u.lastname, l.confirmed_time
                  FROM {academy_earnings} e
             LEFT JOIN {user} u ON u.id = e.studentid
             LEFT JOIN {academy_lessons} l ON l.id = e.lessonid
                 WHERE e.teacherid = :tid
              ORDER BY e.timecreated DESC, e.id DESC";
        $rows = $DB->get_records_sql($sql, array('tid' => $teacherid));
        $out = array();
        foreach ($rows as $r) {
            $e = self::format_earning($r);
            $e['student_name'] = trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''));
            $e['lesson_time'] = (int)($r->confirmed_time ?? 0);
            $out[] = $e;
        }
        return $out;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-FN-2-1: teacher requests a withdrawal
    // ──────────────────────────────────────────────────────────────────────────

    public static function request_withdrawal($teacherid, $amount, $method, $account) {
        global $DB;
        $amount = round((float)$amount, 2);
        if ($amount <= 0) {
            throw new \moodle_exception('err_amountpositive', 'local_academy');
        }
        $available = self::available_balance($teacherid);
        if ($amount > $available + 0.001) { // small epsilon for float comparison
            throw new \moodle_exception('err_insufficientbalance', 'local_academy');
        }
        $now = time();
        $wd = (object) array(
            'teacherid'   => (int)$teacherid,
            'amount'      => $amount,
            'method'      => $method !== '' ? $method : 'bank',
            'account'     => $account,
            'status'      => self::WD_PENDING,
            'timecreated' => $now,
        );
        $wd->id = $DB->insert_record('academy_withdrawals', $wd);
        return self::format_withdrawal($DB->get_record('academy_withdrawals', array('id' => $wd->id)));
    }

    /** A teacher's own withdrawals (most recent first). */
    public static function get_my_withdrawals($teacherid) {
        global $DB;
        $rows = $DB->get_records('academy_withdrawals', array('teacherid' => $teacherid),
            'timecreated DESC, id DESC');
        return array_map(array('self', 'format_withdrawal'), array_values($rows));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-FN-2-2: admin processes a withdrawal
    // ──────────────────────────────────────────────────────────────────────────

    /** All withdrawals (admin), optionally filtered by status, with teacher names. */
    public static function list_withdrawals($status = '') {
        global $DB;
        $params = array();
        $where = '';
        if ($status !== '') {
            $where = 'WHERE w.status = :status';
            $params['status'] = $status;
        }
        $sql = "SELECT w.*, u.firstname, u.lastname, u.email
                  FROM {academy_withdrawals} w
                  JOIN {user} u ON u.id = w.teacherid
                  $where
              ORDER BY w.timecreated DESC, w.id DESC";
        $rows = $DB->get_records_sql($sql, $params);
        $out = array();
        foreach ($rows as $r) {
            $w = self::format_withdrawal($r);
            $w['teacher_name'] = trim($r->firstname . ' ' . $r->lastname);
            $w['teacher_email'] = $r->email;
            $out[] = $w;
        }
        return $out;
    }

    /**
     * @param int $adminid
     * @param int $withdrawalid
     * @param string $action approve | reject | pay
     * @param array $opts reason (for reject), reference (for pay)
     */
    public static function process_withdrawal($adminid, $withdrawalid, $action, array $opts = array()) {
        global $DB;
        $wd = $DB->get_record('academy_withdrawals', array('id' => $withdrawalid));
        if (!$wd) {
            throw new \moodle_exception('err_withdrawalnotfound', 'local_academy');
        }
        $now = time();

        switch ($action) {
            case 'approve':
                if ($wd->status !== self::WD_PENDING) {
                    throw new \moodle_exception('err_withdrawalstate', 'local_academy');
                }
                $wd->status = self::WD_APPROVED;
                break;

            case 'reject':
                // Pending or approved requests can be rejected; the reserved amount returns to balance.
                if (!in_array($wd->status, array(self::WD_PENDING, self::WD_APPROVED), true)) {
                    throw new \moodle_exception('err_withdrawalstate', 'local_academy');
                }
                $reason = trim($opts['reason'] ?? '');
                if ($reason === '') {
                    throw new \moodle_exception('err_reasonrequired', 'local_academy');
                }
                $wd->status = self::WD_REJECTED;
                $wd->reason = $reason;
                break;

            case 'pay':
                // Only an approved request can be marked paid.
                if ($wd->status !== self::WD_APPROVED) {
                    throw new \moodle_exception('err_withdrawalstate', 'local_academy');
                }
                $wd->status = self::WD_PAID;
                $wd->reference = trim($opts['reference'] ?? '');
                break;

            default:
                throw new \moodle_exception('err_badaction', 'local_academy');
        }

        $wd->processedby   = $adminid;
        $wd->timeprocessed = $now;
        $DB->update_record('academy_withdrawals', $wd);
        return self::format_withdrawal($DB->get_record('academy_withdrawals', array('id' => $wd->id)));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Platform wallet (admin overview; full reports are Phase 4)
    // ──────────────────────────────────────────────────────────────────────────

    public static function get_platform_wallet() {
        global $DB;

        $platformearnings = (float)$DB->get_field_sql(
            "SELECT COALESCE(SUM(platform_amount),0) FROM {academy_earnings} WHERE status = :st",
            array('st' => self::EARNING_ACTIVE));
        $teacherearnings = (float)$DB->get_field_sql(
            "SELECT COALESCE(SUM(teacher_amount),0) FROM {academy_earnings} WHERE status = :st",
            array('st' => self::EARNING_ACTIVE));
        $paid = (float)$DB->get_field_sql(
            "SELECT COALESCE(SUM(amount),0) FROM {academy_withdrawals} WHERE status = 'paid'", array());
        $payments = (float)$DB->get_field_sql(
            "SELECT COALESCE(SUM(amount),0) FROM {academy_payments} WHERE status = 'success'", array());

        // Undistributed = value of Flexes not yet consumed (remaining + reserved) across all purchases.
        $undistributed = 0;
        $purchases = $DB->get_records_select('academy_package_purchases', 'flex_count > 0', array(),
            '', 'id, price_paid, flex_count, remaining_flex, reserved_flex');
        foreach ($purchases as $p) {
            $flexvalue = (float)$p->price_paid / (int)$p->flex_count;
            $undistributed += ((int)$p->remaining_flex + (int)$p->reserved_flex) * $flexvalue;
        }

        return array(
            'current_money'        => round($payments - $paid, 2),
            'undistributed_money'  => round($undistributed, 2),
            'teachers_money'       => round($teacherearnings - $paid, 2),
            'platform_earnings'    => round($platformearnings, 2),
            'total_payments'       => round($payments, 2),
            'total_paid_out'       => round($paid, 2),
        );
    }

    // ── formatters ──

    private static function format_earning($e) {
        return array(
            'id'               => (int)$e->id,
            'lessonid'         => (int)$e->lessonid,
            'teacherid'        => (int)$e->teacherid,
            'studentid'        => (int)$e->studentid,
            'flex_value'       => (float)$e->flex_value,
            'teacher_amount'   => (float)$e->teacher_amount,
            'platform_amount'  => (float)$e->platform_amount,
            'teacher_percent'  => (int)$e->teacher_percent,
            'platform_percent' => (int)$e->platform_percent,
            'status'           => $e->status,
            'reverse_reason'   => isset($e->reverse_reason) ? $e->reverse_reason : null,
            'timecreated'      => (int)$e->timecreated,
            'timereversed'     => (int)$e->timereversed,
        );
    }

    private static function format_withdrawal($w) {
        return array(
            'id'            => (int)$w->id,
            'teacherid'     => (int)$w->teacherid,
            'amount'        => (float)$w->amount,
            'method'        => $w->method,
            'account'       => $w->account,
            'reference'     => $w->reference,
            'status'        => $w->status,
            'reason'        => $w->reason,
            'processedby'   => (int)$w->processedby,
            'timeprocessed' => (int)$w->timeprocessed,
            'timecreated'   => (int)$w->timecreated,
        );
    }
}
