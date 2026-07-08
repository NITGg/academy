<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD + rules for course-access subscription plans, and the course↔subscription access map.
 *
 * Covers user stories US-AD-5-1 .. US-AD-5-4 (manage plans) and US-AD-6-1 (course access).
 * See docs/specs/admin/.
 */
class subscription_manager {

    /** Allowed plan statuses. */
    const STATUS_ACTIVE   = 'active';
    const STATUS_INACTIVE = 'inactive';

    /** Sentinel subscriptionid in academy_course_access meaning "all subscriptions". */
    const ALL_SUBSCRIPTIONS = 0;

    // ──────────────────────────────────────────────────────────────────────────
    // US-AD-5-1 .. US-AD-5-4: plan CRUD
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Create a subscription plan (US-AD-5-1).
     *
     * @param array $data name, description, price, duration_days, active, b2b_enabled, seat_options[]
     * @param int $userid admin performing the action
     * @return int new subscription id
     */
    public static function create_subscription(array $data, $userid) {
        global $DB;

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw new \moodle_exception('err_subnamerequired', 'local_academy');
        }
        $price = (float)($data['price'] ?? 0);
        if ($price < 0) {
            throw new \moodle_exception('err_pricenegative', 'local_academy');
        }
        $duration = (int)($data['duration_days'] ?? 0);
        if ($duration <= 0) {
            throw new \moodle_exception('err_durationpositive', 'local_academy');
        }
        $b2benabled = !empty($data['b2b_enabled']) ? 1 : 0;

        $now = time();
        $record = new \stdClass();
        $record->name          = $name;
        $record->description   = $data['description'] ?? '';
        $record->price         = $price;
        $record->duration_days = $duration;
        $record->status        = !empty($data['active']) ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
        $record->b2b_enabled   = $b2benabled;
        $record->timecreated   = $now;
        $record->timemodified  = $now;
        $record->usermodified  = $userid;

        $id = $DB->insert_record('academy_subscriptions', $record);

        // Seat options only apply to a B2B-enabled plan.
        if ($b2benabled && array_key_exists('seat_options', $data)) {
            self::save_seat_options($id, (array)$data['seat_options']);
        }
        return $id;
    }

    /**
     * Update a subscription plan (US-AD-5-2). Only provided fields change; changes apply to future
     * purchases only (existing purchases keep their snapshot price + expiry).
     *
     * @param int $id
     * @param array $data subset of: name, description, price, duration_days, status
     * @param int $userid admin performing the action
     */
    public static function update_subscription($id, array $data, $userid) {
        global $DB;

        $sub = self::get_subscription($id); // throws if missing

        $update = new \stdClass();
        $update->id = $sub->id;

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if ($name === '') {
                throw new \moodle_exception('err_subnameempty', 'local_academy');
            }
            $update->name = $name;
        }
        if (array_key_exists('description', $data)) {
            $update->description = $data['description'];
        }
        if (array_key_exists('price', $data)) {
            $price = (float)$data['price'];
            if ($price < 0) {
                throw new \moodle_exception('err_pricenegative', 'local_academy');
            }
            $update->price = $price;
        }
        if (array_key_exists('duration_days', $data)) {
            $duration = (int)$data['duration_days'];
            if ($duration <= 0) {
                throw new \moodle_exception('err_durationpositive', 'local_academy');
            }
            $update->duration_days = $duration;
        }
        if (array_key_exists('status', $data)) {
            $update->status = self::normalize_status($data['status']);
        }
        $b2benabled = null;
        if (array_key_exists('b2b_enabled', $data)) {
            $b2benabled = !empty($data['b2b_enabled']) ? 1 : 0;
            $update->b2b_enabled = $b2benabled;
        }

        $update->timemodified = time();
        $update->usermodified = $userid;

        $DB->update_record('academy_subscriptions', $update);

        // Seat options (US-AD-5-2): replace-on-save when provided. Disabling B2B clears them.
        $effectiveb2b = ($b2benabled === null) ? (int)$sub->b2b_enabled : $b2benabled;
        if ($effectiveb2b && array_key_exists('seat_options', $data)) {
            self::save_seat_options($sub->id, (array)$data['seat_options']);
        } else if ($b2benabled === 0) {
            $DB->delete_records('academy_sub_seat_options', array('subscriptionid' => $sub->id));
        }
    }

    /** Deactivate a plan (US-AD-5-3). Existing student subscriptions are unaffected. */
    public static function deactivate_subscription($id, $userid) {
        self::set_status($id, self::STATUS_INACTIVE, $userid);
    }

    /** Reactivate a plan (US-AD-5-3 note: admin can activate again later). */
    public static function activate_subscription($id, $userid) {
        self::set_status($id, self::STATUS_ACTIVE, $userid);
    }

    /** Delete a plan (US-AD-5-4). Allowed only if it was never purchased. */
    public static function delete_subscription($id) {
        global $DB;

        self::get_subscription($id); // throws if missing

        if (self::has_purchases($id)) {
            throw new \moodle_exception('err_subhaspurchases', 'local_academy');
        }

        $transaction = $DB->start_delegated_transaction();
        // Drop the plan's course-access rows first (a "specific" grant referencing this plan).
        $DB->delete_records('academy_course_access', array('subscriptionid' => $id));
        $DB->delete_records('academy_sub_seat_options', array('subscriptionid' => $id));
        $DB->delete_records('academy_subscriptions', array('id' => $id));
        $transaction->allow_commit();
    }

    /** Whether any student ever purchased this plan (payment/usage records exist). */
    public static function has_purchases($subscriptionid) {
        global $DB;
        return $DB->record_exists('academy_sub_purchases', array('subscriptionid' => $subscriptionid))
            || $DB->record_exists('academy_sub_payments', array('subscriptionid' => $subscriptionid));
    }

    /** Fetch a single plan or throw. */
    public static function get_subscription($id) {
        global $DB;
        $sub = $DB->get_record('academy_subscriptions', array('id' => $id));
        if (!$sub) {
            throw new \moodle_exception('err_subnotfound', 'local_academy');
        }
        return $sub;
    }

    /**
     * List plans, optionally filtered by status, each with its granted courses.
     *
     * @param string $status '' for all, or 'active' | 'inactive'
     * @return array
     */
    public static function get_subscriptions($status = '') {
        global $DB;
        $conditions = array();
        if ($status !== '') {
            $conditions['status'] = self::normalize_status($status);
        }
        $rows = array_values($DB->get_records('academy_subscriptions', $conditions, 'timecreated DESC'));
        foreach ($rows as $r) {
            $r->courses = self::courses_detail($r->id);
            $r->b2b_enabled = (int)$r->b2b_enabled;
            $r->seat_options = self::get_seat_options($r->id, (float)$r->price);
        }
        return $rows;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // B2B seat options + price (US-AD-5-1 / US-AD-5-2 / US-B2B-1-1)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * The B2B price for a seat option.
     *
     * @param float $base normal price
     * @param int $seats capacity
     * @param float $pct discount percentage (0..100)
     * @return array ['original'=>, 'discount'=>, 'final'=>]
     */
    public static function b2b_price($base, $seats, $pct) {
        $original = round((float)$base * (int)$seats, 2);
        $discount = round($original * (float)$pct / 100, 2);
        return array(
            'original' => $original,
            'discount' => $discount,
            'final'    => round($original - $discount, 2),
        );
    }

    /**
     * Seat options for a plan, each with its computed B2B price.
     *
     * @param int $subscriptionid
     * @param float|null $baseprice plan price (fetched if null)
     * @return array
     */
    public static function get_seat_options($subscriptionid, $baseprice = null) {
        global $DB;
        if ($baseprice === null) {
            $baseprice = (float)$DB->get_field('academy_subscriptions', 'price', array('id' => $subscriptionid));
        }
        $rows = array_values($DB->get_records('academy_sub_seat_options',
            array('subscriptionid' => $subscriptionid), 'seats ASC'));
        $out = array();
        foreach ($rows as $r) {
            $price = self::b2b_price($baseprice, $r->seats, $r->discount_percent);
            $out[] = array(
                'id'               => (int)$r->id,
                'seats'            => (int)$r->seats,
                'discount_percent' => (float)$r->discount_percent,
                'original_price'   => $price['original'],
                'discount_amount'  => $price['discount'],
                'b2b_price'        => $price['final'],
            );
        }
        return $out;
    }

    /**
     * Replace a plan's seat options (US-AD-5-1 / US-AD-5-2). Validates seats>0 and 0<=discount<=100,
     * and de-duplicates by seat count.
     *
     * @param int $subscriptionid
     * @param array $options list of ['seats'=>, 'discount_percent'=>]
     */
    public static function save_seat_options($subscriptionid, array $options) {
        global $DB;
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('academy_sub_seat_options', array('subscriptionid' => $subscriptionid));

        $seen = array();
        foreach ($options as $opt) {
            $seats = (int)($opt['seats'] ?? 0);
            $pct = (float)($opt['discount_percent'] ?? 0);
            if ($seats <= 0) {
                throw new \moodle_exception('err_seatspositive', 'local_academy');
            }
            if ($pct < 0 || $pct > 100) {
                throw new \moodle_exception('err_discountrange', 'local_academy');
            }
            if (isset($seen[$seats])) {
                continue; // ignore duplicate seat counts
            }
            $seen[$seats] = true;
            $DB->insert_record('academy_sub_seat_options', (object) array(
                'subscriptionid'   => $subscriptionid,
                'seats'            => $seats,
                'discount_percent' => $pct,
                'timecreated'      => $now,
                'timemodified'     => $now,
            ));
        }
        $transaction->allow_commit();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // US-AD-6-1: course ↔ subscription access map
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Set which subscriptions can access a course (US-AD-6-1).
     * Applies immediately; replaces any existing rules for the course.
     *
     * @param int $courseid
     * @param string $mode 'all' (every subscription) or 'specific'
     * @param int[] $subscriptionids used when $mode = 'specific'
     * @param int $userid admin performing the action
     * @return array the new access state for the course
     */
    public static function set_course_subscriptions($courseid, $mode, array $subscriptionids, $userid) {
        global $DB;

        if (!$DB->record_exists('course', array('id' => $courseid))) {
            throw new \moodle_exception('err_coursenotfound', 'local_academy');
        }

        // Which subscriptions grant this course BEFORE the change, to detect ones that lose it.
        $oldsubs = self::subscriptions_granting_course($courseid);

        $now = time();
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('academy_course_access', array('courseid' => $courseid));

        if ($mode === 'all') {
            $DB->insert_record('academy_course_access', (object) array(
                'courseid'       => $courseid,
                'subscriptionid' => self::ALL_SUBSCRIPTIONS,
                'timecreated'    => $now,
                'usermodified'   => $userid,
            ));
        } else {
            $seen = array();
            foreach ($subscriptionids as $sid) {
                $sid = (int)$sid;
                if ($sid <= 0 || isset($seen[$sid])) {
                    continue;
                }
                self::get_subscription($sid); // validate it exists
                $seen[$sid] = true;
                $DB->insert_record('academy_course_access', (object) array(
                    'courseid'       => $courseid,
                    'subscriptionid' => $sid,
                    'timecreated'    => $now,
                    'usermodified'   => $userid,
                ));
            }
        }
        $transaction->allow_commit();

        // Unenrol holders of any subscription that no longer grants this course (after commit).
        $newsubs = self::subscriptions_granting_course($courseid);
        foreach (array_diff($oldsubs, $newsubs) as $sid) {
            subscription_purchase_manager::revoke_removed_courses($sid, array($courseid));
        }

        return self::get_course_access($courseid);
    }

    /**
     * The subscription plan ids that grant access to a course, expanding the "all subscriptions"
     * sentinel to every existing plan. Used to detect which plans lose a course when its access rule
     * changes, so their holders can be unenrolled.
     *
     * @param int $courseid
     * @return int[] plan ids
     */
    private static function subscriptions_granting_course($courseid) {
        global $DB;
        $rows = $DB->get_records('academy_course_access',
            array('courseid' => $courseid), '', 'DISTINCT subscriptionid');
        $ids = array();
        $hasall = false;
        foreach ($rows as $r) {
            $sid = (int)$r->subscriptionid;
            if ($sid === self::ALL_SUBSCRIPTIONS) {
                $hasall = true;
            } else {
                $ids[$sid] = true;
            }
        }
        if ($hasall) {
            foreach ($DB->get_records('academy_subscriptions', array(), '', 'id') as $s) {
                $ids[(int)$s->id] = true;
            }
        }
        return array_keys($ids);
    }

    /**
     * Set the courses for a specific subscription (US-AD-6-1 alternative flow).
     *
     * @param int $subscriptionid
     * @param int[] $courseids
     * @param int $userid
     * @return array the new courses for the subscription
     */
    public static function set_subscription_courses($subscriptionid, array $courseids, $userid) {
        global $DB;
        self::get_subscription($subscriptionid); // validate

        // Snapshot the courses this subscription currently grants, so we can unenrol its holders from
        // any course the admin removes below (US-AD-6-1).
        $oldcourseids = array_map('intval', array_keys(
            $DB->get_records('academy_course_access',
                array('subscriptionid' => $subscriptionid), '', 'DISTINCT courseid')));

        $now = time();
        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records('academy_course_access', array('subscriptionid' => $subscriptionid));

        $seen = array();
        foreach ($courseids as $cid) {
            $cid = (int)$cid;
            if ($cid <= 0 || isset($seen[$cid])) {
                continue;
            }
            if (!$DB->record_exists('course', array('id' => $cid))) {
                continue;
            }
            $seen[$cid] = true;
            $DB->insert_record('academy_course_access', (object) array(
                'courseid'       => $cid,
                'subscriptionid' => $subscriptionid,
                'timecreated'    => $now,
                'usermodified'   => $userid,
            ));
        }

        $transaction->allow_commit();

        // Unenrol holders from any course this subscription no longer grants (after commit — enrolment
        // changes run their own transactions/events).
        $removed = array_values(array_diff($oldcourseids, array_keys($seen)));
        subscription_purchase_manager::revoke_removed_courses($subscriptionid, $removed);

        return self::courses_detail($subscriptionid);
    }

    /**
     * Get categories and their courses for the admin UI.
     *
     * @return array
     */
    public static function get_categories_with_courses() {
        global $DB;
        $cats = $DB->get_records('course_categories', array(), 'sortorder ASC', 'id, name');
        $courses = $DB->get_records('course', array(), 'sortorder ASC', 'id, fullname, category');
        $tree = array();
        foreach ($cats as $cat) {
            $tree[$cat->id] = array(
                'id' => (int)$cat->id,
                'name' => format_string($cat->name),
                'courses' => array()
            );
        }
        foreach ($courses as $c) {
            if ($c->category > 0 && isset($tree[$c->category])) {
                $tree[$c->category]['courses'][] = array(
                    'id' => (int)$c->id,
                    'fullname' => format_string($c->fullname)
                );
            }
        }
        // Filter out empty categories
        $result = array();
        foreach ($tree as $node) {
            if (!empty($node['courses'])) {
                $result[] = $node;
            }
        }
        return $result;
    }

    /**
     * The access rule for a course.
     *
     * @param int $courseid
     * @return array ['courseid'=>, 'mode'=>'all'|'specific'|'none', 'subscriptionids'=>[]]
     */
    public static function get_course_access($courseid) {
        global $DB;
        $rows = $DB->get_records('academy_course_access', array('courseid' => $courseid));
        $subids = array();
        $all = false;
        foreach ($rows as $r) {
            if ((int)$r->subscriptionid === self::ALL_SUBSCRIPTIONS) {
                $all = true;
            } else {
                $subids[] = (int)$r->subscriptionid;
            }
        }
        return array(
            'courseid'        => (int)$courseid,
            'mode'            => $all ? 'all' : (empty($subids) ? 'none' : 'specific'),
            'subscriptionids' => $subids,
        );
    }

    /**
     * The course ids a given subscription unlocks: courses granted to it specifically, plus any
     * course open to "all subscriptions".
     *
     * @param int $subscriptionid
     * @return int[] course ids
     */
    public static function courses_for_subscription($subscriptionid) {
        global $DB;
        $rows = $DB->get_records_select('academy_course_access',
            'subscriptionid = :sid OR subscriptionid = :all',
            array('sid' => $subscriptionid, 'all' => self::ALL_SUBSCRIPTIONS),
            '', 'DISTINCT courseid');
        return array_map('intval', array_keys($rows));
    }

    /**
     * The courses a subscription unlocks, as [{id, fullname}] for display.
     *
     * @param int $subscriptionid
     * @return array
     */
    public static function courses_detail($subscriptionid) {
        global $DB;
        $ids = self::courses_for_subscription($subscriptionid);
        if (empty($ids)) {
            return array();
        }
        list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select('course', "id $insql", $params, 'fullname ASC', 'id, fullname');
        $out = array();
        foreach ($rows as $c) {
            $out[] = array('id' => (int)$c->id, 'fullname' => format_string($c->fullname));
        }
        return $out;
    }

    // ── helpers ──

    /** Set a plan status (shared by activate/deactivate). */
    private static function set_status($id, $status, $userid) {
        global $DB;
        self::get_subscription($id); // throws if missing
        $update = new \stdClass();
        $update->id           = $id;
        $update->status       = $status;
        $update->timemodified = time();
        $update->usermodified = $userid;
        $DB->update_record('academy_subscriptions', $update);
    }

    /** Validate/normalize a status value. */
    private static function normalize_status($status) {
        $status = strtolower(trim((string)$status));
        if (!in_array($status, array(self::STATUS_ACTIVE, self::STATUS_INACTIVE), true)) {
            throw new \moodle_exception('err_status', 'local_academy');
        }
        return $status;
    }
}
