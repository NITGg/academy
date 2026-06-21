<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Prints an instance of mod_googlemeet.
 *
 * @package     mod_googlemeet
 * @copyright   2020 Rone Santos <ronefel@hotmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_googlemeet\client;

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

$config = get_config('googlemeet');

$id = optional_param('id', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('googlemeet', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $googlemeet = $DB->get_record('googlemeet', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($g) {
    $googlemeet = $DB->get_record('googlemeet', array('id' => $n), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $googlemeet->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('googlemeet', $googlemeet->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('missingidandcmid', 'mod_googlemeet');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/googlemeet:view', $context);

$PAGE->set_url('/mod/googlemeet/view.php', array('id' => $cm->id));
$PAGE->set_context($context);

if (has_capability('mod/googlemeet:editrecording', $context)) {
    $client = new client();
    $logout = optional_param('logout', 0, PARAM_BOOL);
    if ($logout) {
        $client->logout();
    }
    $sync = optional_param('sync', 0, PARAM_BOOL);
    if ($sync) {
        $client->syncrecordings($googlemeet);
    }
}

// Make sure URL exists before generating output - some older sites may contain empty urls
// Do not use PARAM_URL here, it is too strict and does not support general URIs!
$url = trim($googlemeet->url);
$pattern = "/^https:\/\/meet.google.com\/[-a-zA-Z0-9@:%._\+~#=]{3}-[-a-zA-Z0-9@:%._\+~#=]{4}-[-a-zA-Z0-9@:%._\+~#=]{3}$/";
if (!preg_match($pattern, $url)) {
    googlemeet_print_header($googlemeet, $cm, $course);
    googlemeet_print_heading($googlemeet, $cm, $course);
    googlemeet_print_intro($googlemeet, $cm, $course);
    notice(get_string('invalidstoredurl', 'googlemeet'), new moodle_url('/course/view.php', array('id' => $cm->course)));
    die;
}
unset($url);

// Check academy session access control.
$is_teacher = has_capability('mod/googlemeet:editrecording', $context);
$session_allowed = true;
$session = null;

$linked_session = $DB->get_record('academy_live_sessions', array('googlemeetid' => $googlemeet->id));
if ($linked_session && !$is_teacher) {
    $session = $linked_session;
    $is_allowed_student = $DB->record_exists('academy_session_students', array(
        'sessionid' => $session->id,
        'userid' => $USER->id
    ));
    if (!$is_allowed_student) {
        googlemeet_print_header($googlemeet, $cm, $course);
        googlemeet_print_heading($googlemeet, $cm, $course);
        echo $OUTPUT->notification('You are not allowed to access this session.', 'warning');
        echo $OUTPUT->footer();
        die;
    }

    $now = time();
    $link_available_from = $session->start_time - 1800;
    $link_available_until = $session->start_time + ($session->duration * 60);

    if ($now < $link_available_from) {
        googlemeet_print_header($googlemeet, $cm, $course);
        googlemeet_print_heading($googlemeet, $cm, $course);
        $mins = ceil(($link_available_from - $now) / 60);
        echo $OUTPUT->notification("Session link will be available in {$mins} minute(s).", 'info');
        echo $OUTPUT->footer();
        die;
    }

    if ($now > $link_available_until) {
        googlemeet_print_header($googlemeet, $cm, $course);
        googlemeet_print_heading($googlemeet, $cm, $course);
        echo $OUTPUT->notification('This session has ended.', 'info');
        googlemeet_print_recordings($googlemeet, $cm, $context);
        echo $OUTPUT->footer();
        die;
    }

    // Record attendance.
    if (!$DB->record_exists('academy_session_attendance', array('sessionid' => $session->id, 'userid' => $USER->id))) {
        $att = new stdClass();
        $att->sessionid = $session->id;
        $att->userid = $USER->id;
        $att->joined_at = $now;
        $att->duration_seconds = 0;
        $DB->insert_record('academy_session_attendance', $att);
    }
}

// Completion and trigger events.
googlemeet_view($googlemeet, $course, $cm, $context);

googlemeet_print_header($googlemeet, $cm, $course);
googlemeet_print_heading($googlemeet, $cm, $course, true);
googlemeet_print_intro($googlemeet, $cm, $course, true);

// Build Jitsi config.
$jitsi_host = get_config('local_academysessions', 'jitsi_host') ?: 'localhost:8443';
$jitsi_scheme = (strpos($jitsi_host, 'localhost') !== false) ? 'http' : 'https';
$jitsi_room = 'academy_' . $cm->id . '_' . substr(md5($googlemeet->url . $cm->id), 0, 8);
$display_name = fullname($USER);
$user_email = $USER->email;

// Toolbar buttons — teachers get recording + admin controls.
$toolbar_teacher = 'microphone,camera,desktop,chat,recording,whiteboard,raisehand,participants-pane,tileview,fullscreen,hangup';
$toolbar_student = 'microphone,camera,chat,whiteboard,raisehand,tileview,fullscreen,hangup';
$toolbar = $is_teacher ? $toolbar_teacher : $toolbar_student;
$toolbar_json = json_encode(explode(',', array_map('trim', explode(',', $toolbar))));

// Build Jitsi iframe URL with config via hash params.
$hash_params = implode('&', [
    'config.startWithAudioMuted=true',
    'config.startWithVideoMuted=false',
    'config.prejoinPageEnabled=false',
    'config.disableDeepLinking=true',
    'config.disableInviteFunctions=true',
    'config.enableClosePage=false',
    'config.subject=' . urlencode($googlemeet->name),
    'config.toolbarButtons=' . urlencode(json_encode(array_map('trim', explode(',', $toolbar)))),
    'config.enableRecording=' . ($is_teacher ? 'true' : 'false'),
    'config.fileRecordingsEnabled=' . ($is_teacher ? 'true' : 'false'),
    'config.liveStreamingEnabled=false',
    'userInfo.displayName=' . urlencode($display_name),
    'userInfo.email=' . urlencode($user_email),
]);

$jitsi_url = $jitsi_scheme . '://' . $jitsi_host . '/' . urlencode($jitsi_room) . '#' . $hash_params;

// Excalidraw config.
$excalidraw_app  = get_config('local_academysessions', 'excalidraw_app') ?: 'http://localhost:9091';
$excalidraw_room = 'academy_wb_' . $cm->id;

echo '<div id="jitsi-session-container" style="margin:15px 0;">';

// Tab buttons.
echo '<div style="display:flex;gap:8px;margin-bottom:10px;">';
echo '<button id="tab-video" onclick="switchTab(\'video\')" class="btn btn-primary btn-sm" style="border-radius:16px;">Video Session</button>';
echo '<button id="tab-whiteboard" onclick="switchTab(\'whiteboard\')" class="btn btn-outline-secondary btn-sm" style="border-radius:16px;">Whiteboard</button>';
if ($is_teacher) {
    echo '<span class="ml-auto badge badge-info" style="align-self:center;font-size:12px;">You are the host</span>';
}
echo '</div>';

// Jitsi iframe panel.
echo '<div id="panel-video" style="width:100%;height:600px;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.15);">';
echo '<iframe id="jitsi-frame" src="' . s($jitsi_url) . '"
    style="width:100%;height:100%;border:none;"
    allow="camera; microphone; display-capture; autoplay; clipboard-write; fullscreen"
    allowfullscreen>
</iframe>';
echo '</div>';

// Excalidraw whiteboard panel (hidden by default).
echo '<div id="panel-whiteboard" style="display:none;width:100%;height:600px;border-radius:8px;overflow:hidden;border:1px solid #e0e0e0;">';
echo '<iframe src="' . s(rtrim($excalidraw_app, '/') . '/#room=' . $excalidraw_room) . '" style="width:100%;height:100%;border:none;" allow="clipboard-read;clipboard-write"></iframe>';
echo '</div>';

echo '</div>';

echo '<script>
function switchTab(tab) {
    var videoPanel = document.getElementById("panel-video");
    var wbPanel = document.getElementById("panel-whiteboard");
    var tabVideo = document.getElementById("tab-video");
    var tabWb = document.getElementById("tab-whiteboard");

    if (tab === "video") {
        videoPanel.style.display = "block";
        wbPanel.style.display = "none";
        tabVideo.className = "btn btn-primary btn-sm";
        tabVideo.style.borderRadius = "16px";
        tabWb.className = "btn btn-outline-secondary btn-sm";
        tabWb.style.borderRadius = "16px";
    } else {
        videoPanel.style.display = "none";
        wbPanel.style.display = "block";
        tabVideo.className = "btn btn-outline-secondary btn-sm";
        tabVideo.style.borderRadius = "16px";
        tabWb.className = "btn btn-primary btn-sm";
        tabWb.style.borderRadius = "16px";
    }
}
</script>';

echo $OUTPUT->render_from_template('mod_googlemeet/upcomingevents', googlemeet_get_upcoming_events($googlemeet->id));

googlemeet_print_recordings($googlemeet, $cm, $context);

echo $OUTPUT->footer();