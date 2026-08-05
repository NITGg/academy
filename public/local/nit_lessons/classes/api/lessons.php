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

namespace local_nit_lessons\api;

use local_nit_lessons\service\lesson_service;

/**
 * Public facade for the lesson lifecycle.
 *
 * @package    local_nit_lessons
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
final class lessons {
    /**
     * Student requests a lesson (US-LS-1-1).
     *
     * @param int $studentid
     * @param int $teacherid
     * @param string $subject
     * @param int $when unix start time
     * @param string $note
     * @return array
     */
    public static function request(int $studentid, int $teacherid, string $subject, int $when,
            string $note): array {
        return (new lesson_service())->request_lesson($studentid, $teacherid, $subject, $when, $note);
    }

    /**
     * Teacher accepts / rejects / suggests (US-LS-2-1 / 2-3).
     *
     * @param int $teacherid
     * @param int $lessonid
     * @param string $action
     * @param array $opts
     * @return array
     */
    public static function teacher_respond(int $teacherid, int $lessonid, string $action, array $opts = []): array {
        return (new lesson_service())->teacher_respond($teacherid, $lessonid, $action, $opts);
    }

    /**
     * Student accepts / rejects / suggests (US-LS-2-2).
     *
     * @param int $studentid
     * @param int $lessonid
     * @param string $action
     * @param array $opts
     * @return array
     */
    public static function student_respond(int $studentid, int $lessonid, string $action, array $opts = []): array {
        return (new lesson_service())->student_respond($studentid, $lessonid, $action, $opts);
    }

    /**
     * Teacher starts a confirmed lesson (US-LS-3-1).
     *
     * @param int $teacherid
     * @param int $lessonid
     * @return array
     */
    public static function start(int $teacherid, int $lessonid): array {
        return (new lesson_service())->start_lesson($teacherid, $lessonid);
    }

    /**
     * Teacher completes a lesson (US-LS-3-2).
     *
     * @param int $teacherid
     * @param int $lessonid
     * @param string|null $note
     * @return array
     */
    public static function complete(int $teacherid, int $lessonid, ?string $note = null): array {
        return (new lesson_service())->complete_lesson($teacherid, $lessonid, $note);
    }

    /**
     * Teacher reports the student absent (US-LS-3-3).
     *
     * @param int $teacherid
     * @param int $lessonid
     * @return array
     */
    public static function report_student_absent(int $teacherid, int $lessonid): array {
        return (new lesson_service())->report_student_absent($teacherid, $lessonid);
    }

    /**
     * Student reports the teacher absent (US-LS-3-4).
     *
     * @param int $studentid
     * @param int $lessonid
     * @return array
     */
    public static function report_teacher_absent(int $studentid, int $lessonid): array {
        return (new lesson_service())->report_teacher_absent($studentid, $lessonid);
    }

    /**
     * Student withdraws an un-confirmed request (US-ST-2-2).
     *
     * @param int $studentid
     * @param int $lessonid
     * @param string $reason
     * @return array
     */
    public static function cancel_request(int $studentid, int $lessonid, string $reason = ''): array {
        return (new lesson_service())->cancel_request_as_student($studentid, $lessonid, $reason);
    }

    /**
     * Student cancels a confirmed lesson (US-LS-4-1).
     *
     * @param int $studentid
     * @param int $lessonid
     * @param string $reason
     * @return array
     */
    public static function cancel_as_student(int $studentid, int $lessonid, string $reason = ''): array {
        return (new lesson_service())->cancel_as_student($studentid, $lessonid, $reason);
    }

    /**
     * Teacher cancels a confirmed lesson (US-LS-4-2).
     *
     * @param int $teacherid
     * @param int $lessonid
     * @param string $reason
     * @return array
     */
    public static function cancel_as_teacher(int $teacherid, int $lessonid, string $reason = ''): array {
        return (new lesson_service())->cancel_as_teacher($teacherid, $lessonid, $reason);
    }

    /**
     * Request a time update on a confirmed lesson (US-LS-5-1).
     *
     * @param int $userid
     * @param int $lessonid
     * @param int $when
     * @return array
     */
    public static function request_time_update(int $userid, int $lessonid, int $when): array {
        return (new lesson_service())->request_time_update($userid, $lessonid, $when);
    }

    /**
     * Respond to a time-update request (US-LS-5-2).
     *
     * @param int $userid
     * @param int $lessonid
     * @param string $action
     * @return array
     */
    public static function respond_time_update(int $userid, int $lessonid, string $action): array {
        return (new lesson_service())->respond_time_update($userid, $lessonid, $action);
    }

    /**
     * Admin reverses a distributed Flex (US-FN-1-5).
     *
     * @param int $lessonid
     * @param int $adminid
     * @param string $reason
     * @return array
     */
    public static function reverse_flex(int $lessonid, int $adminid, string $reason): array {
        return (new lesson_service())->reverse_flex($lessonid, $adminid, $reason);
    }

    /**
     * Lessons for a user (US-TR-1-2 / US-ST-2-2).
     *
     * @param int $userid
     * @param string $role
     * @param string $status
     * @return array
     */
    public static function my_lessons(int $userid, string $role = '', string $status = ''): array {
        return (new lesson_service())->get_my_lessons($userid, $role, $status);
    }

    /**
     * A single lesson.
     *
     * @param int $userid
     * @param int $lessonid
     * @return array
     */
    public static function get(int $userid, int $lessonid): array {
        return (new lesson_service())->get_lesson($userid, $lessonid);
    }
}
