<?php
namespace local_payments\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

global $CFG;

/**
 * Refund a payment, or ask for one.
 *
 * Deliberately one call rather than two. Which route applies is the server's
 * decision — it depends on a window that may have closed since the app drew the
 * screen — so an app that had to choose the endpoint could choose the wrong one.
 * Here it says "the buyer wants their money back" and the server does whichever
 * is correct, reporting which it did.
 */
class submit_refund extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'transaction_id' => new external_value(PARAM_INT, 'Transaction to refund'),
            'reason' => new external_value(PARAM_TEXT, 'Why. Required when the request goes to staff.',
                VALUE_DEFAULT, ''),
            'lang' => new external_value(PARAM_LANG, 'Display language (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $transaction_id, string $reason = '', string $lang = '',
            string $alang = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'transaction_id' => $transaction_id,
            'reason' => $reason,
            'lang' => $lang,
            'alang' => $alang,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $wslang = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($wslang !== '') {
            force_current_language($wslang);
        }

        $txn = $DB->get_record('local_payments_transactions',
            ['id' => $params['transaction_id']], '*', MUST_EXIST);

        // Only the buyer refunds their own payment. Staff act from the web list,
        // where the decision is recorded against them.
        if ((int) $txn->userid !== (int) $USER->id) {
            throw new \moodle_exception('invalidaccess', 'error');
        }

        $blocker = \local_payments\refund_manager::blocker($txn);
        if ($blocker !== '') {
            throw new \moodle_exception($blocker, 'local_payments');
        }

        $reasontext = trim($params['reason']);
        $quote = \local_payments\refund_policy::quote($txn);

        // Re-decided here, not taken from the caller: the window may have closed
        // between the app drawing its screen and the buyer pressing the button.
        if ($quote->withinwindow) {
            $result = \local_payments\refund_manager::self_refund($txn, $reasontext);
            return [
                'outcome' => $result->success ? 'refunded' : 'failed',
                'message' => $result->message,
                'amount' => $result->success ? $quote->net : 0,
                'currency' => $quote->currency,
            ];
        }

        if ($reasontext === '') {
            throw new \moodle_exception('refund_err_needreason', 'local_payments');
        }

        \local_payments\refund_manager::request($txn, $reasontext);

        return [
            'outcome' => 'requested',
            'message' => get_string('refund_requested', 'local_payments'),
            'amount' => $quote->net,
            'currency' => $quote->currency,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'outcome' => new external_value(PARAM_ALPHA,
                'refunded = money returned; requested = waiting on staff; failed = gateway refused'),
            'message' => new external_value(PARAM_TEXT, 'Ready to show to the buyer'),
            'amount' => new external_value(PARAM_FLOAT, 'What is coming back, or would if approved'),
            'currency' => new external_value(PARAM_TEXT, 'Currency of that amount'),
        ]);
    }
}
