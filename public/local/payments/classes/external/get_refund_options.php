<?php
namespace local_payments\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;

global $CFG;

/**
 * What the app should offer for one payment: refund now, ask, or nothing.
 *
 * The decision belongs here rather than in the app, so a phone with a stale
 * screen cannot offer an instant refund after the window has closed, and so the
 * rules can change without shipping a release.
 */
class get_refund_options extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'transaction_id' => new external_value(PARAM_INT, 'Transaction to ask about'),
            'lang' => new external_value(PARAM_LANG, 'Display language (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $transaction_id, string $lang = '', string $alang = ''): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(),
            ['transaction_id' => $transaction_id, 'lang' => $lang, 'alang' => $alang]);

        $context = \context_system::instance();
        self::validate_context($context);

        $wslang = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($wslang !== '') {
            force_current_language($wslang);
        }

        $txn = $DB->get_record('local_payments_transactions',
            ['id' => $params['transaction_id']], '*', MUST_EXIST);

        // A buyer asks about their own payment; staff use the web list.
        if ((int) $txn->userid !== (int) $USER->id) {
            require_capability('local/payments:viewalltransactions', $context);
        }

        $blocker = \local_payments\refund_manager::blocker($txn);
        $quote = \local_payments\refund_policy::quote($txn);
        $pending = \local_payments\refund_manager::open_request((int) $txn->id);

        // One field the app switches on, rather than three booleans it has to
        // combine correctly.
        if ($pending) {
            $action = 'pending';
        } else if ($blocker !== '') {
            $action = 'none';
        } else {
            $action = $quote->withinwindow ? 'refund' : 'request';
        }

        return [
            'action' => $action,
            'reason_required' => ($action === 'request'),
            'message' => $blocker !== '' ? get_string($blocker, 'local_payments') : '',
            'paid' => $quote->paid,
            'fee' => $quote->fee,
            'fee_percent' => $quote->feepercent,
            'net' => $quote->net,
            'currency' => $quote->currency,
            'window_hours' => $quote->hours,
            'deadline' => $quote->deadline,
            'policy' => \local_payments\refund_policy::describe($quote),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'action' => new external_value(PARAM_ALPHA,
                'refund = can refund now; request = must ask; pending = already asked; none = not offered'),
            'reason_required' => new external_value(PARAM_BOOL, 'Whether a reason must be supplied'),
            'message' => new external_value(PARAM_TEXT, 'Why nothing is offered, when action is none'),
            'paid' => new external_value(PARAM_FLOAT, 'What was paid'),
            'fee' => new external_value(PARAM_FLOAT, 'What would be kept, in the currency below'),
            'fee_percent' => new external_value(PARAM_FLOAT,
                'The fee as a percentage of the amount paid, which is how the policy states it'),
            'net' => new external_value(PARAM_FLOAT, 'What would come back — show this figure'),
            'currency' => new external_value(PARAM_TEXT, 'Currency of all three amounts'),
            'window_hours' => new external_value(PARAM_INT, 'Length of the self-service window; 0 means none'),
            'deadline' => new external_value(PARAM_INT, 'Unix time the window closes; 0 when there is none'),
            'policy' => new external_value(PARAM_TEXT, 'The policy as a sentence, ready to display'),
        ]);
    }
}
