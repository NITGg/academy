<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

class invoice_generator {

    /**
     * Generate an invoice for a completed transaction.
     *
     * @param int $transaction_id
     * @return int Invoice ID.
     */
    public static function create(int $transaction_id): int {
        global $DB;

        $txn = $DB->get_record('local_payments_transactions', ['id' => $transaction_id], '*', MUST_EXIST);

        // Check for existing invoice.
        $existing = $DB->get_record('local_payments_invoices', ['transaction_id' => $transaction_id]);
        if ($existing) {
            return (int) $existing->id;
        }

        $invoice_number = self::generate_number();

        $invoice = (object) [
            'transaction_id' => $transaction_id,
            'userid' => $txn->userid,
            'invoice_number' => $invoice_number,
            'amount' => $txn->amount,
            'currency' => $txn->currency,
            'status' => 'issued',
            'pdf_path' => null,
            'timecreated' => time(),
        ];

        return (int) $DB->insert_record('local_payments_invoices', $invoice);
    }

    /**
     * Generate a sequential invoice number: INV-YYYY-NNNNNNN
     */
    private static function generate_number(): string {
        global $DB;

        $year = date('Y');
        $prefix = "INV-{$year}-";

        $last = $DB->get_record_sql(
            "SELECT invoice_number FROM {local_payments_invoices}
             WHERE invoice_number LIKE :prefix
             ORDER BY id DESC LIMIT 1",
            ['prefix' => $prefix . '%']
        );

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->invoice_number);
            $seq = ((int) end($parts)) + 1;
        }

        return $prefix . str_pad($seq, 7, '0', STR_PAD_LEFT);
    }
}
