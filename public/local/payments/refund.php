<?php
/**
 * The buyer's side of a refund.
 *
 * One screen for both routes. It shows what the money would be, says plainly
 * whether the answer is immediate or goes to a person, and takes a reason. The
 * route is decided here rather than by the button that linked here, so a page
 * left open past the deadline cannot refund itself.
 */
require_once(__DIR__ . '/../../config.php');

$transactionid = required_param('transaction_id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$reason = trim(optional_param('reason', '', PARAM_TEXT));

require_login();

$context = context_system::instance();
$transaction = $DB->get_record('local_payments_transactions', ['id' => $transactionid], '*', MUST_EXIST);

// Only the buyer asks for their own money back. Staff refund from the payments
// list instead, where the decision is recorded against them.
if ((int) $transaction->userid !== (int) $USER->id) {
    throw new moodle_exception('invalidaccess', 'error');
}

$returnurl = new moodle_url('/local/payments/history.php');
$pageurl = new moodle_url('/local/payments/refund.php', ['transaction_id' => $transactionid]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('refund_request', 'local_payments'));
$PAGE->set_heading(get_string('refund_request', 'local_payments'));

$blocker = \local_payments\refund_manager::blocker($transaction);
if ($blocker !== '') {
    throw new moodle_exception($blocker, 'local_payments', $returnurl);
}

$quote = \local_payments\refund_policy::quote($transaction);
$instant = $quote->withinwindow;

if ($confirm && confirm_sesskey()) {
    if ($instant) {
        $result = \local_payments\refund_manager::self_refund($transaction, $reason);
        redirect($returnurl, $result->message,
            null,
            $result->success ? \core\output\notification::NOTIFY_SUCCESS
                : \core\output\notification::NOTIFY_ERROR);
    }

    if ($reason === '') {
        // A request with no reason gives staff nothing to decide on.
        redirect($pageurl, get_string('refund_err_needreason', 'local_payments'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    \local_payments\refund_manager::request($transaction, $reason);
    redirect($returnurl, get_string('refund_requested', 'local_payments'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

$course = !empty($transaction->courseid)
    ? $DB->get_record('course', ['id' => $transaction->courseid], 'id, fullname')
    : null;

echo $OUTPUT->header();
echo $OUTPUT->heading($instant
    ? get_string('refund_now_title', 'local_payments')
    : get_string('refund_ask_title', 'local_payments'));

$money = static function (float $value) use ($quote): string {
    return format_float($value, 2, true, true) . ' ' . $quote->currency;
};

$table = new html_table();
$table->attributes['class'] = 'generaltable lp-refund-summary';
$table->data = [
    [get_string('orderid', 'local_payments'), $transaction->order_id],
];
if ($course) {
    $table->data[] = [get_string('course', 'local_payments'), format_string($course->fullname)];
}
$table->data[] = [get_string('refund_paid', 'local_payments'), $money($quote->paid)];
if ($quote->fee > 0) {
    $table->data[] = [get_string('refund_fee', 'local_payments'), '-' . $money($quote->fee)];
}
$table->data[] = [
    html_writer::tag('strong', get_string('refund_youget', 'local_payments')),
    html_writer::tag('strong', $money($quote->net)),
];

echo html_writer::table($table);

// Say what happens next before asking them to commit to it.
if ($instant) {
    echo $OUTPUT->notification(
        get_string('refund_now_notice', 'local_payments',
            userdate($quote->deadline)),
        'info'
    );
} else {
    echo $OUTPUT->notification(
        $quote->hours > 0
            ? get_string('refund_ask_notice_closed', 'local_payments', userdate($quote->deadline))
            : get_string('refund_ask_notice_nowindow', 'local_payments'),
        'info'
    );
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);

echo html_writer::start_div('mb-3');
echo html_writer::tag('label', $instant
    ? get_string('refund_reason_optional', 'local_payments')
    : get_string('refund_reason_required', 'local_payments'),
    ['for' => 'lp-refund-reason', 'class' => 'form-label']);
echo html_writer::tag('textarea', s($reason), [
    'id' => 'lp-refund-reason',
    'name' => 'reason',
    'rows' => 4,
    'class' => 'form-control',
    'required' => $instant ? null : 'required',
]);
echo html_writer::end_div();

echo html_writer::tag('button', $instant
    ? get_string('refund_now_button', 'local_payments')
    : get_string('refund_ask_button', 'local_payments'),
    ['type' => 'submit', 'class' => 'btn btn-primary']);
echo html_writer::link($returnurl, get_string('cancel'), ['class' => 'btn btn-outline-secondary ms-2']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
