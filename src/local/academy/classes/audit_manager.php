<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Lesson-request action audit trail.
 *
 * Records the time of every action a student/teacher/system takes on a lesson request
 * (create, accept, reject, suggest, start, complete, absence, cancel, time-update …).
 *
 * Per the spec (docs/specs/00-overview.md "Lesson action audit trail"), the recorded time is
 * **encrypted at rest** — only the encrypted blob is stored, never the plaintext timestamp. The
 * admin reports UI (US-AD-3-1) decrypts it for display via {@see report_manager::lesson_events_report}.
 * Rows are ordered by id (insertion order) so chronology survives without a plaintext time column.
 */
class audit_manager {

    /**
     * Record one action's time against a lesson (best-effort: never breaks the action it logs).
     *
     * @param int $lessonid
     * @param string $action short action key, e.g. 'requested', 'teacher_accepted', 'completed'
     * @param int $actorid user who performed the action (0 = system)
     * @param string $role  'student' | 'teacher' | 'admin' | 'system'
     */
    public static function record($lessonid, $action, $actorid = 0, $role = 'system') {
        global $DB;
        try {
            $DB->insert_record('academy_lesson_events', (object) array(
                'lessonid' => (int)$lessonid,
                'action'   => (string)$action,
                'actorid'  => (int)$actorid,
                'role'     => (string)$role,
                'time_enc' => self::encrypt_time(time()),
            ));
        } catch (\Throwable $e) {
            // Auditing must not block the underlying lesson action; swallow and move on.
            debugging('academy audit record failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Record an action only the first time it happens for a lesson.
     *
     * Used for meeting-join events that can fire repeatedly (a teacher/student leaving and
     * rejoining the call) but should appear exactly once in the timeline. Best-effort like
     * {@see record()} — never breaks the action it logs.
     *
     * @param int $lessonid
     * @param string $action short action key, e.g. 'teacher_joined', 'student_joined'
     * @param int $actorid user who performed the action (0 = system)
     * @param string $role  'student' | 'teacher' | 'admin' | 'system'
     */
    public static function record_once($lessonid, $action, $actorid = 0, $role = 'system') {
        global $DB;
        try {
            if ($DB->record_exists('academy_lesson_events',
                    array('lessonid' => (int)$lessonid, 'action' => (string)$action))) {
                return;
            }
        } catch (\Throwable $e) {
            debugging('academy audit record_once check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return;
        }
        self::record($lessonid, $action, $actorid, $role);
    }

    /**
     * Decrypted action timeline for a lesson, oldest first.
     *
     * @param int $lessonid
     * @return array list of ['action','actorid','actor_name','role','time'] (time = unix or 0 if unreadable)
     */
    public static function get_timeline($lessonid) {
        global $DB;
        $rows = $DB->get_records('academy_lesson_events', array('lessonid' => $lessonid), 'id ASC');
        $out = array();
        foreach ($rows as $r) {
            $actor = $r->actorid ? $DB->get_record('user', array('id' => $r->actorid), 'id, firstname, lastname') : null;
            $out[] = array(
                'action'     => $r->action,
                'actorid'    => (int)$r->actorid,
                'actor_name' => $actor ? trim($actor->firstname . ' ' . $actor->lastname) : '',
                'role'       => $r->role,
                'time'       => self::decrypt_time($r->time_enc),
            );
        }
        return $out;
    }

    // ── encryption helpers (base64 over \core\encryption so the blob is text-safe) ──

    private static function encrypt_time($unixtime) {
        return base64_encode(\core\encryption::encrypt((string)(int)$unixtime));
    }

    private static function decrypt_time($enc) {
        if ($enc === null || $enc === '') {
            return 0;
        }
        try {
            return (int)\core\encryption::decrypt(base64_decode($enc));
        } catch (\Throwable $e) {
            return 0; // unreadable (e.g. key rotated) — don't break the report
        }
    }
}
