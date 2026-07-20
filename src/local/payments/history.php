<?php
require_once(__DIR__ . '/../../config.php');

/**
 * Best-effort item name for a transaction with no invoice yet (pending/failed/cancelled — an
 * invoice is only issued once a purchase completes). Read from the metadata snapshotted at
 * checkout time rather than looking the item up live, since a failed purchase's item may since
 * have been deleted.
 */
function local_payments_history_item_name(stdClass $txn): string {
    global $DB;

    $meta = json_decode($txn->metadata ?? '{}');
    $type = $meta->item_type ?? 'course';

    switch ($type) {
        case 'package':
            return $meta->package_name ?? '';
        case 'subscription':
            return $meta->subscription_name ?? '';
        case 'program':
            return $meta->program_name ?? '';
        default:
            return (string) ($DB->get_field('course', 'fullname', ['id' => $txn->courseid]) ?: '');
    }
}

require_login();

$PAGE->set_url(new moodle_url('/local/payments/history.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('paymenthistory', 'local_payments'));
$PAGE->set_heading(get_string('paymenthistory', 'local_payments'));
$PAGE->set_pagelayout('standard');

$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;

$transactions = $DB->get_records_select(
    'local_payments_transactions',
    'userid = :userid',
    ['userid' => $USER->id],
    'timecreated DESC',
    '*',
    $page * $perpage,
    $perpage
);

$total = $DB->count_records('local_payments_transactions', ['userid' => $USER->id]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('paymenthistory', 'local_payments'));

if (empty($transactions)) {
    echo $OUTPUT->notification(get_string('nopayments', 'local_payments'), 'info');
} else {
    $rows = [];
    foreach ($transactions as $txn) {
        // The invoice (once issued) already carries the resolved item name for every purchase
        // type, course/package/subscription/program alike — reuse it rather than re-deriving.
        // Package and subscription transactions never carry a courseid, so without this a
        // completed package/subscription/program purchase showed a blank "-" here.
        $invoice = $DB->get_record('local_payments_invoices', ['transaction_id' => $txn->id]);
        $itemname = $invoice->item_name ?? local_payments_history_item_name($txn);

        $status_class = '';
        switch ($txn->status) {
            case 'completed': $status_class = 'badge-success'; break;
            case 'pending': $status_class = 'badge-warning'; break;
            case 'failed': case 'cancelled': $status_class = 'badge-danger'; break;
            case 'refunded': case 'voided': $status_class = 'badge-info'; break;
            default: $status_class = 'badge-secondary';
        }

        $rows[] = [
            'order_id' => $txn->order_id,
            'course_name' => $itemname ?: '-',
            'amount' => number_format((float) $txn->amount, 2) . ' ' . $txn->currency,
            'status' => $txn->status,
            'status_class' => $status_class,
            'payment_method' => $txn->payment_method_type ?? '-',
            'invoice_number' => $invoice->invoice_number ?? '-',
            'date' => userdate($txn->timecreated),
        ];
    }

    $templatedata = [
        'transactions' => $rows,
        'has_transactions' => true,
    ];
    echo $OUTPUT->render_from_template('local_payments/payment_history', $templatedata);
    echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url('/local/payments/history.php'));
}

echo $OUTPUT->footer();
