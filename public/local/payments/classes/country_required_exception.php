<?php
namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * The buyer is signed in but their profile carries no country, so no price may be shown.
 *
 * Every price on this site is a *country* price. A signed-in account is priced on the country
 * it registered with and nothing else — no IP guess, no admin default — so an account with an
 * empty country has no price at all, rather than a borrowed one. Callers that display a price
 * must catch this SPECIFICALLY and print
 * {@see \local_payments\country_detector::country_required_notice()}; they must NOT let it fall
 * into a generic `catch (\moodle_exception)` that ends up advertising the course as free or
 * reaching past the resolver for "any active rule" — both would show a price the rule forbids.
 *
 * Thrown by {@see price_resolver::resolve()} and
 * {@see \local_nit_subscriptions\subscription_manager::resolve_price()}.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class country_required_exception extends \moodle_exception {

    public function __construct(?string $debuginfo = null) {
        parent::__construct('countryrequired_desc', 'local_payments', '', null, $debuginfo);
    }
}
