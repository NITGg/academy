<?php
/**
 * GET /local/multitopics/getalltopics.php?courseid={id}&wstoken={token}
 *
 * Returns full course structure for the mobile app:
 *   - Course metadata + availability
 *   - other_fields: active Jitsi session (native SDK) or Zoom/external meeting
 *   - parents[]: top-level sections with nested topics and activities
 *
 * Auth: wstoken validated against Moodle's external_tokens table.
 * Visibility: only sections/activities where uservisible = true are returned.
 */

define('NO_MOODLE_COOKIES', true);

// Spoof HTTP_HOST so Moodle's URL check doesn't redirect internal calls.
// Must match the actual scheme of $CFG->wwwroot (set below by config.php) — forcing
// HTTPS unconditionally trips Moodle's wwwroot-mismatch redirect when the site is
// actually served over plain HTTP, which silently turns the JSON response into an
// HTML "Redirect" page.
$_wwwroot  = getenv('MOODLE_WWWROOT') ?: 'http://localhost';
$_parsed   = parse_url($_wwwroot);
if (!empty($_parsed['host'])) {
    $_host = $_parsed['host'];
    if (!empty($_parsed['port'])) {
        $_host .= ':' . $_parsed['port'];
    }
    $_is_https              = (($_parsed['scheme'] ?? 'http') === 'https');
    $_SERVER['HTTP_HOST']   = $_host;
    $_SERVER['HTTPS']       = $_is_https ? 'on' : '';
    $_SERVER['SERVER_PORT'] = (string)($_parsed['port'] ?: ($_is_https ? 443 : 80));
}
unset($_wwwroot, $_parsed, $_host, $_is_https);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

header('Content-Type: application/json; charset=utf-8');

// ── Helper: JSON error exit ────────────────────────────────────────────────
function api_error(string $errorcode, string $message, int $http = 400): void {
    http_response_code($http);
    echo json_encode([
        'exception' => 'moodle_exception',
        'errorcode' => $errorcode,
        'message'   => $message,
    ]);
    exit;
}

// ── 1. Validate wstoken ────────────────────────────────────────────────────
$wstoken  = optional_param('wstoken',  '', PARAM_ALPHANUM);
$courseid = optional_param('courseid', 0,  PARAM_INT);

if (!$wstoken) {
    api_error('invalidtoken', 'Token is required', 401);
}
if (!$courseid) {
    api_error('invalidparameter', 'courseid is required');
}

$token_record = $DB->get_record('external_tokens', ['token' => $wstoken]);
if (!$token_record) {
    api_error('invalidtoken', 'Invalid token', 401);
}

// Load user from token.
$USER = $DB->get_record('user', ['id' => $token_record->userid, 'deleted' => 0]);
if (!$USER) {
    api_error('invalidtoken', 'Token user not found', 401);
}
\core\session\manager::set_user($USER);

// ── 2. Load course ─────────────────────────────────────────────────────────
$course = $DB->get_record('course', ['id' => $courseid]);
if (!$course) {
    api_error('invalidcourseid', 'Course not found', 404);
}

// Check enrolment/access.
$course_context = context_course::instance($course->id);
if (!is_enrolled($course_context, $USER) && !has_capability('moodle/course:view', $course_context)) {
    api_error('nopermissions', 'Not enrolled in this course', 403);
}

$course_available = true;
$course_status    = 'available';
$now = time();
if (!empty($course->startdate) && $course->startdate > $now) {
    $course_available = false;
    $course_status    = 'not_started';
} elseif (!empty($course->enddate) && $course->enddate < $now) {
    $course_status = 'ended';
}
if ($course->visible == 0 && !has_capability('moodle/course:viewhiddencourses', $course_context)) {
    $course_available = false;
    $course_status    = 'hidden';
}

// ── 3. other_fields: active Jitsi session for this course ─────────────────
$other_fields = (object)[];

// Find any jitsi activity in this course that has an active/upcoming session.
$jitsi_cms = $DB->get_records_sql(
    "SELECT cm.id as cmid, j.id as jitsiid, j.name
       FROM {course_modules} cm
       JOIN {modules} m ON m.id = cm.module AND m.name = 'jitsi'
       JOIN {jitsi} j   ON j.id = cm.instance
      WHERE cm.course = ? AND cm.visible = 1
      ORDER BY cm.id ASC",
    [$courseid]
);

foreach ($jitsi_cms as $jcm) {
    $session = $DB->get_record('academy_live_sessions', ['jitsiid' => $jcm->jitsiid]);
    if (!$session || $session->status === 'ended') {
        continue;
    }

    $open_from  = $session->start_time - 1800; // 30 min early access
    $open_until = $session->start_time + ($session->duration * 60);

    // Include if within window or starting within 24h.
    if ($now >= $open_from && $now <= $open_until) {
        $is_teacher_ctx = context_module::instance($jcm->cmid);
        $is_moderator   = has_capability('mod/jitsi:moderate', $is_teacher_ctx);
        $jitsi_host     = get_config('local_academysessions', 'jitsi_host') ?: 'localhost:8443';
        $server_url     = (strpos($jitsi_host, 'http') === 0)
            ? rtrim($jitsi_host, '/')
            : 'https://' . $jitsi_host;

        // Hold the student until the teacher is actually in the call (set by teacher_present.php).
        $teacher_present = !empty($session->teacher_joined_at);
        $available       = $is_moderator || $teacher_present;

        $room = 'academy_jitsi_' . $jcm->cmid . '_' . substr(md5($jcm->jitsiid . $jcm->cmid), 0, 8);
        $jwt  = \local_academysessions\jitsi_jwt::generate(
            $room, fullname($USER), $USER->email, $is_moderator
        );

        $start_dt = date('Y-m-d\TH:i:s', $session->start_time);
        $end_dt   = date('Y-m-d\TH:i:s', $session->start_time + ($session->duration * 60));

        // Find teacher (first enrolled user with moderate cap).
        $host_id = 0;
        $teachers = get_enrolled_users($is_teacher_ctx, 'mod/jitsi:moderate', 0, 'u.id', null, 0, 1);
        if ($teachers) {
            $host_id = (int)reset($teachers)->id;
        }

        $other_fields = (object)[
            'online' => 'no',
            'jitsi'  => (object)[
                'cmid'           => (int)$jcm->cmid,
                'room'           => $room,
                'server_url'     => $server_url,
                'jwt'            => $jwt,
                'title'          => $jcm->name,
                'start'          => $start_dt,
                'end'            => $end_dt,
                'host_id'        => (string)$host_id,
                'available'      => $available,
                'available_info' => $available ? '' : get_string('waitingforteacher', 'jitsi'),
            ],
        ];
        break;
    }
}

// ── 4. Course sections and activities ─────────────────────────────────────
$modinfo = get_fast_modinfo($course, $USER->id);

// Completion tracking for this course (points 1, 3, 4, 5, 7).
$completioninfo = new completion_info($course);

// Map section number → section info.
$sections = $modinfo->get_section_info_all();

// Detect multitopic section parents via section info.
// In the multitopic format each section has a 'parent' field (section number of parent).
// Fall back to a flat top-level-only structure if not present.
$section_parent = [];   // sectionnum => parent sectionnum (0 = top-level)
$is_multitopic  = ($course->format === 'multitopics');
foreach ($sections as $snum => $sinfo) {
    $parent_val = 0;
    if ($is_multitopic) {
        try {
            $dbsec = $DB->get_record('course_sections',
                ['course' => $courseid, 'section' => $snum],
                'id,section,parent');
            $parent_val = isset($dbsec->parent) ? (int)$dbsec->parent : 0;
        } catch (\Exception $e) {
            // parent column missing — treat all sections as top-level.
        }
    }
    $section_parent[$snum] = $parent_val;
}

// Bunny demo API config (for jitsi recording playback URLs).
$demo_url = get_config('local_academysessions', 'bunny_demo_url') ?: 'http://host.docker.internal:3000';
$demo_key = get_config('local_academysessions', 'bunny_demo_key') ?: 'academy-internal-secret-2024';

// Whiteboard base URL.
$excalidraw_app = get_config('local_academysessions', 'excalidraw_app') ?: 'https://localhost/whiteboard';

// Build activity list for each section.
function build_activities(array $cms, object $modinfo, string $wstoken, string $wwwroot,
                           string $demo_url, string $demo_key, string $excalidraw_app,
                           object $DB, object $USER, object $course, $completioninfo): array {
    global $CFG;
    $result = [];
    foreach ($cms as $cmid) {
        $cm = $modinfo->get_cm($cmid);

        // Point 6 — Restricted activities.
        // Show a restricted activity greyed-out when the user can't access it but there
        // is a restriction reason to display. Hide it only when there is nothing to show
        // (teacher-hidden / stealth activities with no availability message).
        if (!$cm->uservisible && empty($cm->availableinfo)) {
            continue;
        }
        $islocked = !$cm->uservisible;

        $url      = $wwwroot . '/mod/' . $cm->modname . '/view.php?id=' . $cm->id;
        $modicon  = $wwwroot . '/theme/image.php/' . (isset($CFG->theme) ? $CFG->theme : 'boost')
                    . '/' . $cm->modname . '/icon';

        $act = [
            'id'          => (string)$cm->id,
            'modname'     => $cm->modname,
            'name'        => format_string($cm->name),
            'sectionnum'  => (string)$cm->sectionnum,
            'visible'     => (bool)$cm->visible,
            'uservisible' => (bool)$cm->uservisible,
            // Point 6 — restriction message (empty when the activity is fully available).
            'locked'           => $islocked,
            'availabilityinfo' => $islocked
                ? \core_availability\info::format_info($cm->availableinfo, $course) : '',
            'url'         => $url,
            'tags'        => [],
            'modicon'     => $modicon,
            'resourcetype'=> '',
            'fileurl'     => '',
        ];

        // ── Completion status for this activity (points 1, 3, 7) ────────────
        $act['completion']         = (int)$cm->completion;         // 0 none · 1 manual · 2 auto
        $act['completionexpected'] = (int)$cm->completionexpected; // unix ts, 0 when not set (point 7)
        if ($completioninfo->is_enabled($cm) != COMPLETION_DISABLED) {
            $cd = \core_completion\cm_completion_details::get_instance($cm, $USER->id);
            $act['hascompletion']   = true;
            $act['isautomatic']     = $cd->is_automatic();          // true → auto (point 3), false → manual (point 2)
            $act['completionstate'] = $cd->get_overall_completion(); // 0 incomplete·1 complete·2 pass·3 fail (point 1)
            $rules = [];
            foreach ($cd->get_details() as $rulename => $rv) {       // automatic-completion rules (point 3)
                $rules[] = [
                    'rulename'    => $rulename,
                    'status'      => (int)$rv->status,               // 1 met · 0 not met
                    'description' => (string)$rv->description,
                ];
            }
            $act['completiondetails'] = $rules;
        } else {
            $act['hascompletion']     = false;
            $act['isautomatic']       = false;
            $act['completionstate']   = 0;
            $act['completiondetails'] = [];
        }

        // Do not expose file URLs / meeting tokens for a locked activity — the student
        // cannot open it. Return the metadata + restriction message only.
        if ($islocked) {
            $result[] = $act;
            continue;
        }

        // ── Resource: get file URL and type ─────────────────────────────────
        if ($cm->modname === 'resource') {
            $fs   = get_file_storage();
            $ctx  = context_module::instance($cm->id);
            $files = $fs->get_area_files($ctx->id, 'mod_resource', 'content', false, 'sortorder DESC, id ASC', false);
            if ($files) {
                $file = reset($files);
                $mime = $file->get_mimetype();
                $act['resourcetype'] = $mime;
                $act['fileurl']      = \moodle_url::make_pluginfile_url(
                    $ctx->id, 'mod_resource', 'content', $file->get_itemid(),
                    $file->get_filepath(), $file->get_filename()
                )->out(false) . '?token=' . $wstoken;
            }
        }

        // ── resource2: custom fork of mod_resource, get file URL and type ───
        if ($cm->modname === 'resource2') {
            $fs   = get_file_storage();
            $ctx  = context_module::instance($cm->id);
            $files = $fs->get_area_files($ctx->id, 'mod_resource2', 'content', false, 'sortorder DESC, id ASC', false);
            if ($files) {
                $file = reset($files);
                $mime = $file->get_mimetype();
                $act['resourcetype'] = $mime;
                $act['fileurl']      = \moodle_url::make_pluginfile_url(
                    $ctx->id, 'mod_resource2', 'content', $file->get_itemid(),
                    $file->get_filepath(), $file->get_filename()
                )->out(false) . '?token=' . $wstoken;
            }
        }

        // ── testnew: custom single-PDF activity, get file URL and type ──────
        if ($cm->modname === 'testnew') {
            $fs   = get_file_storage();
            $ctx  = context_module::instance($cm->id);
            $files = $fs->get_area_files($ctx->id, 'mod_testnew', 'content', 0, 'sortorder DESC, id ASC', false);
            if ($files) {
                $file = reset($files);
                $mime = $file->get_mimetype();
                $act['resourcetype'] = $mime;
                $act['fileurl']      = \moodle_url::make_pluginfile_url(
                    $ctx->id, 'mod_testnew', 'content', $file->get_itemid(),
                    $file->get_filepath(), $file->get_filename()
                )->out(false) . '?token=' . $wstoken;
            }
        }

        // ── URL module ───────────────────────────────────────────────────────
        if ($cm->modname === 'url') {
            $urlrec = $DB->get_record('url', ['id' => $cm->instance], 'externalurl');
            if ($urlrec) {
                $act['fileurl'] = $urlrec->externalurl;
            }
        }

        // ── Jitsi: add session info ──────────────────────────────────────────
        if ($cm->modname === 'jitsi') {
            $jitsi_rec  = $DB->get_record('jitsi', ['id' => $cm->instance]);
            $jitsi_host = get_config('local_academysessions', 'jitsi_host') ?: 'localhost:8443';
            $server_url = (strpos($jitsi_host, 'http') === 0)
                ? rtrim($jitsi_host, '/')
                : 'https://' . $jitsi_host;

            $jitsi_ctx  = context_module::instance($cm->id);
            $is_teacher = has_capability('mod/jitsi:moderate', $jitsi_ctx);
            $room       = 'academy_jitsi_' . $cm->id . '_' . substr(md5($cm->instance . $cm->id), 0, 8);
            $jwt        = \local_academysessions\jitsi_jwt::generate(
                $room, fullname($USER), $USER->email, $is_teacher
            );

            // Whiteboard URL.
            $wb_room        = 'academy_wb_jitsi_' . $cm->id;
            $whiteboard_url = rtrim($excalidraw_app, '/') . '/#room=' . rawurlencode($wb_room);

            // Recordings.
            $recs     = $DB->get_records('academy_session_recordings', ['cmid' => $cm->id], 'id DESC');
            $rec_list = [];
            foreach ($recs as $rec) {
                $playback_url  = '';
                $thumbnail_url = '';
                $embed_url     = '';
                if ($rec->status === 'ready' && !empty($rec->bunny_video_id)) {
                    $ch = curl_init($demo_url . '/api/internal/videos/'
                        . urlencode($rec->bunny_video_id) . '/playback');
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER     => ['X-Internal-Key: ' . $demo_key],
                        CURLOPT_TIMEOUT        => 6,
                    ]);
                    $body = curl_exec($ch);
                    curl_close($ch);
                    $bdata = json_decode($body, true);
                    if ($bdata && ($bdata['status'] ?? '') === 'READY') {
                        $playback_url  = $bdata['playback_url']  ?? '';
                        $thumbnail_url = $bdata['thumbnail_url'] ?? '';
                        $embed_url     = $bdata['embed_url']     ?? '';
                    }
                }
                $rec_list[] = [
                    'id'            => (int)$rec->id,
                    'title'         => $rec->title ?? '',
                    'status'        => $rec->status,
                    'playback_url'  => $playback_url,
                    'thumbnail_url' => $thumbnail_url,
                    'embed_url'     => $embed_url,
                    'timecreated'   => (int)($rec->timecreated ?? 0),
                ];
            }

            // Session timing.
            $session       = $DB->get_record('academy_live_sessions', ['jitsiid' => $cm->instance]);
            $sess_start    = $session ? date('Y-m-d\TH:i:s', $session->start_time) : null;
            $sess_end      = $session ? date('Y-m-d\TH:i:s', $session->start_time + ($session->duration * 60)) : null;
            $sess_status   = $session ? $session->status : null;

            // Hold the student until the teacher is actually in the call (set by teacher_present.php).
            $teacher_present = $session ? !empty($session->teacher_joined_at) : false;
            $available       = $is_teacher || $teacher_present;

            $host_id = 0;
            $teachers = get_enrolled_users($jitsi_ctx, 'mod/jitsi:moderate', 0, 'u.id', null, 0, 1);
            if ($teachers) {
                $host_id = (int)reset($teachers)->id;
            }

            $act['jitsi_session'] = [
                'server_url'     => $server_url,
                'room'           => $room,
                'jwt'            => $jwt,
                'subject'        => format_string($jitsi_rec->name ?? $cm->name),
                'is_teacher'     => $is_teacher,
                'available'      => $available,
                'available_info' => $available ? '' : get_string('waitingforteacher', 'jitsi'),
                'session_start'  => $sess_start ?? '',
                'session_end'    => $sess_end   ?? '',
                'session_status' => $sess_status ?? '',
                'host_id'        => (string)$host_id,
                'whiteboard_url' => $whiteboard_url,
                'recordings'     => $rec_list,
                'feature_flags'  => [
                    'recording.enabled'        => $is_teacher,
                    'livestreaming.enabled'     => false,
                    'invite.enabled'            => $is_teacher,
                    'security-options.enabled'  => $is_teacher,
                    'breakout-rooms.enabled'    => $is_teacher,
                    'video-share.enabled'       => $is_teacher,
                    'kick-out.enabled'          => $is_teacher,
                    'mute-everyone.enabled'     => $is_teacher,
                    'screen-sharing.enabled'    => true,
                    'chat.enabled'              => true,
                    'raise-hand.enabled'        => true,
                    'tile-view.enabled'         => true,
                ],
            ];
        }

        $result[] = $act;
    }
    return $result;
}

// ── 5. Build parents/topics structure ─────────────────────────────────────
$parents_map = [];   // sectionnum => parent entry
$topics_map  = [];   // sectionnum => topic entry (child of a parent)

$wwwroot = $CFG->wwwroot;

foreach ($sections as $snum => $sinfo) {
    if ($snum === 0) {
        continue; // Skip section 0 (general).
    }
    // Point 6 — restricted sections: keep them when there is a restriction message to
    // show; hide only when there is nothing to display.
    if (!$sinfo->uservisible && empty($sinfo->availableinfo)) {
        continue;
    }
    $section_locked  = !$sinfo->uservisible;
    $section_availinfo = $section_locked
        ? \core_availability\info::format_info($sinfo->availableinfo, $course) : '';

    $section_name = format_string($sinfo->name ?: get_section_name($course, $sinfo));
    $cms_in_sec   = $modinfo->sections[$snum] ?? [];
    $activities   = build_activities($cms_in_sec, $modinfo, $wstoken, $wwwroot,
                                     $demo_url, $demo_key, $excalidraw_app, $DB, $USER,
                                     $course, $completioninfo);

    $parent_snum = $section_parent[$snum] ?? 0;

    if ($parent_snum === 0) {
        // Top-level section.
        $parents_map[$snum] = [
            'id'               => (string)$sinfo->id,
            'sectionnum'       => $snum,
            'name'             => $section_name,
            'parent'           => true,
            'uservisible'      => (bool)$sinfo->uservisible,
            'locked'           => $section_locked,
            'availabilityinfo' => $section_availinfo,
            'activities'       => $activities,
            'topics'           => [],
        ];
    } else {
        // Child section — belongs under $parent_snum.
        $topics_map[$snum] = [
            'parent_snum' => $parent_snum,
            'entry'       => [
                'id'               => (string)$sinfo->id,
                'sectionnum'       => $snum,
                'name'             => $section_name,
                'uservisible'      => (bool)$sinfo->uservisible,
                'locked'           => $section_locked,
                'availabilityinfo' => $section_availinfo,
                'activities'       => $activities,
            ],
        ];
    }
}

// Attach topics to their parents.
foreach ($topics_map as $snum => $tdata) {
    $psnum = $tdata['parent_snum'];
    if (isset($parents_map[$psnum])) {
        $parents_map[$psnum]['topics'][] = $tdata['entry'];
    } else {
        // Parent not found — promote to top-level.
        $parents_map[$snum] = array_merge($tdata['entry'], ['parent' => true, 'topics' => []]);
    }
}

// ── 6. Course-level completion (points 4 and 5) ────────────────────────────
$completion_enabled = (bool)$completioninfo->is_enabled();
// Course progress percentage (0..100) — null when completion is off or no trackable activities.
$course_progress    = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
$course_completed   = $completion_enabled ? (bool)$completioninfo->is_course_complete($USER->id) : null;

// Course completion criteria breakdown (point 5).
$completion_criteria = [];
if ($completion_enabled) {
    foreach ($completioninfo->get_completions($USER->id) as $completion) {
        $crit = $completion->get_criteria();
        $completion_criteria[] = [
            'type'          => (int)$crit->criteriatype,   // e.g. 4 activity · 6 date · 8 grade · 1 self
            'title'         => format_string($crit->get_title()),
            'complete'      => (bool)$completion->is_complete(),
            'timecompleted' => (int)($completion->timecompleted ?? 0),
        ];
    }
}

// ── 7. Build response ──────────────────────────────────────────────────────
$response = [
    'courseid'            => (int)$course->id,
    'fullname'            => format_string($course->fullname),
    'shortname'           => $course->shortname,
    'format'              => $course->format,
    'isavailable'         => $course_available,
    'status'              => $course_status,
    // Course completion (points 4 and 5).
    'completion_enabled'  => $completion_enabled,
    'progress'            => $course_progress,      // 0..100 or null
    'course_completed'    => $course_completed,     // true / false / null
    'completion_criteria' => $completion_criteria,
    'other_fields'        => $other_fields,
    'parents'             => array_values($parents_map),
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
