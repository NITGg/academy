<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * Giving money back.
 *
 * Two routes, decided by {@see refund_policy}:
 *
 *  - Inside the window, the buyer refunds themselves. No queue, no waiting on a
 *    human, and the fee is taken off automatically.
 *  - Outside it — or when the window is zero, which is how a course is marked
 *    "no automatic refunds" — the buyer asks and staff decide. Nothing is
 *    refused outright, because "you cannot even ask" is not a policy anyone
 *    wants to defend to a student.
 *
 * Both routes end in the same place: one gateway call, one row in
 * local_payments_refunds, the transaction marked refunded, and access removed.
 * Only the authorisation differs.
 */
class refund_manager {

    /** @var string A pending request, waiting on staff. */
    const REQ_PENDING = 'pending';
    /** @var string Staff agreed; the money went back. */
    const REQ_APPROVED = 'approved';
    /** @var string Staff said no. */
    const REQ_REJECTED = 'rejected';
    /** @var string The buyer withdrew it. */
    const REQ_CANCELLED = 'cancelled';

    /**
     * Why this transaction cannot be refunded, or '' if it can.
     *
     * Checks the order rather than the person; the caller checks the person.
     *
     * @param \stdClass $transaction
     * @return string A language string key, or ''.
     */
    public static function blocker(\stdClass $transaction): string {
        if (!refund_policy::enabled()) {
            return 'refund_err_disabled';
        }

        if ($transaction->status !== status_machine::COMPLETED) {
            // Already refunded, or never paid.
            return 'refund_err_notrefundable';
        }

        if (self::open_request($transaction->id)) {
            return 'refund_err_alreadyasked';
        }

        return '';
    }

    /**
     * The buyer's outstanding request for this transaction, if any.
     *
     * @param int $transactionid
     * @return \stdClass|false
     */
    public static function open_request(int $transactionid) {
        global $DB;
        return $DB->get_record('local_payments_refund_reqs',
            ['transaction_id' => $transactionid, 'status' => self::REQ_PENDING]);
    }

    /**
     * Refund inside the window: the buyer's own decision, applied immediately.
     *
     * @param \stdClass $transaction
     * @param string $reason Free text from the buyer.
     * @return object {success:bool, message:string}
     */
    public static function self_refund(\stdClass $transaction, string $reason = ''): object {
        $quote = refund_policy::quote($transaction);

        if (!$quote->withinwindow) {
            // Belt and braces: the caller should have sent this down the request
            // route. Refusing here means a stale page cannot skip the queue.
            return (object) [
                'success' => false,
                'message' => get_string('refund_err_windowclosed', 'local_payments'),
            ];
        }

        return self::execute($transaction, $quote, $reason, 'self');
    }

    /**
     * A refund a member of staff decided on directly, from the payments list.
     *
     * The window does not apply: it exists to bound what a buyer may do without
     * asking, and staff are the people who would have been asked. The fee is
     * optional for the same reason — most staff-initiated refunds are fixing
     * something, and charging a cancellation fee for our own mistake is not a
     * position anyone wants to be in.
     *
     * The caller is responsible for the capability check.
     *
     * @param \stdClass $transaction
     * @param string $reason
     * @param bool $applyfee Charge the policy fee anyway.
     * @return object {success:bool, message:string}
     */
    public static function staff_refund(\stdClass $transaction, string $reason,
            bool $applyfee = false): object {
        $quote = refund_policy::quote($transaction);

        if (!$applyfee) {
            $quote->fee = 0.0;
            $quote->net = $quote->paid;
        }

        return self::execute($transaction, $quote, $reason, 'staff');
    }

    /**
     * Ask for a refund the policy does not grant automatically.
     *
     * The quote is stored on the request. The policy may change between asking
     * and deciding, and the buyer is entitled to the terms they were shown.
     *
     * @param \stdClass $transaction
     * @param string $reason
     * @return int The request id.
     */
    public static function request(\stdClass $transaction, string $reason): int {
        global $DB, $USER;

        $quote = refund_policy::quote($transaction);

        $id = $DB->insert_record('local_payments_refund_reqs', (object) [
            'transaction_id' => $transaction->id,
            'userid' => $transaction->userid,
            'status' => self::REQ_PENDING,
            'reason' => $reason,
            'quoted_amount' => $quote->paid,
            'quoted_fee' => $quote->fee,
            'currency' => $quote->currency,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        manager::audit_log($transaction->id, $USER->id, 'refund_requested', '', (string) $id);

        return (int) $id;
    }

    /**
     * Staff agree: refund on the terms quoted when the buyer asked.
     *
     * @param \stdClass $request Row from local_payments_refund_reqs.
     * @param string $note Shown to the buyer.
     * @return object {success:bool, message:string}
     */
    public static function approve(\stdClass $request, string $note = ''): object {
        global $DB, $USER;

        if ($request->status !== self::REQ_PENDING) {
            return (object) [
                'success' => false,
                'message' => get_string('refund_err_decided', 'local_payments'),
            ];
        }

        $transaction = $DB->get_record('local_payments_transactions',
            ['id' => $request->transaction_id], '*', MUST_EXIST);

        // Honour what the buyer was quoted, not what today's settings say.
        $quote = refund_policy::quote($transaction);
        if ($request->quoted_amount !== null) {
            $quote->paid = (float) $request->quoted_amount;
            $quote->fee = (float) ($request->quoted_fee ?? 0);
            $quote->net = round(max(0, $quote->paid - $quote->fee), 2);
        }

        $result = self::execute($transaction, $quote, (string) $request->reason, 'approved');

        $DB->update_record('local_payments_refund_reqs', (object) [
            'id' => $request->id,
            'status' => $result->success ? self::REQ_APPROVED : self::REQ_PENDING,
            'decided_by' => $USER->id,
            'decision_note' => $note,
            'refund_id' => $result->refundid ?? null,
            'timemodified' => time(),
        ]);

        if ($result->success) {
            self::notify_decision($request, $transaction, true, $note);
        }

        return $result;
    }

    /**
     * Staff decline. Nothing moves except the request.
     *
     * @param \stdClass $request
     * @param string $note Shown to the buyer, so it should say why.
     * @return object {success:bool, message:string}
     */
    public static function reject(\stdClass $request, string $note = ''): object {
        global $DB, $USER;

        if ($request->status !== self::REQ_PENDING) {
            return (object) [
                'success' => false,
                'message' => get_string('refund_err_decided', 'local_payments'),
            ];
        }

        $DB->update_record('local_payments_refund_reqs', (object) [
            'id' => $request->id,
            'status' => self::REQ_REJECTED,
            'decided_by' => $USER->id,
            'decision_note' => $note,
            'timemodified' => time(),
        ]);

        manager::audit_log((int) $request->transaction_id, $USER->id, 'refund_rejected', '', $note);

        $transaction = $DB->get_record('local_payments_transactions',
            ['id' => $request->transaction_id]);
        if ($transaction) {
            self::notify_decision($request, $transaction, false, $note);
        }

        return (object) ['success' => true, 'message' => get_string('refund_rejected', 'local_payments')];
    }

    /**
     * The part both routes share: call the gateway, record it, revoke access.
     *
     * @param \stdClass $transaction
     * @param object $quote from refund_policy::quote()
     * @param string $reason
     * @param string $route 'self' or 'approved', for the audit trail.
     * @return object {success:bool, message:string, refundid:?int}
     */
    private static function execute(\stdClass $transaction, object $quote, string $reason,
            string $route): object {
        global $DB, $USER;

        $provider = manager::get_provider_by_id((int) $transaction->provider_id);

        if (!$provider->supports_refund()) {
            return (object) [
                'success' => false,
                'message' => get_string('refund_err_gateway', 'local_payments'),
                'refundid' => null,
            ];
        }

        // The gateway needs its own id for the payment, not ours.
        $providerref = (string) ($transaction->provider_order_id ?: $transaction->provider_txn_id);
        if ($providerref === '') {
            return (object) [
                'success' => false,
                'message' => get_string('refund_err_noreference', 'local_payments'),
                'refundid' => null,
            ];
        }

        // Recorded before the call so a gateway timeout still leaves a trace of
        // what was attempted; the outcome is written back below.
        $refundid = $DB->insert_record('local_payments_refunds', (object) [
            'transaction_id' => $transaction->id,
            'operation_type' => 'refund',
            'amount' => $quote->net,
            'currency' => $quote->currency,
            'reason' => $reason,
            'status' => 'pending',
            'provider_order_id' => $providerref,
            'initiated_by' => $USER->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $result = $provider->refund($providerref, $quote->net, $quote->currency, $reason);

        $DB->update_record('local_payments_refunds', (object) [
            'id' => $refundid,
            'status' => $result->success ? 'completed' : 'failed',
            'provider_refund_id' => $result->provider_refund_id,
            'gateway_code' => $result->gateway_code ?: ($result->status ?: ''),
            'timemodified' => time(),
        ]);

        if (!$result->success) {
            manager::log_entry((int) $transaction->provider_id, (int) $transaction->id, 'error',
                'Refund failed: ' . $result->error_message);
            return (object) [
                'success' => false,
                'message' => $result->error_message ?: get_string('refund_err_gatewayfailed', 'local_payments'),
                'refundid' => (int) $refundid,
            ];
        }

        // Refunded, fee or no fee. "Partially refunded" means part of the
        // purchase still stands — one seat of three given back, say — and reads
        // to anyone scanning a list as though the student still has something.
        // They do not: access is removed either way. The fee is a deduction from
        // the refund, not a portion of the order left in force, and it is
        // recorded on the refund row where it belongs.
        $newstatus = status_machine::REFUNDED;

        if (status_machine::can_transition($transaction->status, $newstatus)) {
            $DB->update_record('local_payments_transactions', (object) [
                'id' => $transaction->id,
                'status' => $newstatus,
                'timemodified' => time(),
            ]);
            manager::audit_log((int) $transaction->id, $USER->id, 'refund_' . $route,
                $transaction->status, $newstatus);
        }

        self::revoke_access($transaction);

        return (object) [
            'success' => true,
            'message' => get_string('refund_done', 'local_payments', (object) [
                'amount' => format_float($quote->net, 2, true, true) . ' ' . $quote->currency,
            ]),
            'refundid' => (int) $refundid,
        ];
    }

    /**
     * Take back what the payment bought.
     *
     * A refunded course purchase that leaves the student enrolled is a paid
     * course given away. Subscriptions are handled by their own plugin, which
     * owns the notion of an active purchase.
     */
    private static function revoke_access(\stdClass $transaction): void {
        $itemtype = refund_policy::item_type($transaction);

        if ($itemtype === 'subscription') {
            if (class_exists('\local_nit_subscriptions\subscription_purchase_manager')
                    && method_exists('\local_nit_subscriptions\subscription_purchase_manager',
                        'revoke_for_transaction')) {
                \local_nit_subscriptions\subscription_purchase_manager::revoke_for_transaction(
                    (int) $transaction->id);
            }
            return;
        }

        if (!empty($transaction->courseid)) {
            enrollment_handler::unenrol_user((int) $transaction->userid, (int) $transaction->courseid);
        }
    }

    /**
     * Tell the buyer what was decided. Best effort: a message that fails to send
     * must not roll back a refund that already left the gateway.
     */
    private static function notify_decision(\stdClass $request, \stdClass $transaction,
            bool $approved, string $note): void {
        global $DB;

        try {
            $user = $DB->get_record('user', ['id' => $request->userid]);
            if (!$user) {
                return;
            }

            $subject = get_string($approved ? 'refund_msg_approved_subject' : 'refund_msg_rejected_subject',
                'local_payments');
            $body = get_string($approved ? 'refund_msg_approved_body' : 'refund_msg_rejected_body',
                'local_payments', (object) [
                    'order' => $transaction->order_id,
                    'note' => $note !== '' ? $note : '-',
                ]);

            $message = new \core\message\message();
            $message->component = 'local_payments';
            $message->name = 'refund_decision';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $user;
            $message->subject = $subject;
            $message->fullmessage = $body;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = nl2br(s($body));
            $message->smallmessage = $subject;
            $message->notification = 1;

            message_send($message);
        } catch (\Throwable $e) {
            debugging('Refund decision notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
