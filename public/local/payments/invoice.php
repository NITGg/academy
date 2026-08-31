<?php
/**
 * Download a paid transaction's invoice as a PDF.
 *
 * Language follows the site's current language unless ?lang= says otherwise, so
 * the button can offer both without the page having to switch language first.
 */
require_once(__DIR__ . '/../../config.php');
// send_file() lives in filelib, which a plain page does not pull in.
require_once($CFG->libdir . '/filelib.php');

$transactionid = required_param('transaction_id', PARAM_INT);
$lang = optional_param('lang', '', PARAM_ALPHA);

require_login();

$context = context_system::instance();
$transaction = $DB->get_record('local_payments_transactions', ['id' => $transactionid], '*', MUST_EXIST);

// A buyer may download their own invoice; anyone else needs the staff capability.
// Without this, the sequential transaction id would let any logged-in user walk
// the whole table and read other people's names, emails and purchases.
if ((int) $transaction->userid !== (int) $USER->id) {
    require_capability('local/payments:viewalltransactions', $context);
}

// Only a payment that actually went through has an invoice to show.
if (!\local_payments\status_machine::is_successful($transaction->status)
        && !in_array($transaction->status, [
            \local_payments\status_machine::REFUNDED,
            \local_payments\status_machine::PARTIALLY_REFUNDED,
        ], true)) {
    throw new moodle_exception('invoice_notavailable', 'local_payments',
        new moodle_url('/local/payments/history.php'));
}

// Fall back to the current language, and only honour the two we render.
if (!in_array($lang, ['en', 'ar'], true)) {
    $lang = (current_language() === 'ar') ? 'ar' : 'en';
}

// An invoice should exist from fulfilment, but generate one now if a payment
// completed before invoicing existed — better than refusing the download.
if (!$DB->record_exists('local_payments_invoices', ['transaction_id' => $transaction->id])) {
    \local_payments\invoice_generator::create((int) $transaction->id);
}

// Switch the whole request, not just the strings: userdate() formats month names
// from the current language, so an Arabic invoice pulled while the interface is
// in English would otherwise carry English dates.
force_current_language($lang);

$pdf = \local_payments\invoice_document::render($transaction, $lang);
$filename = \local_payments\invoice_document::filename($transaction, $lang);

send_file($pdf, $filename, 0, 0, true, true, 'application/pdf');
