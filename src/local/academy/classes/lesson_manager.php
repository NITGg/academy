<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Lessons + negotiation + lifecycle (Phase 2).
 *
 * Stories: US-LS-1-1 (request), US-LS-2-1/2-2/2-3 (accept/reject/suggest), US-LS-3-1..3-4
 * (start/complete/absence), US-LS-4-1/4-2 (cancel), US-LS-5-1/5-2 (time update),
 * US-TR-1-2 / US-ST-2-2 (view lessons). Flex reserve/return goes through {@see flex_manager}.
 *
 * Status machine (docs/specs/00-overview.md):
 *   pending → (waiting_student ⇄ waiting_teacher) → confirmed → in_progress →
 *   completed | student_absent | teacher_absent | cancelled | cancelled_teacher | rejected
 */
class lesson_manager {

    const STATUS_PENDING           = 'pending';
    const STATUS_WAITING_STUDENT   = 'waiting_student';
    const STATUS_WAITING_TEACHER   = 'waiting_teacher';
    const STATUS_CONFIRMED         = 'confirmed';
    const STATUS_IN_PROGRESS       = 'in_progress';
    const STATUS_COMPLETED         = 'completed';
    const STATUS_STUDENT_ABSENT    = 'student_absent';
    const STATUS_TEACHER_ABSENT    = 'teacher_absent';
    const STATUS_CANCELLED         = 'cancelled';          // by student
    const STATUS_CANCELLED_TEACHER = 'cancelled_teacher';
    const STATUS_REJECTED          = 'rejected';           // by teacher

    const DEFAULT_DURATION = 60; // minutes (one lesson = 1 hour)

    // ──────────────────────────────────────────────────────────────────────────
    // US-LS-1-1: student requests a lesson
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param int $studentid
     * @param int $teacherid
     * @param string $subject
     * @param int $requestedtime unix start time
     * @param string $note
     * @return array lesson detail
     */
    public static function request_lesson($studentid, $teacherid, $subject, $requestedtime, $note = '') {
        global $DB;

        $subject = trim($subject);
        if ($subject === '') {
            throw new \moodle_exception('err_subjectrequired', 'local_academy');
        }
        // US-LS-1-1: the lesson note is required (not optional).
        $note = trim($note);
        if ($note === '') {
            throw new \moodle_exception('err_noterequired', 'local_academy');
        }
        if (!teacher_manager::is_teacher($teacherid)) {
            throw new \moodle_exception('err_teachernotfound', 'local_academy');
        }
        if ($teacherid == $studentid) {
            throw new \moodle_exception('err_selfbooking', 'local_academy');
        }
        // Subject must be supported by the teacher.
        if (!self::teacher_supports_subject($teacherid, $subject)) {
            throw new \moodle_exception('err_subjectunsupported', 'local_academy');
        }
        // Student must have an active package with available Flex (not reserved yet).
        $purchase = purchase_manager::get_active_purchase($studentid);
        if (!$purchase || (int)$purchase->remaining_flex < 1) {
            throw new \moodle_exception('err_noflex', 'local_academy');
        }
        // The lesson must be at least the minimum booking lead time from now.
        self::require_min_booking($requestedtime);
        self::check_time_conflict($teacherid, $requestedtime);

        $now = time();
        $lesson = (object) array(
            'studentid'      => $studentid,
            'teacherid'      => $teacherid,
            'subject'        => $subject,
            'status'         => self::STATUS_PENDING,
            'requested_time' => (int)$requestedtime,
            'confirmed_time' => 0,
            'duration'       => self::DEFAULT_DURATION,
            'note'           => $note,
            'purchaseid'     => 0,
            'flex_state'     => 'none',
            'timecreated'    => $now,
            'timemodified'   => $now,
        );
        $lesson->id = $DB->insert_record('academy_lessons', $lesson);
        audit_manager::record($lesson->id, 'requested', $studentid, 'student');
        // US-LS-1-1: notify the teacher of the new request.
        $saved = $DB->get_record('academy_lessons', array('id' => $lesson->id));
        notification_manager::lesson_event($saved, 'requested', $teacherid, $studentid);
        return self::format_lesson($saved, $studentid);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-LS-2-1 / US-LS-2-3: teacher responds (pending → ... ; waiting_teacher → ...)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param int $teacherid
     * @param int $lessonid
     * @param string $action accept | reject | suggest
     * @param array $opts suggested_time, reject_reason
     */
    public static function teacher_respond($teacherid, $lessonid, $action, array $opts = array()) {
        global $DB;
        $lesson = self::get_owned_lesson($lessonid);
        if ((int)$lesson->teacherid !== (int)$teacherid) {
            throw new \moodle_exception('err_forbidden', 'local_academy');
        }
        if (!in_array($lesson->status, array(self::STATUS_PENDING, self::STATUS_WAITING_TEACHER), true)) {
            throw new \moodle_exception('err_badstate', 'local_academy');
        }

        switch ($action) {
            case 'accept':
                // Confirm at the requested time (pending) or at the student's latest suggested time.
                $time = ($lesson->status === self::STATUS_PENDING)
                    ? (int)$lesson->requested_time
                    : self::latest_pending_proposal_time($lessonid);
                self::check_time_conflict($teacherid, $time, $lesson->duration, $lessonid);
                audit_manager::record($lessonid, 'teacher_accepted', $teacherid, 'teacher');
                $result = self::confirm_lesson($lesson, $time, $teacherid);
                // US-LS-2-1 / US-LS-2-3: tell the student the lesson is confirmed.
                notification_manager::lesson_event($lesson, 'confirmed_by_teacher', $lesson->studentid, $teacherid,
                    array('time' => $time));
                return $result;

            case 'reject':
                self::supersede_pending_proposals($lessonid);
                audit_manager::record($lessonid, 'teacher_rejected', $teacherid, 'teacher');
                $reason = trim($opts['reject_reason'] ?? '');
                $result = self::transition($lesson, self::STATUS_REJECTED, $teacherid, array(
                    'reject_reason' => $reason,
                ));
                notification_manager::lesson_event($lesson, 'rejected_by_teacher', $lesson->studentid, $teacherid,
                    array('reason' => $reason));
                return $result;

            case 'suggest':
                // Teacher can only suggest from a pending request (US-LS-2-1); from waiting_teacher the
                // teacher accepts or rejects (US-LS-2-3).
                if ($lesson->status !== self::STATUS_PENDING) {
                    throw new \moodle_exception('err_badstate', 'local_academy');
                }
                $time = self::required_future_time($opts['suggested_time'] ?? 0);
                self::check_time_conflict($teacherid, $time, $lesson->duration, $lessonid);
                self::add_proposal($lessonid, $teacherid, 'teacher', $time, 'suggest');
                audit_manager::record($lessonid, 'teacher_suggested', $teacherid, 'teacher');
                $result = self::transition($lesson, self::STATUS_WAITING_STUDENT, $teacherid);
                // US-LS-2-1: tell the student a new time was suggested.
                notification_manager::lesson_event($lesson, 'teacher_suggested', $lesson->studentid, $teacherid,
                    array('time' => $time));
                return $result;

            default:
                throw new \moodle_exception('err_badaction', 'local_academy');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-LS-2-2: student responds to a suggested time (waiting_student → ...)
    // ──────────────────────────────────────────────────────────────────────────

    public static function student_respond($studentid, $lessonid, $action, array $opts = array()) {
        global $DB;
        $lesson = self::get_owned_lesson($lessonid);
        if ((int)$lesson->studentid !== (int)$studentid) {
            throw new \moodle_exception('err_forbidden', 'local_academy');
        }
        if ($lesson->status !== self::STATUS_WAITING_STUDENT) {
            throw new \moodle_exception('err_badstate', 'local_academy');
        }

        switch ($action) {
            case 'accept':
                $time = self::latest_pending_proposal_time($lessonid);
                self::check_time_conflict($lesson->teacherid, $time, $lesson->duration, $lessonid);
                audit_manager::record($lessonid, 'student_accepted', $studentid, 'student');
                $result = self::confirm_lesson($lesson, $time, $studentid);
                // US-LS-2-2: tell the teacher the student accepted the suggested time.
                notification_manager::lesson_event($lesson, 'confirmed_by_student', $lesson->teacherid, $studentid,
                    array('time' => $time));
                return $result;

            case 'reject':
                // Student declines the suggested time — negotiation ends (no Flex was reserved).
                self::supersede_pending_proposals($lessonid);
                audit_manager::record($lessonid, 'student_rejected', $studentid, 'student');
                $reason = trim($opts['reject_reason'] ?? 'Student rejected the suggested time');
                $result = self::transition($lesson, self::STATUS_CANCELLED, $studentid, array(
                    'cancel_reason' => $reason,
                ));
                notification_manager::lesson_event($lesson, 'rejected_by_student', $lesson->teacherid, $studentid,
                    array('reason' => $reason));
                return $result;

            case 'suggest':
                $time = self::required_future_time($opts['suggested_time'] ?? 0);
                self::check_time_conflict($lesson->teacherid, $time, $lesson->duration, $lessonid);
                self::add_proposal($lessonid, $studentid, 'student', $time, 'suggest');
                audit_manager::record($lessonid, 'student_suggested', $studentid, 'student');
                $result = self::transition($lesson, self::STATUS_WAITING_TEACHER, $studentid);
                // US-LS-2-2: tell the teacher the student suggested a new time.
                notification_manager::lesson_event($lesson, 'student_suggested', $lesson->teacherid, $studentid,
                    array('time' => $time));
                return $result;

            default:
                throw new \moodle_exception('err_badaction', 'local_academy');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-LS-3-1: start a lesson (teacher)
    // ──────────────────────────────────────────────────────────────────────────

    public static function start_lesson($teacherid, $lessonid) {
        $lesson = self::get_owned_lesson($lessonid);
        if ((int)$lesson->teacherid !== (int)$teacherid) {
            throw new \moodle_exception('err_forbidden', 'local_academy');
        }
        if ($lesson->status !== self::STATUS_CONFIRMED) {
            throw new \moodle_exception('err_badstate', 'local_academy');
        }
        // Not before the allowed start window (start_allowed_minutes before the confirmed time).
        $allowed = (int)$lesson->confirmed_time - settings_manager::get('start_allowed_minutes') * MINSECS;
        if (time() < $allowed) {
            throw new \moodle_exception('err_tooearlytostart', 'local_academy');
        }
        // Create the meeting room (teacher + student whitelisted). If this fails the lesson stays confirmed.
        $room = room_manager::create_for_lesson($lesson);
        audit_manager::record($lessonid, 'started', $teacherid, 'teacher');
        $result = self::transition($lesson, self::STATUS_IN_PROGRESS, $teacherid, array(
            'actual_start' => time(),
            'sessionid'    => $room->sessionid,
            'cmid'         => $room->cmid,
        ));
        // US-LS-3-1: tell the student the room is ready to join.
        notification_manager::lesson_event($lesson, 'started', $lesson->studentid, $teacherid);
        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-LS-3-2: complete a lesson (teacher) → consume the Flex
    // ──────────────────────────────────────────────────────────────────────────

    public static function complete_lesson($teacherid, $lessonid, $note = null) {
        global $DB;
        $lesson = self::get_owned_lesson($lessonid);
        if ((int)$lesson->teacherid !== (int)$teacherid) {
            throw new \moodle_exception('err_forbidden', 'local_academy');
        }
        if (!in_array($lesson->status, array(self::STATUS_CONFIRMED, self::STATUS_IN_PROGRESS), true)) {
            throw new \moodle_exception('err_badstate', 'local_academy');
        }
        // US-LS-3-2: the lesson can only be completed once it has run for the configured time —
        // at least complete_allowed_minutes after it started.
        self::require_complete_window($lesson);
        $transaction = $DB->start_delegated_transaction();
        room_manager::end_for_lesson($lesson); // close the meeting room
        flex_manager::consume($lesson->studentid, $lesson->purchaseid, $lesson->id, $teacherid, 'Lesson completed');
        $extra = array('actual_end' => time(), 'flex_state' => 'consumed');
        if ($note !== null) { $extra['note'] = $note; }
        audit_manager::record($lesson->id, 'completed', $teacherid, 'teacher');
        $result = self::transition($lesson, self::STATUS_COMPLETED, $teacherid, $extra);
        // Distribute revenue on completion (US-FN-1-4): teacher/platform split of the Flex value.
        finance_manager::distribute_for_lesson($DB->get_record('academy_lessons', array('id' => $lesson->id)));
        $transaction->allow_commit();
        // US-LS-3-2: tell the student the lesson is complete (after the commit).
        notification_manager::lesson_event($lesson, 'completed', $lesson->studentid, $teacherid,
            array('reason' => $note !== null ? trim((string)$note) : ''));
        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-LS-3-3: report student absence (teacher) → Flex consumed (platform policy)
    // ──────────────────────────────────────────────────────────────────────────

    public static function report_student_absent($teacherid, $lessonid) {
        global $DB;
        $lesson = self::get_owned_lesson($lessonid);
        if ((int)$lesson->teacherid !== (int)$teacherid) {
            throw new \moodle_exception('err_forbidden', 'local_academy');
        }
        if (!in_array($lesson->status, array(self::STATUS_CONFIRMED, self::STATUS_IN_PROGRESS), true)) {
            throw new \moodle_exception('err_badstate', 'local_academy');
        }
        self::require_absence_window($lesson);
        $transaction = $DB->start_delegated_transaction();
        room_manager::end_for_lesson($lesson); // close the meeting room
        flex_manager::consume($lesson->studentid, $lesson->purchaseid, $lesson->id, $teacherid, 'Student absent');
        audit_manager::record($lesson->id, 'student_absent_reported', $teacherid, 'teacher');
        $result = self::transition($lesson, self::STATUS_STUDENT_ABSENT, $teacherid, array('flex_state' => 'consumed'));
        $transaction->allow_commit();
        // US-LS-3-3: tell the student they were marked absent.
        notification_manager::lesson_event($lesson, 'student_absent', $lesson->studentid, $teacherid);
        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-LS-3-4: report teacher absence (student) → Flex returned
    // ──────────────────────────────────────────────────────────────────────────

    public static function report_teacher_absent($studentid, $lessonid) {
        global $DB;
        $lesson = self::get_owned_lesson($lessonid);
        if ((int)$lesson->studentid !== (int)$studentid) {
            throw new \moodle_exception('err_forbidden', 'local_academy');
        }
        if (!in_array($lesson->status, array(self::STATUS_CONFIRMED, self::STATUS_IN_PROGRESS), true)) {
            throw new \moodle_exception('err_badstate', 'local_academy');
        }
        self::require_absence_window($lesson);
        $transaction = $DB->start_delegated_transaction();
        room_manager::end_for_lesson($lesson); // close the meeting room
        flex_manager::return_flex($lesson->studentid, $lesson->purchaseid, $lesson->id, $studentid, 'Teacher absent');
        audit_manager::record($lesson->id, 'teacher_absent_reported', $studentid, 'student');
        $result = self::transition($lesson, self::STATUS_TEACHER_ABSENT, $studentid, array('flex_state' => 'returned'));
        $transaction->allow_commit();
        // US-LS-3-4: notify the teacher and the platform admins.
        notification_manager::lesson_event($lesson, 'teacher_absent', $lesson->teacherid, $studentid);
        notification_manager::lesson_event_admins($lesson, 'teacher_absent_admin', $studentid);
        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-ST-2-2: student withdraws an un-confirmed request (no Flex reserved yet)
    // ──────────────────────────────────────────────────────────────────────────

    public static function cancel_request_as_student($studentid, $lessonid, $reason = '') {
        $lesson = self::get_owned_lesson($lessonid);
        if ((int)$lesson->studentid !== (int)$studentid) {
            throw new \moodle_exception('err_forbidden', 'local_academy');
        }
        $pre = array(self::STATUS_PENDING, self::STATUS_WAITING_STUDENT, self::STATUS_WAITING_TEACHER);
        if (!in_array($lesson->status, $pre, true)) {
            throw new \moodle_exception('err_badstate', 'local_academy');
        }
        self::supersede_pending_proposals($lessonid);
        audit_manager::record($lessonid, 'request_cancelled', $studentid, 'student');
        $reason = trim($reason) !== '' ? trim($reason) : 'Request withdrawn by student';
        $result = self::transition($lesson, self::STATUS_CANCELLED, $studentid, array(
            'cancel_reason' => $reason,
        ));
        // US-ST-2-2: tell the teacher the pending request was withdrawn.
        notification_manager::lesson_event($lesson, 'request_cancelled', $lesson->teacherid, $studentid,
            array('reason' => $reason));
        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-LS-4-1: student cancels (early → return Flex; late → consume Flex)
    // ──────────────────────────────────────────────────────────────────────────

    public static function cancel_as_student($studentid, $lessonid, $reason = '') {
        global $DB;
        $lesson = self::get_owned_lesson($lessonid);
        if ((int)$lesson->studentid !== (int)$studentid) {
            throw new \moodle_exception('err_forbidden', 'local_academy');
        }
        if ($lesson->status !== self::STATUS_CONFIRMED) {
            throw new \moodle_exception('err_badstate', 'local_academy');
        }
        $deadline = (int)$lesson->confirmed_time - settings_manager::get('cancel_deadline_minutes') * MINSECS;
        $early = time() <= $deadline;

        $transaction = $DB->start_delegated_transaction();
        if ($early) {
            flex_manager::return_flex($lesson->studentid, $lesson->purchaseid, $lesson->id, $studentid, 'Student cancelled (early)');
            $flexstate = 'returned';
        } else {
            // Late cancellation consumes the Flex.
            flex_manager::consume($lesson->studentid, $lesson->purchaseid, $lesson->id, $studentid, 'Student cancelled (late)');
            $flexstate = 'consumed';
        }
        audit_manager::record($lesson->id, 'cancelled_by_student', $studentid, 'student');
        $result = self::transition($lesson, self::STATUS_CANCELLED, $studentid, array(
            'cancel_reason' => trim($reason),
            'flex_state'    => $flexstate,
        ));
        $transaction->allow_commit();
        // US-LS-4-1: tell the teacher the lesson was cancelled.
        notification_manager::lesson_event($lesson, 'cancelled_by_student', $lesson->teacherid, $studentid,
            array('reason' => trim($reason)));
        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-LS-4-2: teacher cancels → Flex returned
    // ──────────────────────────────────────────────────────────────────────────

    public static function cancel_as_teacher($teacherid, $lessonid, $reason = '') {
        global $DB;
        $lesson = self::get_owned_lesson($lessonid);
        if ((int)$lesson->teacherid !== (int)$teacherid) {
            throw new \moodle_exception('err_forbidden', 'local_academy');
        }
        if ($lesson->status !== self::STATUS_CONFIRMED) {
            throw new \moodle_exception('err_badstate', 'local_academy');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new \moodle_exception('err_reasonrequired', 'local_academy');
        }
        $transaction = $DB->start_delegated_transaction();
        flex_manager::return_flex($lesson->studentid, $lesson->purchaseid, $lesson->id, $teacherid, 'Teacher cancelled');
        audit_manager::record($lesson->id, 'cancelled_by_teacher', $teacherid, 'teacher');
        $result = self::transition($lesson, self::STATUS_CANCELLED_TEACHER, $teacherid, array(
            'cancel_reason' => $reason,
            'flex_state'    => 'returned',
        ));
        $transaction->allow_commit();
        // US-LS-4-2: tell the student the teacher cancelled (Flex returned).
        notification_manager::lesson_event($lesson, 'cancelled_by_teacher', $lesson->studentid, $teacherid,
            array('reason' => $reason));
        return $result;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-LS-5-1: request a time update (student or teacher) — lesson stays confirmed
    // ──────────────────────────────────────────────────────────────────────────

    public static function request_time_update($userid, $lessonid, $proposedtime) {
        $lesson = self::get_owned_lesson($lessonid);
        $role = self::participant_role($lesson, $userid); // throws if not a participant
        if ($lesson->status !== self::STATUS_CONFIRMED) {
            throw new \moodle_exception('err_badstate', 'local_academy');
        }
        // Must be before the configured update deadline.
        $deadline = (int)$lesson->confirmed_time - settings_manager::get('update_deadline_minutes') * MINSECS;
        if (time() > $deadline) {
            throw new \moodle_exception('err_updatedeadline', 'local_academy');
        }
        $time = self::required_future_time($proposedtime);
        self::check_time_conflict($lesson->teacherid, $time, $lesson->duration, $lessonid);
        // Only one active time-update request per lesson.
        if (self::has_pending_reschedule($lessonid)) {
            throw new \moodle_exception('err_updatepending', 'local_academy');
        }
        self::add_proposal($lessonid, $userid, $role, $time, 'reschedule');
        audit_manager::record($lessonid, 'time_update_requested', $userid, $role);
        // US-LS-5-1: notify the other party of the requested new time.
        $recipientid = ($role === 'student') ? $lesson->teacherid : $lesson->studentid;
        notification_manager::lesson_event($lesson, 'time_update_requested', $recipientid, $userid,
            array('time' => $time, 'actor' => self::user_fullname($userid)));
        return self::get_lesson($userid, $lessonid);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-LS-5-2: respond to a time-update request (the other party)
    // ──────────────────────────────────────────────────────────────────────────

    public static function respond_time_update($userid, $lessonid, $action) {
        global $DB;
        $lesson = self::get_owned_lesson($lessonid);
        self::participant_role($lesson, $userid); // throws if not a participant
        if ($lesson->status !== self::STATUS_CONFIRMED) {
            throw new \moodle_exception('err_badstate', 'local_academy');
        }
        $proposal = $DB->get_record('academy_lesson_proposals',
            array('lessonid' => $lessonid, 'type' => 'reschedule', 'status' => 'pending'),
            '*', IGNORE_MULTIPLE);
        if (!$proposal) {
            throw new \moodle_exception('err_noupdaterequest', 'local_academy');
        }
        // Only the other party may respond.
        if ((int)$proposal->proposedby === (int)$userid) {
            throw new \moodle_exception('err_forbidden', 'local_academy');
        }

        $role = self::participant_role($lesson, $userid);
        // The party who requested the reschedule is the one to notify of the outcome.
        $requesterid = (int)$proposal->proposedby;
        if ($action === 'accept') {
            self::check_time_conflict($lesson->teacherid, (int)$proposal->proposed_time, $lesson->duration, $lessonid);
            $proposal->status = 'accepted';
            $DB->update_record('academy_lesson_proposals', $proposal);
            audit_manager::record($lessonid, 'time_update_accepted', $userid, $role);
            $result = self::transition($lesson, self::STATUS_CONFIRMED, $userid,
                array('confirmed_time' => (int)$proposal->proposed_time));
            // US-LS-5-2: tell the requester the new time was accepted.
            notification_manager::lesson_event($lesson, 'time_update_accepted', $requesterid, $userid,
                array('time' => (int)$proposal->proposed_time, 'actor' => self::user_fullname($userid)));
            return $result;
        } else if ($action === 'reject') {
            $proposal->status = 'rejected';
            $DB->update_record('academy_lesson_proposals', $proposal);
            audit_manager::record($lessonid, 'time_update_rejected', $userid, $role);
            // US-LS-5-2: tell the requester the new time was rejected (original time stands).
            notification_manager::lesson_event($lesson, 'time_update_rejected', $requesterid, $userid,
                array('time' => (int)$lesson->confirmed_time, 'actor' => self::user_fullname($userid)));
            return self::get_lesson($userid, $lessonid);
        }
        throw new \moodle_exception('err_badaction', 'local_academy');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-TR-1-2 / US-ST-2-2: view related lessons + single lesson
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Lessons related to a user, optionally filtered by role (student|teacher) and status.
     * Ordered: open requests/negotiation first, then upcoming confirmed by time, then history.
     */
    public static function get_my_lessons($userid, $role = '', $status = '') {
        global $DB;
        $params = array();
        if ($role === 'student') {
            $where = 'studentid = :uid';
            $params['uid'] = $userid;
        } else if ($role === 'teacher') {
            $where = 'teacherid = :uid';
            $params['uid'] = $userid;
        } else {
            $where = '(studentid = :uid1 OR teacherid = :uid2)';
            $params['uid1'] = $userid;
            $params['uid2'] = $userid;
        }
        if ($status !== '') {
            $where .= ' AND status = :status';
            $params['status'] = $status;
        }
        $rows = $DB->get_records_select('academy_lessons', $where, $params, 'timecreated DESC');

        $out = array();
        foreach ($rows as $r) {
            $out[] = self::format_lesson($r, $userid);
        }
        usort($out, function ($a, $b) {
            $ra = self::sort_rank($a['status']);
            $rb = self::sort_rank($b['status']);
            if ($ra !== $rb) { return $ra - $rb; }
            // Within a bucket: upcoming confirmed by soonest; otherwise newest first.
            if ($ra === 1) {
                return $a['effective_time'] - $b['effective_time'];
            }
            return $b['timecreated'] - $a['timecreated'];
        });
        return $out;
    }

    /** A single lesson the user participates in, with proposals + available actions. */
    public static function get_lesson($userid, $lessonid) {
        $lesson = self::get_owned_lesson($lessonid);
        self::participant_role($lesson, $userid); // throws if not a participant
        return self::format_lesson($lesson, $userid, true);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ──────────────────────────────────────────────────────────────────────────

    /** Confirm a lesson at $time and reserve one Flex (US-FN-1-2). Shared by teacher/student accept. */
    private static function confirm_lesson($lesson, $time, $actorid) {
        global $DB;
        if ($time <= 0) {
            throw new \moodle_exception('err_notime', 'local_academy');
        }
        $transaction = $DB->start_delegated_transaction();
        $purchaseid = flex_manager::reserve($lesson->studentid, $lesson->id, $actorid, 'Lesson confirmed');
        self::mark_proposal_accepted($lesson->id, $time);
        $result = self::transition($lesson, self::STATUS_CONFIRMED, $actorid, array(
            'confirmed_time' => (int)$time,
            'purchaseid'     => $purchaseid,
            'flex_state'     => 'reserved',
        ));
        
        $reminder_minutes_str = settings_manager::get('lesson_start_reminder_minutes');
        if (!empty($reminder_minutes_str)) {
            $minutes_arr = array_filter(array_map('trim', explode(',', (string)$reminder_minutes_str)));
            $immediate_queued = false;
            foreach ($minutes_arr as $min_val) {
                $m = (int)$min_val;
                if ($m > 0) {
                    $run_time = (int)$time - ($m * 60);
                    if ($run_time < time()) {
                        if (!$immediate_queued) {
                            $run_time = time(); // Queue one to run immediately
                            $immediate_queued = true;
                        } else {
                            continue; // Skip queuing duplicate immediate tasks
                        }
                    }
                    $task = new \local_academy\task\lesson_start_reminder_task();
                    $task->set_next_run_time($run_time);
                    $task->set_custom_data(array(
                        'lessonid'       => $lesson->id,
                        'confirmed_time' => (int)$time,
                    ));
                    \core\task\manager::queue_adhoc_task($task);
                }
            }
        }
        
        $transaction->allow_commit();
        return $result;
    }

    /** Apply a status change + extra fields, bump timemodified, return the formatted lesson. */
    private static function transition($lesson, $status, $actorid, array $extra = array()) {
        global $DB;
        $lesson->status = $status;
        foreach ($extra as $k => $v) {
            $lesson->$k = $v;
        }
        $lesson->timemodified = time();
        $DB->update_record('academy_lessons', $lesson);
        return self::format_lesson($DB->get_record('academy_lessons', array('id' => $lesson->id)), $actorid, true);
    }

    private static function get_owned_lesson($lessonid) {
        global $DB;
        $lesson = $DB->get_record('academy_lessons', array('id' => $lessonid));
        if (!$lesson) {
            throw new \moodle_exception('err_lessonnotfound', 'local_academy');
        }
        return $lesson;
    }

    /** 'student' | 'teacher', or throw if the user is not part of the lesson. */
    private static function participant_role($lesson, $userid) {
        if ((int)$lesson->studentid === (int)$userid) { return 'student'; }
        if ((int)$lesson->teacherid === (int)$userid) { return 'teacher'; }
        throw new \moodle_exception('err_forbidden', 'local_academy');
    }

    /** Display name for a user id (used in reschedule notifications). */
    private static function user_fullname($userid) {
        global $DB;
        $u = $DB->get_record('user', array('id' => $userid), 'id, firstname, lastname');
        return $u ? fullname($u) : '';
    }

    private static function teacher_supports_subject($teacherid, $subject) {
        global $DB;
        $sql = "SELECT 1 FROM {academy_teacher_subjects}
                 WHERE teacherid = :tid AND " . $DB->sql_compare_text('subject') . " = " . $DB->sql_compare_text(':subject');
        return $DB->record_exists_sql($sql, array('tid' => $teacherid, 'subject' => $subject));
    }

    private static function require_min_booking($time) {
        $min = settings_manager::get('min_booking_minutes') * MINSECS;
        if ((int)$time < time() + $min) {
            throw new \moodle_exception('err_minbooking', 'local_academy');
        }
    }

    private static function required_future_time($time) {
        $time = (int)$time;
        if ($time <= 0) {
            throw new \moodle_exception('err_notime', 'local_academy');
        }
        self::require_min_booking($time);
        return $time;
    }

    private static function check_time_conflict($teacherid, $time, $duration = self::DEFAULT_DURATION, $exclude_lessonid = 0) {
        global $DB;
        $start = (int)$time;
        $end = $start + (int)$duration * MINSECS;
        
        $sql = "SELECT id, requested_time, confirmed_time, duration 
                  FROM {academy_lessons}
                 WHERE teacherid = :tid
                   AND status IN ('pending', 'waiting_student', 'waiting_teacher', 'confirmed', 'in_progress')";
        $params = array('tid' => $teacherid);
        
        if ($exclude_lessonid > 0) {
            $sql .= " AND id != :exid";
            $params['exid'] = $exclude_lessonid;
        }
        
        $active_lessons = $DB->get_records_sql($sql, $params);
        foreach ($active_lessons as $l) {
            $l_start = (int)$l->confirmed_time > 0 ? (int)$l->confirmed_time : (int)$l->requested_time;
            $l_end = $l_start + (int)$l->duration * MINSECS;
            if ($start < $l_end && $end > $l_start) {
                throw new \moodle_exception('err_timeconflict', 'local_academy');
            }
        }
    }

    /** Minimum run time: a lesson can only be completed complete_allowed_minutes after it starts. */
    private static function require_complete_window($lesson) {
        $wait = settings_manager::get('complete_allowed_minutes') * MINSECS;
        // Count from the actual start if it was started; otherwise from the confirmed (scheduled) time.
        $start = (int)$lesson->actual_start > 0 ? (int)$lesson->actual_start : (int)$lesson->confirmed_time;
        if (time() < $start + $wait) {
            throw new \moodle_exception('err_completetooearly', 'local_academy');
        }
    }

    /** Enforce the absence-report wait window (absence_report_minutes after the confirmed start). */
    private static function require_absence_window($lesson) {
        $wait = settings_manager::get('absence_report_minutes') * MINSECS;
        if (time() < (int)$lesson->confirmed_time + $wait) {
            throw new \moodle_exception('err_absencetooearly', 'local_academy');
        }
    }

    private static function add_proposal($lessonid, $userid, $role, $time, $type) {
        global $DB;
        // A new proposal supersedes any earlier pending one of the same kind.
        self::supersede_pending_proposals($lessonid, $type);
        $DB->insert_record('academy_lesson_proposals', (object) array(
            'lessonid'      => $lessonid,
            'proposedby'    => $userid,
            'role'          => $role,
            'proposed_time' => (int)$time,
            'type'          => $type,
            'status'        => 'pending',
            'timecreated'   => time(),
        ));
    }

    private static function supersede_pending_proposals($lessonid, $type = null) {
        global $DB;
        $params = array('lessonid' => $lessonid, 'status' => 'pending');
        if ($type !== null) { $params['type'] = $type; }
        $DB->set_field('academy_lesson_proposals', 'status', 'superseded', $params);
    }

    private static function latest_pending_proposal_time($lessonid) {
        global $DB;
        $rows = $DB->get_records('academy_lesson_proposals',
            array('lessonid' => $lessonid, 'type' => 'suggest', 'status' => 'pending'),
            'timecreated DESC, id DESC', 'id, proposed_time', 0, 1);
        if (!$rows) {
            throw new \moodle_exception('err_notime', 'local_academy');
        }
        $row = reset($rows);
        return (int)$row->proposed_time;
    }

    private static function mark_proposal_accepted($lessonid, $time) {
        global $DB;
        // Accept the matching pending suggestion (if any); supersede the rest.
        $rows = $DB->get_records('academy_lesson_proposals',
            array('lessonid' => $lessonid, 'type' => 'suggest', 'status' => 'pending'));
        foreach ($rows as $r) {
            $r->status = ((int)$r->proposed_time === (int)$time) ? 'accepted' : 'superseded';
            $DB->update_record('academy_lesson_proposals', $r);
        }
    }

    private static function has_pending_reschedule($lessonid) {
        global $DB;
        return $DB->record_exists('academy_lesson_proposals',
            array('lessonid' => $lessonid, 'type' => 'reschedule', 'status' => 'pending'));
    }

    /** Ordering buckets: 0 = needs action/open, 1 = upcoming confirmed, 2 = history. */
    private static function sort_rank($status) {
        $open = array(self::STATUS_PENDING, self::STATUS_WAITING_STUDENT, self::STATUS_WAITING_TEACHER);
        $active = array(self::STATUS_CONFIRMED, self::STATUS_IN_PROGRESS);
        if (in_array($status, $open, true)) { return 0; }
        if (in_array($status, $active, true)) { return 1; }
        return 2;
    }

    private static function format_lesson($lesson, $viewerid = 0, $withdetail = false) {
        global $DB, $CFG;
        $student = $DB->get_record('user', array('id' => $lesson->studentid), 'id, firstname, lastname');
        $teacher = $DB->get_record('user', array('id' => $lesson->teacherid), 'id, firstname, lastname');
        $confirmed = (int)$lesson->confirmed_time;
        $out = array(
            'id'             => (int)$lesson->id,
            'studentid'      => (int)$lesson->studentid,
            'student_name'   => $student ? trim($student->firstname . ' ' . $student->lastname) : '',
            'teacherid'      => (int)$lesson->teacherid,
            'teacher_name'   => $teacher ? trim($teacher->firstname . ' ' . $teacher->lastname) : '',
            'subject'        => format_string($lesson->subject),
            'status'         => $lesson->status,
            'requested_time' => (int)$lesson->requested_time,
            'confirmed_time' => $confirmed,
            'effective_time' => $confirmed > 0 ? $confirmed : (int)$lesson->requested_time,
            'duration'       => (int)$lesson->duration,
            'note'           => $lesson->note,
            'reject_reason'  => $lesson->reject_reason,
            'cancel_reason'  => $lesson->cancel_reason,
            'flex_state'     => $lesson->flex_state,
            'actual_start'   => (int)$lesson->actual_start,
            'actual_end'     => (int)$lesson->actual_end,
            'sessionid'      => (int)$lesson->sessionid,
            'cmid'           => (int)$lesson->cmid,
            'timecreated'    => (int)$lesson->timecreated,
            'timemodified'   => (int)$lesson->timemodified,
        );
        // Meeting room (US-LS-3-1): while the lesson is in progress, both the teacher and the
        // student can open the room. The app shows a "Join Lesson" button when can_join is true.
        $canjoin = ($lesson->status === self::STATUS_IN_PROGRESS) && ((int)$lesson->cmid > 0);
        $out['can_join'] = $canjoin;
        $out['join_url'] = $canjoin ? ($CFG->wwwroot . '/mod/jitsi/view.php?id=' . (int)$lesson->cmid) : '';
        // Native Jitsi SDK payload (server_url + room + jwt) for the viewer — same shape as getalltopics.php.
        $out['jitsi_session'] = ($canjoin && $viewerid) ? room_manager::session_payload($lesson, $viewerid) : null;
        if ($viewerid) {
            $out['my_role'] = ((int)$lesson->studentid === (int)$viewerid) ? 'student'
                : (((int)$lesson->teacherid === (int)$viewerid) ? 'teacher' : '');
            $out['actions'] = self::available_actions($lesson, $out['my_role']);
        }
        if ($withdetail) {
            $props = array_values($DB->get_records('academy_lesson_proposals',
                array('lessonid' => $lesson->id), 'timecreated ASC, id ASC'));
            $out['proposals'] = array_map(function ($p) {
                return array(
                    'id'            => (int)$p->id,
                    'proposedby'    => (int)$p->proposedby,
                    'role'          => $p->role,
                    'proposed_time' => (int)$p->proposed_time,
                    'type'          => $p->type,
                    'status'        => $p->status,
                    'timecreated'   => (int)$p->timecreated,
                );
            }, $props);
        }
        return $out;
    }

    /** Actions available to a role for the lesson's current status (US-TR-1-2 / US-ST-2-2). */
    private static function available_actions($lesson, $role) {
        $a = array();
        $status = $lesson->status;
        $hasreschedule = self::has_pending_reschedule($lesson->id);
        if ($role === 'teacher') {
            if ($status === self::STATUS_PENDING) { $a = array('accept', 'reject', 'suggest'); }
            else if ($status === self::STATUS_WAITING_TEACHER) { $a = array('accept', 'reject'); }
            else if ($status === self::STATUS_CONFIRMED) {
                $a = array('start', 'cancel', 'request_time_update');
                if ($hasreschedule) { $a[] = 'respond_time_update'; }
            } else if ($status === self::STATUS_IN_PROGRESS) {
                $a = array('join', 'report_student_absent');
                $wait = settings_manager::get('complete_allowed_minutes') * MINSECS;
                $start = (int)$lesson->actual_start > 0 ? (int)$lesson->actual_start : (int)$lesson->confirmed_time;
                if (time() >= $start + $wait) {
                    $a[] = 'complete';
                }
            }
        } else if ($role === 'student') {
            if ($status === self::STATUS_PENDING) { $a = array('cancel_request'); }
            else if ($status === self::STATUS_WAITING_STUDENT) { $a = array('accept', 'reject', 'suggest'); }
            else if ($status === self::STATUS_CONFIRMED) {
                $a = array('cancel', 'report_teacher_absent', 'request_time_update');
                if ($hasreschedule) { $a[] = 'respond_time_update'; }
            } else if ($status === self::STATUS_IN_PROGRESS) {
                $a = array('join', 'report_teacher_absent');
            }
        }
        $a[] = 'view';
        return $a;
    }
}
