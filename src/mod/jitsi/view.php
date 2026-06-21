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

$cm      = get_coursemodule_from_id('jitsi', $id, 0, false, MUST_EXIST);
$course  = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$jitsi   = $DB->get_record('jitsi', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/jitsi:view', $context);

$PAGE->set_url('/mod/jitsi/view.php', ['id' => $cm->id]);
$PAGE->set_context($context);
$PAGE->set_title(format_string($jitsi->name));
$PAGE->set_heading(format_string($course->fullname));

// -------------------------------------------------------------------------
// Access control – check linked academy session (if any).
// -------------------------------------------------------------------------
$is_teacher = has_capability('mod/jitsi:moderate', $context);
$session    = $DB->get_record('academy_live_sessions', ['jitsiid' => $jitsi->id]);

if ($session && !$is_teacher) {

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

    $now              = time();
    $open_from        = $session->start_time - 1800;          // 30 min before
    $open_until       = $session->start_time + ($session->duration * 60);

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

    // 3. Session has ended — show recordings and exit.
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

// Toolbar: teachers get recording + admin controls; students get whiteboard + basics.
$toolbar_teacher = ['microphone','camera','desktop','chat','recording','whiteboard','raisehand','participants-pane','tileview','fullscreen','hangup'];
$toolbar_student = ['microphone','camera','chat','whiteboard','raisehand','tileview','fullscreen','hangup'];
$toolbar         = $is_teacher ? $toolbar_teacher : $toolbar_student;

// Jitsi iframe URL — toolbar goes in the hash WITHOUT double-encoding.
// json_encode produces ["mic","cam",...]; s() will HTML-escape the quotes
// inside the attribute value, which is exactly what browsers expect.
$jitsi_scheme = (strpos($jitsi_host, 'localhost') !== false || strpos($jitsi_host, '127.0.0.1') !== false)
    ? 'http' : 'https';
$toolbar_json = json_encode($toolbar);   // raw JSON, no urlencode
$jitsi_url = $jitsi_scheme . '://' . $jitsi_host . '/' . rawurlencode($jitsi_room)
    . '#config.startWithAudioMuted=true'
    . '&config.startWithVideoMuted=false'
    . '&config.prejoinPageEnabled=false'
    . '&config.disableDeepLinking=true'
    . '&config.disableInviteFunctions=true'
    . '&config.enableClosePage=false'
    . '&config.subject=' . rawurlencode($jitsi->name)
    . '&config.defaultLanguage=' . rawurlencode($jitsi_lang)
    . '&config.toolbarButtons=' . $toolbar_json   // intentionally no urlencode
    . '&config.fileRecordingsEnabled=' . ($is_teacher ? 'true' : 'false')
    . '&config.liveStreamingEnabled=false'
    . '&userInfo.displayName=' . rawurlencode($display_name)
    . '&userInfo.email=' . rawurlencode($user_email);

// Whiteboard — use local app if configured and reachable, otherwise excalidraw.com.
$excalidraw_app = get_config('local_academysessions', 'excalidraw_app') ?: '';
$wb_room = 'academy_wb_jitsi_' . $cm->id;
$wb_url  = ($excalidraw_app && strpos($excalidraw_app, 'localhost') === false)
    ? rtrim($excalidraw_app, '/') . '/#room=' . rawurlencode($wb_room)
    : 'https://excalidraw.com/#room=' . rawurlencode($wb_room);

// Session token for AJAX end-session call.
$sesskey      = sesskey();
$session_id   = $session ? (int)$session->id : 0;
$end_ajax_url = (new moodle_url('/mod/jitsi/ajax.php'))->out(false);
$jitsi_origin = $jitsi_scheme . '://' . $jitsi_host;  // for postMessage origin check

// -------------------------------------------------------------------------
// Output.
// -------------------------------------------------------------------------
echo $OUTPUT->header();
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

// ── Jitsi iframe ─────────────────────────────────────────────────────────
echo '<div id="panel-video" style="width:100%;height:620px;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.15);">';
echo '<iframe id="jitsi-iframe" src="' . s($jitsi_url) . '"
    style="width:100%;height:100%;border:none;"
    allow="camera; microphone; display-capture; autoplay; clipboard-write; fullscreen"
    allowfullscreen>
</iframe>';
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

$js_config = json_encode([
    'isTeacher'   => (bool)$is_teacher,
    'sessionId'   => $session_id,
    'sesskey'     => $sesskey,
    'endUrl'      => $end_ajax_url,
    'jitsiOrigin' => $jitsi_origin,
]);

echo <<<HTML
<script>
(function() {
    var CFG = {$js_config};

    /* ── Jitsi sends postMessage events to the parent window ──────────────
       We listen for them here — no external_api.js needed.
       Events arrive as JSON strings: {"name":"video-conference-left",...}  */
    window.addEventListener('message', function(e) {
        /* only trust messages from our Jitsi server */
        if (e.origin !== CFG.jitsiOrigin) return;

        var data = e.data;
        if (typeof data === 'string') {
            try { data = JSON.parse(data); } catch(x) { return; }
        }
        if (!data || !data.name) return;

        if (data.name === 'readyToClose') {
            onSessionLeft(true);   /* teacher ended the meeting */
        } else if (data.name === 'video-conference-left') {
            onSessionLeft(false);  /* user left (may still rejoin) */
        }
    });

    function onSessionLeft(ended) {
        /* remove the iframe so the camera/mic are released */
        var fr = document.getElementById('jitsi-iframe');
        if (fr) { fr.src = 'about:blank'; fr.remove(); }

        document.getElementById('jitsi-tabs').style.display = 'none';
        document.getElementById('panel-video').style.display = 'none';
        document.getElementById('panel-whiteboard').style.display = 'none';
        document.getElementById('panel-ended').style.display = 'block';

        if (ended && CFG.isTeacher && CFG.sessionId) {
            document.getElementById('ended-sub').textContent =
                'Marking session as completed…';
            fetch(CFG.endUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'function=end_session&sesskey=' + encodeURIComponent(CFG.sesskey)
                    + '&sessionid=' + CFG.sessionId
            }).then(function() {
                document.getElementById('ended-sub').textContent =
                    'Session marked as done. Recordings will appear below once processed.';
                loadRecordingsAjax();
            }).catch(function() {
                loadRecordingsAjax();
            });
        } else {
            document.getElementById('ended-sub').textContent =
                'You left the session. Recordings will appear below once processed.';
            loadRecordingsAjax();
        }
    }

    function loadRecordingsAjax() {
        if (!CFG.sessionId) return;
        fetch(CFG.endUrl + '?function=get_session_recordings&sessionid=' + CFG.sessionId
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

// Recordings shown at page-load only if session has already ended (teacher sees them always).
if ($session) {
    $show_recs = $is_teacher;
    if (!$is_teacher) {
        $now = time();
        $open_until = $session->start_time + ($session->duration * 60);
        $show_recs  = ($now > $open_until);
    }
    if ($show_recs) {
        jitsi_print_recordings($session, $context, $is_teacher);
    }
}

echo $OUTPUT->footer();

// -------------------------------------------------------------------------
// Helper: render recordings for this session.
// -------------------------------------------------------------------------
function jitsi_print_recordings($session, $context, $is_teacher) {
    global $DB, $OUTPUT;

    $recordings = $DB->get_records(
        'academy_session_recordings',
        ['sessionid' => $session->id],
        'timecreated DESC'
    );

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
        echo '<h5 class="card-title">' . s($rec->title ?: get_string('recording', 'jitsi')) . '</h5>';

        if ($rec->status === 'ready' && !empty($rec->bunny_video_id)) {
            try {
                $bunny      = new \local_academysessions\bunny_client();
                $expiresat  = time() + (2 * 3600);
                $embed_url  = $bunny->get_embed_url($rec->bunny_video_id, $expiresat);

                echo '<div style="position:relative;padding-top:56.25%;border-radius:6px;overflow:hidden;">';
                echo '<iframe src="' . s($embed_url) . '" loading="lazy"
                    style="border:none;position:absolute;top:0;left:0;height:100%;width:100%;"
                    allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;"
                    allowfullscreen="true"></iframe>';
                echo '</div>';
            } catch (Exception $e) {
                echo '<p class="text-muted">' . get_string('recnotavailable', 'jitsi') . '</p>';
            }
        } else if (!empty($rec->bunny_video_url)) {
            echo '<div style="position:relative;padding-top:56.25%;border-radius:6px;overflow:hidden;">';
            echo '<iframe src="' . s($rec->bunny_video_url) . '" loading="lazy"
                style="border:none;position:absolute;top:0;left:0;height:100%;width:100%;"
                allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;"
                allowfullscreen="true"></iframe>';
            echo '</div>';
        } else {
            echo '<p class="text-muted">' . get_string('recprocessing', 'jitsi') . '</p>';
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

    echo '</div>'; // jitsi-recordings
}
