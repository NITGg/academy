<?php
namespace local_vdocipher;

defined('MOODLE_INTERNAL') || die();

/**
 * Playback side of VdoCipher: mints a short-lived OTP for a given activity, with
 * the *viewer's* identity baked into a dynamic watermark.
 *
 * The watermark text is built here, server-side, from the requesting user — so a
 * client can never forge or strip it, and every stream carries the identity of
 * whoever requested it.
 */
class playback_service {

    /**
     * Build an OTP + playbackInfo for the video attached to a course module,
     * after verifying the user may view it.
     *
     * Minting the OTP is the app's way of opening the activity — it never loads
     * mod/vdocipher/view.php — so once the OTP exists the activity is also marked
     * viewed for the user, exactly as view.php does for the website. That is
     * what completes an "automatic, require view" activity and unlocks whatever
     * depends on it; without it an app user could never complete a video.
     *
     * @param int $cmid course module id of the activity carrying the video
     * @param \stdClass $user the viewer (token owner)
     * @return array ['videoid','otp','playbackInfo','watermark','ttl','viewed','completionstate']
     */
    public static function get_playback(int $cmid, \stdClass $user): array {
        global $DB;

        $row = $DB->get_record(video_service::TABLE, ['cmid' => $cmid]);
        if (!$row) {
            throw new api_exception(get_string('err_novideo', 'local_vdocipher'));
        }

        $cm = self::require_view($cmid, $user);

        $data = self::mint($row->videoid, $user);

        // Only after the OTP exists: a view is recorded for a video the user was
        // actually granted, and a hiccup while recording it never costs them the
        // playback (record_view() swallows its own failures).
        return $data + self::record_view($cm, $user);
    }

    /**
     * Record that the user viewed the activity, without minting an OTP.
     *
     * The explicit door for a client that already holds a playable OTP. Most
     * clients never need it — {@see get_playback} records the view itself — but
     * it is the same check and the same write, so calling both is harmless.
     *
     * @param int $cmid course module id of the activity carrying the video
     * @param \stdClass $user the viewer (token owner)
     * @return array ['cmid','viewed','completionstate']
     */
    public static function mark_viewed(int $cmid, \stdClass $user): array {
        global $DB;

        if (!$DB->record_exists(video_service::TABLE, ['cmid' => $cmid])) {
            throw new api_exception(get_string('err_novideo', 'local_vdocipher'));
        }

        $cm = self::require_view($cmid, $user);

        return ['cmid' => $cmid] + self::record_view($cm, $user);
    }

    /**
     * Mint a watermarked OTP for a video WITHOUT an access check.
     *
     * The caller MUST have already verified the user may view it (e.g. the token
     * API via {@see get_playback}, or the web player via require_login +
     * require_capability). This is the shared OTP-building step.
     *
     * @param string $videoid
     * @param \stdClass $user the viewer whose identity is watermarked
     * @return array ['videoid','otp','playbackInfo','watermark','ttl']
     */
    public static function mint(string $videoid, \stdClass $user): array {
        $ttl = (int) get_config('local_vdocipher', 'otpttl');
        if ($ttl <= 0) {
            $ttl = 300;
        }

        $watermark = self::watermark_text($user);
        $annotate  = self::build_annotate($watermark);

        $client = new api_client();
        $result = $client->create_otp($videoid, $ttl, $annotate);

        return [
            'videoid'      => $videoid,
            'otp'          => $result['otp'] ?? '',
            'playbackInfo' => $result['playbackInfo'] ?? '',
            'watermark'    => $watermark,
            'ttl'          => $ttl,
        ];
    }

    /**
     * Verify the user may view this activity: it must exist, be visible/available
     * to them, and they must be enrolled (or hold a teaching/manage capability).
     *
     * @param int $cmid
     * @param \stdClass $user
     * @return \stdClass the course module record (as get_coursemodule_from_id returns it)
     */
    protected static function require_view(int $cmid, \stdClass $user): \stdClass {
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        $coursecontext = \context_course::instance($cm->course);
        $modcontext    = \context_module::instance($cm->id);

        // Teachers / managers who can manage videos always pass.
        if (has_capability('local/vdocipher:manage', $modcontext, $user)) {
            return $cm;
        }

        // Otherwise: must be enrolled (active) and hold the view capability, and
        // the activity must actually be visible/available to this user.
        $enrolled = is_enrolled($coursecontext, $user, '', true);
        $canview  = has_capability('local/vdocipher:view', $modcontext, $user);
        if (!$enrolled || !$canview) {
            throw new api_exception(get_string('err_noaccess', 'local_vdocipher'));
        }

        $modinfo = get_fast_modinfo($cm->course, $user->id);
        $cminfo  = $modinfo->get_cm($cm->id);
        if (!$cminfo->uservisible) {
            throw new api_exception(get_string('err_noaccess', 'local_vdocipher'));
        }

        return $cm;
    }

    /**
     * Do what mod/vdocipher/view.php does on a page load, for a user who plays
     * through the token API instead: flag the module viewed for completion and
     * log the module's course_module_viewed event.
     *
     * Runs as the given user, never $USER (the API has no page session). Every
     * failure is swallowed: the caller has already minted the OTP, and completion
     * being off, the module having no event class, or a log-store hiccup are no
     * reason to withhold the video. set_module_viewed() is a no-op unless the
     * activity has "require view" turned on, and idempotent once viewed, so
     * calling it on every OTP mint (retries included) is fine.
     *
     * @param \stdClass $cm course module record (as get_coursemodule_from_id returns it)
     * @param \stdClass $user the viewer
     * @return array ['viewed' => bool the view was recorded,
     *                'completionstate' => int|null the user's completion state for the
     *                activity afterwards (COMPLETION_COMPLETE = 1), null when completion
     *                is not enabled for it]
     */
    protected static function record_view(\stdClass $cm, \stdClass $user): array {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $result = ['viewed' => false, 'completionstate' => null];

        try {
            $course  = get_course($cm->course);
            $context = \context_module::instance($cm->id);
        } catch (\Throwable $e) {
            debugging('local_vdocipher: could not load cm ' . $cm->id . ' to record a view: '
                . $e->getMessage(), DEBUG_DEVELOPER);
            return $result;
        }

        // The completion flag is the part that matters — it is the only thing the
        // "require view" rule ever reads.
        try {
            $completion = new \completion_info($course);
            $completion->set_module_viewed($cm, $user->id);
            $result['viewed'] = true;
            if ($completion->is_enabled($cm)) {
                $result['completionstate'] = (int) $completion->get_data($cm, false, $user->id)->completionstate;
            }
        } catch (\Throwable $e) {
            debugging('local_vdocipher: could not mark cm ' . $cm->id . ' viewed for user '
                . $user->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // The module's own viewed event (mod_vdocipher defines one), so the
        // activity's logs and participation report see the play like a page view.
        try {
            $eventclass = '\\mod_' . $cm->modname . '\\event\\course_module_viewed';
            if (class_exists($eventclass)) {
                $event = $eventclass::create([
                    'objectid' => $cm->instance,
                    'context'  => $context,
                    'userid'   => $user->id,
                ]);
                $event->add_record_snapshot('course_modules', $cm);
                $event->add_record_snapshot('course', $course);
                $event->trigger();
            }
        } catch (\Throwable $e) {
            debugging('local_vdocipher: could not log the view of cm ' . $cm->id . ' for user '
                . $user->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return $result;
    }

    /**
     * Resolve the watermark template against the viewer.
     *
     * @param \stdClass $user
     * @return string
     */
    protected static function watermark_text(\stdClass $user): string {
        $template = (string) get_config('local_vdocipher', 'watermarktext');
        if (trim($template) === '') {
            $template = '{fullname} · {email}';
        }
        return strtr($template, [
            '{fullname}' => fullname($user),
            '{email}'    => $user->email ?? '',
            '{userid}'   => (string) $user->id,
        ]);
    }

    /**
     * Build the VdoCipher "annotate" payload (a stringified JSON array) for a
     * moving rtext watermark. Returns '' when watermarking is disabled.
     *
     * @param string $text
     * @return string
     */
    protected static function build_annotate(string $text): string {
        if (!get_config('local_vdocipher', 'watermarkenabled')) {
            return '';
        }
        $alpha = (string) get_config('local_vdocipher', 'watermarkalpha');
        if ($alpha === '') {
            $alpha = '0.60';
        }
        $size = (int) get_config('local_vdocipher', 'watermarksize');
        if ($size <= 0) {
            $size = 15;
        }

        // rtext = roaming text that moves around the frame, hardest to crop out.
        $annotation = [[
            'type'     => 'rtext',
            'text'     => $text,
            'alpha'    => $alpha,
            'color'    => '0xFFFFFF',
            'size'     => $size,
            'interval' => 5000,
        ]];

        return json_encode($annotation);
    }
}
