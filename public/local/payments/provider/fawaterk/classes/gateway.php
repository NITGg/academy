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
 * Fawaterk has two generations of API and they use different credentials. Both
 * are implemented; `auth_mode` picks one, because the credential and the API
 * version go together:
 *
 *  - **v3 + OAuth** (`auth_mode = oauth`, the default). Client id/secret from
 *    the dashboard's Integrations page mint a token on /oauth/token, which
 *    authenticates /api/v3/*. This is the current API: it takes a per-request
 *    webhook URL, exposes refunds, and reports transactions by `intent_key`.
 *  - **v2 + HASH API key** (`auth_mode = apikey`). The older /api/v2/* endpoints
 *    take the HASH API key straight through as the bearer. Kept as a fallback;
 *    it has no refund API and the webhook URL must be set in the dashboard.
 *
 * The HASH API key is required either way: every webhook is signed with it, not
 * with an access token, so without it no payment can ever be confirmed.
 *
 * Endpoints verified against a live account (Aug 2026): the OAuth grant, both
 * createTransaction shapes, getTransactionData, and the v2 equivalents.
 */
class gateway extends base_provider {

    /** Live API host. */
    const LIVE_URL = 'https://app.fawaterk.com';

    /** Staging/sandbox API host. */
    const SANDBOX_URL = 'https://staging.fawaterk.com';

    // ─── Configuration ──────────────────────────────────────────────────────

    /**
     * The HASH API key from "Iframe/Webhook integrations settings".
     *
     * Signs every webhook (both API generations), and is the bearer in v2 mode.
     * Trimmed because it is long enough that people paste it with a trailing
     * newline, and Fawaterk then rejects a key that looks correct.
     */
    private function get_vendor_key(): string {
        return trim((string) $this->get_setting('vendor_key', ''));
    }

    private function get_auth_mode(): string {
        return $this->get_setting('auth_mode', 'oauth') === 'apikey' ? 'apikey' : 'oauth';
    }

    /** OAuth mode means the v3 API; the static key means v2. */
    private function uses_v3(): bool {
        return $this->get_auth_mode() === 'oauth';
    }

    private function get_client_id(): string {
        return trim((string) $this->get_setting('client_id', ''));
    }

    private function get_client_secret(): string {
        return trim((string) $this->get_setting('client_secret', ''));
    }

    private function get_api_base(): string {
        $key = $this->is_sandbox() ? 'sandbox_url' : 'base_url';
        $default = $this->is_sandbox() ? self::SANDBOX_URL : self::LIVE_URL;
        return rtrim((string) $this->get_setting($key, $default) ?: $default, '/');
    }

    /**
     * Token endpoint. Defaults to /oauth/token on the current mode's host.
     */
    private function get_token_url(): string {
        $override = trim((string) $this->get_setting('token_url', ''));
        return $override !== '' ? $override : $this->get_api_base() . '/oauth/token';
    }

    // ─── Authentication ─────────────────────────────────────────────────────

    /**
     * Cache key for the current credentials, so changing a key or flipping
     * sandbox can never hand back the previous account's token.
     */
    private function token_cache_key(): string {
        return $this->plugin_name . '_' . substr(sha1(
            $this->get_token_url() . '|' . $this->get_client_id() . '|' . $this->get_client_secret()
        ), 0, 20);
    }

    /**
     * @param bool $forcerefresh Skip the cache (used once after a 401).
     * @return string Empty when no token could be obtained.
     */
    private function get_access_token(bool $forcerefresh = false): string {
        if (!$this->uses_v3()) {
            return $this->get_vendor_key();
        }

        $cache = \cache::make('local_payments', 'provider_oauth_tokens');
        $key = $this->token_cache_key();

        if (!$forcerefresh) {
            $stored = $cache->get($key);
            // Renew a minute early — a token that expires mid-request is a
            // failed checkout for whoever is holding the page.
            if (is_array($stored) && !empty($stored['token']) && ($stored['expires'] ?? 0) > time() + MINSECS) {
                return $stored['token'];
            }
        }

        $token = $this->request_access_token();
        if ($token === null) {
            return '';
        }

        $cache->set($key, $token);
        return $token['token'];
    }

    /**
     * POST /oauth/token — client_credentials grant. Form-encoded; the response
     * is the standard {access_token, token_type, expires_in}.
     *
     * @return array|null ['token' => string, 'expires' => int] or null on failure.
     */
    private function request_access_token(): ?array {
        $clientid = $this->get_client_id();
        $clientsecret = $this->get_client_secret();

        if ($clientid === '' || $clientsecret === '') {
            $this->log('error', 'Fawaterk OAuth is selected but the client id/secret are not set');
            return null;
        }

        $result = $this->http_form_request($this->get_token_url(), [
            'grant_type' => 'client_credentials',
            'client_id' => $clientid,
            'client_secret' => $clientsecret,
        ]);

        $body = is_array($result['body']) ? $result['body'] : [];
        $token = (string) ($body['access_token'] ?? '');

        if ($result['http_code'] < 200 || $result['http_code'] >= 300 || $token === '') {
            $this->log('error', 'Fawaterk OAuth token request failed', [
                'http_code' => $result['http_code'],
                'token_url' => $this->get_token_url(),
                'client_id' => $clientid,
                'error' => $body['error'] ?? '',
                'error_description' => $body['error_description'] ?? ($body['message'] ?? ''),
                // Raw, because an OAuth server that is unhappy for an unexpected
                // reason often says so outside the standard error fields — or in
                // HTML, which decodes to nothing at all. The request is not
                // logged here: it is three fields, one of them the secret.
                'response_raw' => $this->truncate_for_log($result['raw'] ?? ''),
                'curl_error' => $result['error'] ?? '',
            ]);
            return null;
        }

        $lifetime = (int) ($body['expires_in'] ?? 0);
        if ($lifetime <= 0) {
            $lifetime = HOURSECS;
        }

        return ['token' => $token, 'expires' => time() + $lifetime];
    }

    /**
     * Form-encoded POST. base_provider::http_request only speaks JSON, and the
     * OAuth token endpoint wants a form body.
     */
    private function http_form_request(string $url, array $fields, int $timeout = 30): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_RETURNTRANSFER' => true,
        ]);
        $curl->setHeader('Content-Type: application/x-www-form-urlencoded');
        $curl->setHeader('Accept: application/json');

        $response = $curl->post($url, http_build_query($fields, '', '&'));

        return [
            'http_code' => $curl->get_info()['http_code'] ?? 0,
            'body' => json_decode($response, true) ?? [],
            'raw' => $response,
            'error' => $curl->get_errno() ? $curl->error : '',
        ];
    }

    private function get_auth_headers(): array {
        return [
            'Authorization' => 'Bearer ' . $this->get_access_token(),
            'Accept' => 'application/json',
        ];
    }

    /**
     * Authenticated API call, with one retry after a 401.
     *
     * A token can be revoked server-side before its stated expiry and the only
     * way to find out is to be rejected, so drop it and try once more rather
     * than failing a real payment.
     */
    /**
     * Cap a body for the log table. Long enough for a full Fawaterk response,
     * short enough that a stray HTML error page cannot bloat the row.
     */
    private function truncate_for_log(?string $raw): string {
        $raw = (string) $raw;
        if ($raw === '') {
            return '(empty body)';
        }
        return \core_text::strlen($raw) > 4000
            ? \core_text::substr($raw, 0, 4000) . ' …[truncated]'
            : $raw;
    }

    /**
     * Record a call verbatim. Off by default — on a busy site every checkout
     * would write a row — but failures are logged regardless, because the exact
     * response is the only thing that explains a rejection.
     *
     * The Authorization header is never included. The request body is, because
     * the whole point is to see what Fawaterk was asked to validate, and that
     * body carries the buyer's name, email, phone and address.
     */
    private function log_api_call(string $method, string $url, $body, array $result): void {
        $failed = ($result['http_code'] < 200 || $result['http_code'] >= 300)
            || (is_array($result['body']) && ($result['body']['status'] ?? '') !== 'success');

        if (!$failed && !$this->get_setting('log_api_calls', 0)) {
            return;
        }

        $this->log($failed ? 'error' : 'info', "Fawaterk API {$method} " . parse_url($url, PHP_URL_PATH), [
            'url' => $url,
            'request' => $body !== null ? $this->truncate_for_log(json_encode($body)) : '(no body)',
            'http_code' => $result['http_code'],
            'curl_error' => $result['error'] ?? '',
            'response_raw' => $this->truncate_for_log($result['raw'] ?? ''),
        ]);
    }

    private function api_request(string $method, string $url, $body = null): array {
        $headers = $this->get_auth_headers();

        // Fawaterk validates a content-type on GET too — without it the answer
        // is "The content-type field is required". base_provider only sets it
        // for POST/PUT, and setting it in the auth headers would send it twice.
        if (strtoupper($method) === 'GET') {
            $headers['Content-Type'] = 'application/json';
        }

        $result = $this->http_request($method, $url, $headers, $body);

        if ($result['http_code'] === 401 && $this->uses_v3()) {
            $this->log('info', 'Fawaterk returned 401; refreshing the OAuth token and retrying once');
            if ($this->get_access_token(true) !== '') {
                $result = $this->http_request($method, $url, $this->get_auth_headers(), $body);
            }
        }

        $this->log_api_call($method, $url, $body, $result);

        return $result;
    }

    // ─── Customer details ───────────────────────────────────────────────────

    /**
     * Split the buyer's Moodle profile into the fields Fawaterk requires.
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

        // Fawaterk validates every one of these and rejects the request if any
        // is empty or malformed, so fall back to values that are valid but
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
     * accepts (01XXXXXXXXX). "+20 100 123 4567", "0020…", "201…" all reduce to
     * the same 11 digits; anything that still doesn't fit falls back to the
     * configured placeholder, because a malformed phone is a hard rejection.
     */
    private function normalise_phone(string $phone): string {
        $fallback = (string) $this->get_setting('default_phone', '01000000000');

        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') {
            return $fallback;
        }

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

    // ─── Creating a payment ─────────────────────────────────────────────────

    /**
     * Create the payment.
     *
     * With a payment method the charge is server-to-server and we get back
     * either a 3-D Secure URL or a reference code to display. Without one,
     * Fawaterk returns a hosted page where it asks for the method itself.
     */
    public function initialize_payment(payment_request $request): checkout_response {
        $methodid = $request->payment_method_id;

        // -1 is the explicit "give me the hosted page" escape hatch.
        if ($methodid < 0) {
            $methodid = 0;
        } else if ($methodid === 0) {
            // Nothing chosen (the web checkout, which has no picker): pick one.
            $methodid = $this->resolve_auto_method();
        }

        $request->payment_method_id = $methodid;

        return $this->uses_v3()
            ? $this->create_transaction_v3($request, $methodid)
            : $this->create_invoice_v2($request, $methodid);
    }

    /**
     * Choose a payment method when the caller didn't.
     *
     * One enabled method → use it. Several → the first named in the configured
     * priority list. That keeps the web checkout on a single, predictable
     * method without asking the buyer to choose.
     *
     * @return int Method id, or 0 for the hosted page.
     */
    private function resolve_auto_method(): int {
        if (!$this->get_setting('auto_select_method', 1)) {
            return 0;
        }

        $priority = array_values(array_filter(array_map('intval',
            explode(',', (string) $this->get_setting('method_priority', '2,4,3')))));

        $methods = $this->get_payment_methods();

        if (empty($methods)) {
            // An empty list does NOT mean the account can't take payments: the
            // list reports what is configured for the hosted iframe, and the
            // account this was built against returns [] while createTransaction
            // charges card (id 2) perfectly well. Trust the configured
            // preference over the enumeration.
            if (!empty($priority)) {
                $this->log('info', 'Fawaterk listed no payment methods; using the first '
                    . 'configured priority id (' . $priority[0] . ') instead.');
                return $priority[0];
            }

            $this->log('warning', 'Fawaterk listed no payment methods and no method priority '
                . 'is configured; falling back to the hosted page. Check the credentials '
                . 'with cli/fawaterk_diagnose.php.');
            return 0;
        }

        if (count($methods) === 1) {
            return (int) $methods[0]['id'];
        }

        $available = array_column($methods, 'id');
        foreach ($priority as $preferred) {
            if (in_array($preferred, $available, true)) {
                return $preferred;
            }
        }

        return (int) $methods[0]['id'];
    }

    /**
     * The cart body both API generations share.
     *
     * Fawaterk validates cartTotal against the sum of cartItems, so the cart is
     * a single line worth exactly the amount we are charging.
     */
    private function build_cart(payment_request $request): array {
        $amount = round($request->amount, 2);
        $itemname = $request->description !== '' ? $request->description : ('Order ' . $request->order_id);

        return [
            'amount' => $amount,
            'item_name' => \core_text::substr($itemname, 0, 100),
            // Echoed back on every webhook — this is how we find the transaction
            // again, since Fawaterk does not carry our order id natively.
            'payload' => array_merge($request->metadata, [
                'transaction_id' => $request->transaction_id,
                'courseid' => $request->courseid,
                'order_id' => $request->order_id,
            ]),
        ];
    }

    /**
     * POST /api/v3/createTransaction.
     */
    private function create_transaction_v3(payment_request $request, int $methodid): checkout_response {
        $cart = $this->build_cart($request);

        $body = [
            'cartTotal' => $cart['amount'],
            'currency' => $request->currency,
            'customer' => $this->get_customer($request),
            'cartItems' => [
                ['name' => $cart['item_name'], 'price' => $cart['amount'], 'quantity' => 1],
            ],
            'pay_load' => $cart['payload'],
            'redirectionUrls' => [
                'successUrl' => $request->success_url,
                'failUrl' => $request->failure_url,
                'pendingUrl' => $request->success_url,
                // v3 takes the webhook per request, so the paid/pending callback
                // does not depend on the dashboard being configured.
                'webhookUrl' => $request->webhook_url,
            ],
            'sendEmail' => (bool) $this->get_setting('send_email', 0),
            'sendSMS' => (bool) $this->get_setting('send_sms', 0),
            'lang' => $request->display_lang === 'ar' ? 'ar' : 'en',
        ];

        if ($methodid > 0) {
            $body['payment_method_id'] = $methodid;
        }

        // How long Fawaterk keeps the transaction payable. Left unset it applies
        // its own default (2 days), which is why a link for a 30-minute order
        // shows a due date days out. Sending it makes that window a decision
        // rather than an inherited surprise. Our order can still expire first —
        // a late payment is fulfilled by the expired->completed transition.
        $duedays = (int) $this->get_setting('due_date_days', 2);
        if ($duedays > 0) {
            $body['due_date'] = gmdate('Y-m-d\TH:i:s\Z', time() + ($duedays * DAYSECS));
        }

        $this->log('info', 'Creating Fawaterk v3 transaction', [
            'order_id' => $request->order_id,
            'payment_method_id' => $methodid,
            'amount' => $cart['amount'],
            'currency' => $request->currency,
            'transaction_id' => $request->transaction_id,
        ]);

        $result = $this->api_request('POST', $this->get_api_base() . '/api/v3/createTransaction', $body);

        $error = $this->check_api_error($result, 'transaction creation', $request->transaction_id);
        if ($error !== null) {
            return $error;
        }

        $data = $result['body']['data'] ?? [];
        $intentkey = (string) ($data['intent_key'] ?? '');

        if ($intentkey === '') {
            $this->log('error', 'Fawaterk v3 response missing intent_key', [
                'response' => $result['body'],
                'transaction_id' => $request->transaction_id,
            ]);
            return checkout_response::failure('Missing intent_key in Fawaterk response', $result['body']);
        }

        // Hosted page (no method chosen) answers with `url`; a direct charge
        // answers with `payment_data` shaped by the method.
        $hostedurl = (string) ($data['url'] ?? '');
        $paymentdata = is_array($data['payment_data'] ?? null) ? $data['payment_data'] : [];

        $normalised = $hostedurl !== ''
            ? array_merge(checkout_response::empty_payment_data(),
                ['type' => 'redirect', 'redirect_url' => $hostedurl])
            : $this->normalise_payment_data($paymentdata);
        $normalised['method_name'] = $this->method_name($methodid);

        if ($normalised['type'] === 'none') {
            $this->log('error', 'Fawaterk returned no usable payment_data', [
                'response' => $result['body'],
                'payment_method_id' => $methodid,
                'transaction_id' => $request->transaction_id,
            ]);
            return checkout_response::failure(
                'Fawaterk returned no redirect URL or reference for payment method ' . $methodid,
                $result['body']
            );
        }

        $this->log('info', 'Fawaterk v3 transaction created', [
            'intent_key' => $intentkey,
            'payment_type' => $normalised['type'],
            'transaction_id' => $request->transaction_id,
        ]);

        return checkout_response::success($normalised['redirect_url'], $intentkey, $result['body'], $normalised);
    }

    /**
     * Legacy v2: createInvoiceLink (hosted) or invoiceInitPay (direct charge).
     */
    private function create_invoice_v2(payment_request $request, int $methodid): checkout_response {
        $cart = $this->build_cart($request);
        $amount = number_format($cart['amount'], 2, '.', '');

        $body = [
            'cartTotal' => $amount,
            'currency' => $request->currency,
            'customer' => $this->get_customer($request),
            'redirectionUrls' => [
                'successUrl' => $request->success_url,
                'failUrl' => $request->failure_url,
                'pendingUrl' => $request->success_url,
            ],
            'cartItems' => [
                ['name' => $cart['item_name'], 'price' => $amount, 'quantity' => '1'],
            ],
            'payLoad' => $cart['payload'],
            'sendEmail' => (bool) $this->get_setting('send_email', 0),
            'sendSMS' => (bool) $this->get_setting('send_sms', 0),
        ];

        $direct = ($methodid > 0);
        if ($direct) {
            $body['payment_method_id'] = $methodid;
        }
        $endpoint = $direct ? '/api/v2/invoiceInitPay' : '/api/v2/createInvoiceLink';

        $this->log('info', 'Creating Fawaterk v2 invoice', [
            'order_id' => $request->order_id,
            'payment_method_id' => $methodid,
            'amount' => $cart['amount'],
            'transaction_id' => $request->transaction_id,
        ]);

        $result = $this->api_request('POST', $this->get_api_base() . $endpoint, $body);

        $error = $this->check_api_error($result, 'invoice creation', $request->transaction_id);
        if ($error !== null) {
            return $error;
        }

        $data = $result['body']['data'] ?? [];
        $invoiceid = (string) ($data['invoiceId'] ?? $data['invoice_id'] ?? '');
        if ($invoiceid === '') {
            return checkout_response::failure('Missing invoice id in Fawaterk response', $result['body']);
        }

        if (!$direct) {
            $url = (string) ($data['url'] ?? '');
            if ($url === '') {
                return checkout_response::failure('Missing url in Fawaterk response', $result['body']);
            }
            return checkout_response::success($url, $invoiceid, $result['body'], array_merge(
                checkout_response::empty_payment_data(),
                ['type' => 'redirect', 'redirect_url' => $url, 'method_name' => '']
            ));
        }

        $normalised = $this->normalise_payment_data(
            is_array($data['payment_data'] ?? null) ? $data['payment_data'] : []);
        $normalised['method_name'] = $this->method_name($methodid);

        if ($normalised['type'] === 'none') {
            return checkout_response::failure(
                'Fawaterk returned no redirect URL or reference for payment method ' . $methodid,
                $result['body']
            );
        }

        return checkout_response::success($normalised['redirect_url'], $invoiceid, $result['body'], $normalised);
    }

    /**
     * Flatten Fawaterk's per-method payment_data into our fixed shape.
     *
     * Card returns somewhere to go; Fawry/Meeza return a code to pay at an
     * outlet; wallets return a reference plus a QR to scan.
     */
    private function normalise_payment_data(array $data): array {
        $out = checkout_response::empty_payment_data();

        $redirect = (string) ($data['redirectTo'] ?? $data['redirectUrl'] ?? '');
        if ($redirect !== '') {
            $out['type'] = 'redirect';
            $out['redirect_url'] = $redirect;
            return $out;
        }

        $reference = (string) ($data['referenceNumber'] ?? $data['fawryCode'] ?? $data['meezaReference']
            ?? $data['systemReference'] ?? $data['aman_code'] ?? $data['masaryCode'] ?? '');
        if ($reference !== '') {
            $out['type'] = 'reference';
            $out['reference'] = $reference;
            $out['reference_expires_at'] = (string) ($data['expireDate'] ?? $data['expirationTime'] ?? '');
            $out['qr'] = (string) ($data['isoQr'] ?? '');
        }

        return $out;
    }

    /**
     * Display name of a method id, from the cached account list.
     */
    private function method_name(int $methodid): string {
        if ($methodid <= 0) {
            return '';
        }
        foreach ($this->get_payment_methods() as $method) {
            if ((int) $method['id'] === $methodid) {
                return current_language() === 'ar' && $method['name_ar'] !== ''
                    ? $method['name_ar'] : $method['name_en'];
            }
        }
        return '';
    }

    /**
     * Turn a non-2xx or non-success API answer into a checkout_response.
     *
     * The gateway's own validation message is folded into the error text —
     * without it a failure is untraceable from the site, and the reason is
     * nearly always a rejected field or a credential mismatch.
     *
     * @return checkout_response|null Null when the call succeeded.
     */
    private function check_api_error(array $result, string $what, int $transactionid): ?checkout_response {
        $body = is_array($result['body']) ? $result['body'] : [];

        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            $this->log('error', "Fawaterk {$what} failed", [
                'http_code' => $result['http_code'],
                'response_raw' => $this->truncate_for_log($result['raw'] ?? ''),
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
                'response_raw' => $this->truncate_for_log($result['raw'] ?? ''),
                'transaction_id' => $transactionid,
            ]);
            return checkout_response::failure('Fawaterk error: ' . $this->stringify_error($body), $body);
        }

        return null;
    }

    private function stringify_error(array $payload): string {
        if (!empty($payload['message'])) {
            $text = is_array($payload['message']) ? json_encode($payload['message']) : (string) $payload['message'];
        } else if (!empty($payload['errors'])) {
            $text = json_encode($payload['errors']);
        } else {
            $text = json_encode($payload);
        }

        // A rejected credential comes back as a "token"/"vendor" complaint —
        // and on v2 as HTTP 400, which reads like a bad request. Name the usual
        // cause, since sandbox and live are separate accounts.
        if (stripos($text, 'token') !== false || stripos($text, 'vendor') !== false) {
            $env = $this->is_sandbox() ? 'sandbox' : 'live';
            $credential = $this->uses_v3() ? 'the OAuth client id/secret' : 'the HASH API key';
            $text .= sprintf(
                ' — Fawaterk rejected the credentials. This provider is in %s mode and calls %s,'
                . ' so %s must belong to the %s account: sandbox and live are separate accounts'
                . ' with separate credentials. Run cli/fawaterk_diagnose.php to see which is in use.',
                $env, $this->get_api_base(), $credential, $env
            );
        }

        return $text;
    }

    // ─── Payment methods ────────────────────────────────────────────────────

    /**
     * The methods enabled on the account.
     *
     * Cached (see local_payments db/caches.php) so selecting a method does not
     * add an API round-trip to every checkout. Purge caches after enabling a new
     * method in the Fawaterk dashboard, or wait out the hour.
     */
    public function get_payment_methods(): array {
        $cache = \cache::make('local_payments', 'provider_payment_methods');
        $key = $this->plugin_name . ($this->is_sandbox() ? '_sandbox' : '_live')
            . ($this->uses_v3() ? '_v3' : '_v2');

        $cached = $cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $methods = $this->fetch_payment_methods();

        // Only cache a real answer — caching an empty list would keep the site
        // on the fallback for an hour after a transient API failure.
        if (!empty($methods)) {
            $cache->set($key, $methods);
        }

        return $methods;
    }

    private function fetch_payment_methods(): array {
        $url = $this->get_api_base()
            . ($this->uses_v3() ? '/api/v3/getTrPaymentmethods' : '/api/v2/getPaymentmethods');

        $result = $this->api_request('GET', $url);

        if ($result['http_code'] < 200 || $result['http_code'] >= 300
                || (($result['body']['status'] ?? '') !== 'success')) {
            $this->log('error', 'Fawaterk payment method listing failed', [
                'http_code' => $result['http_code'],
                'response_raw' => $this->truncate_for_log($result['raw'] ?? ''),
            ]);
            return [];
        }

        $methods = [];
        foreach (($result['body']['data'] ?? []) as $method) {
            // v3 calls it payment_method_id, v2 calls it paymentId.
            $id = (int) ($method['payment_method_id'] ?? $method['paymentId'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            // v3 reports whether the method is actually live for this account.
            if (isset($method['integration_status']) && (int) $method['integration_status'] !== 1) {
                continue;
            }
            $methods[] = [
                'id' => $id,
                'name_en' => (string) ($method['name_en'] ?? ''),
                'name_ar' => (string) ($method['name_ar'] ?? ''),
                'logo' => (string) ($method['logo'] ?? ''),
                // Sent as the string "true"/"false".
                'redirect' => filter_var($method['redirect'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $methods;
    }

    public function supports_payment_methods(): bool {
        return true;
    }

    // ─── Verification ───────────────────────────────────────────────────────

    /**
     * Read the authoritative state of a payment.
     *
     * $provider_reference is the v3 intent_key or the v2 invoice id, whichever
     * we stored as provider_session_id at checkout time.
     *
     * @return array|null Normalised {paid, status, amount, currency, method, txn_id}.
     */
    private function fetch_transaction(string $provider_reference): ?array {
        if ($provider_reference === '') {
            return null;
        }

        $base = $this->get_api_base();

        if ($this->uses_v3()) {
            $result = $this->api_request('POST', $base . '/api/v3/getTransactionData',
                ['intent_key' => $provider_reference]);
        } else {
            $result = $this->api_request('GET', $base . '/api/v2/getInvoiceData/' . $provider_reference);
        }

        if ($result['http_code'] < 200 || $result['http_code'] >= 300
                || (($result['body']['status'] ?? '') !== 'success')) {
            $this->log('error', 'Fawaterk transaction lookup failed', [
                'reference' => $provider_reference,
                'http_code' => $result['http_code'],
                'response_raw' => $this->truncate_for_log($result['raw'] ?? ''),
            ]);
            return null;
        }

        $data = $result['body']['data'] ?? [];

        return [
            'paid' => (int) ($data['paid'] ?? 0) === 1,
            'status' => (string) ($data['status_text'] ?? ($data['invoice_status'] ?? '')),
            'amount' => (float) ($data['total'] ?? 0),
            'currency' => (string) ($data['currency'] ?? ''),
            'method' => (string) ($data['payment_method'] ?? ''),
            'txn_id' => (string) ($data['transaction_id'] ?? $data['invoice_id'] ?? ''),
            'raw' => $data,
        ];
    }

    public function verify_payment(string $provider_reference): verification_result {
        $data = $this->fetch_transaction($provider_reference);

        if ($data === null) {
            return new verification_result([
                'verified' => false,
                'error_message' => 'Fawaterk transaction lookup failed for ' . $provider_reference,
            ]);
        }

        return new verification_result([
            'verified' => $data['paid'],
            'status' => $data['paid'] ? 'SUCCESS' : strtoupper($data['status'] ?: 'UNPAID'),
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'provider_txn_id' => $data['txn_id'],
            'provider_order_id' => $data['txn_id'],
            'payment_method_type' => $data['method'],
            'raw_response' => $data['raw'],
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

    // ─── Webhooks ───────────────────────────────────────────────────────────

    /**
     * Process a Fawaterk webhook.
     *
     * Fawaterk posts four different shapes, and the v3 and v2 generations name
     * their fields differently, so the event is inferred from what is present:
     *
     *  - status paid/pending (+ transaction_key) → payment, v3
     *  - invoice_status paid                     → payment, v2
     *  - errorMessage                            → failed attempt
     *  - status EXPIRED/CANCELED                 → reference expired
     *  - approvedAt                              → refund approved
     *
     * Every shape is signed HMAC-SHA256 with the HASH API key over a fixed
     * string; only the fields in that string differ.
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

        // Refund approved.
        if (isset($data['approvedAt']) || (isset($data['transactionId'], $data['amount'], $data['currency'])
                && !isset($data['referenceId']))) {
            return $this->webhook_refund($data, $meta, $merchantorderid);
        }

        // Reference expired or cancelled.
        if (in_array(strtoupper((string) ($data['status'] ?? '')), ['EXPIRED', 'CANCELED', 'CANCELLED'], true)) {
            return $this->webhook_cancelled($data, $meta, $merchantorderid);
        }

        // Failed attempt.
        if (isset($data['errorMessage'])) {
            return $this->webhook_failed($data, $meta, $merchantorderid);
        }

        return $this->webhook_payment($data, $meta, $merchantorderid);
    }

    /**
     * Paid or pending. v3 signs this one as `transactionHashKey`.
     */
    private function webhook_payment(array $data, array $meta, string $merchantorderid): webhook_result {
        $transactionkey = (string) ($data['transaction_key'] ?? '');
        $transactionid = (string) ($data['transaction_id'] ?? '');
        $method = (string) ($data['payment_method'] ?? '');

        $hash = (string) ($data['transactionHashKey'] ?? $data['hashKey'] ?? '');
        $valid = $this->check_hash($hash, $this->payment_string_to_sign($data));

        if (!$valid) {
            $this->log('warning', 'Fawaterk payment webhook signature check failed', [
                'transaction_key' => $transactionkey,
                'transaction_id' => $transactionid,
            ]);
            return new webhook_result([
                'signature_valid' => false,
                'processed' => false,
                'event_type' => 'pay',
                'merchant_order_id' => $merchantorderid,
                'provider_order_id' => $transactionid,
                'error_message' => 'hashKey verification failed',
            ]);
        }

        // v3 reports pending for async methods (a Fawry code issued but not yet
        // paid). That is not a failure and must not fail the order — acknowledge
        // it and wait for the paid callback.
        $status = strtolower((string) ($data['status'] ?? $data['invoice_status'] ?? ''));
        if ($status === 'pending') {
            return new webhook_result([
                'signature_valid' => true,
                'processed' => true,
                'event_type' => 'pending',
                'merchant_order_id' => $merchantorderid,
                'provider_order_id' => $transactionid,
                'order_reference' => $transactionkey,
                'status' => 'PENDING',
                'payment_method' => $method,
                'metadata' => $meta,
            ]);
        }

        // The signature covers only the ids and the method, not the amount — so
        // a captured webhook could be replayed with a different paidAmount. Read
        // the figures back from the API instead of trusting the body.
        $reference = $transactionkey !== '' ? $transactionkey : (string) ($data['invoice_id'] ?? '');
        $confirmed = $this->fetch_transaction($reference);

        if ($confirmed === null || !$confirmed['paid']) {
            $this->log('error', 'Fawaterk paid webhook not confirmed by the API', [
                'transaction_key' => $transactionkey,
                'reported_status' => $status,
            ]);
            return new webhook_result([
                'signature_valid' => true,
                'processed' => false,
                'event_type' => 'pay',
                'merchant_order_id' => $merchantorderid,
                'provider_order_id' => $transactionid,
                'error_message' => 'Transaction is not confirmed paid by the Fawaterk API',
            ]);
        }

        return new webhook_result([
            'signature_valid' => true,
            'processed' => true,
            'event_type' => 'pay',
            'merchant_order_id' => $merchantorderid,
            'provider_order_id' => $transactionid ?: $confirmed['txn_id'],
            'provider_txn_id' => $transactionid ?: $confirmed['txn_id'],
            'order_reference' => $transactionkey,
            'status' => 'SUCCESS',
            'amount' => $confirmed['amount'],
            'currency' => $confirmed['currency'],
            // getTransactionData reports the account's base-currency figure: an
            // order charged as USD 4.50 comes back as 240.435 EGP. The webhook
            // states what was actually paid, in the currency the buyer saw, so
            // carry both and let the manager match on either.
            'reported_amount' => (float) ($data['paidAmount'] ?? 0),
            'reported_currency' => (string) ($data['paidCurrency'] ?? ''),
            'payment_method' => $method ?: $confirmed['method'],
            'metadata' => $meta,
        ]);
    }

    private function webhook_failed(array $data, array $meta, string $merchantorderid): webhook_result {
        $response = $this->decode_payload_field($data['response'] ?? null);

        $valid = $this->check_hash((string) ($data['hashKey'] ?? ''), $this->payment_string_to_sign($data));
        if (!$valid) {
            $this->log('warning', 'Fawaterk failure webhook signature check failed', [
                'transaction_key' => $data['transaction_key'] ?? '',
            ]);
        }

        return new webhook_result([
            'signature_valid' => $valid,
            'processed' => $valid,
            'event_type' => 'pay',
            'merchant_order_id' => $merchantorderid,
            'provider_order_id' => (string) ($data['transaction_id'] ?? ''),
            'order_reference' => (string) ($data['transaction_key'] ?? ''),
            'status' => 'FAILED',
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => (string) ($data['paidCurrency'] ?? ''),
            'payment_method' => (string) ($data['payment_method'] ?? ''),
            'response_code' => (string) ($response['gatewayCode'] ?? ''),
            'response_message' => (string) ($data['errorMessage'] ?? ''),
            'metadata' => $meta,
        ]);
    }

    private function webhook_cancelled(array $data, array $meta, string $merchantorderid): webhook_result {
        $method = (string) ($data['paymentMethod'] ?? '');
        $referenceid = (string) ($data['referenceId'] ?? '');

        $valid = $this->check_hash((string) ($data['hashKey'] ?? ''),
            "referenceId={$referenceid}&PaymentMethod={$method}");

        if (!$valid) {
            $this->log('warning', 'Fawaterk cancellation webhook signature check failed', [
                'reference_id' => $referenceid,
            ]);
        }

        return new webhook_result([
            'signature_valid' => $valid,
            'processed' => $valid,
            'event_type' => 'pay',
            'merchant_order_id' => $merchantorderid,
            'provider_order_id' => (string) ($data['transactionId'] ?? ''),
            'order_reference' => (string) ($data['transactionKey'] ?? $referenceid),
            'status' => strtoupper((string) ($data['status'] ?? 'EXPIRED')),
            'payment_method' => $method,
            'response_message' => 'Payment reference ' . strtolower((string) ($data['status'] ?? 'expired')),
            'metadata' => $meta,
        ]);
    }

    private function webhook_refund(array $data, array $meta, string $merchantorderid): webhook_result {
        $txnid = (string) ($data['transactionId'] ?? '');
        $amount = (string) ($data['amount'] ?? '');
        $currency = (string) ($data['currency'] ?? '');

        $valid = $this->check_hash((string) ($data['hashKey'] ?? ''),
            "transactionId={$txnid}&amount={$amount}&currency={$currency}");

        if (!$valid) {
            $this->log('warning', 'Fawaterk refund webhook signature check failed', ['transaction_id' => $txnid]);
        }

        return new webhook_result([
            'signature_valid' => $valid,
            'processed' => $valid,
            'event_type' => 'refund',
            'merchant_order_id' => $merchantorderid,
            // The refund payload carries no intent key, so this id is the only
            // handle back to the transaction — the manager matches on it.
            'provider_order_id' => $txnid,
            'provider_txn_id' => $txnid,
            'status' => (string) ($data['status'] ?? ''),
            'amount' => (float) $amount,
            'currency' => $currency,
            'response_message' => (string) ($data['reason'] ?? ''),
            'metadata' => $meta,
        ]);
    }

    /**
     * Paid and failed webhooks sign the same three fields. v3 names them
     * TransactionId/TransactionKey; the older invoice payloads use
     * InvoiceId/InvoiceKey.
     */
    private function payment_string_to_sign(array $data): string {
        $method = (string) ($data['payment_method'] ?? '');

        if (isset($data['transaction_key'])) {
            return 'TransactionId=' . (string) ($data['transaction_id'] ?? '')
                . '&TransactionKey=' . (string) $data['transaction_key']
                . '&PaymentMethod=' . $method;
        }

        return 'InvoiceId=' . (string) ($data['invoice_id'] ?? '')
            . '&InvoiceKey=' . (string) ($data['invoice_key'] ?? '')
            . '&PaymentMethod=' . $method;
    }

    /**
     * Constant-time HMAC-SHA256 comparison against the HASH API key.
     */
    private function check_hash(string $received, string $stringtosign): bool {
        if ($received === '') {
            return false;
        }
        $vendorkey = $this->get_vendor_key();
        if ($vendorkey === '') {
            $this->log('error', 'Fawaterk HASH API key is not configured; webhooks cannot be verified');
            return false;
        }
        return hash_equals(hash_hmac('sha256', $stringtosign, $vendorkey, false), $received);
    }

    /**
     * pay_load comes back as an object on some events and a JSON string on
     * others; `response` on the failure webhook is a JSON string too.
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

    // ─── Refunds ────────────────────────────────────────────────────────────

    /**
     * POST /api/v3/refund/create.
     *
     * refund_type 3 is "Integration transaction" — what createTransaction makes.
     * $provider_order_id is Fawaterk's numeric transaction id, which we record
     * from the paid webhook.
     */
    public function refund(string $provider_order_id, float $amount, string $currency, string $reason = ''): refund_result {
        if (!$this->uses_v3()) {
            return new refund_result([
                'success' => false,
                'amount' => $amount,
                'currency' => $currency,
                'error_message' => 'Refunds need the v3 API. Switch the authentication method to '
                    . 'OAuth, or raise the refund in the Fawaterk dashboard.',
            ]);
        }

        $body = [
            'refund_type' => '3',
            'refund_id' => (int) $provider_order_id,
            'reason' => $reason ?: 'Customer requested refund',
            'refundable_amount' => round($amount, 2),
            'comment' => 'Refund raised from Moodle',
        ];

        $this->log('info', 'Requesting Fawaterk refund', [
            'provider_order_id' => $provider_order_id,
            'amount' => $amount,
        ]);

        $result = $this->api_request('POST', $this->get_api_base() . '/api/v3/refund/create', $body);
        $payload = is_array($result['body']) ? $result['body'] : [];
        $success = ($result['http_code'] >= 200 && $result['http_code'] < 300)
            && (($payload['status'] ?? '') === 'success');

        if (!$success) {
            $this->log('error', 'Fawaterk refund request failed', [
                'provider_order_id' => $provider_order_id,
                'http_code' => $result['http_code'],
                'response' => $payload,
            ]);
        }

        return new refund_result([
            'success' => $success,
            'status' => $success ? 'requested' : 'failed',
            'amount' => $amount,
            'currency' => $currency,
            'error_message' => $success ? '' : ('Refund failed: ' . $this->stringify_error($payload)),
            'raw_response' => $payload,
        ]);
    }

    /**
     * Fawaterk has no void — an unpaid transaction simply expires.
     */
    public function void_payment(string $provider_order_id, string $reason = ''): refund_result {
        return new refund_result([
            'success' => false,
            'error_message' => 'Fawaterk does not support voiding a transaction; an unpaid one expires on its own.',
        ]);
    }

    public function supports_refund(): bool {
        // Refunds are a v3 feature; v2 has no refund endpoint.
        return $this->uses_v3();
    }

    public function supports_void(): bool {
        return false;
    }

    /**
     * Fawaterk reports in EGP whatever the order was charged in.
     *
     * Not a setting — it is documented behaviour of the API. getTransactionData
     * describes `total` as "Transaction total converted to EGP" and `currency`
     * as "Returned currency is EGP after conversion". A 4.50 USD order is
     * presented to the buyer as "Pay USD 4.50" and reported back as 240.44 EGP;
     * the two are the same payment, expressed differently.
     */
    public function reports_normalised_amounts(): bool {
        return true;
    }

    /**
     * Currencies Fawaterk may be offered for.
     *
     * The buyer is charged in the order's currency, so multi-currency pricing
     * works; only the reporting is normalised. Narrow this list if an account
     * genuinely cannot take one of them.
     */
    public function supported_currencies(): array {
        $configured = (string) $this->get_setting('currencies', 'EGP,USD,SAR,AED');
        $list = array_filter(array_map(
            static function ($code) {
                return strtoupper(trim($code));
            },
            explode(',', $configured)
        ));
        return !empty($list) ? array_values($list) : ['EGP'];
    }

    public function supported_countries(): array {
        return ['EG', 'SA', 'AE'];
    }
}
