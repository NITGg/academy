<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_payments_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026070106) {
        // Add a "Payment history" entry to the user (avatar) dropdown menu so
        // students can reach their history from any page. This appends to the
        // core customusermenuitems setting, idempotently — existing items are
        // preserved and we never add a duplicate.
        local_payments_add_usermenu_item();
        upgrade_plugin_savepoint(true, 2026070106, 'local', 'payments');
    }

    if ($oldversion < 2026083000) {
        // Register the Fawaterk provider on sites that were installed before it
        // existed. It is seeded disabled; enable it from Manage providers.
        require_once(__DIR__ . '/install.php');
        local_payments_seed_fawaterk_provider();
        upgrade_plugin_savepoint(true, 2026083000, 'local', 'payments');
    }

    if ($oldversion < 2026083120) {
        // Refund requests: what a buyer asks for once the self-service window has
        // closed, and what staff decide about it.
        $table = new xmldb_table('local_payments_refund_reqs');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('transaction_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('quoted_amount', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
            $table->add_field('quoted_fee', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null);
            $table->add_field('currency', XMLDB_TYPE_CHAR, '3', null, null, null, null);
            $table->add_field('decided_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('decision_note', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('refund_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_transaction_id', XMLDB_KEY_FOREIGN, ['transaction_id'],
                'local_payments_transactions', ['id']);
            $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_index('idx_status_time', XMLDB_INDEX_NOTUNIQUE, ['status', 'timecreated']);
            $table->add_index('idx_txn_status', XMLDB_INDEX_NOTUNIQUE, ['transaction_id', 'status']);
            $table->add_index('idx_user_time', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timecreated']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026083120, 'local', 'payments');
    }

    if ($oldversion < 2026090100) {
        // Per-item refund policy. A flagship course can refuse automatic refunds
        // while everything else allows them, without that being a code change.
        $table = new xmldb_table('local_payments_refund_rules');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('itemtype', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL);
            $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('hours', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('feetype', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'percent');
            $table->add_field('feevalue', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('feecurrency', XMLDB_TYPE_CHAR, '3', null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_item', XMLDB_INDEX_UNIQUE, ['itemtype', 'itemid']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026090100, 'local', 'payments');
    }

    if ($oldversion < 2026090102) {
        // The refund fee belongs beside the price, because that is where the
        // currency already is. A flat fee stated anywhere else has to carry its
        // own currency and be skipped when it does not match; here it cannot
        // mismatch, because the row it sits on defines the currency.
        $table = new xmldb_table('local_payments_course_prices');
        $field = new xmldb_field('refund_fee', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null,
            'sale_price');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026090102, 'local', 'payments');
    }

    if ($oldversion < 2026090110) {
        // The refund window joins the fee on the price row. Keeping them apart
        // meant two screens for one policy, and the fee needed a currency field
        // that a price row already provides.
        $table = new xmldb_table('local_payments_course_prices');
        $field = new xmldb_field('refund_hours', XMLDB_TYPE_INTEGER, '10', null, null, null, null,
            'sale_price');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // The separate per-item override table is redundant now.
        $rules = new xmldb_table('local_payments_refund_rules');
        if ($dbman->table_exists($rules)) {
            $dbman->drop_table($rules);
        }

        upgrade_plugin_savepoint(true, 2026090110, 'local', 'payments');
    }

    return true;
}

/**
 * Append the Payment history link to customusermenuitems if not already present.
 */
function local_payments_add_usermenu_item() {
    $item = 'paymenthistory,local_payments|/local/payments/history.php';
    $current = (string) get_config('core', 'customusermenuitems');

    // Already there (match on the URL so a relabelled entry still counts).
    if (strpos($current, '/local/payments/history.php') !== false) {
        return;
    }

    $new = ($current === '') ? $item : rtrim($current, "\n") . "\n" . $item;
    set_config('customusermenuitems', $new);
}
