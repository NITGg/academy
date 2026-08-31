<?php
namespace local_payments;

use local_payments\provider\provider_interface;
use local_payments\provider\payment_request;
use local_payments\provider\checkout_response;
use local_payments\provider\webhook_result;

defined('MOODLE_INTERNAL') || die();

class manager {

    /**
     * Get the best provider for a given country and currency.
     *
     * @param string $country ISO 3166-1 alpha-2
     * @param string $currency ISO 4217
     * @return provider_interface
     * @throws \moodle_exception if no suitable provider found.
     */
    public static function get_provider(string $country, string $currency): provider_interface {
        global $DB;

        $providers = $DB->get_records('local_payments_providers', ['enabled' => 1], 'priority ASC');

        foreach ($providers as $provider_record) {
            $provider = self::instantiate_provider($provider_record);
            if (self::provider_supports($provider, $country, $currency)) {
                return $provider;
            }
        }

        throw new \moodle_exception('noproviderfound', 'local_payments', '', null,
            "No enabled payment provider for country={$country}, currency={$currency}");
    }

    /**
     * Get a provider by name.
     */
    public static function get_provider_by_name(string $name): provider_interface {
        global $DB;
        $record = $DB->get_record('local_payments_providers', ['name' => $name, 'enabled' => 1], '*', MUST_EXIST);
        return self::instantiate_provider($record);
    }

    /**
     * Get a provider by its DB record ID.
     */
    public static function get_provider_by_id(int $id): provider_interface {
        global $DB;
        $record = $DB->get_record('local_payments_providers', ['id' => $id], '*', MUST_EXIST);
        return self::instantiate_provider($record);
    }

    /**
     * Can this gateway take this country and currency?
     *
     * The gateway is the only authority. Each provider also has
     * supported_countries / supported_currencies columns on its database row,
     * seeded at install and with no screen to edit them — so consulting those as
     * well meant a stale row could silently veto a currency the plugin settings
     * allowed, with nothing in the interface to explain why. The row is now
     * display and seed data only; what a gateway will accept is configured in
     * that provider's own settings.
     *
     * An empty list from a gateway means no restriction.
     *
     * @param provider_interface $provider
     * @param string $country ISO 3166-1 alpha-2
     * @param string $currency ISO 4217
     * @return bool
     */
    public static function provider_supports(provider_interface $provider, string $country,
            string $currency): bool {

        $countries = $provider->supported_countries();
        if (!empty($countries) && $country !== '' && !in_array($country, $countries)) {
            return false;
        }

        $currencies = $provider->supported_currencies();
        if (!empty($currencies) && !in_array($currency, $currencies)) {
            return false;
        }

        return true;
    }

    private static function instantiate_provider(\stdClass $record): provider_interface {
        $class = "\\{$record->plugin_name}\\gateway";
        if (!class_exists($class)) {
            throw new \coding_exception("Provider class {$class} not found for plugin {$record->plugin_name}");
        }
        return new $class($record);
    }

    /**
     * Create a payment checkout.
     *
     * @param int $courseid
     * @param int|null $userid
     * @param string|null $app_country
     * @param string $display_lang
     * @param string $coupon_code
     * @param int $payment_method_id Provider payment method to charge directly (0 = hosted picker).
     * @return object {order_id, checkout_url, expires_at, provider, transaction_id, payment_data}
     */
    public static function create_checkout(int $courseid, ?int $userid = null, ?string $app_country = null,
            string $display_lang = 'en', string $coupon_code = '', int $payment_method_id = 0): object {
        global $DB, $USER, $CFG;

        $userid = $userid ?? $USER->id;
        $user = $DB->get_record('user', ['id' => $userid], 'id, email, firstname, lastname, country', MUST_EXIST);

        // Resolve price.
        $pricing = price_resolver::resolve($courseid, $userid, $app_country);

        // Apply NIT commerce discount (auto offer + optional coupon code) on the resolved price.
        $disc = self::apply_nit_discount('course', $courseid, $userid, (float) $pricing->price, $coupon_code);
        $amount = $disc['amount'];
        $discountmeta = $disc['discount'];

        // Check for duplicate pending payment.
        $existing = $DB->get_record_select(
            'local_payments_transactions',
            'userid = :userid AND courseid = :courseid AND status = :status AND expires_at > :now',
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'status' => status_machine::PENDING,
                'now' => time(),
            ],
            '*',
            IGNORE_MULTIPLE
        );

        if ($existing) {
            $existingmeta = json_decode($existing->metadata ?? '{}', true) ?: [];
            $samemethod = ((int) ($existingmeta['payment_method_id'] ?? 0) === $payment_method_id);

            // Reuse the pending gateway session ONLY if it was created for the same price AND the
            // same payment method. If a coupon/offer now makes the price different, the old session
            // still shows the OLD amount on the gateway screen; and a session opened for one method
            // (say a Fawry code) is useless to a buyer who has now chosen a card. Either way, retire
            // it (freeing any coupon reservation) and fall through to create a fresh one.
            if ($samemethod && abs((float) $existing->amount - $amount) < 0.01
                    && (!empty($existing->checkout_url) || !empty($existingmeta['payment_data']['reference']))) {
                return (object) [
                    'order_id' => $existing->order_id,
                    'checkout_url' => $existing->checkout_url,
                    'expires_at' => (int) $existing->expires_at,
                    'provider' => $DB->get_field('local_payments_providers', 'name', ['id' => $existing->provider_id]),
                    'transaction_id' => (int) $existing->id,
                    'amount' => (float) $existing->amount,
                    'original_amount' => (float) ($existing->original_amount ?? $existing->amount),
                    'currency' => $existing->currency,
                    'payment_data' => $existingmeta['payment_data']
                        ?? \local_payments\provider\checkout_response::empty_payment_data(),
                ];
            }

            if (empty($existing->checkout_url) && empty($existingmeta['payment_data']['reference'])) {
                // Never got as far as a usable session — nothing to supersede.
                $existing = null;
            }
        }

        if ($existing) {
            $DB->update_record('local_payments_transactions', (object) [
                'id' => $existing->id,
                'status' => status_machine::EXPIRED,
                'reject_reason' => 'Superseded by a new checkout at a different price or payment method',
                'timemodified' => time(),
            ]);
            self::release_nit_discount((int) $existing->id);
            self::audit_log($existing->id, $userid, 'status_changed', status_machine::PENDING, status_machine::EXPIRED);
        }

        // Check already purchased.
        if (price_resolver::is_purchased($courseid, $userid)) {
            throw new \moodle_exception('alreadypurchased', 'local_payments');
        }

        // Check already enrolled.
        if (enrollment_handler::is_enrolled($userid, $courseid)) {
            throw new \moodle_exception('alreadyenrolled', 'local_payments');
        }

        // Select provider.
        $provider = self::get_provider($pricing->country, $pricing->currency);
        $provider_record = $DB->get_record('local_payments_providers', ['name' => $provider->get_name()]);

        // Generate order ID and idempotency key.
        $order_id = self::generate_order_id();
        $idempotency_key = self::generate_idempotency_key($userid, $courseid);

        $ttl = (int) get_config('local_payments', 'payment_ttl') ?: 1800; // 30 min default.
        $expires_at = time() + $ttl;

        // Create transaction record.
        $transaction = (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'provider_id' => $provider_record->id,
            'price_id' => $pricing->price_id,
            'order_id' => $order_id,
            'idempotency_key' => $idempotency_key,
            'amount' => $amount,
            'original_amount' => $pricing->original_price,
            'currency' => $pricing->currency,
            'status' => status_machine::PENDING,
            'customer_email' => $user->email,
            'customer_reference' => (string) $userid,
            'display_lang' => $display_lang,
            'country' => $pricing->country,
            'ip_address' => getremoteaddr(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'metadata' => json_encode([
                'pricing' => $pricing,
                'course_name' => $DB->get_field('course', 'fullname', ['id' => $courseid]),
                'item_type' => 'course',
                'item_id' => $courseid,
                'discount' => $discountmeta,
                'coupon_code' => $coupon_code,
                'payment_method_id' => $payment_method_id,
            ]),
            'expires_at' => $expires_at,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $transaction_id = $DB->insert_record('local_payments_transactions', $transaction);

        // Audit log.
        self::audit_log($transaction_id, $userid, 'payment_created', '', status_machine::PENDING);

        // Build webhook URL.
        $webhook_url = $CFG->wwwroot . '/local/payments/webhook.php?provider=' . $provider->get_name();
        $success_url = $CFG->wwwroot . '/local/payments/callback.php?order_id=' . urlencode($order_id);
        $failure_url = $CFG->wwwroot . '/local/payments/callback.php?order_id=' . urlencode($order_id) . '&status=failed';

        // Initialize payment with provider.
        $request = new payment_request([
            'order_id' => $order_id,
            'amount' => $amount,
            'currency' => $pricing->currency,
            'description' => get_string('paymentfor', 'local_payments',
                $DB->get_field('course', 'fullname', ['id' => $courseid])),
            'userid' => $userid,
            'courseid' => $courseid,
            'customer_email' => $user->email,
            'customer_reference' => (string) $userid,
            'display_lang' => $display_lang,
            'webhook_url' => $webhook_url,
            'success_url' => $success_url,
            'failure_url' => $failure_url,
            'metadata' => ['transaction_id' => $transaction_id, 'courseid' => $courseid],
            'transaction_id' => $transaction_id,
            'payment_method_id' => $payment_method_id,
        ]);

        $response = $provider->initialize_payment($request);

        if (!$response->success) {
            // Mark transaction as failed.
            $DB->update_record('local_payments_transactions', (object) [
                'id' => $transaction_id,
                'status' => status_machine::FAILED,
                'reject_reason' => substr($response->error_message, 0, 255),
                'timemodified' => time(),
            ]);
            self::audit_log($transaction_id, $userid, 'status_changed', status_machine::PENDING, status_machine::FAILED);

            throw new \moodle_exception('paymentinitiationfailed', 'local_payments', '', $response->error_message);
        }

        // Update transaction with provider session info. payment_data is kept in
        // metadata so a reference code (Fawry/Meeza) can be shown again later —
        // the buyer pays it hours after leaving the checkout screen.
        $storedmeta = json_decode($transaction->metadata, true) ?: [];
        $storedmeta['payment_data'] = $response->payment_data;
        $expires_at = self::resolve_expiry($response->payment_data, $provider_record->plugin_name, $expires_at);
        $DB->update_record('local_payments_transactions', (object) [
            'id' => $transaction_id,
            'provider_session_id' => $response->provider_session_id,
            'checkout_url' => $response->checkout_url,
            'metadata' => json_encode($storedmeta),
            'expires_at' => $expires_at,
            'timemodified' => time(),
        ]);

        // Reserve coupon/offer usage for this pending checkout so a capped coupon
        // can't be over-redeemed by concurrent checkouts. Released on failure /
        // abandonment (callback + cleanup task); confirmed as-is at fulfilment.
        self::reserve_nit_discount($discountmeta, $userid, $transaction_id, 'course', $courseid);

        return (object) [
            'order_id' => $order_id,
            'checkout_url' => $response->checkout_url,
            'expires_at' => $expires_at,
            'provider' => $provider->get_name(),
            'transaction_id' => $transaction_id,
            'amount' => (float) $amount,
            'original_amount' => (float) $pricing->original_price,
            'currency' => $pricing->currency,
            'payment_data' => $response->payment_data,
        ];
    }

    /**
     * Create a Kashier checkout for a NIT subscription (item_type=subscription, courseid sentinel 0).
     *
     * The transaction is fulfilled on payment success by {@see self::fulfil_subscription()} — it
     * creates a subscription purchase (local_nit_subscriptions) which grants live course access.
     *
     * @param int $subscriptionid
     * @param int|null $userid
     * @param string|null $app_country
     * @param string $display_lang
     * @param string $type normal | b2b
     * @param int $seats B2B seat capacity
     * @param string $coupon_code optional coupon entered at checkout
     * @param string $return_url page the checkout was launched from
     * @return object {order_id, checkout_url, expires_at, provider, transaction_id}
     */
    public static function create_subscription_checkout(int $subscriptionid, ?int $userid = null,
            ?string $app_country = null, string $display_lang = 'en', string $type = 'normal',
            int $seats = 0, string $coupon_code = '', string $return_url = '',
            int $payment_method_id = 0): object {
        global $DB, $USER, $CFG;

        $userid = $userid ?? $USER->id;
        $user = $DB->get_record('user', ['id' => $userid], 'id, email, firstname, lastname, country', MUST_EXIST);

        // Same rule as a course: a signed-in account with no profile country has no price, so
        // there is nothing to charge. Checked here as well as in the resolver because the
        // standalone branch below (local_nit_subscriptions absent) would otherwise fall back
        // to the plan's base price and quote a country nobody chose.
        if (country_detector::pricing_blocked($userid)) {
            throw new country_required_exception("Subscription {$subscriptionid}: user {$userid} has no profile country");
        }

        $sub = $DB->get_record('nit_subscription', ['id' => $subscriptionid], '*', MUST_EXIST);
        // The plan must be active. The public block only lists active plans, but
        // this endpoint accepts any id, so guard against buying a
        // deactivated/discontinued plan by supplying its id directly.
        if (($sub->status ?? '') !== 'active') {
            throw new \moodle_exception('error', 'moodle', '', null, 'This subscription plan is not available');
        }

        // Resolve the buyer's country-based base price + currency (falls back to the plan's
        // default price/currency when their country has no override — see subscription_manager).
        $basePrice = (float) $sub->price;
        $currency = (string) ($sub->currency ?? 'EGP');
        $country = $app_country ?: ($user->country ?: 'EG');
        if (class_exists('\local_nit_subscriptions\subscription_manager')) {
            $resolved = \local_nit_subscriptions\subscription_manager::resolve_price($subscriptionid, $userid, $app_country);
            $basePrice = (float) $resolved->price;
            $currency = (string) $resolved->currency;
            $country = (string) $resolved->country;
        }

        $isb2b = ($type === 'b2b');
        $b2bseats = 0;
        $amount = $basePrice;
        $discountmeta = null;

        if ($isb2b) {
            if (empty($sub->b2b_enabled)) {
                throw new \moodle_exception('error', 'moodle', '', null, 'This subscription is not available for B2B purchase');
            }
            $option = $DB->get_record('nit_sub_seat_option', ['subscriptionid' => $sub->id, 'seats' => (int) $seats]);
            if (!$option) {
                throw new \moodle_exception('error', 'moodle', '', null, 'The selected capacity is not available');
            }
            $b2bseats = (int) $seats;
            if (class_exists('\local_nit_subscriptions\subscription_manager')) {
                $price = \local_nit_subscriptions\subscription_manager::b2b_price($basePrice, $b2bseats, $option->discount_percent);
                $amount = (float) $price['final'];
            }
        } else {
            // A user may hold only one active NORMAL subscription at a time.
            if (class_exists('\local_nit_subscriptions\subscription_purchase_manager')
                    && \local_nit_subscriptions\subscription_purchase_manager::has_active_normal($userid)) {
                throw new \moodle_exception('error', 'moodle', '', null, 'You already have an active subscription');
            }
            // Apply coupon/offer (normal purchase only) on the resolved base price.
            $disc = self::apply_nit_discount('subscription', $subscriptionid, $userid, $basePrice, $coupon_code);
            $amount = $disc['amount'];
            $discountmeta = $disc['discount'];
        }

        $originalamount = $basePrice;

        $provider = self::get_provider($country, $currency);
        $provider_record = $DB->get_record('local_payments_providers', ['name' => $provider->get_name()]);

        $order_id = self::generate_order_id();
        $idempotency_key = self::generate_idempotency_key($userid, $subscriptionid + 2000000);

        $ttl = (int) get_config('local_payments', 'payment_ttl') ?: 1800;
        $expires_at = time() + $ttl;

        $transaction = (object) [
            'userid' => $userid,
            'courseid' => 0, // Sentinel: subscription transactions are not tied to a course.
            'provider_id' => $provider_record->id,
            'price_id' => null,
            'order_id' => $order_id,
            'idempotency_key' => $idempotency_key,
            'amount' => $amount,
            'original_amount' => $originalamount,
            'currency' => $currency,
            'status' => status_machine::PENDING,
            'customer_email' => $user->email,
            'customer_reference' => (string) $userid,
            'display_lang' => $display_lang,
            'country' => $country,
            'ip_address' => getremoteaddr(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'metadata' => json_encode([
                'item_type' => 'subscription',
                'item_id' => $subscriptionid,
                'subscription_name' => $sub->name,
                'sub_type' => $isb2b ? 'b2b' : 'normal',
                'seats' => $b2bseats,
                'discount' => $discountmeta,
                'coupon_code' => $coupon_code,
                'return_url' => $return_url,
                'payment_method_id' => $payment_method_id,
            ]),
            'expires_at' => $expires_at,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $transaction_id = $DB->insert_record('local_payments_transactions', $transaction);
        self::audit_log($transaction_id, $userid, 'payment_created', '', status_machine::PENDING);

        $webhook_url = $CFG->wwwroot . '/local/payments/webhook.php?provider=' . $provider->get_name();
        $success_url = $CFG->wwwroot . '/local/payments/callback.php?order_id=' . urlencode($order_id);
        $failure_url = $CFG->wwwroot . '/local/payments/callback.php?order_id=' . urlencode($order_id) . '&status=failed';

        $request = new payment_request([
            'order_id' => $order_id,
            'amount' => $amount,
            'currency' => $currency,
            'description' => 'Subscription: ' . format_string($sub->name),
            'userid' => $userid,
            'courseid' => 0,
            'customer_email' => $user->email,
            'customer_reference' => (string) $userid,
            'display_lang' => $display_lang,
            'webhook_url' => $webhook_url,
            'success_url' => $success_url,
            'failure_url' => $failure_url,
            'metadata' => ['transaction_id' => $transaction_id],
            'transaction_id' => $transaction_id,
            'payment_method_id' => $payment_method_id,
        ]);

        $response = $provider->initialize_payment($request);

        if (!$response->success) {
            $DB->update_record('local_payments_transactions', (object) [
                'id' => $transaction_id,
                'status' => status_machine::FAILED,
                'reject_reason' => substr($response->error_message, 0, 255),
                'timemodified' => time(),
            ]);
            self::audit_log($transaction_id, $userid, 'status_changed', status_machine::PENDING, status_machine::FAILED);
            throw new \moodle_exception('paymentinitiationfailed', 'local_payments', '', $response->error_message);
        }

        $storedmeta = json_decode($transaction->metadata, true) ?: [];
        $storedmeta['payment_data'] = $response->payment_data;
        $expires_at = self::resolve_expiry($response->payment_data, $provider_record->plugin_name, $expires_at);
        $DB->update_record('local_payments_transactions', (object) [
            'id' => $transaction_id,
            'provider_session_id' => $response->provider_session_id,
            'checkout_url' => $response->checkout_url,
            'metadata' => json_encode($storedmeta),
            'expires_at' => $expires_at,
            'timemodified' => time(),
        ]);

        // Reserve coupon/offer usage for this pending subscription checkout (see
        // create_checkout). Fails the checkout cleanly if a capped coupon is now used up.
        self::reserve_nit_discount($discountmeta, $userid, $transaction_id, 'subscription', $subscriptionid);

        return (object) [
            'order_id' => $order_id,
            'checkout_url' => $response->checkout_url,
            'expires_at' => $expires_at,
            'provider' => $provider->get_name(),
            'transaction_id' => $transaction_id,
            // The actual charged (post-discount) amount, so the app can display the correct price
            // even if it renders its own summary/gateway screen instead of opening checkout_url.
            'amount' => (float) $amount,
            'original_amount' => (float) $originalamount,
            'currency' => $currency,
            'payment_data' => $response->payment_data,
        ];
    }

    /**
     * Decide whether a gateway's figures confirm the amount we quoted.
     *
     * There are three ways a payment can legitimately be reported:
     *  - exactly what we asked for;
     *  - the same value stated a second way (some gateways send the charged
     *    figure on the webhook and a base-currency figure over the API);
     *  - normalised into the gateway's own settlement currency, which cannot be
     *    compared to our order at all.
     *
     * That last case is not a hole. The amount is fixed when we create the
     * gateway session — the buyer can only pay what we put on it — and by the
     * time this runs the gateway has already confirmed *that session* as paid,
     * against credentials only we hold. The amount is guaranteed by creation
     * rather than by reporting, so re-checking a converted figure would prove
     * nothing. It is still only accepted from a provider that declares it
     * normalises, so a genuinely wrong amount from a normal provider fails.
     *
     * The first two comparisons are against the amount we already expect, so a
     * tampered figure can make the check fail but never pass for a wrong value.
     *
     * @return array {ok: bool, converted: bool, message: string}
     */
    private static function verify_amount(\stdClass $transaction, provider_interface $provider,
            float $amount, string $currency, float $reported_amount = 0,
            string $reported_currency = ''): array {

        $expected = (float) $transaction->amount;
        $expectedcurrency = (string) $transaction->currency;

        $matches = static function (float $value, string $code) use ($expected, $expectedcurrency): bool {
            if (abs($expected - $value) > 0.01) {
                return false;
            }
            // Currency is only enforced when the result carries one, so a
            // provider that omits it cannot break payments; a right-number,
            // wrong-currency message is still rejected.
            return $code === '' || strcasecmp($code, $expectedcurrency) === 0;
        };

        if ($matches($amount, $currency) || ($reported_amount > 0 && $matches($reported_amount, $reported_currency))) {
            return ['ok' => true, 'converted' => false, 'message' => ''];
        }

        $differentcurrency = $currency !== '' && strcasecmp($currency, $expectedcurrency) !== 0;
        if ($differentcurrency && $amount > 0 && $provider->reports_normalised_amounts()) {
            return [
                'ok' => true,
                'converted' => true,
                'message' => "Order of {$expected} {$expectedcurrency} confirmed paid; the gateway "
                    . "reports it normalised as {$amount} {$currency}",
            ];
        }

        $message = "Amount mismatch: expected {$expected} {$expectedcurrency}; "
            . "gateway reported {$amount} {$currency}";
        if ($reported_amount > 0) {
            $message .= " and {$reported_amount} {$reported_currency}";
        }

        return ['ok' => false, 'converted' => false, 'message' => $message];
    }

    /**
     * Note the figure the gateway reported, when it differs from the quote.
     * The order keeps the price the buyer agreed to; the gateway's own number is
     * recorded beside it so reconciliation against their statements has
     * something to work from.
     */
    private static function record_settlement(\stdClass $transaction, float $amount, string $currency): void {
        global $DB;

        $meta = json_decode($transaction->metadata ?? '{}', true) ?: [];
        $meta['settled'] = ['amount' => $amount, 'currency' => $currency];

        $DB->set_field('local_payments_transactions', 'metadata', json_encode($meta),
            ['id' => $transaction->id]);
    }

    /**
     * How long an order must stay open, given how the buyer was told to pay.
     *
     * A card payment happens in the next few minutes, so the normal checkout TTL
     * is right. An offline reference code (Fawry, Meeza) is typically paid the
     * next day — if the order has expired by then the gateway's confirmation
     * arrives against a dead transaction. Prefer the expiry the gateway itself
     * put on the code; fall back to the provider's configured window.
     *
     * @param array $payment_data checkout_response::$payment_data
     * @param string $plugin_name Provider plugin, for its reference_ttl_days setting.
     * @param int $default_expires_at The normal expiry, used when nothing applies.
     * @return int Unix timestamp.
     */
    private static function resolve_expiry(array $payment_data, string $plugin_name,
            int $default_expires_at): int {
        if (($payment_data['type'] ?? '') !== 'reference') {
            return $default_expires_at;
        }

        $stated = trim((string) ($payment_data['reference_expires_at'] ?? ''));
        if ($stated !== '') {
            $ts = strtotime($stated);
            if ($ts !== false && $ts > time()) {
                // Give the webhook a little room after the code itself dies.
                return $ts + HOURSECS;
            }
        }

        $days = (int) get_config($plugin_name, 'reference_ttl_days');
        if ($days > 0) {
            return time() + ($days * DAYSECS);
        }

        return $default_expires_at;
    }

    /**
     * List the payment methods offered by the provider that would handle a
     * purchase for this country/currency.
     *
     * @param string $country ISO 3166-1 alpha-2
     * @param string $currency ISO 4217
     * @return object {provider, supports_payment_methods, methods[]}
     */
    public static function get_provider_payment_methods(string $country, string $currency): object {
        $provider = self::get_provider($country, $currency);

        if (!$provider->supports_payment_methods()) {
            // Hosted-picker providers (Kashier): the buyer chooses on the gateway
            // page, so there is nothing for the app to render.
            return (object) [
                'provider' => $provider->get_name(),
                'supports_payment_methods' => false,
                'methods' => [],
            ];
        }

        return (object) [
            'provider' => $provider->get_name(),
            'supports_payment_methods' => true,
            'methods' => $provider->get_payment_methods(),
        ];
    }

    /**
     * Resolve the charged amount for a NIT commerce discount (coupon/offer), for checkout.
     *
     * @param string $item_type course | package | subscription
     * @param int $item_id
     * @param int $userid
     * @param float $base base price before discount
     * @param string $coupon_code
     * @return array {amount: float, discount: array|null}
     */
    private static function apply_nit_discount(string $item_type, int $item_id, int $userid,
            float $base, string $coupon_code): array {
        if (!class_exists('\local_nit_commerce\discount_manager')) {
            return ['amount' => $base, 'discount' => null];
        }
        $resolved = \local_nit_commerce\discount_manager::resolve($item_type, $item_id, $userid, $coupon_code, $base);
        return [
            'amount' => (float) $resolved['final'],
            'discount' => [
                'original'        => $resolved['original'],
                'offers'          => $resolved['offers'] ?? [],
                'coupon_id'       => $resolved['coupon_id'] ?? 0,
                'coupon_code'     => $resolved['coupon_code'] ?? '',
                'coupon_discount' => $resolved['coupon_discount'] ?? 0,
                'offer_discount'  => $resolved['offer_discount'] ?? 0,
                'discount'        => $resolved['discount'] ?? 0,
                'final'           => $resolved['final'],
            ],
        ];
    }

    /**
     * Fulfil a completed subscription transaction: create the purchase (grants live course access) and
     * record coupon/offer usage. Safe to call more than once (fulfilment is idempotent by order id).
     *
     * @param \stdClass $transaction
     * @param \stdClass $meta decoded transaction metadata
     * @return void
     */
    private static function fulfil_subscription(\stdClass $transaction, \stdClass $meta): void {
        try {
            if (class_exists('\local_nit_subscriptions\subscription_purchase_manager')) {
                \local_nit_subscriptions\subscription_purchase_manager::fulfil_from_gateway(
                    (int) $transaction->userid,
                    (int) ($meta->item_id ?? 0),
                    (float) $transaction->amount,
                    (string) $transaction->order_id,
                    $meta->sub_type ?? 'normal',
                    (int) ($meta->seats ?? 0)
                );
                self::audit_log($transaction->id, $transaction->userid, 'subscription_purchased', '',
                    (string) ($meta->item_id ?? 0));
            }
        } catch (\Exception $e) {
            self::log_entry($transaction->provider_id, $transaction->id, 'error',
                'Subscription fulfilment failed: ' . $e->getMessage());
        }

        // Record coupon/offer usage (idempotent by transaction id).
        self::record_nit_discount($transaction, $meta, 'subscription', (int) ($meta->item_id ?? 0));
    }

    /**
     * Record NIT commerce coupon/offer usage for a fulfilled transaction (idempotent by transaction).
     *
     * @param \stdClass $transaction
     * @param \stdClass|null $meta decoded transaction metadata (may carry ->discount)
     * @param string $itemtype course | package | subscription
     * @param int $itemid
     * @return void
     */
    private static function record_nit_discount(\stdClass $transaction, $meta, string $itemtype, int $itemid): void {
        if (!isset($meta->discount) || !$meta->discount || !class_exists('\local_nit_commerce\discount_manager')) {
            return;
        }
        try {
            \local_nit_commerce\discount_manager::record_usage(
                json_decode(json_encode($meta->discount), true),
                (int) $transaction->userid,
                (int) $transaction->id,
                $itemtype,
                $itemid
            );
        } catch (\Exception $e) {
            self::log_entry($transaction->provider_id, $transaction->id, 'warning',
                'Discount usage record failed: ' . $e->getMessage());
        }
    }

    /**
     * Reserve NIT commerce coupon/offer usage for a pending checkout. If the
     * coupon's limit is reached during reservation, this fails the checkout
     * cleanly (marks the transaction FAILED and rethrows) so a capped coupon is
     * never over-redeemed by concurrent checkouts.
     *
     * @param array|null $discount stored discount metadata (from apply_nit_discount)
     * @param int $userid
     * @param int $transactionid
     * @param string $itemtype
     * @param int $itemid
     * @return void
     */
    private static function reserve_nit_discount($discount, int $userid, int $transactionid,
            string $itemtype, int $itemid): void {
        if (empty($discount) || !is_array($discount) || !class_exists('\local_nit_commerce\discount_manager')) {
            return;
        }
        try {
            \local_nit_commerce\discount_manager::reserve_usage($discount, $userid, $transactionid, $itemtype, $itemid);
        } catch (\moodle_exception $e) {
            global $DB;
            $DB->update_record('local_payments_transactions', (object) [
                'id' => $transactionid,
                'status' => status_machine::FAILED,
                'reject_reason' => substr($e->getMessage(), 0, 255),
                'timemodified' => time(),
            ]);
            self::audit_log($transactionid, $userid, 'status_changed', status_machine::PENDING, status_machine::FAILED);
            throw $e;
        }
    }

    /**
     * Release any coupon/offer reservation held by a transaction whose payment
     * failed or was abandoned, freeing a capped coupon for others.
     *
     * @param int $transactionid
     * @return void
     */
    private static function release_nit_discount(int $transactionid): void {
        if ($transactionid > 0 && class_exists('\local_nit_commerce\discount_manager')) {
            \local_nit_commerce\discount_manager::release_usage($transactionid);
        }
    }

    /**
     * Process a webhook from a payment provider.
     */
    public static function process_webhook(string $provider_name, string $payload, array $headers): bool {
        global $DB;

        $provider_record = $DB->get_record('local_payments_providers', ['name' => $provider_name]);
        if (!$provider_record) {
            return false;
        }

        $provider = self::instantiate_provider($provider_record);
        $result = $provider->handle_webhook($payload, $headers);

        // Store webhook record.
        $webhook_id = $DB->insert_record('local_payments_webhooks', (object) [
            'provider_id' => $provider_record->id,
            'event_type' => $result->event_type,
            'merchant_order_id' => $result->merchant_order_id,
            'provider_order_id' => $result->provider_order_id,
            'order_reference' => $result->order_reference,
            'payment_method' => $result->payment_method,
            'amount' => $result->amount,
            'currency' => $result->currency,
            'payload' => $payload,
            'headers' => json_encode($headers),
            'card_info' => !empty($result->card_info) ? json_encode($result->card_info) : null,
            'source_of_funds' => !empty($result->source_of_funds) ? json_encode($result->source_of_funds) : null,
            'channel' => $result->channel,
            'signature_keys' => !empty($result->signature_keys) ? json_encode($result->signature_keys) : null,
            'signature_valid' => $result->signature_valid ? 1 : 0,
            'status' => 'received',
            'timecreated' => time(),
        ]);

        if (!$result->signature_valid) {
            $DB->update_record('local_payments_webhooks', (object) [
                'id' => $webhook_id,
                'status' => 'failed',
                'processed_at' => time(),
            ]);
            return false;
        }

        // Find matching transaction by merchant_order_id (our order_id).
        $transaction = $DB->get_record('local_payments_transactions', ['order_id' => $result->merchant_order_id]);

        if (!$transaction) {
            // Try metadata-based lookup for providers that embed transaction_id.
            $txn_id = $result->metadata['transaction_id'] ?? null;
            if ($txn_id) {
                $transaction = $DB->get_record('local_payments_transactions', ['id' => $txn_id]);
            }
        }

        if (!$transaction && !empty($result->order_reference)) {
            // Match on the gateway's own session key. Providers that echo our
            // order id only inside a custom payload (Fawaterk's pay_load) have
            // nothing else to match on if that payload goes missing.
            $transaction = $DB->get_record('local_payments_transactions', [
                'provider_id' => $provider_record->id,
                'provider_session_id' => $result->order_reference,
            ], '*', IGNORE_MULTIPLE);
        }

        if (!$transaction && !empty($result->provider_order_id)) {
            // Refund notifications carry only the gateway's numeric transaction
            // id — no session key and no payload — so this is the only handle
            // back to the order. We record it when the payment completes.
            $transaction = $DB->get_record('local_payments_transactions', [
                'provider_id' => $provider_record->id,
                'provider_order_id' => $result->provider_order_id,
            ], '*', IGNORE_MULTIPLE);

            if (!$transaction) {
                $transaction = $DB->get_record('local_payments_transactions', [
                    'provider_id' => $provider_record->id,
                    'provider_session_id' => $result->provider_order_id,
                ], '*', IGNORE_MULTIPLE);
            }
        }

        if (!$transaction) {
            $DB->update_record('local_payments_webhooks', (object) [
                'id' => $webhook_id,
                'status' => 'failed',
                'processed_at' => time(),
            ]);
            return false;
        }

        // Link webhook to transaction.
        $DB->update_record('local_payments_webhooks', (object) [
            'id' => $webhook_id,
            'transaction_id' => $transaction->id,
        ]);

        // Idempotency: skip if already completed.
        if ($transaction->status === status_machine::COMPLETED) {
            $DB->update_record('local_payments_webhooks', (object) [
                'id' => $webhook_id,
                'status' => 'processed',
                'processed_at' => time(),
            ]);
            return true;
        }

        // Process based on event type.
        $success = false;
        if ($result->event_type === 'pending') {
            // An async method has issued a reference but nothing has been paid
            // yet (a Fawry code handed over at checkout). Acknowledge it and
            // leave the order pending — treating it as a payment result would
            // wrongly fail an order the buyer is still on their way to paying.
            $success = true;
        } else if (in_array($result->event_type, ['pay', 'capture'])) {
            $success = self::process_payment_webhook($transaction, $result, $webhook_id);
        } else if ($result->event_type === 'refund') {
            $success = self::process_refund_webhook($transaction, $result, $webhook_id);
        } else if ($result->event_type === 'void') {
            $success = self::process_void_webhook($transaction, $result, $webhook_id);
        }

        $DB->update_record('local_payments_webhooks', (object) [
            'id' => $webhook_id,
            'status' => $success ? 'processed' : 'failed',
            'processed_at' => time(),
        ]);

        return $success;
    }

    private static function process_payment_webhook(\stdClass $transaction, webhook_result $result, int $webhook_id): bool {
        global $DB;

        $provider_status = strtoupper($result->status);

        if ($provider_status !== 'SUCCESS') {
            // Payment failed.
            if (status_machine::can_transition($transaction->status, status_machine::FAILED)) {
                $DB->update_record('local_payments_transactions', (object) [
                    'id' => $transaction->id,
                    'status' => status_machine::FAILED,
                    'provider_order_id' => $result->provider_order_id,
                    'provider_txn_id' => $result->provider_txn_id,
                    'payment_method_type' => $result->payment_method,
                    'reject_reason' => substr("Provider status: {$result->status}", 0, 255),
                    'provider_response_code' => $result->response_code,
                    'provider_response_message' => $result->response_message,
                    'timemodified' => time(),
                ]);
                self::audit_log($transaction->id, $transaction->userid, 'status_changed',
                    $transaction->status, status_machine::FAILED);
            }
            return true;
        }

        // Verify the amount matches what we asked to be charged.
        $check = self::verify_amount(
            $transaction,
            self::get_provider_by_id((int) $transaction->provider_id),
            $result->amount,
            (string) $result->currency,
            $result->reported_amount,
            (string) $result->reported_currency
        );

        if (!$check['ok']) {
            self::log_entry($transaction->provider_id, $transaction->id, 'error', $check['message']);
            $DB->update_record('local_payments_transactions', (object) [
                'id' => $transaction->id,
                'status' => status_machine::FAILED,
                'reject_reason' => substr($check['message'], 0, 255),
                'timemodified' => time(),
            ]);
            return false;
        }

        if ($check['converted']) {
            self::log_entry($transaction->provider_id, $transaction->id, 'info', $check['message']);
            self::record_settlement($transaction, $result->amount, (string) $result->currency);
        }

        if (!status_machine::can_transition($transaction->status, status_machine::COMPLETED)) {
            return true; // Already in a terminal state.
        }

        // Update transaction to completed.
        $DB->update_record('local_payments_transactions', (object) [
            'id' => $transaction->id,
            'status' => status_machine::COMPLETED,
            'provider_order_id' => $result->provider_order_id,
            'provider_txn_id' => $result->provider_txn_id,
            'payment_method_type' => $result->payment_method,
            'provider_response_code' => $result->response_code,
            'provider_response_message' => $result->response_message,
            'timemodified' => time(),
        ]);

        self::audit_log($transaction->id, $transaction->userid, 'status_changed',
            $transaction->status, status_machine::COMPLETED);

        // Subscriptions are fulfilled differently (no course enrolment / course invoice / course event).
        $meta = json_decode($transaction->metadata ?? '{}');
        if (($meta->item_type ?? 'course') === 'subscription') {
            self::fulfil_subscription($transaction, $meta);
            return true;
        }

        // Fulfilment: enrol the student in the purchased course.
        try {
            $enrolled = enrollment_handler::enrol_user((int) $transaction->userid, (int) $transaction->courseid);
            if ($enrolled) {
                self::audit_log($transaction->id, $transaction->userid, 'student_enrolled', '', (string) $transaction->courseid);
            } else {
                self::log_entry($transaction->provider_id, $transaction->id, 'error',
                    'Enrolment call completed without throwing but user is not enrolled.');
            }
        } catch (\Exception $e) {
            self::log_entry($transaction->provider_id, $transaction->id, 'error',
                'Fulfillment failed: ' . $e->getMessage());
        }

        // Record coupon/offer usage for the course purchase (idempotent).
        self::record_nit_discount($transaction, $meta, 'course', (int) $transaction->courseid);

        // Generate invoice.
        try {
            invoice_generator::create((int) $transaction->id);
        } catch (\Exception $e) {
            self::log_entry($transaction->provider_id, $transaction->id, 'warning',
                'Invoice generation failed: ' . $e->getMessage());
        }

        // Send confirmation message.
        self::send_confirmation($transaction);

        // Fire event.
        $event = \local_payments\event\payment_completed::create([
            'context' => \context_course::instance($transaction->courseid),
            'objectid' => $transaction->id,
            'userid' => $transaction->userid,
            'other' => [
                'courseid' => $transaction->courseid,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'provider' => $DB->get_field('local_payments_providers', 'name', ['id' => $transaction->provider_id]),
            ],
        ]);
        $event->trigger();

        return true;
    }

    private static function process_refund_webhook(\stdClass $transaction, webhook_result $result, int $webhook_id): bool {
        global $DB;

        $new_status = ($result->amount >= (float) $transaction->amount)
            ? status_machine::REFUNDED
            : status_machine::PARTIALLY_REFUNDED;

        if (!status_machine::can_transition($transaction->status, $new_status)) {
            return true;
        }

        $DB->update_record('local_payments_transactions', (object) [
            'id' => $transaction->id,
            'status' => $new_status,
            'timemodified' => time(),
        ]);

        self::audit_log($transaction->id, $transaction->userid, 'status_changed', $transaction->status, $new_status);
        return true;
    }

    private static function process_void_webhook(\stdClass $transaction, webhook_result $result, int $webhook_id): bool {
        global $DB;

        if (!status_machine::can_transition($transaction->status, status_machine::VOIDED)) {
            return true;
        }

        $DB->update_record('local_payments_transactions', (object) [
            'id' => $transaction->id,
            'status' => status_machine::VOIDED,
            'timemodified' => time(),
        ]);

        self::audit_log($transaction->id, $transaction->userid, 'status_changed', $transaction->status, status_machine::VOIDED);
        return true;
    }

    /**
     * Verify a payment after the user is redirected back from the provider.
     */
    public static function verify_callback(string $order_id): object {
        global $DB;

        $transaction = $DB->get_record('local_payments_transactions', ['order_id' => $order_id]);
        if (!$transaction) {
            throw new \moodle_exception('transactionnotfound', 'local_payments');
        }

        $meta = json_decode($transaction->metadata ?? '{}');
        $item_type = $meta->item_type ?? 'course';

        $issubscription = ($item_type === 'subscription');

        // If already completed (by webhook), return success immediately.
        if ($transaction->status === status_machine::COMPLETED) {
            return (object) [
                'success' => true,
                'status' => $transaction->status,
                'courseid' => (int) $transaction->courseid,
                'item_type' => $item_type,
                'enrolled' => $issubscription ? false
                    : enrollment_handler::is_enrolled((int) $transaction->userid, (int) $transaction->courseid),
            ];
        }

        // Otherwise verify with provider.
        if (empty($transaction->provider_session_id)) {
            return (object) [
                'success' => false,
                'status' => $transaction->status,
                'courseid' => (int) $transaction->courseid,
                'item_type' => $item_type,
                'enrolled' => false,
            ];
        }

        $provider = self::get_provider_by_id((int) $transaction->provider_id);
        $result = $provider->verify_payment($transaction->provider_session_id);

        if ($result->verified) {
            // Same check the webhook runs — the buyer can land here first, and an
            // order that the webhook would have accepted must not be refused just
            // because the browser got back before the callback did.
            $check = self::verify_amount($transaction, $provider, $result->amount,
                (string) $result->currency);

            if (!$check['ok']) {
                self::log_entry($transaction->provider_id, $transaction->id, 'error', $check['message']);
                return (object) [
                    'success' => false,
                    'status' => 'amount_mismatch',
                    'courseid' => (int) $transaction->courseid,
                    'item_type' => $item_type,
                    'enrolled' => false,
                ];
            }

            if ($check['converted']) {
                self::log_entry($transaction->provider_id, $transaction->id, 'info', $check['message']);
                self::record_settlement($transaction, $result->amount, (string) $result->currency);
            }

            if (status_machine::can_transition($transaction->status, status_machine::COMPLETED)) {
                $DB->update_record('local_payments_transactions', (object) [
                    'id' => $transaction->id,
                    'status' => status_machine::COMPLETED,
                    'provider_order_id' => $result->provider_order_id,
                    'provider_txn_id' => $result->provider_txn_id,
                    'payment_method_type' => $result->payment_method_type,
                    'timemodified' => time(),
                ]);
                self::audit_log($transaction->id, $transaction->userid, 'status_changed',
                    $transaction->status, status_machine::COMPLETED);

                if ($issubscription) {
                    // Subscription: create the purchase (grants live course access); no course enrolment.
                    self::fulfil_subscription($transaction, $meta);
                    return (object) [
                        'success' => true,
                        'status' => status_machine::COMPLETED,
                        'courseid' => 0,
                        'item_type' => $item_type,
                        'enrolled' => false,
                    ];
                }

                $enrolled = false;
                try {
                    $enrolled = enrollment_handler::enrol_user((int) $transaction->userid, (int) $transaction->courseid);
                    if ($enrolled) {
                        self::audit_log($transaction->id, $transaction->userid, 'student_enrolled', '', (string) $transaction->courseid);
                    } else {
                        self::log_entry($transaction->provider_id, $transaction->id, 'error',
                            'Enrolment call completed without throwing but user is not enrolled.');
                    }
                } catch (\Exception $e) {
                    self::log_entry($transaction->provider_id, $transaction->id, 'error',
                        'Fulfillment failed: ' . $e->getMessage());
                }

                // Record coupon/offer usage for the course purchase (idempotent).
                self::record_nit_discount($transaction, $meta, 'course', (int) $transaction->courseid);

                invoice_generator::create((int) $transaction->id);
                self::send_confirmation($transaction);
            } else {
                $enrolled = $issubscription ? false
                    : enrollment_handler::is_enrolled((int) $transaction->userid, (int) $transaction->courseid);
            }

            return (object) [
                'success' => true,
                'status' => status_machine::COMPLETED,
                'courseid' => (int) $transaction->courseid,
                'item_type' => $item_type,
                'enrolled' => $enrolled,
            ];
        }

        return (object) [
            'success' => false,
            'status' => $transaction->status,
            'courseid' => (int) $transaction->courseid,
            'item_type' => $item_type,
            'enrolled' => false,
        ];
    }

    /**
     * Get all available providers for a country and currency.
     */
    public static function get_available_providers(string $country, string $currency): array {
        global $DB;
        $providers = $DB->get_records('local_payments_providers', ['enabled' => 1], 'priority ASC');
        $available = [];

        foreach ($providers as $p) {
            // Same authority as get_provider(): what the gateway says, not what
            // the seeded row says. Otherwise this list and the provider actually
            // chosen at checkout could disagree.
            if (!self::provider_supports(self::instantiate_provider($p), $country, $currency)) {
                continue;
            }

            $available[] = [
                'name' => $p->name,
                'display_name' => $p->display_name,
                'priority' => (int) $p->priority,
            ];
        }

        return $available;
    }

    // ─── Helpers ───────────────────────────────────────────────────

    private static function generate_order_id(): string {
        return 'PAY-' . date('Y') . '-' . str_pad(random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
    }

    private static function generate_idempotency_key(int $userid, int $courseid): string {
        return hash('sha256', $userid . '-' . $courseid . '-' . time() . '-' . random_int(1, 999999));
    }

    private static function audit_log(?int $transaction_id, ?int $userid, string $action,
            string $old_value = '', string $new_value = ''): void {
        global $DB;
        $DB->insert_record('local_payments_audit_logs', (object) [
            'transaction_id' => $transaction_id,
            'userid' => $userid,
            'action' => $action,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'ip_address' => getremoteaddr(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'timecreated' => time(),
        ]);
    }

    private static function log_entry(?int $provider_id, ?int $transaction_id, string $level, string $message): void {
        global $DB;
        $DB->insert_record('local_payments_logs', (object) [
            'provider_id' => $provider_id,
            'transaction_id' => $transaction_id,
            'level' => $level,
            'message' => substr($message, 0, 500),
            'timecreated' => time(),
        ]);
    }

    private static function send_confirmation(\stdClass $transaction): void {
        global $DB;
        $user = $DB->get_record('user', ['id' => $transaction->userid]);
        $course = $DB->get_record('course', ['id' => $transaction->courseid]);

        if (!$user || !$course) {
            return;
        }

        // The admin-editable purchase email (Site administration › Plugins ›
        // Local plugins › Purchase & registration emails) is the one students
        // actually receive: it carries the whole course file summary in the
        // buyer's own language. Once that plugin owns this message the plain
        // notification below is skipped entirely — including when an admin has
        // switched the email off, which has to mean silence rather than a
        // silent fall back to the old wording.
        if (class_exists('\local_nit_emails\mailer')
                && \local_nit_emails\mailer::handles(\local_nit_emails\templates::EVENT_COURSE)) {
            \local_nit_emails\mailer::send_course_purchase($user, $course, $transaction);
            return;
        }

        $message = new \core\message\message();
        $message->component = 'local_payments';
        $message->name = 'payment_confirmation';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = get_string('payment_confirmation_subject', 'local_payments', $course->fullname);
        $message->fullmessage = get_string('payment_confirmation_body', 'local_payments', (object) [
            'coursename' => $course->fullname,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'order_id' => $transaction->order_id,
        ]);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = get_string('payment_confirmation_html', 'local_payments', (object) [
            'coursename' => $course->fullname,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'order_id' => $transaction->order_id,
        ]);
        $message->smallmessage = get_string('payment_confirmation_small', 'local_payments', $course->fullname);
        $message->notification = 1;

        try {
            message_send($message);
        } catch (\Exception $e) {
            self::log_entry($transaction->provider_id, $transaction->id, 'warning',
                'Confirmation message failed: ' . $e->getMessage());
        }
    }
}
