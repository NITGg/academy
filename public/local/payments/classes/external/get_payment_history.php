<?php
namespace local_payments\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_warnings;
use core_external\external_format_value;
use core_external\external_files;

global $CFG;

class get_payment_history extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'page' => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Per page', VALUE_DEFAULT, 20),
            'lang' => new external_value(PARAM_LANG, 'Display language, e.g. en or ar (optional)', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $page = 0, int $perpage = 20, string $lang = '', string $alang = ''): array {
        global $USER, $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'page' => $page,
            'perpage' => $perpage,
            'lang' => $lang,
            'alang' => $alang,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        $wslang = $params['alang'] !== '' ? $params['alang'] : $params['lang'];
        if ($wslang !== '') {
            force_current_language($wslang);
        }
        require_capability('local/payments:viewownhistory', $context);

        $transactions = $DB->get_records_select(
            'local_payments_transactions',
            'userid = :userid',
            ['userid' => $USER->id],
            'timecreated DESC',
            '*',
            $params['page'] * $params['perpage'],
            $params['perpage']
        );

        $result = [];
        foreach ($transactions as $txn) {
            // Not just the course: a subscription sale has no courseid, so the
            // app was showing a blank name against every plan purchase.
            $itemname = \local_payments\item_name::of($txn);
            $provider_name = $DB->get_field('local_payments_providers', 'display_name', ['id' => $txn->provider_id]);

            $invoice = $DB->get_record('local_payments_invoices', ['transaction_id' => $txn->id]);

            $result[] = [
                'transaction_id' => (int) $txn->id,
                'order_id' => $txn->order_id,
                'courseid' => (int) $txn->courseid,
                'item_type' => \local_payments\refund_policy::item_type($txn),
                'item_name' => $itemname,
                // Kept so an app built against the old field keeps working; it
                // is the same string, and empty for a subscription as before.
                'course_name' => $txn->courseid ? $itemname : '',
                'amount' => (float) $txn->amount,
                'original_amount' => (float) ($txn->original_amount ?? $txn->amount),
                'currency' => $txn->currency,
                'status' => $txn->status,
                'provider' => $provider_name ?: '',
                'payment_method' => $txn->payment_method_type ?? '',
                'invoice_number' => $invoice->invoice_number ?? '',
                'timecreated' => (int) $txn->timecreated,
            ];
        }

        return $result;
    }

    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'transaction_id' => new external_value(PARAM_INT, 'Transaction ID'),
                'order_id' => new external_value(PARAM_TEXT, 'Order ID'),
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'item_type' => new external_value(PARAM_ALPHANUMEXT,
                    'course or subscription — what was bought'),
                'item_name' => new external_value(PARAM_TEXT,
                    'Name of the course or plan, in the current language'),
                'course_name' => new external_value(PARAM_TEXT,
                    'Deprecated: same as item_name for a course, empty for a subscription'),
                'amount' => new external_value(PARAM_FLOAT, 'Amount paid'),
                'original_amount' => new external_value(PARAM_FLOAT, 'Original amount'),
                'currency' => new external_value(PARAM_TEXT, 'Currency'),
                'status' => new external_value(PARAM_TEXT, 'Status'),
                'provider' => new external_value(PARAM_TEXT, 'Payment provider'),
                'payment_method' => new external_value(PARAM_TEXT, 'Payment method'),
                'invoice_number' => new external_value(PARAM_TEXT, 'Invoice number'),
                'timecreated' => new external_value(PARAM_INT, 'Created timestamp'),
            ])
        );
    }
}
