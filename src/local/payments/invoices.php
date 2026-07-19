<?php
/**
 * Student-facing invoices: a list of the current user's invoices across course,
 * package and subscription purchases, an on-screen detail view, and a PDF download.
 */
require_once(__DIR__ . '/../../config.php');

use local_payments\invoice_manager;

require_login();

$id       = optional_param('id', 0, PARAM_INT);        // Invoice id (detail / PDF).
$download = optional_param('download', '', PARAM_ALPHA); // 'pdf' to download.
$page     = optional_param('page', 0, PARAM_INT);
$perpage  = 20;

$context = context_system::instance();
$baseurl = new moodle_url('/local/payments/invoices.php');

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

    $PAGE->navbar->add(get_string('myinvoices', 'local_payments'), $baseurl);
    $PAGE->navbar->add($invoice->invoice_number);

    $data = invoice_manager::detail_context($invoice, $owner);
    $data['pdf_url'] = (new moodle_url($baseurl, ['id' => $invoice->id, 'download' => 'pdf']))->out(false);
    $data['back_url'] = $baseurl->out(false);

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_payments/invoice_detail', $data);
    echo $OUTPUT->footer();
    exit;
}

// ── List ────────────────────────────────────────────────────────────────────
$result = invoice_manager::get_user_invoices((int) $USER->id, $page, $perpage);

// Attach detail/PDF urls to each row.
foreach ($result['rows'] as &$row) {
    $row['view_url'] = (new moodle_url($baseurl, ['id' => $row['id']]))->out(false);
    $row['pdf_url'] = (new moodle_url($baseurl, ['id' => $row['id'], 'download' => 'pdf']))->out(false);
}
unset($row);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('myinvoices', 'local_payments'));

if (empty($result['rows'])) {
    echo $OUTPUT->notification(get_string('noinvoices', 'local_payments'), 'info');
} else {
    echo $OUTPUT->render_from_template('local_payments/invoices_list', [
        'invoices'     => array_values($result['rows']),
        'has_invoices' => true,
    ]);
    echo $OUTPUT->paging_bar($result['total'], $page, $perpage, $baseurl);
}

echo $OUTPUT->footer();
