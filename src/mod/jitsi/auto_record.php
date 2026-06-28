<?php
/**
 * Auto-start Jibri recording for the current Jitsi session.
 * Called via fetch() from view.php after videoConferenceJoined fires.
 * Only teachers can trigger this; sesskey validates the request.
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/jitsi/lib.php');

require_sesskey();

$cmid = required_param('cmid', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'jitsi');
$context = context_module::instance($cm->id);
require_login($course, false, $cm);
require_capability('mod/jitsi:moderate', $context);

$jitsi = $DB->get_record('jitsi', ['id' => $cm->instance], '*', MUST_EXIST);

$jitsi_room = 'academy_jitsi_' . $cm->id . '_' . substr(md5($jitsi->id . $cm->id), 0, 8);

// Jibri runs inside Docker and reaches Jitsi web via the internal network name.
// The JWT is required because Jitsi uses JWT auth — without it, the page shows
// an error and APP never initializes (causing "APP is not defined" timeout).
$jibri_base_url = get_config('local_academysessions', 'jibri_jitsi_url') ?: 'http://meet.jitsi';
$jibri_url      = get_config('local_academysessions', 'jibri_api_url') ?: 'http://academy_jibri:2223';
$jibri_pass     = get_config('local_academysessions', 'jibri_recorder_password') ?: '3542497ebee440c2c4b12b5a41f474d2';

// Generate a recorder JWT (non-moderator, so it doesn't affect room ownership).
$recorder_jwt = \local_academysessions\jitsi_jwt::generate(
    $jitsi_room, 'Recorder', 'recorder@meet.jitsi', false
);

$body = json_encode([
    'sessionId'       => 'academy-' . $cm->id . '-' . time(),
    'sinkType'        => 'file',
    'callParams'      => [
        'callUrlInfo' => [
            'baseUrl'   => $jibri_base_url,
            'callName'  => $jitsi_room,
            'urlParams' => ['jwt=' . $recorder_jwt],
        ],
    ],
    'callLoginParams' => [
        'domain'   => 'hidden.meet.jitsi',
        'username' => 'recorder',
        'password' => $jibri_pass,
    ],
]);

$ch = curl_init($jibri_url . '/jibri/api/v1.0/startService');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
echo json_encode(['status' => $code, 'response' => $resp]);
