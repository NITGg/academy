<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * The price moved between the buyer opening checkout and the checkout being created (AC-4.13.6).
 *
 * The usual cause is an automatic offer reaching its end date while the buyer was still on the
 * confirmation screen, but a deactivated offer, an edited campaign, a coupon that hit its cap or a
 * changed course price all produce the same situation and the same answer: the amount the buyer
 * agreed to is no longer the amount we would charge.
 *
 * No transaction has been opened and no gateway session exists when this is thrown — it is raised
 * before any of that, so the only thing to undo is the screen. Callers MUST catch it specifically,
 * show the buyer the revised price against the one they were quoted, and take a fresh confirmation
 * before charging. They must NOT let it fall into a generic catch that would either charge the new
 * amount silently or drop the sale with a raw error message.
 *
 * Thrown by {@see manager::create_checkout()} and {@see manager::create_subscription_checkout()},
 * and only when the caller supplied a quote to check against.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class price_changed_exception extends \moodle_exception {

    /** @var float The amount the buyer was quoted when they opened checkout. */
    public float $quoted;

    /** @var float The amount that would be charged now. */
    public float $amount;

    /** @var string ISO 4217 currency both amounts are in. */
    public string $currency;

    /**
     * @param float $quoted amount the buyer was shown at checkout
     * @param float $amount amount that would be charged now
     * @param string $currency ISO 4217
     */
    public function __construct(float $quoted, float $amount, string $currency = '') {
        $this->quoted = round($quoted, 2);
        $this->amount = round($amount, 2);
        $this->currency = $currency;

        // The message is the one shown when a surface has nowhere better to put it (the mobile
        // web service, a JSON reply). Screens that can do better render their own confirmation.
        parent::__construct('pricechanged_desc', 'local_payments', '', (object) [
            'old' => format_float($this->quoted, 2, true, true) . ' ' . $currency,
            'new' => format_float($this->amount, 2, true, true) . ' ' . $currency,
        ], "quoted {$this->quoted}, now {$this->amount} {$currency}");
    }

    /**
     * The revised quote, for a JSON caller that will render its own confirmation.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'code'     => 'price_changed',
            'quoted'   => $this->quoted,
            'amount'   => $this->amount,
            'currency' => $this->currency,
            'increase' => $this->amount > $this->quoted,
        ];
    }
}
