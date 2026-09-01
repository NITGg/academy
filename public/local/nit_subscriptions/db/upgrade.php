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
 * Upgrade steps for local_nit_subscriptions.
 *
 * @package    local_nit_subscriptions
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin database.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool
 */
function xmldb_local_nit_subscriptions_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026080901) {
        // Add the nit_sub_purchase table (subscription purchases via the payment gateway).
        $table = new xmldb_table('nit_sub_purchase');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('subscriptionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'normal');
        $table->add_field('seats', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('base_price', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('discount_percent', XMLDB_TYPE_NUMBER, '5, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('price_paid', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('duration_days', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
        $table->add_field('source', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'online');
        $table->add_field('reference', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('timeactivated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('expires_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('subscriptionid_fk', XMLDB_KEY_FOREIGN, ['subscriptionid'], 'nit_subscription', ['id']);
        $table->add_index('user_status_idx', XMLDB_INDEX_NOTUNIQUE, ['userid', 'status']);
        $table->add_index('reference_idx', XMLDB_INDEX_NOTUNIQUE, ['reference']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026080901, 'local', 'nit_subscriptions');
    }

    if ($oldversion < 2026082400) {
        // Per-country subscription pricing (mirror of local_payments course pricing).

        // 1. Base-price currency on the plan itself (the default price's currency).
        $table = new xmldb_table('nit_subscription');
        $field = new xmldb_field('currency', XMLDB_TYPE_CHAR, '3', null, XMLDB_NOTNULL, null, 'EGP', 'price');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // 2. Per-country price override table.
        $table = new xmldb_table('nit_sub_price');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('subscriptionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('country', XMLDB_TYPE_CHAR, '2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('currency', XMLDB_TYPE_CHAR, '3', null, XMLDB_NOTNULL, null, null);
        $table->add_field('price', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('is_active', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('created_by', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('subscriptionid_fk', XMLDB_KEY_FOREIGN, ['subscriptionid'], 'nit_subscription', ['id']);
        $table->add_index('sub_country_uk', XMLDB_INDEX_UNIQUE, ['subscriptionid', 'country']);
        $table->add_index('sub_country_active_idx', XMLDB_INDEX_NOTUNIQUE, ['subscriptionid', 'country', 'is_active']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026082400, 'local', 'nit_subscriptions');
    }

    if ($oldversion < 2026090110) {
        // Refund terms beside the price, matching how courses do it. Null means
        // "not set here", which is different from zero: zero is a deliberate
        // no-window or full refund.
        foreach ([
            ['nit_subscription', 'price'],
            ['nit_sub_price', 'price'],
        ] as [$tablename, $after]) {
            $table = new xmldb_table($tablename);

            $hours = new xmldb_field('refund_hours', XMLDB_TYPE_INTEGER, '10', null, null, null,
                null, $after);
            if (!$dbman->field_exists($table, $hours)) {
                $dbman->add_field($table, $hours);
            }

            $fee = new xmldb_field('refund_fee', XMLDB_TYPE_NUMBER, '10, 2', null, null, null,
                null, 'refund_hours');
            if (!$dbman->field_exists($table, $fee)) {
                $dbman->add_field($table, $fee);
            }
        }

        upgrade_plugin_savepoint(true, 2026090110, 'local', 'nit_subscriptions');
    }

    if ($oldversion < 2026090120) {
        // A fee is a percentage or a flat amount. A plan sells in several
        // currencies at once, so one flat number on the plan could never be
        // right for all of them — the per-country price rows carry their own.
        foreach (['nit_subscription', 'nit_sub_price'] as $tablename) {
            $table = new xmldb_table($tablename);
            $field = new xmldb_field('refund_feetype', XMLDB_TYPE_CHAR, '10', null, null, null,
                null, 'refund_hours');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }
        upgrade_plugin_savepoint(true, 2026090120, 'local', 'nit_subscriptions');
    }

    if ($oldversion < 2026090130) {
        // The fee is a percentage now, which is what lets one number sit on the
        // plan and stay right for every currency it sells in: it carries no
        // currency to mismatch, and it follows whatever was actually charged, so
        // a discount shrinks the fee with the price. That leaves the fee type and
        // the per-country refund columns with nothing to do.
        $plan = new xmldb_table('nit_subscription');
        $field = new xmldb_field('refund_feetype');
        if ($dbman->field_exists($plan, $field)) {
            $dbman->drop_field($plan, $field);
        }

        // Move any flat fee off the plan rather than reading it as a percentage,
        // where 150 EGP would silently become 150%.
        $DB->set_field('nit_subscription', 'refund_fee', null, []);

        $prices = new xmldb_table('nit_sub_price');
        foreach (['refund_hours', 'refund_feetype', 'refund_fee'] as $name) {
            $field = new xmldb_field($name);
            if ($dbman->field_exists($prices, $field)) {
                $dbman->drop_field($prices, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026090130, 'local', 'nit_subscriptions');
    }

    if ($oldversion < 2026090140) {
        // Expiry reminders. The row is the receipt: it exists only once a reminder has actually
        // gone out, which is what stops the hourly task re-sending the same warning every hour.
        // Keyed on the deadline as well as the lead time, so a renewal — which moves the
        // deadline — starts a fresh set of reminders rather than inheriting the old one's.
        $table = new xmldb_table('nit_sub_reminder');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('purchaseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('subscriptionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('days', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('expires_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('purchaseid_fk', XMLDB_KEY_FOREIGN, ['purchaseid'], 'nit_sub_purchase', ['id']);

            $table->add_index('purchase_days_expiry_uk', XMLDB_INDEX_UNIQUE, ['purchaseid', 'days', 'expires_at']);
            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            $dbman->create_table($table);
        }

        // The refund-percentage step above shipped as 2026090130 on one branch while this
        // reminder table shipped as 2026090130 on the other, so a site upgraded before the
        // merge recorded that savepoint having run only one of the two. Re-apply the column
        // drops here; they are guarded, so a site that already did them pays nothing.
        $plan = new xmldb_table('nit_subscription');
        $feetype = new xmldb_field('refund_feetype');
        if ($dbman->field_exists($plan, $feetype)) {
            // The column still being here is what tells us the fee is a flat amount, so
            // nulling it is right. On an already-migrated site it holds percentages to keep.
            $DB->set_field('nit_subscription', 'refund_fee', null, []);
            $dbman->drop_field($plan, $feetype);
        }

        $prices = new xmldb_table('nit_sub_price');
        foreach (['refund_hours', 'refund_feetype', 'refund_fee'] as $name) {
            $field = new xmldb_field($name);
            if ($dbman->field_exists($prices, $field)) {
                $dbman->drop_field($prices, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026090140, 'local', 'nit_subscriptions');
    }

    return true;
}
