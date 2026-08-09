<?php
defined('MOODLE_INTERNAL') || die();

function local_payments_extend_navigation(global_navigation $navigation) {
    // Navigation hooks will be added as needed.
}

function local_payments_extend_navigation_course(\navigation_node $navigation, \stdClass $course, \context_course $context) {
    if (has_capability('local/payments:managecoursepricing', $context)) {
        $url = new \moodle_url('/local/payments/course_pricing.php', ['courseid' => $course->id]);
        $navigation->add(
            get_string('coursepricing', 'local_payments'),
            $url,
            \navigation_node::TYPE_SETTING,
            null,
            'local_payments_pricing',
            new \pix_icon('i/payment', '')
        );
    }
}

// The course-view payment gate previously implemented here as
// local_payments_before_http_headers() is now a hook callback
// (\local_payments\hook_callbacks::before_http_headers) registered in db/hooks.php,
// as required by the Moodle 4.4+ Hooks API.
