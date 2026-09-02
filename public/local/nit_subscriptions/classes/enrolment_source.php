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
 * AC-4.10.5 - every course enrolment is recorded with the source that produced it.
 *
 * Moodle stores *how* a user was enrolled (the enrol plugin) but not *why*: a bought course, a
 * subscription package, a coupon redemption, an automatic offer and an administrator grant all
 * land in {user_enrolments} as the same "manual" row. This class adds the missing column of
 * truth - one {nit_enrol_source} row per enrolment, written the moment the enrolment is created -
 * so the money question ("where did these students come from?") is answerable.
 *
 * Nothing else in the platform has to change to feed it: the classifier reads the evidence the
 * purchase flows already leave behind (a completed local_payments transaction, the coupon/offer
 * usage rows local_nit_commerce writes against it, a nit_sub_purchase whose plan covers the
 * course), so recording is a pure observer on \core\event\user_enrolment_created.
 *
 * Enrolments that predate the feature are classified on demand by {@see backfill()}, in batches,
 * from the same evidence - those rows are flagged `inferred` so the report never presents a
 * reconstruction as a recorded fact.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_subscriptions;

defined('MOODLE_INTERNAL') || die();

/**
 * Records and reports the source behind each course enrolment.
 */
class enrolment_source {

    /** @var string Bought this one course outright (a completed course transaction). */
    const SOURCE_PURCHASE = 'purchase';
    /** @var string Unlocked by a subscription plan / package the user holds. */
    const SOURCE_PACKAGE = 'package';
    /** @var string A purchase whose price was cut by a coupon code the buyer typed. */
    const SOURCE_COUPON = 'coupon';
    /** @var string A purchase whose price was cut by an automatic offer. */
    const SOURCE_OFFER = 'offer';
    /** @var string Granted by an administrator or teacher (manual enrolment by someone else). */
    const SOURCE_ADMIN = 'admin';
    /** @var string The learner enrolled themselves (self enrolment, or a free registration). */
    const SOURCE_SELF = 'self';
    /** @var string Anything else: cohort sync, restore, a cron/CLI enrolment, another plugin. */
    const SOURCE_OTHER = 'other';

    /** @var int How long after a payment completes its enrolment may still be attributed to it. */
    const PURCHASE_GRACE = 900;

    /** @var int Enrolments classified per backfill pass, so opening the report stays cheap. */
    const BACKFILL_BATCH = 1000;

    /** @var int Hard cap on report rows returned in one call. */
    const REPORT_LIMIT = 5000;

    /** @var int Seconds between backfill/prune passes, so clicking filters does not re-run them. */
    const MAINTAIN_EVERY = 60;

    /**
     * Every source key, in the order the report presents them.
     *
     * @return string[]
     */
    public static function sources(): array {
        return [
            self::SOURCE_PURCHASE,
            self::SOURCE_PACKAGE,
            self::SOURCE_COUPON,
            self::SOURCE_OFFER,
            self::SOURCE_ADMIN,
            self::SOURCE_SELF,
            self::SOURCE_OTHER,
        ];
    }

    /**
     * Human label for a source key.
     *
     * @param string $source
     * @return string
     */
    public static function source_label(string $source): string {
        $known = in_array($source, self::sources(), true) ? $source : self::SOURCE_OTHER;
        return get_string('es_source_' . $known, 'local_nit_subscriptions');
    }

    // -- Recording -------------------------------------------------------------------

    /**
     * Record the source of an enrolment that has just been created.
     *
     * Idempotent on the user_enrolments id: replaying the event (or a restore that re-fires it)
     * never doubles a row up.
     *
     * @param int $ueid user_enrolments.id
     * @param int $userid the enrolled user
     * @param int $courseid
     * @param int $actorid the user who performed the enrolment (0 = cron/CLI/no session)
     * @param string $enrolplugin the enrol plugin name from the event ('manual', 'self', ...)
     * @return int the new nit_enrol_source.id, or 0 when there was nothing to write
     */
    public static function record(int $ueid, int $userid, int $courseid, int $actorid,
            string $enrolplugin): int {
        global $DB;

        // ueid is the row's identity - it is what makes recording idempotent and what the
        // backfill joins on - so an enrolment we cannot name is not worth a half-row.
        if ($ueid <= 0 || $userid <= 0 || $courseid <= 0 || $courseid == SITEID) {
            return 0;
        }
        if ($DB->record_exists('nit_enrol_source', ['ueid' => $ueid])) {
            return 0;
        }

        $row = self::classify($userid, $courseid, $actorid, $enrolplugin, time());
        $row['ueid'] = $ueid;
        $row['inferred'] = 0;

        return self::insert($row);
    }

    /**
     * Insert one classified row.
     *
     * @param array $row from {@see classify()}, plus ueid/inferred
     * @return int inserted id
     */
    protected static function insert(array $row): int {
        global $DB;

        $code = $row['code'] ?? null;
        $record = (object) [
            'ueid'          => (int) ($row['ueid'] ?? 0),
            'userid'        => (int) $row['userid'],
            'courseid'      => (int) $row['courseid'],
            'source'        => (string) $row['source'],
            'itemid'        => (int) ($row['itemid'] ?? 0),
            'transactionid' => (int) ($row['transactionid'] ?? 0),
            'couponid'      => (int) ($row['couponid'] ?? 0),
            'offerid'       => (int) ($row['offerid'] ?? 0),
            'code'          => $code !== null ? \core_text::substr((string) $code, 0, 100) : null,
            'amount'        => (float) ($row['amount'] ?? 0),
            'currency'      => ($row['currency'] ?? null) ?: null,
            'grantedby'     => (int) ($row['grantedby'] ?? 0),
            'enrolplugin'   => \core_text::substr((string) ($row['enrolplugin'] ?? ''), 0, 20),
            'inferred'      => !empty($row['inferred']) ? 1 : 0,
            'timecreated'   => (int) ($row['timecreated'] ?? time()),
        ];

        return (int) $DB->insert_record('nit_enrol_source', $record);
    }

    /**
     * Work out why this user ended up enrolled in this course.
     *
     * The order matters and is the business order, not a convenience: a paid route always beats an
     * unpaid one, and within a paid route the *reason the price moved* (coupon, then offer) beats
     * the bare purchase, because that is what a marketing report is asking about.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $actorid who performed the enrolment; 0 when unknown (backfill, cron)
     * @param string $enrolplugin enrol plugin name, '' when unknown
     * @param int $when unix time the enrolment happened
     * @return array insertable row (without ueid/inferred)
     */
    public static function classify(int $userid, int $courseid, int $actorid, string $enrolplugin,
            int $when): array {
        $row = [
            'userid'        => $userid,
            'courseid'      => $courseid,
            'source'        => self::SOURCE_OTHER,
            'itemid'        => 0,
            'transactionid' => 0,
            'couponid'      => 0,
            'offerid'       => 0,
            'code'          => null,
            'amount'        => 0,
            'currency'      => null,
            'grantedby'     => 0,
            'enrolplugin'   => $enrolplugin,
            'timecreated'   => $when > 0 ? $when : time(),
        ];

        // 1. A payment that bought this exact course.
        $txn = self::find_purchase($userid, $courseid, $when);
        if ($txn) {
            $meta = json_decode((string) $txn->metadata);
            $itemtype = isset($meta->item_type) ? (string) $meta->item_type : 'course';

            $row['transactionid'] = (int) $txn->id;
            $row['amount']        = (float) $txn->amount;
            $row['currency']      = (string) $txn->currency;
            $row['itemid']        = (int) ($meta->item_id ?? $courseid);
            $row['source']        = ($itemtype === 'course') ? self::SOURCE_PURCHASE : self::SOURCE_PACKAGE;

            // A coupon the buyer typed, or an offer the site applied for them, is the story of
            // that sale - so it, not "purchase", is the source. The transaction id stays on the
            // row, so the amount and the sale itself are never lost.
            $discount = self::discount_for_transaction((int) $txn->id);
            if ($discount) {
                $row['source']   = $discount['source'];
                $row['couponid'] = $discount['couponid'];
                $row['offerid']  = $discount['offerid'];
                $row['code']     = $discount['code'];
            }
            return $row;
        }

        // 2. A subscription plan / package that covers the course.
        $plan = self::find_covering_plan($userid, $courseid, $when);
        if ($plan) {
            $row['source'] = self::SOURCE_PACKAGE;
            $row['itemid'] = (int) $plan->subscriptionid;
            $row['amount'] = (float) $plan->price_paid;
            return $row;
        }

        // 3. Nobody paid for this seat, so somebody handed it over. Self enrolment - and the free
        //    one-click registration, which runs as the learner - is the learner's own doing;
        //    anything performed by a different account is an administrator grant.
        if (in_array($enrolplugin, ['self', 'guest'], true)) {
            $row['source'] = self::SOURCE_SELF;
            return $row;
        }
        if ($actorid > 0 && $actorid !== $userid) {
            $row['source']    = self::SOURCE_ADMIN;
            $row['grantedby'] = $actorid;
            return $row;
        }
        if ($actorid > 0 && $actorid === $userid) {
            $row['source'] = self::SOURCE_SELF;
            return $row;
        }

        // 4. No actor at all: cron, CLI, restore, cohort sync. "Other" is the honest answer -
        //    except for a manual enrolment, which by definition only a person can create, so the
        //    grant did happen; we simply no longer know who made it.
        $row['source'] = ($enrolplugin === 'manual') ? self::SOURCE_ADMIN : self::SOURCE_OTHER;
        return $row;
    }

    /**
     * The completed payment that most plausibly produced an enrolment at $when.
     *
     * Only COMPLETED counts: a refunded or cancelled transaction had its enrolment taken away, so
     * a live enrolment is not its doing. The grace window absorbs the seconds between the gateway
     * callback marking the sale complete and the enrolment landing.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $when
     * @return \stdClass|false
     */
    protected static function find_purchase(int $userid, int $courseid, int $when) {
        global $DB;

        if (!class_exists('\local_payments\status_machine')) {
            return false;
        }
        $params = [
            'userid'   => $userid,
            'courseid' => $courseid,
            'status'   => \local_payments\status_machine::COMPLETED,
            'cutoff'   => ($when > 0 ? $when : time()) + self::PURCHASE_GRACE,
        ];
        $sql = "SELECT id, amount, currency, metadata, timemodified, timecreated
                  FROM {local_payments_transactions}
                 WHERE userid = :userid AND courseid = :courseid AND status = :status
                       AND timecreated <= :cutoff
              ORDER BY timemodified DESC, id DESC";
        $records = $DB->get_records_sql($sql, $params, 0, 1);

        return $records ? reset($records) : false;
    }

    /**
     * The coupon or offer that moved the price on a transaction, if any.
     *
     * A coupon outranks an offer: the buyer had to go and find the code, which is exactly the
     * campaign a report is trying to measure, whereas an offer applies to everyone anyway.
     *
     * @param int $transactionid
     * @return array|null ['source','couponid','offerid','code']
     */
    protected static function discount_for_transaction(int $transactionid): ?array {
        global $DB;

        if ($transactionid <= 0) {
            return null;
        }

        $dbman = $DB->get_manager();
        if ($dbman->table_exists('nit_coupon_usage')) {
            $usage = $DB->get_records('nit_coupon_usage', ['transactionid' => $transactionid],
                'id ASC', 'id, couponid', 0, 1);
            if ($usage) {
                $usage = reset($usage);
                return [
                    'source'   => self::SOURCE_COUPON,
                    'couponid' => (int) $usage->couponid,
                    'offerid'  => 0,
                    'code'     => $DB->get_field('nit_coupon', 'code', ['id' => $usage->couponid]) ?: null,
                ];
            }
        }
        if ($dbman->table_exists('nit_offer_usage')) {
            $usage = $DB->get_records('nit_offer_usage', ['transactionid' => $transactionid],
                'id ASC', 'id, offerid', 0, 1);
            if ($usage) {
                $usage = reset($usage);
                return [
                    'source'   => self::SOURCE_OFFER,
                    'couponid' => 0,
                    'offerid'  => (int) $usage->offerid,
                    'code'     => null,
                ];
            }
        }
        return null;
    }

    /**
     * A subscription purchase held by this user whose plan unlocks this course.
     *
     * Status is deliberately not filtered: a plan that has since expired is still the reason the
     * enrolment exists, and the report must keep saying so.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $when
     * @return \stdClass|false
     */
    protected static function find_covering_plan(int $userid, int $courseid, int $when) {
        global $DB;

        if (!$DB->get_manager()->table_exists('nit_sub_purchase')) {
            return false;
        }

        // nit_course_access.subscriptionid = 0 is the wildcard: every plan unlocks the course.
        $sql = "SELECT p.id, p.subscriptionid, p.price_paid, p.timecreated
                  FROM {nit_sub_purchase} p
                  JOIN {nit_course_access} ca
                    ON ca.courseid = :courseid
                   AND (ca.subscriptionid = p.subscriptionid OR ca.subscriptionid = 0)
                 WHERE p.userid = :userid AND p.timecreated <= :cutoff
              ORDER BY p.timecreated DESC, p.id DESC";
        $records = $DB->get_records_sql($sql, [
            'userid'   => $userid,
            'courseid' => $courseid,
            'cutoff'   => ($when > 0 ? $when : time()) + self::PURCHASE_GRACE,
        ], 0, 1);

        return $records ? reset($records) : false;
    }

    // -- Backfill --------------------------------------------------------------------

    /**
     * Classify enrolments that have no source row yet, oldest first.
     *
     * Runs in bounded batches so opening the report never turns into a table scan of a large
     * site's enrolments; each visit catches a batch up until {@see pending_count()} is zero.
     * Rows written here carry `inferred = 1`: the evidence is the same, but the actor behind a
     * manual enrolment is not recoverable after the fact, so the report can say so.
     *
     * @param int $limit maximum enrolments to classify in this pass
     * @return int how many rows were written
     */
    public static function backfill(int $limit = self::BACKFILL_BATCH): int {
        global $DB;

        $limit = max(0, min($limit, 20000));
        if ($limit === 0) {
            return 0;
        }

        $sql = "SELECT ue.id AS ueid, ue.userid, e.courseid, e.enrol, ue.timecreated
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
             LEFT JOIN {nit_enrol_source} s ON s.ueid = ue.id
                 WHERE s.id IS NULL AND c.id <> :siteid
              ORDER BY ue.id ASC";
        $rows = $DB->get_records_sql($sql, ['siteid' => SITEID], 0, $limit);

        $written = 0;
        foreach ($rows as $row) {
            $classified = self::classify((int) $row->userid, (int) $row->courseid, 0,
                (string) $row->enrol, (int) $row->timecreated);
            $classified['ueid'] = (int) $row->ueid;
            $classified['inferred'] = 1;
            self::insert($classified);
            $written++;
        }
        return $written;
    }

    /**
     * Forget the source of an enrolment that has just been deleted.
     *
     * The targeted counterpart to {@see prune()}: the enrolment is gone, so the note about why it
     * existed goes with it — immediately, rather than at the next throttled sweep, so the report
     * never shows access that was revoked a moment ago.
     *
     * @param int $ueid user_enrolments.id
     * @return void
     */
    public static function forget(int $ueid): void {
        global $DB;

        if ($ueid > 0) {
            $DB->delete_records('nit_enrol_source', ['ueid' => $ueid]);
        }
    }

    /**
     * How many enrolments are still waiting to be classified.
     *
     * @return int
     */
    public static function pending_count(): int {
        global $DB;

        return (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
          LEFT JOIN {nit_enrol_source} s ON s.ueid = ue.id
              WHERE s.id IS NULL AND c.id <> :siteid", ['siteid' => SITEID]);
    }

    /**
     * Drop the source rows whose enrolment no longer exists.
     *
     * The record of *how someone was enrolled* is only meaningful while the enrolment is there;
     * the money trail lives in local_payments_transactions, which this never touches.
     *
     * @return int rows removed
     */
    public static function prune(): int {
        global $DB;

        $ids = $DB->get_fieldset_sql(
            "SELECT s.id
               FROM {nit_enrol_source} s
          LEFT JOIN {user_enrolments} ue ON ue.id = s.ueid
              WHERE s.ueid > 0 AND ue.id IS NULL");
        if (!$ids) {
            return 0;
        }
        // Chunked: a bulk unenrolment (course deletion, an enrol instance removed) can leave
        // thousands of orphans behind, which is more placeholders than a database will take in
        // one IN () list.
        foreach (array_chunk($ids, 500) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'id');
            $DB->delete_records_select('nit_enrol_source', "id $insql", $params);
        }
        return count($ids);
    }

    /**
     * Catch the table up with reality: classify a batch of older enrolments, then drop the rows
     * whose enrolment has since been deleted.
     *
     * Throttled, because the report calls it and the report is re-fetched on every filter click:
     * both passes walk {user_enrolments}, which is one of the biggest tables on a busy site, and
     * neither has anything new to do a second later. Reopening the tab is what advances a large
     * backlog, and that is minutes apart, not milliseconds.
     *
     * @param bool $force run even if the throttle window has not elapsed
     * @return void
     */
    public static function maintain(bool $force = false): void {
        $last = (int) get_config('local_nit_subscriptions', 'enrolsource_lastsync');
        if (!$force && $last > 0 && (time() - $last) < self::MAINTAIN_EVERY) {
            return;
        }
        set_config('enrolsource_lastsync', time(), 'local_nit_subscriptions');

        self::backfill();
        self::prune();
    }

    // -- Reporting -------------------------------------------------------------------

    /**
     * The enrolment-source report: one row per enrolment, newest first.
     *
     * @param array $filters source, courseid, from, to, q (name/email/course/code search)
     * @param int $limit
     * @return array ['rows','summary','total','truncated','limit','pending','courses']
     */
    public static function get_report(array $filters = [], int $limit = self::REPORT_LIMIT): array {
        global $DB;

        self::maintain();

        [$where, $params] = self::report_where($filters);

        $sql = "SELECT s.id, s.ueid, s.userid, s.courseid, s.source, s.itemid, s.transactionid,
                       s.couponid, s.offerid, s.code, s.amount, s.currency, s.grantedby,
                       s.enrolplugin, s.inferred, s.timecreated,
                       u.firstname, u.lastname, u.email,
                       c.fullname AS course_fullname,
                       g.firstname AS g_firstname, g.lastname AS g_lastname
                  FROM {nit_enrol_source} s
                  JOIN {user} u ON u.id = s.userid
             LEFT JOIN {course} c ON c.id = s.courseid
             LEFT JOIN {user} g ON g.id = s.grantedby
                 $where
              ORDER BY s.timecreated DESC, s.id DESC";

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {nit_enrol_source} s
               JOIN {user} u ON u.id = s.userid
          LEFT JOIN {course} c ON c.id = s.courseid
          LEFT JOIN {user} g ON g.id = s.grantedby
              $where", $params);

        $records = $DB->get_records_sql($sql, $params, 0, $limit);

        // One lookup per referenced plan/offer instead of one per row.
        $plannames = self::plan_names($records);
        $offernames = self::offer_names($records);

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                'id'              => (int) $r->id,
                'userid'          => (int) $r->userid,
                'user_fullname'   => fullname($r),
                'user_email'      => (string) $r->email,
                'courseid'        => (int) $r->courseid,
                'course_fullname' => $r->course_fullname !== null
                    ? format_string($r->course_fullname)
                    : get_string('mc_course_deleted', 'local_nit_subscriptions'),
                'source'          => (string) $r->source,
                'source_label'    => self::source_label((string) $r->source),
                'detail'          => self::detail_for($r, $plannames, $offernames),
                'amount'          => (float) $r->amount,
                'currency'        => (string) ($r->currency ?? ''),
                'transactionid'   => (int) $r->transactionid,
                'grantedby'       => (int) $r->grantedby,
                'enrolplugin'     => (string) $r->enrolplugin,
                'inferred'        => (bool) $r->inferred,
                'timecreated'     => (int) $r->timecreated,
            ];
        }

        return [
            'rows'      => $rows,
            'summary'   => self::summary($filters),
            'total'     => $total,
            'truncated' => $total > count($rows),
            'limit'     => $limit,
            'pending'   => self::pending_count(),
            'courses'   => self::report_courses(),
        ];
    }

    /**
     * Counts per source under the current filters, plus the grand total.
     *
     * @param array $filters
     * @return array ['total' => int, 'items' => [['source','label','count'], ...]]
     */
    public static function summary(array $filters = []): array {
        global $DB;

        [$where, $params] = self::report_where($filters);
        $counts = $DB->get_records_sql_menu(
            "SELECT s.source, COUNT(1) AS rowcount
               FROM {nit_enrol_source} s
               JOIN {user} u ON u.id = s.userid
          LEFT JOIN {course} c ON c.id = s.courseid
          LEFT JOIN {user} g ON g.id = s.grantedby
              $where
           GROUP BY s.source", $params);

        $out = ['total' => 0, 'items' => []];
        foreach (self::sources() as $source) {
            $count = (int) ($counts[$source] ?? 0);
            $out['items'][] = [
                'source' => $source,
                'label'  => self::source_label($source),
                'count'  => $count,
            ];
            $out['total'] += $count;
        }
        return $out;
    }

    /**
     * Shared WHERE for the report and its summary, so the numbers on the cards always describe
     * exactly the rows in the table below them.
     *
     * @param array $filters
     * @return array [string $where, array $params]
     */
    protected static function report_where(array $filters): array {
        global $DB;

        $clauses = [];
        $params = [];

        $source = (string) ($filters['source'] ?? '');
        if ($source !== '' && in_array($source, self::sources(), true)) {
            $clauses[] = 's.source = :source';
            $params['source'] = $source;
        }

        $courseid = (int) ($filters['courseid'] ?? 0);
        if ($courseid > 0) {
            $clauses[] = 's.courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        $from = (int) ($filters['from'] ?? 0);
        if ($from > 0) {
            $clauses[] = 's.timecreated >= :fromtime';
            $params['fromtime'] = $from;
        }

        $to = (int) ($filters['to'] ?? 0);
        if ($to > 0) {
            $clauses[] = 's.timecreated <= :totime';
            $params['totime'] = $to;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $DB->sql_like_escape($q) . '%';
            $fullname = $DB->sql_concat('u.firstname', "' '", 'u.lastname');
            $clauses[] = '(' . implode(' OR ', [
                $DB->sql_like($fullname, ':qname', false),
                $DB->sql_like('u.email', ':qemail', false),
                $DB->sql_like('c.fullname', ':qcourse', false),
                $DB->sql_like('s.code', ':qcode', false),
            ]) . ')';
            $params['qname'] = $like;
            $params['qemail'] = $like;
            $params['qcourse'] = $like;
            $params['qcode'] = $like;
        }

        return [$clauses ? 'WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    /**
     * Courses that appear in the report, for the filter dropdown.
     *
     * @return array [['id','name'], ...]
     */
    protected static function report_courses(): array {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT DISTINCT c.id, c.fullname
               FROM {nit_enrol_source} s
               JOIN {course} c ON c.id = s.courseid
           ORDER BY c.fullname ASC", [], 0, 500);

        $out = [];
        foreach ($records as $r) {
            $out[] = ['id' => (int) $r->id, 'name' => format_string($r->fullname)];
        }
        return $out;
    }

    /**
     * Plan names for every package row in the result set, in one query.
     *
     * @param array $records
     * @return array id => name
     */
    protected static function plan_names(array $records): array {
        global $DB;

        $ids = [];
        foreach ($records as $r) {
            if ($r->source === self::SOURCE_PACKAGE && (int) $r->itemid > 0) {
                $ids[(int) $r->itemid] = true;
            }
        }
        if (!$ids || !$DB->get_manager()->table_exists('nit_subscription')) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($ids), SQL_PARAMS_NAMED, 'p');
        $names = $DB->get_records_select_menu('nit_subscription', "id $insql", $params, '', 'id, name');

        return array_map(static function ($name) {
            return format_string(subscription_manager::resolve_mlang($name));
        }, $names);
    }

    /**
     * Offer names for every offer row in the result set, in one query.
     *
     * @param array $records
     * @return array id => name
     */
    protected static function offer_names(array $records): array {
        global $DB;

        $ids = [];
        foreach ($records as $r) {
            if ((int) $r->offerid > 0) {
                $ids[(int) $r->offerid] = true;
            }
        }
        if (!$ids || !$DB->get_manager()->table_exists('nit_offer')) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($ids), SQL_PARAMS_NAMED, 'o');
        $names = $DB->get_records_select_menu('nit_offer', "id $insql", $params, '', 'id, name');

        return array_map(static function ($name) {
            return format_string(subscription_manager::resolve_mlang($name));
        }, $names);
    }

    /**
     * The one extra fact that makes a row's source concrete: the coupon code, the offer or plan
     * name, or who granted the seat.
     *
     * @param \stdClass $r
     * @param array $plannames
     * @param array $offernames
     * @return string
     */
    protected static function detail_for(\stdClass $r, array $plannames, array $offernames): string {
        switch ($r->source) {
            case self::SOURCE_COUPON:
                return (string) ($r->code ?? '');
            case self::SOURCE_OFFER:
                return (string) ($offernames[(int) $r->offerid] ?? '');
            case self::SOURCE_PACKAGE:
                return (string) ($plannames[(int) $r->itemid] ?? '');
            case self::SOURCE_ADMIN:
                return ($r->grantedby > 0 && $r->g_firstname !== null)
                    ? fullname((object) ['firstname' => $r->g_firstname, 'lastname' => $r->g_lastname])
                    : '';
            default:
                return '';
        }
    }
}
