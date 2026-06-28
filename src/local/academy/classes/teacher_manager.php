<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Teacher profiles, subjects, working hours, and public browse.
 *
 * Covers US-TR-1-1 (update profile) and US-ST-2-1 (browse teachers).
 */
class teacher_manager {

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
