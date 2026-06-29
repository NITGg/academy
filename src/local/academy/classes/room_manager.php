<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

use local_academysessions\session_manager;

/**
 * Creates and tears down the Jitsi meeting room for a lesson (US-LS-3-1 / US-LS-3-2).
 *
 * Reuses the existing live-session machinery rather than re-implementing Jitsi access control:
 * a per-lesson mod_jitsi activity is created in the configured lessons course, then linked to an
 * {@see session_manager::create_session()} row so the Jitsi view.php access layers (student
 * whitelist, time window, end-of-session close) apply — limiting the room to the assigned teacher
 * and student. The room is reached at /mod/jitsi/view.php?id={cmid}.
 */
class room_manager {

    /**
     * Create the meeting room for a freshly-started lesson.
     *
     * @param \stdClass $lesson academy_lessons row (needs subject, teacherid, studentid, duration)
     * @return \stdClass {sessionid, cmid, join_url}
     */
    public static function create_for_lesson($lesson) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/lib/enrollib.php');

        $courseid = (int) settings_manager::get('lessons_courseid');
        if ($courseid <= 0 || !$DB->record_exists('course', array('id' => $courseid))) {
            throw new \moodle_exception('err_nolessonscourse', 'local_academy');
        }

        // Both participants need access to the course that hosts the room.
        self::enrol($courseid, (int)$lesson->teacherid, 'editingteacher');
        self::enrol($courseid, (int)$lesson->studentid, 'student');
        // Refresh the current user's (teacher's) capabilities so create_module() sees the new role.
        reload_all_capabilities();

        $student = $DB->get_record('user', array('id' => $lesson->studentid), 'id, firstname, lastname');
        $title = trim($lesson->subject) . ' — ' . ($student ? fullname($student) : 'Lesson #' . $lesson->id);

        // Create the Jitsi activity in the lessons course.
        $moduleinfo = new \stdClass();
        $moduleinfo->modulename        = 'jitsi';
        $moduleinfo->course            = $courseid;
        $moduleinfo->section           = 0;
        $moduleinfo->visible           = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->cmidnumber        = '';
        $moduleinfo->name              = $title;
        $moduleinfo->introeditor       = array('text' => '', 'format' => FORMAT_HTML, 'itemid' => 0);
        $moduleinfo->lobby_enabled     = 1; // 1:1 lesson — the teacher admits the student.
        $created = create_module($moduleinfo);

        $cmid    = (int) $created->coursemodule;
        $jitsiid = (int) $created->instance;
        $joinurl = $CFG->wwwroot . '/mod/jitsi/view.php?id=' . $cmid;

        // Link the activity to a session so the whitelist + time window apply (reuses the existing service).
        // Window opens now so the student can join immediately after the teacher starts.
        $sessionid = session_manager::create_session(
            $courseid,
            (int)$lesson->teacherid,
            $title,
            time(),
            array((int)$lesson->studentid),
            $joinurl,
            (int)$lesson->duration,
            null,
            $jitsiid
        );
        session_manager::start_session($sessionid); // status scheduled → live

        return (object) array('sessionid' => $sessionid, 'cmid' => $cmid, 'join_url' => $joinurl);
    }

    /** Close the room when the lesson ends (complete / absence). No-op if no room was created. */
    public static function end_for_lesson($lesson) {
        if (empty($lesson->sessionid)) {
            return;
        }
        session_manager::end_session((int)$lesson->sessionid);
    }

    /** Enrol a user in the room's course with the given role (idempotent). */
    private static function enrol($courseid, $userid, $roleshortname) {
        global $DB;
        $role = $DB->get_record('role', array('shortname' => $roleshortname));
        enrol_try_internal_enrol($courseid, $userid, $role ? $role->id : null);
    }
}
