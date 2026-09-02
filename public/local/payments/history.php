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
$perpage = \local_payments\history_api::PERPAGE;

// ── Filters ─────────────────────────────────────────────────────────────────
// A student who has bought a handful of courses does not need these; one who has
// been on the platform for two years and is claiming a year of them back from an
// employer does, and scrolling twenty at a time is not a way to find them.
//
// The filter vocabulary, the query behind it and what each row is allowed to do
// all live in \local_payments\history_api, because the app draws this same list
// through local_payments_get_transactions and the two must not be able to
// disagree about what is on it. This page keeps its HTML and nothing else.
$search = trim(optional_param('q', '', PARAM_TEXT));
$status = optional_param('status', '', PARAM_ALPHAEXT);
$fcourse = optional_param('courseid', 0, PARAM_INT);
// Dates come from <input type="date">, so they are always YYYY-MM-DD or empty.
$datefrom = optional_param('from', '', PARAM_RAW_TRIMMED);
$dateto = optional_param('to', '', PARAM_RAW_TRIMMED);

$filters = \local_payments\history_api::filters([
    'q' => $search,
    'status' => $status,
    'courseid' => $fcourse,
    'datefrom' => $datefrom,
    'dateto' => $dateto,
]);

// Read back out of the normalised set, so the paging links carry only the
// filters that were actually applied - a status this site does not use, or a
// half-typed date, is dropped by filters() and must not reappear in the URL.
$status = $filters['status'];
$fromts = $filters['from'];
$tots = $filters['to'];

$transactions = \local_payments\history_api::fetch($USER->id, $filters, $page, $perpage);
$total = \local_payments\history_api::count($USER->id, $filters);

// The course list offers only what this account has actually paid for: a filter
// listing every course on the site would be mostly dead options.
$owncourses = \local_payments\history_api::courses($USER->id);

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
    $statuses = ['' => get_string('allstatuses', 'local_payments')]
        + \local_payments\history_api::statuses();

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
    // What each payment bought, whether it has an invoice, and what its refund
    // button should say - all decided by history_api, batch-loaded rather than a
    // query per row (N+1), and identical to what the app is told.
    $rows = [];
    foreach (\local_payments\history_api::rows($transactions) as $row) {
        $status_class = '';
        switch ($row['status']) {
            case 'completed': $status_class = 'text-bg-success'; break;
            case 'pending': $status_class = 'text-bg-warning'; break;
            case 'failed': case 'cancelled': $status_class = 'text-bg-danger'; break;
            case 'refunded': case 'voided': $status_class = 'text-bg-info'; break;
            default: $status_class = 'text-bg-secondary';
        }

        $rows[] = [
            'order_id' => $row['order_id'],
            'item_name' => $row['item_name'] !== '' ? $row['item_name'] : '-',
            'amount' => number_format($row['amount'], 2) . ' ' . $row['currency'],
            // The badge said "completed" in the middle of an Arabic page: the
            // status is a machine value and needs a translated label like
            // everything else on the row.
            'status' => $row['status_label'],
            'status_class' => $status_class,
            'payment_method' => $row['payment_method'] !== '' ? $row['payment_method'] : '-',
            'invoice_number' => $row['invoice_number'] !== '' ? $row['invoice_number'] : '-',
            'date' => userdate($row['timecreated']),
            'can_download' => $row['can_download_invoice'],
            // The button says what will happen: an instant refund inside the
            // window, a request outside it. Deciding this before it is drawn
            // keeps the promise on the button honest.
            'can_refund' => $row['can_refund'],
            'refund_url' => (new moodle_url('/local/payments/refund.php',
                ['transaction_id' => $row['transaction_id']]))->out(false),
            'refund_label' => $row['refund_label'] !== ''
                ? $row['refund_label']
                : get_string('refund_ask_button', 'local_payments'),
            'refund_pending' => $row['refund_pending'],
            // Both languages are offered outright rather than following the
            // interface language: a student reads the site in Arabic but may
            // well need the English copy to claim it back from an employer.
            'invoice_url_en' => (new moodle_url('/local/payments/invoice.php',
                ['transaction_id' => $row['transaction_id'], 'lang' => 'en']))->out(false),
            'invoice_url_ar' => (new moodle_url('/local/payments/invoice.php',
                ['transaction_id' => $row['transaction_id'], 'lang' => 'ar']))->out(false),
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
