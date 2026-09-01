<?php
/**
 * Refund a payment as a member of staff, from the payments list.
 *
 * Separate from refund.php, which is the buyer's route and is bound by the
 * refund window. This one is not: staff are the people the window exists to
 * escalate to. It is deliberately a confirmation page rather than a button that
 * acts on click, because the money leaves immediately and cannot be recalled.
 */
require_once(__DIR__ . '/../../config.php');

$transactionid = required_param('transaction_id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$reason = trim(optional_param('reason', '', PARAM_TEXT));
$applyfee = optional_param('applyfee', 0, PARAM_BOOL);
$returnto = optional_param('returnto', '', PARAM_LOCALURL);

require_login();

$context = context_system::instance();
require_capability('local/payments:managerefunds', $context);

$transaction = $DB->get_record('local_payments_transactions', ['id' => $transactionid], '*', MUST_EXIST);

$returnurl = $returnto
    ? new moodle_url($returnto)
    : new moodle_url('/local/payments/transactions.php');
$pageurl = new moodle_url('/local/payments/staff_refund.php',
    ['transaction_id' => $transactionid, 'returnto' => $returnto]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('refund_staff_title', 'local_payments'));
$PAGE->set_heading(get_string('refund_staff_title', 'local_payments'));

if (!\local_payments\refund_policy::enabled()) {
    throw new moodle_exception('refund_err_disabled', 'local_payments', $returnurl);
}
if ($transaction->status !== \local_payments\status_machine::COMPLETED) {
    throw new moodle_exception('refund_err_notrefundable', 'local_payments', $returnurl);
}

$quote = \local_payments\refund_policy::quote($transaction);

if ($confirm && confirm_sesskey()) {
    $result = \local_payments\refund_manager::staff_refund($transaction, $reason, (bool) $applyfee);
    redirect($returnurl, $result->message, null,
        $result->success ? \core\output\notification::NOTIFY_SUCCESS
            : \core\output\notification::NOTIFY_ERROR);
}

$buyer = $DB->get_record('user', ['id' => $transaction->userid]);
$course = !empty($transaction->courseid)
    ? $DB->get_record('course', ['id' => $transaction->courseid], 'id, fullname')
    : null;

echo $OUTPUT->header();

$money = static function (float $value) use ($transaction): string {
    return format_float($value, 2, true, true) . ' ' . $transaction->currency;
};

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->data = [
    [get_string('orderid', 'local_payments'), $transaction->order_id],
    [get_string('student', 'local_payments'), $buyer ? fullname($buyer) : '-'],
];
if ($course) {
    $table->data[] = [get_string('course', 'local_payments'), format_string($course->fullname)];
}
$table->data[] = [get_string('refund_paid', 'local_payments'), $money((float) $transaction->amount)];
echo html_writer::table($table);

// Refunding removes access, which is the part that surprises people.
echo $OUTPUT->notification(get_string('refund_staff_warning', 'local_payments'), 'warning');

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);

echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('refund_reason_required', 'local_payments'),
    ['for' => 'lp-staff-reason', 'class' => 'form-label']);
echo html_writer::tag('textarea', '', [
    'id' => 'lp-staff-reason',
    'name' => 'reason',
    'rows' => 3,
    'class' => 'form-control',
    'required' => 'required',
]);
echo html_writer::end_div();

// Offered rather than assumed: a refund staff are giving is usually a correction.
if ($quote->fee > 0) {
    echo html_writer::start_div('form-check mb-3');
    echo html_writer::empty_tag('input', [
        'type' => 'checkbox',
        'id' => 'lp-staff-applyfee',
        'name' => 'applyfee',
        'value' => 1,
        'class' => 'form-check-input',
    ]);
    echo html_writer::tag('label',
        get_string('refund_staff_applyfee', 'local_payments', $money($quote->fee)),
        ['for' => 'lp-staff-applyfee', 'class' => 'form-check-label']);
    echo html_writer::end_div();
}

echo html_writer::tag('button', get_string('refund_staff_button', 'local_payments'),
    ['type' => 'submit', 'class' => 'btn btn-danger']);
echo html_writer::link($returnurl, get_string('cancel'), ['class' => 'btn btn-outline-secondary ms-2']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
