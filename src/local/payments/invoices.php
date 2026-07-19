<?php
/**
 * Student-facing invoices: a list of the current user's invoices across course,
 * package and subscription purchases, an on-screen detail view, and a PDF download.
 */
require_once(__DIR__ . '/../../config.php');

use local_payments\invoice_manager;
use local_payments\invoice_generator;

require_login();

$id       = optional_param('id', 0, PARAM_INT);        // Invoice id (detail / PDF).
$download = optional_param('download', '', PARAM_ALPHA); // 'pdf' to download.
$page     = optional_param('page', 0, PARAM_INT);
$perpage  = 20;

// Filters for the list view.
$filters = [
    'invoicenumber' => optional_param('invoicenumber', '', PARAM_RAW_TRIMMED),
    'item'          => optional_param('item', '', PARAM_RAW_TRIMMED),
    'type'          => optional_param('type', '', PARAM_ALPHA),
    'amountmin'     => optional_param('amountmin', '', PARAM_RAW_TRIMMED),
    'amountmax'     => optional_param('amountmax', '', PARAM_RAW_TRIMMED),
    'datefrom'      => optional_param('datefrom', '', PARAM_RAW_TRIMMED),
    'dateto'        => optional_param('dateto', '', PARAM_RAW_TRIMMED),
];
$hasfilters = (bool) array_filter($filters, fn($v) => $v !== '');

$context = context_system::instance();
$baseurl = new moodle_url('/local/payments/invoices.php');
// Preserve active filters across pagination / detail links.
$listurl = new moodle_url($baseurl, array_filter($filters, fn($v) => $v !== ''));

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('myinvoices', 'local_payments'));
$PAGE->set_heading(get_string('myinvoices', 'local_payments'));

$canviewall = has_capability('local/payments:viewalltransactions', $context);

// ── Single invoice: PDF download or detail view ─────────────────────────────
if ($id) {
    $invoice = invoice_manager::get_invoice($id, (int) $USER->id, $canviewall);
    if (!$invoice) {
        throw new moodle_exception('invalidaccess', 'error', $baseurl);
    }

    $owner = ($invoice->userid == $USER->id) ? $USER : core_user::get_user($invoice->userid, '*', MUST_EXIST);

    if ($download === 'pdf') {
        invoice_manager::stream_pdf($invoice, $owner);
        // stream_pdf() exits.
    }

    $PAGE->navbar->add(get_string('myinvoices', 'local_payments'), $listurl);
    $PAGE->navbar->add($invoice->invoice_number);

    $data = invoice_manager::detail_context($invoice, $owner);
    $data['pdf_url'] = (new moodle_url($baseurl, ['id' => $invoice->id, 'download' => 'pdf']))->out(false);
    $data['back_url'] = $listurl->out(false);

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_payments/invoice_detail', $data);
    echo $OUTPUT->footer();
    exit;
}

// ── List ────────────────────────────────────────────────────────────────────
$result = invoice_manager::get_user_invoices((int) $USER->id, $page, $perpage, $filters);

// Attach detail/PDF urls to each row (carrying the active filters along).
foreach ($result['rows'] as &$row) {
    $row['view_url'] = (new moodle_url($listurl, ['id' => $row['id']]))->out(false);
    $row['pdf_url'] = (new moodle_url($listurl, ['id' => $row['id'], 'download' => 'pdf']))->out(false);
}
unset($row);

// Filter form context: option lists with a precomputed 'selected' flag per option
// (mustache has no equality helper, so this is resolved here).
$typeoptions = [];
foreach ([
    '' => get_string('all'),
    invoice_generator::SOURCE_COURSE => get_string('invoice_item_course', 'local_payments'),
    invoice_generator::SOURCE_PACKAGE => get_string('invoice_item_package', 'local_payments'),
    invoice_generator::SOURCE_SUBSCRIPTION => get_string('invoice_item_subscription', 'local_payments'),
] as $value => $label) {
    $typeoptions[] = ['value' => $value, 'label' => $label, 'selected' => ($filters['type'] === $value)];
}

$itemoptions = [['value' => '', 'label' => get_string('all'), 'selected' => ($filters['item'] === '')]];
foreach (invoice_manager::get_user_items((int) $USER->id) as $itemname) {
    $itemoptions[] = ['value' => $itemname, 'label' => $itemname, 'selected' => ($filters['item'] === $itemname)];
}

$filtercontext = [
    'action'          => $baseurl->out(false),
    'invoicenumber'   => $filters['invoicenumber'],
    'amountmin'       => $filters['amountmin'],
    'amountmax'       => $filters['amountmax'],
    'datefrom'        => $filters['datefrom'],
    'dateto'          => $filters['dateto'],
    'type_options'    => $typeoptions,
    'item_options'    => $itemoptions,
    'has_filters'     => $hasfilters,
    'clear_url'       => $baseurl->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('myinvoices', 'local_payments'));

echo $OUTPUT->render_from_template('local_payments/invoices_filters', $filtercontext);

if (empty($result['rows'])) {
    echo $OUTPUT->notification(get_string(
        $hasfilters ? 'noinvoices_filtered' : 'noinvoices', 'local_payments'), 'info');
} else {
    echo $OUTPUT->render_from_template('local_payments/invoices_list', [
        'invoices'     => array_values($result['rows']),
        'has_invoices' => true,
    ]);
    echo $OUTPUT->paging_bar($result['total'], $page, $perpage, $listurl);
}

echo $OUTPUT->footer();
