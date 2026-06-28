<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/jitsi/lib.php');

require_sesskey();

$cmid = required_param('cmid', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'jitsi');
$context = context_module::instance($cm->id);
require_login($course, false, $cm);
require_capability('mod/jitsi:moderate', $context);

$jibri_url = get_config('local_academysessions', 'jibri_api_url') ?: 'http://academy_jibri:2223';

$ch = curl_init($jibri_url . '/jibri/api/v1.0/stopService');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => '{}',
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
echo json_encode(['status' => $code, 'response' => $resp]);
