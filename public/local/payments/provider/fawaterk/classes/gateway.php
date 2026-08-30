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

    /**
     * The vendor key is trimmed: it is long enough that people paste it with a
     * trailing newline, and Fawaterk then answers "Invalid Token" for what looks
     * like a correct key.
     */
    private function get_vendor_key(): string {
        return trim((string) $this->get_setting('vendor_key', ''));
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
        $address = '';

        if ($request->userid) {
            $user = $DB->get_record('user', ['id' => $request->userid],
                'id, firstname, lastname, phone1, phone2, email, city, address, country');
            if ($user) {
                $firstname = trim((string) $user->firstname);
                $lastname = trim((string) $user->lastname);
                $phone = trim((string) ($user->phone1 ?: $user->phone2));
                $address = trim((string) ($user->address ?: $user->city));
            }
        }

        // Fawaterk validates every one of these and answers HTTP 400 if any is
        // empty or malformed, so fall back to values that are valid but
        // obviously placeholder rather than letting the checkout die.
        if ($firstname === '') {
            $firstname = 'Customer';
        }
        if ($lastname === '') {
            $lastname = (string) $request->customer_reference ?: 'Account';
        }
        if ($address === '') {
            $address = (string) $this->get_setting('default_address', 'N/A');
        }

        return [
            'first_name' => \core_text::substr($firstname, 0, 50),
            'last_name' => \core_text::substr($lastname, 0, 50),
            'email' => $request->customer_email,
            'phone' => $this->normalise_phone($phone),
            'address' => \core_text::substr($address, 0, 100),
        ];
    }

    /**
     * Coerce a Moodle profile phone into the local Egyptian format Fawaterk
     * accepts (01XXXXXXXXX). "+20 100 123 4567", "0020...", "201..." all reduce
     * to the same 11 digits; anything that still doesn't fit falls back to the
     * configured placeholder, because a malformed phone is a hard 400.
     */
    private function normalise_phone(string $phone): string {
        $fallback = (string) $this->get_setting('default_phone', '01000000000');

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return $fallback;
        }

        // Strip an international Egyptian prefix in any of its spellings.
        if (strpos($digits, '0020') === 0) {
            $digits = substr($digits, 4);
        } else if (strpos($digits, '20') === 0 && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 10 && strpos($digits, '1') === 0) {
            $digits = '0' . $digits;
        }

        return preg_match('/^01[0-9]{9}$/', $digits) ? $digits : $fallback;
    }

    /**
     * Build the invoice body both endpoints share.
     *
     * Fawaterk validates cartTotal against the sum of cartItems, so the cart is
     * kept to a single line worth exactly the amount we are charging.
     */
    private function build_invoice_body(payment_request $request): array {
        $amount = number_format(round($request->amount, 2), 2, '.', '');
        $itemname = $request->description !== '' ? $request->description : ('Order ' . $request->order_id);

        return [
            'cartTotal' => $amount,
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
                    'price' => $amount,
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
    }

    /**
     * Create the payment.
     *
     * With a payment_method_id the charge goes server-to-server through
     * /api/v2/invoiceInitPay — the buyer never sees a Fawaterk method picker and
     * we get back either a 3-D Secure URL or a reference code to display. This is
     * the flow Fawaterk recommends and the one the mobile app uses.
     *
     * Without one we fall back to /api/v2/createInvoiceLink, which returns a
     * hosted page where Fawaterk asks for the method itself.
     */
    public function initialize_payment(payment_request $request): checkout_response {
        $methodid = $request->payment_method_id;

        // -1 is the explicit "give me the hosted page" escape hatch.
        if ($methodid < 0) {
            return $this->init_hosted_invoice($request);
        }

        // Nothing chosen (the web checkout, which has no picker): pick for them.
        if ($methodid === 0) {
            $methodid = $this->resolve_auto_method();
        }

        if ($methodid <= 0) {
            // No usable method — either auto-selection is off or the account
            // reported none. The hosted page can still take the payment.
            return $this->init_hosted_invoice($request);
        }

        $request->payment_method_id = $methodid;
        return $this->init_direct_payment($request);
    }

    /**
     * Choose a payment method when the caller didn't.
     *
     * One enabled method → use it. Several → take the first one named in the
     * configured priority list; anything the list doesn't mention falls to the
     * end, in the order Fawaterk returned it. That keeps the web checkout on a
     * single, predictable method without asking the buyer to choose.
     *
     * @return int Method id, or 0 to fall back to the hosted page.
     */
    private function resolve_auto_method(): int {
        if (!$this->get_setting('auto_select_method', 1)) {
            return 0;
        }

        $methods = $this->get_payment_methods();
        if (empty($methods)) {
            return 0;
        }
        if (count($methods) === 1) {
            return (int) $methods[0]['id'];
        }

        $available = array_column($methods, 'id');

        $priority = array_filter(array_map('intval',
            explode(',', (string) $this->get_setting('method_priority', '2,4,3'))));
        foreach ($priority as $preferred) {
            if (in_array($preferred, $available, true)) {
                return $preferred;
            }
        }

        // Priority list matched nothing the account actually has enabled.
        return (int) $methods[0]['id'];
    }

    /**
     * POST /api/v2/createInvoiceLink — hosted invoice page.
     */
    private function init_hosted_invoice(payment_request $request): checkout_response {
        $base = $this->get_api_base();
        $body = $this->build_invoice_body($request);

        $this->log('info', 'Creating Fawaterk invoice link', [
            'order_id' => $request->order_id,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'transaction_id' => $request->transaction_id,
        ]);

        $result = $this->http_request('POST', "{$base}/api/v2/createInvoiceLink",
            $this->get_auth_headers(), $body);

        $error = $this->check_api_error($result, 'invoice creation', $request->transaction_id);
        if ($error !== null) {
            return $error;
        }

        $payload = $result['body'];
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

        $this->log('info', 'Fawaterk invoice link created', [
            'invoice_id' => $invoiceid,
            'transaction_id' => $request->transaction_id,
        ]);

        return checkout_response::success($url, $invoiceid, $payload, array_merge(
            checkout_response::empty_payment_data(),
            ['type' => 'redirect', 'redirect_url' => $url]
        ));
    }

    /**
     * POST /api/v2/invoiceInitPay — charge one specific payment method.
     */
    private function init_direct_payment(payment_request $request): checkout_response {
        $base = $this->get_api_base();
        $body = $this->build_invoice_body($request);
        $body['payment_method_id'] = $request->payment_method_id;

        $this->log('info', 'Initiating Fawaterk direct payment', [
            'order_id' => $request->order_id,
            'payment_method_id' => $request->payment_method_id,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'transaction_id' => $request->transaction_id,
        ]);

        $result = $this->http_request('POST', "{$base}/api/v2/invoiceInitPay",
            $this->get_auth_headers(), $body);

        $error = $this->check_api_error($result, 'payment initiation', $request->transaction_id);
        if ($error !== null) {
            return $error;
        }

        $payload = $result['body'];
        $data = $payload['data'] ?? [];
        $invoiceid = (string) ($data['invoice_id'] ?? $data['invoiceId'] ?? '');
        $paymentdata = is_array($data['payment_data'] ?? null) ? $data['payment_data'] : [];

        if ($invoiceid === '') {
            $this->log('error', 'Fawaterk direct payment response missing invoice_id', [
                'response' => $payload,
                'transaction_id' => $request->transaction_id,
            ]);
            return checkout_response::failure('Missing invoice_id in Fawaterk response', $payload);
        }

        $normalised = $this->normalise_payment_data($paymentdata);
        $normalised['method_name'] = $this->method_name($request->payment_method_id);

        if ($normalised['type'] === 'none') {
            // Nothing to redirect to and no code to show — the buyer would be
            // stuck, so treat it as a failure rather than a silent dead end.
            $this->log('error', 'Fawaterk returned no usable payment_data', [
                'response' => $payload,
                'payment_method_id' => $request->payment_method_id,
                'transaction_id' => $request->transaction_id,
            ]);
            return checkout_response::failure(
                'Fawaterk returned no redirect URL or reference for payment method '
                    . $request->payment_method_id,
                $payload
            );
        }

        $this->log('info', 'Fawaterk direct payment initiated', [
            'invoice_id' => $invoiceid,
            'payment_type' => $normalised['type'],
            'transaction_id' => $request->transaction_id,
        ]);

        // checkout_url stays the redirect target when there is one; for a
        // reference-code method there is no page to open and it is empty.
        return checkout_response::success($normalised['redirect_url'], $invoiceid, $payload, $normalised);
    }

    /**
     * Display name of a method id, from the cached account list.
     */
    private function method_name(int $methodid): string {
        foreach ($this->get_payment_methods() as $method) {
            if ((int) $method['id'] === $methodid) {
                return current_language() === 'ar' && $method['name_ar'] !== ''
                    ? $method['name_ar'] : $method['name_en'];
            }
        }
        return '';
    }

    /**
     * Flatten Fawaterk's per-method payment_data into our fixed shape.
     */
    private function normalise_payment_data(array $data): array {
        $out = checkout_response::empty_payment_data();

        $redirect = (string) ($data['redirectTo'] ?? $data['redirectUrl'] ?? '');
        if ($redirect !== '') {
            $out['type'] = 'redirect';
            $out['redirect_url'] = $redirect;
            return $out;
        }

        // Fawry / Meeza / wallet codes — the buyer pays with the code elsewhere
        // and the webhook tells us when they did.
        $reference = (string) ($data['fawryCode'] ?? $data['meezaReference']
            ?? $data['aman_code'] ?? $data['masaryCode'] ?? $data['reference'] ?? '');
        if ($reference !== '') {
            $out['type'] = 'reference';
            $out['reference'] = $reference;
            $out['reference_expires_at'] = (string) ($data['expireDate'] ?? '');
        }

        return $out;
    }

    /**
     * Turn a non-2xx or non-success API answer into a checkout_response.
     *
     * The gateway's own validation message is folded into the error text —
     * without it a 400 is untraceable from the site, and the reason is nearly
     * always a rejected field (currency, phone format, cart total).
     *
     * @return checkout_response|null Null when the call succeeded.
     */
    private function check_api_error(array $result, string $what, int $transactionid): ?checkout_response {
        $body = is_array($result['body']) ? $result['body'] : [];

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            $this->log('error', "Fawaterk {$what} failed", [
                'http_code' => $result['http_code'],
                'response' => $body ?: $result['raw'],
                'transaction_id' => $transactionid,
            ]);
            return checkout_response::failure(
                sprintf('Fawaterk %s failed: HTTP %d — %s', $what, $result['http_code'],
                    $this->stringify_error($body ?: ['message' => (string) $result['raw']])),
                $body
            );
        }

        if (($body['status'] ?? '') !== 'success') {
            $this->log('error', "Fawaterk {$what} returned a non-success status", [
                'response' => $body,
                'transaction_id' => $transactionid,
            ]);
            return checkout_response::failure('Fawaterk error: ' . $this->stringify_error($body), $body);
        }

        return null;
    }

    /**
     * GET /api/v2/getPaymentmethods — the methods enabled on the account.
     *
     * Cached (see local_payments db/caches.php) so selecting a method does not
     * add an API round-trip to every checkout. Purge caches after enabling a new
     * method in the Fawaterk dashboard, or wait out the hour.
     */
    public function get_payment_methods(): array {
        $cache = \cache::make('local_payments', 'provider_payment_methods');
        $key = $this->plugin_name . ($this->is_sandbox() ? '_sandbox' : '_live');

        $cached = $cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $methods = $this->fetch_payment_methods();

        // Only cache a real answer — caching an empty list would keep the site on
        // the hosted-page fallback for an hour after a transient API failure.
        if (!empty($methods)) {
            $cache->set($key, $methods);
        }

        return $methods;
    }

    private function fetch_payment_methods(): array {
        $base = $this->get_api_base();
        $result = $this->http_request('GET', "{$base}/api/v2/getPaymentmethods", $this->get_auth_headers());

        if ($result['http_code'] < 200 || $result['http_code'] >= 300
                || (($result['body']['status'] ?? '') !== 'success')) {
            $this->log('error', 'Fawaterk getPaymentmethods failed', [
                'http_code' => $result['http_code'],
                'response' => $result['body'],
            ]);
            return [];
        }

        $methods = [];
        foreach (($result['body']['data'] ?? []) as $method) {
            if (empty($method['paymentId'])) {
                continue;
            }
            $methods[] = [
                'id' => (int) $method['paymentId'],
                'name_en' => (string) ($method['name_en'] ?? ''),
                'name_ar' => (string) ($method['name_ar'] ?? ''),
                'logo' => (string) ($method['logo'] ?? ''),
                // Fawaterk sends this as the string "true"/"false".
                'redirect' => filter_var($method['redirect'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $methods;
    }

    public function supports_payment_methods(): bool {
        return true;
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
        $text = '';
        if (!empty($payload['message'])) {
            $text = is_array($payload['message']) ? json_encode($payload['message']) : (string) $payload['message'];
        } else if (!empty($payload['errors'])) {
            $text = json_encode($payload['errors']);
        } else {
            $text = json_encode($payload);
        }

        // Fawaterk answers a bad vendor key with HTTP 400 and a "token" error
        // rather than a 401, which reads like a bad request. Name the usual
        // cause, because staging and live are separate accounts with separate
        // keys and mixing them up is by far the most common setup mistake.
        if (stripos($text, 'token') !== false || stripos($text, 'vendor') !== false) {
            $mode = $this->is_sandbox() ? 'sandbox' : 'live';
            $other = $this->is_sandbox() ? 'live' : 'sandbox';
            $text .= sprintf(
                ' — Fawaterk rejected the vendor key. This provider is in %s mode and calls %s,'
                . ' so the key must be the %s account\'s API key; a %s key will always fail here.',
                $mode, $this->get_api_base(), $mode, $other
            );
        }

        return $text;
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
