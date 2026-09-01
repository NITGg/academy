<?php
/**
 * The refund requests waiting on a decision.
 *
 * Approving one runs the gateway refund on the terms the buyer was quoted when
 * they asked — not today's settings, which may have moved since.
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$action = optional_param('action', '', PARAM_ALPHA);
$requestid = optional_param('id', 0, PARAM_INT);
$note = trim(optional_param('note', '', PARAM_TEXT));
$status = optional_param('status', \local_payments\refund_manager::REQ_PENDING, PARAM_ALPHA);

admin_externalpage_setup('local_payments_refund_requests');
$context = context_system::instance();
require_capability('local/payments:managerefunds', $context);

$pageurl = new moodle_url('/local/payments/refund_requests.php', ['status' => $status]);
$PAGE->set_url($pageurl);

if ($action && $requestid && confirm_sesskey()) {
    $request = $DB->get_record('local_payments_refund_reqs', ['id' => $requestid], '*', MUST_EXIST);

    if ($action === 'approve') {
        $result = \local_payments\refund_manager::approve($request, $note);
    } else if ($action === 'reject') {
        $result = \local_payments\refund_manager::reject($request, $note);
    } else {
        $result = (object) ['success' => false, 'message' => get_string('error')];
    }

    redirect($pageurl, $result->message, null,
        $result->success ? \core\output\notification::NOTIFY_SUCCESS
            : \core\output\notification::NOTIFY_ERROR);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('refund_requests', 'local_payments'));

if (!\local_payments\refund_policy::enabled()) {
    echo $OUTPUT->notification(get_string('refund_err_disabled', 'local_payments'), 'warning');
}

// Status tabs: pending is the working queue, the rest are history.
$tabs = [
    \local_payments\refund_manager::REQ_PENDING => 'refund_status_pending',
    \local_payments\refund_manager::REQ_APPROVED => 'refund_status_approved',
    \local_payments\refund_manager::REQ_REJECTED => 'refund_status_rejected',
];
$links = [];
foreach ($tabs as $value => $key) {
    $count = $DB->count_records('local_payments_refund_reqs', ['status' => $value]);
    $links[] = html_writer::link(
        new moodle_url('/local/payments/refund_requests.php', ['status' => $value]),
        get_string($key, 'local_payments') . ' (' . $count . ')',
        ['class' => 'btn btn-sm ' . ($status === $value ? 'btn-primary' : 'btn-outline-secondary')]
    );
}
echo html_writer::div(implode(' ', $links), 'lp-invoice-actions mb-3');

$userfields = \core_user\fields::for_name()->get_sql('u')->selects;
$requests = $DB->get_records_sql(
    "SELECT r.*, t.order_id, t.courseid, t.amount AS paid_amount, t.status AS txn_status,
            u.email {$userfields},
            c.fullname AS coursename
       FROM {local_payments_refund_reqs} r
       JOIN {local_payments_transactions} t ON t.id = r.transaction_id
       JOIN {user} u ON u.id = r.userid
  LEFT JOIN {course} c ON c.id = t.courseid
      WHERE r.status = :status
   ORDER BY r.timecreated ASC",
    ['status' => $status]
);

if (empty($requests)) {
    echo $OUTPUT->notification(get_string('refund_norequests', 'local_payments'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$pending = ($status === \local_payments\refund_manager::REQ_PENDING);

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [
    get_string('date', 'local_payments'),
    get_string('student', 'local_payments'),
    get_string('orderid', 'local_payments'),
    get_string('course', 'local_payments'),
    get_string('refund_youget', 'local_payments'),
    get_string('refund_reason_required', 'local_payments'),
    $pending ? get_string('refund_decide', 'local_payments') : get_string('refund_decision', 'local_payments'),
];

foreach ($requests as $r) {
    $net = max(0, (float) $r->quoted_amount - (float) $r->quoted_fee);
    $amount = format_float($net, 2, true, true) . ' ' . $r->currency;
    if ((float) $r->quoted_fee > 0) {
        $amount .= html_writer::tag('div',
            get_string('refund_after_fee', 'local_payments',
                format_float((float) $r->quoted_fee, 2, true, true) . ' ' . $r->currency),
            ['class' => 'small text-muted']);
    }

    if ($pending) {
        // The note travels with whichever button is pressed, so a rejection can
        // carry the reason the buyer will be shown.
        $decide = html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $pageurl->out(false),
            'class' => 'lp-refund-decide',
        ]);
        $decide .= html_writer::empty_tag('input',
            ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $decide .= html_writer::empty_tag('input',
            ['type' => 'hidden', 'name' => 'id', 'value' => $r->id]);
        $decide .= html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'note',
            'class' => 'form-control form-control-sm',
            'placeholder' => get_string('refund_note_placeholder', 'local_payments'),
        ]);
        $decide .= html_writer::tag('button', get_string('refund_approve', 'local_payments'), [
            'type' => 'submit', 'name' => 'action', 'value' => 'approve',
            'class' => 'btn btn-sm btn-success',
        ]);
        $decide .= html_writer::tag('button', get_string('refund_reject', 'local_payments'), [
            'type' => 'submit', 'name' => 'action', 'value' => 'reject',
            'class' => 'btn btn-sm btn-outline-danger',
        ]);
        $decide .= html_writer::end_tag('form');
    } else {
        $decider = $r->decided_by ? $DB->get_record('user', ['id' => $r->decided_by]) : null;
        $decide = $decider ? fullname($decider) : '-';
        if (!empty($r->decision_note)) {
            $decide .= html_writer::tag('div', s($r->decision_note), ['class' => 'small text-muted']);
        }
    }

    $table->data[] = [
        userdate($r->timecreated),
        html_writer::link(new moodle_url('/user/profile.php', ['id' => $r->userid]), fullname($r))
            . html_writer::tag('div', $r->email, ['class' => 'small text-muted']),
        html_writer::tag('code', $r->order_id),
        $r->coursename ? format_string($r->coursename) : '-',
        $amount,
        shorten_text(s((string) $r->reason), 160),
        $decide,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
