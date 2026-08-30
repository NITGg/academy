<?php
namespace local_payments\provider;

defined('MOODLE_INTERNAL') || die();

class checkout_response {
    public bool $success;
    public string $checkout_url;
    public string $provider_session_id;
    public string $error_message;
    public array $raw_response;

    /**
     * Normalised instructions for finishing a server-to-server payment.
     *
     * Always has the same four keys so the app can switch on `type`:
     *  - type: redirect | reference | none
     *  - redirect_url: 3-D Secure / hosted page to open (type=redirect)
     *  - reference: code the buyer pays with at an outlet or in a wallet app
     *               (Fawry code, Meeza reference) (type=reference)
     *  - reference_expires_at: human-readable expiry for that code, if given
     *  - method_name: the method actually charged, for display ("Fawry")
     *  - qr: payload for a scannable code, when the method issues one (wallets)
     */
    public array $payment_data;

    public function __construct(bool $success, string $checkout_url = '', string $provider_session_id = '',
            string $error_message = '', array $raw_response = [], array $payment_data = []) {
        $this->success = $success;
        $this->checkout_url = $checkout_url;
        $this->provider_session_id = $provider_session_id;
        $this->error_message = $error_message;
        $this->raw_response = $raw_response;
        $this->payment_data = $payment_data ?: self::empty_payment_data();
    }

    public static function success(string $checkout_url, string $session_id, array $raw = [],
            array $payment_data = []): self {
        return new self(true, $checkout_url, $session_id, '', $raw, $payment_data);
    }

    public static function failure(string $message, array $raw = []): self {
        return new self(false, '', '', $message, $raw);
    }

    /**
     * The shape every caller can rely on when the provider gave no extra
     * instructions (a plain hosted-checkout redirect).
     */
    public static function empty_payment_data(): array {
        return [
            'type' => 'none',
            'redirect_url' => '',
            'reference' => '',
            'reference_expires_at' => '',
            'method_name' => '',
            'qr' => '',
        ];
    }
}
