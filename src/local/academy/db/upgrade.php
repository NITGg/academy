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

    return true;
}
