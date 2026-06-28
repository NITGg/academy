<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * View page for mod_jitsi.
 *
 * Handles session access restrictions, Jitsi Meet embed (with user identity and
 * language), collaborative whiteboard tab, and post-session recording display.
 *
 * @package   mod_jitsi
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);

// Use cm_info so Moodle availability conditions (date restrictions etc.) are loaded.
$modinfo = get_fast_modinfo(get_course(
    $DB->get_field('course_modules', 'course', ['id' => $id], MUST_EXIST)
));
$cm      = $modinfo->get_cm($id);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$jitsi   = $DB->get_record('jitsi', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/jitsi:view', $context);

// ── Moodle availability / date restriction check ──────────────────────────
// If the activity has "available until" in the past, block access for students.
$is_teacher = has_capability('mod/jitsi:moderate', $context);
if (!$is_teacher && !$cm->available) {
    $PAGE->set_url('/mod/jitsi/view.php', ['id' => $cm->id]);
    $PAGE->set_context($context);
    $PAGE->set_title(format_string($jitsi->name));
    $PAGE->set_heading(format_string($course->fullname));
    $session_for_locked = $DB->get_record('academy_live_sessions', ['jitsiid' => $jitsi->id]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($jitsi->name));
    echo $OUTPUT->notification(
        $cm->availableinfo ?: get_string('sessionended', 'jitsi'),
        'warning'
    );
    jitsi_print_recordings($session_for_locked ?: null, $context, $is_teacher, $cm->id);
    echo $OUTPUT->footer();
    exit;
}

$PAGE->set_url('/mod/jitsi/view.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($jitsi->name));
$PAGE->set_heading(format_string($course->fullname));

// -------------------------------------------------------------------------
// Access control – check linked academy session (if any).
// -------------------------------------------------------------------------
// $is_teacher already set above.

// Standalone ended check (no linked session — teacher ended via AJAX).
if (get_config('mod_jitsi', 'ended_' . $cm->id)) {
    $session_for_ended = $DB->get_record('academy_live_sessions', ['jitsiid' => $jitsi->id]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(format_string($jitsi->name));
    echo $OUTPUT->notification(get_string('sessionended', 'jitsi'), 'info');
    jitsi_print_recordings($session_for_ended ?: null, $context, $is_teacher, $cm->id);
    echo $OUTPUT->footer();
    exit;
}

$session    = $DB->get_record('academy_live_sessions', ['jitsiid' => $jitsi->id]);

if ($session) {

    // For everyone (teachers included): once the session is marked 'ended',
    // the room is closed — refreshing should not allow rejoining.
    if ($session->status === 'ended') {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($jitsi->name));
        echo $OUTPUT->notification(get_string('sessionended', 'jitsi'), 'info');
        jitsi_print_recordings($session, $context, $is_teacher);
        echo $OUTPUT->footer();
        exit;
    }

    if (!$is_teacher) {
        // 1. Student must be on the allowed list.
        $allowed = $DB->record_exists('academy_session_students', [
            'sessionid' => $session->id,
            'userid'    => $USER->id,
        ]);
        if (!$allowed) {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($jitsi->name));
            echo $OUTPUT->notification(get_string('notallowed', 'jitsi'), 'warning');
            echo $OUTPUT->footer();
            exit;
        }

        $now       = time();
        $open_from = $session->start_time - 1800;
        $open_until = $session->start_time + ($session->duration * 60);

        // 2. Too early.
        if ($now < $open_from) {
            $mins = ceil(($open_from - $now) / 60);
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($jitsi->name));
            echo $OUTPUT->notification(
                get_string('sessionopening', 'jitsi', $mins),
                'info'
            );
            echo $OUTPUT->footer();
            exit;
        }

        // 3. Time window passed (but status not yet 'ended' — lifecycle cron hasn't run).
        if ($now > $open_until) {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($jitsi->name));
            echo $OUTPUT->notification(get_string('sessionended', 'jitsi'), 'info');
            jitsi_print_recordings($session, $context, $is_teacher);
            echo $OUTPUT->footer();
            exit;
        }

        // 4. Record attendance (first join only).
        if (!$DB->record_exists('academy_session_attendance', ['sessionid' => $session->id, 'userid' => $USER->id])) {
            $att                   = new stdClass();
            $att->sessionid        = $session->id;
            $att->userid           = $USER->id;
            $att->joined_at        = $now;
            $att->duration_seconds = 0;
            $DB->insert_record('academy_session_attendance', $att);
        }
    }
}

// Completion tracking.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

// -------------------------------------------------------------------------
// Build Jitsi configuration.
// -------------------------------------------------------------------------
$jitsi_host = get_config('local_academysessions', 'jitsi_host') ?: 'localhost:8443';

// Unique, stable room name per activity instance.
$jitsi_room = 'academy_jitsi_' . $cm->id . '_' . substr(md5($jitsi->id . $cm->id), 0, 8);

$display_name = fullname($USER);
$user_email   = $USER->email;

// JWT token — tells Jitsi who is moderator.
$jitsi_jwt = \local_academysessions\jitsi_jwt::generate(
    $jitsi_room, $display_name, $user_email, $is_teacher
);

// Auto-start Jibri recording when teacher joins — fire-and-forget.
if ($is_teacher) {
    $jibri_url  = get_config('local_academysessions', 'jibri_api_url') ?: 'http://academy_jibri:2223';
    $jitsi_base = (strpos($jitsi_host, 'http') === 0) ? rtrim($jitsi_host, '/') : 'https://' . $jitsi_host;

    $jibri_recorder_pass = get_config('local_academysessions', 'jibri_recorder_password')
        ?: '3542497ebee440c2c4b12b5a41f474d2';
    $jibri_body = json_encode([
        'sessionId'       => 'academy-' . $cm->id . '-' . time(),
        'sinkType'        => 'file',
        'callParams'      => [
            'callUrlInfo' => [
                'baseUrl'  => $jitsi_base,
                'callName' => $jitsi_room,
            ],
        ],
        'callLoginParams' => [
            'domain'   => 'hidden.meet.jitsi',
            'username' => 'recorder',
            'password' => $jibri_recorder_pass,
        ],
        'appData' => json_encode(['file_recording_metadata' => ['upload_credentials' => []]]),
    ]);

    $ch = curl_init($jibri_url . '/jibri/api/v1.0/startService');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jibri_body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// Map Moodle lang codes to Jitsi / i18n codes.
$lang_map = [
    'ar'    => 'ar',
    'en'    => 'en',
    'fr'    => 'fr',
    'de'    => 'de',
    'es'    => 'es',
    'pt'    => 'pt',
    'tr'    => 'tr',
    'ru'    => 'ru',
    'zh_cn' => 'zh',
    'ja'    => 'ja',
];
$moodle_lang  = current_language();
$jitsi_lang   = $lang_map[$moodle_lang] ?? 'en';

// Teacher: full control panel.
$toolbar_teacher = [
    'microphone', 'camera', 'desktop',
    'chat', 'recording', 'livestreaming',
    'invite',
    'raisehand', 'participants-pane', 'mute-everyone',
    'whiteboard', 'etherpad',
    'select-background', 'noisesuppression',
    'tileview', 'filmstrip', 'videoquality', 'stats',
    'security', 'closedcaptions', 'shortcuts',
    'fullscreen', 'hangup',
];
// Students: useful personal controls only.
$toolbar_student = [
    'microphone', 'camera', 'desktop',
    'chat', 'raisehand', 'whiteboard',
    'select-background', 'noisesuppression',
    'tileview', 'videoquality',
    'fullscreen', 'hangup',
];
$toolbar         = $is_teacher ? $toolbar_teacher : $toolbar_student;

// Jitsi is now on HTTPS (mkcert cert, port 8443 → container 443).
// External API requires HTTPS — this is now satisfied.
$jitsi_scheme = 'https';

// Whiteboard — self-hosted Excalidraw frontend (port 9091).
// Port 9090 is the Socket.IO relay only; the drawable UI is on 9091.
$excalidraw_app = get_config('local_academysessions', 'excalidraw_app') ?: 'http://localhost:9091';
// Guard: if the relay URL (port 9090) was accidentally saved in config, correct it.
if (strpos($excalidraw_app, ':9090') !== false) {
    $excalidraw_app = 'http://localhost:9091';
}
$wb_room = 'academy_wb_jitsi_' . $cm->id;
$wb_url  = rtrim($excalidraw_app, '/') . '/#room=' . rawurlencode($wb_room);

// Session token for AJAX end-session call.
$sesskey      = sesskey();
$session_id   = $session ? (int)$session->id : 0;
$end_ajax_url = (new moodle_url('/mod/jitsi/ajax.php'))->out(false);

// -------------------------------------------------------------------------
// Output.
// -------------------------------------------------------------------------
echo $OUTPUT->header();

// Load Jitsi External API as a static <script> tag.
echo '<script src="' . s($jitsi_scheme . '://' . $jitsi_host . '/external_api.js') . '"></script>' . "\n";

echo $OUTPUT->heading(format_string($jitsi->name));

if (!empty($jitsi->intro)) {
    echo $OUTPUT->box(format_module_intro('jitsi', $jitsi, $cm->id), 'generalbox mod_introbox');
}

echo '<div id="jitsi-activity-container" style="margin:15px 0;">';

// ── Tab bar ──────────────────────────────────────────────────────────────
echo '<div id="jitsi-tabs" style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;">';
echo '<button id="tab-video" onclick="jitsiSwitchTab(\'video\')" class="btn btn-primary btn-sm" style="border-radius:16px;">'
     . get_string('tab_video', 'jitsi') . '</button>';
echo '<button id="tab-whiteboard" onclick="jitsiSwitchTab(\'whiteboard\')" class="btn btn-outline-secondary btn-sm" style="border-radius:16px;">'
     . get_string('tab_whiteboard', 'jitsi') . '</button>';
if ($is_teacher) {
    echo '<span class="badge badge-info ml-auto" style="align-self:center;font-size:12px;">'
         . get_string('youarehost', 'jitsi') . '</span>';
}
echo '</div>';

// ── Jitsi External API container ─────────────────────────────────────────
echo '<div id="panel-video" style="width:100%;height:620px;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.15);">';
echo '<div id="jitsi-container" style="width:100%;height:100%;"></div>';
echo '</div>';

// ── Whiteboard panel ─────────────────────────────────────────────────────
echo '<div id="panel-whiteboard" style="display:none;width:100%;height:620px;border-radius:8px;overflow:hidden;border:1px solid #e0e0e0;">';
echo '<iframe src="' . s($wb_url) . '" style="width:100%;height:100%;border:none;" allow="clipboard-read;clipboard-write"></iframe>';
echo '</div>';

// ── Post-call panel (hidden until call ends) ──────────────────────────────
echo '<div id="panel-ended" style="display:none;padding:30px;text-align:center;background:#f8f9fa;border-radius:8px;">';
echo '<h4 style="color:#495057;">' . get_string('sessionended', 'jitsi') . '</h4>';
echo '<p class="text-muted" id="ended-sub"></p>';
echo '<div id="ended-recordings"></div>';
echo '</div>';

echo '</div>'; // #jitsi-activity-container

// Security settings stored on the activity.
$room_password  = !empty($jitsi->roompassword)  ? $jitsi->roompassword  : '';
$lobby_enabled  = !empty($jitsi->lobby_enabled);

$js_config = json_encode([
    'isTeacher'      => (bool)$is_teacher,
    'cmId'           => (int)$cm->id,
    'sessionId'      => $session_id,
    'sesskey'        => $sesskey,
    'endUrl'         => $end_ajax_url,
    'jitsiHost'      => $jitsi_host,
    'jitsiRoom'      => $jitsi_room,
    'jitsiName'      => $jitsi->name,
    'jitsiLang'      => $jitsi_lang,
    'displayName'    => $display_name,
    'userEmail'      => $user_email,
    'toolbarButtons' => $toolbar,
    'jwt'            => $jitsi_jwt,
    'roomPassword'   => $room_password,
    'lobbyEnabled'   => $lobby_enabled,
]);

echo <<<HTML
<script>
(function() {
    var CFG = {$js_config};
    var api = null;

    function initJitsiAPI() {
        if (typeof JitsiMeetExternalAPI === 'undefined') {
            document.getElementById('jitsi-container').innerHTML =
                '<div style="padding:40px;text-align:center;color:#dc3545;">'
                + '<strong>Could not load Jitsi API.</strong><br>'
                + 'Your browser may be blocking <code>https://' + CFG.jitsiHost + '/external_api.js</code> '
                + 'due to an untrusted SSL certificate.<br>'
                + 'Please open <a href="https://' + CFG.jitsiHost + '" target="_blank">https://' + CFG.jitsiHost + '</a> '
                + 'in a new tab, accept the certificate warning, then refresh this page.'
                + '</div>';
            return;
        }
        api = new JitsiMeetExternalAPI(CFG.jitsiHost, {
            roomName: CFG.jitsiRoom,
            jwt: CFG.jwt,
            parentNode: document.getElementById('jitsi-container'),
            width: '100%',
            height: '100%',
            configOverwrite: {
                startWithAudioMuted: true,
                startWithVideoMuted: true,
                prejoinPageEnabled: false,
                disableDeepLinking: true,
                disableInviteFunctions: !CFG.isTeacher,
                enableClosePage: false,
                subject: CFG.jitsiName,
                defaultLanguage: CFG.jitsiLang,
                toolbarButtons: CFG.toolbarButtons,
                fileRecordingsEnabled: CFG.isTeacher,
                localRecording: { enabled: false },
                liveStreamingEnabled: true,
                hiddenPremeetingButtons: [],
                disableProfile: false,
                enableNoisyMicDetection: true,
                enableNoAudioDetection: true,
                channelLastN: -1,
                startWithAudioMuted: true,
                startWithVideoMuted: false,
                disableSelfViewSettings: false,
                disableRemoteMute: !CFG.isTeacher,
                remoteVideoMenu: { disabled: false, disableKick: !CFG.isTeacher, disableGrantModerator: !CFG.isTeacher },
                breakoutRooms: { hideAddRoomButton: !CFG.isTeacher, hideAutoAssignButton: !CFG.isTeacher, hideJoinRoomButton: false },
                participantsPane: { hideModeratorSettingsTab: !CFG.isTeacher, hideMoreActionsButton: false, hideMuteAllButton: !CFG.isTeacher },
            },
            interfaceConfigOverwrite: {
                SHOW_JITSI_WATERMARK: false,
                SHOW_WATERMARK_FOR_GUESTS: false,
                SHOW_BRAND_WATERMARK: false,
                SHOW_POWERED_BY: false,
                DISPLAY_WELCOME_FOOTER: false,
                HIDE_INVITE_MORE_HEADER: !CFG.isTeacher,
                TOOLBAR_ALWAYS_VISIBLE: false,
                ENFORCE_NOTIFICATION_AUTO_DISMISS_TIMEOUT: 5000,
            },
            userInfo: {
                displayName: CFG.displayName,
                email: CFG.userEmail
            }
        });
        // readyToClose fires when teacher clicks "End meeting for all" — ended=true marks the session.
        api.addEventListener('readyToClose', function() { onSessionLeft(true); });
        // videoConferenceLeft fires on plain leave — teacher leaving without ending doesn't mark session.
        api.addEventListener('videoConferenceLeft', function() { onSessionLeft(false); });

        // Auto-submit password for everyone so no one gets a prompt
        // (Moodle already gates who can reach this page).
        if (CFG.roomPassword) {
            api.addEventListener('passwordRequired', function() {
                api.executeCommand('password', CFG.roomPassword);
            });
        }

        // Teacher: set password on first join and enable lobby if configured.
        // JWT moderator role automatically bypasses the lobby on rejoin.
        if (CFG.isTeacher) {
            api.addEventListener('videoConferenceJoined', function() {
                if (CFG.roomPassword) {
                    api.executeCommand('password', CFG.roomPassword);
                }
                if (CFG.lobbyEnabled) {
                    api.executeCommand('toggleLobby', true);
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initJitsiAPI);
    } else {
        initJitsiAPI();
    }

    var _sessionEndCalled = false;

    function onSessionLeft(teacherEndedForAll) {
        if (api) { try { api.dispose(); } catch(x) {} api = null; }

        document.getElementById('jitsi-tabs').style.display = 'none';
        document.getElementById('panel-video').style.display = 'none';
        document.getElementById('panel-whiteboard').style.display = 'none';
        document.getElementById('panel-ended').style.display = 'block';

        if (teacherEndedForAll && CFG.isTeacher && !_sessionEndCalled) {
            _sessionEndCalled = true;
            document.getElementById('ended-sub').textContent = 'Ending session…';

            // Determine which AJAX function to call:
            // end_session  — if there is a linked academy session
            // end_room     — standalone activity (no linked session)
            var body = CFG.sessionId
                ? 'function=end_session&sesskey=' + encodeURIComponent(CFG.sesskey) + '&sessionid=' + CFG.sessionId
                : 'function=end_room&sesskey=' + encodeURIComponent(CFG.sesskey) + '&cmid=' + CFG.cmId;

            fetch(CFG.endUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body
            }).then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('ended-sub').textContent =
                    data.status === 'success'
                        ? 'Session ended. Recordings will appear below once processed.'
                        : 'Session could not be marked ended: ' + (data.error || '');
                loadRecordingsAjax();
            }).catch(function() {
                document.getElementById('ended-sub').textContent =
                    'Session ended (could not update server status).';
                loadRecordingsAjax();
            });
        } else if (teacherEndedForAll && !CFG.isTeacher) {
            // Student: teacher ended the meeting for all.
            document.getElementById('ended-sub').textContent =
                'The host has ended this session. Recordings will appear below once processed.';
            loadRecordingsAjax();
        } else {
            document.getElementById('ended-sub').textContent =
                'You left the session. Recordings will appear below once processed.';
            loadRecordingsAjax();
        }
    }

    function loadRecordingsAjax() {
        if (!CFG.sessionId) return;
        var recParams = CFG.sessionId
            ? 'sessionid=' + CFG.sessionId + '&cmid=' + CFG.cmId
            : 'cmid=' + CFG.cmId;
        fetch(CFG.endUrl + '?function=get_session_recordings&' + recParams
              + '&sesskey=' + encodeURIComponent(CFG.sesskey))
            .then(function(r){ return r.json(); })
            .then(function(data) {
                var box = document.getElementById('ended-recordings');
                if (!data.data || data.data.length === 0) {
                    box.innerHTML = '<p class="text-muted mt-3">No recordings available yet.</p>';
                    return;
                }
                var html = '<h5 class="mt-4">Session Recordings</h5>';
                data.data.forEach(function(rec) {
                    html += '<div class="card mb-3" style="border-radius:8px;text-align:left;">';
                    html += '<div class="card-body">';
                    html += '<h6>' + (rec.title || 'Recording') + '</h6>';
                    if (rec.embed_url) {
                        html += '<div style="position:relative;padding-top:56.25%;border-radius:6px;overflow:hidden;">';
                        html += '<iframe src="' + rec.embed_url + '" loading="lazy"'
                            + ' style="border:none;position:absolute;top:0;left:0;height:100%;width:100%;"'
                            + ' allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;"'
                            + ' allowfullscreen></iframe></div>';
                    } else {
                        html += '<p class="text-muted">Recording is being processed.</p>';
                    }
                    if (rec.storage && CFG.isTeacher) {
                        html += '<small class="text-muted">Storage: ' + rec.storage + '</small>';
                    }
                    html += '</div></div>';
                });
                box.innerHTML = html;
            })
            .catch(function() {
                document.getElementById('ended-recordings').innerHTML =
                    '<p class="text-muted">Could not load recordings right now.</p>';
            });
    }

    /* ── tab switcher ───────────────────────────────────────────────── */
    window.jitsiSwitchTab = function(tab) {
        var vp = document.getElementById('panel-video');
        var wp = document.getElementById('panel-whiteboard');
        var tv = document.getElementById('tab-video');
        var tw = document.getElementById('tab-whiteboard');
        if (tab === 'video') {
            vp.style.display = 'block'; wp.style.display = 'none';
            tv.className = 'btn btn-primary btn-sm'; tv.style.borderRadius = '16px';
            tw.className = 'btn btn-outline-secondary btn-sm'; tw.style.borderRadius = '16px';
        } else {
            vp.style.display = 'none'; wp.style.display = 'block';
            tv.className = 'btn btn-outline-secondary btn-sm'; tv.style.borderRadius = '16px';
            tw.className = 'btn btn-primary btn-sm'; tw.style.borderRadius = '16px';
        }
    };
})();
</script>
HTML;

// Show recordings below the conference:
// - If there's a linked session: teacher always sees them; students see after session window ends.
// - If standalone (no session): always show if any recordings exist.
if ($session) {
    $show_recs = $is_teacher;
    if (!$is_teacher) {
        $now        = time();
        $open_until = $session->start_time + ($session->duration * 60);
        $show_recs  = ($now > $open_until);
    }
    if ($show_recs) {
        jitsi_print_recordings($session, $context, $is_teacher, $cm->id);
    }
} else {
    // Standalone room — show recordings if any exist for this cmid.
    $has_recs = $DB->record_exists('academy_session_recordings', ['cmid' => $cm->id]);
    if ($has_recs) {
        jitsi_print_recordings(null, $context, $is_teacher, $cm->id);
    }
}

echo $OUTPUT->footer();

// -------------------------------------------------------------------------
// Helper: render recordings for this session.
// -------------------------------------------------------------------------
function jitsi_print_recordings($session, $context, $is_teacher, $cmid = null) {
    global $DB, $OUTPUT, $cm;

    if ($session) {
        $recordings = $DB->get_records(
            'academy_session_recordings',
            ['sessionid' => $session->id],
            'timecreated DESC'
        );
        // Also include any standalone recordings by cmid for this activity.
        $standalone_cmid = $cmid ?: ($cm->id ?? null);
        if ($standalone_cmid) {
            $extra = $DB->get_records_sql(
                "SELECT * FROM {academy_session_recordings}
                 WHERE cmid = :cmid AND (sessionid IS NULL OR sessionid = 0)
                 ORDER BY timecreated DESC",
                ['cmid' => $standalone_cmid]
            );
            $recordings = array_merge($recordings, $extra);
        }
    } else {
        $standalone_cmid = $cmid ?: ($cm->id ?? null);
        $recordings = $standalone_cmid
            ? $DB->get_records_sql(
                "SELECT * FROM {academy_session_recordings}
                 WHERE cmid = :cmid ORDER BY timecreated DESC",
                ['cmid' => $standalone_cmid]
              )
            : [];
    }

    echo '<div class="jitsi-recordings mt-4">';
    echo '<h4>' . get_string('recordings', 'jitsi') . '</h4>';

    if (empty($recordings)) {
        echo '<p class="text-muted">' . get_string('norecordings', 'jitsi') . '</p>';
        echo '</div>';
        return;
    }

    foreach ($recordings as $rec) {
        echo '<div class="card mb-3" style="border-radius:8px;">';
        echo '<div class="card-body">';
        // title is the MinIO key path — extract a human date from the filename.
        $display_title = get_string('recording', 'jitsi');
        if (!empty($rec->title)) {
            $fname = basename($rec->title, '.mp4');
            if (preg_match('/(\d{4}-\d{2}-\d{2})-(\d{2})-(\d{2})-(\d{2})$/', $fname, $dm)) {
                $ts = strtotime("{$dm[1]} {$dm[2]}:{$dm[3]}:{$dm[4]}");
                if ($ts) {
                    $display_title = userdate($ts, get_string('strftimedatetimeshort', 'langconfig'));
                }
            }
        }
        echo '<h5 class="card-title">' . s($display_title) . '</h5>';

        $embed_url = null;
        if ($rec->status === 'ready') {
            // Use stored signed URL; refresh via demo API if expired.
            if (!empty($rec->bunny_video_url) && (empty($rec->expires_at) || $rec->expires_at > time() + 300)) {
                $embed_url = $rec->bunny_video_url;
            } elseif (!empty($rec->bunny_video_id)) {
                // Refresh from demo API.
                try {
                    $demo_url = get_config('local_academysessions', 'bunny_demo_url') ?: 'http://host.docker.internal:3000';
                    $demo_key = get_config('local_academysessions', 'bunny_demo_key') ?: 'academy-internal-secret-2024';
                    $ch = curl_init($demo_url . '/api/internal/videos/' . $rec->bunny_video_id . '/embed');
                    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['X-Internal-Key: ' . $demo_key], CURLOPT_TIMEOUT => 10]);
                    $resp = json_decode(curl_exec($ch), true);
                    curl_close($ch);
                    if (!empty($resp['embedUrl'])) {
                        $embed_url = $resp['embedUrl'];
                        // Save refreshed URL.
                        $upd = new stdClass();
                        $upd->id             = $rec->id;
                        $upd->bunny_video_url = $embed_url;
                        $upd->expires_at     = !empty($resp['expiresAt']) ? strtotime($resp['expiresAt']) : (time() + 86400 * 30);
                        $upd->timemodified   = time();
                        $DB->update_record('academy_session_recordings', $upd);
                    }
                } catch (Exception $e) { /* non-fatal */ }
            }
        }

        if ($embed_url) {
            echo '<div style="position:relative;padding-top:56.25%;border-radius:6px;overflow:hidden;">';
            echo '<iframe src="' . s($embed_url) . '" loading="lazy"
                style="border:none;position:absolute;top:0;left:0;height:100%;width:100%;"
                allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;"
                allowfullscreen="true"></iframe>';
            echo '</div>';
        } else {
            // Still syncing — show spinner and auto-poll until READY.
            echo '<div id="rec-status-' . (int)$rec->id . '" class="rec-polling-card"'
                . ' data-rec-id="' . (int)$rec->id . '">';
            echo '<div style="display:flex;align-items:center;gap:14px;padding:16px 0;">';
            echo '<div class="spinner-border text-primary" role="status" style="flex-shrink:0;">'
                . '<span class="sr-only">Loading...</span></div>';
            echo '<div>';
            echo '<p class="mb-1" style="font-weight:500;">' . get_string('recprocessing', 'jitsi') . '</p>';
            echo '<small class="text-muted" id="rec-label-' . (int)$rec->id . '">Uploading to Bunny CDN&hellip;</small>';
            echo '</div></div></div>';
        }

        // Show storage location to teachers.
        if ($is_teacher) {
            $minio_endpoint = get_config('local_academysessions', 'minio_endpoint') ?: 'http://minio:9000';
            $minio_bucket   = get_config('local_academysessions', 'minio_bucket') ?: 'academy-recordings';
            echo '<div class="mt-2 p-2" style="background:#f8f9fa;border-radius:4px;font-size:12px;">';
            echo '<strong>' . get_string('recstorage', 'jitsi') . ':</strong> ';
            echo s($minio_endpoint . '/' . $minio_bucket);
            if (!empty($rec->bunny_video_id)) {
                echo ' &rarr; Bunny Stream ID: <code>' . s($rec->bunny_video_id) . '</code>';
            }
            echo '</div>';
        }

        if (!empty($rec->timecreated)) {
            echo '<small class="text-muted">' . userdate($rec->timecreated) . '</small>';
        }

        echo '</div>'; // card-body
        echo '</div>'; // card
    }

    // Collect IDs of recordings that are still processing so JS can poll them.
    $pending_ids = [];
    foreach ($recordings as $r) {
        if ($r->status !== 'ready') {
            $pending_ids[] = (int)$r->id;
        }
    }

    if (!empty($pending_ids)) {
        $ajax_url  = (new moodle_url('/mod/jitsi/ajax.php'))->out(false);
        $sesskey   = sesskey();
        $ids_json  = json_encode($pending_ids);
        $ajax_json = json_encode($ajax_url);
        $key_json  = json_encode($sesskey);
        echo <<<POLL_JS
<script>
(function() {
    var pendingIds = {$ids_json};
    var ajaxUrl   = {$ajax_json};
    var sesskey   = {$key_json};
    var pollIntervalMs = 5000;

    var statusLabels = {
        'PENDING':    'Waiting in queue…',
        'UPLOADING':  'Uploading to Bunny CDN…',
        'PROCESSING': 'Transcoding video…',
        'READY':      'Done!'
    };

    function pollOne(recId) {
        fetch(ajaxUrl + '?function=check_recording_status&rec_id=' + recId
              + '&sesskey=' + encodeURIComponent(sesskey))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var card  = document.getElementById('rec-status-' + recId);
                var label = document.getElementById('rec-label-' + recId);
                if (!card) return;

                if (data.status === 'ready') {
                    if (data.embed_url) {
                        card.innerHTML =
                            '<div style="position:relative;padding-top:56.25%;border-radius:6px;overflow:hidden;">'
                            + '<iframe src="' + data.embed_url + '" loading="lazy"'
                            + ' style="border:none;position:absolute;top:0;left:0;height:100%;width:100%;"'
                            + ' allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;"'
                            + ' allowfullscreen></iframe></div>';
                    } else {
                        // embed_url not available yet — reload so PHP can render it
                        window.location.reload();
                    }
                    pendingIds = pendingIds.filter(function(id) { return id !== recId; });
                    return;
                }

                // Update status label
                if (label && data.bunny_status && statusLabels[data.bunny_status]) {
                    label.textContent = statusLabels[data.bunny_status];
                }

                // Re-schedule poll
                setTimeout(function() { pollOne(recId); }, pollIntervalMs);
            })
            .catch(function() {
                setTimeout(function() { pollOne(recId); }, pollIntervalMs * 2);
            });
    }

    // Start polling after a short initial delay.
    pendingIds.forEach(function(id) {
        setTimeout(function() { pollOne(id); }, pollIntervalMs);
    });
})();
</script>
POLL_JS;
    }

    echo '</div>'; // jitsi-recordings
}
