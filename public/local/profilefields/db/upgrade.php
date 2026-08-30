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
 * Upgrade steps for local_profilefields.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Run the plugin upgrade steps.
 *
 * @param int $oldversion the version we are upgrading from
 * @return bool
 */
function xmldb_local_profilefields_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026082402) {
        // Repair any recommended datetime field created without valid year
        // parameters, which otherwise makes every profile edit page fatal.
        \local_profilefields\provision::repair();

        upgrade_plugin_savepoint(true, 2026082402, 'local', 'profilefields');
    }

    if ($oldversion < 2026082403) {
        // Also collapse any {mlang} field names that leaked onto a site without a
        // multilang filter to render them.
        \local_profilefields\provision::repair();

        upgrade_plugin_savepoint(true, 2026082403, 'local', 'profilefields');
    }

    if ($oldversion < 2026082404) {
        // The first provisioning run aborted before setting the default sign-up
        // order (a since-fixed fatal in the cache purge), so set it now if the admin
        // has not arranged the fields themselves.
        \local_profilefields\provision::ensure_signup_order();

        upgrade_plugin_savepoint(true, 2026082404, 'local', 'profilefields');
    }

    if ($oldversion < 2026082600) {
        // Switch the completion gate on. Accounts created outside the sign-up form
        // (a Google login) skipped every field this plugin manages, which among
        // other things leaves `country` empty - and local_payments prices on it.
        // Off by default in the class so a fresh install never locks itself out;
        // an existing site that already relies on these fields wants it on.
        set_config(\local_profilefields\completion::SETTING, 1, \local_profilefields\manager::COMPONENT);

        upgrade_plugin_savepoint(true, 2026082600, 'local', 'profilefields');
    }

    if ($oldversion < 2026082900) {
        // The plugin had no tables of its own until now, so these are created here
        // rather than by install.xml on every existing site.
        $table = new xmldb_table('local_profilefields_log');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('ip', XMLDB_TYPE_CHAR, '45', null, XMLDB_NOTNULL, null, '');
        $table->add_field('declared', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, '');
        $table->add_field('detected', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, '');
        $table->add_field('reason', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, '');
        $table->add_field('origin', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'signup');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        $table->add_index('ip_idx', XMLDB_INDEX_NOTUNIQUE, ['ip']);
        $table->add_index('reason_idx', XMLDB_INDEX_NOTUNIQUE, ['reason']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_profilefields_ip');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ip', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('note', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('ip_uix', XMLDB_INDEX_UNIQUE, ['ip']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Make the "refuse an address we cannot place" rule explicit rather than
        // leaving it to the class default, so it shows as set on the register tab.
        set_config('blockunresolvedip', 1, \local_profilefields\manager::COMPONENT);

        upgrade_plugin_savepoint(true, 2026082900, 'local', 'profilefields');
    }


    if ($oldversion < 2026083000) {
        $component = \local_profilefields\manager::COMPONENT;

        // AC-4.6.6: the mirror image of the deny list - addresses that skip the
        // location check entirely, for the academy's own offices and for testing.
        $table = new xmldb_table('local_profilefields_allow');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('ip', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('note', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('ip_uix', XMLDB_INDEX_UNIQUE, ['ip']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // AC-4.3.5: one row per device a learner has asked us to trust. The
        // selector is looked up, the validator is compared as a hash - see
        // local_profilefields\rememberme for why the two are separate.
        $table = new xmldb_table('local_profilefields_remember');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('selector', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, '');
        $table->add_field('validator', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
        $table->add_field('useragent', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
        $table->add_field('lastip', XMLDB_TYPE_CHAR, '45', null, XMLDB_NOTNULL, null, '');
        $table->add_field('expires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('selector_uix', XMLDB_INDEX_UNIQUE, ['selector']);
        $table->add_index('expires_idx', XMLDB_INDEX_NOTUNIQUE, ['expires']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // AC-4.5.4: requests to change a field only an administrator may set, and
        // the audit trail of what was decided about each one.
        $table = new xmldb_table('local_profilefields_request');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('field', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, '');
        $table->add_field('oldvalue', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('newvalue', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('reason', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('decidedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('decisionnote', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timedecided', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // The chapter-4 defaults, written explicitly so that the settings page
        // shows them as set rather than silently falling back to a constant.
        set_config('gatebuttons', 1, $component);
        set_config('remembermeenabled', 1, $component);
        set_config('remembermedays', 30, $component);
        set_config('linkttlhours', 24, $component);
        set_config('resendcooldown', 60, $component);
        set_config('resendmax', 5, $component);

        // Core settings the specification names values for. Set once, on upgrade,
        // and never re-enforced: an administrator who deliberately changes one of
        // these later has to be allowed to keep their change.
        //
        // The password block needs explaining. AC-4.1.6 wants one message naming
        // the one rule that was broken, in the specification's own words. Moodle
        // prints one message per broken rule in its own words. Both cannot be true
        // at once, so the four minimums are zeroed and the whole rule set moves to
        // local_profilefields_check_password_policy(), which core calls from the
        // same place it would have applied these. The policy stays switched *on*,
        // because that flag is also what makes core call plugins at all.
        //
        // The consequence to be aware of: with this plugin disabled the site has no
        // password policy. That is the price of not showing the learner two
        // contradictory messages, and this plugin is not optional on this site.
        set_config('passwordpolicy', 1);
        set_config('minpasswordlength', 0);
        set_config('minpassworddigits', 0);
        set_config('minpasswordlower', 0);
        set_config('minpasswordupper', 0);
        set_config('minpasswordnonalphanum', 0);

        // AC-4.3.2. Moodle's default threshold is 0, which means no lock-out at
        // all, and it already emails the account holder an unlock link as soon as
        // one is applied - so these three settings are the whole requirement.
        set_config('lockoutthreshold', 5);
        set_config('lockoutduration', 15 * MINSECS);

        // AC-4.3.5's shorter half. The 30-day half cannot be a session setting at
        // all - see local_profilefields\rememberme for why - and is the token.
        set_config('sessiontimeout', 24 * HOURSECS);

        // AC-4.4.6: "may not be identical to the previous password". Core already
        // implements this in both the reset and change-password forms, but only
        // when it is told how many previous passwords to remember; the default of
        // zero means the check never runs.
        set_config('passwordreuselimit', 1);

        // AC-4.4.7: "all existing sessions for that account are terminated". Core
        // does this on a password change only when this flag is set, or when the
        // user happens to tick a box.
        set_config('passwordchangelogout', 1);

        upgrade_plugin_savepoint(true, 2026083000, 'local', 'profilefields');
    }

    return true;
}
