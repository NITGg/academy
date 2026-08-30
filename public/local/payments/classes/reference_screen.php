<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders the "pay with this code" screen for offline payment methods.
 *
 * Fawry/Meeza style methods hand the buyer a reference instead of taking the
 * money there and then, so there is nowhere to redirect to — this screen IS the
 * checkout result. It is rendered from checkout.php on the way out, and again
 * from callback.php whenever someone returns to a still-unpaid order.
 */
class reference_screen {

    /**
     * Does this transaction have a code the buyer still needs to pay?
     *
     * @param \stdClass $transaction Row from local_payments_transactions.
     * @return bool
     */
    public static function applies(\stdClass $transaction): bool {
        if ($transaction->status !== status_machine::PENDING) {
            return false;
        }
        return self::reference_of($transaction) !== '';
    }

    /**
     * @param \stdClass $transaction
     * @return string The stored reference code, or '' when there isn't one.
     */
    private static function reference_of(\stdClass $transaction): string {
        $meta = json_decode($transaction->metadata ?? '{}', true) ?: [];
        return trim((string) ($meta['payment_data']['reference'] ?? ''));
    }

    /**
     * Build the template context for a transaction.
     *
     * @param \stdClass $transaction
     * @return array
     */
    public static function export(\stdClass $transaction): array {
        global $DB;

        $meta = json_decode($transaction->metadata ?? '{}', true) ?: [];
        $paymentdata = $meta['payment_data'] ?? [];

        // What was bought — a course name, or the plan name we stored at checkout.
        $itemname = '';
        if (($meta['item_type'] ?? 'course') === 'course' && !empty($transaction->courseid)) {
            $itemname = (string) $DB->get_field('course', 'fullname', ['id' => $transaction->courseid]);
        }
        if ($itemname === '') {
            $itemname = (string) ($meta['subscription_name'] ?? $meta['course_name'] ?? '');
        }

        // Prefer the gateway's own expiry for the code; fall back to the order's.
        $expires = '';
        $stated = trim((string) ($paymentdata['reference_expires_at'] ?? ''));
        if ($stated !== '') {
            $ts = strtotime($stated);
            if ($ts !== false) {
                $expires = userdate($ts);
            }
        } else if (!empty($transaction->expires_at)) {
            $expires = userdate((int) $transaction->expires_at);
        }

        return [
            'item_name' => format_string($itemname),
            'amount_formatted' => format_float((float) $transaction->amount, 2) . ' ' . $transaction->currency,
            'reference' => (string) ($paymentdata['reference'] ?? ''),
            'method_name' => (string) ($paymentdata['method_name'] ?? ''),
            'expires_formatted' => $expires,
            'order_id' => $transaction->order_id,
            'status_url' => (new \moodle_url('/local/payments/callback.php',
                ['order_id' => $transaction->order_id]))->out(false),
            'history_url' => (new \moodle_url('/local/payments/history.php'))->out(false),
        ];
    }
}
