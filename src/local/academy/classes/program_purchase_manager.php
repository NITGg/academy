<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Paid programs: pricing for enrol_programs programs, and allocation once payment completes.
 *
 * The third-party enrol_programs plugin is never modified. Everything lives here:
 *   - the price sits in academy_program_prices, not in the plugin's tables;
 *   - allocation goes through the plugin's own public API (manual::allocate_users()).
 * That keeps a plugin update from wiping either the pricing or the purchase flow.
 *
 * Free stays free: a program with no price row (or enabled = 0) keeps the plugin's own signup
 * behaviour untouched, so existing programs are unaffected by this feature.
 *
 * Security note: a paid program must NOT have the plugin's 'selfallocation' source enabled — that
 * source exposes /enrol/programs/catalogue/source_selfallocation.php, which allocates a student for
 * free regardless of what the UI shows. Hiding a button is not a gate; see is_bypassable().
 */
class program_purchase_manager {

    /** Source type we allocate paid buyers through (the plugin's admin-assign source). */
    const SOURCE_TYPE = 'manual';

    /** The plugin's free self-signup source — must stay off for paid programs. */
    const SOURCE_SELF = 'selfallocation';

    // ──────────────────────────────────────────────────────────────────────────
    // Availability
    // ──────────────────────────────────────────────────────────────────────────

    /** Is enrol_programs installed and usable? Everything here no-ops without it. */
    public static function available() {
        global $DB;
        return class_exists('\enrol_programs\local\source\manual')
            && $DB->get_manager()->table_exists('enrol_programs_programs');
    }

    /** @throws \moodle_exception if the programs plugin is missing */
    private static function require_available() {
        if (!self::available()) {
            throw new \moodle_exception('err_programsunavailable', 'local_academy');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Pricing (admin)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The active price for a program, or null when it is free.
     *
     * @param int $programid
     * @return \stdClass|null price record
     */
    public static function get_price($programid) {
        global $DB;
        $row = $DB->get_record('academy_program_prices', array('programid' => (int)$programid));
        if (!$row || !$row->enabled || (float)$row->price <= 0) {
            return null;
        }
        return $row;
    }

    /** Convenience: is this program paid? */
    public static function is_paid($programid) {
        return self::get_price($programid) !== null;
    }

    /**
     * Set (or clear) a program's price.
     *
     * Upserts on programid — the unique index makes repeated calls safe. Passing a price of 0
     * disables the row rather than deleting it, so an admin can toggle a program back to paid
     * without retyping the amount.
     *
     * @param int    $programid
     * @param float  $price     0 or less makes the program free again
     * @param string $currency
     * @param int    $adminid
     * @return \stdClass|null the stored record, or null when the program was made free
     */
    public static function set_price($programid, $price, $currency = 'EGP', $adminid = 0) {
        global $DB;
        self::require_available();

        $programid = (int)$programid;
        $price = round((float)$price, 2);
        if ($price < 0) {
            throw new \moodle_exception('err_invalidprice', 'local_academy');
        }
        if (!$DB->record_exists('enrol_programs_programs', array('id' => $programid))) {
            throw new \moodle_exception('err_programnotfound', 'local_academy');
        }

        $now = time();
        $existing = $DB->get_record('academy_program_prices', array('programid' => $programid));

        $record = $existing ? clone $existing : new \stdClass();
        $record->programid    = $programid;
        $record->price        = $price;
        $record->currency     = $currency ?: 'EGP';
        $record->enabled      = $price > 0 ? 1 : 0;
        $record->timemodified = $now;
        $record->usermodified = (int)$adminid;

        if ($existing) {
            $DB->update_record('academy_program_prices', $record);
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record('academy_program_prices', $record);
        }

        return $record->enabled ? $record : null;
    }

    /**
     * Every program with its price and the warnings an admin needs to see.
     *
     * @return array list of program rows for the admin pricing screen
     */
    public static function list_programs() {
        global $DB;
        if (!self::available()) {
            return array();
        }

        $sql = "SELECT p.id, p.fullname, p.idnumber, p.archived, p.public,
                       pr.price, pr.currency, pr.enabled
                  FROM {enrol_programs_programs} p
             LEFT JOIN {academy_program_prices} pr ON pr.programid = p.id
              ORDER BY p.fullname";
        $rows = $DB->get_records_sql($sql);
        $sales = self::sales_by_program(); // One query for all programs, not one each.

        $out = array();
        foreach ($rows as $r) {
            $paid = $r->enabled && (float)$r->price > 0;
            $hasfree = self::has_free_signup((int)$r->id);
            $out[] = array(
                'id'          => (int)$r->id,
                'fullname'    => format_string($r->fullname),
                'idnumber'    => $r->idnumber,
                'archived'    => (int)$r->archived,
                'public'      => (int)$r->public,
                'price'       => $r->price === null ? 0.0 : (float)$r->price,
                'currency'    => $r->currency ?: 'EGP',
                'paid'        => $paid ? 1 : 0,
                'sales'       => $sales[(int)$r->id] ?? 0,
                // Paid + self-signup on: the admin screen warns loudly — a student can take the
                // program for free straight from the plugin's own URL, bypassing the price.
                'bypassable'  => ($paid && $hasfree) ? 1 : 0,
                // Free + self-signup off: the ONLY way in is closed. The admin screen offers to
                // turn it back on rather than leaving a "free" program nobody can actually join.
                'needsopen'   => (!$paid && !$hasfree) ? 1 : 0,
            );
        }
        return $out;
    }

    /**
     * Does this program currently have the plugin's free self-signup source enabled?
     *
     * Same underlying fact ("Allocation sources → Self allocation" is on, in the plugin's own
     * management UI) but the fact means opposite things depending on price: on a paid program it is
     * a bypass (a free path that undercuts the price we are charging); on a free program it is the
     * *only* way a student can join at all, since we never enable it ourselves automatically.
     */
    public static function has_free_signup($programid) {
        global $DB;
        if (!self::available()) {
            return false;
        }
        return $DB->record_exists('enrol_programs_sources',
            array('programid' => (int)$programid, 'type' => self::SOURCE_SELF));
    }

    /**
     * Can this program be obtained for free despite having a price?
     *
     * True when the plugin's self-signup source is enabled: that source publishes a URL which
     * allocates the student immediately, with no reference to our pricing. Removing our Buy button
     * would not close it — the source itself has to go.
     */
    public static function is_bypassable($programid) {
        return self::is_paid($programid) && self::has_free_signup($programid);
    }

    /** Turn off the plugin's free self-signup source for a program (refuses if it has allocations). */
    public static function disable_free_signup($programid) {
        global $DB;
        self::require_available();

        $source = $DB->get_record('enrol_programs_sources',
            array('programid' => (int)$programid, 'type' => self::SOURCE_SELF));
        if (!$source) {
            return true; // Already closed.
        }
        // The plugin refuses to drop a source that students are already allocated through, so say
        // so plainly rather than letting its coding_exception surface to the admin.
        if ($DB->record_exists('enrol_programs_allocations', array('sourceid' => $source->id))) {
            throw new \moodle_exception('err_programsourceinuse', 'local_academy');
        }
        \enrol_programs\local\source\selfallocation::update_source((object)array(
            'programid' => (int)$programid,
            'type'      => self::SOURCE_SELF,
            'enable'    => 0,
        ));
        return true;
    }

    /**
     * Turn on the plugin's free self-signup source for a program — open to anyone, no key, no seat
     * cap (allowsignup=1, key=null, maxusers=null; see selfallocation::encode_datajson()).
     *
     * A free program with no allocation source enabled has no way for a student to join at all —
     * we only ever allocate paid buyers ourselves, through the 'manual' source. Making a program
     * free (price = 0) does not do this automatically; the admin turns it on deliberately, exactly
     * as they deliberately turn it off when making a program paid.
     */
    public static function enable_free_signup($programid) {
        self::require_available();
        \enrol_programs\local\source\selfallocation::update_source((object)array(
            'programid' => (int)$programid,
            'type'      => self::SOURCE_SELF,
            'enable'    => 1,
        ));
        return true;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Allocation (called after payment)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The program's 'manual' source, created on demand.
     *
     * enrol_programs creates no sources for a new program, so the first paid purchase has to make
     * one. Verified against the installed plugin — see update_source() in its source\base.
     *
     * @return \stdClass source record
     */
    private static function ensure_manual_source($programid) {
        global $DB;

        $source = $DB->get_record('enrol_programs_sources',
            array('programid' => (int)$programid, 'type' => self::SOURCE_TYPE));
        if ($source) {
            return $source;
        }
        // Go through the plugin's own API rather than inserting the row ourselves: it encodes
        // datajson (NOT NULL) and takes a program snapshot the plugin expects.
        return \enrol_programs\local\source\manual::update_source((object)array(
            'programid' => (int)$programid,
            'type'      => self::SOURCE_TYPE,
            'enable'    => 1,
        ));
    }

    /**
     * Give a paying student access to a program.
     *
     * Safe to call more than once for the same user and program — payment webhooks do retry, and
     * the plugin returns the existing allocation instead of creating a second one.
     *
     * @param int $userid
     * @param int $programid
     * @return \stdClass the allocation record
     */
    public static function allocate_buyer($userid, $programid) {
        global $DB;
        self::require_available();

        $userid = (int)$userid;
        $programid = (int)$programid;

        $program = $DB->get_record('enrol_programs_programs', array('id' => $programid), '*', MUST_EXIST);
        if ($program->archived) {
            throw new \moodle_exception('err_programarchived', 'local_academy');
        }

        $existing = $DB->get_record('enrol_programs_allocations',
            array('programid' => $programid, 'userid' => $userid));
        if ($existing) {
            return $existing; // Already allocated (repeat webhook, or an admin got there first).
        }

        $source = self::ensure_manual_source($programid);
        \enrol_programs\local\source\manual::allocate_users($programid, (int)$source->id, array($userid));

        return $DB->get_record('enrol_programs_allocations',
            array('programid' => $programid, 'userid' => $userid), '*', MUST_EXIST);
    }

    /** Has this user already got this program? */
    public static function has_allocation($userid, $programid) {
        global $DB;
        if (!self::available()) {
            return false;
        }
        return $DB->record_exists('enrol_programs_allocations',
            array('programid' => (int)$programid, 'userid' => (int)$userid));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Student-facing
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * What the catalogue should show this user for a program.
     *
     * @return array state: free | owned | buyable, plus price/currency when priced
     */
    public static function get_student_state($userid, $programid) {
        $price = self::get_price($programid);
        if (!$price) {
            return array('state' => 'free');
        }
        if (self::has_allocation($userid, $programid)) {
            return array('state' => 'owned', 'price' => (float)$price->price, 'currency' => $price->currency);
        }
        return array('state' => 'buyable', 'price' => (float)$price->price, 'currency' => $price->currency);
    }

    /**
     * Programs this user may see in the catalogue, with their price/free state.
     *
     * Visibility mirrors enrol_programs' own catalogue rule (see catalogue::get_programs_sql()):
     * a program shows when it is not archived AND (it is public, OR the user is already allocated,
     * OR the user is in one of its cohorts). Passing 0 gives the guest/visitor view — public only.
     *
     * @param int $userid 0 for guests
     * @return array list of program rows for the front-page cards
     */
    public static function get_catalogue_programs($userid = 0) {
        global $DB;
        if (!self::available()) {
            return array();
        }
        $userid = (int)$userid;

        $sql = "SELECT p.id, p.fullname, p.description, p.descriptionformat, p.contextid,
                       pr.price, pr.currency, pr.enabled,
                       pa.id AS allocationid
                  FROM {enrol_programs_programs} p
             LEFT JOIN {academy_program_prices} pr ON pr.programid = p.id
             LEFT JOIN {enrol_programs_allocations} pa
                       ON pa.programid = p.id AND pa.userid = :userid1 AND pa.archived = 0
                 WHERE p.archived = 0
                       AND (p.public = 1 OR pa.id IS NOT NULL OR EXISTS (
                            SELECT cm.id
                              FROM {cohort_members} cm
                              JOIN {enrol_programs_cohorts} pc ON pc.cohortid = cm.cohortid
                             WHERE cm.userid = :userid2 AND pc.programid = p.id))
              ORDER BY p.fullname ASC";
        $rows = $DB->get_records_sql($sql, array('userid1' => $userid, 'userid2' => $userid));

        $out = array();
        foreach ($rows as $r) {
            $paid = $r->enabled && (float)$r->price > 0;
            $out[] = array(
                'id'          => (int)$r->id,
                'name'        => format_string($r->fullname),
                'description' => self::plain_description($r),
                'free'        => $paid ? 0 : 1,
                'price'       => $paid ? (float)$r->price : 0.0,
                'currency'    => $r->currency ?: 'EGP',
                // Automatic offer badge, same as packages/subscriptions (US-US-OF-1-1).
                'offer'       => $paid
                    ? discount_manager::offer_summary(discount_manager::TYPE_PROGRAM, (int)$r->id, (float)$r->price)
                    : null,
                'owned'       => $r->allocationid ? 1 : 0,
                // A free program is only joinable when the plugin's self-signup source is on; with
                // it off there is no way in, so the card links to the catalogue instead of promising
                // a signup that does not exist.
                'joinable'    => (!$paid && self::has_free_signup((int)$r->id)) ? 1 : 0,
            );
        }
        return $out;
    }

    /**
     * The programs this user is allocated to, for the front-page "My programs" cards.
     *
     * @param int $userid
     * @return array
     */
    public static function get_my_programs($userid) {
        global $DB;
        if (!self::available()) {
            return array();
        }

        $sql = "SELECT pa.id AS allocationid, pa.timeallocated, pa.timestart, pa.timedue,
                       pa.timeend, pa.timecompleted, pa.archived,
                       p.id, p.fullname, p.description, p.descriptionformat, p.contextid
                  FROM {enrol_programs_allocations} pa
                  JOIN {enrol_programs_programs} p ON p.id = pa.programid
                 WHERE pa.userid = :userid AND pa.archived = 0 AND p.archived = 0
              ORDER BY pa.timeallocated DESC";
        $rows = $DB->get_records_sql($sql, array('userid' => (int)$userid));

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'id'            => (int)$r->id,
                'name'          => format_string($r->fullname),
                'description'   => self::plain_description($r),
                'timeallocated' => (int)$r->timeallocated,
                'timestart'     => (int)$r->timestart,
                'timedue'       => (int)$r->timedue,
                'timeend'       => (int)$r->timeend,
                'timecompleted' => (int)$r->timecompleted,
                'completed'     => $r->timecompleted ? 1 : 0,
            );
        }
        return $out;
    }

    /**
     * A program's description as short plain text, safe to drop into a card.
     *
     * @param \stdClass $r row carrying description/descriptionformat/contextid
     * @return string
     */
    private static function plain_description($r) {
        if (trim((string)$r->description) === '') {
            return '';
        }
        $context = \context::instance_by_id((int)$r->contextid, IGNORE_MISSING);
        $html = format_text($r->description, $r->descriptionformat,
            array('context' => $context ?: \context_system::instance()));
        return shorten_text(trim(html_to_text($html, 0, false)), 180);
    }

    /**
     * Completed program sales, counted per program in one pass.
     *
     * Deliberately not a LIKE on the JSON: '%"item_id":3%' also matches "item_id":30, which would
     * silently inflate the count. Decoding in PHP is exact, and one query beats one per program.
     *
     * @return array programid => number of completed sales
     */
    public static function sales_by_program() {
        global $DB;
        if (!$DB->get_manager()->table_exists('local_payments_transactions')) {
            return array();
        }
        $rows = $DB->get_records_sql(
            "SELECT id, metadata
               FROM {local_payments_transactions}
              WHERE status = 'completed' AND " . $DB->sql_like('metadata', ':meta'),
            array('meta' => '%"item_type":"program"%'));

        $counts = array();
        foreach ($rows as $r) {
            $meta = json_decode((string)$r->metadata);
            if (!isset($meta->item_type) || $meta->item_type !== 'program' || empty($meta->item_id)) {
                continue;
            }
            $pid = (int)$meta->item_id;
            $counts[$pid] = ($counts[$pid] ?? 0) + 1;
        }
        return $counts;
    }
}
