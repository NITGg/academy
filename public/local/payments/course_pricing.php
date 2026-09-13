<?php
// The former "Course pricing" screen. Prices are now set on the course settings
// form itself — the "Course pricing" section that \local_payments\local\hooks\course_form
// adds to course/edit.php — so this address only forwards there. Kept because
// it is linked from old bookmarks, the diagnose page's history and the docs.
require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/payments:managecoursepricing', $context);

redirect(\local_payments\course_pricing::settings_url($courseid));
