<?php
require_once(__DIR__ . '/../../config.php');

require_login();

// The URL is set once the filters are known, further down: it has to carry them,
// or the paging links and the login redirect drop back to an unfiltered list.
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('paymenthistory', 'local_payments'));
$PAGE->set_heading(get_string('paymenthistory', 'local_payments'));
$PAGE->set_pagelayout('standard');

$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;

// ── Filters ─────────────────────────────────────────────────────────────────
// A student who has bought a handful of courses does not need these; one who has
// been on the platform for two years and is claiming a year of them back from an
// employer does, and scrolling twenty at a time is not a way to find them.
$search = trim(optional_param('q', '', PARAM_TEXT));
$status = optional_param('status', '', PARAM_ALPHAEXT);
$fcourse = optional_param('courseid', 0, PARAM_INT);
// Dates come from <input type="date">, so they are always YYYY-MM-DD or empty.
$datefrom = optional_param('from', '', PARAM_RAW_TRIMMED);
$dateto = optional_param('to', '', PARAM_RAW_TRIMMED);

/**
 * A YYYY-MM-DD box turned into the timestamp that bounds the day it names.
 *
 * make_timestamp() rather than strtotime(): the boundary has to fall at midnight
 * in the reader's timezone, or a payment made in the evening drops out of a range
 * that plainly includes its date.
 */
$daybound = function (string $value, bool $endofday): int {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
        return 0;
    }
    return $endofday
        ? (int) make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], 23, 59, 59)
        : (int) make_timestamp((int) $m[1], (int) $m[2], (int) $m[3], 0, 0, 0);
};

$where = ['t.userid = :userid'];
$params = ['userid' => $USER->id];

if ($status !== '') {
    $where[] = 't.status = :status';
    $params['status'] = $status;
}

if ($fcourse) {
    $where[] = 't.courseid = :fcourseid';
    $params['fcourseid'] = $fcourse;
}

if (($fromts = $daybound($datefrom, false)) > 0) {
    $where[] = 't.timecreated >= :fromts';
    $params['fromts'] = $fromts;
}

if (($tots = $daybound($dateto, true)) > 0) {
    $where[] = 't.timecreated <= :tots';
    $params['tots'] = $tots;
}

if ($search !== '') {
    // The two references a student actually holds: what the site called the order
    // and what the invoice was numbered. The invoice number is on the PDF they
    // are looking at, so it has to be searchable even though it lives in its own
    // table — reached with EXISTS rather than a join, so that nothing can list a
    // payment twice or inflate the count.
    $params['searchorder'] = '%' . $DB->sql_like_escape($search) . '%';
    $params['searchinv'] = '%' . $DB->sql_like_escape($search) . '%';
    $where[] = '(' . $DB->sql_like('t.order_id', ':searchorder', false)
        . ' OR EXISTS (SELECT 1 FROM {local_payments_invoices} i
                        WHERE i.transaction_id = t.id
                          AND ' . $DB->sql_like('i.invoice_number', ':searchinv', false) . '))';
}

$from = '{local_payments_transactions} t';
$wheresql = implode(' AND ', $where);

$transactions = $DB->get_records_sql(
    "SELECT t.* FROM {$from} WHERE {$wheresql} ORDER BY t.timecreated DESC",
    $params, $page * $perpage, $perpage);

$total = $DB->count_records_sql("SELECT COUNT(1) FROM {$from} WHERE {$wheresql}", $params);

// The course list offers only what this account has actually paid for: a filter
// listing every course on the site would be mostly dead options.
$owncourses = $DB->get_records_sql(
    "SELECT DISTINCT c.id, c.fullname
       FROM {local_payments_transactions} t
       JOIN {course} c ON c.id = t.courseid
      WHERE t.userid = :userid
   ORDER BY c.fullname", ['userid' => $USER->id]);

// Everything the paging bar and the reset link have to carry with them.
$filterparams = array_filter([
    'q' => $search ?: null,
    'status' => $status ?: null,
    'courseid' => $fcourse ?: null,
    'from' => $fromts ? $datefrom : null,
    'to' => $tots ? $dateto : null,
]);
$hasfilters = !empty($filterparams);

$PAGE->set_url(new moodle_url('/local/payments/history.php', $filterparams));

echo $OUTPUT->header();

// The Invoices entry of the account screen points here, so this page draws itself
// inside that screen's navigation box rather than as a page of its own. Guarded:
// local_profilefields is a separate install, and the payment history has to keep
// working on a site without it.
$inshell = class_exists('\local_profilefields\account');
if ($inshell) {
    \local_profilefields\account::open('invoices');
    echo html_writer::start_div('nit-account__card');
    echo html_writer::tag('h2', get_string('paymenthistory', 'local_payments'),
        ['class' => 'nit-account__cardtitle']);
}

// ── The filter bar ──────────────────────────────────────────────────────────
// Drawn whenever there is anything to filter, so an account with a single
// payment is not asked to narrow it down.
if ($total > 0 || $hasfilters) {
    $statuses = ['' => get_string('allstatuses', 'local_payments')];
    foreach (\local_payments\status_machine::all_statuses() as $value) {
        $statuses[$value] = get_string('status_' . $value, 'local_payments');
    }

    $courseoptions = [0 => get_string('allcourses', 'local_payments')];
    foreach ($owncourses as $c) {
        $courseoptions[$c->id] = \local_payments\multilang::resolve(format_string($c->fullname));
    }

    echo html_writer::start_tag('form', [
        'method' => 'get',
        'action' => new moodle_url('/local/payments/history.php'),
        'class' => 'lp-txn-filters mb-3',
    ]);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'name' => 'q',
        'value' => $search,
        'class' => 'form-control',
        'placeholder' => get_string('searchmypayments', 'local_payments'),
    ]);
    echo html_writer::select($statuses, 'status', $status, false, ['class' => 'form-select']);
    if (count($courseoptions) > 1) {
        echo html_writer::select($courseoptions, 'courseid', $fcourse, false, ['class' => 'form-select']);
    }
    // Labelled, unlike the rest: two bare date boxes side by side do not say
    // which end of the range each one is.
    foreach ([['from', $datefrom, 'datefrom'], ['to', $dateto, 'dateto']] as [$name, $value, $key]) {
        echo html_writer::tag('label',
            get_string($key, 'local_payments') . ' '
            . html_writer::empty_tag('input', [
                'type' => 'date',
                'name' => $name,
                'value' => $value,
                'class' => 'form-control lp-txn-filters__date',
            ]),
            ['class' => 'lp-txn-filters__label']);
    }
    echo html_writer::tag('button', get_string('filter'),
        ['type' => 'submit', 'class' => 'btn btn-primary']);
    if ($hasfilters) {
        echo html_writer::link(new moodle_url('/local/payments/history.php'),
            get_string('reset'), ['class' => 'btn btn-outline-secondary']);
    }
    echo html_writer::end_tag('form');
}

if (empty($transactions)) {
    // Two different situations: nothing was ever paid, or nothing matches what
    // was asked for. Saying "you have no payments" to someone whose filter is
    // simply too narrow sends them looking for a fault that is not there.
    echo $OUTPUT->notification(
        get_string($hasfilters ? 'nopaymentsmatch' : 'nopayments', 'local_payments'), 'info');
} else {
    // Batch-load item names + invoices once, instead of a query per row (N+1).
    $txnids = [];
    foreach ($transactions as $txn) {
        $txnids[] = (int) $txn->id;
    }
    // What each payment bought. Not just the course: a subscription sale has no
    // course, and naming one by its courseid printed a dash where the buyer
    // expected to see the plan they had paid for.
    $itemnames = \local_payments\item_name::for_many($transactions);
    $invoices = $txnids
        ? $DB->get_records_list('local_payments_invoices', 'transaction_id', $txnids, '', 'transaction_id, invoice_number')
        : [];

    $rows = [];
    foreach ($transactions as $txn) {
        $itemname = $itemnames[(int) $txn->id] ?? '';
        $invoice = $invoices[$txn->id] ?? null;

        $status_class = '';
        switch ($txn->status) {
            case 'completed': $status_class = 'text-bg-success'; break;
            case 'pending': $status_class = 'text-bg-warning'; break;
            case 'failed': case 'cancelled': $status_class = 'text-bg-danger'; break;
            case 'refunded': case 'voided': $status_class = 'text-bg-info'; break;
            default: $status_class = 'text-bg-secondary';
        }

        // Refunds: offered only on a completed payment, and only when nothing is
        // already in flight for it.
        $refundpending = false;
        $refundable = false;
        $refundinstant = false;
        if (\local_payments\refund_policy::enabled()
                && $txn->status === \local_payments\status_machine::COMPLETED) {
            $refundpending = (bool) \local_payments\refund_manager::open_request((int) $txn->id);
            $refundable = !$refundpending;
            $refundinstant = \local_payments\refund_policy::quote($txn)->withinwindow;
        }

        // Only a payment that went through has an invoice worth downloading.
        $downloadable = in_array($txn->status, [
            \local_payments\status_machine::COMPLETED,
            \local_payments\status_machine::REFUNDED,
            \local_payments\status_machine::PARTIALLY_REFUNDED,
        ], true);

        $rows[] = [
            'order_id' => $txn->order_id,
            'item_name' => $itemname ?: '-',
            'amount' => number_format((float) $txn->amount, 2) . ' ' . $txn->currency,
            // The badge said "completed" in the middle of an Arabic page: the
            // status is a machine value and needs a translated label like
            // everything else on the row.
            'status' => get_string('status_' . $txn->status, 'local_payments'),
            'status_class' => $status_class,
            'payment_method' => $txn->payment_method_type ?? '-',
            'invoice_number' => $invoice->invoice_number ?? '-',
            'date' => userdate($txn->timecreated),
            'can_download' => $downloadable,
            // The button says what will happen: an instant refund inside the
            // window, a request outside it. Deciding here rather than on the
            // refund page keeps the promise on the button honest.
            'can_refund' => $refundable,
            'refund_url' => (new moodle_url('/local/payments/refund.php',
                ['transaction_id' => $txn->id]))->out(false),
            'refund_label' => $refundinstant
                ? get_string('refund_now_button', 'local_payments')
                : get_string('refund_ask_button', 'local_payments'),
            'refund_pending' => $refundpending,
            // Both languages are offered outright rather than following the
            // interface language: a student reads the site in Arabic but may
            // well need the English copy to claim it back from an employer.
            'invoice_url_en' => (new moodle_url('/local/payments/invoice.php',
                ['transaction_id' => $txn->id, 'lang' => 'en']))->out(false),
            'invoice_url_ar' => (new moodle_url('/local/payments/invoice.php',
                ['transaction_id' => $txn->id, 'lang' => 'ar']))->out(false),
        ];
    }

    $templatedata = [
        'transactions' => $rows,
        'has_transactions' => true,
    ];
    echo $OUTPUT->render_from_template('local_payments/payment_history', $templatedata);
    echo $OUTPUT->paging_bar($total, $page, $perpage,
        new moodle_url('/local/payments/history.php', $filterparams));
}

if ($inshell) {
    echo html_writer::end_div();
    \local_profilefields\account::close();
}

echo $OUTPUT->footer();
