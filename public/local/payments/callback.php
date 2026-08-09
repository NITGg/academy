<?php
require_once(__DIR__ . '/../../config.php');

$order_id      = required_param('order_id', PARAM_TEXT);
// Kashier appends paymentStatus=SUCCESS|FAILED to the redirect URL.
$kashier_status = strtoupper(optional_param('paymentStatus', '', PARAM_ALPHANUMEXT));

require_login();

$PAGE->set_url(new moodle_url('/local/payments/callback.php', ['order_id' => $order_id]));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

// ── FAILED redirect from Kashier ────────────────────────────────────────────
// Kashier told us right now that the payment failed. Update the DB and show
// the failure page immediately — no need to call Kashier's API again.
if ($kashier_status === 'FAILED') {
    $transaction = $DB->get_record('local_payments_transactions', ['order_id' => $order_id]);

    if ($transaction && $transaction->status === \local_payments\status_machine::PENDING) {
        $DB->update_record('local_payments_transactions', (object) [
            'id'            => $transaction->id,
            'status'        => \local_payments\status_machine::FAILED,
            'reject_reason' => 'Payment failed at provider (redirect)',
            'timemodified'  => time(),
        ]);
    }

    $PAGE->set_title(get_string('payment_failure', 'local_payments'));
    echo $OUTPUT->header();
    $templatedata = [
        'success'      => false,
        'status'       => 'FAILED',
        'order_id'     => $order_id,
        'retry_url'    => $transaction
            ? (new moodle_url('/local/payments/buy.php', ['courseid' => $transaction->courseid]))->out(false)
            : (new moodle_url('/'))->out(false),
        'history_url'  => (new moodle_url('/local/payments/history.php'))->out(false),
    ];
    echo $OUTPUT->render_from_template('local_payments/payment_failure', $templatedata);
    echo $OUTPUT->footer();
    exit;
}

// ── SUCCESS / unknown redirect — read DB state set by the webhook ────────────
// The webhook (server-to-server) is the authoritative path for enrollment.
// We just read what the webhook already wrote. The Kashier API fallback inside
// verify_callback() is only reached when the webhook hasn't fired yet.
try {
    $result = \local_payments\manager::verify_callback($order_id);

    if ($result->success) {
        // Packages and subscriptions are not tied to a single course, so there
        // is no course page to land on. Send the student straight to the home
        // dashboard with a success notice instead of the generic success page.
        $item_type = $result->item_type ?? 'course';
        if ($item_type === 'package' || $item_type === 'subscription') {
            redirect(
                new moodle_url('/'),
                get_string('payment_success', 'local_payments'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        $PAGE->set_title(get_string('payment_success', 'local_payments'));
        echo $OUTPUT->header();

        $course = $DB->get_record('course', ['id' => $result->courseid], 'id, fullname');
        $templatedata = [
            'success'      => true,
            'course_name'  => $course->fullname ?? '',
            'course_url'   => (new moodle_url('/course/view.php', ['id' => $result->courseid]))->out(false),
            'order_id'     => $order_id,
            'history_url'  => (new moodle_url('/local/payments/history.php'))->out(false),
        ];
        echo $OUTPUT->render_from_template('local_payments/payment_success', $templatedata);
    } else {
        $PAGE->set_title(get_string('payment_failure', 'local_payments'));
        echo $OUTPUT->header();

        $transaction = $DB->get_record('local_payments_transactions', ['order_id' => $order_id]);
        $templatedata = [
            'success'     => false,
            'status'      => $result->status,
            'order_id'    => $order_id,
            'retry_url'   => $transaction
                ? (new moodle_url('/local/payments/buy.php', ['courseid' => $transaction->courseid]))->out(false)
                : (new moodle_url('/'))->out(false),
            'history_url' => (new moodle_url('/local/payments/history.php'))->out(false),
        ];
        echo $OUTPUT->render_from_template('local_payments/payment_failure', $templatedata);
    }
} catch (\Exception $e) {
    $PAGE->set_title(get_string('payment_failure', 'local_payments'));
    echo $OUTPUT->header();
    echo $OUTPUT->notification($e->getMessage(), 'error');
    echo $OUTPUT->continue_button(new moodle_url('/'));
}

echo $OUTPUT->footer();
