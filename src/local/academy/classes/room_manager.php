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

        // In this dedicated lessons course, stop editing teachers from bypassing the per-room
        // group restriction so they only see their own rooms (mirrors how students are limited).
        self::lock_down_teacher_visibility($courseid);

        // Both participants need access to the course that hosts the room.
        self::enrol($courseid, (int)$lesson->teacherid, 'editingteacher');
        self::enrol($courseid, (int)$lesson->studentid, 'student');

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
        // Create the room with elevated privileges: teachers are deliberately denied
        // moodle/course:manageactivities in this course (see lock_down_teacher_visibility) so
        // they can't see other teachers' rooms, which means they also can't create this one.
        $created = self::create_module_elevated($moduleinfo);

        $cmid    = (int) $created->coursemodule;
        $jitsiid = (int) $created->instance;
        $joinurl = $CFG->wwwroot . '/mod/jitsi/view.php?id=' . $cmid;

        // Restrict the room on the course page to its two participants (US-LS-3-1): a per-lesson
        // group holds the teacher + student, and the activity is made available only to that group
        // (hidden from everyone else). Belt-and-braces with the view.php entry gate — it also keeps
        // unrelated users from even seeing other people's lesson rooms in the shared lessons course.
        $groupid = self::participant_group($courseid, $lesson);
        self::restrict_module_to_group($courseid, $cmid, $groupid);

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

    /**
     * Build the native-SDK Jitsi session payload for a lesson's viewer (US-LS-3-1).
     *
     * Mirrors the `jitsi_session` object produced by local/multitopics/getalltopics.php so the
     * mobile app can join the room directly with the Jitsi SDK (server_url + room + jwt) without
     * loading view.php. The JWT is signed for this specific viewer (moderator = the teacher).
     *
     * @param \stdClass $lesson academy_lessons row (needs cmid, teacherid, subject)
     * @param int $viewerid the user the JWT/identity is minted for
     * @return array|null null when there is no room or no viewer
     */
    public static function session_payload($lesson, $viewerid) {
        global $DB;

        $cmid = (int) $lesson->cmid;
        if ($cmid <= 0 || !$viewerid) {
            return null;
        }
        $jitsiid = (int) $DB->get_field('course_modules', 'instance', array('id' => $cmid));
        $user = $DB->get_record('user', array('id' => $viewerid));
        if (!$jitsiid || !$user) {
            return null;
        }

        $isteacher = ((int)$lesson->teacherid === (int)$viewerid);

        // Hold the student until the teacher is actually in the call (set by teacher_present.php).
        // The teacher themselves is never gated.
        $session = !empty($lesson->sessionid)
            ? $DB->get_record('academy_live_sessions', array('id' => (int)$lesson->sessionid), 'id, teacher_joined_at')
            : null;
        $teacherpresent = $session ? !empty($session->teacher_joined_at) : false;
        $available = $isteacher || $teacherpresent;

        $jitsihost = get_config('local_academysessions', 'jitsi_host') ?: 'localhost:8443';
        $serverurl = (strpos($jitsihost, 'http') === 0) ? rtrim($jitsihost, '/') : 'https://' . $jitsihost;

        // Same stable room name formula used by view.php / getalltopics — the JWT is signed for it.
        $room = 'academy_jitsi_' . $cmid . '_' . substr(md5($jitsiid . $cmid), 0, 8);
        $jwt  = \local_academysessions\jitsi_jwt::generate($room, fullname($user), $user->email, $isteacher);

        $jitsirec = $DB->get_record('jitsi', array('id' => $jitsiid), 'id, name');
        $subject  = $jitsirec ? format_string($jitsirec->name) : $lesson->subject;

        return array(
            'server_url'     => $serverurl,
            'room'           => $room,
            'jwt'            => $jwt,
            'subject'        => $subject,
            'is_teacher'     => $isteacher,
            'available'      => $available, // gated by can_join AND teacher presence
            'available_info' => $available ? '' : get_string('waitingforteacher', 'jitsi'),
            'host_id'        => (string)(int)$lesson->teacherid,
            'feature_flags'  => array(
                'recording.enabled'        => $isteacher,
                'livestreaming.enabled'    => false,
                'invite.enabled'           => $isteacher,
                'security-options.enabled' => $isteacher,
                'breakout-rooms.enabled'   => $isteacher,
                'video-share.enabled'      => $isteacher,
                'kick-out.enabled'         => $isteacher,
                'mute-everyone.enabled'    => $isteacher,
                'screen-sharing.enabled'   => true,
                'chat.enabled'             => true,
                'raise-hand.enabled'       => true,
                'tile-view.enabled'        => true,
            ),
        );
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

    /** Create a course module with site-admin privileges (used because teachers are denied
     *  manageactivities in the lessons course — see lock_down_teacher_visibility). */
    private static function create_module_elevated($moduleinfo) {
        global $USER;
        $realuser = $USER;
        \core\session\manager::set_user(get_admin());
        try {
            return create_module($moduleinfo);
        } finally {
            \core\session\manager::set_user($realuser);
        }
    }

    /**
     * Prevent teachers from seeing rooms they don't belong to in the lessons course, so a teacher
     * only sees their own rooms (the same effect the group restriction already has on students).
     * Applied once as a capability override on the course context; idempotent — after the first run
     * the checks are cheap no-ops. The room is created with admin privileges instead (see
     * {@see create_module_elevated()}), so removing manageactivities here does not block creation.
     */
    private static function lock_down_teacher_visibility($courseid) {
        global $DB;

        $context = \context_course::instance($courseid);
        // Capabilities that would otherwise let a teacher see rooms they don't belong to:
        //  - accessallgroups: makes the group-availability condition treat them as a member of every
        //    group, bypassing the per-room restriction entirely (the main leak).
        //  - manageactivities: makes them a course editor — Moodle shows editors every activity.
        //  - ignoreavailabilityrestrictions / viewhiddenactivities: direct availability bypasses.
        $caps = array(
            'moodle/site:accessallgroups',
            'moodle/course:manageactivities',
            'moodle/course:ignoreavailabilityrestrictions',
            'moodle/course:viewhiddenactivities',
        );
        $changed = false;
        foreach (array('editingteacher', 'teacher') as $shortname) {
            $roleid = $DB->get_field('role', 'id', array('shortname' => $shortname));
            if (!$roleid) {
                continue;
            }
            foreach ($caps as $cap) {
                $existing = $DB->get_field('role_capabilities', 'permission', array(
                    'roleid'     => $roleid,
                    'contextid'  => $context->id,
                    'capability' => $cap,
                ));
                if ((int)$existing === CAP_PREVENT) {
                    continue; // already locked down
                }
                assign_capability($cap, CAP_PREVENT, $roleid, $context->id, true);
                $changed = true;
            }
        }
        if ($changed) {
            $context->mark_dirty();
        }
    }

    /**
     * Create (or reuse) the group that holds this lesson's teacher + student.
     * Both must already be enrolled in $courseid. Returns the group id.
     */
    private static function participant_group($courseid, $lesson) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');

        // One stable group per lesson, keyed by idnumber so re-starts reuse it.
        $idnumber = 'academy_lesson_' . (int)$lesson->id;
        $group = $DB->get_record('groups', array('courseid' => $courseid, 'idnumber' => $idnumber));
        if ($group) {
            $groupid = (int)$group->id;
        } else {
            $groupid = groups_create_group((object) array(
                'courseid'    => $courseid,
                'name'        => 'Lesson #' . (int)$lesson->id . ' participants',
                'idnumber'    => $idnumber,
                'description' => 'Auto-created to restrict the lesson room to its teacher and student.',
            ));
        }
        groups_add_member($groupid, (int)$lesson->teacherid);
        groups_add_member($groupid, (int)$lesson->studentid);
        return $groupid;
    }

    /**
     * Set the course module's access restriction to "must belong to $groupid", hidden from
     * everyone else. This is the "Restricted" availability condition shown on the course page.
     */
    private static function restrict_module_to_group($courseid, $cmid, $groupid) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/modinfolib.php');

        // core_availability tree: all of (member of $groupid); showc=false → hidden when not met.
        $availability = json_encode(array(
            'op'    => '&',
            'c'     => array(array('type' => 'group', 'id' => (int)$groupid)),
            'showc' => array(false),
        ));
        $DB->set_field('course_modules', 'availability', $availability, array('id' => (int)$cmid));
        rebuild_course_cache($courseid, true);
    }
}
