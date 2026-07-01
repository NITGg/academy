<?php
require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login();
require_capability('local/payments:purchasecourse', $context);

// Already enrolled — send straight to the course.
if (is_enrolled($context, $USER->id)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

// No active pricing — course is free/open, no payment gate to show.
if (!\local_payments\price_resolver::has_pricing($courseid)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

$PAGE->set_url(new moodle_url('/local/payments/buy.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

if (!empty($course->summary)) {
    echo $OUTPUT->box(format_text($course->summary, $course->summaryformat), 'generalbox mb-3');
}

try {
    $pricing = \local_payments\price_resolver::resolve($courseid, $USER->id);
    $is_purchased = \local_payments\price_resolver::is_purchased($courseid, $USER->id);

    $templatedata = [
        'courseid'       => $courseid,
        'is_enrolled'    => false,
        'is_purchased'   => $is_purchased,
        'price'          => number_format((float) $pricing->price, 2),
        'sale_price'     => $pricing->sale_price !== null ? number_format((float) $pricing->sale_price, 2) : '',
        'original_price' => number_format((float) $pricing->original_price, 2),
        'currency'       => $pricing->currency,
        'is_sale_active' => (bool) $pricing->is_sale_active,
        'discount_pct'   => (int) $pricing->discount_pct,
        'buy_url'        => (new moodle_url('/local/payments/checkout.php', ['courseid' => $courseid]))->out(false),
    ];

    echo $OUTPUT->render_from_template('local_payments/course_page_price', $templatedata);
} catch (\moodle_exception $e) {
    echo $OUTPUT->notification(get_string('nopricefound', 'local_payments'), 'info');
    echo $OUTPUT->continue_button(new moodle_url('/'));
}

echo $OUTPUT->footer();
