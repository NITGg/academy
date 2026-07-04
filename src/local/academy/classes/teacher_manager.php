<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Teacher profiles, subjects, working hours, and public browse.
 *
 * Covers US-TR-1-1 (update profile) and US-ST-2-1 (browse teachers).
 */
class teacher_manager {

    /**
     * Is this user a teacher? True if they hold a (editing)teacher role anywhere.
     * Uses role archetype (not capability) so site admins are NOT treated as teachers.
     */
    public static function is_teacher($userid) {
        global $DB;
        if (empty($userid) || isguestuser($userid)) {
            return false;
        }
        $sql = "SELECT 1
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :uid AND r.archetype IN ('teacher', 'editingteacher')";
        return $DB->record_exists_sql($sql, array('uid' => $userid));
    }

    /** Full profile for a teacher (own view): profile + subjects + hours. */
    public static function get_profile($userid) {
        global $DB;
        $p = $DB->get_record('academy_teacher_profiles', array('userid' => $userid));
        $user = $DB->get_record('user', array('id' => $userid), 'id, firstname, lastname, email');
        if (!$p) {
            $p = (object) array(
                'userid' => $userid, 'headline' => '', 'bio' => '', 'experience' => '',
                'photourl' => '', 'rating' => 0, 'approved' => 1, 'available' => 1,
            );
        }
        return self::format_profile($p, $user);
    }

    /**
     * Create/update the teacher's own profile (US-TR-1-1).
     *
     * @param int $userid
     * @param array $data headline, bio, experience, photourl, available, subjects[], hours[]
     */
    public static function update_profile($userid, array $data) {
        global $DB;
        $now = time();

        $p = $DB->get_record('academy_teacher_profiles', array('userid' => $userid));
        $record = $p ?: new \stdClass();
        $record->userid = $userid;
        foreach (array('headline', 'bio', 'experience', 'photourl') as $f) {
            if (array_key_exists($f, $data)) {
                $record->$f = $data[$f];
            }
        }
        if (array_key_exists('available', $data)) {
            $record->available = !empty($data['available']) ? 1 : 0;
        }
        $record->timemodified = $now;
        if ($p) {
            $DB->update_record('academy_teacher_profiles', $record);
            $teacherid = $userid;
        } else {
            if (!isset($record->approved)) { $record->approved = 1; }
            if (!isset($record->available)) { $record->available = 1; }
            if (!isset($record->rating)) { $record->rating = 0; }
            $DB->insert_record('academy_teacher_profiles', $record);
            $teacherid = $userid;
        }

        // Replace subjects if provided.
        if (array_key_exists('subjects', $data) && is_array($data['subjects'])) {
            $DB->delete_records('academy_teacher_subjects', array('teacherid' => $teacherid));
            foreach ($data['subjects'] as $s) {
                $subject = trim($s['subject'] ?? '');
                if ($subject === '') { continue; }
                $DB->insert_record('academy_teacher_subjects', (object) array(
                    'teacherid' => $teacherid,
                    'subject' => $subject,
                    'specialization' => $s['specialization'] ?? '',
                ));
            }
        }

        // Replace years if provided.
        if (array_key_exists('years', $data) && is_array($data['years'])) {
            $DB->delete_records('academy_teacher_years', array('teacherid' => $teacherid));
            foreach ($data['years'] as $y) {
                $year = trim((string)$y);
                if ($year === '') { continue; }
                $DB->insert_record('academy_teacher_years', (object) array(
                    'teacherid' => $teacherid,
                    'year'      => $year,
                ));
            }
        }

        // Replace working hours if provided (validate no overlap within a day).
        if (array_key_exists('hours', $data) && is_array($data['hours'])) {
            self::validate_hours($data['hours']);
            $DB->delete_records('academy_teacher_hours', array('teacherid' => $teacherid));
            foreach ($data['hours'] as $h) {
                $DB->insert_record('academy_teacher_hours', (object) array(
                    'teacherid' => $teacherid,
                    'dayofweek' => (int)$h['dayofweek'],
                    'starttime' => $h['starttime'],
                    'endtime'   => $h['endtime'],
                ));
            }
        }

        return self::get_profile($userid);
    }

    /**
     * Admin: list all teachers with optional filters and pagination.
     *
     * Base set is every Moodle user with a teacher/editingteacher role archetype,
     * regardless of whether they have saved an academy profile yet.
     * Profile fields default to empty/1 for users without a profile row.
     *
     * @param array $filters  approved (0|1|''), available (0|1|''), subject, search, page, perpage
     * @return array          { total, page, perpage, teachers[] }
     */
    public static function get_all_teachers(array $filters = []) {
        global $DB;

        // Start from role_assignments so teachers without a profile row still appear.
        $where  = [
            'u.deleted = 0',
            "r.archetype IN ('teacher', 'editingteacher')",
        ];
        $params = [];

        // approved / available — use COALESCE so profile-less teachers default to 1
        if (isset($filters['approved']) && $filters['approved'] !== '') {
            $where[]            = 'COALESCE(p.approved, 1) = :approved';
            $params['approved'] = (int)$filters['approved'];
        }
        if (isset($filters['available']) && $filters['available'] !== '') {
            $where[]             = 'COALESCE(p.available, 1) = :available';
            $params['available'] = (int)$filters['available'];
        }

        // subject filter — teacher must have at least one matching subject
        if (!empty($filters['subject'])) {
            $where[]           = 'EXISTS (SELECT 1 FROM {academy_teacher_subjects} s
                                           WHERE s.teacherid = u.id
                                             AND ' . $DB->sql_like('s.subject', ':subject', false) . ')';
            $params['subject'] = '%' . $DB->sql_like_escape($filters['subject']) . '%';
        }

        // year filter — teacher must teach this year/grade level
        if (!empty($filters['year'])) {
            $where[]        = 'EXISTS (SELECT 1 FROM {academy_teacher_years} ty
                                        WHERE ty.teacherid = u.id
                                          AND ' . $DB->sql_like('ty.year', ':year', false) . ')';
            $params['year'] = '%' . $DB->sql_like_escape($filters['year']) . '%';
        }

        // courseid filter — teacher must hold a teacher role in that specific course
        if (!empty($filters['courseid'])) {
            $where[]            = 'EXISTS (SELECT 1 FROM {role_assignments} raf
                                            JOIN {context} ctx ON ctx.id = raf.contextid
                                                               AND ctx.contextlevel = 50
                                            JOIN {role} rf ON rf.id = raf.roleid
                                                          AND rf.archetype IN (\'teacher\', \'editingteacher\')
                                           WHERE raf.userid = u.id
                                             AND ctx.instanceid = :courseid)';
            $params['courseid'] = (int)$filters['courseid'];
        }

        // categoryid filter — teacher must teach in at least one course inside that category
        if (!empty($filters['categoryid'])) {
            $where[]               = 'EXISTS (SELECT 1 FROM {role_assignments} raf
                                               JOIN {context} ctx ON ctx.id = raf.contextid
                                                                  AND ctx.contextlevel = 50
                                               JOIN {course} c    ON c.id = ctx.instanceid
                                                                  AND c.category = :categoryid
                                               JOIN {role} rf     ON rf.id = raf.roleid
                                                                  AND rf.archetype IN (\'teacher\', \'editingteacher\')
                                              WHERE raf.userid = u.id)';
            $params['categoryid'] = (int)$filters['categoryid'];
        }

        // free-text search on name or email
        if (!empty($filters['search'])) {
            $q             = '%' . $DB->sql_like_escape($filters['search']) . '%';
            $where[]       = '(' . $DB->sql_like('u.firstname', ':sq1', false)
                           . ' OR ' . $DB->sql_like('u.lastname',  ':sq2', false)
                           . ' OR ' . $DB->sql_like('u.email',     ':sq3', false) . ')';
            $params['sq1'] = $q;
            $params['sq2'] = $q;
            $params['sq3'] = $q;
        }

        $whereClause = implode(' AND ', $where);
        $baseSql     = "FROM {user} u
                        JOIN {role_assignments} ra ON ra.userid = u.id
                        JOIN {role} r              ON r.id = ra.roleid
                   LEFT JOIN {academy_teacher_profiles} p ON p.userid = u.id
                       WHERE $whereClause";

        $total   = (int)$DB->count_records_sql("SELECT COUNT(DISTINCT u.id) $baseSql", $params);
        $page    = max(0, (int)($filters['page']    ?? 0));
        $perpage = min(200, max(1, (int)($filters['perpage'] ?? 20)));

        $rows = $DB->get_records_sql(
            "SELECT DISTINCT u.id AS userid, u.firstname, u.lastname, u.email,
                    p.headline, p.bio, p.experience, p.photourl,
                    p.rating, p.approved, p.available
               $baseSql
              ORDER BY u.lastname ASC, u.firstname ASC",
            $params,
            $page * $perpage,
            $perpage
        );

        $teachers = [];
        foreach ($rows as $row) {
            $user = (object)[
                'id'        => $row->userid,
                'firstname' => $row->firstname,
                'lastname'  => $row->lastname,
                'email'     => $row->email,
            ];
            // Build a profile object with safe defaults for teachers without a profile row.
            $p = (object)[
                'userid'     => $row->userid,
                'headline'   => $row->headline   ?? '',
                'bio'        => $row->bio         ?? '',
                'experience' => $row->experience  ?? '',
                'photourl'   => $row->photourl    ?? '',
                'rating'     => $row->rating      ?? 0,
                'approved'   => $row->approved    ?? 1,
                'available'  => $row->available   ?? 1,
            ];
            $teachers[] = self::format_profile($p, $user);
        }

        return ['total' => $total, 'page' => $page, 'perpage' => $perpage, 'teachers' => $teachers];
    }

    /** US-ST-2-1: list approved + available teachers, optionally filtered by subject. */
    public static function browse_teachers($subject = '') {
        global $DB;
        $profiles = $DB->get_records('academy_teacher_profiles',
            array('approved' => 1, 'available' => 1));
        $out = array();
        foreach ($profiles as $p) {
            $user = $DB->get_record('user', array('id' => $p->userid), 'id, firstname, lastname, email');
            if (!$user) { continue; }
            $formatted = self::format_profile($p, $user);
            if ($subject !== '') {
                $match = false;
                foreach ($formatted['subjects'] as $s) {
                    if (stripos($s['subject'], $subject) !== false) { $match = true; break; }
                }
                if (!$match) { continue; }
            }
            // Public browse: drop email.
            unset($formatted['email']);
            $out[] = $formatted;
        }
        return $out;
    }

    /** US-ST-2-1: a single teacher's public profile. */
    public static function get_teacher($teacherid) {
        global $DB;
        $p = $DB->get_record('academy_teacher_profiles',
            array('userid' => $teacherid, 'approved' => 1));
        if (!$p) {
            throw new \moodle_exception('err_teachernotfound', 'local_academy');
        }
        $user = $DB->get_record('user', array('id' => $teacherid), 'id, firstname, lastname, email');
        $formatted = self::format_profile($p, $user);
        unset($formatted['email']);
        return $formatted;
    }

    // ── helpers ──

    private static function format_profile($p, $user) {
        global $DB;
        $teacherid = $p->userid;
        $subjects = array_values($DB->get_records('academy_teacher_subjects',
            array('teacherid' => $teacherid), 'subject ASC', 'id, subject, specialization'));
        $hours = array_values($DB->get_records('academy_teacher_hours',
            array('teacherid' => $teacherid), 'dayofweek ASC, starttime ASC',
            'id, dayofweek, starttime, endtime'));
        $years = array_values($DB->get_records('academy_teacher_years',
            array('teacherid' => $teacherid), 'year ASC', 'id, year'));
        return array(
            'userid'     => (int)$teacherid,
            'fullname'   => $user ? trim($user->firstname . ' ' . $user->lastname) : '',
            'email'      => $user ? $user->email : '',
            'headline'   => $p->headline ?? '',
            'bio'        => $p->bio ?? '',
            'experience' => $p->experience ?? '',
            'photourl'   => $p->photourl ?? '',
            'rating'     => isset($p->rating) ? (float)$p->rating : 0,
            'approved'   => (int)($p->approved ?? 1),
            'available'  => (int)($p->available ?? 1),
            'subjects'   => array_map(function ($s) {
                return array('subject' => $s->subject, 'specialization' => $s->specialization);
            }, $subjects),
            'years'      => array_map(function ($y) { return $y->year; }, $years),
            'hours'      => array_map(function ($h) {
                return array('dayofweek' => (int)$h->dayofweek,
                    'starttime' => $h->starttime, 'endtime' => $h->endtime);
            }, $hours),
        );
    }

    private static function validate_hours(array $hours) {
        // Group by day, ensure intervals don't overlap.
        $byday = array();
        foreach ($hours as $h) {
            $day = (int)$h['dayofweek'];
            $start = self::to_minutes($h['starttime']);
            $end = self::to_minutes($h['endtime']);
            if ($start === null || $end === null || $end <= $start) {
                throw new \moodle_exception('err_badhours', 'local_academy');
            }
            $byday[$day][] = array($start, $end);
        }
        foreach ($byday as $intervals) {
            usort($intervals, function ($a, $b) { return $a[0] - $b[0]; });
            for ($i = 1; $i < count($intervals); $i++) {
                if ($intervals[$i][0] < $intervals[$i - 1][1]) {
                    throw new \moodle_exception('err_hoursoverlap', 'local_academy');
                }
            }
        }
    }

    private static function to_minutes($hhmm) {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', (string)$hhmm, $m)) {
            return null;
        }
        $h = (int)$m[1]; $min = (int)$m[2];
        if ($h > 23 || $min > 59) { return null; }
        return $h * 60 + $min;
    }
}
