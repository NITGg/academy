<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_academy.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool
 */
function xmldb_local_academy_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026062802) {

        // New balance/expiry fields on purchases.
        $table = new xmldb_table('academy_package_purchases');

        $field = new xmldb_field('remaining_flex', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'source');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('expires_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'remaining_flex');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('timeactivated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'expires_at');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('user_status_idx', XMLDB_INDEX_NOTUNIQUE, array('userid', 'status'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // New payments table.
        $payments = new xmldb_table('academy_payments');
        if (!$dbman->table_exists($payments)) {
            $payments->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $payments->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $payments->add_field('purchaseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $payments->add_field('packageid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $payments->add_field('amount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $payments->add_field('method', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'online');
            $payments->add_field('reference', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $payments->add_field('transaction_no', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $payments->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'success');
            $payments->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $payments->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $payments->add_key('purchaseid_fk', XMLDB_KEY_FOREIGN, array('purchaseid'), 'academy_package_purchases', array('id'));
            $payments->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, array('userid'));
            $payments->add_index('txn_idx', XMLDB_INDEX_UNIQUE, array('transaction_no'));
            $dbman->create_table($payments);
        }

        upgrade_plugin_savepoint(true, 2026062802, 'local', 'academy');
    }

    if ($oldversion < 2026062803) {

        // Teacher profiles.
        $t = new xmldb_table('academy_teacher_profiles');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('headline', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_field('bio', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $t->add_field('experience', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_field('photourl', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_field('rating', XMLDB_TYPE_NUMBER, '4, 2', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('approved', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $t->add_field('available', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_index('userid_uk', XMLDB_INDEX_UNIQUE, array('userid'));
            $dbman->create_table($t);
        }

        // Teacher subjects.
        $t = new xmldb_table('academy_teacher_subjects');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('teacherid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('subject', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $t->add_field('specialization', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_index('teacherid_idx', XMLDB_INDEX_NOTUNIQUE, array('teacherid'));
            $dbman->create_table($t);
        }

        // Teacher working hours.
        $t = new xmldb_table('academy_teacher_hours');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('teacherid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('dayofweek', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
            $t->add_field('starttime', XMLDB_TYPE_CHAR, '5', null, XMLDB_NOTNULL, null, null);
            $t->add_field('endtime', XMLDB_TYPE_CHAR, '5', null, XMLDB_NOTNULL, null, null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_index('teacherid_idx', XMLDB_INDEX_NOTUNIQUE, array('teacherid'));
            $dbman->create_table($t);
        }

        upgrade_plugin_savepoint(true, 2026062803, 'local', 'academy');
    }

    if ($oldversion < 2026062804) {

        // Flex reserve/consume tracking on purchases (install.xml had these but they were never migrated).
        $purchases = new xmldb_table('academy_package_purchases');
        $field = new xmldb_field('reserved_flex', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'remaining_flex');
        if (!$dbman->field_exists($purchases, $field)) {
            $dbman->add_field($purchases, $field);
        }
        $field = new xmldb_field('consumed_flex', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'reserved_flex');
        if (!$dbman->field_exists($purchases, $field)) {
            $dbman->add_field($purchases, $field);
        }

        // Lessons.
        $t = new xmldb_table('academy_lessons');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('teacherid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('subject', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $t->add_field('status', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'pending');
            $t->add_field('requested_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('confirmed_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '60');
            $t->add_field('note', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $t->add_field('reject_reason', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_field('cancel_reason', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_field('purchaseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('flex_state', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none');
            $t->add_field('actual_start', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('actual_end', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_index('student_status_idx', XMLDB_INDEX_NOTUNIQUE, array('studentid', 'status'));
            $t->add_index('teacher_status_idx', XMLDB_INDEX_NOTUNIQUE, array('teacherid', 'status'));
            $t->add_index('purchaseid_idx', XMLDB_INDEX_NOTUNIQUE, array('purchaseid'));
            $dbman->create_table($t);
        }

        // Lesson proposals (suggested / rescheduled times).
        $t = new xmldb_table('academy_lesson_proposals');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('lessonid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('proposedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('role', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('proposed_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('type', XMLDB_TYPE_CHAR, '12', null, XMLDB_NOTNULL, null, 'suggest');
            $t->add_field('status', XMLDB_TYPE_CHAR, '12', null, XMLDB_NOTNULL, null, 'pending');
            $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_key('lessonid_fk', XMLDB_KEY_FOREIGN, array('lessonid'), 'academy_lessons', array('id'));
            $t->add_index('lesson_status_idx', XMLDB_INDEX_NOTUNIQUE, array('lessonid', 'status'));
            $dbman->create_table($t);
        }

        // Flex ledger.
        $t = new xmldb_table('academy_flex_tx');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('purchaseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('lessonid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('type', XMLDB_TYPE_CHAR, '12', null, XMLDB_NOTNULL, null, null);
            $t->add_field('amount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('balance_before', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('balance_after', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('performedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('reason', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, array('userid'));
            $t->add_index('purchaseid_idx', XMLDB_INDEX_NOTUNIQUE, array('purchaseid'));
            $t->add_index('lessonid_idx', XMLDB_INDEX_NOTUNIQUE, array('lessonid'));
            $dbman->create_table($t);
        }

        upgrade_plugin_savepoint(true, 2026062804, 'local', 'academy');
    }

    if ($oldversion < 2026062805) {

        // Earnings (revenue split per consumed Flex).
        $t = new xmldb_table('academy_earnings');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('lessonid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('teacherid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('purchaseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('flex_value', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('teacher_amount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('platform_amount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('teacher_percent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('platform_percent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('status', XMLDB_TYPE_CHAR, '12', null, XMLDB_NOTNULL, null, 'active');
            $t->add_field('reverse_reason', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_field('reversedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timereversed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_key('lessonid_fk', XMLDB_KEY_FOREIGN, array('lessonid'), 'academy_lessons', array('id'));
            $t->add_index('teacher_status_idx', XMLDB_INDEX_NOTUNIQUE, array('teacherid', 'status'));
            $dbman->create_table($t);
        }

        // Withdrawals.
        $t = new xmldb_table('academy_withdrawals');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('teacherid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('amount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('method', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'bank');
            $t->add_field('account', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_field('reference', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_field('status', XMLDB_TYPE_CHAR, '12', null, XMLDB_NOTNULL, null, 'pending');
            $t->add_field('reason', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_field('processedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timeprocessed', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_index('teacher_status_idx', XMLDB_INDEX_NOTUNIQUE, array('teacherid', 'status'));
            $t->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, array('status'));
            $dbman->create_table($t);
        }

        upgrade_plugin_savepoint(true, 2026062805, 'local', 'academy');
    }

    if ($oldversion < 2026062806) {

        // Meeting-room link on lessons (US-LS-3-1): the live session + Jitsi course module created on start.
        $table = new xmldb_table('academy_lessons');

        $field = new xmldb_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'actual_end');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'sessionid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026062806, 'local', 'academy');
    }

    if ($oldversion < 2026062807) {

        // Lesson action audit trail (encrypted timestamps) — docs/specs/00-overview.md + US-AD-3-1.
        $t = new xmldb_table('academy_lesson_events');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('lessonid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('action', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
            $t->add_field('actorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('role', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'system');
            $t->add_field('time_enc', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            // FK on lessonid already provides the index — no separate lessonid_idx (avoids collision).
            $t->add_key('lessonid_fk', XMLDB_KEY_FOREIGN, array('lessonid'), 'academy_lessons', array('id'));
            $dbman->create_table($t);
        }

        upgrade_plugin_savepoint(true, 2026062807, 'local', 'academy');
    }

    if ($oldversion < 2026070100) {

        // Track when a package-expiry reminder was sent, so the daily task notifies each purchase once.
        $table = new xmldb_table('academy_package_purchases');
        $field = new xmldb_field('expiry_notified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timeactivated');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026070100, 'local', 'academy');
    }

    if ($oldversion < 2026070200) {

        // ── Subscriptions (course-access plans): US-AD-5-*, US-AD-6-1, US-SB-*. ──

        // Subscription plans.
        $t = new xmldb_table('academy_subscriptions');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $t->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $t->add_field('price', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('duration_days', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
            $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, array('status'));
            $dbman->create_table($t);
        }

        // Course access map (which subscription unlocks which course; subscriptionid 0 = all).
        $t = new xmldb_table('academy_course_access');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('subscriptionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_index('course_sub_uk', XMLDB_INDEX_UNIQUE, array('courseid', 'subscriptionid'));
            $t->add_index('subscriptionid_idx', XMLDB_INDEX_NOTUNIQUE, array('subscriptionid'));
            $dbman->create_table($t);
        }

        // Student subscription purchases.
        $t = new xmldb_table('academy_sub_purchases');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('subscriptionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('price_paid', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('duration_days', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
            $t->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'online');
            $t->add_field('timeactivated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('expires_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('expiry_notified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_key('subscriptionid_fk', XMLDB_KEY_FOREIGN, array('subscriptionid'), 'academy_subscriptions', array('id'));
            $t->add_index('user_status_idx', XMLDB_INDEX_NOTUNIQUE, array('userid', 'status'));
            $dbman->create_table($t);
        }

        // Subscription payments.
        $t = new xmldb_table('academy_sub_payments');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('purchaseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('subscriptionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('amount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('method', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'online');
            $t->add_field('reference', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $t->add_field('transaction_no', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null);
            $t->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'success');
            $t->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_key('purchaseid_fk', XMLDB_KEY_FOREIGN, array('purchaseid'), 'academy_sub_purchases', array('id'));
            $t->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, array('userid'));
            $t->add_index('txn_idx', XMLDB_INDEX_UNIQUE, array('transaction_no'));
            $dbman->create_table($t);
        }

        upgrade_plugin_savepoint(true, 2026070200, 'local', 'academy');
    }

    if ($oldversion < 2026070403) {

        // Teacher year levels (grade levels a teacher offers — e.g. Year 1, Grade 10, KG2).
        $t = new xmldb_table('academy_teacher_years');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id',        XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('teacherid', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null,           null);
            $t->add_field('year',      XMLDB_TYPE_CHAR,    '50',  null, XMLDB_NOTNULL, null,           null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_index('teacherid_idx', XMLDB_INDEX_NOTUNIQUE, array('teacherid'));
            $dbman->create_table($t);
        }

        upgrade_plugin_savepoint(true, 2026070403, 'local', 'academy');
    }

    if ($oldversion < 2026070406) {

        // Ensure academy_teacher_years exists (skipped if DB version jumped past 2026070403).
        $t = new xmldb_table('academy_teacher_years');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('teacherid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null,           null);
            $t->add_field('year',      XMLDB_TYPE_CHAR,    '50', null, XMLDB_NOTNULL, null,           null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_index('teacherid_idx', XMLDB_INDEX_NOTUNIQUE, array('teacherid'));
            $dbman->create_table($t);
        }

        upgrade_plugin_savepoint(true, 2026070406, 'local', 'academy');
    }

    if ($oldversion < 2026070407) {

        // Ensure academy_teacher_years exists (DB may have been stamped 2026070406 before the table was created).
        $t = new xmldb_table('academy_teacher_years');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('teacherid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null,           null);
            $t->add_field('year',      XMLDB_TYPE_CHAR,    '50', null, XMLDB_NOTNULL, null,           null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_index('teacherid_idx', XMLDB_INDEX_NOTUNIQUE, array('teacherid'));
            $dbman->create_table($t);
        }

        upgrade_plugin_savepoint(true, 2026070407, 'local', 'academy');
    }

    if ($oldversion < 2026070408) {

        // Add phone field to teacher profiles.
        $table = new xmldb_table('academy_teacher_profiles');
        $field = new xmldb_field('phone', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'available');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026070408, 'local', 'academy');
    }

    if ($oldversion < 2026070411) {

        // Per-question answers saved during an in-progress quiz attempt (mobile quiz API).
        $table = new xmldb_table('academy_quiz_answers');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('attemptid',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('questionid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('answer',       XMLDB_TYPE_TEXT,    null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_index('attempt_question_idx', XMLDB_INDEX_UNIQUE, array('attemptid', 'questionid'));
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, array('userid'));
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026070411, 'local', 'academy');
    }

    if ($oldversion < 2026070419) {

        // ── B2B subscriptions (seat-based, multi-user): US-B2B-1-*. ──

        // Flag a plan as B2B-purchasable.
        $table = new xmldb_table('academy_subscriptions');
        $field = new xmldb_field('b2b_enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // B2B snapshot columns on the purchase record (normal purchases keep the defaults).
        $table = new xmldb_table('academy_sub_purchases');
        $fields = array(
            new xmldb_field('type',             XMLDB_TYPE_CHAR,   '10',    null, XMLDB_NOTNULL, null, 'normal', 'userid'),
            new xmldb_field('seats',            XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL, null, '0',      'type'),
            new xmldb_field('base_price',       XMLDB_TYPE_NUMBER,  '10, 2', null, XMLDB_NOTNULL, null, '0',      'seats'),
            new xmldb_field('discount_percent', XMLDB_TYPE_NUMBER,  '5, 2',  null, XMLDB_NOTNULL, null, '0',      'base_price'),
        );
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Seat-capacity options per B2B plan (10/20/50 … each with its own discount %).
        $t = new xmldb_table('academy_sub_seat_options');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id',              XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('subscriptionid',  XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL, null, null);
            $t->add_field('seats',           XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL, null, '0');
            $t->add_field('discount_percent', XMLDB_TYPE_NUMBER, '5, 2',  null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timecreated',     XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL, null, null);
            $t->add_field('timemodified',    XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL, null, null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_key('subscriptionid_fk', XMLDB_KEY_FOREIGN, array('subscriptionid'), 'academy_subscriptions', array('id'));
            $t->add_index('sub_seats_uk', XMLDB_INDEX_UNIQUE, array('subscriptionid', 'seats'));
            $dbman->create_table($t);
        }

        // Invitation links (hashed token; a link never grants access directly).
        $t = new xmldb_table('academy_b2b_invitations');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('purchaseid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('b2b_admin_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('token_hash',   XMLDB_TYPE_CHAR,    '64', null, XMLDB_NOTNULL, null, null);
            // status: active | expired | disabled | revoked
            $t->add_field('status',       XMLDB_TYPE_CHAR,    '10', null, XMLDB_NOTNULL, null, 'active');
            $t->add_field('expires_at',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'); // 0 = never
            $t->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_key('purchaseid_fk', XMLDB_KEY_FOREIGN, array('purchaseid'), 'academy_sub_purchases', array('id'));
            $t->add_index('token_uk', XMLDB_INDEX_UNIQUE, array('token_hash'));
            $dbman->create_table($t);
        }

        // Membership records: one per (B2B subscription, user). consumes_seat is stored per record so
        // historical capacity is never recomputed from the current global seat-return setting.
        $t = new xmldb_table('academy_b2b_memberships');
        if (!$dbman->table_exists($t)) {
            $t->add_field('id',            XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $t->add_field('purchaseid',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('subscriptionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('b2b_admin_id',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('userid',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('invitationid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            // status: pending | approved | rejected | removed | expired
            $t->add_field('status',        XMLDB_TYPE_CHAR,    '10', null, XMLDB_NOTNULL, null, 'pending');
            $t->add_field('consumes_seat', XMLDB_TYPE_INTEGER, '1',  null, XMLDB_NOTNULL, null, '0');
            $t->add_field('reject_reason', XMLDB_TYPE_TEXT,    null, null, null, null, null);
            $t->add_field('approved_by',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('approved_at',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('removed_by',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('removed_at',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $t->add_field('timecreated',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_field('timemodified',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $t->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $t->add_key('purchaseid_fk', XMLDB_KEY_FOREIGN, array('purchaseid'), 'academy_sub_purchases', array('id'));
            $t->add_index('purchase_user_uk', XMLDB_INDEX_UNIQUE, array('purchaseid', 'userid'));
            $t->add_index('purchase_status_idx', XMLDB_INDEX_NOTUNIQUE, array('purchaseid', 'status'));
            $dbman->create_table($t);
        }

        upgrade_plugin_savepoint(true, 2026070419, 'local', 'academy');
    }

    if ($oldversion < 2026070420) {

        // Store the raw invite token (owner-only) alongside its hash, so an existing active link can
        // be re-displayed with a copy button instead of only being shown once at generation.
        $table = new xmldb_table('academy_b2b_invitations');
        $field = new xmldb_field('token', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'token_hash');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026070420, 'local', 'academy');
    }

    if ($oldversion < 2026071100) {

        // Coupons + Offers (Phase 1: US-AD-7-*, US-AD-8-*, US-US-CP-*, US-US-OF-*).
        // Six new tables. Created from their xmldb definitions so existing installs get them too;
        // fresh installs get the same tables from install.xml.

        // academy_coupons — coupon definitions.
        $table = new xmldb_table('academy_coupons');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('code', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
            $table->add_field('discount_type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'percent');
            $table->add_field('discount_value', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('max_discount', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
            $table->add_field('usage_type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'multiple');
            $table->add_field('usage_limit', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('startdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('enddate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_index('code_uk', XMLDB_INDEX_UNIQUE, array('code'));
            $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, array('status'));
            $dbman->create_table($table);
        }

        // academy_coupon_items — applicable types + scope.
        $table = new xmldb_table('academy_coupon_items');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('couponid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('item_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('item_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_index('coupon_type_item_idx', XMLDB_INDEX_NOTUNIQUE, array('couponid', 'item_type', 'item_id'));
            $table->add_index('type_item_idx', XMLDB_INDEX_NOTUNIQUE, array('item_type', 'item_id'));
            $dbman->create_table($table);
        }

        // academy_coupon_usages — redemption records.
        $table = new xmldb_table('academy_coupon_usages');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('couponid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('transactionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('item_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('item_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('original_amount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('discount_amount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('final_amount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_index('coupon_user_idx', XMLDB_INDEX_NOTUNIQUE, array('couponid', 'userid'));
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, array('userid'));
            $table->add_index('transactionid_idx', XMLDB_INDEX_NOTUNIQUE, array('transactionid'));
            $dbman->create_table($table);
        }

        // academy_offers — automatic offer definitions.
        $table = new xmldb_table('academy_offers');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('discount_type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'percent');
            $table->add_field('discount_value', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('startdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('enddate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, array('status'));
            $dbman->create_table($table);
        }

        // academy_offer_items — applicable types + scope.
        $table = new xmldb_table('academy_offer_items');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('offerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('item_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('item_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_index('offer_type_item_idx', XMLDB_INDEX_NOTUNIQUE, array('offerid', 'item_type', 'item_id'));
            $table->add_index('type_item_idx', XMLDB_INDEX_NOTUNIQUE, array('item_type', 'item_id'));
            $dbman->create_table($table);
        }

        // academy_offer_usages — application records.
        $table = new xmldb_table('academy_offer_usages');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('offerid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('transactionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('item_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
            $table->add_field('item_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('original_amount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('discount_amount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('final_amount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_index('offer_user_idx', XMLDB_INDEX_NOTUNIQUE, array('offerid', 'userid'));
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, array('userid'));
            $table->add_index('transactionid_idx', XMLDB_INDEX_NOTUNIQUE, array('transactionid'));
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026071100, 'local', 'academy');
    }

    if ($oldversion < 2026071900) {

        // academy_cert_rulesets — plugin-agnostic certificate eligibility rules per course.
        $table = new xmldb_table('academy_cert_rulesets');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('operator', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'and');
            $table->add_field('rules', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
            // NB: the courseid foreign key already indexes courseid; a separate index on the same
            // single column collides in Moodle's xmldb. (A unique index would also be wrong — the
            // model allows many certificates per course, see the 2026071901 step below.)
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026071900, 'local', 'academy');
    }

    if ($oldversion < 2026071901) {

        // Domain correction: eligibility rules belong to a CERTIFICATE, not a course. A course may
        // have many certificates (Completion, Attendance, Excellence, ...), each with its own rules.
        // academy_cert_rulesets (one-per-course) becomes academy_certificates (many-per-course).
        $old = new xmldb_table('academy_cert_rulesets');
        $new = new xmldb_table('academy_certificates');
        if ($dbman->table_exists($old) && !$dbman->table_exists($new)) {
            $dbman->rename_table($old, 'academy_certificates');
        }

        $table = new xmldb_table('academy_certificates');
        if (!$dbman->table_exists($table)) {
            // Fresh install path (no earlier ruleset table existed).
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
            $table->add_field('type', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'completion');
            $table->add_field('externalref', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('operator', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'and');
            $table->add_field('rules', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_key('courseid_fk', XMLDB_KEY_FOREIGN, array('courseid'), 'course', array('id'));
            $table->add_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, array('courseid'));
            $table->add_index('externalref_ix', XMLDB_INDEX_NOTUNIQUE, array('externalref'));
            $dbman->create_table($table);
        } else {
            // Renamed-from-ruleset path: add the new certificate columns.
            $field = new xmldb_field('type', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, 'completion', 'name');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            $field = new xmldb_field('externalref', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'type');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
            // Many certificates per course now — drop the unique(courseid) index, add a plain one.
            $uix = new xmldb_index('courseid_uix', XMLDB_INDEX_UNIQUE, array('courseid'));
            if ($dbman->index_exists($table, $uix)) {
                $dbman->drop_index($table, $uix);
            }
            $ix = new xmldb_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, array('courseid'));
            if (!$dbman->index_exists($table, $ix)) {
                $dbman->add_index($table, $ix);
            }
            $ix = new xmldb_index('externalref_ix', XMLDB_INDEX_NOTUNIQUE, array('externalref'));
            if (!$dbman->index_exists($table, $ix)) {
                $dbman->add_index($table, $ix);
            }
        }

        upgrade_plugin_savepoint(true, 2026071901, 'local', 'academy');
    }

    if ($oldversion < 2026072000) {
        // Paid programs: price for an enrol_programs program. Held here, not in the third-party
        // plugin, so its files stay untouched and a plugin update cannot wipe the pricing.
        $table = new xmldb_table('academy_program_prices');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('price', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('currency', XMLDB_TYPE_CHAR, '3', null, XMLDB_NOTNULL, null, 'EGP');
            $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            // One price per program — the unique index is what makes "set price" safely idempotent.
            $table->add_index('programid_uix', XMLDB_INDEX_UNIQUE, array('programid'));
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026072000, 'local', 'academy');
    }

    if ($oldversion < 2026072001) {
        // Tracks which enrol_programs allocations already got a program-expiry reminder. Held here,
        // not as a column on enrol_programs_allocations, so the third-party plugin's tables stay
        // untouched (same reasoning as academy_program_prices above).
        $table = new xmldb_table('academy_program_notif');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('allocationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timenotified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
            $table->add_index('allocationid_uix', XMLDB_INDEX_UNIQUE, array('allocationid'));
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026072001, 'local', 'academy');
    }

    if ($oldversion < 2026072002) {
        // Program certificates: a certificate can now be scoped to an enrol_programs program
        // (programid) instead of a course. Exactly one of courseid/programid is set; the eligibility
        // engine passes the matching scope id to the rules.
        $table = new xmldb_table('academy_certificates');

        $field = new xmldb_field('programid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // courseid is now 0 for program-scoped certificates, so it can no longer carry a NOTNULL
        // default-less value; give it a default of 0 to match install.xml.
        $courseid = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
        if ($dbman->field_exists($table, $courseid)) {
            $dbman->change_field_default($table, $courseid);
        }

        $index = new xmldb_index('programid_ix', XMLDB_INDEX_NOTUNIQUE, array('programid'));
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026072002, 'local', 'academy');
    }

    return true;
}
