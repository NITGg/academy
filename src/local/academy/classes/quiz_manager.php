<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Quiz API for the Academy platform.
 *
 * Covers: list quizzes, get quiz questions (structured JSON),
 * start attempt, submit answers, review attempt.
 *
 * Supported question types: multichoice, truefalse.
 * Unsupported types appear in the response with supported=false and no options.
 */
class quiz_manager {

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Check if a user is enrolled in a course. Throws if not.
     */
    private static function require_enrolled(int $userid, int $courseid): void {
        $context = \context_course::instance($courseid);
        if (!is_enrolled($context, $userid, '', true)) {
            throw new \moodle_exception('notenrolled', 'local_academy', '', null, 'You are not enrolled in this course');
        }
    }

    /**
     * List quizzes. Admins see all; students see only their enrolled courses.
     */
    public static function get_quizzes(int $userid, bool $is_admin = false, int $courseid = 0): array {
        global $DB;

        if ($is_admin) {
            $quizzes = $courseid
                ? $DB->get_records('quiz', ['course' => $courseid], 'name ASC', 'id, course, name, intro, timelimit, attempts')
                : $DB->get_records('quiz', [],                       'name ASC', 'id, course, name, intro, timelimit, attempts');
        } else {
            // Only return quizzes from courses the student is enrolled in.
            $sql = "SELECT q.id, q.course, q.name, q.intro, q.timelimit, q.attempts
                      FROM {quiz} q
                      JOIN {enrol} e       ON e.courseid = q.course AND e.status = 0
                      JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = :userid AND ue.status = 0
                     WHERE 1=1" . ($courseid ? " AND q.course = :courseid" : "") . "
                  ORDER BY q.name ASC";
            $params = ['userid' => $userid];
            if ($courseid) { $params['courseid'] = $courseid; }
            $quizzes = $DB->get_records_sql($sql, $params);
        }

        $out = [];
        foreach ($quizzes as $q) {
            $cm = get_coursemodule_from_instance('quiz', $q->id, $q->course);
            $out[] = [
                'quizid'           => (int)$q->id,
                'cmid'             => $cm ? (int)$cm->id : null,
                'courseid'         => (int)$q->course,
                'name'             => $q->name,
                'intro'            => strip_tags($q->intro ?? ''),
                'timelimit'        => (int)$q->timelimit,
                'attempts_allowed' => (int)$q->attempts,
            ];
        }
        return $out;
    }

    /**
     * Get a single quiz with all questions structured as JSON.
     *
     * @param int  $cmid         Course module id
     * @param bool $show_correct Include correct flag on each answer option (admin only)
     */
    public static function get_quiz(int $cmid, int $userid, bool $show_correct = false, bool $is_admin = false): array {
        global $DB;

        $cm   = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

        if (!$is_admin) {
            self::require_enrolled($userid, $cm->course);
        }
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot ASC');

        $questions = [];
        foreach ($slots as $slot) {
            $q = $DB->get_record('question', ['id' => $slot->questionid]);
            if (!$q) { continue; }
            $questions[] = self::format_question($q, (int)$slot->slot, $show_correct);
        }

        return [
            'quizid'           => (int)$quiz->id,
            'cmid'             => (int)$cmid,
            'courseid'         => (int)$cm->course,
            'name'             => $quiz->name,
            'intro'            => strip_tags($quiz->intro ?? ''),
            'timelimit'        => (int)$quiz->timelimit,
            'attempts_allowed' => (int)$quiz->attempts,
            'questions'        => $questions,
        ];
    }

    /**
     * Start a new quiz attempt for a user.
     */
    public static function start_attempt(int $quizid, int $userid): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);

        // Ensure the student is enrolled in the course that contains this quiz.
        self::require_enrolled($userid, $quiz->course);

        // Check attempt limit (0 = unlimited).
        if ($quiz->attempts > 0) {
            $done = $DB->count_records('quiz_attempts', ['quiz' => $quizid, 'userid' => $userid, 'state' => 'finished']);
            if ($done >= $quiz->attempts) {
                throw new \moodle_exception('attemptsexhausted', 'quiz');
            }
        }

        $quizobj       = \quiz::create($quizid, $userid);
        $attempts      = quiz_get_user_attempts($quizid, $userid, 'all', true);
        $lastattempt   = end($attempts) ?: null;
        $attemptnumber = count($attempts) + 1;
        $attempt       = quiz_prepare_and_start_new_attempt($quizobj, $attemptnumber, $lastattempt);

        return [
            'attemptid'        => (int)$attempt->id,
            'quizid'           => (int)$quizid,
            'attempt_number'   => (int)$attempt->attempt,
            'timestart'        => (int)$attempt->timestart,
            'timelimit'        => (int)$quiz->timelimit,
            'state'            => $attempt->state,
        ];
    }

    /**
     * Submit answers and finish a quiz attempt.
     *
     * $answers format:
     *   For single-answer MCQ or true/false: { "questionid": 101, "answer": 3 }  (answer = answer row id)
     *   For multi-answer MCQ:                { "questionid": 102, "answer": [3, 5] }
     */
    public static function submit_attempt(int $attemptid, int $userid, array $answers): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $attemptrow = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);

        if ((int)$attemptrow->userid !== $userid) {
            throw new \moodle_exception('notyourattempt', 'quiz');
        }
        if ($attemptrow->state === 'finished') {
            throw new \moodle_exception('attemptalreadyclosed', 'quiz');
        }

        $quiz  = $DB->get_record('quiz', ['id' => $attemptrow->quiz], '*', MUST_EXIST);
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot ASC');

        // Map questionid → slot number.
        $qid_to_slot = [];
        foreach ($slots as $s) { $qid_to_slot[(int)$s->questionid] = (int)$s->slot; }

        // Index submitted answers by questionid.
        $submitted = [];
        foreach ($answers as $a) { $submitted[(int)$a['questionid']] = $a['answer']; }

        // Grade each question and accumulate score.
        $results   = [];
        $sumgrades = 0.0;

        foreach ($slots as $slot) {
            $q        = $DB->get_record('question', ['id' => $slot->questionid]);
            $maxmark  = (float)$slot->maxmark;
            $answer   = $submitted[(int)$q->id] ?? null;
            $mark     = 0.0;
            $correct  = false;

            if ($answer !== null && in_array($q->qtype, ['multichoice', 'truefalse'])) {
                $allAnswers = array_values($DB->get_records('question_answers', ['question' => $q->id], 'id ASC'));

                if ($q->qtype === 'truefalse' || !is_array($answer)) {
                    // Single answer: find the fraction for the chosen answer id.
                    foreach ($allAnswers as $a) {
                        if ((int)$a->id === (int)$answer) {
                            $frac    = (float)$a->fraction;
                            $mark    = max(0, $frac * $maxmark);
                            $correct = $frac >= 1.0;
                            break;
                        }
                    }
                } else {
                    // Multi-answer MCQ: sum fractions of chosen answers.
                    $frac = 0.0;
                    foreach ($allAnswers as $a) {
                        if (in_array((int)$a->id, array_map('intval', $answer))) {
                            $frac += (float)$a->fraction;
                        }
                    }
                    $mark    = max(0, round($frac * $maxmark, 5));
                    $correct = $frac >= 1.0;
                }
            }

            $sumgrades += $mark;
            $results[] = [
                'questionid' => (int)$q->id,
                'type'       => $q->qtype,
                'mark'       => round($mark, 2),
                'max_mark'   => round($maxmark, 2),
                'correct'    => $correct,
            ];
        }

        // Mark attempt as finished.
        $now = time();
        $DB->update_record('quiz_attempts', (object)[
            'id'         => $attemptid,
            'state'      => 'finished',
            'timefinish' => $now,
            'timemodified' => $now,
            'sumgrades'  => $sumgrades,
        ]);

        // Update gradebook.
        quiz_save_best_grade($quiz, $userid);

        $maxscore = array_sum(array_column(array_map('get_object_vars', $slots), 'maxmark'));

        return [
            'attemptid' => $attemptid,
            'state'     => 'finished',
            'score'     => round($sumgrades, 2),
            'max_score' => round((float)$maxscore, 2),
            'percent'   => $maxscore > 0 ? round(($sumgrades / $maxscore) * 100, 1) : 0,
            'results'   => $results,
        ];
    }

    /**
     * Review a finished attempt.
     *
     * @param bool $show_correct Include correct answers (admin only)
     */
    public static function get_attempt(int $attemptid, int $userid, bool $is_admin = false): array {
        global $DB;

        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
        if (!$is_admin && (int)$attempt->userid !== $userid) {
            throw new \moodle_exception('notyourattempt', 'quiz');
        }

        $quiz  = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);
        $slots = $DB->get_records('quiz_slots', ['quizid' => $quiz->id], 'slot ASC');
        $maxscore = 0.0;
        $questions = [];

        foreach ($slots as $slot) {
            $q       = $DB->get_record('question', ['id' => $slot->questionid]);
            $maxmark = (float)$slot->maxmark;
            $maxscore += $maxmark;

            $qdata = [
                'slot'       => (int)$slot->slot,
                'questionid' => (int)$q->id,
                'type'       => $q->qtype,
                'text'       => strip_tags($q->questiontext),
                'max_mark'   => round($maxmark, 2),
            ];

            if ($is_admin && in_array($q->qtype, ['multichoice', 'truefalse'])) {
                $allAnswers = $DB->get_records('question_answers', ['question' => $q->id], 'id ASC');
                $qdata['correct_answers'] = array_values(array_map(function($a) {
                    return ['id' => (int)$a->id, 'text' => strip_tags($a->answer), 'correct' => (float)$a->fraction > 0];
                }, $allAnswers));
            }

            $questions[] = $qdata;
        }

        return [
            'attemptid'      => (int)$attempt->id,
            'quizid'         => (int)$quiz->id,
            'quiz_name'      => $quiz->name,
            'attempt_number' => (int)$attempt->attempt,
            'state'          => $attempt->state,
            'timestart'      => (int)$attempt->timestart,
            'timefinish'     => (int)$attempt->timefinish,
            'score'          => round((float)($attempt->sumgrades ?? 0), 2),
            'max_score'      => round($maxscore, 2),
            'percent'        => $maxscore > 0 ? round(((float)($attempt->sumgrades ?? 0) / $maxscore) * 100, 1) : 0,
            'questions'      => $questions,
        ];
    }

    /**
     * List all attempts by the current user on a quiz.
     */
    public static function get_my_attempts(int $quizid, int $userid): array {
        global $DB;

        $attempts = $DB->get_records('quiz_attempts',
            ['quiz' => $quizid, 'userid' => $userid], 'attempt ASC');

        $quiz     = $DB->get_record('quiz', ['id' => $quizid], 'id, name, attempts');
        $slots    = $DB->get_records('quiz_slots', ['quizid' => $quizid], '', 'id, maxmark');
        $maxscore = array_sum(array_column(array_map('get_object_vars', $slots), 'maxmark'));

        $out = [];
        foreach ($attempts as $a) {
            $out[] = [
                'attemptid'      => (int)$a->id,
                'attempt_number' => (int)$a->attempt,
                'state'          => $a->state,
                'score'          => isset($a->sumgrades) ? round((float)$a->sumgrades, 2) : null,
                'max_score'      => round((float)$maxscore, 2),
                'percent'        => ($maxscore > 0 && isset($a->sumgrades))
                    ? round(((float)$a->sumgrades / $maxscore) * 100, 1) : null,
                'timestart'      => (int)$a->timestart,
                'timefinish'     => (int)$a->timefinish,
            ];
        }
        return $out;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function format_question(\stdClass $q, int $slot, bool $show_correct): array {
        $base = [
            'slot'        => $slot,
            'questionid'  => (int)$q->id,
            'type'        => $q->qtype,
            'text'        => strip_tags($q->questiontext),
            'defaultmark' => (float)$q->defaultmark,
            'supported'   => in_array($q->qtype, ['multichoice', 'truefalse']),
        ];

        switch ($q->qtype) {
            case 'multichoice': return array_merge($base, self::format_multichoice($q, $show_correct));
            case 'truefalse':   return array_merge($base, self::format_truefalse($q, $show_correct));
            default:            return $base;
        }
    }

    private static function format_multichoice(\stdClass $q, bool $show_correct): array {
        global $DB;
        $opts    = $DB->get_record('qtype_multichoice_options', ['questionid' => $q->id]);
        $answers = $DB->get_records('question_answers', ['question' => $q->id], 'id ASC');

        $options = [];
        foreach ($answers as $a) {
            $opt = ['id' => (int)$a->id, 'text' => strip_tags($a->answer)];
            if ($show_correct) { $opt['correct'] = (float)$a->fraction > 0; }
            $options[] = $opt;
        }

        return [
            'single'  => $opts ? (bool)$opts->single : true,
            'options' => $options,
        ];
    }

    private static function format_truefalse(\stdClass $q, bool $show_correct): array {
        global $DB;
        $answers = $DB->get_records('question_answers', ['question' => $q->id], 'id ASC');

        $options = [];
        foreach ($answers as $a) {
            $opt = ['id' => (int)$a->id, 'text' => strip_tags($a->answer)];
            if ($show_correct) { $opt['correct'] = (float)$a->fraction > 0; }
            $options[] = $opt;
        }

        return ['options' => $options];
    }
}
