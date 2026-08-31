<?php
namespace local_payments\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_warnings;
use core_external\external_format_value;
use core_external\external_files;

global $CFG;

class get_invoice extends external_api {

    public static function execute_parameters(): external_function_parameters {
        // The order here is the argument order of execute(): the web-service
        // layer drops the keys and calls positionally, so anything new goes on
        // the end, in both places.
        return new external_function_parameters([
            'transaction_id' => new external_value(PARAM_INT, 'Transaction ID'),
            'lang' => new external_value(PARAM_LANG, 'Display language, e.g. en or ar (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
            'include_pdf' => new external_value(PARAM_BOOL,
                'Return the invoice PDF itself in pdf_base64 (default 1). Pass 0 for the details only.',
                VALUE_DEFAULT, 1),
        ]);
    }

    public static function execute(int $transaction_id, string $lang = '', string $alang = '',
            bool $include_pdf = true): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            ['transaction_id' => $transaction_id, 'lang' => $lang, 'alang' => $alang,
             'include_pdf' => $include_pdf]);

        $context = \context_system::instance();
        self::validate_context($context);
        $wslang = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($wslang !== '') {
            force_current_language($wslang);
        }

        $txn = $DB->get_record('local_payments_transactions', ['id' => $params['transaction_id']], '*', MUST_EXIST);

        // Users can only see their own invoices unless they have viewalltransactions.
        if ($txn->userid != $USER->id) {
            require_capability('local/payments:viewalltransactions', $context);
        }

        // The document renders in one of two languages; anything else falls back
        // the same way the browser download does, so both routes agree.
        $doclang = in_array($wslang, ['en', 'ar'], true)
            ? $wslang
            : ((current_language() === 'ar') ? 'ar' : 'en');

        // Only a payment that went through has an invoice to issue.
        $printable = \local_payments\status_machine::is_successful($txn->status)
            || in_array($txn->status, [
                \local_payments\status_machine::REFUNDED,
                \local_payments\status_machine::PARTIALLY_REFUNDED,
            ], true);

        // Generated on demand for a payment that completed before invoicing
        // existed — the same catch-up the browser download does.
        if ($printable && !$DB->record_exists('local_payments_invoices', ['transaction_id' => $txn->id])) {
            \local_payments\invoice_generator::create((int) $txn->id);
        }

        $invoice = $DB->get_record('local_payments_invoices', ['transaction_id' => $params['transaction_id']]);
        $course = $DB->get_record('course', ['id' => $txn->courseid], 'fullname');

        // The app has a token, not a session, so it cannot fetch invoice.php.
        // Ship the bytes with the details instead: an invoice is a page of text,
        // and one round trip beats a second authenticated request.
        $pdfbase64 = '';
        $filename = '';
        if ($printable) {
            // Switch the whole request, not just the strings: userdate() takes its
            // month names from the current language, so an Arabic invoice fetched
            // by an English session would otherwise carry English dates.
            force_current_language($doclang);
            $filename = \local_payments\invoice_document::filename($txn, $doclang);
            if (!empty($params['include_pdf'])) {
                $pdfbase64 = base64_encode(\local_payments\invoice_document::render($txn, $doclang));
            }
        }

        return [
            'invoice_number' => $invoice ? $invoice->invoice_number : '',
            'amount' => (float) $txn->amount,
            'original_amount' => (float) ($txn->original_amount ?? $txn->amount),
            'currency' => $txn->currency,
            'status' => $invoice ? $invoice->status : '',
            'order_id' => $txn->order_id,
            'course_name' => \local_payments\multilang::resolve($course->fullname ?? '', $doclang),
            'payment_date' => (int) $txn->timecreated,
            'invoice_date' => $invoice ? (int) $invoice->timecreated : 0,
            'lang' => $doclang,
            'pdf_available' => $printable,
            'filename' => $filename,
            'pdf_base64' => $pdfbase64,
            'pdf_url' => $printable
                ? (new \moodle_url('/local/payments/invoice.php',
                    ['transaction_id' => $txn->id, 'lang' => $doclang]))->out(false)
                : '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'invoice_number' => new external_value(PARAM_TEXT, 'Invoice number'),
            'amount' => new external_value(PARAM_FLOAT, 'Amount'),
            'original_amount' => new external_value(PARAM_FLOAT, 'Original amount'),
            'currency' => new external_value(PARAM_TEXT, 'Currency'),
            'status' => new external_value(PARAM_TEXT, 'Invoice status'),
            'order_id' => new external_value(PARAM_TEXT, 'Order ID'),
            'course_name' => new external_value(PARAM_TEXT, 'Course name'),
            'payment_date' => new external_value(PARAM_INT, 'Payment timestamp'),
            'invoice_date' => new external_value(PARAM_INT, 'Invoice timestamp'),
            'lang' => new external_value(PARAM_ALPHA, 'Language the invoice was rendered in (en or ar)'),
            'pdf_available' => new external_value(PARAM_BOOL,
                'Whether this payment has an invoice to print at all'),
            'filename' => new external_value(PARAM_FILE, 'Suggested filename to save the PDF as'),
            'pdf_base64' => new external_value(PARAM_RAW,
                'The PDF itself, base64 encoded. Empty when include_pdf=0 or there is no invoice.'),
            'pdf_url' => new external_value(PARAM_RAW,
                'Browser download URL for the same PDF — needs a session, not a token'),
        ]);
    }
}
