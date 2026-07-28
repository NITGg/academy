<?php
namespace local_academy\cert;

defined('MOODLE_INTERNAL') || die();

/**
 * The one place that knows about mod_customcert.
 *
 * {@see eligibility_manager} decides WHO deserves a certificate and deliberately knows nothing about
 * any certificate plugin — see its SCOPE note. This class is the other half of that split: it turns an
 * eligible student into someone who can actually reach the real certificate, which mod_customcert
 * issues. Everything customcert-specific (finding its activities, resolving a cmid to its host course,
 * building the view URL) lives here and nowhere else, so a different provider later means replacing
 * this class only.
 *
 * WHY ACCESS NEEDS GRANTING
 * -------------------------
 * A Custom Certificate is an activity, so it must live INSIDE a course — but a program has no course
 * of its own. The admin therefore creates one host course ("Program certificates") holding a
 * customcert activity per program, and links it to the Academy certificate through
 * {academy_certificates}.externalref (the cmid). A student who becomes eligible is enrolled into that
 * host course so the activity is reachable; without the enrolment the download link would only lead to
 * an access-denied page.
 *
 * Everything here is best-effort: enrolling is a convenience, never a precondition for rendering. A
 * failure is logged with debugging() and swallowed — a student's certificate card must never break
 * because an enrolment could not be created.
 */
class customcert_link {

    /** @var array<string,bool> user+cmid pairs already granted in this request, to avoid repeat work. */
    private static $granted = [];

    /**
     * Is mod_customcert installed on this site?
     *
     * @return bool
     */
    public static function available(): bool {
        global $DB;
        return $DB->get_manager()->table_exists('customcert')
            && $DB->record_exists('modules', ['name' => 'customcert']);
    }

    /**
     * Every Custom Certificate activity on the site, for the admin's "linked activity" picker.
     *
     * Courses are discovered by a single query (there are only a handful of host courses in practice)
     * and each one's activities are then read through get_fast_modinfo, so names/visibility come from
     * the same cache the rest of Moodle uses.
     *
     * @return array list of ['cmid' => int, 'name' => string, 'courseid' => int, 'coursename' => string]
     */
    public static function list_activities(): array {
        global $DB;

        if (!self::available()) {
            return [];
        }

        $courseids = $DB->get_fieldset_sql('SELECT DISTINCT course FROM {customcert} ORDER BY course ASC');
        $out = [];
        foreach ($courseids as $courseid) {
            try {
                $modinfo = get_fast_modinfo((int)$courseid);
            } catch (\Throwable $e) {
                // A deleted/broken course must not hide the activities of the healthy ones.
                continue;
            }
            $coursename = format_string($modinfo->get_course()->fullname);
            foreach ($modinfo->get_instances_of('customcert') as $cm) {
                if (!empty($cm->deletioninprogress)) {
                    continue;
                }
                $out[] = [
                    'cmid'       => (int)$cm->id,
                    'name'       => format_string($cm->name),
                    'courseid'   => (int)$courseid,
                    'coursename' => $coursename,
                ];
            }
        }
        return $out;
    }

    /**
     * The course module record of a linked certificate activity.
     *
     * @param int $cmid {academy_certificates}.externalref
     * @return \stdClass|null null when the cmid is unset, not a customcert, or no longer exists.
     */
    public static function get_activity(int $cmid): ?\stdClass {
        if ($cmid <= 0 || !self::available()) {
            return null;
        }
        $cm = get_coursemodule_from_id('customcert', $cmid, 0, false, IGNORE_MISSING);
        return $cm ?: null;
    }

    /**
     * The student-facing URL of a linked certificate activity.
     *
     * Points at the activity page rather than forcing a download: mod_customcert's own page carries the
     * download/verify controls (and the sesskey they need), so we never duplicate its flow.
     *
     * @param int $cmid
     * @return \moodle_url|null null when the activity does not exist.
     */
    public static function view_url(int $cmid): ?\moodle_url {
        if (!self::get_activity($cmid)) {
            return null;
        }
        return new \moodle_url('/mod/customcert/view.php', ['id' => $cmid]);
    }

    /**
     * Will the linked certificate activity's page actually render for this user *right now*?
     *
     * Program-level eligibility (our ruleset) is not the whole story: the customcert activity can
     * carry its OWN "Restrict access" conditions — most commonly "activity X must be marked complete"
     * — and its view page enforces them live. Course/program completion is latched (un-completing one
     * activity does not un-complete the course), so our program_completed rule can still say "done"
     * while the activity's finer condition has flipped back to false and the page would block.
     *
     * This asks core the exact question the page asks: is the module available and visible to the
     * user, evaluating its access conditions against LIVE completion. Call it before advertising a
     * certificate as openable so we never hand out a link to a page that will refuse to render.
     *
     * Enrolment matters (the check includes course access), so call {@see grant_access} first.
     *
     * @param int $userid
     * @param int $cmid the linked customcert activity
     * @return bool true only when the activity's own restrictions are all satisfied for this user
     */
    public static function is_available_to(int $userid, int $cmid): bool {
        if ($userid <= 0 || !self::get_activity($cmid)) {
            return false;
        }
        try {
            // $checkcourse = true so enrolment/course access is part of the answer, exactly like the
            // view page (which require_login()s into the host course before showing the certificate).
            return \core_availability\info_module::is_user_visible($cmid, $userid, true);
        } catch (\Throwable $e) {
            // Fail safe: if we cannot confirm the activity is reachable, do NOT advertise it as open.
            debugging('local_academy: customcert availability check failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
            return false;
        }
    }

    /** Script name for the one-time auto-login keys this class mints. */
    const AUTOLOGIN_SCRIPT = 'local_academy/autologin';

    /** How long a freshly minted auto-login key stays valid — it is meant to be opened at once. */
    const AUTOLOGIN_TTL = 120;

    /**
     * A ready-to-open, self-authenticating URL for a linked certificate activity.
     *
     * The mobile app authenticates with a web-service token, but /mod/customcert/view.php is a normal
     * web page that needs a logged-in browser session — a token alone lands on the login page. This
     * mints a single, short-lived, IP-restricted key (the same core primitive tool_mobile's autologin
     * uses) bound to THIS cmid, and returns {@see /local/academy/autologin.php}. Opening that URL in a
     * plain WebView logs the user in and drops them straight on the certificate — no token handling,
     * no cooldown, no extra round-trip in the app.
     *
     * Mint it only for a user who is eligible and already granted access (call {@see grant_access}
     * first) — this method does not re-check either.
     *
     * @param int  $userid
     * @param int  $cmid       the linked customcert activity
     * @param bool $iprestrict bind the key to the caller's IP (true, the mobile default). Pass false
     *                         for the web/server-side flow, where the key is minted by the web server
     *                         on the user's behalf but redeemed from the user's browser — a different
     *                         IP — so IP binding would always reject. Single use + the short TTL + the
     *                         cmid binding remain in force either way.
     * @return \moodle_url|null null when the activity does not exist
     */
    public static function mint_autologin_url(int $userid, int $cmid, bool $iprestrict = true): ?\moodle_url {
        if ($userid <= 0 || !self::get_activity($cmid)) {
            return null;
        }
        // Bind the key to this cmid (its instance) and — for the mobile flow — to the caller's IP, and
        // expire it fast: it is handed back to be opened immediately. validate_user_key() enforces all
        // active constraints on redemption. A null IP tells create_user_key to skip the IP check.
        $ip  = $iprestrict ? \getremoteaddr() : null;
        $key = \create_user_key(self::AUTOLOGIN_SCRIPT, $userid, (string)$cmid,
            $ip, time() + self::AUTOLOGIN_TTL);
        return new \moodle_url('/local/academy/autologin.php', ['key' => $key, 'cmid' => $cmid]);
    }

    /**
     * Make sure an eligible student can reach the linked certificate activity, by enrolling them into
     * the course that hosts it.
     *
     * Idempotent (an already-enrolled student is left alone, and repeated calls in one request do
     * nothing) and fail-safe (any problem is logged, never thrown). Call it only for a student who is
     * actually eligible — this method does not re-check eligibility.
     *
     * @param int $userid
     * @param int $cmid the linked customcert activity
     * @return bool true when the student can now reach the activity
     */
    public static function grant_access(int $userid, int $cmid): bool {
        global $DB, $CFG;

        if ($userid <= 0 || $cmid <= 0) {
            return false;
        }
        $key = $userid . ':' . $cmid;
        if (isset(self::$granted[$key])) {
            return self::$granted[$key];
        }
        self::$granted[$key] = false;

        try {
            require_once($CFG->libdir . '/enrollib.php');

            $cm = self::get_activity($cmid);
            if (!$cm) {
                return false;
            }
            $context = \context_course::instance((int)$cm->course, IGNORE_MISSING);
            if (!$context) {
                return false;
            }
            // Already enrolled (by us before, manually, or through the course's own enrolment): done.
            if (is_enrolled($context, $userid)) {
                self::$granted[$key] = true;
                return true;
            }

            $plugin = enrol_get_plugin('manual');
            if (!$plugin) {
                debugging('local_academy: manual enrolment plugin unavailable, certificate access not granted',
                    DEBUG_DEVELOPER);
                return false;
            }
            $instance = $DB->get_record('enrol', [
                'courseid' => (int)$cm->course,
                'enrol'    => 'manual',
                'status'   => ENROL_INSTANCE_ENABLED,
            ], '*', IGNORE_MULTIPLE);
            if (!$instance) {
                // The host course has no usable manual enrolment method — that is an admin setup issue,
                // not something to fix silently by creating enrolment methods behind their back.
                debugging('local_academy: no enabled manual enrolment instance in course ' . (int)$cm->course,
                    DEBUG_DEVELOPER);
                return false;
            }

            $roleid = (int)($instance->roleid ?? 0);
            if ($roleid <= 0) {
                $studentroles = get_archetype_roles('student');
                $roleid = $studentroles ? (int)reset($studentroles)->id : 0;
            }
            if ($roleid <= 0) {
                debugging('local_academy: no student role to grant certificate access with', DEBUG_DEVELOPER);
                return false;
            }

            $plugin->enrol_user($instance, $userid, $roleid);
            self::$granted[$key] = true;
            return true;
        } catch (\Throwable $e) {
            debugging('local_academy: certificate access grant failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
}
