<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only financial reporting for the admin "Financial Reports" page (manage_withdrawals.php).
 *
 * One method per tab: overview (platform money), packages, subscriptions, coupons, offers.
 * The CRUD for each area stays on its own manage_*.php page — this class never writes.
 *
 * All methods are admin-only (gated by local/academy:manageplatform at the API layer) and accept the
 * same assoc filter array: 'from' / 'to' (unix, inclusive, applied to the row's timecreated).
 */
class finance_report_manager {

    // ──────────────────────────────────────────────────────────────────────────
    // Shared helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Build a date-window SQL fragment on $column.
     *
     * @param array  $filters recognised keys: from, to (unix)
     * @param string $column  fully-qualified column (e.g. 'p.timecreated')
     * @param string $prefix  unique prefix so several windows can coexist in one query
     * @return array [sql fragment starting with ' AND ...' or '', params]
     */
    private static function window(array $filters, $column, $prefix = 'w') {
        $sql = '';
        $params = array();
        if (!empty($filters['from'])) {
            $sql .= " AND $column >= :{$prefix}from";
            $params[$prefix . 'from'] = (int)$filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= " AND $column <= :{$prefix}to";
            $params[$prefix . 'to'] = (int)$filters['to'];
        }
        return array($sql, $params);
    }

    /** Sum a numeric column with an optional date window. */
    private static function sum($table, $column, $where, array $params, array $filters, $datecolumn = 'timecreated') {
        global $DB;
        list($wsql, $wparams) = self::window($filters, $datecolumn);
        return (float)$DB->get_field_sql(
            "SELECT COALESCE(SUM($column),0) FROM {" . $table . "} WHERE $where $wsql",
            array_merge($params, $wparams));
    }

    /** Count rows with an optional date window. */
    private static function count($table, $where, array $params, array $filters, $datecolumn = 'timecreated') {
        global $DB;
        list($wsql, $wparams) = self::window($filters, $datecolumn);
        return (int)$DB->count_records_select($table, "$where $wsql", array_merge($params, $wparams));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tab 1: overview — where the platform's money currently is
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Platform-wide money picture: wallet balances, revenue by source, discounts given, payouts.
     *
     * The wallet block comes from finance_manager (same numbers the old withdrawals page showed) and
     * is deliberately NOT date-filtered — it is a "right now" balance. Everything else honours the
     * date window so admins can ask "what did we take in last month?".
     *
     * @param array $filters from, to (unix)
     */
    public static function overview(array $filters = array()) {
        $wallet = finance_manager::get_platform_wallet();

        // Revenue actually collected, by source (successful payments only).
        $packagerevenue = self::sum('academy_payments', 'amount', "status = 'success'", array(), $filters);
        $subrevenue     = self::sum('academy_sub_payments', 'amount', "status = 'success'", array(), $filters);
        // Single-course sales live in local_payments, not in a plugin table — see course_transactions().
        $coursetx       = self::course_transactions($filters);
        $courserevenue  = 0.0;
        $coursecount    = 0;
        foreach ($coursetx as $t) {
            if ($t->status === \local_payments\status_machine::COMPLETED) {
                $courserevenue += (float)$t->amount;
                $coursecount++;
            }
        }

        // Discounts given away — gross revenue would have been this much higher.
        $coupondiscount = self::sum('academy_coupon_usages', 'discount_amount', '1=1', array(), $filters);
        $offerdiscount  = self::sum('academy_offer_usages', 'discount_amount', '1=1', array(), $filters);

        // Payouts to teachers, by withdrawal state.
        $payouts = array();
        foreach (array('pending', 'approved', 'paid', 'rejected') as $status) {
            $payouts[$status] = array(
                'count'  => self::count('academy_withdrawals', 'status = :st', array('st' => $status), $filters),
                'amount' => round(self::sum('academy_withdrawals', 'amount', 'status = :st',
                    array('st' => $status), $filters), 2),
            );
        }

        $revenue = array(
            'packages'      => round($packagerevenue, 2),
            'subscriptions' => round($subrevenue, 2),
            'courses'       => round($courserevenue, 2),
            'total'         => round($packagerevenue + $subrevenue + $courserevenue, 2),
        );
        $discounts = array(
            'coupons' => round($coupondiscount, 2),
            'offers'  => round($offerdiscount, 2),
            'total'   => round($coupondiscount + $offerdiscount, 2),
        );
        // Gross = what customers would have paid at list price, before any discount.
        $discounts['gross_before_discount'] = round($revenue['total'] + $discounts['total'], 2);

        return array(
            'wallet'    => $wallet,
            'revenue'   => $revenue,
            'discounts' => $discounts,
            'payouts'   => $payouts,
            'volume'    => array(
                'package_purchases'      => self::count('academy_package_purchases', '1=1', array(), $filters),
                'subscription_purchases' => self::count('academy_sub_purchases', '1=1', array(), $filters),
                'course_purchases'       => $coursecount,
                'coupon_redemptions'     => self::count('academy_coupon_usages', '1=1', array(), $filters),
                'offer_applications'     => self::count('academy_offer_usages', '1=1', array(), $filters),
            ),
            'monthly'   => self::monthly_revenue($filters),
        );
    }

    /**
     * Revenue per calendar month, split packages / subscriptions / courses.
     *
     * Bucketing is done in PHP rather than SQL date functions so the query stays portable across
     * the DB engines Moodle supports. Volumes here are small (one row per successful payment).
     *
     * @return array list of ['month' => 'YYYY-MM', 'packages', 'subscriptions', 'courses', 'total']
     *               oldest first
     */
    private static function monthly_revenue(array $filters) {
        global $DB;

        // Default to the last 12 months when no window was given.
        if (empty($filters['from']) && empty($filters['to'])) {
            $filters = array('from' => strtotime('-11 months', strtotime(date('Y-m-01'))));
        }

        $buckets = array();
        $blank = array('packages' => 0.0, 'subscriptions' => 0.0, 'courses' => 0.0);
        $add = function ($month, $key, $amount) use (&$buckets, $blank) {
            if (!isset($buckets[$month])) {
                $buckets[$month] = array_merge(array('month' => $month), $blank);
            }
            $buckets[$month][$key] += $amount;
        };

        $sources = array(
            'packages'      => 'academy_payments',
            'subscriptions' => 'academy_sub_payments',
        );
        foreach ($sources as $key => $table) {
            list($wsql, $wparams) = self::window($filters, 'timecreated');
            $rows = $DB->get_records_sql(
                "SELECT id, amount, timecreated FROM {" . $table . "} WHERE status = 'success' $wsql", $wparams);
            foreach ($rows as $r) {
                $add(date('Y-m', (int)$r->timecreated), $key, (float)$r->amount);
            }
        }
        foreach (self::course_transactions($filters) as $t) {
            if ($t->status === \local_payments\status_machine::COMPLETED) {
                $add(date('Y-m', (int)$t->timecreated), 'courses', (float)$t->amount);
            }
        }

        ksort($buckets);
        $out = array();
        foreach ($buckets as $b) {
            $b['packages']      = round($b['packages'], 2);
            $b['subscriptions'] = round($b['subscriptions'], 2);
            $b['courses']       = round($b['courses'], 2);
            $b['total']         = round($b['packages'] + $b['subscriptions'] + $b['courses'], 2);
            $out[] = $b;
        }
        return $out;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tab 2: packages
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * One row per package: how many were sold, for how much, and how much of the Flex is still owed.
     *
     * "Unused value" is the liability side — Flex the student paid for but has not consumed yet.
     *
     * @param array $filters from, to (unix, on purchase time)
     */
    public static function packages_report(array $filters = array()) {
        global $DB;

        list($wsql, $wparams) = self::window($filters, 'pu.timecreated');
        // A recordset, not get_records_sql(): the result has one row per *purchase*, so the leading
        // package id repeats and get_records_sql() would collapse all but the last row of each package.
        $sql = "SELECT p.id, p.name, p.price, p.flex_count, p.status,
                       pu.id AS purchaseid, pu.price_paid, pu.source, pu.flex_count AS bought_flex,
                       pu.remaining_flex, pu.reserved_flex, pu.consumed_flex
                  FROM {academy_packages} p
             LEFT JOIN {academy_package_purchases} pu ON pu.packageid = p.id $wsql
              ORDER BY p.id";
        $rs = $DB->get_recordset_sql($sql, $wparams);

        $packages = array();
        foreach ($rs as $r) {
            if (!isset($packages[$r->id])) {
                $packages[$r->id] = array(
                    'id'             => (int)$r->id,
                    // format_string() resolves {mlang} to the requested language (api.php sets
                    // $SESSION->forcelang from ?alang before dispatch). Without it the raw
                    // "{mlang en}...{mlang}{mlang ar}...{mlang}" markup leaks into the table.
                    'name'           => format_string($r->name),
                    'price'          => (float)$r->price,
                    'flex_count'     => (int)$r->flex_count,
                    'status'         => $r->status,
                    'sales'          => 0,
                    'online'         => 0,
                    'assigned'       => 0,
                    'revenue'        => 0.0,
                    'flex_sold'      => 0,
                    'flex_consumed'  => 0,
                    'flex_unused'    => 0,
                    'unused_value'   => 0.0,
                );
            }
            if (empty($r->purchaseid)) {
                continue; // Package with no purchases in this window.
            }
            $p = &$packages[$r->id];
            $p['sales']++;
            $p[$r->source === 'admin_assigned' ? 'assigned' : 'online']++;
            $p['revenue']       += (float)$r->price_paid;
            $p['flex_sold']     += (int)$r->bought_flex;
            $p['flex_consumed'] += (int)$r->consumed_flex;
            $unused = (int)$r->remaining_flex + (int)$r->reserved_flex;
            $p['flex_unused']   += $unused;
            if ((int)$r->bought_flex > 0) {
                $p['unused_value'] += $unused * ((float)$r->price_paid / (int)$r->bought_flex);
            }
            unset($p);
        }
        $rs->close();

        $summary = array('packages' => 0, 'sales' => 0, 'revenue' => 0.0,
            'flex_sold' => 0, 'flex_consumed' => 0, 'unused_value' => 0.0);
        $out = array();
        foreach ($packages as $p) {
            $p['revenue']      = round($p['revenue'], 2);
            $p['unused_value'] = round($p['unused_value'], 2);
            $p['avg_price']    = $p['sales'] ? round($p['revenue'] / $p['sales'], 2) : 0.0;
            $out[] = $p;
            $summary['packages']++;
            $summary['sales']         += $p['sales'];
            $summary['revenue']       += $p['revenue'];
            $summary['flex_sold']     += $p['flex_sold'];
            $summary['flex_consumed'] += $p['flex_consumed'];
            $summary['unused_value']  += $p['unused_value'];
        }
        $summary['revenue']      = round($summary['revenue'], 2);
        $summary['unused_value'] = round($summary['unused_value'], 2);

        return array('rows' => $out, 'summary' => $summary);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tab 3: subscriptions
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * One row per subscription plan, splitting normal vs B2B (seat-based) sales.
     *
     * @param array $filters from, to (unix, on purchase time)
     */
    public static function subscriptions_report(array $filters = array()) {
        global $DB;

        list($wsql, $wparams) = self::window($filters, 'sp.timecreated');
        $sql = "SELECT s.id, s.name, s.price, s.duration_days, s.status, s.b2b_enabled,
                       sp.id AS purchaseid, sp.price_paid, sp.type, sp.seats, sp.status AS pstatus,
                       sp.base_price, sp.discount_percent
                  FROM {academy_subscriptions} s
             LEFT JOIN {academy_sub_purchases} sp ON sp.subscriptionid = s.id $wsql
              ORDER BY s.id";
        // Recordset: one row per purchase, so the leading plan id repeats (see packages_report()).
        $rs = $DB->get_recordset_sql($sql, $wparams);

        $plans = array();
        foreach ($rs as $r) {
            if (!isset($plans[$r->id])) {
                $plans[$r->id] = array(
                    'id'            => (int)$r->id,
                    'name'          => format_string($r->name), // Resolves {mlang}; see packages_report().
                    'price'         => (float)$r->price,
                    'duration_days' => (int)$r->duration_days,
                    'status'        => $r->status,
                    'b2b_enabled'   => (int)$r->b2b_enabled,
                    'sales'          => 0,
                    // The two purchase types are reported side by side rather than merged: a
                    // "normal" sale is one student buying access for themselves, while a "b2b" sale
                    // is an organisation buying N seats its members later claim. Mixing their counts
                    // and revenue makes both meaningless, so each keeps its own set of figures.
                    'normal'         => 0,
                    'normal_revenue' => 0.0,
                    'b2b'            => 0,
                    'b2b_revenue'    => 0.0,
                    'seats_sold'     => 0,
                    'active'         => 0,
                    'expired'        => 0,
                    'cancelled'      => 0,
                    'revenue'        => 0.0,
                    'b2b_discount'   => 0.0,
                );
            }
            if (empty($r->purchaseid)) {
                continue;
            }
            $p = &$plans[$r->id];
            $p['sales']++;
            $p['revenue'] += (float)$r->price_paid;
            if ($r->type === 'b2b') {
                $p['b2b']++;
                $p['b2b_revenue'] += (float)$r->price_paid;
                $p['seats_sold']  += (int)$r->seats;
                // What the seat-option discount cost us, relative to seats × base price.
                $list = (float)$r->base_price * max(1, (int)$r->seats);
                $p['b2b_discount'] += max(0, $list - (float)$r->price_paid);
            } else {
                $p['normal']++;
                $p['normal_revenue'] += (float)$r->price_paid;
            }
            if (isset($p[$r->pstatus])) {
                $p[$r->pstatus]++;
            }
            unset($p);
        }
        $rs->close();

        $summary = array('plans' => 0, 'sales' => 0, 'revenue' => 0.0,
            'normal' => 0, 'normal_revenue' => 0.0,
            'b2b' => 0, 'b2b_revenue' => 0.0,
            'seats_sold' => 0, 'active' => 0, 'b2b_discount' => 0.0);
        $out = array();
        foreach ($plans as $p) {
            $p['revenue']        = round($p['revenue'], 2);
            $p['normal_revenue'] = round($p['normal_revenue'], 2);
            $p['b2b_revenue']    = round($p['b2b_revenue'], 2);
            $p['b2b_discount']   = round($p['b2b_discount'], 2);
            $p['avg_price']      = $p['sales'] ? round($p['revenue'] / $p['sales'], 2) : 0.0;
            // Average per seat is the meaningful B2B unit price — per *sale* would hide the fact
            // that one B2B purchase covers many users.
            $p['b2b_per_seat']   = $p['seats_sold'] ? round($p['b2b_revenue'] / $p['seats_sold'], 2) : 0.0;
            $out[] = $p;
            $summary['plans']++;
            $summary['sales']          += $p['sales'];
            $summary['revenue']        += $p['revenue'];
            $summary['normal']         += $p['normal'];
            $summary['normal_revenue'] += $p['normal_revenue'];
            $summary['b2b']            += $p['b2b'];
            $summary['b2b_revenue']    += $p['b2b_revenue'];
            $summary['seats_sold']     += $p['seats_sold'];
            $summary['active']         += $p['active'];
            $summary['b2b_discount']   += $p['b2b_discount'];
        }
        foreach (array('revenue', 'normal_revenue', 'b2b_revenue', 'b2b_discount') as $k) {
            $summary[$k] = round($summary[$k], 2);
        }

        return array('rows' => $out, 'summary' => $summary);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tab 4: individual courses
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Single-course purchase transactions in the window, whatever their status.
     *
     * Course sales have no plugin table — they are local_payments transactions carrying a courseid,
     * so this mirrors course_purchase_manager::get_all_course_purchases(): filter on courseid, then
     * confirm item_type from the JSON metadata (a package/subscription checkout can also carry a
     * course id, and counting those would double-count revenue already in academy_payments).
     *
     * @param array $filters from, to (unix)
     * @return array list of transaction records (id, userid, courseid, amount, original_amount,
     *               status, timecreated) plus course_fullname
     */
    private static function course_transactions(array $filters) {
        global $DB;

        // The plugin is a hard dependency of the course-purchase flow, but guard anyway so the rest
        // of the Financial Reports page still works on an install without it.
        if (!class_exists('\local_payments\status_machine')) {
            return array();
        }

        list($wsql, $wparams) = self::window($filters, 't.timecreated');
        $sql = "SELECT t.id, t.userid, t.courseid, t.amount, t.original_amount, t.currency,
                       t.status, t.metadata, t.timecreated, c.fullname AS course_fullname
                  FROM {local_payments_transactions} t
             LEFT JOIN {course} c ON c.id = t.courseid
                 WHERE t.courseid > 0 $wsql
              ORDER BY t.timecreated DESC";
        $rows = $DB->get_records_sql($sql, $wparams);

        $out = array();
        foreach ($rows as $r) {
            $meta = json_decode((string)$r->metadata);
            $itemtype = isset($meta->item_type) ? $meta->item_type : 'course';
            if ($itemtype !== 'course') {
                continue;
            }
            $out[] = $r;
        }
        return $out;
    }

    /**
     * One row per course that has ever been sold: units, revenue, discount, and refunds/revocations.
     *
     * Only 'completed' transactions count as revenue. Refunded and cancelled ones are reported
     * separately so an admin can see money that came in and then went back out (a revoked purchase
     * via manage_courses.php lands here as cancelled).
     *
     * @param array $filters from, to (unix, on transaction time)
     */
    public static function courses_report(array $filters = array()) {
        $transactions = self::course_transactions($filters);
        $completed = \local_payments\status_machine::COMPLETED;
        $refunded  = \local_payments\status_machine::REFUNDED;
        $cancelled = \local_payments\status_machine::CANCELLED;

        $courses = array();
        $buyers = array(); // courseid => set of userids, for unique-buyer counts.
        foreach ($transactions as $t) {
            $cid = (int)$t->courseid;
            if (!isset($courses[$cid])) {
                $courses[$cid] = array(
                    'id'              => $cid,
                    'name'            => $t->course_fullname !== null
                        ? format_string($t->course_fullname)
                        : get_string('mc_course_deleted', 'local_academy'),
                    'deleted'         => $t->course_fullname === null ? 1 : 0,
                    'currency'        => $t->currency,
                    'sales'           => 0,
                    'unique_buyers'   => 0,
                    'revenue'         => 0.0,
                    'original_total'  => 0.0,
                    'discount_total'  => 0.0,
                    'refunded_count'  => 0,
                    'refunded_amount' => 0.0,
                    'revoked_count'   => 0,
                    'failed_count'    => 0,
                );
                $buyers[$cid] = array();
            }
            $c = &$courses[$cid];
            $amount   = (float)$t->amount;
            $original = $t->original_amount !== null ? (float)$t->original_amount : $amount;

            if ($t->status === $completed) {
                $c['sales']++;
                $c['revenue']        += $amount;
                $c['original_total'] += $original;
                $c['discount_total'] += max(0, $original - $amount);
                $buyers[$cid][(int)$t->userid] = true;
            } else if ($t->status === $refunded) {
                $c['refunded_count']++;
                $c['refunded_amount'] += $amount;
            } else if ($t->status === $cancelled) {
                $c['revoked_count']++;
            } else {
                $c['failed_count']++; // pending / failed / expired / voided — never granted access.
            }
            unset($c);
        }

        $summary = array('courses' => 0, 'sales' => 0, 'revenue' => 0.0, 'original_total' => 0.0,
            'discount_total' => 0.0, 'refunded_amount' => 0.0, 'net_revenue' => 0.0);
        $out = array();
        foreach ($courses as $c) {
            $c['unique_buyers']   = count($buyers[$c['id']]);
            $c['revenue']         = round($c['revenue'], 2);
            $c['original_total']  = round($c['original_total'], 2);
            $c['discount_total']  = round($c['discount_total'], 2);
            $c['refunded_amount'] = round($c['refunded_amount'], 2);
            // Net = what we actually kept after giving refunds back.
            $c['net_revenue']     = round($c['revenue'] - $c['refunded_amount'], 2);
            $c['avg_price']       = $c['sales'] ? round($c['revenue'] / $c['sales'], 2) : 0.0;
            $out[] = $c;
            $summary['courses']++;
            $summary['sales']           += $c['sales'];
            $summary['revenue']         += $c['revenue'];
            $summary['original_total']  += $c['original_total'];
            $summary['discount_total']  += $c['discount_total'];
            $summary['refunded_amount'] += $c['refunded_amount'];
            $summary['net_revenue']     += $c['net_revenue'];
        }
        // Best sellers first — that is the question this tab is usually opened to answer.
        usort($out, function ($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });
        foreach (array('revenue', 'original_total', 'discount_total', 'refunded_amount', 'net_revenue') as $k) {
            $summary[$k] = round($summary[$k], 2);
        }

        return array('rows' => $out, 'summary' => $summary);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tabs 5 & 6: coupons and offers
    // ──────────────────────────────────────────────────────────────────────────

    /** @param array $filters from, to (unix, on redemption time) */
    public static function coupons_report(array $filters = array()) {
        return self::discount_report('coupon', $filters);
    }

    /** @param array $filters from, to (unix, on application time) */
    public static function offers_report(array $filters = array()) {
        return self::discount_report('offer', $filters);
    }

    /**
     * Coupons and offers share the same usage-table shape, so one query serves both tabs.
     * The only differences are the table names and the label column (code vs name).
     *
     * @param string $kind 'coupon' or 'offer'
     */
    private static function discount_report($kind, array $filters) {
        global $DB;

        $deftable   = $kind === 'coupon' ? 'academy_coupons' : 'academy_offers';
        $usetable   = $kind === 'coupon' ? 'academy_coupon_usages' : 'academy_offer_usages';
        $fk         = $kind === 'coupon' ? 'couponid' : 'offerid';
        $labelfield = $kind === 'coupon' ? 'code' : 'name';

        list($wsql, $wparams) = self::window($filters, 'u.timecreated');
        $sql = "SELECT d.id, d.$labelfield AS label, d.discount_type, d.discount_value,
                       d.startdate, d.enddate, d.status,
                       u.id AS usageid, u.userid, u.item_type,
                       u.original_amount, u.discount_amount, u.final_amount
                  FROM {" . $deftable . "} d
             LEFT JOIN {" . $usetable . "} u ON u.$fk = d.id $wsql
              ORDER BY d.id";
        // Recordset: one row per usage, so the leading definition id repeats (see packages_report()).
        $rs = $DB->get_recordset_sql($sql, $wparams);

        $defs = array();
        $users = array(); // id => set of userids, so we can report unique redeemers.
        foreach ($rs as $r) {
            if (!isset($defs[$r->id])) {
                $defs[$r->id] = array(
                    'id'             => (int)$r->id,
                    // Offer names can be multilang; coupon codes are literal identifiers and must
                    // stay byte-exact, so only the offer side goes through format_string().
                    'label'          => $kind === 'offer' ? format_string($r->label) : $r->label,
                    'discount_type'  => $r->discount_type,
                    'discount_value' => (float)$r->discount_value,
                    'startdate'      => (int)$r->startdate,
                    'enddate'        => (int)$r->enddate,
                    'status'         => $r->status,
                    'uses'           => 0,
                    'unique_users'   => 0,
                    'original_total' => 0.0,
                    'discount_total' => 0.0,
                    'final_total'    => 0.0,
                    'by_item_type'   => array(),
                );
                $users[$r->id] = array();
            }
            if (empty($r->usageid)) {
                continue;
            }
            $d = &$defs[$r->id];
            $d['uses']++;
            $d['original_total'] += (float)$r->original_amount;
            $d['discount_total'] += (float)$r->discount_amount;
            $d['final_total']    += (float)$r->final_amount;
            $users[$r->id][(int)$r->userid] = true;
            $type = $r->item_type ?: 'other';
            $d['by_item_type'][$type] = ($d['by_item_type'][$type] ?? 0) + 1;
            unset($d);
        }
        $rs->close();

        $summary = array('definitions' => 0, 'active' => 0, 'uses' => 0,
            'original_total' => 0.0, 'discount_total' => 0.0, 'final_total' => 0.0);
        $out = array();
        foreach ($defs as $d) {
            $d['unique_users']   = count($users[$d['id']]);
            $d['original_total'] = round($d['original_total'], 2);
            $d['discount_total'] = round($d['discount_total'], 2);
            $d['final_total']    = round($d['final_total'], 2);
            $d['avg_discount']   = $d['uses'] ? round($d['discount_total'] / $d['uses'], 2) : 0.0;
            $out[] = $d;
            $summary['definitions']++;
            if ($d['status'] === 'active') {
                $summary['active']++;
            }
            $summary['uses']           += $d['uses'];
            $summary['original_total'] += $d['original_total'];
            $summary['discount_total'] += $d['discount_total'];
            $summary['final_total']    += $d['final_total'];
        }
        $summary['original_total'] = round($summary['original_total'], 2);
        $summary['discount_total'] = round($summary['discount_total'], 2);
        $summary['final_total']    = round($summary['final_total'], 2);

        return array('rows' => $out, 'summary' => $summary);
    }
}
