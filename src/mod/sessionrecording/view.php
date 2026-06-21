<?php
require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('sessionrecording', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$recording = $DB->get_record('sessionrecording', array('id' => $cm->instance), '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/sessionrecording:view', $context);

$PAGE->set_url('/mod/sessionrecording/view.php', array('id' => $cm->id));
$PAGE->set_title($recording->name);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($recording->name);

$now = time();

// Check expiry.
if (!empty($recording->visible_until) && $now > $recording->visible_until) {
    echo $OUTPUT->notification(get_string('recordingexpired', 'sessionrecording'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

// Check attendance — only attended students + teachers can view.
$isteacher = has_capability('local/academysessions:managesessions', context_course::instance($course->id));
if (!$isteacher && !empty($recording->sessionid)) {
    $attended = $DB->record_exists('academy_session_attendance', array(
        'sessionid' => $recording->sessionid,
        'userid' => $USER->id
    ));
    if (!$attended) {
        echo $OUTPUT->notification(get_string('notattended', 'sessionrecording'), 'warning');
        echo $OUTPUT->footer();
        exit;
    }
}

// Generate fresh signed embed URL for each view (2h token).
$bunnyrecording = null;
if (!empty($recording->recordingid)) {
    $bunnyrecording = $DB->get_record('academy_session_recordings', array('id' => $recording->recordingid));
}

if ($bunnyrecording && !empty($bunnyrecording->bunny_video_id) && $bunnyrecording->status === 'ready') {
    $bunny = new \local_academysessions\bunny_client();
    $expiresat = time() + (2 * 3600);
    $embedurl = $bunny->get_embed_url($bunnyrecording->bunny_video_id, $expiresat);

    echo '<div style="position:relative;padding-top:56.25%;margin:20px 0;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">';
    echo '<iframe src="' . s($embedurl) . '" loading="lazy" style="border:none;position:absolute;top:0;left:0;height:100%;width:100%;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;" allowfullscreen="true"></iframe>';
    echo '</div>';
} else if (!empty($recording->bunny_video_url)) {
    echo '<div style="position:relative;padding-top:56.25%;margin:20px 0;border-radius:8px;overflow:hidden;">';
    echo '<iframe src="' . s($recording->bunny_video_url) . '" loading="lazy" style="border:none;position:absolute;top:0;left:0;height:100%;width:100%;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;" allowfullscreen="true"></iframe>';
    echo '</div>';
} else {
    echo $OUTPUT->notification(get_string('recordingnotready', 'sessionrecording'), 'info');
}

if (!empty($recording->visible_until)) {
    $remaining = $recording->visible_until - $now;
    $days = floor($remaining / 86400);
    if ($days > 0) {
        echo '<p class="text-muted" style="margin-top:10px;">This recording will be available for ' . $days . ' more day(s).</p>';
    }
}

echo $OUTPUT->footer();
