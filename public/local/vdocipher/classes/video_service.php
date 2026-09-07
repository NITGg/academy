<?php
namespace local_vdocipher;

defined('MOODLE_INTERNAL') || die();

/**
 * Business logic for VdoCipher videos: create (upload credentials), refresh
 * status, list, attach to an activity, and delete — each guarded by the
 * local/vdocipher:manage capability in the relevant context.
 *
 * Playback (OTP + watermark) lives in {@see playback_service}; this class is the
 * teacher/manager CRUD side.
 */
class video_service {

    /** @var string DB table. */
    const TABLE = 'local_vdocipher_videos';

    /** @var int Seconds between two API probes for the same video's playing time. */
    const LENGTH_PROBE_INTERVAL = 600;

    /** @var int How many videos one page request is allowed to probe. */
    const LENGTH_PROBE_PER_REQUEST = 1;

    /** @var int Network timeout for a probe made while a page is rendering. */
    const LENGTH_PROBE_TIMEOUT = 5;

    /** @var array Per-request memo of cmid => mapping row, or null for "no video". */
    protected static $rowmemo = [];

    /** @var bool[] Courses whose rows have already been loaded in one go. */
    protected static $preloaded = [];

    /** @var int Probes already spent in this request. */
    protected static $probesused = 0;

    /**
     * Obtain S3 upload credentials for a new video and record a pending row.
     *
     * The caller uploads the file bytes straight to VdoCipher's S3 link using the
     * returned payload — the bytes never pass through Moodle.
     *
     * @param string $title  human title shown in the dashboard
     * @param int $courseid  course the video belongs to (0 = site-level, admins only)
     * @param int $cmid      course module to attach to now (0 = attach later)
     * @return array ['videoid'=>…, 'upload'=>[…S3 payload…], 'rowid'=>…]
     */
    public static function create_upload(string $title, int $courseid = 0, int $cmid = 0): array {
        global $DB, $USER;

        self::require_manage(self::context_for($courseid));

        $title = trim($title) !== '' ? trim($title) : 'Untitled video';

        $client = new api_client();
        $result = $client->get_upload_credentials($title);

        $videoid = (string) ($result['videoId'] ?? '');
        if ($videoid === '') {
            throw new api_exception('VdoCipher did not return a videoId', 0, json_encode($result));
        }

        $now = time();
        $record = (object) [
            'videoid'      => $videoid,
            'cmid'         => $cmid,
            'courseid'     => $courseid,
            'title'        => \core_text::substr($title, 0, 255),
            'status'       => 'PRE-Upload',
            'length'       => 0,
            'usermodified' => (int) $USER->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);

        return [
            'videoid' => $videoid,
            'rowid'   => (int) $record->id,
            'upload'  => $result['clientPayload'] ?? $result,
        ];
    }

    /**
     * Re-fetch a video's status/length/title from VdoCipher and update our row.
     *
     * @param string $videoid
     * @return array normalized ['videoid','status','length','title']
     */
    public static function refresh_status(string $videoid): array {
        global $DB;

        $row = self::get_row($videoid);
        self::require_manage(self::context_for((int) $row->courseid));

        $client = new api_client();
        $video  = $client->get_video($videoid);

        $row->status       = (string) ($video['status'] ?? $row->status);
        $row->length       = (int) ($video['length'] ?? $row->length);
        $row->title        = \core_text::substr((string) ($video['title'] ?? $row->title), 0, 255);
        $row->timemodified = time();
        $DB->update_record(self::TABLE, $row);

        return [
            'videoid' => $videoid,
            'status'  => $row->status,
            'length'  => (int) $row->length,
            'title'   => $row->title,
        ];
    }

    /**
     * List videos, optionally scoped to a course.
     *
     * @param int $courseid 0 = all (site-level manage required)
     * @return array list of row dicts
     */
    public static function list_videos(int $courseid = 0): array {
        global $DB;

        self::require_manage(self::context_for($courseid));

        $conditions = $courseid ? ['courseid' => $courseid] : [];
        $rows = $DB->get_records(self::TABLE, $conditions, 'timecreated DESC');

        return array_values(array_map(static function ($r) {
            return [
                'id'       => (int) $r->id,
                'videoid'  => $r->videoid,
                'cmid'     => (int) $r->cmid,
                'courseid' => (int) $r->courseid,
                'title'    => $r->title,
                'status'   => $r->status,
                'length'   => (int) $r->length,
            ];
        }, $rows));
    }

    /**
     * Attach an existing video to a course module (used by the resource2 form).
     *
     * @param string $videoid
     * @param int $cmid
     * @return array the updated row dict
     */
    public static function attach(string $videoid, int $cmid): array {
        global $DB;

        $row = self::get_row($videoid);
        $cm  = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);

        self::require_manage(\context_course::instance($cm->course));

        $row->cmid         = $cmid;
        $row->courseid     = (int) $cm->course;
        $row->timemodified = time();
        $DB->update_record(self::TABLE, $row);

        return [
            'videoid'  => $videoid,
            'cmid'     => (int) $row->cmid,
            'courseid' => (int) $row->courseid,
        ];
    }

    /**
     * Delete a video from VdoCipher and remove our row.
     *
     * @param string $videoid
     * @return bool
     */
    public static function delete_video(string $videoid): bool {
        global $DB;

        $row = self::get_row($videoid);
        self::require_manage(self::context_for((int) $row->courseid));

        $client = new api_client();
        $client->delete_videos([$videoid]);

        $DB->delete_records(self::TABLE, ['id' => $row->id]);
        return true;
    }

    // ── Playing time ─────────────────────────────────────────────────────────

    /**
     * Playing time of the video attached to a course module, in seconds.
     *
     * Read-only and deliberately capability-free: this is the length printed
     * beside the activity name on the course page, so every visitor of that page
     * asks for it. It exposes nothing but the number — playback still goes
     * through {@see playback_service}, which does check access.
     *
     * VdoCipher only knows a length once it has finished transcoding, so a row
     * written at upload time carries 0 for the first few minutes. The scheduled
     * task {@see \local_vdocipher\task\refresh_lengths} fills those in the
     * background; the throttled probe below is the safety net for a site whose
     * cron has fallen behind, and costs at most one API call per request.
     *
     * @param int $cmid course module id
     * @param int $courseid the module's course, so a course page costs one query
     *                      for all of its videos instead of one for each
     * @return int|null seconds, or null while the length is still unknown
     */
    public static function length_for_cm(int $cmid, int $courseid = 0): ?int {
        $row = self::mapping_for_cm($cmid, $courseid);
        if (!$row) {
            return null;
        }

        $length = (int) $row->length;
        if ($length <= 0) {
            $length = self::probe_length($row);
            // Write it back into the memo: a probe that learned something must
            // not be spent again on the next question about the same module.
            $row->length = $length;
        }

        return $length > 0 ? $length : null;
    }

    /**
     * The mapping row for a course module, remembered for the rest of the request.
     *
     * Given a course id this loads that whole course's videos in one query, which
     * is the shape every caller actually has: a course page asks about each of
     * its activities in turn. Without it a section of twenty videos would be
     * twenty round trips for twenty small rows.
     *
     * @param int $cmid
     * @param int $courseid 0 when the caller does not know it
     * @return \stdClass|null
     */
    protected static function mapping_for_cm(int $cmid, int $courseid = 0): ?\stdClass {
        global $DB;

        if ($courseid > 0 && !isset(self::$preloaded[$courseid])) {
            self::$preloaded[$courseid] = true;
            $rows = $DB->get_records(self::TABLE, ['courseid' => $courseid], '',
                'id, cmid, videoid, status, length, timemodified');
            foreach ($rows as $row) {
                if ((int) $row->cmid > 0 && !array_key_exists((int) $row->cmid, self::$rowmemo)) {
                    self::$rowmemo[(int) $row->cmid] = $row;
                }
            }
        }

        // Still unanswered: either no course id, or a row whose courseid is stale
        // because the activity was moved. One targeted query settles it.
        if (!array_key_exists($cmid, self::$rowmemo)) {
            self::$rowmemo[$cmid] = $DB->get_record(self::TABLE, ['cmid' => $cmid],
                'id, cmid, videoid, status, length, timemodified') ?: null;
        }

        return self::$rowmemo[$cmid];
    }

    /**
     * Ask VdoCipher for one row's length, no more often than the throttle allows.
     *
     * The row's own timemodified is the stamp: a probe writes it back whether or
     * not it learned anything, so a video still being processed is re-asked on a
     * fixed cadence rather than on every single page view. The per-request cap
     * keeps a section full of freshly uploaded videos from turning one course
     * page into a queue of API calls.
     *
     * @param \stdClass $row a local_vdocipher_videos row
     * @return int seconds, or 0 when still unknown
     */
    protected static function probe_length(\stdClass $row): int {
        if (self::$probesused >= self::LENGTH_PROBE_PER_REQUEST
                || time() - (int) $row->timemodified < self::LENGTH_PROBE_INTERVAL
                || !api_client::is_configured()) {
            return 0;
        }

        self::$probesused++;
        return self::fetch_length($row, self::LENGTH_PROBE_TIMEOUT);
    }

    /**
     * Read a row's status and length straight from VdoCipher and store them.
     *
     * Unlike {@see refresh_status} this asks for no capability, because its
     * callers — the course page and cron — act for nobody in particular. It
     * never throws either: an unreachable API just means "length still unknown",
     * which is exactly how a video mid-transcode already reads.
     *
     * @param \stdClass $row a local_vdocipher_videos row (id, videoid, status, length)
     * @param int $timeout network timeout in seconds; 0 keeps the client default
     * @return int seconds, or 0 when the length is still unknown
     */
    public static function fetch_length(\stdClass $row, int $timeout = 0): int {
        global $DB;

        $known  = (int) $row->length;
        $length = 0;
        $status = (string) $row->status;

        try {
            $client = new api_client();
            if ($timeout > 0) {
                $client->set_timeout($timeout);
            }
            $video  = $client->get_video((string) $row->videoid);
            $length = max(0, (int) ($video['length'] ?? 0));
            $status = (string) ($video['status'] ?? $status);
        } catch (\Throwable $e) {
            debugging('local_vdocipher: could not read the length of ' . $row->videoid
                . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Stamp the row either way — timemodified is what throttles the next probe.
        $DB->update_record(self::TABLE, (object) [
            'id'           => (int) $row->id,
            'status'       => \core_text::substr($status, 0, 32),
            'length'       => $length ?: $known,
            'timemodified' => time(),
        ]);

        return $length ?: $known;
    }

    /**
     * Rows whose playing time we still do not know, least recently probed first.
     *
     * Ordering by timemodified makes a batch round-robin: every run stamps the
     * rows it touched, so the next run reaches the ones behind them instead of
     * asking about the same stuck video forever.
     *
     * @param int $limit how many rows to return
     * @return \stdClass[]
     */
    public static function rows_missing_length(int $limit = 20): array {
        global $DB;

        return $DB->get_records_select(self::TABLE, 'length <= 0 AND videoid <> ?', [''],
            'timemodified ASC', 'id, videoid, status, length, timemodified', 0, $limit);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Fetch our mapping row or fail.
     *
     * @param string $videoid
     * @return \stdClass
     */
    public static function get_row(string $videoid): \stdClass {
        global $DB;
        $row = $DB->get_record(self::TABLE, ['videoid' => $videoid]);
        if (!$row) {
            throw new api_exception(get_string('err_novideo', 'local_vdocipher'));
        }
        return $row;
    }

    /**
     * Context for a course id (course context, or system when 0).
     *
     * @param int $courseid
     * @return \context
     */
    protected static function context_for(int $courseid): \context {
        if ($courseid && $courseid != SITEID) {
            return \context_course::instance($courseid);
        }
        return \context_system::instance();
    }

    /**
     * Require the manage capability in the given context.
     *
     * @param \context $context
     */
    protected static function require_manage(\context $context): void {
        require_capability('local/vdocipher:manage', $context);
    }
}
