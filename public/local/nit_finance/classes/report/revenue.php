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

/**
 * One revenue report, asked six different ways.
 *
 * Four admin pages used to answer the money question in four different shapes — an enrolment
 * source list here, a user-subscription list there, a coupon redemption table, an offer usage
 * table — none of which added up to "what did we earn, and where did each pound come from?".
 * They now all render the SAME report; only the scope differs, and the master page
 * (local/nit_finance/manage_withdrawals.php) runs every scope in one place.
 *
 * There is exactly one ledger of money in: {local_payments_transactions}. Every sale on the
 * platform lands there — a single course, a subscription plan, a discounted order, a refunded
 * one — with the full price breakdown in its `metadata` JSON. Money out is
 * {local_payments_refunds}. Everything below is those two tables, sliced.
 *
 * Money is handled as decimal floats, not minor units, because that is how the payment tables
 * store it; nit_earning / nit_withdrawal (minor units) belong to a different, Flex-based model
 * and are deliberately not mixed in here.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_finance\report;

defined('MOODLE_INTERNAL') || die();

/**
 * The platform revenue report: totals, a breakdown by source, and the orders behind them.
 */
class revenue {

    /** @var string Everything sold, whatever it was — the master page. */
    const SCOPE_ALL = 'all';
    /** @var string Single-course purchases only (transactions carry a real courseid). */
    const SCOPE_COURSES = 'courses';
    /** @var string Subscription plan purchases only (courseid is the 0 sentinel). */
    const SCOPE_SUBSCRIPTIONS = 'subscriptions';
    /** @var string Orders a coupon code actually discounted. */
    const SCOPE_COUPONS = 'coupons';
    /** @var string Orders an automatic offer actually discounted. */
    const SCOPE_OFFERS = 'offers';
    /** @var string Money that went back out. */
    const SCOPE_REFUNDS = 'refunds';

    /** @var string Paid orders: the money actually received, refunded or not. The default. */
    const STATE_PAID = 'paid';
    /** @var string Paid and still standing. */
    const STATE_COMPLETED = 'completed';
    /** @var string Paid and given back. */
    const STATE_REFUNDED = 'refunded';
    /** @var string Attempted and never paid — what the platform did not earn. */
    const STATE_LOST = 'lost';
    /** @var string Every attempt, paid or not. */
    const STATE_ALL = 'all';

    /** @var int Rows returned per page of the detail table. */
    const PER_PAGE = 25;

    /**
     * @var int Hard cap on rows examined in one call.
     *
     * Rows come back newest first, so a site past this many orders gets a report about its most
     * recent 5000 — and is told so, because a silently capped total is worse than no total.
     * The window is the cure: a month at a time is under the cap and is what anyone reconciling
     * actually wants. Matches enrolment_source::REPORT_LIMIT, which answers the same problem.
     */
    const MAX_ROWS = 5000;

    /** @var string A refund written by an admin revoking a purchase, not by the gateway. */
    const REFUND_MANUAL = 'MANUAL_REVOKE';

    /**
     * The scopes, in the order the master page presents them.
     *
     * @return string[]
     */
    public static function scopes(): array {
        return [
            self::SCOPE_ALL,
            self::SCOPE_COURSES,
            self::SCOPE_SUBSCRIPTIONS,
            self::SCOPE_COUPONS,
            self::SCOPE_OFFERS,
            self::SCOPE_REFUNDS,
        ];
    }

    /**
     * Normalise a scope that came off a URL or a form.
     *
     * @param string $scope
     * @return string
     */
    public static function scope(string $scope): string {
        return in_array($scope, self::scopes(), true) ? $scope : self::SCOPE_ALL;
    }

    /**
     * Transaction statuses that mean money was actually received.
     *
     * Refunded rows belong here: the money came in, and it is the refund column — not the
     * disappearance of the order — that records it going back out. Dropping them would make
     * the refund total describe orders the report no longer shows.
     *
     * @return string[]
     */
    public static function paid_statuses(): array {
        return ['completed', 'refunded', 'partially_refunded'];
    }

    /**
     * Statuses that mean the sale never happened.
     *
     * @return string[]
     */
    public static function lost_statuses(): array {
        return ['failed', 'cancelled', 'expired', 'timed_out', 'voided', 'chargeback'];
    }

    // -- The report ------------------------------------------------------------------

    /**
     * Run the report.
     *
     * @param array $filters scope, from, to, q, state, currency, itemid, page, perpage, source
     * @return array kpis, currency, currencies, breakdown, rows, total, page, perpage, truncated
     */
    public static function run(array $filters): array {
        $f = self::normalise($filters);
        $rows = self::rows($f, $currency, $currencies, $examined);

        $kpis = self::totals($rows);
        $breakdown = self::breakdown($rows, $f['scope']);

        $total = count($rows);
        $perpage = $f['perpage'] > 0 ? $f['perpage'] : self::PER_PAGE;
        $page = $f['page'];
        if ($page * $perpage >= $total) {
            $page = (int) max(0, ceil($total / $perpage) - 1);
        }

        return [
            'scope'          => $f['scope'],
            'kpis'           => $kpis,
            'currency'       => $currency,
            'currencies'     => $currencies,
            'breakdown'      => $breakdown,
            'breakdownlabel' => self::breakdown_label($f['scope']),
            'rows'           => array_slice($rows, $page * $perpage, $perpage),
            'total'          => $total,
            'page'           => $page,
            'perpage'        => $perpage,
            'truncated'      => $examined >= self::MAX_ROWS,
        ];
    }

    /**
     * Every row the filters match, for a CSV export — no paging.
     *
     * @param array $filters
     * @return array {currency, rows}
     */
    public static function all_rows(array $filters): array {
        $f = self::normalise($filters);
        $rows = self::rows($f, $currency, $currencies, $examined);

        return ['currency' => $currency, 'rows' => $rows];
    }

    /**
     * The matching rows, decorated, in newest-first order.
     *
     * @param array $f normalised filters
     * @param string|null $currency out: the currency the report settled on
     * @param array|null $currencies out: every currency present in range
     * @param int|null $examined out: how many raw transactions were read
     * @return array
     */
    private static function rows(array $f, &$currency, &$currencies, &$examined): array {
        $records = self::fetch($f);
        $examined = count($records);

        // Which currencies are in range at all. One is the overwhelmingly common case; the
        // selector only appears on a site that prices per country in more than one of them.
        $seen = [];
        foreach ($records as $rec) {
            $seen[$rec->currency] = true;
        }
        $currencies = array_keys($seen);
        sort($currencies);

        $currency = $f['currency'];
        if ($currency === '' || !in_array($currency, $currencies, true)) {
            $currency = self::dominant_currency($records)
                ?: ((string) get_config('local_payments', 'default_currency') ?: 'EGP');
        }

        // Summing money across currencies would be a lie, so the report is always about one.
        $rows = [];
        foreach ($records as $rec) {
            if ($rec->currency !== $currency) {
                continue;
            }
            $row = self::decorate($rec);
            if ($f['source'] !== '' && $row['source'] !== $f['source']) {
                continue;
            }
            $rows[] = $row;
        }

        // Newest first: a financial report is read from today backwards.
        usort($rows, static function (array $a, array $b): int {
            return ($b['time'] <=> $a['time']) ?: ($b['id'] <=> $a['id']);
        });

        return $rows;
    }

    /**
     * Fill in the defaults a caller left out.
     *
     * @param array $filters
     * @return array
     */
    private static function normalise(array $filters): array {
        $scope = self::scope((string) ($filters['scope'] ?? self::SCOPE_ALL));
        $state = (string) ($filters['state'] ?? self::STATE_PAID);
        $valid = [self::STATE_PAID, self::STATE_COMPLETED, self::STATE_REFUNDED,
            self::STATE_LOST, self::STATE_ALL];
        if (!in_array($state, $valid, true)) {
            $state = self::STATE_PAID;
        }
        // The refunds scope is about money that went back; any other state contradicts it.
        if ($scope === self::SCOPE_REFUNDS) {
            $state = self::STATE_REFUNDED;
        }

        return [
            'scope'    => $scope,
            'state'    => $state,
            'from'     => max(0, (int) ($filters['from'] ?? 0)),
            'to'       => max(0, (int) ($filters['to'] ?? 0)),
            'q'        => trim((string) ($filters['q'] ?? '')),
            'currency' => strtoupper(trim((string) ($filters['currency'] ?? ''))),
            'itemid'   => max(0, (int) ($filters['itemid'] ?? 0)),
            'source'   => trim((string) ($filters['source'] ?? '')),
            'page'     => max(0, (int) ($filters['page'] ?? 0)),
            'perpage'  => (int) ($filters['perpage'] ?? self::PER_PAGE),
        ];
    }

    /**
     * The one query: transactions in scope, with their buyer and their refunds attached.
     *
     * @param array $f normalised filters
     * @return \stdClass[]
     */
    private static function fetch(array $f): array {
        global $DB;

        $params = [];
        $where = [];

        // -- state ------------------------------------------------------------------
        if ($f['state'] === self::STATE_PAID) {
            [$insql, $inparams] = $DB->get_in_or_equal(self::paid_statuses(), SQL_PARAMS_NAMED, 'st');
            $where[] = "t.status $insql";
            $params += $inparams;
        } else if ($f['state'] === self::STATE_COMPLETED) {
            $where[] = 't.status = :stcompleted';
            $params['stcompleted'] = 'completed';
        } else if ($f['state'] === self::STATE_REFUNDED) {
            $where[] = '(t.status IN (:strefunded, :stpartial) OR rf.refundcount IS NOT NULL)';
            $params['strefunded'] = 'refunded';
            $params['stpartial'] = 'partially_refunded';
        } else if ($f['state'] === self::STATE_LOST) {
            [$insql, $inparams] = $DB->get_in_or_equal(self::lost_statuses(), SQL_PARAMS_NAMED, 'st');
            $where[] = "t.status $insql";
            $params += $inparams;
        }

        // -- scope ------------------------------------------------------------------
        // Subscription checkouts write courseid = 0 as a sentinel (local_payments\manager), so
        // the split is a plain integer test rather than a LIKE over the metadata JSON.
        switch ($f['scope']) {
            case self::SCOPE_COURSES:
                $where[] = 't.courseid > 0';
                if ($f['itemid'] > 0) {
                    $where[] = 't.courseid = :fitem';
                    $params['fitem'] = $f['itemid'];
                }
                break;

            case self::SCOPE_SUBSCRIPTIONS:
                $where[] = 't.courseid = 0';
                break;

            case self::SCOPE_COUPONS:
                $where[] = 'EXISTS (SELECT 1 FROM {nit_coupon_usage} cu
                                     WHERE cu.transactionid = t.id AND cu.discount_amount > 0'
                        . ($f['itemid'] > 0 ? ' AND cu.couponid = :fitem' : '') . ')';
                if ($f['itemid'] > 0) {
                    $params['fitem'] = $f['itemid'];
                }
                break;

            case self::SCOPE_OFFERS:
                $where[] = 'EXISTS (SELECT 1 FROM {nit_offer_usage} ou
                                     WHERE ou.transactionid = t.id AND ou.discount_amount > 0'
                        . ($f['itemid'] > 0 ? ' AND ou.offerid = :fitem' : '') . ')';
                if ($f['itemid'] > 0) {
                    $params['fitem'] = $f['itemid'];
                }
                break;

            case self::SCOPE_REFUNDS:
                $where[] = '(t.status IN (:rstrefunded, :rstpartial) OR rf.refundcount IS NOT NULL)';
                $params['rstrefunded'] = 'refunded';
                $params['rstpartial'] = 'partially_refunded';
                break;
        }

        // -- window -----------------------------------------------------------------
        if ($f['from'] > 0) {
            $where[] = 't.timecreated >= :ffrom';
            $params['ffrom'] = $f['from'];
        }
        if ($f['to'] > 0) {
            $where[] = 't.timecreated <= :fto';
            $params['fto'] = $f['to'];
        }

        // -- free text: buyer, e-mail or order reference ----------------------------
        if ($f['q'] !== '') {
            $like = [];
            $needle = '%' . $DB->sql_like_escape($f['q']) . '%';
            foreach (['u.firstname', 'u.lastname', 'u.email', 't.order_id', 't.provider_txn_id'] as $i => $field) {
                $name = 'q' . $i;
                $like[] = $DB->sql_like($field, ':' . $name, false, false);
                $params[$name] = $needle;
            }
            // The buyer's full name as one string, so "ahmed ali" matches across the two columns.
            $fullname = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
            $like[] = $DB->sql_like($fullname, ':qfull', false, false);
            $params['qfull'] = $needle;

            $where[] = '(' . implode(' OR ', $like) . ')';
        }

        // Refunds are summed per transaction first: a purchase can be given back in parts, and
        // joining the rows directly would multiply the order out across them.
        $refundjoin = 'LEFT JOIN (
                SELECT r.transaction_id,
                       SUM(COALESCE(r.amount, 0)) AS refunded,
                       COUNT(1)                   AS refundcount,
                       MAX(r.timecreated)         AS refundtime,
                       MIN(CASE WHEN r.gateway_code = :manualcode THEN 1 ELSE 0 END) AS allmanual
                  FROM {local_payments_refunds} r
                 WHERE r.status = :refundok
              GROUP BY r.transaction_id
            ) rf ON rf.transaction_id = t.id';
        $params['refundok'] = 'completed';
        $params['manualcode'] = self::REFUND_MANUAL;

        $sql = "SELECT t.id, t.userid, t.courseid, t.amount, t.original_amount, t.currency,
                       t.status, t.order_id, t.metadata, t.country, t.timecreated, t.timemodified,
                       u.firstname, u.lastname, u.email,
                       rf.refunded, rf.refundcount, rf.refundtime, rf.allmanual
                  FROM {local_payments_transactions} t
                  JOIN {user} u ON u.id = t.userid
                  $refundjoin
                 WHERE " . ($where ? implode(' AND ', $where) : '1 = 1') . "
              ORDER BY t.timecreated DESC, t.id DESC";

        return array_values($DB->get_records_sql($sql, $params, 0, self::MAX_ROWS));
    }

    /**
     * Turn a raw transaction into a report row: what it was, what it cost, what discounted it.
     *
     * @param \stdClass $rec
     * @return array
     */
    private static function decorate(\stdClass $rec): array {
        $meta = json_decode((string) $rec->metadata, true);
        $meta = is_array($meta) ? $meta : [];
        $discount = isset($meta['discount']) && is_array($meta['discount']) ? $meta['discount'] : [];

        $net = round((float) $rec->amount, 2);

        // The list price, in falling order of trustworthiness: what the checkout recorded, the
        // column the gateway wrote, then the amount itself (an order with no discount at all).
        $gross = (float) ($discount['original'] ?? 0);
        if ($gross <= 0) {
            $gross = (float) ($rec->original_amount ?? 0);
        }
        if ($gross <= 0) {
            $gross = $net;
        }
        $gross = round($gross, 2);
        $cut = round(max(0.0, $gross - $net), 2);

        $coupondiscount = round((float) ($discount['coupon_discount'] ?? 0), 2);
        $offerdiscount = round((float) ($discount['offer_discount'] ?? 0), 2);
        $couponcode = (string) ($discount['coupon_code'] ?? ($meta['coupon_code'] ?? ''));

        $offernames = [];
        foreach (($discount['offers'] ?? []) as $offer) {
            if (is_array($offer) && !empty($offer['name'])) {
                $offernames[] = (string) $offer['name'];
            }
        }
        if (!$offernames && !empty($discount['offer_name'])) {
            $offernames[] = (string) $discount['offer_name'];
        }

        $itemtype = (string) ($meta['item_type'] ?? '');
        if ($itemtype === '') {
            $itemtype = ((int) $rec->courseid > 0) ? 'course' : 'subscription';
        }
        $itemid = (int) ($meta['item_id'] ?? 0) ?: (int) $rec->courseid;

        // A coupon and an offer never stack (AC-4.12.6, discount_manager::resolve): the larger
        // wins outright. Rows written before that rule can carry both, so the larger is what
        // names the row and the other is only mentioned in the detail text.
        if ($coupondiscount > 0 && $coupondiscount >= $offerdiscount) {
            $channel = 'coupon';
        } else if ($offerdiscount > 0) {
            $channel = 'offer';
        } else {
            $channel = 'full';
        }

        $detail = [];
        if ($coupondiscount > 0 && $couponcode !== '') {
            $detail[] = get_string('rep_detail_coupon', 'local_nit_finance', (object) [
                'code' => $couponcode,
                'amount' => self::money($coupondiscount),
            ]);
        }
        if ($offerdiscount > 0) {
            $detail[] = get_string('rep_detail_offer', 'local_nit_finance', (object) [
                'name' => $offernames ? implode(', ', $offernames) : get_string('rep_offer', 'local_nit_finance'),
                'amount' => self::money($offerdiscount),
            ]);
        }
        if (!empty($discount['coupon_superseded']) && $couponcode !== '' && $coupondiscount <= 0) {
            $detail[] = get_string('rep_detail_couponbeaten', 'local_nit_finance', $couponcode);
        }

        // What was given back. A transaction marked refunded with no refund row behind it was
        // revoked before those rows were written; it is still money out, and saying so is more
        // honest than reporting zero.
        $refunded = round((float) ($rec->refunded ?? 0), 2);
        $refundkind = '';
        if ($rec->refundcount !== null) {
            $refundkind = ((int) $rec->allmanual === 1) ? 'manual' : 'gateway';
            if ($refunded <= 0) {
                // A void records no amount: the whole order came back.
                $refunded = $net;
            }
        } else if (in_array($rec->status, ['refunded', 'partially_refunded'], true)) {
            $refunded = $net;
            $refundkind = 'unrecorded';
        }
        $refunded = round(min($refunded, $net), 2);

        // An attempt that never paid is not revenue, however much it was for. Its value is
        // carried in `lost` instead of `net`, so that "Every attempt" and "Never paid" stay
        // honest views rather than ones that quietly count abandoned baskets as income.
        $paid = in_array($rec->status, self::paid_statuses(), true);

        return [
            'id'              => (int) $rec->id,
            'order'           => (string) $rec->order_id,
            'time'            => (int) $rec->timecreated,
            'timeformatted'   => userdate((int) $rec->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            'userid'          => (int) $rec->userid,
            'user'            => fullname($rec),
            'email'           => (string) $rec->email,
            'itemtype'        => $itemtype,
            'itemid'          => $itemid,
            'item'            => self::item_name($itemtype, $itemid, $meta),
            'source'          => $itemtype . ':' . $channel,
            'sourcelabel'     => self::source_label($itemtype . ':' . $channel),
            'channel'         => $channel,
            'detail'          => implode(' · ', $detail),
            'couponcode'      => $couponcode,
            'coupondiscount'  => $coupondiscount,
            'offernames'      => implode(', ', $offernames),
            'offerdiscount'   => $offerdiscount,
            'paid'            => $paid,
            'gross'           => $paid ? $gross : 0.0,
            'discount'        => $paid ? $cut : 0.0,
            'net'             => $paid ? $net : 0.0,
            'lost'            => $paid ? 0.0 : $net,
            'refunded'        => $refunded,
            'refundkind'      => $refundkind,
            'refundkindlabel' => $refundkind === '' ? '' : get_string('rep_refund_' . $refundkind, 'local_nit_finance'),
            'refundtime'      => (int) ($rec->refundtime ?? 0),
            'earned'          => $paid ? round($net - $refunded, 2) : 0.0,
            'status'          => (string) $rec->status,
            'statuslabel'     => self::status_label((string) $rec->status),
            'country'         => (string) $rec->country,
            'currency'        => (string) $rec->currency,
        ];
    }

    /**
     * Add the rows up.
     *
     * @param array $rows
     * @return array
     */
    private static function totals(array $rows): array {
        $gross = $discount = $net = $refunded = $lost = 0.0;
        $learners = [];
        $refundorders = 0;
        $paidorders = 0;
        foreach ($rows as $row) {
            $gross += $row['gross'];
            $discount += $row['discount'];
            $net += $row['net'];
            $lost += $row['lost'];
            $refunded += $row['refunded'];
            $learners[$row['userid']] = true;
            if ($row['refunded'] > 0) {
                $refundorders++;
            }
            if ($row['paid']) {
                $paidorders++;
            }
        }

        return [
            'gross'        => round($gross, 2),
            'discount'     => round($discount, 2),
            'net'          => round($net, 2),
            'lost'         => round($lost, 2),
            'refunded'     => round($refunded, 2),
            'earned'       => round($net - $refunded, 2),
            'orders'       => count($rows),
            'paidorders'   => $paidorders,
            'lostorders'   => count($rows) - $paidorders,
            'learners'     => count($learners),
            'refundorders' => $refundorders,
            // Averaged over the orders that actually paid: dividing by abandoned baskets too
            // would report a basket size nobody ever paid.
            'average'      => $paidorders > 0 ? round($net / $paidorders, 2) : 0.0,
        ];
    }

    /**
     * Group the rows by whatever this scope exists to compare.
     *
     * The master page wants "where did the money come from?" — course or plan, at full price or
     * because a coupon or an offer brought someone in. A single-subject page wants the same
     * totals per course, per plan, per coupon or per offer. Same columns either way, which is
     * what lets one table serve all six.
     *
     * @param array $rows
     * @param string $scope
     * @return array
     */
    private static function breakdown(array $rows, string $scope): array {
        $groups = [];
        foreach ($rows as $row) {
            foreach (self::keys_for($row, $scope) as $key => $label) {
                if (!isset($groups[$key])) {
                    $groups[$key] = [
                        'key' => (string) $key, 'label' => $label,
                        'orders' => 0, 'learners' => [], 'gross' => 0.0,
                        'discount' => 0.0, 'net' => 0.0, 'lost' => 0.0, 'refunded' => 0.0,
                    ];
                }
                $groups[$key]['orders']++;
                $groups[$key]['learners'][$row['userid']] = true;
                $groups[$key]['gross'] += $row['gross'];
                $groups[$key]['discount'] += $row['discount'];
                $groups[$key]['net'] += $row['net'];
                $groups[$key]['lost'] += $row['lost'];
                $groups[$key]['refunded'] += $row['refunded'];
            }
        }

        $out = [];
        foreach ($groups as $g) {
            $g['learners'] = count($g['learners']);
            foreach (['gross', 'discount', 'net', 'lost', 'refunded'] as $key) {
                $g[$key] = round($g[$key], 2);
            }
            $g['earned'] = round($g['net'] - $g['refunded'], 2);
            $out[] = $g;
        }

        // Biggest earner first: the answer to "where does the money come from" is the top row.
        usort($out, static function (array $a, array $b): int {
            return ($b['earned'] <=> $a['earned']) ?: ($b['net'] <=> $a['net']);
        });

        return $out;
    }

    /**
     * The group key(s) one row contributes to, under this scope.
     *
     * @param array $row
     * @param string $scope
     * @return array key => label
     */
    private static function keys_for(array $row, string $scope): array {
        switch ($scope) {
            case self::SCOPE_COURSES:
            case self::SCOPE_SUBSCRIPTIONS:
                $key = $row['itemtype'] . '-' . $row['itemid'];
                return [$key => $row['item'] !== '' ? $row['item'] : get_string('rep_itemgone', 'local_nit_finance')];

            case self::SCOPE_COUPONS:
                $code = $row['couponcode'] !== '' ? $row['couponcode'] : get_string('rep_nocode', 'local_nit_finance');
                return ['c-' . $code => $code];

            case self::SCOPE_OFFERS:
                $name = $row['offernames'] !== '' ? $row['offernames'] : get_string('rep_offer', 'local_nit_finance');
                return ['o-' . $name => $name];

            case self::SCOPE_REFUNDS:
                $kind = $row['refundkind'] !== '' ? $row['refundkind'] : 'unrecorded';
                return ['r-' . $kind => get_string('rep_refund_' . $kind, 'local_nit_finance')];

            default:
                return [$row['source'] => $row['sourcelabel']];
        }
    }

    /**
     * What the breakdown table is grouping by, for its heading.
     *
     * @param string $scope
     * @return string
     */
    private static function breakdown_label(string $scope): string {
        $map = [
            self::SCOPE_COURSES => 'rep_by_course',
            self::SCOPE_SUBSCRIPTIONS => 'rep_by_plan',
            self::SCOPE_COUPONS => 'rep_by_coupon',
            self::SCOPE_OFFERS => 'rep_by_offer',
            self::SCOPE_REFUNDS => 'rep_by_refundkind',
        ];
        return get_string($map[$scope] ?? 'rep_by_source', 'local_nit_finance');
    }

    // -- Labels ----------------------------------------------------------------------

    /**
     * "Course — coupon", "Plan — full price": what was sold, and what brought the buyer in.
     *
     * @param string $source itemtype:channel
     * @return string
     */
    public static function source_label(string $source): string {
        [$itemtype, $channel] = array_pad(explode(':', $source, 2), 2, 'full');
        $item = $itemtype === 'subscription'
            ? get_string('rep_item_subscription', 'local_nit_finance')
            : get_string('rep_item_course', 'local_nit_finance');
        $known = in_array($channel, ['full', 'coupon', 'offer'], true) ? $channel : 'full';

        return get_string('rep_source_' . $known, 'local_nit_finance', $item);
    }

    /**
     * A transaction status in words, using local_payments' own wording where it has one.
     *
     * @param string $status
     * @return string
     */
    public static function status_label(string $status): string {
        $manager = get_string_manager();
        if ($manager->string_exists('status_' . $status, 'local_payments')) {
            return get_string('status_' . $status, 'local_payments');
        }
        if ($manager->string_exists('rep_status_' . $status, 'local_nit_finance')) {
            return get_string('rep_status_' . $status, 'local_nit_finance');
        }
        return $status;
    }

    /**
     * Name the thing that was bought.
     *
     * The checkout stores the name on the transaction, which is what keeps a report readable
     * after a course or a plan is deleted; the live record is preferred while it exists.
     *
     * @param string $itemtype
     * @param int $itemid
     * @param array $meta decoded transaction metadata
     * @return string
     */
    private static function item_name(string $itemtype, int $itemid, array $meta): string {
        global $DB;
        static $cache = [];

        $key = $itemtype . ':' . $itemid;
        if (!array_key_exists($key, $cache)) {
            $raw = false;
            if ($itemid > 0) {
                $raw = $itemtype === 'subscription'
                    ? $DB->get_field('nit_subscription', 'name', ['id' => $itemid], IGNORE_MISSING)
                    : $DB->get_field('course', 'fullname', ['id' => $itemid], IGNORE_MISSING);
            }
            $cache[$key] = $raw === false ? '' : self::resolve_lang((string) $raw);
        }

        if ($cache[$key] !== '') {
            return $cache[$key];
        }

        $fallback = (string) ($meta['course_name'] ?? ($meta['subscription_name'] ?? ''));
        return $fallback === '' ? '' : self::resolve_lang($fallback);
    }

    /**
     * Names are stored bilingually in one field and filter_multilang2 is not installed here,
     * so nothing else would pick the right half.
     *
     * @param string $text
     * @return string
     */
    private static function resolve_lang(string $text): string {
        if (class_exists('\local_payments\multilang')) {
            return \local_payments\multilang::resolve($text);
        }
        return format_string($text);
    }

    /**
     * The currency most of this money is in.
     *
     * @param array $records
     * @return string
     */
    private static function dominant_currency(array $records): string {
        $count = [];
        foreach ($records as $rec) {
            $count[$rec->currency] = ($count[$rec->currency] ?? 0) + 1;
        }
        if (!$count) {
            return '';
        }
        arsort($count);
        return (string) array_key_first($count);
    }

    /**
     * A money amount as a plain decimal string.
     *
     * @param float $amount
     * @return string
     */
    public static function money(float $amount): string {
        return number_format($amount, 2, '.', ',');
    }

    // -- Filter option lists ---------------------------------------------------------

    /**
     * What to put in this scope's "narrow to one of these" dropdown.
     *
     * @param string $scope
     * @return array [{id, name}]
     */
    public static function options(string $scope): array {
        global $DB;

        switch ($scope) {
            case self::SCOPE_COURSES:
                $sql = "SELECT DISTINCT c.id, c.fullname AS name
                          FROM {local_payments_transactions} t
                          JOIN {course} c ON c.id = t.courseid
                         WHERE t.courseid > 0
                      ORDER BY c.fullname";
                break;

            case self::SCOPE_COUPONS:
                $sql = "SELECT id, code AS name FROM {nit_coupon} ORDER BY code";
                break;

            case self::SCOPE_OFFERS:
                $sql = "SELECT id, name FROM {nit_offer} ORDER BY name";
                break;

            default:
                return [];
        }

        $out = [];
        foreach ($DB->get_records_sql($sql) as $rec) {
            $out[] = ['id' => (int) $rec->id, 'name' => self::resolve_lang((string) $rec->name)];
        }
        return $out;
    }
}
