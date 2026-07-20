<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * Issues invoices for completed purchases of any type: a course (paid via the
 * gateway), a package, a subscription, or a program. One invoice per purchase,
 * identified by (source_type, source_id):
 *   - course        → source_id = local_payments_transactions.id
 *   - package       → source_id = academy_package_purchases.id
 *   - subscription  → source_id = academy_sub_purchases.id
 *   - program       → source_id = local_payments_transactions.id (enrol_programs has no
 *                      purchase table of its own — the transaction row is the record)
 *
 * The generator is idempotent, so it is safe to call from both the gateway
 * fulfilment path and the direct (no-gateway) purchase managers.
 */
class invoice_generator {

    const SOURCE_COURSE = 'course';
    const SOURCE_PACKAGE = 'package';
    const SOURCE_SUBSCRIPTION = 'subscription';
    const SOURCE_PROGRAM = 'program';

    /**
     * Create (or return the existing) invoice for a purchase.
     *
     * @param string $source_type course|package|subscription|program
     * @param int $source_id transaction / purchase id per source_type
     * @return int|null Invoice id, or null when the purchase could not be resolved.
     */
    public static function create(string $source_type, int $source_id): ?int {
        global $DB;

        // Idempotency: one invoice per (source_type, source_id).
        $existing = $DB->get_record('local_payments_invoices',
            ['source_type' => $source_type, 'source_id' => $source_id]);
        if ($existing) {
            return (int) $existing->id;
        }

        switch ($source_type) {
            case self::SOURCE_COURSE:
                $data = self::resolve_course($source_id);
                break;
            case self::SOURCE_PACKAGE:
                $data = self::resolve_package($source_id);
                break;
            case self::SOURCE_SUBSCRIPTION:
                $data = self::resolve_subscription($source_id);
                break;
            case self::SOURCE_PROGRAM:
                $data = self::resolve_program($source_id);
                break;
            default:
                return null;
        }

        if ($data === null) {
            return null;
        }

        $invoice = (object) [
            'source_type'    => $source_type,
            'source_id'      => $source_id,
            'transaction_id' => $data->transaction_id,
            'userid'         => $data->userid,
            'invoice_number' => self::generate_number(),
            'item_name'      => $data->item_name,
            'subtotal'       => $data->subtotal,
            'discount'       => $data->discount,
            'amount'         => $data->amount,
            'currency'       => $data->currency,
            'status'         => 'issued',
            'pdf_path'       => null,
            'timecreated'    => time(),
        ];

        return (int) $DB->insert_record('local_payments_invoices', $invoice);
    }

    /**
     * Backwards-compatible helper: issue a course invoice from a transaction id.
     */
    public static function create_for_transaction(int $transaction_id): ?int {
        return self::create(self::SOURCE_COURSE, $transaction_id);
    }

    // ── resolvers ─────────────────────────────────────────────────────────────

    /** Resolve invoice data for a course transaction. */
    private static function resolve_course(int $transaction_id): ?object {
        global $DB;

        $txn = $DB->get_record('local_payments_transactions', ['id' => $transaction_id]);
        if (!$txn) {
            return null;
        }

        $coursename = $DB->get_field('course', 'fullname', ['id' => $txn->courseid]);
        $subtotal = ($txn->original_amount !== null) ? (float) $txn->original_amount : (float) $txn->amount;
        $discount = max(0, round($subtotal - (float) $txn->amount, 2));

        return (object) [
            'transaction_id' => (int) $txn->id,
            'userid'         => (int) $txn->userid,
            'item_name'      => $coursename ?: get_string('invoice_item_course', 'local_payments'),
            'subtotal'       => $subtotal,
            'discount'       => $discount ?: null,
            'amount'         => (float) $txn->amount,
            'currency'       => $txn->currency,
        ];
    }

    /**
     * Resolve invoice data for a program purchase.
     *
     * Unlike package/subscription, enrol_programs keeps no purchase record of its own — the
     * gateway transaction IS the record, exactly like a course purchase.
     */
    private static function resolve_program(int $transaction_id): ?object {
        global $DB;

        $txn = $DB->get_record('local_payments_transactions', ['id' => $transaction_id]);
        if (!$txn) {
            return null;
        }

        $meta = json_decode($txn->metadata ?? '{}');
        $programid = (int) ($meta->item_id ?? 0);
        // Prefer the live program name; fall back to the name snapshotted at checkout time so the
        // invoice still reads correctly even if the program was later renamed or archived.
        $name = $programid ? $DB->get_field('enrol_programs_programs', 'fullname', ['id' => $programid]) : null;
        if (!$name) {
            $name = $meta->program_name ?? null;
        }

        $subtotal = ($txn->original_amount !== null) ? (float) $txn->original_amount : (float) $txn->amount;
        $discount = max(0, round($subtotal - (float) $txn->amount, 2));

        return (object) [
            'transaction_id' => (int) $txn->id,
            'userid'         => (int) $txn->userid,
            'item_name'      => $name ? format_string($name) : get_string('invoice_item_program', 'local_payments'),
            'subtotal'       => $subtotal,
            'discount'       => $discount ?: null,
            'amount'         => (float) $txn->amount,
            'currency'       => $txn->currency,
        ];
    }

    /** Resolve invoice data for a package purchase. */
    private static function resolve_package(int $purchase_id): ?object {
        global $DB;

        $purchase = $DB->get_record('academy_package_purchases', ['id' => $purchase_id]);
        if (!$purchase) {
            return null;
        }

        $name = $DB->get_field('academy_packages', 'name', ['id' => $purchase->packageid]);
        $payment = self::first_success_payment('academy_payments', $purchase_id);
        $txn = self::linked_transaction($payment);

        $subtotal = (float) $purchase->price_paid;
        $amount = self::effective_amount($txn, $payment, $subtotal);
        $discount = max(0, round($subtotal - $amount, 2));

        return (object) [
            'transaction_id' => $txn ? (int) $txn->id : null,
            'userid'         => (int) $purchase->userid,
            'item_name'      => $name ? format_string($name) : get_string('invoice_item_package', 'local_payments'),
            'subtotal'       => $subtotal,
            'discount'       => $discount ?: null,
            'amount'         => $amount,
            'currency'       => self::currency_for($txn),
        ];
    }

    /** Resolve invoice data for a subscription purchase. */
    private static function resolve_subscription(int $purchase_id): ?object {
        global $DB;

        $purchase = $DB->get_record('academy_sub_purchases', ['id' => $purchase_id]);
        if (!$purchase) {
            return null;
        }

        $name = $DB->get_field('academy_subscriptions', 'name', ['id' => $purchase->subscriptionid]);
        $payment = self::first_success_payment('academy_sub_payments', $purchase_id);
        $txn = self::linked_transaction($payment);

        // Subscriptions snapshot base_price + discount_percent + price_paid on the purchase.
        $subtotal = isset($purchase->base_price) ? (float) $purchase->base_price : (float) $purchase->price_paid;
        $amount = self::effective_amount($txn, $payment, (float) $purchase->price_paid);
        $discount = max(0, round($subtotal - $amount, 2));

        return (object) [
            'transaction_id' => $txn ? (int) $txn->id : null,
            'userid'         => (int) $purchase->userid,
            'item_name'      => $name ? format_string($name) : get_string('invoice_item_subscription', 'local_payments'),
            'subtotal'       => $subtotal,
            'discount'       => $discount ?: null,
            'amount'         => $amount,
            'currency'       => self::currency_for($txn),
        ];
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /** The first successful, non-refund payment for a purchase (opening charge). */
    private static function first_success_payment(string $table, int $purchase_id): ?object {
        global $DB;
        $rows = $DB->get_records_select($table,
            'purchaseid = :pid AND status = :st AND amount > 0',
            ['pid' => $purchase_id, 'st' => 'success'],
            'timecreated ASC, id ASC', '*', 0, 1);
        return $rows ? reset($rows) : null;
    }

    /**
     * The gateway transaction linked to an academy payment, if the payment was made
     * through the gateway (its reference holds the transaction order_id).
     */
    private static function linked_transaction(?object $payment): ?object {
        global $DB;
        if (!$payment || empty($payment->reference)) {
            return null;
        }
        $txn = $DB->get_record('local_payments_transactions', ['order_id' => $payment->reference]);
        return $txn ?: null;
    }

    /** Prefer the real charged gateway amount, then the academy payment, then the snapshot price. */
    private static function effective_amount(?object $txn, ?object $payment, float $fallback): float {
        if ($txn) {
            return (float) $txn->amount;
        }
        if ($payment) {
            return (float) $payment->amount;
        }
        return $fallback;
    }

    /** Currency from the linked gateway transaction, else the configured default. */
    private static function currency_for(?object $txn): string {
        if ($txn && !empty($txn->currency)) {
            return $txn->currency;
        }
        $default = get_config('local_payments', 'default_currency');
        return $default ?: 'USD';
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
