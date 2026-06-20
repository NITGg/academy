<?php
require_once('../../config.php');
require_once($CFG->dirroot . '/local/academysessions/classes/bunny_client.php');

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

if (!empty($recording->visible_until) && $now > $recording->visible_until) {
    echo $OUTPUT->notification(get_string('recordingexpired', 'sessionrecording'), 'warning');
    echo $OUTPUT->footer();
    exit;
}

if (!empty($recording->attendee_groupid)) {
    $ismember = groups_is_member($recording->attendee_groupid, $USER->id);
    $isteacher = has_capability('local/academysessions:managesessions', context_course::instance($course->id));
    if (!$ismember && !$isteacher) {
        echo $OUTPUT->notification(get_string('notattended', 'sessionrecording'), 'warning');
        echo $OUTPUT->footer();
        exit;
    }
}

if (!empty($recording->bunny_video_url)) {
    $embedurl = $recording->bunny_video_url;
    echo '<div style="position:relative;padding-top:56.25%;margin:20px 0;">';
    echo '<iframe src="' . s($embedurl) . '" loading="lazy" style="border:none;position:absolute;top:0;left:0;height:100%;width:100%;" allow="accelerometer;gyroscope;autoplay;encrypted-media;picture-in-picture;" allowfullscreen="true"></iframe>';
    echo '</div>';
} else {
    echo $OUTPUT->notification(get_string('recordingnotready', 'sessionrecording'), 'info');
}

if (!empty($recording->visible_until)) {
    $remaining = $recording->visible_until - $now;
    $days = floor($remaining / 86400);
    if ($days > 0) {
        echo '<p class="text-muted">This recording will be available for ' . $days . ' more day(s).</p>';
    }
}

echo $OUTPUT->footer();
