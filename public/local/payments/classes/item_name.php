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

namespace local_payments;

defined('MOODLE_INTERNAL') || die();

/**
 * What a payment was for, in words.
 *
 * The transaction row has a courseid and nothing else, so every screen that
 * wanted to name a purchase reached for the course — which is right for a course
 * and blank for a subscription. Since a plan sale has no course, those rows
 * printed a dash where the buyer expected to see what they had bought.
 *
 * Names are stored bilingually in one field, so they go through
 * {@see multilang::resolve()} on the way out. filter_multilang2 is not installed
 * here, so nothing else would.
 */
class item_name {

    /**
     * Name one purchase.
     *
     * @param \stdClass $transaction Row from local_payments_transactions.
     * @param string $lang Language to render, or '' for the current one.
     * @return string Empty when the item has since been deleted.
     */
    public static function of(\stdClass $transaction, string $lang = ''): string {
        global $DB;

        $type = refund_policy::item_type($transaction);
        $id = refund_policy::item_id($transaction);

        if ($id <= 0) {
            return '';
        }

        if ($type === 'subscription') {
            $raw = $DB->get_field('nit_subscription', 'name', ['id' => $id]);
        } else {
            $raw = $DB->get_field('course', 'fullname', ['id' => $id]);
        }

        return $raw === false ? '' : multilang::resolve((string) $raw, $lang);
    }

    /**
     * The same, for a set of transactions, without a query per row.
     *
     * @param array $transactions Rows keyed however the caller likes.
     * @param string $lang Language to render, or '' for the current one.
     * @return array Name keyed by transaction id; missing items are absent.
     */
    public static function for_many(array $transactions, string $lang = ''): array {
        global $DB;

        $courseids = [];
        $planids = [];
        foreach ($transactions as $txn) {
            $id = refund_policy::item_id($txn);
            if ($id <= 0) {
                continue;
            }
            if (refund_policy::item_type($txn) === 'subscription') {
                $planids[$id] = true;
            } else {
                $courseids[$id] = true;
            }
        }

        $courses = $courseids
            ? $DB->get_records_list('course', 'id', array_keys($courseids), '', 'id, fullname')
            : [];
        $plans = $planids
            ? $DB->get_records_list('nit_subscription', 'id', array_keys($planids), '', 'id, name')
            : [];

        $names = [];
        foreach ($transactions as $txn) {
            $id = refund_policy::item_id($txn);
            if (refund_policy::item_type($txn) === 'subscription') {
                $raw = isset($plans[$id]) ? $plans[$id]->name : null;
            } else {
                $raw = isset($courses[$id]) ? $courses[$id]->fullname : null;
            }
            if ($raw !== null) {
                $names[(int) $txn->id] = multilang::resolve((string) $raw, $lang);
            }
        }

        return $names;
    }
}
