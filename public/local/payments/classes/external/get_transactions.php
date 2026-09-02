<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_payments\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_payments\history_api;

defined('MOODLE_INTERNAL') || die();

/**
 * `/local/payments/history.php` for a client that cannot render the page.
 *
 * The screen `local_payments_get_payment_history` could not build. That function
 * returns a bare list of everything, newest first, which is enough for a summary
 * and not enough for the actual screen: it has no total, so a paged list cannot
 * tell how far it goes; no filters, so a student claiming a year of purchases
 * back from an employer has to scroll; and no per-row state, so a client cannot
 * know whether a row has an invoice to print or a refund still to take without
 * asking about each row separately.
 *
 * Everything comes from {@see history_api}, which is also what the web page
 * uses, so the two lists cannot drift apart.
 *
 * `local_payments_get_payment_history` is untouched and still works; use this one
 * for the screen.
 *
 * @package    local_payments
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_transactions extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'page' => new external_value(PARAM_INT, 'Zero-based page number.', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,
                'Rows per page. Capped at ' . history_api::MAXPERPAGE . '.', VALUE_DEFAULT, history_api::PERPAGE),
            'q' => new external_value(PARAM_TEXT,
                'Search the order reference and the invoice number. Empty for no search.', VALUE_DEFAULT, ''),
            'status' => new external_value(PARAM_ALPHAEXT,
                'Show only this status - one of the values in filters.statuses. '
                . 'Empty, or a status this site does not use, means all of them.', VALUE_DEFAULT, ''),
            'courseid' => new external_value(PARAM_INT,
                'Show only payments for this course. 0 for all.', VALUE_DEFAULT, 0),
            'datefrom' => new external_value(PARAM_RAW_TRIMMED,
                'Earliest payment date, as YYYY-MM-DD. The whole day is included, in the user\'s own '
                . 'timezone. Anything that is not a date is ignored rather than refused.', VALUE_DEFAULT, ''),
            'dateto' => new external_value(PARAM_RAW_TRIMMED,
                'Latest payment date, as YYYY-MM-DD. The whole day is included.', VALUE_DEFAULT, ''),
            'lang' => new external_value(PARAM_LANG,
                'Display language for the labels, e.g. en or ar (optional).', VALUE_DEFAULT, ''),
            'alang' => new external_value(PARAM_LANG, 'Display language (alias of lang, optional).', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * One filtered page of the caller's own payments.
     *
     * @param int $page zero-based page number
     * @param int $perpage rows per page
     * @param string $q search text
     * @param string $status status filter
     * @param int $courseid course filter
     * @param string $datefrom YYYY-MM-DD
     * @param string $dateto YYYY-MM-DD
     * @param string $lang display language
     * @param string $alang display language, alias
     * @return array
     */
    public static function execute($page = 0, $perpage = history_api::PERPAGE, $q = '', $status = '',
            $courseid = 0, $datefrom = '', $dateto = '', $lang = '', $alang = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'page' => $page,
            'perpage' => $perpage,
            'q' => $q,
            'status' => $status,
            'courseid' => $courseid,
            'datefrom' => $datefrom,
            'dateto' => $dateto,
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

        $userid = (int) $USER->id;
        $perpage = min(max(1, (int) $params['perpage']), history_api::MAXPERPAGE);
        $pagenum = max(0, (int) $params['page']);

        $filters = history_api::filters([
            'q' => $params['q'],
            'status' => $params['status'],
            'courseid' => $params['courseid'],
            'datefrom' => $params['datefrom'],
            'dateto' => $params['dateto'],
        ]);

        $transactions = history_api::fetch($userid, $filters, $pagenum, $perpage);
        $total = history_api::count($userid, $filters);

        $statuses = [];
        foreach (history_api::statuses() as $value => $label) {
            $statuses[] = ['value' => $value, 'label' => $label];
        }

        $courses = [];
        foreach (history_api::courses($userid) as $course) {
            $courses[] = [
                'id' => (int) $course->id,
                'name' => \local_payments\multilang::resolve(format_string($course->fullname), $wslang),
            ];
        }

        return [
            'transactions' => history_api::rows($transactions),
            'total' => $total,
            'page' => $pagenum,
            'perpage' => $perpage,
            // The two things a client has to know before it can say "you have no
            // payments": whether anything was filtered out, and whether the
            // account has ever paid for anything at all. Saying "no payments" to
            // somebody whose filter is simply too narrow sends them looking for a
            // fault that is not there.
            'filtered' => $filters !== history_api::filters([]),
            'filters' => [
                'statuses' => $statuses,
                'courses' => $courses,
            ],
            'warnings' => [],
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'transactions' => new external_multiple_structure(
                new external_single_structure([
                    'transaction_id' => new external_value(PARAM_INT,
                        'Pass this to local_payments_get_invoice, local_payments_get_refund_options '
                        . 'and local_payments_submit_refund.'),
                    'order_id' => new external_value(PARAM_TEXT, 'The site\'s own order reference.'),
                    'courseid' => new external_value(PARAM_INT, 'Course bought, or 0 for a subscription.'),
                    'item_type' => new external_value(PARAM_ALPHANUMEXT, 'course or subscription.'),
                    'item_name' => new external_value(PARAM_TEXT,
                        'What was bought - the course or the plan - in the requested language.'),
                    'amount' => new external_value(PARAM_FLOAT, 'Amount paid.'),
                    'original_amount' => new external_value(PARAM_FLOAT, 'Amount before any discount.'),
                    'currency' => new external_value(PARAM_TEXT, 'ISO currency code.'),
                    'status' => new external_value(PARAM_TEXT,
                        'The machine status - branch on this, never on status_label.'),
                    'status_label' => new external_value(PARAM_TEXT,
                        'The status translated for display.'),
                    'provider' => new external_value(PARAM_TEXT, 'Payment provider display name.'),
                    'payment_method' => new external_value(PARAM_TEXT, 'card, fawry, wallet, ...'),
                    'invoice_number' => new external_value(PARAM_TEXT,
                        'Invoice number, or "" when none has been issued yet.'),
                    'timecreated' => new external_value(PARAM_INT, 'When the payment was made.'),
                    'can_download_invoice' => new external_value(PARAM_BOOL,
                        'True when local_payments_get_invoice will return a PDF for this row.'),
                    'can_refund' => new external_value(PARAM_BOOL,
                        'True when the buyer may still act on this payment - show the refund button.'),
                    'refund_pending' => new external_value(PARAM_BOOL,
                        'True when a refund request is already waiting for a decision.'),
                    'refund_instant' => new external_value(PARAM_BOOL,
                        'True when the refund happens immediately; false when it is a request. '
                        . 'This is what refund_label already says.'),
                    'refund_label' => new external_value(PARAM_TEXT,
                        'The wording for the refund button, or "" when there is no button.'),
                ]), 'This page of payments, newest first.'
            ),
            'total' => new external_value(PARAM_INT, 'How many payments match the filters in total.'),
            'page' => new external_value(PARAM_INT, 'The page returned.'),
            'perpage' => new external_value(PARAM_INT, 'Rows per page actually used, after the cap.'),
            'filtered' => new external_value(PARAM_BOOL,
                'True when a filter narrowed the list. An empty list with this true means "nothing '
                . 'matches"; with it false, "you have never paid for anything".'),
            'filters' => new external_single_structure([
                'statuses' => new external_multiple_structure(
                    new external_single_structure([
                        'value' => new external_value(PARAM_ALPHAEXT, 'Send this back as the status parameter.'),
                        'label' => new external_value(PARAM_TEXT, 'Translated label for the menu.'),
                    ]), 'Every status a payment can be in.'
                ),
                'courses' => new external_multiple_structure(
                    new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'Send this back as the courseid parameter.'),
                        'name' => new external_value(PARAM_TEXT, 'Course name.'),
                    ]), 'Only courses this account has actually paid for.'
                ),
            ], 'What to put in the filter controls.'),
            'warnings' => new \core_external\external_warnings(),
        ]);
    }
}
