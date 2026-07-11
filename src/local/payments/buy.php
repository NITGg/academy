<?php
require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login();
require_capability('local/payments:purchasecourse', $context);

// Already actively enrolled — send straight to the course. An expired
// enrolment (lapsed subscription/package) must NOT short-circuit here, so the
// student can re-purchase or re-enrol rather than bouncing to a course they
// can no longer access.
if (is_enrolled($context, $USER->id, '', true)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

$action = optional_param('action', '', PARAM_ALPHA);

// Check for active subscription coverage — a normal subscription, a B2B admin's own purchase, or an
// approved B2B membership. Enrolment is on-demand for all of them (student clicks "Enroll").
$can_enroll_via_sub = false;
$subaccess = null;
if (class_exists('\local_academy\subscription_purchase_manager')) {
    $subaccess = \local_academy\subscription_purchase_manager::subscription_access_for_course($USER->id, $courseid);
    $can_enroll_via_sub = ($subaccess !== null);
}

// Handle enroll action
if ($can_enroll_via_sub && $action === 'enroll') {
    require_sesskey();
    \local_academy\subscription_purchase_manager::grant_single_course_access($courseid, $USER->id, $subaccess->expires_at);
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

// No active pricing — course is free/open, no payment gate to show.
if (!\local_payments\price_resolver::has_pricing($courseid) && !$can_enroll_via_sub) {
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

    // Price display fields fold in any active local_academy offer, so the discounted price the
    // student will actually be charged is shown (US-US-OF-1-2) — not the pre-offer price.
    $templatedata = array_merge([
        'courseid'       => $courseid,
        'is_enrolled'    => false,
        'is_purchased'   => $is_purchased,
        'buy_url'        => (new moodle_url('/local/payments/checkout.php', ['courseid' => $courseid]))->out(false),
        'can_enroll_via_sub' => $can_enroll_via_sub,
        'enroll_url'     => (new moodle_url('/local/payments/buy.php', ['courseid' => $courseid, 'action' => 'enroll', 'sesskey' => sesskey()]))->out(false),
    ], \local_payments\price_resolver::display_fields($pricing, $courseid));

    echo $OUTPUT->render_from_template('local_payments/course_page_price', $templatedata);

    // The price row above already shows the offer-discounted "was → now" price (via display_fields).
    // Add a compact tag with the offer's name so the student knows why the price dropped.
    if (!$is_purchased && !empty($templatedata['offer_name'])) {
        echo '<div style="max-width:340px;margin-top:.6rem;display:inline-flex;align-items:center;gap:.4rem;'
           . 'background:#fdecef;border:1px solid #f5b7c0;color:#8a1024;border-radius:1rem;'
           . 'padding:.3rem .8rem;font-weight:700;font-size:.9rem">🏷️ ' . s($templatedata['offer_name']) . '</div>';
    }

    // Coupon entry (US-US-CP-1-2). Submitting sends the code to checkout.php, which applies it (and
    // any automatic offer) to the charged amount. Only shown when the course is still purchasable.
    if (!$is_purchased) {
        $checkouturl = (new moodle_url('/local/payments/checkout.php'))->out(false);
        echo '<form method="get" action="' . s($checkouturl) . '" class="mt-3" style="max-width:340px">'
           . '<input type="hidden" name="courseid" value="' . (int) $courseid . '">'
           . '<label for="academy-coupon-code" class="font-weight-bold">'
           . s(get_string('cpn_have_code', 'local_academy')) . '</label>'
           . '<div style="display:flex;gap:.5rem">'
           . '<input type="text" id="academy-coupon-code" name="coupon_code" class="form-control" '
           . 'placeholder="' . s(get_string('cpn_code', 'local_academy')) . '">'
           . '<button type="submit" class="btn btn-outline-primary">'
           . s(get_string('cpn_apply_buy', 'local_academy')) . '</button>'
           . '</div></form>';
    }
} catch (\moodle_exception $e) {
    echo $OUTPUT->notification(get_string('nopricefound', 'local_payments'), 'info');
    echo $OUTPUT->continue_button(new moodle_url('/'));
}

echo $OUTPUT->footer();
