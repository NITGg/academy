<?php
namespace local_payments\provider;

defined('MOODLE_INTERNAL') || die();

interface provider_interface {

    /**
     * Create a checkout session with the provider.
     *
     * @param payment_request $request
     * @return checkout_response
     */
    public function initialize_payment(payment_request $request): checkout_response;

    /**
     * Verify a payment server-side after callback.
     *
     * @param string $provider_reference Provider session ID or transaction ID.
     * @return verification_result
     */
    public function verify_payment(string $provider_reference): verification_result;

    /**
     * Process an incoming webhook payload.
     *
     * @param string $payload Raw request body.
     * @param array $headers Request headers.
     * @return webhook_result
     */
    public function handle_webhook(string $payload, array $headers): webhook_result;

    /**
     * Refund a completed payment.
     *
     * @param string $provider_order_id
     * @param float $amount
     * @param string $currency
     * @param string $reason
     * @return refund_result
     */
    public function refund(string $provider_order_id, float $amount, string $currency, string $reason = ''): refund_result;

    /**
     * Void a payment (full reversal before settlement).
     *
     * @param string $provider_order_id
     * @param string $reason
     * @return refund_result
     */
    public function void_payment(string $provider_order_id, string $reason = ''): refund_result;

    /**
     * Fetch transaction details from provider.
     *
     * @param string $provider_reference
     * @return transaction_info
     */
    public function get_transaction(string $provider_reference): transaction_info;

    /**
     * Whether this provider can charge a specific payment method server-to-server
     * (as opposed to only handing the buyer a hosted page that picks one).
     *
     * @return bool
     */
    public function supports_payment_methods(): bool;

    /**
     * List the payment methods the provider account has enabled.
     *
     * Each entry: id, name_en, name_ar, logo, redirect (bool — whether paying with
     * it sends the buyer to another page rather than returning a reference code).
     *
     * @return array[] Empty when the provider does not support method selection.
     */
    public function get_payment_methods(): array;

    /**
     * Whether the amounts this gateway reports back are normalised.
     *
     * Some gateways report every transaction converted into their own settlement
     * currency regardless of what the buyer was charged — Fawaterk documents
     * getTransactionData's total as "converted to EGP". Comparing that figure to
     * the order is meaningless: it will differ whenever the order is not in the
     * settlement currency, and matching it would prove nothing.
     *
     * For such a gateway the amount is guaranteed at creation instead: we made
     * the session with our own amount, and the gateway confirming that session
     * as paid is the confirmation. Only say true when that is genuinely how the
     * provider behaves.
     *
     * @return bool
     */
    public function reports_normalised_amounts(): bool;

    public function supports_refund(): bool;
    public function supports_void(): bool;
    public function supports_recurring(): bool;
    public function supported_currencies(): array;
    public function supported_countries(): array;

    /**
     * Get the provider's unique name slug.
     * @return string
     */
    public function get_name(): string;
}
