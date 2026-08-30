<?php
namespace paymentprovider_fawaterk;

use local_payments\provider\base_provider;
use local_payments\provider\payment_request;
use local_payments\provider\checkout_response;
use local_payments\provider\verification_result;
use local_payments\provider\webhook_result;
use local_payments\provider\refund_result;
use local_payments\provider\transaction_info;

defined('MOODLE_INTERNAL') || die();

/**
 * Fawaterk (Fawaterak) payment provider.
 *
 * Hosted checkout via POST /api/v2/createInvoiceLink. The invoice id we get back
 * is stored as the transaction's provider_session_id and is what verify_payment()
 * and the webhook handler use to re-read the invoice server-side.
 *
 * Fawaterk has no per-request webhook URL — it is configured once in the Fawaterk
 * dashboard and must end in "_json" to get a JSON body, so point it at
 * {wwwroot}/local/payments/webhook_json.php
 */
class gateway extends base_provider {

    /** Live API host. */
    const LIVE_URL = 'https://app.fawaterk.com';

    /** Staging/sandbox API host. */
    const SANDBOX_URL = 'https://staging.fawaterk.com';

    private function get_vendor_key(): string {
        return (string) $this->get_setting('vendor_key', '');
    }

    /**
     * Base host for the current mode. base_url / sandbox_url are overridable in
     * settings so a host change never needs a code deploy.
     */
    private function get_api_base(): string {
        $key = $this->is_sandbox() ? 'sandbox_url' : 'base_url';
        $default = $this->is_sandbox() ? self::SANDBOX_URL : self::LIVE_URL;
        return rtrim((string) $this->get_setting($key, $default) ?: $default, '/');
    }

    private function get_auth_headers(): array {
        return [
            'Authorization' => 'Bearer ' . $this->get_vendor_key(),
            'Accept' => 'application/json',
        ];
    }

    /**
     * Split the buyer's Moodle profile into the name/phone Fawaterk requires.
     * payment_request only carries the email, so read the rest from the user row.
     */
    private function get_customer(payment_request $request): array {
        global $DB;

        $firstname = '';
        $lastname = '';
        $phone = '';

        if ($request->userid) {
            $user = $DB->get_record('user', ['id' => $request->userid],
                'id, firstname, lastname, phone1, phone2, email');
            if ($user) {
                $firstname = trim((string) $user->firstname);
                $lastname = trim((string) $user->lastname);
                $phone = trim((string) ($user->phone1 ?: $user->phone2));
            }
        }

        // Fawaterk rejects the invoice if any of these are empty, so fall back to
        // values that are valid but obviously placeholder.
        if ($firstname === '') {
            $firstname = 'Customer';
        }
        if ($lastname === '') {
            $lastname = (string) $request->customer_reference ?: 'Account';
        }
        if ($phone === '') {
            $phone = (string) $this->get_setting('default_phone', '01000000000');
        }

        return [
            'first_name' => $firstname,
            'last_name' => $lastname,
            'email' => $request->customer_email,
            'phone' => $phone,
        ];
    }

    /**
     * POST /api/v2/createInvoiceLink — create a hosted invoice/checkout link.
     */
    public function initialize_payment(payment_request $request): checkout_response {
        $base = $this->get_api_base();

        // Fawaterk validates cartTotal against the sum of cartItems, so keep the
        // cart a single line worth exactly the amount we are charging.
        $amount = round($request->amount, 2);
        $itemname = $request->description !== '' ? $request->description : ('Order ' . $request->order_id);

        $body = [
            'cartTotal' => number_format($amount, 2, '.', ''),
            'currency' => $request->currency,
            'customer' => $this->get_customer($request),
            'redirectionUrls' => [
                'successUrl' => $request->success_url,
                'failUrl' => $request->failure_url,
                'pendingUrl' => $request->success_url,
            ],
            'cartItems' => [
                [
                    'name' => \core_text::substr($itemname, 0, 100),
                    'price' => number_format($amount, 2, '.', ''),
                    'quantity' => '1',
                ],
            ],
            // Echoed back verbatim on every webhook — this is how we find the
            // transaction again, since Fawaterk does not carry our order id.
            'payLoad' => array_merge($request->metadata, [
                'transaction_id' => $request->transaction_id,
                'courseid' => $request->courseid,
                'order_id' => $request->order_id,
            ]),
            'sendEmail' => (bool) $this->get_setting('send_email', 0),
            'sendSMS' => (bool) $this->get_setting('send_sms', 0),
        ];

        $this->log('info', 'Creating Fawaterk invoice', [
            'order_id' => $request->order_id,
            'amount' => $amount,
            'currency' => $request->currency,
            'transaction_id' => $request->transaction_id,
        ]);

        $result = $this->http_request('POST', "{$base}/api/v2/createInvoiceLink",
            $this->get_auth_headers(), $body);

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            $this->log('error', 'Fawaterk invoice creation failed', [
                'http_code' => $result['http_code'],
                'response' => $result['body'],
                'transaction_id' => $request->transaction_id,
            ]);
            return checkout_response::failure(
                'Fawaterk invoice creation failed: HTTP ' . $result['http_code'],
                $result['body']
            );
        }

        $payload = $result['body'];
        if (($payload['status'] ?? '') !== 'success') {
            $this->log('error', 'Fawaterk returned a non-success status', [
                'response' => $payload,
                'transaction_id' => $request->transaction_id,
            ]);
            return checkout_response::failure(
                'Fawaterk error: ' . $this->stringify_error($payload),
                $payload
            );
        }

        $data = $payload['data'] ?? [];
        $url = (string) ($data['url'] ?? '');
        $invoiceid = (string) ($data['invoiceId'] ?? $data['invoice_id'] ?? '');

        if ($url === '' || $invoiceid === '') {
            $this->log('error', 'Fawaterk response missing url/invoiceId', [
                'response' => $payload,
                'transaction_id' => $request->transaction_id,
            ]);
            return checkout_response::failure('Missing url or invoiceId in Fawaterk response', $payload);
        }

        $this->log('info', 'Fawaterk invoice created', [
            'invoice_id' => $invoiceid,
            'invoice_key' => $data['invoiceKey'] ?? '',
            'transaction_id' => $request->transaction_id,
        ]);

        return checkout_response::success($url, $invoiceid, $payload);
    }

    /**
     * GET /api/v2/getInvoiceData/:invoiceId — authoritative invoice state.
     *
     * @param string $invoiceid
     * @return array|null Decoded `data` object, or null when the call failed.
     */
    private function get_invoice_data(string $invoiceid): ?array {
        if ($invoiceid === '') {
            return null;
        }

        $base = $this->get_api_base();
        $result = $this->http_request('GET', "{$base}/api/v2/getInvoiceData/{$invoiceid}",
            $this->get_auth_headers());

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            $this->log('error', 'Fawaterk getInvoiceData failed', [
                'invoice_id' => $invoiceid,
                'http_code' => $result['http_code'],
            ]);
            return null;
        }

        $payload = $result['body'];
        if (($payload['status'] ?? '') !== 'success') {
            return null;
        }

        return $payload['data'] ?? [];
    }

    /**
     * Verify a payment server-side. $provider_reference is the Fawaterk invoice id
     * we stored as provider_session_id at checkout time.
     */
    public function verify_payment(string $provider_reference): verification_result {
        $data = $this->get_invoice_data($provider_reference);

        if ($data === null) {
            return new verification_result([
                'verified' => false,
                'error_message' => 'Fawaterk invoice lookup failed for invoice ' . $provider_reference,
            ]);
        }

        $paid = $this->invoice_is_paid($data);

        return new verification_result([
            'verified' => $paid,
            'status' => $paid ? 'SUCCESS' : strtoupper((string) ($data['invoice_status'] ?? 'UNPAID')),
            'amount' => (float) ($data['total'] ?? $data['paid_amount'] ?? 0),
            'currency' => (string) ($data['currency'] ?? ''),
            'provider_txn_id' => $this->extract_txn_id($data),
            'provider_order_id' => (string) ($data['invoice_id'] ?? $provider_reference),
            'payment_method_type' => (string) ($data['payment_method'] ?? ''),
            'raw_response' => $data,
        ]);
    }

    /**
     * Fawaterk reports "paid" either as a 1/0 flag or an invoice_status string
     * depending on the endpoint version — accept both.
     */
    private function invoice_is_paid(array $data): bool {
        if (isset($data['paid'])) {
            return (int) $data['paid'] === 1;
        }
        return strtolower((string) ($data['invoice_status'] ?? '')) === 'paid';
    }

    private function extract_txn_id(array $data): string {
        $transactions = $data['invoice_transactions'] ?? [];
        if (is_array($transactions) && !empty($transactions)) {
            $last = end($transactions);
            if (is_array($last)) {
                return (string) ($last['transaction_id'] ?? $last['id'] ?? $last['reference_number'] ?? '');
            }
        }
        return (string) ($data['referenceNumber'] ?? '');
    }

    /**
     * Process a Fawaterk webhook.
     *
     * Fawaterk posts four different shapes to the same URL, so the event is
     * inferred from the fields present:
     *  - invoice_status = paid  → successful payment
     *  - errorMessage present   → failed payment attempt
     *  - status = EXPIRED       → cancelled (Fawry/Aman/Masary reference expired)
     *  - approvedAt present     → refund approved
     *
     * The hashKey is an HMAC-SHA256 of a fixed query string, keyed with the
     * vendor key. Paid/failed use InvoiceId+InvoiceKey+PaymentMethod, cancelled
     * uses referenceId+PaymentMethod.
     */
    public function handle_webhook(string $payload, array $headers): webhook_result {
        $data = json_decode($payload, true);
        if (empty($data) || !is_array($data)) {
            return new webhook_result([
                'signature_valid' => false,
                'processed' => false,
                'error_message' => 'Invalid JSON payload',
            ]);
        }

        $meta = $this->decode_payload_field($data['pay_load'] ?? $data['payLoad'] ?? null);
        $merchantorderid = (string) ($meta['order_id'] ?? '');
        $hashkey = (string) ($data['hashKey'] ?? '');

        // ── Refund approved ──────────────────────────────────────────────────
        // Fawaterk documents no hashKey for refund notifications and sends no
        // invoice reference, so there is nothing to authenticate or match on.
        // Record it and let an admin reconcile from the dashboard.
        if (isset($data['approvedAt']) || strtolower((string) ($data['status'] ?? '')) === 'approved') {
            $this->log('warning', 'Fawaterk refund webhook received (not auto-applied)', [
                'transaction_reference' => $data['transactionId'] ?? '',
                'amount' => $data['amount'] ?? '',
            ]);
            return new webhook_result([
                'signature_valid' => false,
                'processed' => false,
                'event_type' => 'refund',
                'merchant_order_id' => $merchantorderid,
                'provider_txn_id' => (string) ($data['transactionId'] ?? ''),
                'status' => (string) ($data['status'] ?? ''),
                'amount' => (float) ($data['amount'] ?? 0),
                'currency' => (string) ($data['currency'] ?? ''),
                'response_message' => (string) ($data['reason'] ?? ''),
                'metadata' => $meta,
                'error_message' => 'Fawaterk refund webhooks are unsigned and carry no invoice reference; '
                    . 'reconcile this refund manually.',
            ]);
        }

        // ── Cancelled / expired reference ────────────────────────────────────
        if (strtoupper((string) ($data['status'] ?? '')) === 'EXPIRED') {
            $method = (string) ($data['paymentMethod'] ?? '');
            $referenceid = (string) ($data['referenceId'] ?? '');
            $valid = $this->check_hash($hashkey, "referenceId={$referenceid}&PaymentMethod={$method}");

            if (!$valid) {
                $this->log('warning', 'Fawaterk cancelled-webhook signature check failed', [
                    'reference_id' => $referenceid,
                ]);
            }

            return new webhook_result([
                'signature_valid' => $valid,
                'processed' => $valid,
                'event_type' => 'pay',
                'merchant_order_id' => $merchantorderid,
                'provider_txn_id' => (string) ($data['transactionId'] ?? ''),
                'order_reference' => $referenceid,
                'status' => 'EXPIRED',
                'payment_method' => $method,
                'response_message' => 'Payment reference expired',
                'metadata' => $meta,
            ]);
        }

        // ── Paid / failed ────────────────────────────────────────────────────
        $invoiceid = (string) ($data['invoice_id'] ?? '');
        $invoicekey = (string) ($data['invoice_key'] ?? '');
        $method = (string) ($data['payment_method'] ?? '');
        $ispaid = strtolower((string) ($data['invoice_status'] ?? '')) === 'paid';

        $signaturebase = "InvoiceId={$invoiceid}&InvoiceKey={$invoicekey}&PaymentMethod={$method}";
        $valid = $this->check_hash($hashkey, $signaturebase);

        if ($ispaid) {
            if (!$valid) {
                $this->log('warning', 'Fawaterk paid-webhook signature check failed', [
                    'invoice_id' => $invoiceid,
                ]);
                return new webhook_result([
                    'signature_valid' => false,
                    'processed' => false,
                    'event_type' => 'pay',
                    'merchant_order_id' => $merchantorderid,
                    'provider_order_id' => $invoiceid,
                    'error_message' => 'hashKey verification failed',
                ]);
            }

            // The paid webhook carries no amount, and the amount is what the
            // manager checks before enrolling — so re-read the invoice from the
            // API and use those figures rather than trusting the POST body.
            $invoice = $this->get_invoice_data($invoiceid);
            if ($invoice === null || !$this->invoice_is_paid($invoice)) {
                $this->log('error', 'Fawaterk paid webhook not confirmed by getInvoiceData', [
                    'invoice_id' => $invoiceid,
                ]);
                return new webhook_result([
                    'signature_valid' => true,
                    'processed' => false,
                    'event_type' => 'pay',
                    'merchant_order_id' => $merchantorderid,
                    'provider_order_id' => $invoiceid,
                    'error_message' => 'Invoice is not confirmed paid by the Fawaterk API',
                ]);
            }

            return new webhook_result([
                'signature_valid' => true,
                'processed' => true,
                'event_type' => 'pay',
                'merchant_order_id' => $merchantorderid,
                'provider_order_id' => $invoiceid,
                'provider_txn_id' => (string) ($data['referenceNumber'] ?? $this->extract_txn_id($invoice)),
                'order_reference' => $invoicekey,
                'status' => 'SUCCESS',
                'amount' => (float) ($invoice['total'] ?? 0),
                'currency' => (string) ($invoice['currency'] ?? ''),
                'payment_method' => $method ?: (string) ($invoice['payment_method'] ?? ''),
                'metadata' => $meta,
            ]);
        }

        // Failed attempt. Fawaterk's docs show no hashKey on this shape, so when
        // it is absent confirm server-side that the invoice really is unpaid
        // before letting an unauthenticated POST fail somebody's order.
        if ($hashkey === '') {
            $invoice = $this->get_invoice_data($invoiceid);
            $valid = ($invoice !== null && !$this->invoice_is_paid($invoice));
            if (!$valid) {
                $this->log('warning', 'Unsigned Fawaterk failure webhook could not be confirmed', [
                    'invoice_id' => $invoiceid,
                ]);
            }
        }

        $response = $data['response'] ?? [];

        return new webhook_result([
            'signature_valid' => $valid,
            'processed' => $valid,
            'event_type' => 'pay',
            'merchant_order_id' => $merchantorderid,
            'provider_order_id' => $invoiceid,
            'provider_txn_id' => (string) ($data['referenceNumber'] ?? ''),
            'order_reference' => $invoicekey,
            'status' => 'FAILED',
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => (string) ($data['paidCurrency'] ?? ''),
            'payment_method' => $method,
            'response_code' => (string) ($response['gatewayCode'] ?? ''),
            'response_message' => (string) ($data['errorMessage']
                ?? ($response['gatewayRecommendation'] ?? '')),
            'metadata' => $meta,
        ]);
    }

    /**
     * Constant-time HMAC-SHA256 comparison against the vendor key.
     */
    private function check_hash(string $received, string $querystring): bool {
        if ($received === '') {
            return false;
        }
        $vendorkey = $this->get_vendor_key();
        if ($vendorkey === '') {
            $this->log('error', 'Fawaterk vendor key is not configured; cannot verify webhook');
            return false;
        }
        $calculated = hash_hmac('sha256', $querystring, $vendorkey, false);
        return hash_equals($calculated, $received);
    }

    /**
     * pay_load comes back as an object on some events and a JSON string on others.
     */
    private function decode_payload_field($raw): array {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function stringify_error(array $payload): string {
        if (!empty($payload['message'])) {
            return is_array($payload['message']) ? json_encode($payload['message']) : (string) $payload['message'];
        }
        if (!empty($payload['errors'])) {
            return json_encode($payload['errors']);
        }
        return json_encode($payload);
    }

    /**
     * Fawaterk exposes no refund endpoint on the v2 API — refunds are raised from
     * the Fawaterk dashboard and only notified back over the webhook.
     */
    public function refund(string $provider_order_id, float $amount, string $currency, string $reason = ''): refund_result {
        return new refund_result([
            'success' => false,
            'amount' => $amount,
            'currency' => $currency,
            'error_message' => 'Fawaterk does not expose a refund API; raise the refund in the Fawaterk dashboard.',
        ]);
    }

    public function void_payment(string $provider_order_id, string $reason = ''): refund_result {
        return new refund_result([
            'success' => false,
            'error_message' => 'Fawaterk does not support voiding an invoice through the API.',
        ]);
    }

    public function get_transaction(string $provider_reference): transaction_info {
        $result = $this->verify_payment($provider_reference);
        return new transaction_info([
            'found' => !empty($result->raw_response),
            'status' => $result->status,
            'amount' => $result->amount,
            'currency' => $result->currency,
            'provider_txn_id' => $result->provider_txn_id,
            'provider_order_id' => $result->provider_order_id,
            'payment_method_type' => $result->payment_method_type,
            'raw_response' => $result->raw_response,
        ]);
    }

    public function supports_refund(): bool {
        return false;
    }

    public function supports_void(): bool {
        return false;
    }

    public function supported_currencies(): array {
        return ['EGP', 'USD', 'SAR', 'AED'];
    }

    public function supported_countries(): array {
        return ['EG', 'SA', 'AE'];
    }
}
