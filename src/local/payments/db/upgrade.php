<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_payments_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // Generalize local_payments_invoices so one invoice can represent a course,
    // package or subscription purchase (not just a course transaction).
    if ($oldversion < 2026071900) {
        $table = new xmldb_table('local_payments_invoices');

        // 1. New columns.
        $fields = [
            new xmldb_field('source_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'course', 'id'),
            new xmldb_field('source_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'source_type'),
            new xmldb_field('item_name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'invoice_number'),
            new xmldb_field('subtotal', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'item_name'),
            new xmldb_field('discount', XMLDB_TYPE_NUMBER, '10, 2', null, null, null, null, 'subtotal'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // 2. Drop the old hard FK on transaction_id FIRST — the column cannot be modified while an
        //    index (the FK's) depends on it.
        $oldkey = new xmldb_key('fk_transaction_id', XMLDB_KEY_FOREIGN, ['transaction_id'],
            'local_payments_transactions', ['id']);
        if (method_exists($dbman, 'find_key_name') && $dbman->find_key_name($table, $oldkey)) {
            $dbman->drop_key($table, $oldkey);
        }

        // 3. transaction_id becomes nullable (package/subscription direct purchases have none).
        $txnfield = new xmldb_field('transaction_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'source_id');
        if ($dbman->field_exists($table, $txnfield)) {
            $dbman->change_field_notnull($table, $txnfield);
        }

        // 4. Reclassify existing (legacy) invoices, which were all keyed to a transaction id.
        //    A course transaction stays a course invoice; a package/subscription transaction's
        //    invoice is removed so it can be re-created below keyed to the domain purchase id
        //    (correct source_type/source_id, item name, discount).
        $rs = $DB->get_recordset_select('local_payments_invoices', 'source_id = 0 AND transaction_id IS NOT NULL');
        foreach ($rs as $inv) {
            $txn = $DB->get_record('local_payments_transactions', ['id' => $inv->transaction_id], 'id, metadata');
            $itemtype = 'course';
            if ($txn) {
                $meta = json_decode($txn->metadata ?? '{}');
                $itemtype = $meta->item_type ?? 'course';
            }
            if ($itemtype === 'package' || $itemtype === 'subscription') {
                $DB->delete_records('local_payments_invoices', ['id' => $inv->id]);
            } else {
                $DB->set_field('local_payments_invoices', 'source_id', $inv->transaction_id, ['id' => $inv->id]);
            }
        }
        $rs->close();

        // 5. Now source_id is populated on all remaining rows: add the unique (source_type,
        //    source_id) index and a plain index on transaction_id.
        $srcindex = new xmldb_index('idx_source', XMLDB_INDEX_UNIQUE, ['source_type', 'source_id']);
        if (!$dbman->index_exists($table, $srcindex)) {
            $dbman->add_index($table, $srcindex);
        }
        $txnindex = new xmldb_index('idx_transaction_id', XMLDB_INDEX_NOTUNIQUE, ['transaction_id']);
        if (!$dbman->index_exists($table, $txnindex)) {
            $dbman->add_index($table, $txnindex);
        }

        // 6. Backfill invoices for existing package & subscription purchases, and any completed
        //    course transactions still missing one. The generator is idempotent, so this is safe.
        local_payments_backfill_invoices($dbman);

        upgrade_plugin_savepoint(true, 2026071900, 'local', 'payments');
    }

    // Point the post-payment redirect at the Next.js frontend so students land back on their own
    // site (e.g. /courses/12?payment=success) instead of the legacy Moodle result pages. Only set
    // it when the admin hasn't already configured a value, so a deliberate choice is never clobbered.
    if ($oldversion < 2026072100) {
        if (trim((string) get_config('local_payments', 'frontend_url')) === '') {
            set_config('frontend_url', 'https://academy2026.nitg-eg.com/nextjs-frontend-student', 'local_payments');
        }

        upgrade_plugin_savepoint(true, 2026072100, 'local', 'payments');
    }

    return true;
}

/**
 * Issue invoices for existing purchases that don't have one yet.
 */
function local_payments_backfill_invoices(\database_manager $dbman) {
    global $DB;

    $create = function (string $type, int $id) {
        try {
            \local_payments\invoice_generator::create($type, $id);
        } catch (\Throwable $e) {
            debugging('Invoice backfill failed (' . $type . ' ' . $id . '): ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    };

    if ($dbman->table_exists('academy_package_purchases')) {
        $rs = $DB->get_recordset('academy_package_purchases', null, '', 'id');
        foreach ($rs as $r) {
            $create(\local_payments\invoice_generator::SOURCE_PACKAGE, (int) $r->id);
        }
        $rs->close();
    }

    if ($dbman->table_exists('academy_sub_purchases')) {
        $rs = $DB->get_recordset('academy_sub_purchases', null, '', 'id');
        foreach ($rs as $r) {
            $create(\local_payments\invoice_generator::SOURCE_SUBSCRIPTION, (int) $r->id);
        }
        $rs->close();
    }

    // Completed COURSE transactions missing an invoice. Package/subscription transactions
    // are skipped here — their invoices are keyed to the domain purchase (handled above).
    $rs = $DB->get_recordset_select('local_payments_transactions', "status = 'completed'", null, '', 'id, metadata');
    foreach ($rs as $r) {
        $meta = json_decode($r->metadata ?? '{}');
        $itemtype = $meta->item_type ?? 'course';
        if ($itemtype === 'course') {
            $create(\local_payments\invoice_generator::SOURCE_COURSE, (int) $r->id);
        }
    }
    $rs->close();
}
